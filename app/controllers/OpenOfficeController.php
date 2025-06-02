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

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->objectModel = new ObjectModel($this->pdo);
        $this->userModel = new UserModel($this->pdo); 

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
    }

    /**
     * Display the list of rooms. (R in CRUD for Rooms)
     */
    public function rooms() {
        if (!userHasCapability('MANAGE_ROOMS')) {
            $_SESSION['error_message'] = "You do not have permission to manage rooms.";
            redirect('dashboard');
        }

        $rooms = $this->objectModel->getObjectsByType('room', ['orderby' => 'object_title', 'orderdir' => 'ASC']);
        
        $data = [
            'pageTitle' => 'Manage Rooms',
            'rooms' => $rooms,
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'], 
                ['label' => 'Manage Rooms']
            ]
        ];
        $this->view('openoffice/rooms_list', $data);
    }

    /**
     * Display form to add a new room OR process adding a new room. (C in CRUD for Rooms)
     */
    public function addRoom() {
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

    /**
     * Display form to edit an existing room OR process updating an existing room. (U in CRUD for Rooms)
     */
    public function editRoom($roomId = null) {
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

    /**
     * Delete a room. (D in CRUD for Rooms)
     */
    public function deleteRoom($roomId = null) {
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

    /**
     * Display the list of all room reservations (for admins).
     */
    public function roomreservations() {
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
                    $room = $this->objectModel->getObjectById($res['object_parent']);
                    $res['room_name'] = $room ? $room['object_title'] : 'Unknown Room';
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

    /**
     * Display form to create a new reservation for a specific room OR process the creation.
     */
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

        $commonData = [
            'pageTitle' => 'Book Room: ' . htmlspecialchars($room['object_title']),
            'room' => $room,
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Manage Rooms', 'url' => 'openoffice/rooms'], // Or just 'Open Office'
                ['label' => 'Book Room']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            // Retrieve new form fields
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

            // --- Validation for new fields ---
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

            // --- Parse time slot and construct full datetime strings ---
            $fullStartDateTimeStr = null;
            $fullEndDateTimeStr = null;

            if (!empty($data['reservation_date']) && !empty($data['reservation_time_slot'])) {
                $timeParts = explode('-', $data['reservation_time_slot']);
                if (count($timeParts) === 2) {
                    $startTime = trim($timeParts[0]); // e.g., "08:00"
                    $endTime = trim($timeParts[1]);   // e.g., "09:00"

                    $fullStartDateTimeStr = $data['reservation_date'] . ' ' . $startTime . ':00'; // Add seconds
                    $fullEndDateTimeStr = $data['reservation_date'] . ' ' . $endTime . ':00';   // Add seconds
                    
                    try {
                        $startDateTimeObj = new DateTime($fullStartDateTimeStr);
                        $endDateTimeObj = new DateTime($fullEndDateTimeStr);

                        if ($startDateTimeObj >= $endDateTimeObj) { // Should not happen with 1-hour slots but good check
                            $data['errors']['time_slot_err'] = 'End time must be after start time (logic error).';
                        }
                        // Check if the constructed start time is in the past (considering time as well)
                        if ($startDateTimeObj < new DateTime()) {
                             $data['errors']['date_err'] = 'Reservation start time cannot be in the past.';
                             // Also set time_slot_err to highlight the slot
                             if (empty($data['errors']['time_slot_err'])) $data['errors']['time_slot_err'] = 'Selected time slot is in the past.';
                        }

                    } catch (Exception $e) {
                        $data['errors']['time_slot_err'] = 'Invalid time slot format processed.';
                        $fullStartDateTimeStr = null; // Invalidate for conflict check
                        $fullEndDateTimeStr = null;
                    }
                } else {
                    $data['errors']['time_slot_err'] = 'Invalid time slot selected.';
                }
            }
            
            // Conflict Check for 'approved' reservations
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
                        'reservation_start_datetime' => $fullStartDateTimeStr, // Use parsed full datetime
                        'reservation_end_datetime' => $fullEndDateTimeStr,     // Use parsed full datetime
                        'reservation_user_id' => $_SESSION['user_id'] 
                    ]
                ];

                $reservationId = $this->objectModel->createObject($reservationData);

                if ($reservationId) {
                    $_SESSION['message'] = 'Reservation request submitted successfully! It is now pending approval.';
                    redirect('openoffice/myreservations'); 
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not submit reservation request.';
                    $this->view('openoffice/reservation_form', $data);
                }
            } else {
                // Repopulate form with submitted values if there are errors
                $data['reservation_date'] = $reservationDate;
                $data['reservation_time_slot'] = $reservationTimeSlot;
                $data['reservation_purpose'] = $reservationPurpose;
                $this->view('openoffice/reservation_form', $data);
            }
        } else {
            // Prepare empty form data for GET request
            $data = array_merge($commonData, [
                'reservation_date' => date('Y-m-d'), // Default to today for new reservation
                'reservation_time_slot' => '', 
                'reservation_purpose' => '',
                'errors' => []
            ]);
            $this->view('openoffice/reservation_form', $data); 
        }
    }

    /**
     * Display reservations made by the current user.
     */
    public function myreservations() {
        $userId = $_SESSION['user_id'];
        
        $myReservations = $this->objectModel->getObjectsByConditions('reservation', ['object_author' => $userId], [
            'orderby' => 'object_date', 
            'orderdir' => 'DESC',
            'include_meta' => true
        ]);
        
        if ($myReservations) {
            foreach ($myReservations as &$res) {
                 if (!empty($res['object_parent'])) { 
                    $room = $this->objectModel->getObjectById($res['object_parent']);
                    $res['room_name'] = $room ? $room['object_title'] : 'Unknown Room';
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

    /**
     * Cancel a pending reservation (by the user who made it).
     */
    public function cancelreservation($reservationId = null) {
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
            } else {
                $_SESSION['error_message'] = 'Could not cancel reservation.';
            }
        }
        redirect('openoffice/myreservations');
    }


    /**
     * Approve a pending reservation (by admin).
     */
    public function approvereservation($reservationId = null) {
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
            // These meta fields should now contain the full datetime strings
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
                    
                    $overlappingPending = $this->objectModel->getConflictingReservations(
                        $roomId, $startTime, $endTime, ['pending'], $reservationId 
                    );

                    if ($overlappingPending) {
                        $deniedCount = 0;
                        foreach ($overlappingPending as $pendingConflict) {
                            if ($this->objectModel->updateObject($pendingConflict['object_id'], ['object_status' => 'denied'])) {
                                $deniedCount++;
                                error_log("Reservation ID {$pendingConflict['object_id']} automatically denied due to approval of {$reservationId}.");
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

    /**
     * Deny a pending reservation (by admin).
     */
    public function denyreservation($reservationId = null) {
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
            if ($this->objectModel->updateObject($reservationId, ['object_status' => 'denied'])) {
                $_SESSION['admin_message'] = 'Reservation ' . ($reservation['object_status'] === 'approved' ? 'approval revoked and reservation denied.' : 'denied successfully.');
            } else {
                $_SESSION['admin_message'] = 'Could not deny reservation.';
            }
        }
        redirect('openoffice/roomreservations');
    }

    /**
     * Load a view file for the openoffice area.
     */
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
