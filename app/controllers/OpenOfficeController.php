<?php

/**
 * OpenOfficeController
 *
 * Handles operations related to the Open Office module, including Rooms and Reservations.
 */
class OpenOfficeController {
    private $pdo;
    private $objectModel;
    private $userModel; // Added for fetching user details for reservations

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->objectModel = new ObjectModel($this->pdo);
        $this->userModel = new UserModel($this->pdo); // Instantiate UserModel

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
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'], // Link to itself or a future Open Office dashboard
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
            'room_statuses' => ['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance'] // Operational status of the room itself
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'object_title' => trim($_POST['object_title'] ?? ''), // Room Name
                'object_content' => trim($_POST['object_content'] ?? ''), // Description
                'object_status' => trim($_POST['object_status'] ?? 'available'), // Room operational status
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
                    'object_author' => $_SESSION['user_id'], // Admin creating the room
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
     * @param int $roomId The ID of the room (object_id) to edit.
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
            'original_room_data' => $room, // Pass original data for the form
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
     * @param int $roomId The ID of the room (object_id) to delete.
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

        // Before deleting a room, consider if there are reservations.
        // For simplicity, this example doesn't check, but a real system should.
        // You might want to prevent deletion if active reservations exist, or cascade delete/notify.

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
            'orderby' => 'object_date', // Order by when reservation was made
            'orderdir' => 'DESC',
            'include_meta' => true
        ]);

        // Enhance reservations with room name and user name
        if ($reservations) {
            foreach ($reservations as &$res) {
                if (!empty($res['object_parent'])) { // object_parent is room_id
                    $room = $this->objectModel->getObjectById($res['object_parent']);
                    $res['room_name'] = $room ? $room['object_title'] : 'Unknown Room';
                } else {
                    $res['room_name'] = 'N/A';
                }
                if (!empty($res['object_author'])) { // object_author is user_id
                    $user = $this->userModel->findUserById($res['object_author']);
                    $res['user_display_name'] = $user ? $user['display_name'] : 'Unknown User';
                } else {
                    $res['user_display_name'] = 'N/A';
                }
            }
            unset($res); // Unset reference
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
        $this->view('openoffice/reservations_list', $data); // New view
    }

    /**
     * Display form to create a new reservation for a specific room OR process the creation.
     * @param int $roomId The ID of the room to reserve.
     */
    public function createreservation($roomId = null) {
        // Any logged-in user can attempt to create a reservation
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
                ['label' => 'Manage Rooms', 'url' => 'openoffice/rooms'],
                ['label' => 'Book Room']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'reservation_start_datetime' => trim($_POST['reservation_start_datetime'] ?? ''),
                'reservation_end_datetime' => trim($_POST['reservation_end_datetime'] ?? ''),
                'reservation_purpose' => trim($_POST['reservation_purpose'] ?? ''),
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            // Basic Validation
            if (empty($data['reservation_start_datetime'])) $data['errors']['start_err'] = 'Start date and time are required.';
            if (empty($data['reservation_end_datetime'])) $data['errors']['end_err'] = 'End date and time are required.';
            if (empty($data['reservation_purpose'])) $data['errors']['purpose_err'] = 'Purpose of reservation is required.';

            if (!empty($data['reservation_start_datetime']) && !empty($data['reservation_end_datetime'])) {
                $start = new DateTime($data['reservation_start_datetime']);
                $end = new DateTime($data['reservation_end_datetime']);
                if ($start >= $end) {
                    $data['errors']['end_err'] = 'End time must be after start time.';
                }
                if ($start < new DateTime()) {
                     $data['errors']['start_err'] = 'Reservation cannot be in the past.';
                }
                // TODO: Add conflict checking here - query existing 'approved' reservations for this room and time range.
                // This is a complex part and would typically involve a separate method in ObjectModel.
                // For now, we'll skip direct conflict checking in this iteration for brevity.
            }
            

            if (empty($data['errors'])) {
                $reservationData = [
                    'object_author' => $_SESSION['user_id'], // User making the reservation
                    'object_title' => 'Reservation for ' . $room['object_title'] . ' by ' . $_SESSION['display_name'],
                    'object_type' => 'reservation',
                    'object_parent' => $roomId, // Link to the room
                    'object_status' => 'pending', // Initial status
                    'object_content' => $data['reservation_purpose'], // Use main content for purpose
                    'meta_fields' => [
                        'reservation_start_datetime' => $data['reservation_start_datetime'],
                        'reservation_end_datetime' => $data['reservation_end_datetime'],
                        // 'reservation_room_id' => $roomId, // Redundant if using object_parent
                        'reservation_user_id' => $_SESSION['user_id'] // Also store user ID in meta if needed
                    ]
                ];

                $reservationId = $this->objectModel->createObject($reservationData);

                if ($reservationId) {
                    $_SESSION['message'] = 'Reservation request submitted successfully! It is now pending approval.';
                    redirect('openoffice/myreservations'); // Redirect to user's own reservations
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not submit reservation request.';
                    $this->view('openoffice/reservation_form', $data);
                }
            } else {
                $this->view('openoffice/reservation_form', $data);
            }
        } else {
            // Prepare empty form data for GET request
            $data = array_merge($commonData, [
                'reservation_start_datetime' => '', 
                'reservation_end_datetime' => '', 
                'reservation_purpose' => '',
                'errors' => []
            ]);
            $this->view('openoffice/reservation_form', $data); // New view
        }
    }

    /**
     * Display reservations made by the current user.
     */
    public function myreservations() {
        $userId = $_SESSION['user_id'];
        // We need a way to get objects by author and type
        // For now, get all and filter, or modify ObjectModel to support getObjectsByAuthorAndType
        $allReservations = $this->objectModel->getObjectsByType('reservation', [
            'orderby' => 'meta.reservation_start_datetime', // This won't work directly with current ObjectModel
                                                          // We'd need custom SQL or fetch all then sort.
                                                          // Let's sort by creation date for now.
            'orderby' => 'object_date',
            'orderdir' => 'DESC',
            'include_meta' => true
        ]);
        
        $myReservations = [];
        if ($allReservations) {
            foreach ($allReservations as $res) {
                if ($res['object_author'] == $userId) {
                     if (!empty($res['object_parent'])) { // object_parent is room_id
                        $room = $this->objectModel->getObjectById($res['object_parent']);
                        $res['room_name'] = $room ? $room['object_title'] : 'Unknown Room';
                    } else {
                        $res['room_name'] = 'N/A';
                    }
                    $myReservations[] = $res;
                }
            }
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
        $this->view('openoffice/my_reservations_list', $data); // New view
    }

    /**
     * Cancel a pending reservation (by the user who made it).
     * @param int $reservationId
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
     * @param int $reservationId
     */
    public function approvereservation($reservationId = null) {
        if (!userHasCapability('MANAGE_OPEN_OFFICE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to approve reservations.";
            redirect('openoffice/roomreservations');
        }
        if ($reservationId === null) {
            $_SESSION['admin_message'] = 'No reservation ID specified.';
            redirect('openoffice/roomreservations');
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->objectModel->getObjectById($reservationId);

        if (!$reservation || $reservation['object_type'] !== 'reservation') {
            $_SESSION['admin_message'] = 'Reservation not found.';
        } elseif ($reservation['object_status'] !== 'pending') {
            $_SESSION['admin_message'] = 'Only pending reservations can be approved.';
        } else {
            // TODO: Add conflict checking here before approving.
            // If conflict, set status to 'denied' or provide a message.
            if ($this->objectModel->updateObject($reservationId, ['object_status' => 'approved'])) {
                $_SESSION['admin_message'] = 'Reservation approved successfully.';
                // Optionally, send a notification to the user.
            } else {
                $_SESSION['admin_message'] = 'Could not approve reservation.';
            }
        }
        redirect('openoffice/roomreservations');
    }

    /**
     * Deny a pending reservation (by admin).
     * @param int $reservationId
     */
    public function denyreservation($reservationId = null) {
        if (!userHasCapability('MANAGE_OPEN_OFFICE_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to deny reservations.";
            redirect('openoffice/roomreservations');
        }
         if ($reservationId === null) {
            $_SESSION['admin_message'] = 'No reservation ID specified.';
            redirect('openoffice/roomreservations');
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->objectModel->getObjectById($reservationId);

        if (!$reservation || $reservation['object_type'] !== 'reservation') {
            $_SESSION['admin_message'] = 'Reservation not found.';
        } elseif ($reservation['object_status'] !== 'pending') {
            $_SESSION['admin_message'] = 'Only pending reservations can be denied.';
        } else {
            if ($this->objectModel->updateObject($reservationId, ['object_status' => 'denied'])) {
                $_SESSION['admin_message'] = 'Reservation denied successfully.';
                // Optionally, send a notification to the user.
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

