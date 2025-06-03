<?php

/**
 * OpenofficeController
 * (Note: Filename should be OpenofficeController.php)
 *
 * Handles operations related to the Open Office module, including Rooms and Reservations.
 */
class OpenofficeController { // Class name remains PascalCase as per PSR standards
    private $pdo;
    private $reservationModel; 
    private $roomModel;        
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
        $this->roomModel = new RoomModel($this->pdo); 
        $this->userModel = new UserModel($this->pdo); 
        $this->optionModel = new OptionModel($this->pdo); 

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
    }

    // --- Room Management Methods ---
    /**
     * Display the list of rooms.
     * Protected by VIEW_ROOMS capability.
     */
    public function rooms() {
        if (!userHasCapability('VIEW_ROOMS')) {
            $_SESSION['error_message'] = "You do not have permission to view rooms.";
            redirect('dashboard');
        }

        $rooms = $this->roomModel->getAllRooms(); 
        
        $data = [
            'pageTitle' => 'Manage Rooms',
            'rooms' => $rooms,
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], ['label' => 'Rooms List']] // URL can remain OpenOffice due to router
        ];
        $this->view('openoffice/rooms_list', $data);
    }

    /**
     * Display form to add a new room OR process adding a new room.
     * Protected by CREATE_ROOMS capability.
     */
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
                ],
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Room Name is required.';
            if ($data['meta_fields']['room_capacity'] === false || $data['meta_fields']['room_capacity'] < 0) {
                 $data['errors']['room_capacity_err'] = 'Capacity must be a valid non-negative number.';
                 $data['meta_fields']['room_capacity'] = 0; 
            }
            if (!array_key_exists($data['object_status'], $data['room_statuses'])) {
                $data['errors']['object_status_err'] = 'Invalid room status selected.';
            }

            if (empty($data['errors'])) {
                $roomData = [ 
                    'object_author' => $_SESSION['user_id'], 
                    'object_title' => $data['object_title'],
                    'object_content' => $data['object_content'],
                    'object_status' => $data['object_status'], 
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
            } else {
                $this->view('openoffice/room_form', $data);
            }
        } else {
            $data = array_merge($commonData, [
                'object_title' => '', 'object_content' => '', 'object_status' => 'available',
                'meta_fields' => ['room_capacity' => 0, 'room_location' => '', 'room_equipment' => ''],
                'errors' => []
            ]);
            $this->view('openoffice/room_form', $data);
        }
    }

    /**
     * Display form to edit an existing room OR process updating an existing room.
     * Protected by EDIT_ROOMS capability.
     */
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
            'pageTitle' => 'Edit Room',
            'room_id' => $roomId, 
            'original_room_data' => $room, 
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
                ],
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            
            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Room Name is required.';
            if ($data['meta_fields']['room_capacity'] === false || $data['meta_fields']['room_capacity'] < 0) {
                 $data['errors']['room_capacity_err'] = 'Capacity must be a valid non-negative number.';
                 $data['meta_fields']['room_capacity'] = $room['meta']['room_capacity'] ?? 0; 
            }
            if (!array_key_exists($data['object_status'], $data['room_statuses'])) {
                $data['errors']['object_status_err'] = 'Invalid room status selected.';
            }

            if (empty($data['errors'])) {
                $updateData = [
                    'object_title' => $data['object_title'],
                    'object_content' => $data['object_content'],
                    'object_status' => $data['object_status'],
                    'meta_fields' => $data['meta_fields']
                ];
                if ($this->roomModel->updateRoom($roomId, $updateData)) { 
                    $_SESSION['admin_message'] = 'Room updated successfully!';
                    redirect('OpenOffice/rooms');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not update room.';
                    $this->view('openoffice/room_form', $data);
                }
            } else {
                $this->view('openoffice/room_form', $data);
            }

        } else {
            $data = array_merge($commonData, [
                'object_title' => $room['object_title'],
                'object_content' => $room['object_content'],
                'object_status' => $room['object_status'],
                'meta_fields' => [
                    'room_capacity' => $room['meta']['room_capacity'] ?? 0,
                    'room_location' => $room['meta']['room_location'] ?? '',
                    'room_equipment' => $room['meta']['room_equipment'] ?? ''
                ],
                'errors' => []
            ]);
            $this->view('openoffice/room_form', $data);
        }
    }

    /**
     * Delete a room.
     * Protected by DELETE_ROOMS capability.
     */
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
            $_SESSION['admin_message'] = 'Error: Cannot delete room "' . htmlspecialchars($room['object_title']) . '". It has existing reservations. Please manage or delete them first.';
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
            redirect('dashboard');
        }
        
        $reservations = $this->reservationModel->getAllReservations([], [
            'orderby' => 'object_date', 
            'orderdir' => 'DESC',
            'include_meta' => true 
        ]);

        if ($reservations) {
            foreach ($reservations as &$res) {
                if (!empty($res['object_parent'])) { 
                    $roomFromDb = $this->roomModel->getRoomById($res['object_parent']); 
                    $res['room_name'] = $roomFromDb ? $roomFromDb['object_title'] : 'Unknown Room';
                } else {
                    $res['room_name'] = 'N/A';
                }
                if (!empty($res['object_author'])) { 
                    $user = $this->userModel->findUserById($res['object_author']);
                    $res['user_display_name'] = $user ? $user['display_name'] : 'Unknown User';
                } else {
                    $res['user_display_name'] = 'N/A';
                }
            }
            unset($res); 
        }
        
        $data = [
            'pageTitle' => 'Manage Room Reservations',
            'reservations' => $reservations,
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
            $roomId,
            ['object_status' => 'approved'] 
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
            'approved_reservations_json' => json_encode($approvedReservationsData), 
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
                'reservation_date' => $reservationDate,
                'reservation_time_slot' => $reservationTimeSlot,
                'reservation_purpose' => $reservationPurpose,
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            if (empty($data['reservation_date'])) $data['errors']['date_err'] = 'Reservation date is required.';
            elseif (new DateTime($data['reservation_date']) < new DateTime(date('Y-m-d'))) $data['errors']['date_err'] = 'Reservation date cannot be in the past.';
            if (empty($data['reservation_time_slot'])) $data['errors']['time_slot_err'] = 'Time slot is required.';
            if (empty($data['reservation_purpose'])) $data['errors']['purpose_err'] = 'Purpose of reservation is required.';

            $fullStartDateTimeStr = null;
            $fullEndDateTimeStr = null;

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
                    $data['errors']['form_err'] = 'This time slot is already booked (approved reservation exists). Please choose a different time or date.';
                }
            }

            if (empty($data['errors'])) {
                $reservationObjectData = [ 
                    'object_author' => $_SESSION['user_id'], 
                    'object_title' => 'Reservation for ' . $room['object_title'] . ' by ' . $_SESSION['display_name'],
                    'object_type' => 'reservation', 
                    'object_parent' => $roomId, 
                    'object_status' => 'pending', 
                    'object_content' => $data['reservation_purpose'], 
                    'meta_fields' => [
                        'reservation_start_datetime' => $fullStartDateTimeStr, 
                        'reservation_end_datetime' => $fullEndDateTimeStr,     
                        'reservation_user_id' => $_SESSION['user_id'] 
                    ]
                ];
                $reservationId = $this->reservationModel->createObject($reservationObjectData); 

                if ($reservationId) {
                    $_SESSION['message'] = 'Reservation request submitted successfully! It is now pending approval.';
                    $user = $this->userModel->findUserById($_SESSION['user_id']);
                    $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);
                    $formattedStartTime = format_datetime_for_display($fullStartDateTimeStr);
                    $formattedEndTime = format_datetime_for_display($fullEndDateTimeStr);
                    if ($user && !empty($user['user_email'])) {
                        send_system_email($user['user_email'], "Your Reservation Request for {$room['object_title']} is Pending", "Dear {$user['display_name']},\n\nYour reservation request for '{$room['object_title']}' from {$formattedStartTime} to {$formattedEndTime} is pending approval.\n\nPurpose: {$data['reservation_purpose']}\n\nView status: " . BASE_URL . "OpenOffice/myreservations");
                    }
                    if ($adminEmail) {
                        send_system_email($adminEmail, "New Room Reservation Request: {$room['object_title']} by {$user['display_name']}", "User: {$user['display_name']} ({$user['user_email']})\nRoom: {$room['object_title']} (ID: {$roomId})\nPurpose: {$data['reservation_purpose']}\nStart: {$formattedStartTime}\nEnd: {$formattedEndTime}\n\nReview: " . BASE_URL . "OpenOffice/roomreservations");
                    }
                    redirect('OpenOffice/myreservations'); 
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not submit reservation request.';
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
        $userId = $_SESSION['user_id'];
        $myReservations = $this->reservationModel->getReservationsByUserId($userId, [
            'orderby' => 'object_date', 'orderdir' => 'DESC', 'include_meta' => true
        ]);
        
        if ($myReservations) {
            foreach ($myReservations as &$res) {
                 if (!empty($res['object_parent'])) { 
                    $roomFromDb = $this->roomModel->getRoomById($res['object_parent']); 
                    $res['room_name'] = $roomFromDb ? $roomFromDb['object_title'] : 'Unknown Room';
                } else { $res['room_name'] = 'N/A'; }
            }
            unset($res);
        }

        $data = [
            'pageTitle' => 'My Room Reservations', 'reservations' => $myReservations,
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], ['label' => 'My Reservations']],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('openoffice/my_reservations_list', $data); 
    }

    public function cancelreservation($reservationId = null) {
        if (!userHasCapability('CANCEL_OWN_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to cancel reservations.";
            redirect('OpenOffice/myreservations');
        }
        if ($reservationId === null) { $_SESSION['error_message'] = 'No reservation ID specified.'; redirect('OpenOffice/myreservations'); }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId); 

        if (!$reservation || $reservation['object_type'] !== 'reservation') { $_SESSION['error_message'] = 'Reservation not found.'; }
        elseif ($reservation['object_author'] != $_SESSION['user_id']) { $_SESSION['error_message'] = 'You can only cancel your own reservations.'; }
        elseif ($reservation['object_status'] !== 'pending') { $_SESSION['error_message'] = 'Only pending reservations can be cancelled.'; }
        else {
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'cancelled'])) {
                $_SESSION['message'] = 'Reservation cancelled successfully.';
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']); 
                $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);
                if ($adminEmail && $user && $roomFromDb) {
                    send_system_email($adminEmail, "Reservation Cancelled by User: {$roomFromDb['object_title']}", "User {$user['display_name']} cancelled reservation ID {$reservationId} for room '{$roomFromDb['object_title']}'.\nPurpose: {$reservation['object_content']}\nStart: " . format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . "\nEnd: " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? ''));
                }
            } else { $_SESSION['error_message'] = 'Could not cancel reservation.'; }
        }
        redirect('OpenOffice/myreservations');
    }

    public function approvereservation($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to approve or deny reservations.";
            redirect('OpenOffice/roomreservations'); 
        }
        if ($reservationId === null) { $_SESSION['admin_message'] = 'No reservation ID specified for approval.'; redirect('OpenOffice/roomreservations'); }
        $reservationId = (int)$reservationId;
        $reservationToApprove = $this->reservationModel->getObjectById($reservationId); 

        if (!$reservationToApprove || $reservationToApprove['object_type'] !== 'reservation') { $_SESSION['admin_message'] = 'Reservation not found for approval.'; }
        elseif ($reservationToApprove['object_status'] !== 'pending') { $_SESSION['admin_message'] = 'Only pending reservations can be approved. This one is already ' . $reservationToApprove['object_status'] . '.'; }
        else {
            $roomId = $reservationToApprove['object_parent'];
            $startTime = $reservationToApprove['meta']['reservation_start_datetime'] ?? null;
            $endTime = $reservationToApprove['meta']['reservation_end_datetime'] ?? null;

            if (!$startTime || !$endTime) { $_SESSION['admin_message'] = 'Error: Reservation is missing start or end time data.'; redirect('OpenOffice/roomreservations'); return; }

            $approvedConflicts = $this->reservationModel->getConflictingReservations($roomId, $startTime, $endTime, ['approved'], $reservationId);

            if ($approvedConflicts && count($approvedConflicts) > 0) { $_SESSION['admin_message'] = 'Error: Cannot approve. This time slot conflicts with an existing approved reservation.'; }
            else {
                if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'approved'])) { 
                    $_SESSION['admin_message'] = 'Reservation approved successfully.';
                    $user = $this->userModel->findUserById($reservationToApprove['object_author']);
                    $roomFromDb = $this->roomModel->getRoomById($roomId); 
                    if ($user && !empty($user['user_email']) && $roomFromDb) {
                        send_system_email($user['user_email'], "Your Reservation for {$roomFromDb['object_title']} has been Approved", "Dear {$user['display_name']},\n\nYour reservation for '{$roomFromDb['object_title']}' from " . format_datetime_for_display($startTime) . " to " . format_datetime_for_display($endTime) . " has been approved.\n\nPurpose: {$reservationToApprove['object_content']}");
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
                                    send_system_email($conflictUser['user_email'], "Your Reservation Request for {$conflictRoom['object_title']} was Denied", "Dear {$conflictUser['display_name']},\n\nYour reservation for '{$conflictRoom['object_title']}' for " . format_datetime_for_display($pendingConflict['reservation_start_datetime']) . " to " . format_datetime_for_display($pendingConflict['reservation_end_datetime']) . " was denied due to a conflict.");
                                }
                            }
                        }
                        if ($deniedCount > 0) $_SESSION['admin_message'] .= " {$deniedCount} overlapping pending reservation(s) automatically denied.";
                    }
                } else { $_SESSION['admin_message'] = 'Could not approve reservation due to a system error.'; }
            }
        }
        redirect('OpenOffice/roomreservations');
    }

    public function denyreservation($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to approve or deny reservations.";
            redirect('OpenOffice/roomreservations'); 
        }
        if ($reservationId === null) { $_SESSION['admin_message'] = 'No reservation ID specified for denial.'; redirect('OpenOffice/roomreservations'); }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId); 

        if (!$reservation || $reservation['object_type'] !== 'reservation') { $_SESSION['admin_message'] = 'Reservation not found for denial.'; }
        elseif (!in_array($reservation['object_status'], ['pending', 'approved'])) { $_SESSION['admin_message'] = 'Only pending or approved reservations can be denied/revoked. This one is ' . $reservation['object_status'] . '.'; }
        else {
            $originalStatus = $reservation['object_status'];
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'denied'])) { 
                $_SESSION['admin_message'] = 'Reservation ' . ($originalStatus === 'approved' ? 'approval revoked and reservation denied.' : 'denied successfully.');
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']); 
                if ($user && !empty($user['user_email']) && $roomFromDb) {
                     send_system_email($user['user_email'], "Your Reservation Request for {$roomFromDb['object_title']} was " . ($originalStatus === 'approved' ? 'Revoked/Denied' : 'Denied'), "Dear {$user['display_name']},\n\nYour reservation for '{$roomFromDb['object_title']}' for " . format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . " to " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '') . " has been " . ($originalStatus === 'approved' ? 'revoked/denied.' : 'denied.'));
                }
            } else { $_SESSION['admin_message'] = 'Could not deny reservation.'; }
        }
        redirect('OpenOffice/roomreservations');
    }

    public function editMyReservation($reservationId = null) {
        if (!userHasCapability('EDIT_OWN_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to edit your reservations.";
            redirect('OpenOffice/myreservations');
        }
        $_SESSION['message'] = "Editing reservations is not yet implemented. Reservation ID: {$reservationId}";
        redirect('OpenOffice/myreservations');
    }

    public function editAnyReservation($reservationId = null) {
        if (!userHasCapability('EDIT_ANY_ROOM_RESERVATION')) {
            $_SESSION['error_message'] = "You do not have permission to edit this reservation.";
            redirect('OpenOffice/roomreservations');
        }
        $_SESSION['admin_message'] = "Editing any reservation is not yet implemented. Reservation ID: {$reservationId}";
        redirect('OpenOffice/roomreservations');
    }

    public function deleteAnyReservation($reservationId = null) {
        if (!userHasCapability('DELETE_ANY_ROOM_RESERVATION')) {
            $_SESSION['error_message'] = "You do not have permission to delete reservation records.";
            redirect('OpenOffice/roomreservations');
        }
        $_SESSION['admin_message'] = "Deleting reservation records is not yet implemented. Reservation ID: {$reservationId}";
        redirect('OpenOffice/roomreservations');
    }


    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data); 
            require_once $viewFile;
        } else {
            error_log("OpenOffice view file not found: {$viewFile}");
            die('Error: View not found. Please contact support. Attempted to load: ' . $view);
        }
    }
}
