<?php

/**
 * OpenOfficeController
 *
 * Handles operations related to the Open Office module, including Rooms and Reservations.
 */
class OpenOfficeController {
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
    public function rooms() {
        if (!userHasCapability('VIEW_ROOMS')) {
            $_SESSION['error_message'] = "You do not have permission to view rooms.";
            redirect('dashboard');
        }
        
        $data = [
            'pageTitle' => 'Manage Rooms',
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], ['label' => 'Rooms List']] 
        ];
        $this->view('openoffice/rooms_list', $data);
    }

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

        $rooms = $this->roomModel->getAllRooms(['include_meta' => true]); 
        $dataOutput = []; 

        if ($rooms) {
            foreach ($rooms as $room) {
                $actionsHtml = '';
                $actionsHtml .= '<a href="' . BASE_URL . 'OpenOffice/roomDetails/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-info me-1" title="View Details"><i class="fas fa-eye"></i></a>';
                
                if (userHasCapability('EDIT_ROOMS')) {
                    $actionsHtml .= '<a href="' . BASE_URL . 'OpenOffice/editRoom/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                }
                if (userHasCapability('DELETE_ROOMS')) {
                    $actionsHtml .= ' <a href="' . BASE_URL . 'OpenOffice/deleteRoom/' . htmlspecialchars($room['object_id']) . '" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm(\'Are you sure you want to delete the room &quot;' . htmlspecialchars(addslashes($room['object_title'])) . '&quot;? This may affect existing reservations.\');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>';
                }
                if (userHasCapability('CREATE_ROOM_RESERVATIONS')) {
                     $actionsHtml .= ' <a href="' . BASE_URL . 'OpenOffice/createreservation/' . htmlspecialchars($room['object_id']) . '" class="btn btn-sm btn-success ms-1" title="Book Room"><i class="fas fa-calendar-plus"></i></a>';
                }

                $dataOutput[] = [
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
            "draw"            => intval($_GET['draw'] ?? 0), 
            "recordsTotal"    => count($dataOutput), 
            "recordsFiltered" => count($dataOutput), 
            "data"            => $dataOutput
        ];
        echo json_encode($output);
        exit; 
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

        $existingReservations = $this->reservationModel->getReservationsByParentId($roomId, 'reservation', [], ['limit' => 1]);
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

    public function roomDetails($roomId = null) {
        if (!userHasCapability('VIEW_ROOMS')) {
            $_SESSION['error_message'] = "You do not have permission to view room details.";
            redirect('dashboard');
        }

        if ($roomId === null) {
            $_SESSION['error_message'] = 'No room ID specified.';
            redirect('OpenOffice/rooms'); 
        }

        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId);

        if (!$room) {
            $_SESSION['error_message'] = 'Room not found.';
            redirect('OpenOffice/rooms'); 
        }

        $data = [
            'pageTitle' => 'Room Details: ' . htmlspecialchars($room['object_title']),
            'room' => $room, 
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], 
                ['label' => 'Rooms', 'url' => 'OpenOffice/rooms'],       
                ['label' => 'Room Details: ' . htmlspecialchars($room['object_title'])]
            ]
        ];
        $this->view('openoffice/room_details', $data);
    }

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
        $reservationObjectType = 'reservation'; 

        if ($selectedDate) {
            $startOfDay = $selectedDate . ' 00:00:00';
            $endOfDay = $selectedDate . ' 23:59:59';

            $pendingReservations = $this->reservationModel->getConflictingReservations(
                $roomId, $startOfDay, $endOfDay, ['pending'], null, $reservationObjectType
            );
            $approvedReservations = $this->reservationModel->getConflictingReservations(
                $roomId, $startOfDay, $endOfDay, ['approved'], null, $reservationObjectType
            );
        } else {
            $pendingReservations = $this->reservationModel->getReservationsByParentId(
                $roomId, $reservationObjectType, ['o.object_status' => 'pending'], ['orderby' => 'meta.reservation_start_datetime', 'orderdir' => 'ASC']
            );
            $approvedReservations = $this->reservationModel->getReservationsByParentId(
                $roomId, $reservationObjectType, ['o.object_status' => 'approved'], ['orderby' => 'meta.reservation_start_datetime', 'orderdir' => 'ASC']
            );
        }

        $enrichedPending = [];
        if ($pendingReservations) {
            foreach ($pendingReservations as $res) {
                $user = $this->userModel->findUserById($res['object_author']);
                $res['user_name'] = $user ? htmlspecialchars($user['display_name']) : 'Unknown User';
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
                $res['user_name'] = $user ? htmlspecialchars($user['display_name']) : 'Unknown User';
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

    public function updateReservationStatus() {
        header('Content-Type: application/json');
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']); exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $reservationId = filter_var($input['reservation_id'] ?? null, FILTER_VALIDATE_INT);
        $status = filter_var($input['status'] ?? null, FILTER_SANITIZE_STRING);

        if (!$reservationId || !in_array($status, ['approved', 'denied'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']); exit;
        }
        $reservation = $this->reservationModel->getObjectById($reservationId);
        if (!$reservation || $reservation['object_type'] !== 'reservation') {
            echo json_encode(['success' => false, 'message' => 'Room reservation not found.']); exit;
        }
        if ($reservation['object_status'] === $status) {
            echo json_encode(['success' => true, 'message' => 'Reservation already ' . $status . '.']); exit;
        }
        $message = ''; $success = false;
        if ($status === 'approved') {
            $roomId = $reservation['object_parent'];
            $startTime = $reservation['meta']['reservation_start_datetime'] ?? null;
            $endTime = $reservation['meta']['reservation_end_datetime'] ?? null;
            if (!$startTime || !$endTime) { $message = 'Error: Reservation is missing start or end time data.'; }
            else {
                $approvedConflicts = $this->reservationModel->getConflictingReservations($roomId, $startTime, $endTime, ['approved'], $reservationId, 'reservation');
                if ($approvedConflicts && count($approvedConflicts) > 0) {
                    $message = 'Error: Cannot approve. This time slot conflicts with an existing approved reservation.';
                } else {
                    if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'approved'])) {
                        $success = true; $message = 'Reservation approved successfully.';
                        // Email user and deny overlapping pending logic
                        // ...
                    } else { $message = 'Could not approve reservation due to a system error.'; }
                }
            }
        } elseif ($status === 'denied') {
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'denied'])) {
                $success = true; $message = 'Reservation denied successfully.';
                // ... (email logic) ...
            } else { $message = 'Could not deny reservation.';}
        }
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }

    public function roomreservations() {
        if (!userHasCapability('VIEW_ALL_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to view all room reservations.";
            redirect('dashboard');
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

    public function ajaxRoomReservationsData() {
        header('Content-Type: application/json'); 
        if (!userHasCapability('VIEW_ALL_ROOM_RESERVATIONS')) {
            echo json_encode(['error' => 'Permission denied.', 'data' => [], 'pagination' => null]); exit;
        }
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $searchTerm = isset($_POST['searchTerm']) ? trim($_POST['searchTerm']) : '';
        $filterStatus = isset($_POST['filterStatus']) ? trim($_POST['filterStatus']) : '';
        $offset = ($page - 1) * $limit;
        $conditions = [];
        if (!empty($filterStatus)) $conditions['o.object_status'] = $filterStatus; 
        $args = ['orderby' => 'o.object_date', 'orderdir' => 'DESC', 'include_meta' => true, 'limit' => $limit, 'offset' => $offset];
        
        $reservations = $this->reservationModel->getAllReservationsOfType('reservation', $conditions, $args, $searchTerm);
        $totalRecords = $this->reservationModel->countObjectsByConditions('reservation', $conditions, $searchTerm);
        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 0;
        $enrichedReservations = [];
        if ($reservations) {
            foreach ($reservations as $res) {
                $roomFromDb = $this->roomModel->getRoomById($res['object_parent']);
                $res['room_name'] = $roomFromDb ? htmlspecialchars($roomFromDb['object_title']) : 'Unknown Room';
                $user = $this->userModel->findUserById($res['object_author']);
                $res['user_display_name'] = $user ? htmlspecialchars($user['display_name']) : 'Unknown User';
                $res['formatted_start_datetime'] = format_datetime_for_display($res['meta']['reservation_start_datetime'] ?? '');
                $res['formatted_end_datetime'] = format_datetime_for_display($res['meta']['reservation_end_datetime'] ?? '');
                $res['formatted_object_date'] = format_datetime_for_display($res['object_date']);
                $enrichedReservations[] = $res;
            }
        }
        echo json_encode(['data' => $enrichedReservations, 'pagination' => ['currentPage' => $page, 'limit' => $limit, 'totalPages' => $totalPages, 'totalRecords' => $totalRecords]]);
        exit; 
    }

    public function createreservation($roomId = null) {
        if (!userHasCapability('CREATE_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to create room reservations.";
            redirect('OpenOffice/rooms'); 
        }
        if ($roomId === null) { $_SESSION['error_message'] = 'No room selected for reservation.'; redirect('OpenOffice/rooms'); }
        $roomId = (int)$roomId;
        $room = $this->roomModel->getRoomById($roomId); 
        if (!$room || $room['object_status'] !== 'available') { 
            $_SESSION['error_message'] = 'This room is not available for reservation or does not exist.'; redirect('OpenOffice/rooms');
        }
        $approvedRoomReservations = $this->reservationModel->getReservationsByParentId($roomId, 'reservation', ['o.object_status' => 'approved']);
        $approvedReservationsData = [];
        if ($approvedRoomReservations) {
            foreach ($approvedRoomReservations as $approvedRes) {
                if (isset($approvedRes['meta']['reservation_start_datetime']) && isset($approvedRes['meta']['reservation_end_datetime'])) {
                    $approvedReservationsData[] = ['start' => $approvedRes['meta']['reservation_start_datetime'], 'end' => $approvedRes['meta']['reservation_end_datetime']];
                }
            }
        }
        $commonData = [
            'pageTitle' => 'Book Room: ' . htmlspecialchars($room['object_title']),
            'room' => $room, 'approved_reservations_data_for_js' => $approvedReservationsData, 
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], ['label' => 'Rooms', 'url' => 'OpenOffice/rooms'], ['label' => 'Book Room']]
        ];
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $reservationDate = trim($_POST['reservation_date'] ?? '');
            $reservationTimeSlots = $_POST['reservation_time_slots'] ?? []; 
            $reservationPurpose = trim($_POST['reservation_purpose'] ?? '');
            if (!is_array($reservationTimeSlots)) $reservationTimeSlots = [$reservationTimeSlots];
            $formData = ['reservation_date' => $reservationDate, 'reservation_time_slots' => $reservationTimeSlots, 'reservation_purpose' => $reservationPurpose, 'errors' => []];
            $data = array_merge($commonData, $formData);
            // Validation
            if (empty($data['reservation_date'])) $data['errors']['date_err'] = 'Reservation date is required.';
            elseif (new DateTime($data['reservation_date']) < new DateTime(date('Y-m-d'))) $data['errors']['date_err'] = 'Reservation date cannot be in the past.';
            if (empty($data['reservation_time_slots'])) $data['errors']['time_slot_err'] = 'At least one time slot is required.';
            if (empty($data['reservation_purpose'])) $data['errors']['purpose_err'] = 'Purpose of reservation is required.';
            
            $mergedReservationRanges = [];
            if (empty($data['errors']) && !empty($data['reservation_time_slots'])) {
                $mergedReservationRanges = $this->mergeTimeSlots($data['reservation_time_slots'], $data['reservation_date']);
                if (empty($mergedReservationRanges)) $data['errors']['time_slot_err'] = 'No valid time slots could be merged.';
            }
            if (empty($data['errors']) && !empty($mergedReservationRanges)) {
                $totalCreated = 0; $failedReservations = []; $reservedSlotsDisplay = [];
                foreach ($mergedReservationRanges as $mergedRange) {
                    $fullStartDateTimeStr = $mergedRange['start']; $fullEndDateTimeStr = $mergedRange['end'];
                    $conflicts = $this->reservationModel->getConflictingReservations($roomId, $fullStartDateTimeStr, $fullEndDateTimeStr, ['approved'], null, 'reservation');
                    if ($conflicts && count($conflicts) > 0) {
                        $failedReservations[] = ['slot' => $mergedRange['slot_display'], 'reason' => 'Conflicts with existing approved reservation.'];
                        $data['errors']['form_err'] = 'Some selected time slots are already booked.'; continue;
                    }
                    $reservationObjectData = [
                        'object_author' => $_SESSION['user_id'], 'object_title' => 'Reservation for ' . $room['object_title'] . ' by ' . $_SESSION['display_name'],
                        'object_type' => 'reservation', 'object_parent' => $roomId, 'object_status' => 'pending', 'object_content' => $data['reservation_purpose'],
                        'meta_fields' => ['reservation_start_datetime' => $fullStartDateTimeStr, 'reservation_end_datetime' => $fullEndDateTimeStr, 'reservation_user_id' => $_SESSION['user_id']]
                    ];
                    $reservationId = $this->reservationModel->createObject($reservationObjectData);
                    if ($reservationId) { $totalCreated++; $reservedSlotsDisplay[] = $mergedRange['slot_display']; /* Email logic */ }
                    else { $failedReservations[] = ['slot' => $mergedRange['slot_display'], 'reason' => 'Failed to save to database.']; }
                }
                if ($totalCreated > 0) {
                    $_SESSION['message'] = "Successfully submitted {$totalCreated} reservation request(s)!";
                    redirect('OpenOffice/myreservations');
                } else { $data['errors']['form_err'] = 'No reservation requests could be submitted.'; $this->view('openoffice/reservation_form', $data); }
            } else { $this->view('openoffice/reservation_form', $data); }
        } else {
            $data = array_merge($commonData, ['reservation_date' => date('Y-m-d'), 'reservation_time_slots' => [], 'reservation_purpose' => '', 'errors' => []]);
            $this->view('openoffice/reservation_form', $data); 
        }
    }

    public function getMultipleSlotsQueueInfo() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        $roomId = filter_var($input['roomId'] ?? null, FILTER_VALIDATE_INT);
        $date = filter_var($input['date'] ?? null, FILTER_SANITIZE_STRING);
        $slots = $input['slots'] ?? []; 

        if (!$roomId || !$date || !is_array($slots) || empty($slots)) {
            error_log("[OpenOfficeController::getMultipleSlotsQueueInfo] Error: Missing or invalid parameters. RoomID: {$roomId}, Date: {$date}, Slots: " . print_r($slots, true));
            echo json_encode(['error' => 'Missing or invalid parameters for room queue info.']);
            exit;
        }
        error_log("[OpenOfficeController::getMultipleSlotsQueueInfo] Processing for RoomID: {$roomId}, Date: {$date}, Slots: " . print_r($slots, true));

        $pendingCounts = [];
        foreach ($slots as $slot) {
            $timeParts = explode('-', $slot);
            if (count($timeParts) !== 2) {
                $pendingCounts[$slot] = ['error' => 'Invalid slot format.'];
                error_log("[OpenOfficeController::getMultipleSlotsQueueInfo] Invalid slot format: {$slot}");
                continue;
            }
            $startTimeStr = $date . ' ' . trim($timeParts[0]) . ':00';
            $endTimeStr = $date . ' ' . trim($timeParts[1]) . ':00';
            try { new DateTime($startTimeStr); new DateTime($endTimeStr); } catch (Exception $e) {
                $pendingCounts[$slot] = ['error' => 'Invalid date or time format in slot.']; 
                error_log("[OpenOfficeController::getMultipleSlotsQueueInfo] Invalid date/time in slot: {$slot}. Error: " . $e->getMessage());
                continue;
            }
            
            $conflictingPending = $this->reservationModel->getConflictingReservations(
                $roomId, $startTimeStr, $endTimeStr, ['pending'], null, 'reservation'
            );
            if ($conflictingPending === false) {
                $pendingCounts[$slot] = ['error' => 'Could not retrieve queue information.'];
                error_log("[OpenOfficeController::getMultipleSlotsQueueInfo] getConflictingReservations returned false for slot: {$slot}");
            } else {
                $pendingCounts[$slot] = count($conflictingPending);
                error_log("[OpenOfficeController::getMultipleSlotsQueueInfo] Slot: {$slot}, Pending Count: " . count($conflictingPending));
            }
        }
        echo json_encode(['pendingCounts' => $pendingCounts]);
        exit;
    }

    public function myreservations() {
        $data = [
            'pageTitle' => 'My Room Reservations',
            'breadcrumbs' => [['label' => 'Open Office', 'url' => 'OpenOffice/rooms'], ['label' => 'My Reservations']],
            'reservation_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'cancelled' => 'Cancelled']
        ];
        $this->view('openoffice/my_reservations_list', $data); 
    }

    public function ajaxGetUserReservations() {
        header('Content-Type: application/json');
        if (!isLoggedIn()) {
            error_log("ajaxGetUserReservations: User not logged in.");
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not logged in"]);
            exit;
        }
        $userId = $_SESSION['user_id'];
        $myReservations = $this->reservationModel->getReservationsByUserId(
            $userId, 'reservation', ['orderby' => 'o.object_date', 'orderdir' => 'DESC', 'include_meta' => true]
        );
        
        $dataOutput = []; 
        if ($myReservations === false) { 
            error_log("ajaxGetUserReservations: Error fetching reservations for user ID {$userId}. Model returned false.");
             echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Could not retrieve reservations."]);
            exit;
        }
        if ($myReservations) {
            foreach ($myReservations as $res) {
                $roomName = 'N/A';
                if (!empty($res['object_parent'])) { 
                    $roomFromDb = $this->roomModel->getRoomById($res['object_parent']);
                    $roomName = $roomFromDb ? htmlspecialchars($roomFromDb['object_title']) : 'Unknown Room';
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
                    $actionsHtml .= '<a href="' . BASE_URL . 'OpenOffice/cancelreservation/' . htmlspecialchars($res['object_id']) . '" 
                                       class="btn btn-sm btn-warning text-dark" title="Cancel Reservation"
                                       onclick="return confirm(\'Are you sure you want to cancel this reservation?\');">
                                        <i class="fas fa-times-circle"></i> Cancel
                                    </a>';
                } else {
                    $actionsHtml = '<span class="text-muted small">No actions</span>';
                }

                $dataOutput[] = [
                    "id" => htmlspecialchars($res['object_id']),
                    "room" => $roomName, 
                    "purpose" => nl2br(htmlspecialchars($res['object_content'] ?? 'N/A')),
                    "start_time" => htmlspecialchars(format_datetime_for_display($res['meta']['reservation_start_datetime'] ?? '')),
                    "end_time" => htmlspecialchars(format_datetime_for_display($res['meta']['reservation_end_datetime'] ?? '')),
                    "requested_on" => htmlspecialchars(format_datetime_for_display($res['object_date'])),
                    "status" => $statusHtml,
                    "actions" => $actionsHtml
                ];
            }
        }
        echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => count($dataOutput), "recordsFiltered" => count($dataOutput), "data" => $dataOutput]);
        exit;
    }

    public function cancelreservation($reservationId = null) {
        if (!userHasCapability('CANCEL_OWN_ROOM_RESERVATIONS')) {
            $_SESSION['error_message'] = "You do not have permission to cancel reservations.";
            redirect('OpenOffice/myreservations');
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId);

        if (!$reservation || $reservation['object_type'] !== 'reservation' || $reservation['object_author'] != $_SESSION['user_id'] || $reservation['object_status'] !== 'pending') {
            $_SESSION['error_message'] = 'Invalid request or reservation cannot be cancelled.';
        } else {
            if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'cancelled'])) {
                $_SESSION['message'] = 'Room reservation request cancelled successfully.';
            } else {
                $_SESSION['error_message'] = 'Could not cancel room reservation request.';
            }
        }
        redirect('OpenOffice/myreservations');
    }
    
    public function approvereservation($reservationId = null) {
        if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            $this->handlePermissionErrorAjax(); return;
        }
        $reservationId = (int)$reservationId;
        $reservationToApprove = $this->reservationModel->getObjectById($reservationId);
        $success = false; $message = 'Invalid request.';

        if ($reservationToApprove && $reservationToApprove['object_type'] === 'reservation') {
            if ($reservationToApprove['object_status'] === 'pending') {
                $roomId = $reservationToApprove['object_parent'];
                $startTime = $reservationToApprove['meta']['reservation_start_datetime'] ?? null;
                $endTime = $reservationToApprove['meta']['reservation_end_datetime'] ?? null;

                if ($startTime && $endTime) {
                    $conflicts = $this->reservationModel->getConflictingReservations($roomId, $startTime, $endTime, ['approved'], $reservationId, 'reservation');
                    if ($conflicts && count($conflicts) > 0) {
                        $message = 'Error: Cannot approve. Conflicts with an existing approved room reservation.';
                    } else {
                        if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'approved'])) {
                            $success = true; $message = 'Room reservation approved successfully.';
                        } else { $message = 'Could not approve room reservation due to a system error.'; }
                    }
                } else { $message = 'Error: Reservation is missing start or end time data.'; }
            } else { $message = 'Only pending room reservations can be approved. This one is ' . $reservationToApprove['object_status'] . '.'; }
        }
        if ($this->isAjaxRequest()) { echo json_encode(['success' => $success, 'message' => $message]); exit; }
        $_SESSION[$success ? 'message' : 'error_message'] = $message;
        redirect('OpenOffice/roomreservations');
    }

    public function denyreservation($reservationId = null) {
         if (!userHasCapability('APPROVE_DENY_ROOM_RESERVATIONS')) {
            $this->handlePermissionErrorAjax(); return;
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId);
        $success = false; $message = 'Invalid request.';

        if ($reservation && $reservation['object_type'] === 'reservation') {
            if (in_array($reservation['object_status'], ['pending', 'approved'])) {
                $originalStatus = $reservation['object_status'];
                if ($this->reservationModel->updateObject($reservationId, ['object_status' => 'denied'])) {
                    $success = true;
                    $message = 'Room reservation ' . ($originalStatus === 'approved' ? 'approval revoked and reservation denied.' : 'denied successfully.');
                } else { $message = 'Could not deny room reservation.'; }
            } else { $message = 'Only pending or approved room reservations can be denied. This one is ' . $reservation['object_status'] . '.';}
        }
        if ($this->isAjaxRequest()) { echo json_encode(['success' => $success, 'message' => $message]); exit; }
        $_SESSION[$success ? 'message' : 'error_message'] = $message;
        redirect('OpenOffice/roomreservations');
    }

    public function deleteAnyReservation($reservationId = null) {
        if (!userHasCapability('DELETE_ANY_ROOM_RESERVATION')) {
            $this->handlePermissionErrorAjax(); return;
        }
        $reservationId = (int)$reservationId;
        $reservation = $this->reservationModel->getObjectById($reservationId);
        $success = false; $message = 'Invalid request.';

        if ($reservation && $reservation['object_type'] === 'reservation') {
            if ($this->reservationModel->deleteObject($reservationId)) { 
                $success = true; $message = "Room reservation record ID {$reservationId} deleted successfully.";
            } else { $message = "Could not delete room reservation record ID {$reservationId}."; }
        } else { $message = "Room reservation record ID {$reservationId} not found or not a room reservation.";}
        
        if ($this->isAjaxRequest()) { echo json_encode(['success' => $success, 'message' => $message]); exit; }
        $_SESSION[$success ? 'message' : 'error_message'] = $message;
        redirect('OpenOffice/roomreservations');
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
            error_log("OpenOffice view file not found: {$viewFile}");
            die('Error: View not found. Please contact support. Attempted to load: ' . htmlspecialchars($view));
        }
    }
}