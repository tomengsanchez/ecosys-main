<?php

/**
 * AdminController
 *
 * Handles administrative tasks and pages.
 */
class AdminController {
    private $pdo;
    // You might load other models here, e.g., UserModel, ObjectModel

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;

        // --- Admin Access Control ---
        // Ensure user is logged in
        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        // Ensure user is an administrator
        // For now, we'll assume user_id 1 is the admin.
        // In a real system, you'd check a role from the database.
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
            // If not admin, redirect to the regular dashboard or show an error
            $_SESSION['error_message'] = "You do not have permission to access the admin area.";
            redirect('dashboard'); // Or a dedicated 'access denied' page
        }
    }

    /**
     * Display the main admin dashboard.
     */
    public function index() {
        $data = [
            'pageTitle' => 'Admin Dashboard',
            'welcomeMessage' => 'Welcome to the Admin Panel, ' . htmlspecialchars($_SESSION['display_name'] ?? 'Admin') . '!'
            // Fetch admin-specific data here (e.g., site stats, recent users)
        ];

        // Load the admin dashboard view
        $this->view('admin/index', $data);
    }

    /**
     * Example: Manage Users page (stub)
     */
    public function users() {
        // In a real application, you would fetch users from UserModel
        // For example: $users = (new UserModel($this->pdo))->getAllUsers();
        $data = [
            'pageTitle' => 'Manage Users',
            // 'users' => $users 
        ];
        $this->view('admin/users', $data); // We'll create this view later if needed
    }


    /**
     * Load a view file for the admin area.
     *
     * @param string $view The view file name (e.g., 'admin/index').
     * @param array $data Data to pass to the view.
     */
    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data);
            require_once $viewFile;
        } else {
            error_log("Admin view file not found: {$viewFile}");
            die('Admin view not found.');
        }
    }
}
