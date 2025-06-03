<?php

/**
 * DashboardController
 *
 * Handles the display of the main dashboard area for logged-in users.
 */
class DashboardController {
    private $pdo;
    // private $objectModel; // Will be replaced
    private $reservationModel; // For reservation-specific data
    private $baseObjectModel;  // For other object types if needed (e.g. room details)
    private $userModel;   

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // $this->objectModel = new ObjectModel($this->pdo); // Old
        $this->reservationModel = new ReservationModel($this->pdo); // New
        $this->baseObjectModel = new BaseObjectModel($this->pdo);   // New
        $this->userModel = new UserModel($this->pdo);     

        if (!isLoggedIn()) {
            redirect('auth/login'); 
        }
    }

    /**
     * Display the main dashboard page.
     */
    public function index() {
        $calendarEvents = [];
        // Fetch reservations using ReservationModel's specific or inherited method
        $reservations = $this->reservationModel->getAllReservations(
            ['object_status' => ['pending', 'approved']] 
            // BaseObjectModel's getObjectsByConditions is called via ReservationModel
        );

        if ($reservations) {
            foreach ($reservations as $res) {
                // Use BaseObjectModel to get room details (as RoomModel isn't created yet)
                $room = $this->baseObjectModel->getObjectById($res['object_parent']);
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
                ['label' => 'Home', 'url' => ''], // Assuming '' is the base for home
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
