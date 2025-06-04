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
        
        $viewStartDate = null;
        $viewEndDate = null;

        if ($startDateStr) {
            try {
                $viewStartDate = new DateTime($startDateStr);
            } catch (Exception $e) {
                error_log("Invalid start date from FullCalendar: " . $startDateStr . " Error: " . $e->getMessage());
                $viewStartDate = null; 
            }
        }
        if ($endDateStr) {
             try {
                $viewEndDate = new DateTime($endDateStr);
            } catch (Exception $e) {
                error_log("Invalid end date from FullCalendar: " . $endDateStr . " Error: " . $e->getMessage());
                $viewEndDate = null; 
            }
        }

        $reservations = []; // Initialize reservations array

        // Fetch reservations within the date range
        if ($viewStartDate && $viewEndDate) {
            // *** FIXED: Added 'reservation' as the first parameter for object_type ***
            $reservations = $this->reservationModel->getReservationsInDateRange(
                'reservation', // Specify the object type for room reservations
                $viewStartDate->format('Y-m-d H:i:s'), 
                $viewEndDate->format('Y-m-d H:i:s'),
                ['pending', 'approved'] // Statuses to fetch
            );
        } else {
            // Fallback if no date range is provided (though FullCalendar usually provides it)
            error_log("FullCalendar did not provide start/end dates. Fetching all relevant reservations of type 'reservation'.");
            // This call correctly defaults to 'reservation' type in ReservationModel's getAllReservationsOfType
            $reservations = $this->reservationModel->getAllReservationsOfType(
                'reservation', // Explicitly state 'reservation' type
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
                    try {
                        // Validate date strings before using them
                        new DateTime($rawStartTime);
                        new DateTime($rawEndTime);

                        $calendarEvents[] = [
                            'title' => htmlspecialchars($roomName . ' (' . $userName . ')'), // Ensure HTML entities are encoded
                            'start' => $rawStartTime, 
                            'end' => $rawEndTime,
                            'color' => $color,
                            'allDay' => false, // Assuming reservations are not all-day events
                            'extendedProps' => [
                                'reservation_id' => $res['object_id'], 
                                'purpose' => htmlspecialchars($res['object_content'] ?? 'N/A'),
                                'status' => htmlspecialchars(ucfirst($res['object_status'])),
                                'roomName' => htmlspecialchars($roomName),
                                'userName' => htmlspecialchars($userName),
                                'formattedStartTime' => format_datetime_for_display($rawStartTime),
                                'formattedEndTime' => format_datetime_for_display($rawEndTime)
                            ]
                        ];
                    } catch (Exception $e) {
                        error_log("Invalid date format for reservation ID " . $res['object_id'] . ". Start: " . $rawStartTime . ", End: " . $rawEndTime . ". Error: " . $e->getMessage());
                    }
                } else {
                     error_log("Missing start or end time for reservation ID " . $res['object_id']);
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
