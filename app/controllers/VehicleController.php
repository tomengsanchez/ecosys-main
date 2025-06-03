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
        // This mapping is crucial because DataTables sends 'data' names, which might not be direct DB columns.
        $columnMapping = [
            'object_id' => 'o.object_id',
            'vehicle_name' => 'o.object_title', // Assuming 'vehicle_name' is 'object_title'
            'plate_number' => 'meta_vehicle_plate_number.meta_value', // Example if sorting by meta
            'make_model' => 'o.object_title', // Simplified, could be more complex
            'status' => 'o.object_status',
            'last_modified' => 'o.object_modified'
        ];
        // Ensure the order column is valid and map it, default to object_title
        $orderByDb = $columnMapping[$orderColumnName] ?? 'o.object_title';
        // Basic security: whitelist allowed sort directions
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';


        $conditions = []; // Add any fixed conditions if necessary
        // Example: if you add a status filter dropdown to your DataTables UI
        // $filterStatus = $_POST['filterStatus'] ?? ''; // Assuming you add custom filter data
        // if (!empty($filterStatus)) {
        //     $conditions['o.object_status'] = $filterStatus;
        // }

        $args = [
            'orderby' => $orderByDb,
            'orderdir' => $orderDir,
            'limit' => $length,
            'offset' => $start,
            'include_meta' => true // We need meta fields
        ];
        
        // Fetch paginated and sorted/searched data
        // The BaseObjectModel's getObjectsByConditions needs to correctly handle $searchValue
        // for object_title, object_content, and potentially meta fields if complex search is needed.
        $vehicles = $this->vehicleModel->getObjectsByConditions('vehicle', $conditions, $args, $searchValue);
        
        // Get total number of records without filtering (for DataTables recordsTotal)
        $totalRecords = $this->vehicleModel->countObjectsByConditions('vehicle', []);
        
        // Get total number of records with filtering (for DataTables recordsFiltered)
        // If $searchValue or other filters are applied, this count should reflect that.
        $totalFilteredRecords = $this->vehicleModel->countObjectsByConditions('vehicle', $conditions, $searchValue);

        $data = [];
        if ($vehicles) {
            foreach ($vehicles as $vehicle) {
                $actionsHtml = '';
                if (userHasCapability('EDIT_VEHICLES')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'vehicle/edit/' . htmlspecialchars($vehicle['object_id']) . '" class="btn btn-sm btn-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                }
                if (userHasCapability('DELETE_VEHICLES')) {
                    $actionsHtml .= ' <a href="' . BASE_URL . 'vehicle/delete/' . htmlspecialchars($vehicle['object_id']) . '" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm(\'Are you sure you want to delete this vehicle: ' . htmlspecialchars(addslashes($vehicle['object_title'])) . '?\');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>';
                }
                // Add more actions like "View Details" or "Manage Reservations" if applicable

                $statusKey = $vehicle['object_status'] ?? 'unknown';
                $statusLabel = ucfirst($statusKey);
                $badgeClass = 'bg-secondary';
                if ($statusKey === 'available') $badgeClass = 'bg-success';
                else if ($statusKey === 'maintenance') $badgeClass = 'bg-warning text-dark';
                else if ($statusKey === 'unavailable' || $statusKey === 'booked') $badgeClass = 'bg-danger';
                $statusHtml = "<span class=\"badge {$badgeClass}\">" . htmlspecialchars($statusLabel) . "</span>";

                $data[] = [
                    // Ensure these keys match the 'data' attributes in your DataTables column definitions
                    'object_id' => htmlspecialchars($vehicle['object_id']),
                    'vehicle_name' => htmlspecialchars($vehicle['object_title']), // e.g., "Toyota HiAce (ABC 123)"
                    'plate_number' => htmlspecialchars($vehicle['meta']['vehicle_plate_number'] ?? 'N/A'),
                    'make_model' => htmlspecialchars(($vehicle['meta']['vehicle_make'] ?? '') . ' ' . ($vehicle['meta']['vehicle_model'] ?? '')),
                    'capacity' => htmlspecialchars($vehicle['meta']['vehicle_capacity'] ?? 'N/A'),
                    'status' => $statusHtml,
                    'last_modified' => htmlspecialchars(format_datetime_for_display($vehicle['object_modified'])),
                    'actions' => $actionsHtml
                ];
            }
        }

        $response = [
            "draw" => $draw,
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalFilteredRecords,
            "data" => $data
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
            redirect('vehicle'); // Redirect to vehicle list
        }

        $commonData = [
            'pageTitle' => 'Add New Vehicle',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Vehicles', 'url' => 'vehicle'],
                ['label' => 'Add Vehicle']
            ],
            // Define available statuses for the form dropdown
            'vehicle_statuses' => ['available' => 'Available', 'maintenance' => 'Maintenance', 'unavailable' => 'Unavailable'],
            'vehicle_types' => ['Van' => 'Van', 'Sedan' => 'Sedan', 'SUV' => 'SUV', 'MPV' => 'MPV', 'Truck' => 'Truck', 'Motorcycle' => 'Motorcycle', 'Other' => 'Other'],
            'fuel_types' => ['Gasoline' => 'Gasoline', 'Diesel' => 'Diesel', 'Electric' => 'Electric', 'Hybrid' => 'Hybrid', 'Other' => 'Other']
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            // Basic validation for core object fields
            $formData = [
                'object_title' => trim($_POST['object_title'] ?? ''), // e.g., "Toyota HiAce - ABC 123"
                'object_content' => trim($_POST['object_content'] ?? ''), // General notes/description
                'object_status' => trim($_POST['object_status'] ?? 'available'),
                'meta_fields' => [
                    'vehicle_plate_number' => strtoupper(trim($_POST['vehicle_plate_number'] ?? '')),
                    'vehicle_make' => trim($_POST['vehicle_make'] ?? ''),
                    'vehicle_model' => trim($_POST['vehicle_model'] ?? ''),
                    'vehicle_year' => filter_var(trim($_POST['vehicle_year'] ?? ''), FILTER_VALIDATE_INT),
                    'vehicle_capacity' => filter_var(trim($_POST['vehicle_capacity'] ?? '0'), FILTER_VALIDATE_INT),
                    'vehicle_type' => trim($_POST['vehicle_type'] ?? ''),
                    'vehicle_fuel_type' => trim($_POST['vehicle_fuel_type'] ?? ''),
                    // 'vehicle_current_driver_id' => filter_var(trim($_POST['vehicle_current_driver_id'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['default' => null, 'min_range' => 1]]),
                    'vehicle_notes' => trim($_POST['vehicle_notes'] ?? '') // Specific notes for the vehicle
                ],
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            // Validation
            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Vehicle Name/Identifier is required.';
            if (empty($data['meta_fields']['vehicle_plate_number'])) $data['errors']['vehicle_plate_number_err'] = 'Plate Number is required.';
            // Add more validation for other fields as needed (e.g., year format, capacity range)
            if ($data['meta_fields']['vehicle_year'] !== false && ($data['meta_fields']['vehicle_year'] < 1900 || $data['meta_fields']['vehicle_year'] > (int)date('Y') + 1)) {
                $data['errors']['vehicle_year_err'] = 'Please enter a valid year.';
            }
             if ($data['meta_fields']['vehicle_capacity'] === false || $data['meta_fields']['vehicle_capacity'] < 0) {
                $data['errors']['vehicle_capacity_err'] = 'Capacity must be a valid non-negative number.';
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
                    'object_content' => $data['object_content'], // General description
                    'object_status' => $data['object_status'],
                    'meta_fields' => $data['meta_fields']
                    // object_type will be set by VehicleModel's createVehicle method
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
                // Repopulate form with submitted data and errors
                $this->view('openoffice/vehicle_form', $data);
            }
        } else {
            // Initial form display
            $data = array_merge($commonData, [
                'object_title' => '', 'object_content' => '', 'object_status' => 'available',
                'meta_fields' => [
                    'vehicle_plate_number' => '', 'vehicle_make' => '', 'vehicle_model' => '',
                    'vehicle_year' => '', 'vehicle_capacity' => 0, 'vehicle_type' => '',
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
            redirect('vehicle'); // No ID provided
        }
        $vehicleId = (int)$vehicleId;
        $vehicle = $this->vehicleModel->getVehicleById($vehicleId);

        if (!$vehicle) {
            $_SESSION['error_message'] = 'Vehicle not found.';
            redirect('vehicle');
        }

        $commonData = [
            'pageTitle' => 'Edit Vehicle: ' . htmlspecialchars($vehicle['object_title']),
            'vehicle_id' => $vehicleId, // For form action
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
                    'vehicle_year' => filter_var(trim($_POST['vehicle_year'] ?? ($vehicle['meta']['vehicle_year'] ?? '')), FILTER_VALIDATE_INT),
                    'vehicle_capacity' => filter_var(trim($_POST['vehicle_capacity'] ?? ($vehicle['meta']['vehicle_capacity'] ?? '0')), FILTER_VALIDATE_INT),
                    'vehicle_type' => trim($_POST['vehicle_type'] ?? ($vehicle['meta']['vehicle_type'] ?? '')),
                    'vehicle_fuel_type' => trim($_POST['vehicle_fuel_type'] ?? ($vehicle['meta']['vehicle_fuel_type'] ?? '')),
                    'vehicle_notes' => trim($_POST['vehicle_notes'] ?? ($vehicle['meta']['vehicle_notes'] ?? ''))
                ],
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            $data['original_vehicle_data'] = $vehicle; // Keep original data for reference in view if needed

            // Validation (similar to add, but consider existing values)
            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Vehicle Name/Identifier is required.';
            if (empty($data['meta_fields']['vehicle_plate_number'])) $data['errors']['vehicle_plate_number_err'] = 'Plate Number is required.';
             if ($data['meta_fields']['vehicle_year'] !== false && ($data['meta_fields']['vehicle_year'] < 1900 || $data['meta_fields']['vehicle_year'] > (int)date('Y') + 1)) {
                $data['errors']['vehicle_year_err'] = 'Please enter a valid year.';
            }
             if ($data['meta_fields']['vehicle_capacity'] === false || $data['meta_fields']['vehicle_capacity'] < 0) {
                $data['errors']['vehicle_capacity_err'] = 'Capacity must be a valid non-negative number.';
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
            $data = array_merge($commonData, [
                'object_title' => $vehicle['object_title'],
                'object_content' => $vehicle['object_content'],
                'object_status' => $vehicle['object_status'],
                'meta_fields' => [
                    'vehicle_plate_number' => $vehicle['meta']['vehicle_plate_number'] ?? '',
                    'vehicle_make' => $vehicle['meta']['vehicle_make'] ?? '',
                    'vehicle_model' => $vehicle['meta']['vehicle_model'] ?? '',
                    'vehicle_year' => $vehicle['meta']['vehicle_year'] ?? '',
                    'vehicle_capacity' => $vehicle['meta']['vehicle_capacity'] ?? 0,
                    'vehicle_type' => $vehicle['meta']['vehicle_type'] ?? '',
                    'vehicle_fuel_type' => $vehicle['meta']['vehicle_fuel_type'] ?? '',
                    'vehicle_notes' => $vehicle['meta']['vehicle_notes'] ?? ''
                ],
                'errors' => [],
                'original_vehicle_data' => $vehicle // Pass original data for the view
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

        // Add check for existing reservations if vehicle reservation system is implemented
        // For example:
        // $existingReservations = $this->vehicleReservationModel->getReservationsByVehicleId($vehicleId, ['limit' => 1]);
        // if (!empty($existingReservations)) {
        //     $_SESSION['error_message'] = 'Error: Cannot delete vehicle "' . htmlspecialchars($vehicle['object_title']) . '". It has existing reservations.';
        //     redirect('vehicle');
        //     return;
        // }

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
            extract($data); // Make $data array keys available as variables in the view
            require_once $viewFile;
        } else {
            // Log error and show a generic error page or message
            error_log("VehicleController: View file not found: {$viewFile}");
            // For development, die() is okay. For production, show a user-friendly error.
            die('Error: View not found (' . htmlspecialchars($view) . '). Please contact support.');
        }
    }
}
