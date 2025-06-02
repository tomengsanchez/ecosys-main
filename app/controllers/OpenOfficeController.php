<?php

/**
 * OpenOfficeController
 *
 * Handles operations related to the Open Office module, including Rooms.
 */
class OpenOfficeController {
    private $pdo;
    private $objectModel;

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->objectModel = new ObjectModel($this->pdo);

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
    }

    /**
     * Display the list of rooms. (R in CRUD)
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
                ['label' => 'Open Office', 'url' => '#'], // Or a dedicated Open Office dashboard
                ['label' => 'Manage Rooms']
            ]
        ];
        $this->view('openoffice/rooms_list', $data);
    }

    /**
     * Display form to add a new room OR process adding a new room. (C in CRUD)
     */
    public function addRoom() {
        if (!userHasCapability('MANAGE_ROOMS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to add rooms.'; // Using admin_message for consistency with AdminController
            redirect('openoffice/rooms');
        }

        $commonData = [
            'pageTitle' => 'Add New Room',
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => '#'],
                ['label' => 'Manage Rooms', 'url' => 'openoffice/rooms'],
                ['label' => 'Add Room']
            ],
            'room_statuses' => ['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance']
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
                 $data['meta_fields']['room_capacity'] = 0; // Reset to a safe default for redisplay
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
                    'object_status' => $data['object_status'], // Using object_status for room's operational status
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
            // Prepare empty form data for GET request
            $data = array_merge($commonData, [
                'object_title' => '', 'object_content' => '', 'object_status' => 'available',
                'meta_fields' => ['room_capacity' => 0, 'room_location' => '', 'room_equipment' => ''],
                'errors' => []
            ]);
            $this->view('openoffice/room_form', $data);
        }
    }

    /**
     * Display form to edit an existing room OR process updating an existing room. (U in CRUD)
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
            'room_id' => $roomId, // object_id
            'breadcrumbs' => [
                ['label' => 'Open Office', 'url' => '#'],
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
            // Preserve original room data for the view if there are errors
            $data['original_room_data'] = $room;


            if (empty($data['object_title'])) $data['errors']['object_title_err'] = 'Room Name is required.';
            if ($data['meta_fields']['room_capacity'] === false || $data['meta_fields']['room_capacity'] < 0) {
                 $data['errors']['room_capacity_err'] = 'Capacity must be a valid non-negative number.';
                 $data['meta_fields']['room_capacity'] = $room['meta']['room_capacity'] ?? 0; // Revert to original on error
            }
            if (!array_key_exists($data['object_status'], $data['room_statuses'])) {
                $data['errors']['object_status_err'] = 'Invalid room status selected.';
            }

            if (empty($data['errors'])) {
                $updateData = [
                    'object_title' => $data['object_title'],
                    'object_content' => $data['object_content'],
                    'object_status' => $data['object_status'],
                    // object_name (slug) could be updated if title changes, or kept same. For simplicity, not auto-updating slug here.
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
            // Prepare form data for GET request (editing existing room)
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
     * Delete a room. (D in CRUD)
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

        if ($this->objectModel->deleteObject($roomId)) {
            $_SESSION['admin_message'] = 'Room "' . htmlspecialchars($room['object_title']) . '" deleted successfully.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete room "' . htmlspecialchars($room['object_title']) . '".';
        }
        redirect('openoffice/rooms');
    }


    /**
     * Load a view file for the openoffice area.
     * Ensures the view file exists before requiring it.
     *
     * @param string $view The view file name (e.g., 'openoffice/rooms_list').
     * @param array $data Data to extract for the view.
     */
    protected function view($view, $data = []) {
        // Construct the full path to the view file
        // Assumes this controller is in app/controllers, and views are in app/views
        $viewFile = __DIR__ . '/../views/' . $view . '.php';

        if (file_exists($viewFile)) {
            extract($data); // Extracts $data array into individual variables
            require_once $viewFile;
        } else {
            // Log the error and display a generic error message
            error_log("OpenOffice view file not found: {$viewFile}");
            // It's generally better to have a dedicated error page or handler
            die('Error: View not found. Please contact support. Attempted to load: ' . $view);
        }
    }
}
