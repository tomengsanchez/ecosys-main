<?php

/**
 * DashboardController
 *
 * Handles the display of the main dashboard area for logged-in users.
 */
class DashboardController {
    private $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;

        if (!isLoggedIn()) {
            redirect('auth/login'); 
        }
    }

    /**
     * Display the main dashboard page.
     */
    public function index() {
        // Data to pass to the view
        $data = [
            'pageTitle' => 'Dashboard',
            'welcomeMessage' => 'Welcome to your dashboard, ' . htmlspecialchars($_SESSION['display_name'] ?? 'User') . '!',
            'breadcrumbs' => [ // ADDED Breadcrumbs
                ['label' => 'Home', 'url' => ''],
                ['label' => 'Dashboard']
            ]
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
