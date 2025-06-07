<?php

/**
 * SystemInfoController
 *
 * Handles displaying system and application information.
 */
class SystemInfoController {
    private $pdo;
    private $userModel;
    private $roomModel;
    private $vehicleModel;
    private $reservationModel; // For counting reservations

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo);
        $this->roomModel = new RoomModel($this->pdo);
        $this->vehicleModel = new VehicleModel($this->pdo);
        $this->reservationModel = new ReservationModel($this->pdo);


        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        // Ensure user has permission to view this page
        if (!userHasCapability('VIEW_SYSTEM_INFO')) {
            $_SESSION['error_message'] = "You do not have permission to view system information.";
            redirect('admin'); // Or dashboard
        }
    }

    /**
     * Display the system information page.
     */
    public function index() {
        $systemInfo = [];

        // PHP & Server Info
        $systemInfo['php_version'] = phpversion();
        $systemInfo['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
        $systemInfo['php_memory_limit'] = ini_get('memory_limit');
        $systemInfo['current_memory_usage'] = round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB';
        $systemInfo['peak_memory_usage'] = round(memory_get_peak_usage(true) / (1024 * 1024), 2) . ' MB';
        
        // Disk Space (for the partition of the current script's directory)
        // Suppress errors if disk_free_space/disk_total_space are disabled or path is invalid
        $scriptPath = __DIR__; // A directory on the current partition
        $diskTotalSpace = @disk_total_space($scriptPath);
        $diskFreeSpace = @disk_free_space($scriptPath);

        if ($diskTotalSpace !== false && $diskTotalSpace > 0) {
            $systemInfo['disk_total_space'] = round($diskTotalSpace / (1024 * 1024 * 1024), 2) . ' GB';
            if ($diskFreeSpace !== false) {
                $systemInfo['disk_free_space'] = round($diskFreeSpace / (1024 * 1024 * 1024), 2) . ' GB';
                $systemInfo['disk_used_space'] = round(($diskTotalSpace - $diskFreeSpace) / (1024 * 1024 * 1024), 2) . ' GB';
                $systemInfo['disk_usage_percentage'] = round((($diskTotalSpace - $diskFreeSpace) / $diskTotalSpace) * 100, 2) . '%';
            } else {
                $systemInfo['disk_free_space'] = 'N/A (Could not read free space)';
            }
        } else {
            $systemInfo['disk_total_space'] = 'N/A (Could not read total space)';
            $systemInfo['disk_free_space'] = 'N/A';
        }


        // Database Info
        try {
            $systemInfo['db_server_version'] = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $systemInfo['db_connection_status'] = $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            $systemInfo['db_driver_name'] = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException $e) {
            $systemInfo['db_server_version'] = 'Error: ' . $e->getMessage();
            $systemInfo['db_connection_status'] = 'Error';
        }

        // Application Specific Counts
        $systemInfo['total_users'] = $this->userModel->findUserById(1) ? count($this->userModel->getAllUsers() ?: []) : 'N/A (Error fetching)'; // Simple count
        
        // Count rooms
        $allRooms = $this->roomModel->getAllRooms(['include_meta' => false]); // No need for meta here
        $systemInfo['total_rooms'] = is_array($allRooms) ? count($allRooms) : 'N/A (Error fetching)';

        // Count vehicles
        $allVehicles = $this->vehicleModel->getAllVehicles(['include_meta' => false]);
        $systemInfo['total_vehicles'] = is_array($allVehicles) ? count($allVehicles) : 'N/A (Error fetching)';
        
        // Count room reservations (example: pending and approved)
        $systemInfo['total_room_reservations_pending'] = $this->reservationModel->countObjectsByConditions('reservation', ['o.object_status' => 'pending']);
        $systemInfo['total_room_reservations_approved'] = $this->reservationModel->countObjectsByConditions('reservation', ['o.object_status' => 'approved']);

        // Count vehicle reservations (example: pending and approved)
        $systemInfo['total_vehicle_reservations_pending'] = $this->reservationModel->countObjectsByConditions('vehicle_reservation', ['o.object_status' => 'pending']);
        $systemInfo['total_vehicle_reservations_approved'] = $this->reservationModel->countObjectsByConditions('vehicle_reservation', ['o.object_status' => 'approved']);


        $data = [
            'pageTitle' => 'System Information',
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'System Information']
            ],
            'systemInfo' => $systemInfo
        ];

        $this->view('admin/system_info', $data);
    }

    /**
     * Load a view file.
     *
     * @param string $view The path to the view file (e.g., 'admin/system_info').
     * @param array $data Data to extract and make available to the view.
     */
    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data);
            require_once $viewFile;
        } else {
            error_log("SystemInfoController: View file not found: {$viewFile}");
            die('Error: View not found (' . htmlspecialchars($view) . '). Please contact support.');
        }
    }
}
