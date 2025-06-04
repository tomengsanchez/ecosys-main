<?php

/**
 * DashboardController
 *
 * Handles the display of the main dashboard area for logged-in users.
 */
class DashboardController {
    private $pdo;
    private $reservationModel; // For reservation-specific data
    private $roomModel;        // For room-specific data
    private $userModel;   
    // private $baseObjectModel; // Can be removed if all object types have specific models

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
        // $this->baseObjectModel = new BaseObjectModel($this->pdo); // May not be needed directly

        if (!isLoggedIn()) {
            redirect('auth/login'); 
        }
    }

    /**
     * Display the main dashboard page.
     */
    public function index() {
        $calendarEvents = [];
        $reservations = $this->reservationModel->getAllReservations(
            ['object_status' => ['pending', 'approved']] 
        );

        if ($reservations) {
            foreach ($reservations as $res) {
                // Use RoomModel to get room details
                $room = $this->roomModel->getRoomById($res['object_parent']);
                $roomName = $room ? $room['object_title'] : 'Unknown Room';

                $user = $this->userModel->findUserById($res['object_author']);
                $userName = $user ? $user['display_name'] : 'Unknown User';

                $color = '#f0ad4e'; // Yellow for 'pending' (default)
                if ($res['object_status'] === 'approved') {
                    $color = '#5cb85c'; // Green for 'approved'
                }

                $rawStartTime = $res['meta']['reservation_start_datetime'] ?? '';
                $rawEndTime = $res['meta']['reservation_end_datetime'] ?? '';

                $calendarEvents[] = [
                    'title' => $roomName . ' (' . $userName . ')',
                    'start' => $rawStartTime, 
                    'end' => $rawEndTime,
                    'color' => $color,
                    'extendedProps' => [
                        'purpose' => $res['object_content'] ?? 'N/A',
                        'status' => ucfirst($res['object_status']),
                        'roomName' => $roomName,
                        'userName' => $userName,
                        'formattedStartTime' => format_datetime_for_display($rawStartTime),
                        'formattedEndTime' => format_datetime_for_display($rawEndTime)
                    ]
                ];
            }
        }
        
        $data = [
            'pageTitle' => 'Dashboard',
            'welcomeMessage' => 'Welcome to your dashboard, ' . htmlspecialchars($_SESSION['display_name'] ?? 'User') . '!',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => ''], 
                ['label' => 'Dashboard']
            ],
            'calendarEvents' => json_encode($calendarEvents) 
        ];

        $this->view('dashboard/index', $data);
    }

    /**
     * Load a view file.
     */
    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data);
            require_once $viewFile;
        } else {
            error_log("View file not found: {$viewFile}");
            die('View not found.'); 
        }
    }
}
