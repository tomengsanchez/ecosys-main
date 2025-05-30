<?php

/**
 * DashboardController
 *
 * Handles the display of the main dashboard area for logged-in users.
 */
class DashboardController {
    private $pdo;
    // You might want to load a UserModel or other models here if the dashboard needs data
    // private $userModel; 

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // $this->userModel = new UserModel($this->pdo);

        // Protect the dashboard: only logged-in users can access it.
        if (!isLoggedIn()) {
            redirect('auth/login'); // Redirect to login if not authenticated
        }
    }

    /**
     * Display the main dashboard page.
     */
    public function index() {
        // Data to pass to the view (e.g., user information, dashboard stats)
        $data = [
            'pageTitle' => 'Dashboard',
            'welcomeMessage' => 'Welcome to your dashboard, ' . htmlspecialchars($_SESSION['display_name'] ?? 'User') . '!'
            // You can fetch more data from models here and pass it to the view
        ];

        // Load the dashboard view
        // We'll create this view file next: app/views/dashboard/index.php
        $this->view('dashboard/index', $data);
    }

    /**
     * Load a view file.
     * This is a common helper method, similar to the one in AuthController.
     * You could consider putting this in a BaseController if you have many controllers.
     *
     * @param string $view The view file name (e.g., 'dashboard/index').
     * @param array $data Data to pass to the view.
     */
    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            // Extract data array to variables for use in the view
            extract($data);
            require_once $viewFile;
        } else {
            // View file does not exist
            error_log("View file not found: {$viewFile}");
            die('View not found.'); // Or handle more gracefully
        }
    }

    // You can add more methods here for other dashboard functionalities
    // For example:
    // public function settings() { /* ... */ }
    // public function profile() { /* ... */ }
}
