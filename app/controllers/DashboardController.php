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
     * The calendar events will be loaded via AJAX.
     */
    public function index() {
        $data = [
            'pageTitle' => 'Dashboard',
            'welcomeMessage' => 'Welcome to your dashboard, ' . htmlspecialchars($_SESSION['display_name'] ?? 'User') . '!',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => ''], 
                ['label' => 'Dashboard']
            ]
            // 'calendarEvents' JSON is no longer passed directly from here.
        ];

        $this->view('dashboard/index', $data);
    }

    /**
     * AJAX endpoint to fetch calendar events.
     * Outputs JSON formatted data for FullCalendar.
     * Respects 'start' and 'end' parameters from FullCalendar for date range filtering.
     */
    public function ajaxCalendarEvents() {
        header('Content-Type: application/json');
        
        $calendarEvents = [];
        
        // Get start and end dates from FullCalendar's request (usually in ISO8601 format)
        $startDateStr = $_GET['start'] ?? null;
        $endDateStr = $_GET['end'] ?? null;

        // Basic validation for date strings (you might want more robust validation)
        // FullCalendar sends dates like '2023-05-01T00:00:00-07:00' or '2023-05-01'
        // For database queries, we typically need 'YYYY-MM-DD HH:MM:SS' or 'YYYY-MM-DD'
        
        $viewStartDate = null;
        $viewEndDate = null;

        if ($startDateStr) {
            try {
                $viewStartDate = new DateTime($startDateStr);
            } catch (Exception $e) {
                error_log("Invalid start date from FullCalendar: " . $startDateStr);
                $viewStartDate = null; // Or handle error appropriately
            }
        }
        if ($endDateStr) {
             try {
                $viewEndDate = new DateTime($endDateStr);
            } catch (Exception $e) {
                error_log("Invalid end date from FullCalendar: " . $endDateStr);
                $viewEndDate = null; // Or handle error appropriately
            }
        }

        // Fetch reservations within the date range
        // The ReservationModel will need a method like getReservationsInDateRange
        if ($viewStartDate && $viewEndDate) {
            $reservations = $this->reservationModel->getReservationsInDateRange(
                $viewStartDate->format('Y-m-d H:i:s'), 
                $viewEndDate->format('Y-m-d H:i:s'),
                ['pending', 'approved'] // Statuses to fetch
            );
        } else {
            // Fallback if no date range is provided (though FullCalendar usually provides it)
            // Or, you might choose to return an error or an empty set.
            error_log("FullCalendar did not provide start/end dates. Fetching all relevant reservations.");
            $reservations = $this->reservationModel->getAllReservations(
                ['o.object_status' => ['pending', 'approved']] 
            );
        }


        if ($reservations) {
            foreach ($reservations as $res) {
                // Use RoomModel to get room details
                $room = null;
                if (!empty($res['object_parent'])) {
                    $room = $this->roomModel->getRoomById($res['object_parent']);
                }
                $roomName = $room ? $room['object_title'] : 'Unknown Room';

                $user = null;
                if (!empty($res['object_author'])) {
                     $user = $this->userModel->findUserById($res['object_author']);
                }
                $userName = $user ? $user['display_name'] : 'Unknown User';

                $color = '#f0ad4e'; // Yellow for 'pending' (default)
                if ($res['object_status'] === 'approved') {
                    $color = '#5cb85c'; // Green for 'approved'
                }

                $rawStartTime = $res['meta']['reservation_start_datetime'] ?? '';
                $rawEndTime = $res['meta']['reservation_end_datetime'] ?? '';

                // Ensure start and end times are valid before adding to calendar
                if (!empty($rawStartTime) && !empty($rawEndTime)) {
                    $calendarEvents[] = [
                        'title' => $roomName . ' (' . $userName . ')',
                        'start' => $rawStartTime, 
                        'end' => $rawEndTime,
                        'color' => $color,
                        'allDay' => false, // Assuming reservations are not all-day events
                        'extendedProps' => [
                            'reservation_id' => $res['object_id'], // Useful for eventClick
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
        }
        
        echo json_encode($calendarEvents);
        exit; // Terminate script after sending JSON
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
