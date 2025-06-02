<?php

/**
 * OpenOfficeController
 *
 * Handles operations related to the Open Office module, including Rooms and Reservations.
 */
class OpenOfficeController {
    private $pdo;
    private $objectModel;
    private $userModel; 
    private $optionModel; 

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->objectModel = new ObjectModel($this->pdo);
        $this->userModel = new UserModel($this->pdo); 
        $this->optionModel = new OptionModel($this->pdo); 

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
    }

    // --- Room Management Methods (addRoom, editRoom, deleteRoom, rooms) ---
    // These methods remain largely the same as before.
    // For brevity, they are not repeated in full here but assume they exist as previously defined.
    public function rooms() {
        if (!userHasCapability('MANAGE_ROOMS')) {
            $_SESSION['error_message'] = "You do not have permission to manage rooms.";
            redirect('dashboard');
        }
        $rooms = $this->objectModel->getObjectsByType('room', ['orderby' => 'object_title', 'orderdir' => 'ASC']);
        $data = [
            'pageTitle' => 'Manage Rooms',
            'rooms' => $rooms,
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'openoffice/rooms'], ['label' => 'Manage Rooms']]
        ];
        $this->view('openoffice/rooms_list', $data);
    }

    public function addRoom() {
        // ... (implementation as before) ...
        if (!userHasCapability('MANAGE_ROOMS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to add rooms.';
            redirect('openoffice/rooms');
        }

        $commonData = [
            'pageTitle' => 'Add New Room',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Manage Rooms', 'url' => 'openoffice/rooms'],
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
                $objectData = [
                    'object_author' => $_SESSION['user_id'], 
                    'object_title' => $data['object_title'],
                    'object_type' => 'room',
                    'object_content' => $data['object_content'],
                    'object_status' => $data['object_status'], 
                    'meta_fields' => $data['meta_fields']
                ];

                $roomId = $this->objectModel->createObject($objectData);

                if ($roomId) {
                    $_SESSION['admin_message'] = 'Room created successfully!';
                    redirect('openoffice/rooms');
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
    public function editRoom($roomId = null) {
        // ... (implementation as before) ...
        if (!userHasCapability('MANAGE_ROOMS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to edit rooms.';
            redirect('openoffice/rooms');
        }
        if ($roomId === null) redirect('openoffice/rooms');
        $roomId = (int)$roomId;
        
        $room = $this->objectModel->getObjectById($roomId);

        if (!$room || $room['object_type'] !== 'room') {
            $_SESSION['admin_message'] = 'Room not found or invalid type.';
            redirect('openoffice/rooms');
        }

        $commonData = [
            'pageTitle' => 'Edit Room',
            'room_id' => $roomId, 
            'original_room_data' => $room, 
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Manage Rooms', 'url' => 'openoffice/rooms'],
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

                if ($this->objectModel->updateObject($roomId, $updateData)) {
                    $_SESSION['admin_message'] = 'Room updated successfully!';
                    redirect('openoffice/rooms');
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
    public function deleteRoom($roomId = null) {
        // ... (implementation as before) ...
        if (!userHasCapability('MANAGE_ROOMS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to delete rooms.';
            redirect('openoffice/rooms');
        }
        if ($roomId === null) redirect('openoffice/rooms');
        $roomId = (int)$roomId;

        $room = $this->objectModel->getObjectById($roomId);
        if (!$room || $room['object_type'] !== 'room') {
            $_SESSION['admin_message'] = 'Error: Room not found or invalid type.';
            redirect('openoffice/rooms');
        }

        $existingReservations = $this->objectModel->getObjectsByConditions('reservation', ['object_parent' => $roomId]);
        if (!empty($existingReservations)) {
            $_SESSION['admin_message'] = 'Error: Cannot delete room "' . htmlspecialchars($room['object_title']) . '". It has existing reservations. Please manage or delete them first.';
            redirect('openoffice/rooms');
            return;
        }

        if ($this->objectModel->deleteObject($roomId)) {
            $_SESSION['admin_message'] = 'Room "' . htmlspecialchars($room['object_title']) . '" deleted successfully.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete room "' . htmlspecialchars($room['object_title']) . '".';
        }
        redirect('openoffice/rooms');
    }

    // --- Room Reservation Methods ---

    public function roomreservations() {
        // ... (implementation as before) ...
        if (!userHasCapability('MANAGE_OPEN_OFFICE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to manage room reservations.";
            redirect('dashboard');
        }

        $reservations = $this->objectModel->getObjectsByType('reservation', [
            'orderby' => 'object_date', 
            'orderdir' => 'DESC',
            'include_meta' => true
        ]);

        if ($reservations) {
            foreach ($reservations as &$res) {
                if (!empty($res['object_parent'])) { 
                    $roomFromDb = $this->objectModel->getObjectById($res['object_parent']); // Renamed to avoid conflict
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
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Manage Room Reservations']
            ],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('openoffice/reservations_list', $data); 
    }

    public function createreservation($roomId = null) {
        if ($roomId === null) {
            $_SESSION['error_message'] = 'No room selected for reservation.';
            redirect('openoffice/rooms');
        }
        $roomId = (int)$roomId;
        $room = $this->objectModel->getObjectById($roomId);

        if (!$room || $room['object_type'] !== 'room' || $room['object_status'] !== 'available') {
            $_SESSION['error_message'] = 'This room is not available for reservation or does not exist.';
            redirect('openoffice/rooms');
        }

        // Fetch approved reservations for this room to pass to the view for dynamic slot filtering
        $approvedReservationsData = [];
        $approvedRoomReservations = $this->objectModel->getObjectsByConditions(
            'reservation',
            ['object_parent' => $roomId, 'object_status' => 'approved']
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
            'approved_reservations_json' => json_encode($approvedReservationsData), // Pass as JSON
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Manage Rooms', 'url' => 'openoffice/rooms'], 
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

            if (empty($data['reservation_date'])) {
                $data['errors']['date_err'] = 'Reservation date is required.';
            } elseif (new DateTime($data['reservation_date']) < new DateTime(date('Y-m-d'))) {
                $data['errors']['date_err'] = 'Reservation date cannot be in the past.';
            }

            if (empty($data['reservation_time_slot'])) {
                $data['errors']['time_slot_err'] = 'Time slot is required.';
            }

            if (empty($data['reservation_purpose'])) {
                $data['errors']['purpose_err'] = 'Purpose of reservation is required.';
            }

            $fullStartDateTimeStr = null;
            $fullEndDateTimeStr = null;

            if (!empty($data['reservation_date']) && !empty($data['reservation_time_slot'])) {
                $timeParts = explode('-', $data['reservation_time_slot']);
                if (count($timeParts) === 2) {
                    $startTime = trim($timeParts[0]); 
                    $endTime = trim($timeParts[1]);   

                    $fullStartDateTimeStr = $data['reservation_date'] . ' ' . $startTime . ':00'; 
                    $fullEndDateTimeStr = $data['reservation_date'] . ' ' . $endTime . ':00';   
                    
                    try {
                        $startDateTimeObj = new DateTime($fullStartDateTimeStr);
                        $endDateTimeObj = new DateTime($fullEndDateTimeStr);

                        if ($startDateTimeObj >= $endDateTimeObj) { 
                            $data['errors']['time_slot_err'] = 'End time must be after start time (logic error).';
                        }
                        if ($startDateTimeObj < new DateTime()) {
                             $data['errors']['date_err'] = 'Reservation start time cannot be in the past.';
                             if (empty($data['errors']['time_slot_err'])) $data['errors']['time_slot_err'] = 'Selected time slot is in the past.';
                        }

                    } catch (Exception $e) {
                        $data['errors']['time_slot_err'] = 'Invalid time slot format processed.';
                        $fullStartDateTimeStr = null; 
                        $fullEndDateTimeStr = null;
                    }
                } else {
                    $data['errors']['time_slot_err'] = 'Invalid time slot selected.';
                }
            }
            
            if (empty($data['errors']) && $fullStartDateTimeStr && $fullEndDateTimeStr) {
                $conflicts = $this->objectModel->getConflictingReservations(
                    $roomId, 
                    $fullStartDateTimeStr, 
                    $fullEndDateTimeStr,
                    ['approved'] 
                );
                if ($conflicts && count($conflicts) > 0) {
                    $data['errors']['form_err'] = 'This time slot is already booked (approved reservation exists). Please choose a different time or date.';
                }
            }

            if (empty($data['errors'])) {
                $reservationData = [
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

                $reservationId = $this->objectModel->createObject($reservationData);

                if ($reservationId) {
                    $_SESSION['message'] = 'Reservation request submitted successfully! It is now pending approval.';
                    
                    $user = $this->userModel->findUserById($_SESSION['user_id']);
                    $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);
                    $siteName = $this->optionModel->getOption('site_name', 'Mainsystem');

                    $formattedStartTime = format_datetime_for_display($fullStartDateTimeStr);
                    $formattedEndTime = format_datetime_for_display($fullEndDateTimeStr);

                    if ($user && !empty($user['user_email'])) {
                        $userSubject = "Your Reservation Request for {$room['object_title']} is Pending";
                        $userMessage = "Dear {$user['display_name']},\n\n" .
                                       "Your reservation request for the room '{$room['object_title']}' has been received and is now pending approval.\n" .
                                       "Details:\n" .
                                       "Room: {$room['object_title']}\n" .
                                       "Purpose: {$data['reservation_purpose']}\n" .
                                       "Start Time: {$formattedStartTime}\n" .
                                       "End Time: {$formattedEndTime}\n\n" .
                                       "You will be notified once your request is processed.\n" .
                                       "You can view your reservations here: " . BASE_URL . "openoffice/myreservations";
                        send_system_email($user['user_email'], $userSubject, $userMessage);
                    }

                    if ($adminEmail) {
                        $adminSubject = "New Room Reservation Request: {$room['object_title']} by {$user['display_name']}";
                        $adminMessage = "A new room reservation request has been submitted:\n\n" .
                                        "User: {$user['display_name']} ({$user['user_email']})\n" .
                                        "Room: {$room['object_title']} (ID: {$roomId})\n" .
                                        "Purpose: {$data['reservation_purpose']}\n" .
                                        "Requested Start: {$formattedStartTime}\n" .
                                        "Requested End: {$formattedEndTime}\n\n" .
                                        "Please review this request in the admin panel: " . BASE_URL . "openoffice/roomreservations";
                        send_system_email($adminEmail, $adminSubject, $adminMessage);
                    }

                    redirect('openoffice/myreservations'); 
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not submit reservation request.';
                    $this->view('openoffice/reservation_form', $data);
                }
            } else {
                $data['reservation_date'] = $reservationDate;
                $data['reservation_time_slot'] = $reservationTimeSlot;
                $data['reservation_purpose'] = $reservationPurpose;
                $this->view('openoffice/reservation_form', $data);
            }
        } else {
            // GET Request: Pass approved reservations data
            $data = array_merge($commonData, [
                'reservation_date' => date('Y-m-d'), 
                'reservation_time_slot' => '', 
                'reservation_purpose' => '',
                'errors' => [],
                // 'approved_reservations_json' is already in $commonData
            ]);
            $this->view('openoffice/reservation_form', $data); 
        }
    }

    public function myreservations() {
        // ... (implementation as before) ...
        $userId = $_SESSION['user_id'];
        
        $myReservations = $this->objectModel->getObjectsByConditions('reservation', ['object_author' => $userId], [
            'orderby' => 'object_date', 
            'orderdir' => 'DESC',
            'include_meta' => true
        ]);
        
        if ($myReservations) {
            foreach ($myReservations as &$res) {
                 if (!empty($res['object_parent'])) { 
                    $roomFromDb = $this->objectModel->getObjectById($res['object_parent']); // Renamed
                    $res['room_name'] = $roomFromDb ? $roomFromDb['object_title'] : 'Unknown Room';
                } else {
                    $res['room_name'] = 'N/A';
                }
            }
            unset($res);
        }

        $data = [
            'pageTitle' => 'My Room Reservations',
            'reservations' => $myReservations,
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'My Reservations']
            ],
             'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('openoffice/my_reservations_list', $data); 
    }

    public function cancelreservation($reservationId = null) {
        // ... (implementation as before, including email notification) ...
        if ($reservationId === null) {
            $_SESSION['error_message'] = 'No reservation ID specified.';
            redirect('openoffice/myreservations');
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->objectModel->getObjectById($reservationId);

        if (!$reservation || $reservation['object_type'] !== 'reservation') {
            $_SESSION['error_message'] = 'Reservation not found.';
        } elseif ($reservation['object_author'] != $_SESSION['user_id']) {
            $_SESSION['error_message'] = 'You can only cancel your own reservations.';
        } elseif ($reservation['object_status'] !== 'pending') {
            $_SESSION['error_message'] = 'Only pending reservations can be cancelled.';
        } else {
            if ($this->objectModel->updateObject($reservationId, ['object_status' => 'cancelled'])) {
                $_SESSION['message'] = 'Reservation cancelled successfully.';
                
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->objectModel->getObjectById($reservation['object_parent']); // Renamed
                $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);
                
                if ($adminEmail && $user && $roomFromDb) {
                    $subject = "Reservation Cancelled by User: {$roomFromDb['object_title']}";
                    $message = "The following reservation request has been cancelled by the user ({$user['display_name']}):\n\n" .
                               "Room: {$roomFromDb['object_title']}\n" .
                               "Original Start: " . format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . "\n" .
                               "Original End: " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '') . "\n" .
                               "Purpose: {$reservation['object_content']}\n\n" .
                               "Reservation ID: {$reservationId}";
                    send_system_email($adminEmail, $subject, $message);
                }
            } else {
                $_SESSION['error_message'] = 'Could not cancel reservation.';
            }
        }
        redirect('openoffice/myreservations');
    }

    public function approvereservation($reservationId = null) {
        // ... (implementation as before, including email notifications) ...
        if (!userHasCapability('MANAGE_OPEN_OFFICE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to approve reservations.";
            redirect('openoffice/roomreservations'); 
        }
        if ($reservationId === null) {
            $_SESSION['admin_message'] = 'No reservation ID specified for approval.';
            redirect('openoffice/roomreservations');
        }
        $reservationId = (int)$reservationId;
        $reservationToApprove = $this->objectModel->getObjectById($reservationId);

        if (!$reservationToApprove || $reservationToApprove['object_type'] !== 'reservation') {
            $_SESSION['admin_message'] = 'Reservation not found for approval.';
        } elseif ($reservationToApprove['object_status'] !== 'pending') {
            $_SESSION['admin_message'] = 'Only pending reservations can be approved. This one is already ' . $reservationToApprove['object_status'] . '.';
        } else {
            $roomId = $reservationToApprove['object_parent'];
            $startTime = $reservationToApprove['meta']['reservation_start_datetime'] ?? null;
            $endTime = $reservationToApprove['meta']['reservation_end_datetime'] ?? null;

            if (!$startTime || !$endTime) {
                 $_SESSION['admin_message'] = 'Error: Reservation is missing start or end time data.';
                 redirect('openoffice/roomreservations');
                 return;
            }

            $approvedConflicts = $this->objectModel->getConflictingReservations(
                $roomId, $startTime, $endTime, ['approved'], $reservationId 
            );

            if ($approvedConflicts && count($approvedConflicts) > 0) {
                $_SESSION['admin_message'] = 'Error: Cannot approve. This time slot conflicts with an existing approved reservation.';
            } else {
                if ($this->objectModel->updateObject($reservationId, ['object_status' => 'approved'])) {
                    $_SESSION['admin_message'] = 'Reservation approved successfully.';
                    
                    $user = $this->userModel->findUserById($reservationToApprove['object_author']);
                    $roomFromDb = $this->objectModel->getObjectById($roomId); // Renamed
                    if ($user && !empty($user['user_email']) && $roomFromDb) {
                        $subject = "Your Reservation for {$roomFromDb['object_title']} has been Approved";
                        $message = "Dear {$user['display_name']},\n\n" .
                                   "Your reservation request for the room '{$roomFromDb['object_title']}' has been approved.\n" .
                                   "Details:\n" .
                                   "Room: {$roomFromDb['object_title']}\n" .
                                   "Purpose: {$reservationToApprove['object_content']}\n" .
                                   "Start Time: " . format_datetime_for_display($startTime) . "\n" .
                                   "End Time: " . format_datetime_for_display($endTime) . "\n\n" .
                                   "You can view your reservations here: " . BASE_URL . "openoffice/myreservations";
                        send_system_email($user['user_email'], $subject, $message);
                    }
                    
                    $overlappingPending = $this->objectModel->getConflictingReservations(
                        $roomId, $startTime, $endTime, ['pending'], $reservationId 
                    );

                    if ($overlappingPending) {
                        $deniedCount = 0;
                        foreach ($overlappingPending as $pendingConflict) {
                            if ($this->objectModel->updateObject($pendingConflict['object_id'], ['object_status' => 'denied'])) {
                                $deniedCount++;
                                error_log("Reservation ID {$pendingConflict['object_id']} automatically denied due to approval of {$reservationId}.");
                                $conflictUser = $this->userModel->findUserById($pendingConflict['object_author'] ?? $this->objectModel->getObjectById($pendingConflict['object_id'])['object_author']);
                                $conflictRoom = $this->objectModel->getObjectById($roomId); 
                                if ($conflictUser && !empty($conflictUser['user_email']) && $conflictRoom) {
                                    $cSubject = "Your Reservation Request for {$conflictRoom['object_title']} was Denied";
                                    $cMessage = "Dear {$conflictUser['display_name']},\n\n" .
                                               "We regret to inform you that your reservation request for the room '{$conflictRoom['object_title']}' for the time slot " .
                                               format_datetime_for_display($pendingConflict['reservation_start_datetime']) . " to " . format_datetime_for_display($pendingConflict['reservation_end_datetime']) .
                                               " has been denied due to a scheduling conflict with another approved reservation.\n\n" .
                                               "Please try booking an alternative time slot.";
                                    send_system_email($conflictUser['user_email'], $cSubject, $cMessage);
                                }
                            }
                        }
                        if ($deniedCount > 0) {
                            $_SESSION['admin_message'] .= " {$deniedCount} overlapping pending reservation(s) automatically denied.";
                        }
                    }
                } else {
                    $_SESSION['admin_message'] = 'Could not approve reservation due to a system error.';
                }
            }
        }
        redirect('openoffice/roomreservations');
    }

    public function denyreservation($reservationId = null) {
        // ... (implementation as before, including email notification) ...
        if (!userHasCapability('MANAGE_OPEN_OFFICE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to deny reservations.";
            redirect('openoffice/roomreservations'); 
        }
         if ($reservationId === null) {
            $_SESSION['admin_message'] = 'No reservation ID specified for denial.';
            redirect('openoffice/roomreservations');
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->objectModel->getObjectById($reservationId);

        if (!$reservation || $reservation['object_type'] !== 'reservation') {
            $_SESSION['admin_message'] = 'Reservation not found for denial.';
        } elseif (!in_array($reservation['object_status'], ['pending', 'approved'])) { 
            $_SESSION['admin_message'] = 'Only pending or approved reservations can be denied/revoked. This one is ' . $reservation['object_status'] . '.';
        } else {
            $originalStatus = $reservation['object_status'];
            if ($this->objectModel->updateObject($reservationId, ['object_status' => 'denied'])) {
                $_SESSION['admin_message'] = 'Reservation ' . ($originalStatus === 'approved' ? 'approval revoked and reservation denied.' : 'denied successfully.');
                
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->objectModel->getObjectById($reservation['object_parent']); // Renamed
                if ($user && !empty($user['user_email']) && $roomFromDb) {
                    $subject = "Your Reservation Request for {$roomFromDb['object_title']} was " . ($originalStatus === 'approved' ? 'Revoked/Denied' : 'Denied');
                    $message = "Dear {$user['display_name']},\n\n" .
                               "We regret to inform you that your reservation request for the room '{$roomFromDb['object_title']}' for the time slot " .
                               format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . " to " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '') .
                               " has been " . ($originalStatus === 'approved' ? 'revoked and denied by an administrator.' : 'denied by an administrator.') . "\n\n" .
                               "Reason for denial (if provided by admin): Not specified in this notification.\n" . 
                               "Please contact an administrator if you have questions or try booking an alternative time slot.";
                    send_system_email($user['user_email'], $subject, $message);
                }
            } else {
                $_SESSION['admin_message'] = 'Could not deny reservation.';
            }
        }
        redirect('openoffice/roomreservations');
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
