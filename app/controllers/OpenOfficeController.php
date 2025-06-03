<?php

/**
 * OpenofficeController
 * (Note: Filename should be OpenofficeController.php as per previous discussion for case-sensitivity)
 *
 * Handles operations related to the Open Office module, including Rooms and Reservations.
 */
class OpenofficeController { // Class name remains PascalCase
    private $pdo;
    private $reservationModel; 
    private $roomModel;        
    private $userModel; 
    private $optionModel; 

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->reservationModel = new ReservationModel($this->pdo); 
        $this->roomModel = new RoomModel($this->pdo); 
        $this->userModel = new UserModel($this->pdo); 
        $this->optionModel = new OptionModel($this->pdo); 

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
    }

    // --- AJAX Handler for Slot Queue Information ---
    public function getSlotQueueInfo() {
        header('Content-Type: application/json');
        $response = ['pendingCount' => 0, 'error' => null];

        $roomId = filter_input(INPUT_GET, 'roomId', FILTER_VALIDATE_INT);
        $selectedDate = trim(filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $timeSlotValue = trim(filter_input(INPUT_GET, 'slot', FILTER_SANITIZE_FULL_SPECIAL_CHARS)); 

        if (!$roomId || !$selectedDate || !$timeSlotValue) {
            $response['error'] = 'Missing parameters.';
            echo json_encode($response);
            return;
        }

        $timeParts = explode('-', $timeSlotValue);
        if (count($timeParts) !== 2) {
            $response['error'] = 'Invalid time slot format.';
            echo json_encode($response);
            return;
        }
        $startTimeOnDate = trim($timeParts[0]); 

        try {
            new DateTime($selectedDate . ' ' . $startTimeOnDate . ':00'); 
            $exactStartDateTimeStr = $selectedDate . ' ' . $startTimeOnDate . ':00';
            $pendingCount = $this->reservationModel->countPendingRequestsStartingAt($roomId, $exactStartDateTimeStr);

            if ($pendingCount === false) { 
                $response['error'] = 'Could not retrieve queue information due to a database error.';
            } else {
                $response['pendingCount'] = (int)$pendingCount;
            }
        } catch (Exception $e) {
            $response['error'] = 'Invalid date or time format provided.';
            error_log("Error in getSlotQueueInfo: " . $e->getMessage());
        }
        
        echo json_encode($response);
    }

    // --- AJAX Data Source for "My Reservations" DataTables ---
    public function ajaxGetUserReservations() {
        header('Content-Type: application/json');
        if (!isLoggedIn()) {
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0) , "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not authenticated"]);
            return;
        }

        $userId = $_SESSION['user_id'];
        $myReservations = $this->reservationModel->getReservationsByUserId($userId, [
            'orderby' => 'object_date', 
            'orderdir' => 'DESC',
            'include_meta' => true
        ]);

        $data = [];
        $reservation_statuses_map = ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']; 

        if ($myReservations) {
            foreach ($myReservations as $reservation) {
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']);
                $roomName = $roomFromDb ? $roomFromDb['object_title'] : 'Unknown Room';

                $statusKey = $reservation['object_status'] ?? 'unknown';
                $statusLabel = $reservation_statuses_map[$statusKey] ?? ucfirst($statusKey);
                $badgeClass = 'bg-secondary'; 
                if ($statusKey === 'pending') $badgeClass = 'bg-warning text-dark';
                elseif ($statusKey === 'approved') $badgeClass = 'bg-success';
                elseif ($statusKey === 'denied') $badgeClass = 'bg-danger';
                elseif ($statusKey === 'cancelled') $badgeClass = 'bg-info text-dark';
                $statusHtml = '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span>';

                $actionsHtml = '<span class="text-muted small">No actions</span>';
                if ($reservation['object_status'] === 'pending' && userHasCapability('CANCEL_OWN_ROOM_RESERVATIONS')) {
                    $cancelUrl = BASE_URL . 'OpenOffice/cancelreservation/' . htmlspecialchars($reservation['object_id']);
                    $actionsHtml = '<a href="' . $cancelUrl . '" 
                                       class="btn btn-sm btn-warning text-dark" title="Cancel Request"
                                       onclick="return confirm(\'Are you sure you want to cancel this reservation request?\');">
                                        <i class="fas fa-ban"></i> Cancel
                                    </a>';
                }

                $data[] = [
                    "id" => htmlspecialchars($reservation['object_id']),
                    "room" => htmlspecialchars($roomName),
                    "purpose" => nl2br(htmlspecialchars($reservation['object_content'] ?? 'N/A')),
                    "start_time" => htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '')),
                    "end_time" => htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '')),
                    "requested_on" => htmlspecialchars(format_datetime_for_display($reservation['object_date'])),
                    "status" => $statusHtml,
                    "actions" => $actionsHtml
                ];
            }
        }
        $output = [
            "draw"            => intval($_GET['draw'] ?? 0), 
            "recordsTotal"    => count($data),
            "recordsFiltered" => count($data),
            "data"            => $data
        ];
        echo json_encode($output);
    }

    // --- AJAX Data Source for "All Reservations" (Admin View) DataTables ---
    public function ajaxGetAllReservations() {
        header('Content-Type: application/json');
        if (!isLoggedIn() || !userHasCapability('VIEW_ALL_ROOM_RESERVATIONS')) {
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not authorized"]);
            return;
        }

        $allReservations = $this->reservationModel->getAllReservations([], [
            'orderby' => 'object_date', 
            'orderdir' => 'DESC',
            'include_meta' => true
        ]);

        $data = [];
        $reservation_statuses_map = ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled'];

        if ($allReservations) {
            foreach ($allReservations as $reservation) {
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']);
                $roomName = $roomFromDb ? $roomFromDb['object_title'] : 'Unknown Room';

                $user = $this->userModel->findUserById($reservation['object_author']);
                $userName = $user ? $user['display_name'] : 'Unknown User';

                $statusKey = $reservation['object_status'] ?? 'unknown';
                $statusLabel = $reservation_statuses_map[$statusKey] ?? ucfirst($statusKey);
                $badgeClass = 'bg-secondary';
                if ($statusKey === 'pending') $badgeClass = 'bg-warning text-dark';
                elseif ($statusKey === 'approved') $badgeClass = 'bg-success';
                elseif ($statusKey === 'denied') $badgeClass = 'bg-danger';
                elseif ($statusKey === 'cancelled') $badgeClass = 'bg-info text-dark';
                $statusHtml = '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span>';

                $actionsHtml = '<span class="text-muted small">No actions</span>';
                if (userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
                    if ($reservation['object_status'] === 'pending') {
                        $approveUrl = BASE_URL . 'OpenOffice/approvereservation/' . htmlspecialchars($reservation['object_id']);
                        $denyUrl = BASE_URL . 'OpenOffice/denyreservation/' . htmlspecialchars($reservation['object_id']);
                        $actionsHtml = '<a href="' . $approveUrl . '" class="btn btn-sm btn-success mb-1 me-1" title="Approve" onclick="return confirm(\'Approve this reservation?\');"><i class="fas fa-check"></i></a>';
                        $actionsHtml .= '<a href="' . $denyUrl . '" class="btn btn-sm btn-danger mb-1" title="Deny" onclick="return confirm(\'Deny this reservation?\');"><i class="fas fa-times"></i></a>';
                    } elseif ($reservation['object_status'] === 'approved') {
                        $revokeUrl = BASE_URL . 'OpenOffice/denyreservation/' . htmlspecialchars($reservation['object_id']);
                        $actionsHtml = '<a href="' . $revokeUrl . '" class="btn btn-sm btn-warning text-dark mb-1" title="Revoke Approval" onclick="return confirm(\'Revoke approval and deny this reservation?\');"><i class="fas fa-undo"></i></a>';
                    }
                }
                
                $data[] = [
                    "id" => htmlspecialchars($reservation['object_id']),
                    "room" => htmlspecialchars($roomName),
                    "user" => htmlspecialchars($userName),
                    "purpose" => nl2br(htmlspecialchars($reservation['object_content'] ?? 'N/A')),
                    "start_time" => htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '')),
                    "end_time" => htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '')),
                    "requested_on" => htmlspecialchars(format_datetime_for_display($reservation['object_date'])),
                    "status" => $statusHtml,
                    "actions" => $actionsHtml
                ];
            }
        }

        $output = [
            "draw"            => intval($_GET['draw'] ?? 0),
            "recordsTotal"    => count($data),
            "recordsFiltered" => count($data),
            "data"            => $data
        ];

        echo json_encode($output);
    }

    // --- AJAX Data Source for "Manage Rooms" DataTables ---
    public function ajaxGetRooms() {
        header('Content-Type: application/json');
        if (!isLoggedIn() || !userHasCapability('VIEW_ROOMS')) { // VIEW_ROOMS is the base capability to see the list
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not authorized"]);
            return;
        }

        $rooms = $this->roomModel->getAllRooms(['orderby' => 'object_title', 'orderdir' => 'ASC']);
        $data = [];

        if ($rooms) {
            foreach ($rooms as $room) {
                // Status badge
                $statusKey = $room['object_status'] ?? 'unknown';
                $statusLabel = ucfirst($statusKey);
                $badgeClass = 'bg-secondary';
                if ($statusKey === 'available') $badgeClass = 'bg-success';
                elseif ($statusKey === 'unavailable') $badgeClass = 'bg-warning text-dark';
                elseif ($statusKey === 'maintenance') $badgeClass = 'bg-danger';
                $statusHtml = '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span>';

                // Actions
                $actionsHtml = '';
                if ($room['object_status'] === 'available' && userHasCapability('CREATE_ROOM_RESERVATIONS')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'OpenOffice/createreservation/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-info me-1 mb-1" title="Book this room"><i class="fas fa-calendar-plus"></i> Book</a>';
                }
                if (userHasCapability('EDIT_ROOMS')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'OpenOffice/editRoom/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-primary me-1 mb-1" title="Edit"><i class="fas fa-edit"></i> Edit</a>';
                }
                if (userHasCapability('DELETE_ROOMS')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'OpenOffice/deleteRoom/' . htmlspecialchars($room['object_id']) . '" 
                                       class="btn btn-sm btn-danger mb-1" title="Delete"
                                       onclick="return confirm(\'Are you sure you want to delete the room &quot;' . htmlspecialchars(addslashes($room['object_title'])) . '&quot;? This action cannot be undone.\');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>';
                }
                if (empty(trim($actionsHtml))) {
                    $actionsHtml = '<span class="text-muted small">No actions available</span>';
                }


                $rowData = [
                    "id" => htmlspecialchars($room['object_id']),
                    "name" => htmlspecialchars($room['object_title']),
                    "capacity" => htmlspecialchars($room['meta']['room_capacity'] ?? 'N/A'),
                    "location" => htmlspecialchars($room['meta']['room_location'] ?? 'N/A'),
                    "equipment" => nl2br(htmlspecialchars($room['meta']['room_equipment'] ?? 'N/A')),
                    "status" => $statusHtml,
                    "modified" => htmlspecialchars(format_datetime_for_display($room['object_modified'])),
                    "actions" => $actionsHtml
                ];
                
                // Conditionally include ID and Last Modified based on higher room management capabilities
                // For DataTables, it's better to always send all potential columns and let JS hide/show them
                // or send a consistent structure. For simplicity here, we'll adjust based on a common admin check.
                // If we want different columns for different users, DataTables column definitions in JS would need to adapt too.
                // For now, let's assume if they can VIEW_ROOMS, they see a basic set.
                // More advanced columns like ID and modified date are often for those with more permissions.

                $data[] = $rowData;
            }
        }
        $output = [
            "draw"            => intval($_GET['draw'] ?? 0),
            "recordsTotal"    => count($data),
            "recordsFiltered" => count($data),
            "data"            => $data
        ];
        echo json_encode($output);
    }


    // --- Room Management Methods ---
    public function rooms() {
        if (!userHasCapability('VIEW_ROOMS')) {
            $_SESSION['error_message'] = "You do not have permission to view rooms.";
            redirect('Dashboard'); 
        }
        // $rooms = $this->roomModel->getAllRooms(); // Data loaded via AJAX
        $data = [
            'pageTitle' => 'Manage Rooms',
            // 'rooms' => $rooms, // Removed
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], ['label' => 'Rooms List']]
        ];
        $this->view('openoffice/rooms_list', $data);
    }

    public function addRoom() {
        if (!userHasCapability('CREATE_ROOMS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to add new rooms.';
            redirect('OpenOffice/rooms');
        }
        $commonData = [
            'pageTitle' => 'Add New Room',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'OpenOffice/rooms'],
                ['label' => 'Rooms', 'url' => 'OpenOffice/rooms'],
                ['label' => 'Add Room']
            ],
            'room_statuses' => ['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance'] 
        ];
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'object_title' => trim($_POST['object_title'] ?? ''), 
                'object_content' => trim($_POST['object_content'] ?? ''), 
                'object_status' => trim($_POST['object_status'] ?? 'available'), 
                'meta_fields' => [
                    'room_capacity' => filter_var(trim($_POST['room_capacity'] ?? '0'), FILTER_VALIDATE_INT),
                    'room_location' => trim($_POST['room_location'] ?? ''),
                    'room_equipment' => trim($_POST['room_equipment'] ?? '') 
                ], 'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Room Name is required.';
            if ($data['meta_fields']['room_capacity'] === false || $data['meta_fields']['room_capacity'] < 0) {
                 $data['errors']['room_capacity_err'] = 'Capacity must be a valid non-negative number.';
                 $data['meta_fields']['room_capacity'] = 0; 
            }
            if (!array_key_exists($data['object_status'], $data['room_statuses'])) $data['errors']['object_status_err'] = 'Invalid room status selected.';
            if (empty($data['errors'])) {
                $roomData = [ 
                    'object_author' => $_SESSION['user_id'], 'object_title' => $data['object_title'],
                    'object_content' => $data['object_content'], 'object_status' => $data['object_status'], 
                    'meta_fields' => $data['meta_fields']
                ];
                $roomId = $this->roomModel->createRoom($roomData); 
                if ($roomId) {
                    $_SESSION['admin_message'] = 'Room created successfully!';
                    redirect('OpenOffice/rooms');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not create room.';
                    $this->view('openoffice/room_form', $data);
                }
            } else { $this->view('openoffice/room_form', $data); }
        } else {
            $data = array_merge($commonData, [
                'object_title' => '', 'object_content' => '', 'object_status' => 'available',
                'meta_fields' => ['room_capacity' => 0, 'room_location' => '', 'room_equipment' => ''],
                'errors' => []
            ]);
            $this->view('openoffice/room_form', $data);
        }
    }

    public function editRoom($roomId = null) {
        if (!userHasCapability('EDIT_ROOMS')) { 
            $_SESSION['admin_message'] = 'Error: You do not have permission to edit rooms.';
            redirect('OpenOffice/rooms');
        }
        if ($roomId === null) redirect('OpenOffice/rooms');
        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId); 
        if (!$room) { 
            $_SESSION['admin_message'] = 'Room not found.';
            redirect('OpenOffice/rooms');
        }
        $commonData = [
            'pageTitle' => 'Edit Room', 'room_id' => $roomId, 'original_room_data' => $room, 
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'OpenOffice/rooms'],
                ['label' => 'Rooms', 'url' => 'OpenOffice/rooms'],
                ['label' => 'Edit Room: ' . htmlspecialchars($room['object_title'])]
            ],
            'room_statuses' => ['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance']
        ];
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
             $formData = [
                'object_title' => trim($_POST['object_title'] ?? ''),
                'object_content' => trim($_POST['object_content'] ?? ''),
                'object_status' => trim($_POST['object_status'] ?? $room['object_status']),
                'meta_fields' => [
                    'room_capacity' => filter_var(trim($_POST['room_capacity'] ?? '0'), FILTER_VALIDATE_INT),
                    'room_location' => trim($_POST['room_location'] ?? ''),
                    'room_equipment' => trim($_POST['room_equipment'] ?? '')
                ], 'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Room Name is required.';
            if ($data['meta_fields']['room_capacity'] === false || $data['meta_fields']['room_capacity'] < 0) {
                 $data['errors']['room_capacity_err'] = 'Capacity must be a valid non-negative number.';
                 $data['meta_fields']['room_capacity'] = $room['meta']['room_capacity'] ?? 0; 
            }
            if (!array_key_exists($data['object_status'], $data['room_statuses'])) $data['errors']['object_status_err'] = 'Invalid room status selected.';
            if (empty($data['errors'])) {
                $updateData = [
                    'object_title' => $data['object_title'], 'object_content' => $data['object_content'],
                    'object_status' => $data['object_status'], 'meta_fields' => $data['meta_fields']
                ];
                if ($this->roomModel->updateRoom($roomId, $updateData)) { 
                    $_SESSION['admin_message'] = 'Room updated successfully!';
                    redirect('OpenOffice/rooms');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not update room.';
                    $this->view('openoffice/room_form', $data);
                }
            } else { $this->view('openoffice/room_form', $data); }
        } else {
            $data = array_merge($commonData, [
                'object_title' => $room['object_title'], 'object_content' => $room['object_content'],
                'object_status' => $room['object_status'],
                'meta_fields' => [
                    'room_capacity' => $room['meta']['room_capacity'] ?? 0,
                    'room_location' => $room['meta']['room_location'] ?? '',
                    'room_equipment' => $room['meta']['room_equipment'] ?? ''
                ], 'errors' => []
            ]);
            $this->view('openoffice/room_form', $data);
        }
    }

    public function deleteRoom($roomId = null) {
        if (!userHasCapability('DELETE_ROOMS')) { 
            $_SESSION['admin_message'] = 'Error: You do not have permission to delete rooms.';
            redirect('OpenOffice/rooms');
        }
        if ($roomId === null) redirect('OpenOffice/rooms');
        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId); 
        if (!$room) {
            $_SESSION['admin_message'] = 'Error: Room not found.';
            redirect('OpenOffice/rooms');
        }
        $existingReservations = $this->reservationModel->getReservationsByRoomId($roomId, [], ['limit' => 1]);
        if (!empty($existingReservations)) {
            $_SESSION['admin_message'] = 'Error: Cannot delete room "' . htmlspecialchars($room['object_title']) . '". It has existing reservations.';
            redirect('OpenOffice/rooms');
            return;
        }
        if ($this->roomModel->deleteRoom($roomId)) { 
            $_SESSION['admin_message'] = 'Room "' . htmlspecialchars($room['object_title']) . '" deleted successfully.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete room "' . htmlspecialchars($room['object_title']) . '".';
        }
        redirect('OpenOffice/rooms');
    }

    // --- Room Reservation Methods ---
    public function roomreservations() {
        if (!userHasCapability('VIEW_ALL_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to view all room reservations.";
            redirect('Dashboard');
        }
        $data = [
            'pageTitle' => 'Manage Room Reservations',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'OpenOffice/rooms'],
                ['label' => 'Manage Room Reservations']
            ],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('openoffice/reservations_list', $data); 
    }

    public function createreservation($roomId = null) {
        if (!userHasCapability('CREATE_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to create room reservations.";
            redirect('OpenOffice/rooms'); 
        }
        if ($roomId === null) {
            $_SESSION['error_message'] = 'No room selected for reservation.';
            redirect('OpenOffice/rooms');
        }
        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId); 
        if (!$room || $room['object_status'] !== 'available') { 
            $_SESSION['error_message'] = 'This room is not available for reservation or does not exist.';
            redirect('OpenOffice/rooms');
        }
        
        $approvedReservationsData = []; 
        $approvedRoomReservations = $this->reservationModel->getReservationsByRoomId(
            $roomId, ['object_status' => 'approved'] 
        );

        if ($approvedRoomReservations) {
            foreach ($approvedRoomReservations as $approvedRes) {
                if (isset($approvedRes['meta']['reservation_start_datetime']) && isset($approvedRes['meta']['reservation_end_datetime'])) {
                    $approvedReservationsData[] = [
                        'start' => $approvedRes['meta']['reservation_start_datetime'],
                        'end' => $approvedRes['meta']['reservation_end_datetime']
                    ];
                }
            }
        }

        $commonData = [
            'pageTitle' => 'Book Room: ' . htmlspecialchars($room['object_title']),
            'room' => $room,
            'approved_reservations_data_for_js' => $approvedReservationsData, 
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'OpenOffice/rooms'],
                ['label' => 'Rooms', 'url' => 'OpenOffice/rooms'], 
                ['label' => 'Book Room']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $reservationDate = trim($_POST['reservation_date'] ?? '');
            $reservationTimeSlot = trim($_POST['reservation_time_slot'] ?? '');
            $reservationPurpose = trim($_POST['reservation_purpose'] ?? '');
            $formData = [
                'reservation_date' => $reservationDate, 'reservation_time_slot' => $reservationTimeSlot,
                'reservation_purpose' => $reservationPurpose, 'errors' => []
            ];
            $data = array_merge($commonData, $formData); 

            if (empty($data['reservation_date'])) $data['errors']['date_err'] = 'Reservation date is required.';
            elseif (new DateTime($data['reservation_date']) < new DateTime(date('Y-m-d'))) $data['errors']['date_err'] = 'Reservation date cannot be in the past.';
            if (empty($data['reservation_time_slot'])) $data['errors']['time_slot_err'] = 'Time slot is required.';
            if (empty($data['reservation_purpose'])) $data['errors']['purpose_err'] = 'Purpose of reservation is required.';
            
            $fullStartDateTimeStr = null; $fullEndDateTimeStr = null;
            if (!empty($data['reservation_date']) && !empty($data['reservation_time_slot'])) {
                $timeParts = explode('-', $data['reservation_time_slot']);
                if (count($timeParts) === 2) {
                    $startTime = trim($timeParts[0]); $endTime = trim($timeParts[1]);   
                    $fullStartDateTimeStr = $data['reservation_date'] . ' ' . $startTime . ':00'; 
                    $fullEndDateTimeStr = $data['reservation_date'] . ' ' . $endTime . ':00';   
                    try {
                        $startDateTimeObj = new DateTime($fullStartDateTimeStr); $endDateTimeObj = new DateTime($fullEndDateTimeStr);
                        if ($startDateTimeObj >= $endDateTimeObj) $data['errors']['time_slot_err'] = 'End time must be after start time.';
                        if ($startDateTimeObj < new DateTime()) {
                             $data['errors']['date_err'] = 'Reservation start time cannot be in the past.';
                             if (empty($data['errors']['time_slot_err'])) $data['errors']['time_slot_err'] = 'Selected time slot is in the past.';
                        }
                    } catch (Exception $e) {
                        $data['errors']['time_slot_err'] = 'Invalid time slot format processed.';
                        $fullStartDateTimeStr = null; $fullEndDateTimeStr = null;
                    }
                } else { $data['errors']['time_slot_err'] = 'Invalid time slot selected.'; }
            }
            
            if (empty($data['errors']) && $fullStartDateTimeStr && $fullEndDateTimeStr) {
                $conflicts = $this->reservationModel->getConflictingReservations(
                    $roomId, $fullStartDateTimeStr, $fullEndDateTimeStr, ['approved'] 
                );
                if ($conflicts && count($conflicts) > 0) {
                    $data['errors']['form_err'] = 'This time slot is already booked (approved).';
                }
            }

            if (empty($data['errors'])) {
                $reservationObjectData = [ 
                    'object_author' => $_SESSION['user_id'], 
                    'object_title' => 'Reservation for ' . $room['object_title'] . ' by ' . $_SESSION['display_name'],
                    'object_type' => 'reservation', 'object_parent' => $roomId, 'object_status' => 'pending', 
                    'object_content' => $data['reservation_purpose'], 
                    'meta_fields' => [
                        'reservation_start_datetime' => $fullStartDateTimeStr, 
                        'reservation_end_datetime' => $fullEndDateTimeStr,     
                        'reservation_user_id' => $_SESSION['user_id'] 
                    ]
                ];
                $reservationId = $this->reservationModel->createObject($reservationObjectData); 
                if ($reservationId) {
                    $_SESSION['message'] = 'Reservation request submitted successfully! Pending approval.';
                    $user = $this->userModel->findUserById($_SESSION['user_id']);
                    $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);
                    $formattedStartTime = format_datetime_for_display($fullStartDateTimeStr);
                    $formattedEndTime = format_datetime_for_display($fullEndDateTimeStr);
                    if ($user && !empty($user['user_email'])) {
                        send_system_email($user['user_email'], "Your Reservation for {$room['object_title']} is Pending", "Dear {$user['display_name']},\n\nYour reservation request for '{$room['object_title']}' from {$formattedStartTime} to {$formattedEndTime} is pending approval.\nPurpose: {$data['reservation_purpose']}\nView status: " . BASE_URL . "OpenOffice/myreservations");
                    }
                    if ($adminEmail) {
                        send_system_email($adminEmail, "New Room Reservation: {$room['object_title']} by {$user['display_name']}", "User: {$user['display_name']} ({$user['user_email']})\nRoom: {$room['object_title']} (ID: {$roomId})\nPurpose: {$data['reservation_purpose']}\nStart: {$formattedStartTime}\nEnd: {$formattedEndTime}\nReview: " . BASE_URL . "OpenOffice/roomreservations");
                    }
                    redirect('OpenOffice/myreservations'); 
                } else {
                    $data['errors']['form_err'] = 'Could not submit reservation request.';
                    $this->view('openoffice/reservation_form', $data);
                }
            } else {
                $data['reservation_date'] = $reservationDate; $data['reservation_time_slot'] = $reservationTimeSlot; $data['reservation_purpose'] = $reservationPurpose;
                $this->view('openoffice/reservation_form', $data);
            }
        } else {
            $data = array_merge($commonData, ['reservation_date' => date('Y-m-d'), 'reservation_time_slot' => '', 'reservation_purpose' => '', 'errors' => []]);
            $this->view('openoffice/reservation_form', $data); 
        }
    }

    public function myreservations() {
        $data = [
            'pageTitle' => 'My Room Reservations',
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], ['label' => 'My Reservations']],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled'] 
        ];
        $this->view('openoffice/my_reservations_list', $data); 
    }

    public function cancelreservation($reservationId = null) {
        if (!userHasCapability('CANCEL_OWN_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "Permission denied.";
            redirect('OpenOffice/myreservations');
        }
        if ($reservationId === null) { $_SESSION['error_message'] = 'No reservation ID.'; redirect('OpenOffice/myreservations'); }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId); 
        if (!$reservation || $reservation['object_type'] !== 'reservation') { $_SESSION['error_message'] = 'Reservation not found.'; }
        elseif ($reservation['object_author'] != $_SESSION['user_id']) { $_SESSION['error_message'] = 'Not your reservation.'; }
        elseif ($reservation['object_status'] !== 'pending') { $_SESSION['error_message'] = 'Only pending can be cancelled.'; }
        else {
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'cancelled'])) {
                $_SESSION['message'] = 'Reservation cancelled.';
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']); 
                $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);
                if ($adminEmail && $user && $roomFromDb) {
                    send_system_email($adminEmail, "Reservation Cancelled: {$roomFromDb['object_title']}", "User {$user['display_name']} cancelled reservation ID {$reservationId} for '{$roomFromDb['object_title']}'.\nPurpose: {$reservation['object_content']}\nStart: " . format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . "\nEnd: " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? ''));
                }
            } else { $_SESSION['error_message'] = 'Could not cancel reservation.'; }
        }
        redirect('OpenOffice/myreservations');
    }

    public function approvereservation($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "Permission denied.";
            redirect('OpenOffice/roomreservations'); 
        }
        if ($reservationId === null) { $_SESSION['admin_message'] = 'No reservation ID.'; redirect('OpenOffice/roomreservations'); }
        $reservationId = (int)$reservationId;
        $reservationToApprove = $this->reservationModel->getObjectById($reservationId); 
        if (!$reservationToApprove || $reservationToApprove['object_type'] !== 'reservation') { $_SESSION['admin_message'] = 'Reservation not found.'; }
        elseif ($reservationToApprove['object_status'] !== 'pending') { $_SESSION['admin_message'] = 'Only pending can be approved. Status: ' . $reservationToApprove['object_status'] . '.'; }
        else {
            $roomId = $reservationToApprove['object_parent'];
            $startTime = $reservationToApprove['meta']['reservation_start_datetime'] ?? null;
            $endTime = $reservationToApprove['meta']['reservation_end_datetime'] ?? null;
            if (!$startTime || !$endTime) { $_SESSION['admin_message'] = 'Missing time data.'; redirect('OpenOffice/roomreservations'); return; }
            $approvedConflicts = $this->reservationModel->getConflictingReservations($roomId, $startTime, $endTime, ['approved'], $reservationId);
            if ($approvedConflicts && count($approvedConflicts) > 0) { $_SESSION['admin_message'] = 'Conflicts with existing approved reservation.'; }
            else {
                if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'approved'])) { 
                    $_SESSION['admin_message'] = 'Reservation approved.';
                    $user = $this->userModel->findUserById($reservationToApprove['object_author']);
                    $roomFromDb = $this->roomModel->getRoomById($roomId); 
                    if ($user && !empty($user['user_email']) && $roomFromDb) {
                        send_system_email($user['user_email'], "Reservation for {$roomFromDb['object_title']} Approved", "Dear {$user['display_name']},\n\nYour reservation for '{$roomFromDb['object_title']}' from " . format_datetime_for_display($startTime) . " to " . format_datetime_for_display($endTime) . " is approved.\nPurpose: {$reservationToApprove['object_content']}");
                    }
                    $overlappingPending = $this->reservationModel->getConflictingReservations($roomId, $startTime, $endTime, ['pending'], $reservationId);
                    if ($overlappingPending) {
                        $deniedCount = 0;
                        foreach ($overlappingPending as $pendingConflict) {
                            if ($this->reservationModel->updateObject($pendingConflict['object_id'], ['object_status' => 'denied'])) { 
                                $deniedCount++;
                                $conflictUser = $this->userModel->findUserById($pendingConflict['object_author']);
                                $conflictRoom = $this->roomModel->getRoomById($roomId); 
                                if ($conflictUser && !empty($conflictUser['user_email']) && $conflictRoom) {
                                    send_system_email($conflictUser['user_email'], "Reservation for {$conflictRoom['object_title']} Denied", "Dear {$conflictUser['display_name']},\n\nYour reservation for '{$conflictRoom['object_title']}' for " . format_datetime_for_display($pendingConflict['reservation_start_datetime']) . " to " . format_datetime_for_display($pendingConflict['reservation_end_datetime']) . " was denied due to conflict.");
                                }
                            }
                        }
                        if ($deniedCount > 0) $_SESSION['admin_message'] .= " {$deniedCount} overlapping pending auto-denied.";
                    }
                } else { $_SESSION['admin_message'] = 'Could not approve reservation.'; }
            }
        }
        redirect('OpenOffice/roomreservations');
    }

    public function denyreservation($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "Permission denied.";
            redirect('OpenOffice/roomreservations'); 
        }
        if ($reservationId === null) { $_SESSION['admin_message'] = 'No reservation ID.'; redirect('OpenOffice/roomreservations'); }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId); 
        if (!$reservation || $reservation['object_type'] !== 'reservation') { $_SESSION['admin_message'] = 'Reservation not found.'; }
        elseif (!in_array($reservation['object_status'], ['pending', 'approved'])) { $_SESSION['admin_message'] = 'Only pending/approved can be denied. Status: ' . $reservation['object_status'] . '.'; }
        else {
            $originalStatus = $reservation['object_status'];
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'denied'])) { 
                $_SESSION['admin_message'] = 'Reservation ' . ($originalStatus === 'approved' ? 'revoked/denied.' : 'denied.');
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']); 
                if ($user && !empty($user['user_email']) && $roomFromDb) {
                     send_system_email($user['user_email'], "Reservation for {$roomFromDb['object_title']} " . ($originalStatus === 'approved' ? 'Revoked/Denied' : 'Denied'), "Dear {$user['display_name']},\n\nYour reservation for '{$roomFromDb['object_title']}' for " . format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . " to " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '') . " has been " . ($originalStatus === 'approved' ? 'revoked/denied.' : 'denied.'));
                }
            } else { $_SESSION['admin_message'] = 'Could not deny reservation.'; }
        }
        redirect('OpenOffice/roomreservations');
    }

    public function editMyReservation($reservationId = null) {
        if (!userHasCapability('EDIT_OWN_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "Permission denied.";
            redirect('OpenOffice/myreservations');
        }
        $_SESSION['message'] = "Editing reservations not yet implemented. ID: {$reservationId}";
        redirect('OpenOffice/myreservations');
    }

    public function editAnyReservation($reservationId = null) {
        if (!userHasCapability('EDIT_ANY_ROOM_RESERVATION')) {
            $_SESSION['error_message'] = "Permission denied.";
            redirect('OpenOffice/roomreservations');
        }
        $_SESSION['admin_message'] = "Editing any reservation not yet implemented. ID: {$reservationId}";
        redirect('OpenOffice/roomreservations');
    }

    public function deleteAnyReservation($reservationId = null) {
        if (!userHasCapability('DELETE_ANY_ROOM_RESERVATION')) {
            $_SESSION['error_message'] = "Permission denied.";
            redirect('OpenOffice/roomreservations');
        }
        $_SESSION['admin_message'] = "Deleting reservation records not yet implemented. ID: {$reservationId}";
        redirect('OpenOffice/roomreservations');
    }

    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data); 
            require_once $viewFile;
        } else {
            error_log("OpenOffice view file not found: {$viewFile}");
            die('Error: View not found. Attempted: ' . $view);
        }
    }
}
