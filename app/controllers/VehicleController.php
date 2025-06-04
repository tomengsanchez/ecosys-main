<?php

/**
 * VehicleController
 *
 * Handles operations related to the Vehicle module, part of Open Office.
 * This controller will manage CRUD operations for vehicles.
 */
class VehicleController {
    private $pdo;
    private $vehicleModel; // For vehicle-specific operations
    private $userModel;    // For fetching user details (e.g., for 'created by' or 'driver')

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // It's good practice to ensure VehicleModel extends BaseObjectModel
        // and BaseObjectModel is correctly loaded by the autoloader.
        $this->vehicleModel = new VehicleModel($this->pdo);
        $this->userModel = new UserModel($this->pdo);

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
    }

    /**
     * Display the list of vehicles.
     * This page will use DataTables with server-side processing.
     * Protected by VIEW_VEHICLES capability.
     */
    public function index() {
        if (!userHasCapability('VIEW_VEHICLES')) {
            $_SESSION['error_message'] = "You do not have permission to view vehicles.";
            redirect('dashboard'); // Or an appropriate Open Office dashboard
        }

        $data = [
            'pageTitle' => 'Manage Vehicles',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'], // Link to a general Open Office page
                ['label' => 'Vehicles']
            ],
            // Vehicle statuses for a potential filter dropdown in the view
            'vehicle_statuses' => ['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance', 'booked' => 'Booked']
        ];
        // The actual vehicle data will be loaded by DataTables via an AJAX call
        // to ajaxGetVehicles().
        $this->view('openoffice/vehicles_list', $data);
    }

    /**
     * AJAX handler for fetching vehicle data for DataTables (Server-Side Processing).
     * Responds with JSON formatted data.
     */
    public function ajaxGetVehicles() {
        header('Content-Type: application/json');

        if (!userHasCapability('VIEW_VEHICLES')) {
            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Permission denied."
            ]);
            exit;
        }

        $draw = intval($_POST['draw'] ?? 0);
        $start = intval($_POST['start'] ?? 0); // Offset
        $length = intval($_POST['length'] ?? 10); // Limit

        // Server-side search
        $searchValue = $_POST['search']['value'] ?? '';
        
        // Server-side ordering
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 0; // Index of the sorting column
        $orderColumnName = $_POST['columns'][$orderColumnIndex]['data'] ?? 'object_title'; // Get name from 'data' attribute
        $orderDir = $_POST['order'][0]['dir'] ?? 'asc'; // asc or desc

        // Map DataTables column names to actual database column names for ordering
        $columnMapping = [
            'object_id' => 'o.object_id',
            'vehicle_name' => 'o.object_title', 
            'plate_number' => 'meta_vehicle_plate_number.meta_value', 
            'make_model' => 'o.object_title', // Simplified, could be more complex if sorting by combined make and model
            'capacity' => 'meta_vehicle_capacity.meta_value', // Assuming capacity is a meta field and needs a JOIN for sorting
            'status_raw' => 'o.object_status', // For sorting by raw status
            'last_modified' => 'o.object_modified'
        ];
        
        $orderByDb = $columnMapping[$orderColumnName] ?? 'o.object_title';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $conditions = []; 
        // Example filter:
        // $filterStatus = $_POST['columns'][5]['search']['value'] ?? ''; // Assuming status is the 6th column (index 5)
        // if (!empty($filterStatus)) {
        //    $conditions['o.object_status'] = $filterStatus;
        // }


        $args = [
            'orderby' => $orderByDb,
            'orderdir' => $orderDir,
            'limit' => $length,
            'offset' => $start,
            'include_meta' => true 
        ];
        
        $vehicles = $this->vehicleModel->getObjectsByConditions('vehicle', $conditions, $args, $searchValue);
        $totalRecords = $this->vehicleModel->countObjectsByConditions('vehicle', []);
        $totalFilteredRecords = $this->vehicleModel->countObjectsByConditions('vehicle', $conditions, $searchValue);

        $dataOutput = []; // Renamed to avoid conflict with $data from controller method params
        if ($vehicles) {
            foreach ($vehicles as $vehicle) {
                // Actions are now built client-side in vehicles_list.php for better capability handling there.
                // We only need to provide the raw data.

                $statusKey = $vehicle['object_status'] ?? 'unknown';
                $statusLabel = ucfirst($statusKey);
                $badgeClass = 'bg-secondary';
                if ($statusKey === 'available') $badgeClass = 'bg-success';
                else if ($statusKey === 'maintenance') $badgeClass = 'bg-warning text-dark';
                else if ($statusKey === 'unavailable' || $statusKey === 'booked') $badgeClass = 'bg-danger';
                $statusHtml = "<span class=\"badge {$badgeClass}\">" . htmlspecialchars($statusLabel) . "</span>";

                $dataOutput[] = [
                    'object_id' => htmlspecialchars($vehicle['object_id']),
                    'vehicle_name' => htmlspecialchars($vehicle['object_title']),
                    'plate_number' => htmlspecialchars($vehicle['meta']['vehicle_plate_number'] ?? 'N/A'),
                    'make_model' => htmlspecialchars(($vehicle['meta']['vehicle_make'] ?? '') . ' ' . ($vehicle['meta']['vehicle_model'] ?? '')),
                    'capacity' => htmlspecialchars($vehicle['meta']['vehicle_capacity'] ?? 'N/A'),
                    'status' => $statusHtml, // This is the HTML badge for display
                    'status_raw' => $statusKey, // CRITICAL: Raw status key for JS logic (e.g., 'available')
                    'last_modified' => htmlspecialchars(format_datetime_for_display($vehicle['object_modified'])),
                    // 'actions' field is no longer needed here as it's built client-side
                ];
            }
        }

        $response = [
            "draw" => $draw,
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalFilteredRecords,
            "data" => $dataOutput // Use the renamed array
        ];

        echo json_encode($response);
        exit;
    }

    /**
     * Display form to add a new vehicle OR process adding a new vehicle.
     * Protected by CREATE_VEHICLES capability.
     */
    public function add() {
        if (!userHasCapability('CREATE_VEHICLES')) {
            $_SESSION['error_message'] = 'You do not have permission to add new vehicles.';
            redirect('vehicle'); 
        }

        $commonData = [
            'pageTitle' => 'Add New Vehicle',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Vehicles', 'url' => 'vehicle'],
                ['label' => 'Add Vehicle']
            ],
            'vehicle_statuses' => ['available' => 'Available', 'maintenance' => 'Maintenance', 'unavailable' => 'Unavailable'], // Exclude 'booked' for manual setting
            'vehicle_types' => ['Van' => 'Van', 'Sedan' => 'Sedan', 'SUV' => 'SUV', 'MPV' => 'MPV', 'Truck' => 'Truck', 'Motorcycle' => 'Motorcycle', 'Other' => 'Other'],
            'fuel_types' => ['Gasoline' => 'Gasoline', 'Diesel' => 'Diesel', 'Electric' => 'Electric', 'Hybrid' => 'Hybrid', 'Other' => 'Other']
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            $formData = [
                'object_title' => trim($_POST['object_title'] ?? ''),
                'object_content' => trim($_POST['object_content'] ?? ''), 
                'object_status' => trim($_POST['object_status'] ?? 'available'),
                'meta_fields' => [
                    'vehicle_plate_number' => strtoupper(trim($_POST['vehicle_plate_number'] ?? '')),
                    'vehicle_make' => trim($_POST['vehicle_make'] ?? ''),
                    'vehicle_model' => trim($_POST['vehicle_model'] ?? ''),
                    'vehicle_year' => filter_var(trim($_POST['vehicle_year'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
                    'vehicle_capacity' => filter_var(trim($_POST['vehicle_capacity'] ?? '0'), FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
                    'vehicle_type' => trim($_POST['vehicle_type'] ?? ''),
                    'vehicle_fuel_type' => trim($_POST['vehicle_fuel_type'] ?? ''),
                    'vehicle_notes' => trim($_POST['vehicle_notes'] ?? '') 
                ],
                'errors' => []
            ];
            $data = array_merge($commonData, $formData); // $data for the view

            // Validation
            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Vehicle Name/Identifier is required.';
            if (empty($data['meta_fields']['vehicle_plate_number'])) $data['errors']['vehicle_plate_number_err'] = 'Plate Number is required.';
            if ($data['meta_fields']['vehicle_year'] === null && !empty(trim($_POST['vehicle_year'] ?? ''))) $data['errors']['vehicle_year_err'] = 'Year must be a valid number.';
            elseif ($data['meta_fields']['vehicle_year'] !== null && ($data['meta_fields']['vehicle_year'] < 1900 || $data['meta_fields']['vehicle_year'] > (int)date('Y') + 2)) {
                $data['errors']['vehicle_year_err'] = 'Please enter a valid year.';
            }
            if ($data['meta_fields']['vehicle_capacity'] === null && !empty(trim($_POST['vehicle_capacity'] ?? ''))) $data['errors']['vehicle_capacity_err'] = 'Capacity must be a valid number.';
            elseif ($data['meta_fields']['vehicle_capacity'] !== null && $data['meta_fields']['vehicle_capacity'] < 0) {
                $data['errors']['vehicle_capacity_err'] = 'Capacity must be a non-negative number.';
            }
            if (!array_key_exists($data['object_status'], $data['vehicle_statuses'])) {
                $data['errors']['object_status_err'] = 'Invalid vehicle status selected.';
            }
            if (!empty($data['meta_fields']['vehicle_type']) && !array_key_exists($data['meta_fields']['vehicle_type'], $data['vehicle_types'])) {
                $data['errors']['vehicle_type_err'] = 'Invalid vehicle type selected.';
            }
            if (!empty($data['meta_fields']['vehicle_fuel_type']) && !array_key_exists($data['meta_fields']['vehicle_fuel_type'], $data['fuel_types'])) {
                $data['errors']['vehicle_fuel_type_err'] = 'Invalid fuel type selected.';
            }


            if (empty($data['errors'])) {
                $vehicleDataToSave = [
                    'object_author' => $_SESSION['user_id'],
                    'object_title' => $data['object_title'],
                    'object_content' => $data['object_content'], 
                    'object_status' => $data['object_status'],
                    'meta_fields' => $data['meta_fields']
                ];

                $vehicleId = $this->vehicleModel->createVehicle($vehicleDataToSave);

                if ($vehicleId) {
                    $_SESSION['message'] = 'Vehicle added successfully!';
                    redirect('vehicle');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not add vehicle.';
                    $this->view('openoffice/vehicle_form', $data);
                }
            } else {
                $this->view('openoffice/vehicle_form', $data);
            }
        } else {
            // Initial form display
            $data = array_merge($commonData, [ // $data for the view
                'object_title' => '', 'object_content' => '', 'object_status' => 'available',
                'meta_fields' => [
                    'vehicle_plate_number' => '', 'vehicle_make' => '', 'vehicle_model' => '',
                    'vehicle_year' => '', 'vehicle_capacity' => '', 'vehicle_type' => '',
                    'vehicle_fuel_type' => '', 'vehicle_notes' => ''
                ],
                'errors' => []
            ]);
            $this->view('openoffice/vehicle_form', $data);
        }
    }

    /**
     * Display form to edit an existing vehicle OR process updating an existing vehicle.
     * Protected by EDIT_VEHICLES capability.
     */
    public function edit($vehicleId = null) {
        if (!userHasCapability('EDIT_VEHICLES')) {
            $_SESSION['error_message'] = 'You do not have permission to edit vehicles.';
            redirect('vehicle');
        }
        
        if ($vehicleId === null) {
            redirect('vehicle'); 
        }
        $vehicleId = (int)$vehicleId;
        $vehicle = $this->vehicleModel->getVehicleById($vehicleId);

        if (!$vehicle) {
            $_SESSION['error_message'] = 'Vehicle not found.';
            redirect('vehicle');
        }

        $commonData = [
            'pageTitle' => 'Edit Vehicle: ' . htmlspecialchars($vehicle['object_title']),
            'vehicle_id' => $vehicleId, 
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Vehicles', 'url' => 'vehicle'],
                ['label' => 'Edit Vehicle']
            ],
            'vehicle_statuses' => ['available' => 'Available', 'maintenance' => 'Maintenance', 'unavailable' => 'Unavailable', 'booked' => 'Booked'],
            'vehicle_types' => ['Van' => 'Van', 'Sedan' => 'Sedan', 'SUV' => 'SUV', 'MPV' => 'MPV', 'Truck' => 'Truck', 'Motorcycle' => 'Motorcycle', 'Other' => 'Other'],
            'fuel_types' => ['Gasoline' => 'Gasoline', 'Diesel' => 'Diesel', 'Electric' => 'Electric', 'Hybrid' => 'Hybrid', 'Other' => 'Other']
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'object_title' => trim($_POST['object_title'] ?? $vehicle['object_title']),
                'object_content' => trim($_POST['object_content'] ?? $vehicle['object_content']),
                'object_status' => trim($_POST['object_status'] ?? $vehicle['object_status']),
                'meta_fields' => [
                    'vehicle_plate_number' => strtoupper(trim($_POST['vehicle_plate_number'] ?? ($vehicle['meta']['vehicle_plate_number'] ?? ''))),
                    'vehicle_make' => trim($_POST['vehicle_make'] ?? ($vehicle['meta']['vehicle_make'] ?? '')),
                    'vehicle_model' => trim($_POST['vehicle_model'] ?? ($vehicle['meta']['vehicle_model'] ?? '')),
                    'vehicle_year' => filter_var(trim($_POST['vehicle_year'] ?? ($vehicle['meta']['vehicle_year'] ?? '')), FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
                    'vehicle_capacity' => filter_var(trim($_POST['vehicle_capacity'] ?? ($vehicle['meta']['vehicle_capacity'] ?? '0')), FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
                    'vehicle_type' => trim($_POST['vehicle_type'] ?? ($vehicle['meta']['vehicle_type'] ?? '')),
                    'vehicle_fuel_type' => trim($_POST['vehicle_fuel_type'] ?? ($vehicle['meta']['vehicle_fuel_type'] ?? '')),
                    'vehicle_notes' => trim($_POST['vehicle_notes'] ?? ($vehicle['meta']['vehicle_notes'] ?? ''))
                ],
                'errors' => []
            ];
            $data = array_merge($commonData, $formData); // $data for the view
            $data['original_vehicle_data'] = $vehicle; 

            // Validation
            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Vehicle Name/Identifier is required.';
            if (empty($data['meta_fields']['vehicle_plate_number'])) $data['errors']['vehicle_plate_number_err'] = 'Plate Number is required.';
            if ($data['meta_fields']['vehicle_year'] === null && !empty(trim($_POST['vehicle_year'] ?? ($vehicle['meta']['vehicle_year'] ?? '')))) $data['errors']['vehicle_year_err'] = 'Year must be a valid number.';
            elseif ($data['meta_fields']['vehicle_year'] !== null && ($data['meta_fields']['vehicle_year'] < 1900 || $data['meta_fields']['vehicle_year'] > (int)date('Y') + 2)) {
                $data['errors']['vehicle_year_err'] = 'Please enter a valid year.';
            }
            if ($data['meta_fields']['vehicle_capacity'] === null && !empty(trim($_POST['vehicle_capacity'] ?? ($vehicle['meta']['vehicle_capacity'] ?? '')))) $data['errors']['vehicle_capacity_err'] = 'Capacity must be a valid number.';
            elseif ($data['meta_fields']['vehicle_capacity'] !== null && $data['meta_fields']['vehicle_capacity'] < 0) {
                $data['errors']['vehicle_capacity_err'] = 'Capacity must be a non-negative number.';
            }
            if (!array_key_exists($data['object_status'], $data['vehicle_statuses'])) {
                $data['errors']['object_status_err'] = 'Invalid vehicle status selected.';
            }
            if (!empty($data['meta_fields']['vehicle_type']) && !array_key_exists($data['meta_fields']['vehicle_type'], $data['vehicle_types'])) {
                $data['errors']['vehicle_type_err'] = 'Invalid vehicle type selected.';
            }
            if (!empty($data['meta_fields']['vehicle_fuel_type']) && !array_key_exists($data['meta_fields']['vehicle_fuel_type'], $data['fuel_types'])) {
                $data['errors']['vehicle_fuel_type_err'] = 'Invalid fuel type selected.';
            }

            if (empty($data['errors'])) {
                $vehicleDataToUpdate = [
                    'object_title' => $data['object_title'],
                    'object_content' => $data['object_content'],
                    'object_status' => $data['object_status'],
                    'meta_fields' => $data['meta_fields']
                ];

                if ($this->vehicleModel->updateVehicle($vehicleId, $vehicleDataToUpdate)) {
                    $_SESSION['message'] = 'Vehicle updated successfully!';
                    redirect('vehicle');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not update vehicle.';
                    $this->view('openoffice/vehicle_form', $data);
                }
            } else {
                $this->view('openoffice/vehicle_form', $data);
            }
        } else {
            // Initial form display for editing
            $data = array_merge($commonData, [ // $data for the view
                'object_title' => $vehicle['object_title'],
                'object_content' => $vehicle['object_content'],
                'object_status' => $vehicle['object_status'],
                'meta_fields' => [
                    'vehicle_plate_number' => $vehicle['meta']['vehicle_plate_number'] ?? '',
                    'vehicle_make' => $vehicle['meta']['vehicle_make'] ?? '',
                    'vehicle_model' => $vehicle['meta']['vehicle_model'] ?? '',
                    'vehicle_year' => $vehicle['meta']['vehicle_year'] ?? '',
                    'vehicle_capacity' => $vehicle['meta']['vehicle_capacity'] ?? '',
                    'vehicle_type' => $vehicle['meta']['vehicle_type'] ?? '',
                    'vehicle_fuel_type' => $vehicle['meta']['vehicle_fuel_type'] ?? '',
                    'vehicle_notes' => $vehicle['meta']['vehicle_notes'] ?? ''
                ],
                'errors' => [],
                'original_vehicle_data' => $vehicle 
            ]);
            $this->view('openoffice/vehicle_form', $data);
        }
    }

    /**
     * Process deleting a vehicle.
     * Protected by DELETE_VEHICLES capability.
     */
    public function delete($vehicleId = null) {
        if (!userHasCapability('DELETE_VEHICLES')) {
            $_SESSION['error_message'] = 'You do not have permission to delete vehicles.';
            redirect('vehicle');
        }

        if ($vehicleId === null) {
            $_SESSION['error_message'] = 'No vehicle ID specified for deletion.';
            redirect('vehicle');
        }
        $vehicleId = (int)$vehicleId;
        $vehicle = $this->vehicleModel->getVehicleById($vehicleId);

        if (!$vehicle) {
            $_SESSION['error_message'] = 'Vehicle not found.';
            redirect('vehicle');
        }

        // Check for existing 'vehicle_reservation' objects linked to this vehicle
        // This requires the ReservationModel to be able to query by object_parent and object_type
        $reservationModel = new ReservationModel($this->pdo); // Or get from constructor if used elsewhere
        $existingReservations = $reservationModel->getReservationsByParentId($vehicleId, 'vehicle_reservation', [], ['limit' => 1]);

        if (!empty($existingReservations)) {
            $_SESSION['error_message'] = 'Error: Cannot delete vehicle "' . htmlspecialchars($vehicle['object_title']) . '". It has existing reservation requests. Please manage or delete them first.';
            redirect('vehicle');
            return;
        }


        if ($this->vehicleModel->deleteVehicle($vehicleId)) {
            $_SESSION['message'] = 'Vehicle "' . htmlspecialchars($vehicle['object_title']) . '" deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Error: Could not delete vehicle "' . htmlspecialchars($vehicle['object_title']) . '".';
        }
        redirect('vehicle');
    }


    /**
     * Load a view file.
     * Ensures that the view file exists within the 'app/views/' directory.
     *
     * @param string $view The path to the view file (e.g., 'openoffice/vehicles_list').
     * @param array $data Data to extract and make available to the view.
     */
    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data); 
            require_once $viewFile;
        } else {
            error_log("VehicleController: View file not found: {$viewFile}");
            die('Error: View not found (' . htmlspecialchars($view) . '). Please contact support.');
        }
    }
}
