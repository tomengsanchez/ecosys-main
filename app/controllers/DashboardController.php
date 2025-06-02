<?php

/**
 * DashboardController
 *
 * Handles the display of the main dashboard area for logged-in users.
 */
class DashboardController {
    private $pdo;
    private $objectModel; // Added for fetching reservations
    private $userModel;   // Added for fetching user names

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->objectModel = new ObjectModel($this->pdo); // Instantiate ObjectModel
        $this->userModel = new UserModel($this->pdo);     // Instantiate UserModel

        if (!isLoggedIn()) {
            redirect('auth/login'); 
        }
    }

    /**
     * Display the main dashboard page.
     */
    public function index() {
        // --- Fetch and Process Data for Calendar ---
        $calendarEvents = [];
        // Fetch all objects of type 'reservation' that are not denied or cancelled
        $reservations = $this->objectModel->getObjectsByConditions(
            'reservation', 
            ['object_status' => ['pending', 'approved']] // Only show pending and approved
        );

        if ($reservations) {
            foreach ($reservations as $res) {
                // Get the room name from the reservation's parent object
                $room = $this->objectModel->getObjectById($res['object_parent']);
                $roomName = $room ? $room['object_title'] : 'Unknown Room';

                // Get the user's name from the reservation's author
                $user = $this->userModel->findUserById($res['object_author']);
                $userName = $user ? $user['display_name'] : 'Unknown User';

                // Set event color based on status
                $color = '#f0ad4e'; // Yellow for 'pending' (default)
                if ($res['object_status'] === 'approved') {
                    $color = '#5cb85c'; // Green for 'approved'
                }

                // Create the event array for FullCalendar
                $calendarEvents[] = [
                    'title' => $roomName . ' (' . $userName . ')',
                    'start' => $res['meta']['reservation_start_datetime'],
                    'end' => $res['meta']['reservation_end_datetime'],
                    'color' => $color,
                    'extendedProps' => [
                        'purpose' => $res['object_content'],
                        'status' => ucfirst($res['object_status']),
                        'roomName' => $roomName,
                        'userName' => $userName
                    ]
                ];
            }
        }
        
        // --- Prepare Data for the View ---
        $data = [
            'pageTitle' => 'Dashboard',
            'welcomeMessage' => 'Welcome to your dashboard, ' . htmlspecialchars($_SESSION['display_name'] ?? 'User') . '!',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => ''],
                ['label' => 'Dashboard']
            ],
            'calendarEvents' => json_encode($calendarEvents) // Pass events as a JSON string
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
