<?php

/**
 * OpenOfficeController
 *
 * Handles operations related to the Open Office module, including Rooms and Reservations.
 * IMPORTANT: Ensure this file is named OpenofficeController.php (lowercase 'o' in office)
 * if your file system is case-sensitive, to match the router's expectation.
 */
class OpenOfficeController {
    private $pdo;
    private $reservationModel; // For reservation-specific operations
    private $roomModel;        // For room-specific operations
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
        $this->roomModel = new RoomModel($this->pdo); // Instantiate RoomModel
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

        // Data is now primarily loaded by DataTables via AJAX
        // $rooms = $this->roomModel->getAllRooms(); // This line is no longer strictly needed here for the view
        
        $data = [
            'pageTitle' => 'Manage Rooms',
            // 'rooms' => $rooms, // Not passing rooms directly anymore
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'openoffice/rooms'], ['label' => 'Rooms List']]
        ];
        $this->view('openoffice/rooms_list', $data);
    }

    /**
     * AJAX endpoint to fetch room data for DataTables.
     */
    public function ajaxGetRooms() {
        header('Content-Type: application/json');

        if (!isLoggedIn() || !userHasCapability('VIEW_ROOMS')) {
            echo json_encode([
                "draw" => intval($_GET['draw'] ?? 0),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Not authorized to view rooms."
            ]);
            exit;
        }

        $rooms = $this->roomModel->getAllRooms(['include_meta' => true]); // Fetch rooms with metadata
        $data = [];

        if ($rooms) {
            foreach ($rooms as $room) {
                $actionsHtml = '';
                // Add View link
                $actionsHtml .= '<a href="' . BASE_URL . 'openoffice/roomDetails/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-info me-1" title="View Details"><i class="fas fa-eye"></i></a>';
                
                if (userHasCapability('EDIT_ROOMS')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'openoffice/editRoom/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                }
                if (userHasCapability('DELETE_ROOMS')) {
                    $actionsHtml .= ' <a href="' . BASE_URL . 'openoffice/deleteRoom/' . htmlspecialchars($room['object_id']) . '" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm(\'Are you sure you want to delete the room &quot;' . htmlspecialchars(addslashes($room['object_title'])) . '&quot;? This may affect existing reservations.\');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>';
                }
                 // Link to create a reservation for this room
                if (userHasCapability('CREATE_ROOM_RESERVATIONS')) {
                     $actionsHtml .= ' <a href="' . BASE_URL . 'openoffice/createreservation/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-success ms-1" title="Book Room"><i class="fas fa-calendar-plus"></i></a>';
                }


                $data[] = [
                    "id" => htmlspecialchars($room['object_id']),
                    "name" => htmlspecialchars($room['object_title']),
                    "capacity" => htmlspecialchars($room['meta']['room_capacity'] ?? 'N/A'),
                    "location" => htmlspecialchars($room['meta']['room_location'] ?? 'N/A'),
                    "equipment" => nl2br(htmlspecialchars($room['meta']['room_equipment'] ?? 'N/A')),
                    "status" => '<span class="badge bg-' . ($room['object_status'] === 'available' ? 'success' : ($room['object_status'] === 'maintenance' ? 'warning text-dark' : 'danger')) . '">' . htmlspecialchars(ucfirst($room['object_status'])) . '</span>',
                    "modified" => htmlspecialchars(format_datetime_for_display($room['object_modified'])),
                    "actions" => $actionsHtml
                ];
            }
        }

        $output = [
            "draw"            => intval($_GET['draw'] ?? 0), // Sanitize draw parameter
            "recordsTotal"    => count($data), // For client-side processing, total is same as filtered
            "recordsFiltered" => count($data), // For client-side processing
            "data"            => $data
        ];
        echo json_encode($output);
        exit; // Crucial to prevent any further output
    }


    /**
     * Display form to add a new room OR process adding a new room.
     * Protected by CREATE_ROOMS capability.
     */
    public function addRoom() {
        if (!userHasCapability('CREATE_ROOMS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to add new rooms.';
            redirect('openoffice/rooms');
        }

        $commonData = [
            'pageTitle' => 'Add New Room',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Rooms', 'url' => 'openoffice/rooms'],
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
                $roomData = [ // Changed variable name for clarity
                    'object_author' => $_SESSION['user_id'], 
                    'object_title' => $data['object_title'],
                    // 'object_type' will be set by RoomModel's createRoom method
                    'object_content' => $data['object_content'],
                    'object_status' => $data['object_status'], 
                    'meta_fields' => $data['meta_fields']
                ];

                $roomId = $this->roomModel->createRoom($roomData); // Use RoomModel

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
     * Display form to edit an existing room OR process updating an existing room.
     * Protected by EDIT_ROOMS capability.
     */
    public function editRoom($roomId = null) {
        if (!userHasCapability('EDIT_ROOMS')) { 
            $_SESSION['admin_message'] = 'Error: You do not have permission to edit rooms.';
            redirect('openoffice/rooms');
        }
        
        if ($roomId === null) redirect('openoffice/rooms');
        $roomId = (int)$roomId;
        
        $room = $this->roomModel->getRoomById($roomId); // Use RoomModel

        if (!$room) { // getRoomById already checks type
            $_SESSION['admin_message'] = 'Room not found.';
            redirect('openoffice/rooms');
        }

        $commonData = [
            'pageTitle' => 'Edit Room',
            'room_id' => $roomId, 
            'original_room_data' => $room, 
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Rooms', 'url' => 'openoffice/rooms'],
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
                if ($this->roomModel->updateRoom($roomId, $updateData)) { // Use RoomModel
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
     * Delete a room.
     * Protected by DELETE_ROOMS capability.
     */
    public function deleteRoom($roomId = null) {
        if (!userHasCapability('DELETE_ROOMS')) { 
            $_SESSION['admin_message'] = 'Error: You do not have permission to delete rooms.';
            redirect('openoffice/rooms');
        }
        
        if ($roomId === null) redirect('openoffice/rooms');
        $roomId = (int)$roomId;

        $room = $this->roomModel->getRoomById($roomId); // Use RoomModel
        if (!$room) {
            $_SESSION['admin_message'] = 'Error: Room not found.';
            redirect('openoffice/rooms');
        }

        $existingReservations = $this->reservationModel->getReservationsByRoomId($roomId, [], ['limit' => 1]);
        if (!empty($existingReservations)) {
            $_SESSION['admin_message'] = 'Error: Cannot delete room "' . htmlspecialchars($room['object_title']) . '". It has existing reservations. Please manage or delete them first.';
            redirect('openoffice/rooms');
            return;
        }

        if ($this->roomModel->deleteRoom($roomId)) { // Use RoomModel
            $_SESSION['admin_message'] = 'Room "' . htmlspecialchars($room['object_title']) . '" deleted successfully.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete room "' . htmlspecialchars($room['object_title']) . '".';
        }
        redirect('openoffice/rooms');
    }

    /**
     * Display details for a specific room, including pending and approved reservations.
     * Protected by VIEW_ROOMS capability.
     */
    public function roomDetails($roomId = null) {
        if (!userHasCapability('VIEW_ROOMS')) {
            $_SESSION['error_message'] = "You do not have permission to view room details.";
            redirect('dashboard');
        }

        if ($roomId === null) {
            $_SESSION['error_message'] = 'No room ID specified.';
            redirect('openoffice/rooms');
        }

        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId);

        if (!$room) {
            $_SESSION['error_message'] = 'Room not found.';
            redirect('openoffice/rooms');
        }

        $data = [
            'pageTitle' => 'Room Details: ' . htmlspecialchars($room['object_title']),
            'room' => $room, // Pass room data to the view
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Rooms', 'url' => 'openoffice/rooms'],
                ['label' => 'Room Details: ' . htmlspecialchars($room['object_title'])]
            ]
        ];
        $this->view('openoffice/room_details', $data);
    }

    /**
     * AJAX endpoint to fetch room details and its reservations (pending/approved).
     * Used by the room_details.php page to populate data dynamically.
     */
    public function roomDetailsData($roomId = null) {
        header('Content-Type: application/json');

        if (!userHasCapability('VIEW_ROOMS')) {
            echo json_encode(['error' => 'Permission denied.']);
            exit;
        }

        if ($roomId === null) {
            echo json_encode(['error' => 'No room ID specified.']);
            exit;
        }

        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId);

        if (!$room) {
            echo json_encode(['error' => 'Room not found.']);
            exit;
        }

        $selectedDate = $_GET['date'] ?? null;
        
        $pendingReservations = [];
        $approvedReservations = [];

        if ($selectedDate) {
            $startOfDay = $selectedDate . ' 00:00:00';
            $endOfDay = $selectedDate . ' 23:59:59';

        // Fetch pending reservations for the room that conflict with the selected day
        $pendingReservations = $this->reservationModel->getConflictingReservations(
            $roomId,
            $startOfDay,
            $endOfDay,
            ['pending']
        );

        // Fetch approved reservations for the room that conflict with the selected day
        $approvedReservations = $this->reservationModel->getConflictingReservations(
            $roomId,
            $startOfDay,
            $endOfDay,
            ['approved']
        );
        } else {
            // If no date is selected, fetch all pending and approved reservations (or a default range)
            // For now, let's fetch all if no date is provided, as per original behavior before date filter
            $pendingReservations = $this->reservationModel->getReservationsByRoomId(
                $roomId,
                ['o.object_status' => 'pending'],
                ['orderby' => 'meta.reservation_start_datetime', 'orderdir' => 'ASC']
            );

            $approvedReservations = $this->reservationModel->getReservationsByRoomId(
                $roomId,
                ['o.object_status' => 'approved'],
                ['orderby' => 'meta.reservation_start_datetime', 'orderdir' => 'ASC']
            );
        }

        // Enrich reservation data with user names and formatted times
        $enrichedPending = [];
        if ($pendingReservations) {
            foreach ($pendingReservations as $res) {
                $user = $this->userModel->findUserById($res['object_author']);
                $res['user_name'] = $user ? $user['display_name'] : 'Unknown User';
                // Access directly as selected in getConflictingReservations
                $res['start_time'] = format_datetime_for_display($res['reservation_start_datetime'] ?? '');
                $res['end_time'] = format_datetime_for_display($res['reservation_end_datetime'] ?? '');
                $res['purpose'] = htmlspecialchars($res['object_content'] ?? 'N/A');
                $enrichedPending[] = $res;
            }
        }

        $enrichedApproved = [];
        if ($approvedReservations) {
            foreach ($approvedReservations as $res) {
                $user = $this->userModel->findUserById($res['object_author']);
                $res['user_name'] = $user ? $user['display_name'] : 'Unknown User';
                // Access directly as selected in getConflictingReservations
                $res['start_time'] = format_datetime_for_display($res['reservation_start_datetime'] ?? '');
                $res['end_time'] = format_datetime_for_display($res['reservation_end_datetime'] ?? '');
                $res['purpose'] = htmlspecialchars($res['object_content'] ?? 'N/A');
                $enrichedApproved[] = $res;
            }
        }

        echo json_encode([
            'room' => [
                'id' => $room['object_id'],
                'name' => htmlspecialchars($room['object_title']),
                'capacity' => htmlspecialchars($room['meta']['room_capacity'] ?? 'N/A'),
                'description' => nl2br(htmlspecialchars($room['object_content'] ?? 'N/A'))
            ],
            'pendingReservations' => $enrichedPending,
            'approvedReservations' => $enrichedApproved
        ]);
        exit;
    }

    /**
     * AJAX endpoint to update the status of a reservation (approve/deny).
     * Expects POST data: { reservation_id: int, status: string ('approved' or 'denied') }
     * Protected by APPROVE_DENY_ROOM_RESERVATIONS capability.
     */
    public function updateReservationStatus() {
        header('Content-Type: application/json');

        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $reservationId = filter_var($input['reservation_id'] ?? null, FILTER_VALIDATE_INT);
        $status = filter_var($input['status'] ?? null, FILTER_SANITIZE_STRING);

        if (!$reservationId || !in_array($status, ['approved', 'denied'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        $reservation = $this->reservationModel->getObjectById($reservationId);

        if (!$reservation || $reservation['object_type'] !== 'reservation') {
            echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
            exit;
        }

        if ($reservation['object_status'] === $status) {
            echo json_encode(['success' => true, 'message' => 'Reservation already ' . $status . '.']);
            exit;
        }

        $message = '';
        $success = false;

        if ($status === 'approved') {
            $roomId = $reservation['object_parent'];
            $startTime = $reservation['meta']['reservation_start_datetime'] ?? null;
            $endTime = $reservation['meta']['reservation_end_datetime'] ?? null;

            if (!$startTime || !$endTime) {
                $message = 'Error: Reservation is missing start or end time data.';
            } else {
                $approvedConflicts = $this->reservationModel->getConflictingReservations($roomId, $startTime, $endTime, ['approved'], $reservationId);

                if ($approvedConflicts && count($approvedConflicts) > 0) {
                    $message = 'Error: Cannot approve. This time slot conflicts with an existing approved reservation.';
                } else {
                    if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'approved'])) {
                        $success = true;
                        $message = 'Reservation approved successfully.';
                        $user = $this->userModel->findUserById($reservation['object_author']);
                        $roomFromDb = $this->roomModel->getRoomById($roomId);
                        if ($user && !empty($user['user_email']) && $roomFromDb) {
                            send_system_email($user['user_email'], "Your Reservation for {$roomFromDb['object_title']} has been Approved", "Dear {$user['display_name']},\n\nYour reservation for '{$roomFromDb['object_title']}' from " . format_datetime_for_display($startTime) . " to " . format_datetime_for_display($endTime) . " has been approved.\n\nPurpose: {$reservation['object_content']}");
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
                                        send_system_email($conflictUser['user_email'], "Your Reservation Request for {$conflictRoom['object_title']} was Denied", "Dear {$conflictUser['display_name']},\n\nYour reservation for '{$conflictRoom['object_title']}' for " . format_datetime_for_display($pendingConflict['meta']['reservation_start_datetime']) . " to " . format_datetime_for_display($pendingConflict['meta']['reservation_end_datetime']) . " was denied due to a conflict.");
                                    }
                                }
                            }
                            if ($deniedCount > 0) $message .= " {$deniedCount} overlapping pending reservation(s) automatically denied.";
                        }
                    } else {
                        $message = 'Could not approve reservation due to a system error.';
                    }
                }
            }
        } elseif ($status === 'denied') {
            $originalStatus = $reservation['object_status'];
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'denied'])) {
                $success = true;
                $message = 'Reservation ' . ($originalStatus === 'approved' ? 'approval revoked and reservation denied.' : 'denied successfully.');
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']);
                if ($user && !empty($user['user_email']) && $roomFromDb) {
                     send_system_email($user['user_email'], "Your Reservation Request for {$roomFromDb['object_title']} was " . ($originalStatus === 'approved' ? 'Revoked/Denied' : 'Denied'), "Dear {$user['display_name']},\n\nYour reservation for '{$roomFromDb['object_title']}' for " . format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . " to " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '') . " has been " . ($originalStatus === 'approved' ? 'revoked/denied.' : 'denied.'));
                }
            } else {
                $message = 'Could not deny reservation.';
            }
        }

        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }

    // --- Room Reservation Methods ---
    
    /**
     * Display the room reservations management page.
     * This page will now be primarily AJAX driven for its table content.
     */
    public function roomreservations() {
        if (!userHasCapability('VIEW_ALL_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to view all room reservations.";
            redirect('dashboard');
        }
        
        // Data for the initial page load (e.g., filters, page title)
        $data = [
            'pageTitle' => 'Manage Room Reservations',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'], // Corrected to a valid Open Office link
                ['label' => 'Manage Room Reservations']
            ],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('openoffice/reservations_list', $data); 
    }

    /**
     * AJAX handler for fetching room reservation data.
     * Responds with JSON for the reservations_list.php view's JavaScript.
     */
    public function ajaxRoomReservationsData() {
        header('Content-Type: application/json'); // Ensure correct content type for JSON response

        if (!userHasCapability('VIEW_ALL_ROOM_RESERVATIONS')) {
            echo json_encode(['error' => 'Permission denied.', 'data' => [], 'pagination' => null]);
            exit;
        }

        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $searchTerm = isset($_POST['searchTerm']) ? trim($_POST['searchTerm']) : '';
        $filterStatus = isset($_POST['filterStatus']) ? trim($_POST['filterStatus']) : '';

        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        $offset = ($page - 1) * $limit;

        $conditions = [];
        if (!empty($filterStatus)) {
            $conditions['o.object_status'] = $filterStatus; // Alias 'o' for objects table
        }
        
        $args = [
            'orderby' => 'o.object_date', // Alias 'o' for objects table
            'orderdir' => 'DESC',
            'include_meta' => true,
            'limit' => $limit,
            'offset' => $offset
        ];

        $reservations = $this->reservationModel->getObjectsByConditions('reservation', $conditions, $args, $searchTerm);
        
        $totalRecords = $this->reservationModel->countObjectsByConditions('reservation', $conditions, $searchTerm);
        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 0;

        $enrichedReservations = [];
        if ($reservations) {
            foreach ($reservations as $res) {
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
                $res['formatted_start_datetime'] = format_datetime_for_display($res['meta']['reservation_start_datetime'] ?? '');
                $res['formatted_end_datetime'] = format_datetime_for_display($res['meta']['reservation_end_datetime'] ?? '');
                $res['formatted_object_date'] = format_datetime_for_display($res['object_date']);
                
                $enrichedReservations[] = $res;
            }
        }
        
        $response = [
            'data' => $enrichedReservations,
            'pagination' => [
                'currentPage' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'totalRecords' => $totalRecords
            ]
        ];

        echo json_encode($response);
        exit; 
    }


    public function createreservation($roomId = null) {
        if (!userHasCapability('CREATE_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to create room reservations.";
            redirect('openoffice/rooms'); 
        }
        
        if ($roomId === null) {
            $_SESSION['error_message'] = 'No room selected for reservation.';
            redirect('openoffice/rooms');
        }
        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId); 

        if (!$room || $room['object_status'] !== 'available') { 
            $_SESSION['error_message'] = 'This room is not available for reservation or does not exist.';
            redirect('openoffice/rooms');
        }
        
        $approvedReservationsData = [];
        $approvedRoomReservations = $this->reservationModel->getReservationsByRoomId(
            $roomId,
            ['o.object_status' => 'approved'] // Ensure alias is used if model expects it
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
            'approved_reservations_data_for_js' => $approvedReservationsData, // Pass the PHP array directly
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'openoffice/rooms'],
                ['label' => 'Rooms', 'url' => 'openoffice/rooms'], 
                ['label' => 'Book Room']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $reservationDate = trim($_POST['reservation_date'] ?? '');
            $reservationTimeSlots = $_POST['reservation_time_slots'] ?? []; // Expect an array
            $reservationPurpose = trim($_POST['reservation_purpose'] ?? '');
            
            // Ensure reservationTimeSlots is an array, even if only one checkbox was selected
            if (!is_array($reservationTimeSlots)) {
                $reservationTimeSlots = [$reservationTimeSlots];
            }

            $formData = [
                'reservation_date' => $reservationDate,
                'reservation_time_slots' => $reservationTimeSlots, // Pass array to form data
                'reservation_purpose' => $reservationPurpose,
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            if (empty($data['reservation_date'])) {
                $data['errors']['date_err'] = 'Reservation date is required.';
            } elseif (new DateTime($data['reservation_date']) < new DateTime(date('Y-m-d'))) {
                $data['errors']['date_err'] = 'Reservation date cannot be in the past.';
            }
            
            if (empty($data['reservation_time_slots'])) {
                $data['errors']['time_slot_err'] = 'At least one time slot is required.';
            }
            
            if (empty($data['reservation_purpose'])) {
                $data['errors']['purpose_err'] = 'Purpose of reservation is required.';
            }

            $successfulReservations = [];
            $failedReservations = [];
            $allSlotsValid = true;

            $mergedReservationRanges = [];
            if (empty($data['errors']) && !empty($data['reservation_time_slots'])) {
                // Merge adjacent time slots
                $mergedReservationRanges = $this->mergeTimeSlots($data['reservation_time_slots'], $data['reservation_date']);

                if (empty($mergedReservationRanges)) {
                    $data['errors']['time_slot_err'] = 'No valid time slots could be merged.';
                }
            }

            if (empty($data['errors']) && !empty($mergedReservationRanges)) {
                $totalCreated = 0;
                $reservedSlotsDisplay = [];

                foreach ($mergedReservationRanges as $mergedRange) {
                    $fullStartDateTimeStr = $mergedRange['start'];
                    $fullEndDateTimeStr = $mergedRange['end'];
                    $slotDisplay = $mergedRange['slot_display'];

                    try {
                        $startDateTimeObj = new DateTime($fullStartDateTimeStr);
                        $endDateTimeObj = new DateTime($fullEndDateTimeStr);
                        if ($startDateTimeObj >= $endDateTimeObj) {
                            $failedReservations[] = [
                                'slot' => $slotDisplay,
                                'reason' => 'End time must be after start time.'
                            ];
                            $data['errors']['form_err'] = 'Some merged time slots have invalid durations.';
                            continue;
                        }
                        if ($startDateTimeObj < new DateTime()) {
                            $failedReservations[] = [
                                'slot' => $slotDisplay,
                                'reason' => 'Reservation start time cannot be in the past.'
                            ];
                            $data['errors']['form_err'] = 'Some merged time slots are in the past.';
                            continue;
                        }
                    } catch (Exception $e) {
                        $failedReservations[] = [
                            'slot' => $slotDisplay,
                            'reason' => 'Invalid date/time format.'
                        ];
                        $data['errors']['form_err'] = 'Invalid time slot format processed for one or more merged slots.';
                        continue;
                    }

                    // Check for conflicts with approved reservations for the merged range
                    $conflicts = $this->reservationModel->getConflictingReservations(
                        $roomId, $fullStartDateTimeStr, $fullEndDateTimeStr, ['approved']
                    );
                    if ($conflicts && count($conflicts) > 0) {
                        $failedReservations[] = [
                            'slot' => $slotDisplay,
                            'reason' => 'This merged time range conflicts with an existing approved reservation.'
                        ];
                        $data['errors']['form_err'] = 'Some selected time slots are already booked.';
                        continue;
                    }

                    // If no conflicts, prepare for successful reservation
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
                        $totalCreated++;
                        $reservedSlotsDisplay[] = $slotDisplay;
                    } else {
                        $failedReservations[] = [
                            'slot' => $slotDisplay,
                            'reason' => 'Failed to save to database.'
                        ];
                    }
                }

                if ($totalCreated > 0) {
                    $_SESSION['message'] = "Successfully submitted {$totalCreated} reservation request(s)! They are now pending approval.";
                    if (!empty($failedReservations)) {
                        $_SESSION['error_message'] = "Some merged slots could not be reserved: " . implode(', ', array_column($failedReservations, 'slot')) . ". Reasons: " . implode('; ', array_column($failedReservations, 'reason'));
                    }

                    $user = $this->userModel->findUserById($_SESSION['user_id']);
                    $adminEmail = $this->optionModel->getOption('site_admin_email_notifications', DEFAULT_ADMIN_EMAIL_NOTIFICATIONS);

                    $slotsListHtml = "<ul>";
                    foreach ($reservedSlotsDisplay as $slotDisp) {
                        $slotsListHtml .= "<li>{$slotDisp}</li>";
                    }
                    $slotsListHtml .= "</ul>";

                    if ($user && !empty($user['user_email'])) {
                        send_system_email($user['user_email'], "Your Reservation Request for {$room['object_title']} is Pending", "Dear {$user['display_name']},\n\nYour reservation request(s) for '{$room['object_title']}' for the following time slot(s) are pending approval:\n{$slotsListHtml}\nPurpose: {$data['reservation_purpose']}\n\nView status: " . BASE_URL . "openoffice/myreservations");
                    }
                    if ($adminEmail) {
                        send_system_email($adminEmail, "New Room Reservation Request: {$room['object_title']} by {$user['display_name']}", "User: {$user['display_name']} ({$user['user_email']})\nRoom: {$room['object_title']} (ID: {$roomId})\nPurpose: {$data['reservation_purpose']}\nTime Slots:\n{$slotsListHtml}\n\nReview: " . BASE_URL . "openoffice/roomreservations");
                    }
                    redirect('openoffice/myreservations');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. No reservation requests could be submitted.';
                    if (!empty($failedReservations)) {
                        $data['errors']['form_err'] .= " Reasons: " . implode('; ', array_column($failedReservations, 'reason'));
                    }
                    $this->view('openoffice/reservation_form', $data);
                }
            } else {
                // If there are validation errors or no successful reservations to process
                if (!empty($failedReservations)) {
                    $data['errors']['form_err'] = ($data['errors']['form_err'] ?? '') . " Some slots could not be reserved: " . implode(', ', array_column($failedReservations, 'slot')) . ". Reasons: " . implode('; ', array_column($failedReservations, 'reason'));
                }
                $data['reservation_date'] = $reservationDate;
                $data['reservation_time_slots'] = $reservationTimeSlots; // Keep selected slots for repopulation
                $data['reservation_purpose'] = $reservationPurpose;
                $this->view('openoffice/reservation_form', $data);
            }
        } else {
            $data = array_merge($commonData, ['reservation_date' => date('Y-m-d'), 'reservation_time_slots' => [], 'reservation_purpose' => '', 'errors' => []]);
            $this->view('openoffice/reservation_form', $data); 
        }
    }

    /**
     * AJAX endpoint to get queue information for multiple selected room time slots.
     * Expects POST data: { roomId: int, date: string, slots: string[] }
     */
    public function getMultipleSlotsQueueInfo() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        $roomId = filter_var($input['roomId'] ?? null, FILTER_VALIDATE_INT);
        $date = filter_var($input['date'] ?? null, FILTER_SANITIZE_STRING);
        $slots = $input['slots'] ?? []; // Array of slot strings, e.g., ["08:00-09:00", "09:00-10:00"]

        if (!$roomId || !$date || !is_array($slots) || empty($slots)) {
            echo json_encode(['error' => 'Missing or invalid parameters.']);
            exit;
        }

        $pendingCounts = [];
        foreach ($slots as $slot) {
            $timeParts = explode('-', $slot);
            if (count($timeParts) !== 2) {
                $pendingCounts[$slot] = ['error' => 'Invalid slot format.'];
                continue;
            }

            $startTimeStr = $date . ' ' . trim($timeParts[0]) . ':00';
            $endTimeStr = $date . ' ' . trim($timeParts[1]) . ':00';

            try {
                new DateTime($startTimeStr); // Validate date/time format
                new DateTime($endTimeStr);
            } catch (Exception $e) {
                $pendingCounts[$slot] = ['error' => 'Invalid date or time format in slot.'];
                continue;
            }
            
            $conflictingPending = $this->reservationModel->getConflictingReservations(
                $roomId, $startTimeStr, $endTimeStr, ['pending']
            );

            if ($conflictingPending === false) {
                $pendingCounts[$slot] = ['error' => 'Could not retrieve queue information.'];
            } else {
                $pendingCounts[$slot] = count($conflictingPending);
            }
        }
        echo json_encode(['pendingCounts' => $pendingCounts]);
        exit;
    }



    public function myreservations() {
        $userId = $_SESSION['user_id'];
        // Data will be loaded by DataTables via AJAX
        $data = [
            'pageTitle' => 'My Room Reservations',
            // 'reservations' => $myReservations, // Not passing directly anymore
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'openoffice/rooms'], ['label' => 'My Reservations']],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('openoffice/my_reservations_list', $data); 
    }

    /**
     * AJAX endpoint to fetch a user's own reservations.
     */
    public function ajaxGetUserReservations() {
        header('Content-Type: application/json');
        if (!isLoggedIn()) {
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not logged in"]);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $myReservations = $this->reservationModel->getReservationsByUserId(
            $userId,
            'ORDER BY o.object_date DESC' // Pass as string if that's what the model expects
        );
        
        $data = [];
        if ($myReservations) {
            foreach ($myReservations as $res) {
                $roomName = 'N/A';
                if (!empty($res['object_parent'])) { 
                    $roomFromDb = $this->roomModel->getRoomById($res['object_parent']);
                    $roomName = $roomFromDb ? $roomFromDb['object_title'] : 'Unknown Room';
                }

                $statusKey = $res['object_status'] ?? 'unknown';
                $statusLabel = ucfirst($statusKey);
                $badgeClass = 'bg-secondary';
                if ($statusKey === 'pending') $badgeClass = 'bg-warning text-dark';
                else if ($statusKey === 'approved') $badgeClass = 'bg-success';
                else if ($statusKey === 'denied') $badgeClass = 'bg-danger';
                else if ($statusKey === 'cancelled') $badgeClass = 'bg-info text-dark';
                $statusHtml = "<span class=\"badge {$badgeClass}\">" . htmlspecialchars($statusLabel) . "</span>";

                $actionsHtml = '';
                if ($res['object_status'] === 'pending' && userHasCapability('CANCEL_OWN_ROOM_RESERVATIONS')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'openoffice/cancelreservation/' . htmlspecialchars($res['object_id']) . '" 
                                       class="btn btn-sm btn-warning text-dark" title="Cancel Reservation"
                                       onclick="return confirm(\'Are you sure you want to cancel this reservation?\');">
                                        <i class="fas fa-times-circle"></i> Cancel
                                    </a>';
                } else {
                    $actionsHtml = '<span class="text-muted small">No actions</span>';
                }
                // Add edit action if capability exists and status is pending
                // if ($res['object_status'] === 'pending' && userHasCapability('EDIT_OWN_ROOM_RESERVATIONS')) {
                // $actionsHtml .= ' <a href="' . BASE_URL . 'openoffice/editMyReservation/' . htmlspecialchars($res['object_id']) . '" class="btn btn-sm btn-info ms-1" title="Edit (Soon)"><i class="fas fa-edit"></i></a>';
                // }


                $data[] = [
                    "id" => htmlspecialchars($res['object_id']),
                    "room" => htmlspecialchars($roomName),
                    "purpose" => nl2br(htmlspecialchars($res['object_content'] ?? 'N/A')),
                    "start_time" => htmlspecialchars(format_datetime_for_display($res['meta']['reservation_start_datetime'] ?? '')),
                    "end_time" => htmlspecialchars(format_datetime_for_display($res['meta']['reservation_end_datetime'] ?? '')),
                    "requested_on" => htmlspecialchars(format_datetime_for_display($res['object_date'])),
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
        exit;
    }


    public function cancelreservation($reservationId = null) {
        if (!userHasCapability('CANCEL_OWN_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to cancel reservations.";
            redirect('openoffice/myreservations');
        }
        if ($reservationId === null) { $_SESSION['error_message'] = 'No reservation ID specified.'; redirect('openoffice/myreservations'); }
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
        redirect('openoffice/myreservations');
    }

    public function approvereservation($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Permission denied.']);
                exit;
            }
            $_SESSION['error_message'] = "You do not have permission to approve or deny reservations.";
            redirect('openoffice/roomreservations'); 
        }
        if ($reservationId === null) { 
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No reservation ID specified.']);
                exit;
            }
            $_SESSION['admin_message'] = 'No reservation ID specified for approval.'; 
            redirect('openoffice/roomreservations'); 
        }
        $reservationId = (int)$reservationId;
        $reservationToApprove = $this->reservationModel->getObjectById($reservationId); 

        $message = '';
        $success = false;

        if (!$reservationToApprove || $reservationToApprove['object_type'] !== 'reservation') { 
            $message = 'Reservation not found for approval.';
        } elseif ($reservationToApprove['object_status'] !== 'pending') { 
            $message = 'Only pending reservations can be approved. This one is already ' . $reservationToApprove['object_status'] . '.'; 
        } else {
            $roomId = $reservationToApprove['object_parent'];
            $startTime = $reservationToApprove['meta']['reservation_start_datetime'] ?? null;
            $endTime = $reservationToApprove['meta']['reservation_end_datetime'] ?? null;

            if (!$startTime || !$endTime) { 
                $message = 'Error: Reservation is missing start or end time data.';
            } else {
                $approvedConflicts = $this->reservationModel->getConflictingReservations($roomId, $startTime, $endTime, ['approved'], $reservationId);

                if ($approvedConflicts && count($approvedConflicts) > 0) { 
                    $message = 'Error: Cannot approve. This time slot conflicts with an existing approved reservation.'; 
                } else {
                    if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'approved'])) { 
                        $success = true;
                        $message = 'Reservation approved successfully.';
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
                                        send_system_email($conflictUser['user_email'], "Your Reservation Request for {$conflictRoom['object_title']} was Denied", "Dear {$conflictUser['display_name']},\n\nYour reservation for '{$conflictRoom['object_title']}' for " . format_datetime_for_display($pendingConflict['meta']['reservation_start_datetime']) . " to " . format_datetime_for_display($pendingConflict['meta']['reservation_end_datetime']) . " was denied due to a conflict.");
                                    }
                                }
                            }
                            if ($deniedCount > 0) $message .= " {$deniedCount} overlapping pending reservation(s) automatically denied.";
                        }
                    } else { 
                        $message = 'Could not approve reservation due to a system error.'; 
                    }
                }
            }
        }

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'message' => $message]);
            exit;
        } else {
            $_SESSION[$success ? 'admin_message' : 'error_message'] = $message;
            redirect('openoffice/roomreservations');
        }
    }

    public function denyreservation($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
             if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Permission denied.']);
                exit;
            }
            $_SESSION['error_message'] = "You do not have permission to approve or deny reservations.";
            redirect('openoffice/roomreservations'); 
        }
        if ($reservationId === null) { 
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No reservation ID specified.']);
                exit;
            }
            $_SESSION['admin_message'] = 'No reservation ID specified for denial.'; 
            redirect('openoffice/roomreservations'); 
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId); 

        $message = '';
        $success = false;

        if (!$reservation || $reservation['object_type'] !== 'reservation') { 
            $message = 'Reservation not found for denial.'; 
        } elseif (!in_array($reservation['object_status'], ['pending', 'approved'])) { 
            $message = 'Only pending or approved reservations can be denied/revoked. This one is ' . $reservation['object_status'] . '.'; 
        } else {
            $originalStatus = $reservation['object_status'];
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'denied'])) { 
                $success = true;
                $message = 'Reservation ' . ($originalStatus === 'approved' ? 'approval revoked and reservation denied.' : 'denied successfully.');
                $user = $this->userModel->findUserById($reservation['object_author']);
                $roomFromDb = $this->roomModel->getRoomById($reservation['object_parent']); 
                if ($user && !empty($user['user_email']) && $roomFromDb) {
                     send_system_email($user['user_email'], "Your Reservation Request for {$roomFromDb['object_title']} was " . ($originalStatus === 'approved' ? 'Revoked/Denied' : 'Denied'), "Dear {$user['display_name']},\n\nYour reservation for '{$roomFromDb['object_title']}' for " . format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '') . " to " . format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '') . " has been " . ($originalStatus === 'approved' ? 'revoked/denied.' : 'denied.'));
                }
            } else { 
                $message = 'Could not deny reservation.'; 
            }
        }
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'message' => $message]);
            exit;
        } else {
            $_SESSION[$success ? 'admin_message' : 'error_message'] = $message;
            redirect('openoffice/roomreservations');
        }
    }

    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }


    public function editMyReservation($reservationId = null) {
        if (!userHasCapability('EDIT_OWN_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to edit your reservations.";
            redirect('openoffice/myreservations');
        }
        // Basic placeholder - full implementation would involve a form similar to createreservation
        $_SESSION['message'] = "Editing reservations (ID: {$reservationId}) is not yet fully implemented. This would typically involve loading the reservation into a form, allowing changes, and re-validating conflicts.";
        redirect('openoffice/myreservations');
    }

    public function editAnyReservation($reservationId = null) {
        if (!userHasCapability('EDIT_ANY_ROOM_RESERVATION')) {
            $_SESSION['error_message'] = "You do not have permission to edit this reservation.";
            redirect('openoffice/roomreservations');
        }
        // Basic placeholder
        $_SESSION['admin_message'] = "Editing any reservation (ID: {$reservationId}) is not yet fully implemented. This would allow admins to modify details of any reservation, potentially overriding some rules or re-assigning.";
        redirect('openoffice/roomreservations');
    }

    public function deleteAnyReservation($reservationId = null) {
        if (!userHasCapability('DELETE_ANY_ROOM_RESERVATION')) {
            $_SESSION['error_message'] = "You do not have permission to delete reservation records.";
            redirect('openoffice/roomreservations');
        }
        // Basic placeholder - full implementation would involve careful consideration of data integrity
        // For now, let's assume it means a hard delete of the reservation object.
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId);
        if ($reservation && $reservation['object_type'] === 'reservation') {
            if ($this->reservationModel->deleteObject($reservationId)) {
                 $_SESSION['admin_message'] = "Reservation record ID {$reservationId} deleted successfully.";
            } else {
                 $_SESSION['error_message'] = "Could not delete reservation record ID {$reservationId}.";
            }
        } else {
             $_SESSION['error_message'] = "Reservation record ID {$reservationId} not found.";
        }
        redirect('openoffice/roomreservations');
    }

    /**
     * Helper function to merge adjacent time slots into continuous blocks.
     * Assumes slots are in "HH:MM-HH:MM" format and belong to the same date.
     *
     * @param array $slots An array of time slot strings (e.g., ["08:00-09:00", "09:00-10:00"]).
     * @param string $date The date string (YYYY-MM-DD).
     * @return array An array of merged time ranges, each with 'start' and 'end' full datetime strings.
     */
    private function mergeTimeSlots(array $slots, string $date): array {
        if (empty($slots)) {
            return [];
        }

        // Sort slots to ensure correct order for merging
        usort($slots, function($a, $b) {
            $startA = strtotime(explode('-', $a)[0]);
            $startB = strtotime(explode('-', $b)[0]);
            return $startA - $startB;
        });

        $mergedRanges = [];
        $currentMerge = null;

        foreach ($slots as $slot) {
            $timeParts = explode('-', $slot);
            if (count($timeParts) !== 2) {
                // Skip malformed slots, or handle as an error
                continue;
            }
            $slotStart = trim($timeParts[0]);
            $slotEnd = trim($timeParts[1]);

            $fullStartDateTimeStr = $date . ' ' . $slotStart . ':00';
            $fullEndDateTimeStr = $date . ' ' . $slotEnd . ':00';

            try {
                $currentSlotStart = new DateTime($fullStartDateTimeStr);
                $currentSlotEnd = new DateTime($fullEndDateTimeStr);
            } catch (Exception $e) {
                // Handle invalid date/time format
                continue;
            }

            if ($currentMerge === null) {
                // First slot, start a new merge block
                $currentMerge = [
                    'start' => $currentSlotStart,
                    'end' => $currentSlotEnd
                ];
            } else {
                // Check if current slot is adjacent to or overlaps with the current merge block
                // An adjacent slot's start time should be equal to the current merge's end time
                // Or, if there's a slight overlap (e.g., 09:00-10:00 and 09:30-10:30), extend the end time
                if ($currentSlotStart <= $currentMerge['end']) {
                    // Merge by extending the end time if the current slot extends further
                    if ($currentSlotEnd > $currentMerge['end']) {
                        $currentMerge['end'] = $currentSlotEnd;
                    }
                } else {
                    // Not adjacent/overlapping, so finalize the current merge and start a new one
                    $mergedRanges[] = [
                        'start' => $currentMerge['start']->format('Y-m-d H:i:s'),
                        'end' => $currentMerge['end']->format('Y-m-d H:i:s'),
                        'slot_display' => $currentMerge['start']->format('g:i A') . ' - ' . $currentMerge['end']->format('g:i A')
                    ];
                    $currentMerge = [
                        'start' => $currentSlotStart,
                        'end' => $currentSlotEnd
                    ];
                }
            }
        }

        // Add the last merged block if it exists
        if ($currentMerge !== null) {
            $mergedRanges[] = [
                'start' => $currentMerge['start']->format('Y-m-d H:i:s'),
                'end' => $currentMerge['end']->format('Y-m-d H:i:s'),
                'slot_display' => $currentMerge['start']->format('g:i A') . ' - ' . $currentMerge['end']->format('g:i A')
            ];
        }

        return $mergedRanges;
    }


    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data); 
            require_once $viewFile;
        } else {
            error_log("OpenOffice view file not found: {$viewFile}");
            // More user-friendly error for production
            // For development, die() is okay.
            die('Error: View not found. Please contact support. Attempted to load: ' . htmlspecialchars($view));
        }
    }
}
