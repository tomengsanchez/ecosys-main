<?php

/**
 * VehicleRequestController
 *
 * Handles all operations for vehicle reservations (requests).
 * Uses the 'objects' table with object_type = 'vehicle_reservation'.
 */
class VehicleRequestController {
    private $pdo;
    private $reservationModel; // Using existing ReservationModel, will adapt for vehicle_reservation
    private $vehicleModel;
    private $userModel;
    private $optionModel;

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->reservationModel = new ReservationModel($this->pdo);
        $this->vehicleModel = new VehicleModel($this->pdo);
        $this->userModel = new UserModel($this->pdo);
        $this->optionModel = new OptionModel($this->pdo);

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
    }

    /**
     * Display the list of all vehicle reservations for admins.
     * This page will use AJAX to load data.
     */
    public function index() {
        if (!userHasCapability('VIEW_ALL_VEHICLE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to view all vehicle reservations.";
            redirect('dashboard');
        }
        
        $data = [
            'pageTitle' => 'Manage Vehicle Reservations',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'], 
                ['label' => 'Vehicle Reservations']
            ],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('vehicle_request/reservations_list', $data); 
    }

    /**
     * Display the current user's vehicle reservations.
     * This page will use AJAX to load data.
     */
    public function myrequests() {
        $data = [
            'pageTitle' => 'My Vehicle Reservations',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'], 
                ['label' => 'My Vehicle Reservations']
            ],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('vehicle_request/my_reservations_list', $data); 
    }
    
    /**
     * Display form to create a new vehicle reservation OR process the creation.
     * @param int|null $vehicleId The ID of the vehicle to reserve.
     */
    public function create($vehicleId = null) {
        if (!userHasCapability('CREATE_VEHICLE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to request vehicles.";
            redirect('vehicle'); 
        }
        
        if ($vehicleId === null) {
            $_SESSION['error_message'] = 'No vehicle selected for reservation.';
            redirect('vehicle');
        }
        $vehicleId = (int)$vehicleId;
        $vehicle = $this->vehicleModel->getVehicleById($vehicleId); 

        if (!$vehicle || $vehicle['object_status'] !== 'available') { 
            $_SESSION['error_message'] = 'This vehicle is not available for reservation or does not exist.';
            redirect('vehicle');
        }
        
        $approvedVehicleReservations = $this->reservationModel->getReservationsByParentId(
            $vehicleId, 
            'vehicle_reservation', // Specify object_type
            ['o.object_status' => 'approved'] 
        );

        $approvedReservationsData = [];
        if ($approvedVehicleReservations) {
            foreach ($approvedVehicleReservations as $approvedRes) {
                if (isset($approvedRes['meta']['reservation_start_datetime']) && isset($approvedRes['meta']['reservation_end_datetime'])) {
                    $approvedReservationsData[] = [
                        'start' => $approvedRes['meta']['reservation_start_datetime'],
                        'end' => $approvedRes['meta']['reservation_end_datetime']
                    ];
                }
            }
        }

        $commonData = [
            'pageTitle' => 'Request Vehicle: ' . htmlspecialchars($vehicle['object_title']),
            'vehicle' => $vehicle, 
            'approved_reservations_data_for_js' => $approvedReservationsData,
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'], 
                ['label' => 'Vehicles', 'url' => 'vehicle'], 
                ['label' => 'Request Vehicle']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $reservationDate = trim($_POST['reservation_date'] ?? '');
            $reservationTimeSlots = $_POST['reservation_time_slots'] ?? [];
            $reservationPurpose = trim($_POST['reservation_purpose'] ?? '');
            $destination = trim($_POST['destination'] ?? ''); 

            if (!is_array($reservationTimeSlots)) {
                $reservationTimeSlots = [$reservationTimeSlots];
            }

            $formData = [
                'reservation_date' => $reservationDate,
                'reservation_time_slots' => $reservationTimeSlots,
                'reservation_purpose' => $reservationPurpose,
                'destination' => $destination, 
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            if (empty($data['reservation_date'])) $data['errors']['date_err'] = 'Reservation date is required.';
            elseif (new DateTime($data['reservation_date']) < new DateTime(date('Y-m-d'))) $data['errors']['date_err'] = 'Reservation date cannot be in the past.';
            if (empty($data['reservation_time_slots'])) $data['errors']['time_slot_err'] = 'At least one time slot is required.';
            if (empty($data['reservation_purpose'])) $data['errors']['purpose_err'] = 'Purpose of reservation is required.';
            if (empty($data['destination'])) $data['errors']['destination_err'] = 'Destination is required.'; 

            $mergedReservationRanges = [];
            if (empty($data['errors']) && !empty($data['reservation_time_slots'])) {
                $mergedReservationRanges = $this->mergeTimeSlots($data['reservation_time_slots'], $data['reservation_date']);
                if (empty($mergedReservationRanges)) $data['errors']['time_slot_err'] = 'No valid time slots could be merged.';
            }

            if (empty($data['errors']) && !empty($mergedReservationRanges)) {
                $totalCreated = 0;
                $failedReservations = [];
                $successfulRequestDetailsForEmail = []; // For email notification

                foreach ($mergedReservationRanges as $mergedRange) {
                    $fullStartDateTimeStr = $mergedRange['start'];
                    $fullEndDateTimeStr = $mergedRange['end'];

                    $conflicts = $this->reservationModel->getConflictingReservations(
                        $vehicleId, $fullStartDateTimeStr, $fullEndDateTimeStr, ['approved'], null, 'vehicle_reservation'
                    ); 

                    if ($conflicts && count($conflicts) > 0) {
                        $failedReservations[] = ['slot' => $mergedRange['slot_display'], 'reason' => 'Conflicts with existing approved reservation.'];
                        $data['errors']['form_err'] = 'Some selected time slots are already booked for this vehicle.';
                        continue;
                    }
                    
                    $reservationObjectData = [
                        'object_author' => $_SESSION['user_id'],
                        'object_title' => 'Vehicle Request: ' . $vehicle['object_title'] . ' by ' . $_SESSION['display_name'],
                        'object_type' => 'vehicle_reservation', 
                        'object_parent' => $vehicleId, 
                        'object_status' => 'pending', 
                        'object_content' => $data['reservation_purpose'],
                        'meta_fields' => [
                            'reservation_start_datetime' => $fullStartDateTimeStr,
                            'reservation_end_datetime' => $fullEndDateTimeStr,
                            'reservation_user_id' => $_SESSION['user_id'],
                            'vehicle_destination' => $data['destination'] 
                        ]
                    ];

                    $reservationId = $this->reservationModel->createObject($reservationObjectData); 

                    if ($reservationId) {
                        $totalCreated++;
                        $successfulRequestDetailsForEmail[] = [
                            'vehicle_name' => $vehicle['object_title'],
                            'start_time_str' => $fullStartDateTimeStr, // Raw for formatting
                            'end_time_str' => $fullEndDateTimeStr,     // Raw for formatting
                            'purpose' => $data['reservation_purpose'],
                            'destination' => $data['destination'],
                            'request_id' => $reservationId
                        ];
                    } else {
                        $failedReservations[] = ['slot' => $mergedRange['slot_display'], 'reason' => 'Failed to save to database.'];
                    }
                }

                if ($totalCreated > 0) {
                    // Send email notifications
                    $user = $this->userModel->findUserById($_SESSION['user_id']);
                    $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);
                    $siteName = $this->optionModel->getOption('site_name', 'Mainsystem');

                    if ($user && !empty($user['user_email'])) {
                        $userSubject = "Vehicle Reservation Request Submitted - {$siteName}";
                        $userMessage = "<p>Dear " . htmlspecialchars($user['display_name']) . ",</p>";
                        $userMessage .= "<p>Your vehicle reservation request(s) have been successfully submitted and are pending approval. Below are the details:</p>";
                        
                        foreach($successfulRequestDetailsForEmail as $detail) {
                            $userMessage .= "<div style='margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;'>";
                            $userMessage .= "<strong>Vehicle:</strong> " . htmlspecialchars($detail['vehicle_name']) . "<br>";
                            $userMessage .= "<strong>Time:</strong> " . htmlspecialchars(format_datetime_for_display($detail['start_time_str'])) . " to " . htmlspecialchars(format_datetime_for_display($detail['end_time_str'])) . "<br>";
                            $userMessage .= "<strong>Destination:</strong> " . htmlspecialchars($detail['destination']) . "<br>";
                            $userMessage .= "<strong>Purpose:</strong> " . nl2br(htmlspecialchars($detail['purpose'])) . "<br>";
                            $userMessage .= "<strong>Request ID:</strong> " . $detail['request_id'] . "<br>";
                            $userMessage .= "</div>";
                        }
                        $userMessage .= "<p>You will be notified once your request(s) have been reviewed.</p><p>Thank you,<br>The {$siteName} Team</p>";
                        send_system_email($user['user_email'], $userSubject, $userMessage, true);
                    }

                    if ($adminEmail) {
                        $adminSubject = "New Vehicle Reservation Request Pending - {$siteName}";
                        $adminMessage = "<p>A new vehicle reservation request(s) has been submitted by <strong>" . htmlspecialchars($user['display_name'] ?? 'N/A') . " (ID: " . $_SESSION['user_id'] . ")</strong> and requires your approval:</p>";
                         foreach($successfulRequestDetailsForEmail as $detail) {
                            $adminMessage .= "<div style='margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;'>";
                            $adminMessage .= "<strong>Vehicle:</strong> " . htmlspecialchars($detail['vehicle_name']) . "<br>";
                            $adminMessage .= "<strong>Time:</strong> " . htmlspecialchars(format_datetime_for_display($detail['start_time_str'])) . " to " . htmlspecialchars(format_datetime_for_display($detail['end_time_str'])) . "<br>";
                            $adminMessage .= "<strong>Destination:</strong> " . htmlspecialchars($detail['destination']) . "<br>";
                            $adminMessage .= "<strong>Purpose:</strong> " . nl2br(htmlspecialchars($detail['purpose'])) . "<br>";
                            $adminMessage .= "<strong>Request ID:</strong> " . $detail['request_id'] . "<br>";
                            $adminMessage .= "</div>";
                        }
                        $adminMessage .= "<p>Please log in to the admin panel to review this request: <a href='" . BASE_URL . "VehicleRequest/index" . "'>" . BASE_URL . "VehicleRequest/index</a></p>";
                        send_system_email($adminEmail, $adminSubject, $adminMessage, true);
                    }

                    $_SESSION['message'] = "Successfully submitted {$totalCreated} vehicle reservation request(s)! They are now pending approval. You will receive an email confirmation.";
                    if (!empty($failedReservations)) {
                        $_SESSION['error_message'] = "Some merged slots could not be reserved: " . implode(', ', array_column($failedReservations, 'slot')) . ". Reasons: " . implode('; ', array_column($failedReservations, 'reason'));
                    }
                    redirect('VehicleRequest/myrequests');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. No vehicle reservation requests could be submitted.';
                    if (!empty($failedReservations)) {
                         $data['errors']['form_err'] .= " Reasons: " . implode('; ', array_column($failedReservations, 'reason'));
                    }
                    $this->view('vehicle_request/reservation_form', $data);
                }

            } else { 
                 $this->view('vehicle_request/reservation_form', $data);
            }

        } else { 
            $data = array_merge($commonData, [
                'reservation_date' => date('Y-m-d'), 
                'reservation_time_slots' => [], 
                'reservation_purpose' => '',
                'destination' => '', 
                'errors' => []
            ]);
            $this->view('vehicle_request/reservation_form', $data);
        }
    }

    /**
     * AJAX endpoint to get queue information for multiple selected vehicle time slots.
     */
    public function getMultipleSlotsQueueInfo() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        $vehicleId = filter_var($input['vehicleId'] ?? $input['roomId'] ?? null, FILTER_VALIDATE_INT);
        // Replace FILTER_SANITIZE_STRING with FILTER_SANITIZE_FULL_SPECIAL_CHARS
        $date = filter_var($input['date'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS); 
        $slots = $input['slots'] ?? []; 

        if (!$vehicleId || !$date || !is_array($slots) || empty($slots)) {
            error_log("[VehicleRequestController::getMultipleSlotsQueueInfo] Error: Missing or invalid parameters. VehicleID: {$vehicleId}, Date: {$date}, Slots: " . print_r($slots, true));
            echo json_encode(['error' => 'Missing or invalid parameters for vehicle queue info.']);
            exit;
        }
        error_log("[VehicleRequestController::getMultipleSlotsQueueInfo] Processing for VehicleID: {$vehicleId}, Date: {$date}, Slots: " . print_r($slots, true));

        $pendingCounts = [];
        foreach ($slots as $slot) {
            $timeParts = explode('-', $slot);
            if (count($timeParts) !== 2) {
                $pendingCounts[$slot] = ['error' => 'Invalid slot format.'];
                error_log("[VehicleRequestController::getMultipleSlotsQueueInfo] Invalid slot format: {$slot}");
                continue;
            }

            $startTimeStr = $date . ' ' . trim($timeParts[0]) . ':00';
            $endTimeStr = $date . ' ' . trim($timeParts[1]) . ':00';

            try {
                new DateTime($startTimeStr); 
                new DateTime($endTimeStr);
            } catch (Exception $e) {
                $pendingCounts[$slot] = ['error' => 'Invalid date or time format in slot.'];
                error_log("[VehicleRequestController::getMultipleSlotsQueueInfo] Invalid date/time in slot: {$slot}. Error: " . $e->getMessage());
                continue;
            }
            
            $conflictingPending = $this->reservationModel->getConflictingReservations(
                $vehicleId, $startTimeStr, $endTimeStr, ['pending'], null, 'vehicle_reservation'
            );

            if ($conflictingPending === false) { 
                $pendingCounts[$slot] = ['error' => 'Could not retrieve queue information for vehicle.'];
                 error_log("[VehicleRequestController::getMultipleSlotsQueueInfo] getConflictingReservations returned false for slot: {$slot}");
            } else {
                $pendingCounts[$slot] = count($conflictingPending);
                error_log("[VehicleRequestController::getMultipleSlotsQueueInfo] Slot: {$slot}, Pending Count: " . count($conflictingPending));
            }
        }
        echo json_encode(['pendingCounts' => $pendingCounts]);
        exit;
    }


    /**
     * AJAX handler for fetching ALL vehicle reservation data.
     */
    public function ajaxGetAllVehicleReservations() {
        header('Content-Type: application/json');
        if (!userHasCapability('VIEW_ALL_VEHICLE_RESERVATIONS')) {
            echo json_encode(['error' => 'Permission denied.', 'data' => [], 'pagination' => null]);
            exit;
        }

        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $searchTerm = isset($_POST['searchTerm']) ? trim($_POST['searchTerm']) : '';
        $filterStatus = isset($_POST['filterStatus']) ? trim($_POST['filterStatus']) : '';
        $offset = ($page - 1) * $limit;

        $conditions = [];
        if (!empty($filterStatus)) $conditions['o.object_status'] = $filterStatus;
        
        $args = [
            'orderby' => 'o.object_date', 
            'orderdir' => 'DESC',
            'include_meta' => true,
            'limit' => $limit,
            'offset' => $offset
        ];

        $reservations = $this->reservationModel->getObjectsByConditions('vehicle_reservation', $conditions, $args, $searchTerm);
        $totalRecords = $this->reservationModel->countObjectsByConditions('vehicle_reservation', $conditions, $searchTerm);
        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 0;

        $enrichedReservations = [];
        if ($reservations) {
            foreach ($reservations as $res) {
                $vehicle = $this->vehicleModel->getVehicleById($res['object_parent']);
                $res['vehicle_name'] = $vehicle ? htmlspecialchars($vehicle['object_title']) : 'Unknown Vehicle';
                $user = $this->userModel->findUserById($res['object_author']);
                $res['user_display_name'] = $user ? htmlspecialchars($user['display_name']) : 'Unknown User';
                $res['formatted_start_datetime'] = format_datetime_for_display($res['meta']['reservation_start_datetime'] ?? '');
                $res['formatted_end_datetime'] = format_datetime_for_display($res['meta']['reservation_end_datetime'] ?? '');
                $res['formatted_object_date'] = format_datetime_for_display($res['object_date']);
                $res['destination'] = htmlspecialchars($res['meta']['vehicle_destination'] ?? 'N/A'); 
                $enrichedReservations[] = $res;
            }
        }
        
        echo json_encode([
            'data' => $enrichedReservations,
            'pagination' => ['currentPage' => $page, 'limit' => $limit, 'totalPages' => $totalPages, 'totalRecords' => $totalRecords]
        ]);
        exit;
    }

    /**
     * AJAX handler for fetching USER'S OWN vehicle reservation data.
     */
    public function ajaxGetMyVehicleReservations() {
        header('Content-Type: application/json');
        if (!isLoggedIn()) {
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not logged in"]);
            exit;
        }
        $userId = $_SESSION['user_id'];

        $conditions = ['o.object_author' => $userId];
        $args = ['orderby' => 'o.object_date', 'orderdir' => 'DESC', 'include_meta' => true];
        $myReservations = $this->reservationModel->getAllReservationsOfType('vehicle_reservation', $conditions, $args);
        
        $data = [];
        if ($myReservations) {
            foreach ($myReservations as $res) {
                $vehicle = $this->vehicleModel->getVehicleById($res['object_parent']);
                $vehicleName = $vehicle ? htmlspecialchars($vehicle['object_title']) : 'Unknown Vehicle';
                
                $statusKey = $res['object_status'] ?? 'unknown';
                $statusLabel = ucfirst($statusKey);
                $badgeClass = 'bg-secondary';
                if ($statusKey === 'pending') $badgeClass = 'bg-warning text-dark';
                elseif ($statusKey === 'approved') $badgeClass = 'bg-success';
                elseif ($statusKey === 'denied') $badgeClass = 'bg-danger';
                elseif ($statusKey === 'cancelled') $badgeClass = 'bg-info text-dark';
                $statusHtml = "<span class=\"badge {$badgeClass}\">" . htmlspecialchars($statusLabel) . "</span>";

                $actionsHtml = '';
                if ($res['object_status'] === 'pending' && userHasCapability('CANCEL_OWN_VEHICLE_RESERVATIONS')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'VehicleRequest/cancel/' . htmlspecialchars($res['object_id']) . '" 
                                       class="btn btn-sm btn-warning text-dark action-btn" data-action="cancel" data-id="' . htmlspecialchars($res['object_id']) . '" title="Cancel Request"
                                       onclick="return confirm(\'Are you sure you want to cancel this vehicle request?\');">
                                        <i class="fas fa-times-circle"></i> Cancel
                                    </a>';
                } else {
                    $actionsHtml = '<span class="text-muted small">No actions</span>';
                }

                $data[] = [
                    "id" => htmlspecialchars($res['object_id']),
                    "vehicle_name" => $vehicleName,
                    "purpose" => nl2br(htmlspecialchars($res['object_content'] ?? 'N/A')),
                    "destination" => htmlspecialchars($res['meta']['vehicle_destination'] ?? 'N/A'),
                    "start_time" => htmlspecialchars(format_datetime_for_display($res['meta']['reservation_start_datetime'] ?? '')),
                    "end_time" => htmlspecialchars(format_datetime_for_display($res['meta']['reservation_end_datetime'] ?? '')),
                    "requested_on" => htmlspecialchars(format_datetime_for_display($res['object_date'])),
                    "status" => $statusHtml,
                    "actions" => $actionsHtml
                ];
            }
        }
        echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => count($data), "recordsFiltered" => count($data), "data" => $data]);
        exit;
    }


    public function cancel($reservationId = null) {
        if (!userHasCapability('CANCEL_OWN_VEHICLE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to cancel vehicle reservations.";
            redirect('VehicleRequest/myrequests');
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId);
        $siteName = $this->optionModel->getOption('site_name', 'Mainsystem');

        if (!$reservation || $reservation['object_type'] !== 'vehicle_reservation' || $reservation['object_author'] != $_SESSION['user_id'] || $reservation['object_status'] !== 'pending') {
            $_SESSION['error_message'] = 'Invalid request or reservation cannot be cancelled.';
        } else {
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'cancelled'])) {
                $_SESSION['message'] = 'Vehicle reservation request cancelled successfully.';
                
                $user = $this->userModel->findUserById($reservation['object_author']);
                $vehicle = $this->vehicleModel->getVehicleById($reservation['object_parent']);

                if ($user && !empty($user['user_email']) && $vehicle) {
                    $subject = "Vehicle Reservation Cancelled - {$siteName}";
                    $body = "<p>Dear " . htmlspecialchars($user['display_name']) . ",</p>";
                    $body .= "<p>Your reservation for the vehicle '<strong>" . htmlspecialchars($vehicle['object_title']) . "</strong>' scheduled from " . htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '')) . " to " . htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '')) . " has been <strong>cancelled by you</strong>.</p>";
                    $body .= "<p><strong>Details:</strong></p>";
                    $body .= "<ul>";
                    $body .= "<li><strong>Vehicle:</strong> " . htmlspecialchars($vehicle['object_title']) . "</li>";
                    $body .= "<li><strong>Destination:</strong> " . htmlspecialchars($reservation['meta']['vehicle_destination'] ?? 'N/A') . "</li>";
                    $body .= "<li><strong>Purpose:</strong> " . nl2br(htmlspecialchars($reservation['object_content'] ?? 'N/A')) . "</li>";
                    $body .= "<li><strong>Reservation ID:</strong> " . $reservationId . "</li>";
                    $body .= "</ul>";
                    $body .= "<p>If this was a mistake, please contact an administrator or create a new reservation.</p>";
                    $body .= "<p>Thank you,<br>The {$siteName} Team</p>";
                    send_system_email($user['user_email'], $subject, $body, true);
                }
            } else {
                $_SESSION['error_message'] = 'Could not cancel vehicle reservation request.';
            }
        }
        redirect('VehicleRequest/myrequests');
    }

    public function approve($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_VEHICLE_RESERVATIONS')) {
            $this->handlePermissionErrorAjax(); return;
        }
        $reservationId = (int)$reservationId;
        $reservationToApprove = $this->reservationModel->getObjectById($reservationId);
        $success = false; $message = 'Invalid request.';
        $siteName = $this->optionModel->getOption('site_name', 'Mainsystem');

        if ($reservationToApprove && $reservationToApprove['object_type'] === 'vehicle_reservation') {
            if ($reservationToApprove['object_status'] === 'pending') {
                $vehicleId = $reservationToApprove['object_parent'];
                $startTime = $reservationToApprove['meta']['reservation_start_datetime'] ?? null;
                $endTime = $reservationToApprove['meta']['reservation_end_datetime'] ?? null;

                if ($startTime && $endTime) {
                    $conflicts = $this->reservationModel->getConflictingReservations($vehicleId, $startTime, $endTime, ['approved'], $reservationId, 'vehicle_reservation');
                    if ($conflicts && count($conflicts) > 0) {
                        $message = 'Error: Cannot approve. Conflicts with an existing approved vehicle reservation.';
                    } else {
                        if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'approved'])) {
                            $success = true; $message = 'Vehicle reservation approved successfully.';
                            
                            $user = $this->userModel->findUserById($reservationToApprove['object_author']);
                            $vehicle = $this->vehicleModel->getVehicleById($vehicleId);

                            if ($user && !empty($user['user_email']) && $vehicle) {
                                $subject = "Vehicle Reservation Approved - {$siteName}";
                                $body = "<p>Dear " . htmlspecialchars($user['display_name']) . ",</p>";
                                $body .= "<p>Your reservation request for the vehicle '<strong>" . htmlspecialchars($vehicle['object_title']) . "</strong>' has been <strong>approved</strong>.</p>";
                                $body .= "<p><strong>Reservation Details:</strong></p>";
                                $body .= "<ul>";
                                $body .= "<li><strong>Vehicle:</strong> " . htmlspecialchars($vehicle['object_title']) . "</li>";
                                $body .= "<li><strong>Time:</strong> " . htmlspecialchars(format_datetime_for_display($startTime)) . " to " . htmlspecialchars(format_datetime_for_display($endTime)) . "</li>";
                                $body .= "<li><strong>Destination:</strong> " . htmlspecialchars($reservationToApprove['meta']['vehicle_destination'] ?? 'N/A') . "</li>";
                                $body .= "<li><strong>Purpose:</strong> " . nl2br(htmlspecialchars($reservationToApprove['object_content'] ?? 'N/A')) . "</li>";
                                $body .= "<li><strong>Reservation ID:</strong> " . $reservationId . "</li>";
                                $body .= "</ul>";
                                $body .= "<p>Thank you,<br>The {$siteName} Team</p>";
                                send_system_email($user['user_email'], $subject, $body, true);
                            }
                        } else { $message = 'Could not approve vehicle reservation due to a system error.'; }
                    }
                } else { $message = 'Error: Reservation is missing start or end time data.'; }
            } else { $message = 'Only pending vehicle reservations can be approved. This one is ' . $reservationToApprove['object_status'] . '.'; }
        }
        if ($this->isAjaxRequest()) { echo json_encode(['success' => $success, 'message' => $message]); exit; }
        $_SESSION[$success ? 'message' : 'error_message'] = $message;
        redirect('VehicleRequest/index');
    }

    public function deny($reservationId = null) {
         if (!userHasCapability('APPROVE_DENY_VEHICLE_RESERVATIONS')) {
            $this->handlePermissionErrorAjax(); return;
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId);
        $success = false; $message = 'Invalid request.';
        $siteName = $this->optionModel->getOption('site_name', 'Mainsystem');

        if ($reservation && $reservation['object_type'] === 'vehicle_reservation') {
            if (in_array($reservation['object_status'], ['pending', 'approved'])) {
                $originalStatus = $reservation['object_status'];
                if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'denied'])) {
                    $success = true;
                    $message = 'Vehicle reservation ' . ($originalStatus === 'approved' ? 'approval revoked and reservation denied.' : 'denied successfully.');
                    
                    $user = $this->userModel->findUserById($reservation['object_author']);
                    $vehicle = $this->vehicleModel->getVehicleById($reservation['object_parent']);

                    if ($user && !empty($user['user_email']) && $vehicle) {
                        $subject = "Vehicle Reservation Denied - {$siteName}";
                        $body = "<p>Dear " . htmlspecialchars($user['display_name']) . ",</p>";
                        $body .= "<p>We regret to inform you that your reservation request for the vehicle '<strong>" . htmlspecialchars($vehicle['object_title']) . "</strong>' from " . htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '')) . " to " . htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '')) . " has been <strong>denied</strong>.</p>";
                        $body .= "<p><strong>Details of Denied Request:</strong></p>";
                        $body .= "<ul>";
                        $body .= "<li><strong>Vehicle:</strong> " . htmlspecialchars($vehicle['object_title']) . "</li>";
                        $body .= "<li><strong>Destination:</strong> " . htmlspecialchars($reservation['meta']['vehicle_destination'] ?? 'N/A') . "</li>";
                        $body .= "<li><strong>Purpose:</strong> " . nl2br(htmlspecialchars($reservation['object_content'] ?? 'N/A')) . "</li>";
                        $body .= "<li><strong>Reservation ID:</strong> " . $reservationId . "</li>";
                        $body .= "</ul>";
                        // Optionally, add a reason for denial if your system supports it
                        // $body .= "<p><strong>Reason for denial:</strong> [Admin should provide this if applicable]</p>";
                        $body .= "<p>If you have any questions, please contact the administrator.</p>";
                        $body .= "<p>Thank you,<br>The {$siteName} Team</p>";
                        send_system_email($user['user_email'], $subject, $body, true);
                    }
                } else { $message = 'Could not deny vehicle reservation.'; }
            } else { $message = 'Only pending or approved vehicle reservations can be denied. This one is ' . $reservation['object_status'] . '.';}
        }
        if ($this->isAjaxRequest()) { echo json_encode(['success' => $success, 'message' => $message]); exit; }
        $_SESSION[$success ? 'message' : 'error_message'] = $message;
        redirect('VehicleRequest/index');
    }
    
    public function deleteAnyReservation($reservationId = null) {
        if (!userHasCapability('DELETE_ANY_VEHICLE_RESERVATION')) {
            $this->handlePermissionErrorAjax(); return;
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId);
        $success = false; $message = 'Invalid request.';

        if ($reservation && $reservation['object_type'] === 'vehicle_reservation') {
            if ($this->reservationModel->deleteObject($reservationId)) { 
                $success = true; $message = "Vehicle reservation record ID {$reservationId} deleted successfully.";
            } else { $message = "Could not delete vehicle reservation record ID {$reservationId}."; }
        } else { $message = "Vehicle reservation record ID {$reservationId} not found or not a vehicle reservation.";}
        
        if ($this->isAjaxRequest()) { echo json_encode(['success' => $success, 'message' => $message]); exit; }
        $_SESSION[$success ? 'message' : 'error_message'] = $message;
        redirect('VehicleRequest/index');
    }


    private function handlePermissionErrorAjax() {
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }
        $_SESSION['error_message'] = "You do not have permission for this action.";
        redirect('dashboard'); 
    }

    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    private function mergeTimeSlots(array $slots, string $date): array {
        if (empty($slots)) return [];
        usort($slots, function($a, $b) {
            return strtotime(explode('-', $a)[0]) - strtotime(explode('-', $b)[0]);
        });
        $mergedRanges = []; $currentMerge = null;
        foreach ($slots as $slot) {
            $timeParts = explode('-', $slot);
            if (count($timeParts) !== 2) continue;
            $slotStart = trim($timeParts[0]); $slotEnd = trim($timeParts[1]);
            $fullStartDateTimeStr = $date . ' ' . $slotStart . ':00';
            $fullEndDateTimeStr = $date . ' ' . $slotEnd . ':00';
            try {
                $currentSlotStart = new DateTime($fullStartDateTimeStr);
                $currentSlotEnd = new DateTime($fullEndDateTimeStr);
            } catch (Exception $e) { continue; }
            if ($currentMerge === null) {
                $currentMerge = ['start' => $currentSlotStart, 'end' => $currentSlotEnd];
            } else {
                if ($currentSlotStart <= $currentMerge['end']) {
                    if ($currentSlotEnd > $currentMerge['end']) $currentMerge['end'] = $currentSlotEnd;
                } else {
                    $mergedRanges[] = ['start' => $currentMerge['start']->format('Y-m-d H:i:s'), 'end' => $currentMerge['end']->format('Y-m-d H:i:s'), 'slot_display' => $currentMerge['start']->format('g:i A') . ' - ' . $currentMerge['end']->format('g:i A')];
                    $currentMerge = ['start' => $currentSlotStart, 'end' => $currentSlotEnd];
                }
            }
        }
        if ($currentMerge !== null) {
            $mergedRanges[] = ['start' => $currentMerge['start']->format('Y-m-d H:i:s'), 'end' => $currentMerge['end']->format('Y-m-d H:i:s'), 'slot_display' => $currentMerge['start']->format('g:i A') . ' - ' . $currentMerge['end']->format('g:i A')];
        }
        return $mergedRanges;
    }

    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php'; 
        if (file_exists($viewFile)) {
            extract($data); 
            require_once $viewFile;
        } else {
            error_log("VehicleRequestController: View file not found: {$viewFile}");
            die('Error: View not found (' . htmlspecialchars($view) . '). Please contact support.');
        }
    }
}
