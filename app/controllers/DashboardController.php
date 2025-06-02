<?php

/**
 * DashboardController
 *
 * Handles the display of the main dashboard area for logged-in users.
 */
class DashboardController {
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
     * Display the main dashboard page.
     */
    public function index() {
        // --- Fetch and Process Data for Calendar ---
        $calendarEvents = [];
        $reservations = $this->objectModel->getObjectsByConditions(
            'reservation', 
            ['object_status' => ['pending', 'approved']] 
        );

        if ($reservations) {
            foreach ($reservations as $res) {
                $room = $this->objectModel->getObjectById($res['object_parent']);
                $roomName = $room ? $room['object_title'] : 'Unknown Room';

                $user = $this->userModel->findUserById($res['object_author']);
                $userName = $user ? $user['display_name'] : 'Unknown User';

                $color = '#f0ad4e'; // Yellow for 'pending' (default)
                if ($res['object_status'] === 'approved') {
                    $color = '#5cb85c'; // Green for 'approved'
                }

                // Get raw start and end times
                $rawStartTime = $res['meta']['reservation_start_datetime'] ?? '';
                $rawEndTime = $res['meta']['reservation_end_datetime'] ?? '';

                // Create the event array for FullCalendar
                $calendarEvents[] = [
                    'title' => $roomName . ' (' . $userName . ')',
                    'start' => $rawStartTime, // FullCalendar needs ISO-like format for 'start' and 'end'
                    'end' => $rawEndTime,
                    'color' => $color,
                    'extendedProps' => [
                        'purpose' => $res['object_content'] ?? 'N/A',
                        'status' => ucfirst($res['object_status']),
                        'roomName' => $roomName,
                        'userName' => $userName,
                        'formattedStartTime' => format_datetime_for_display($rawStartTime), // Pre-formatted for tooltip
                        'formattedEndTime' => format_datetime_for_display($rawEndTime)    // Pre-formatted for tooltip
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
