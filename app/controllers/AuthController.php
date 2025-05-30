<?php

/**
 * AuthController
 *
 * Handles user authentication (login, logout).
 */
class AuthController {
    private $pdo;
    private $userModel;

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo); // Instantiate UserModel
    }

    /**
     * Display the login page.
     * If the user is already logged in, redirect to the dashboard.
     */
    public function login() {
        // If user is already logged in, redirect them to the dashboard
        if (isLoggedIn()) {
            redirect('dashboard'); // Assumes a DashboardController and index action exist
        }

        // Otherwise, display the login form
        // We'll create this view file next: app/views/auth/login.php
        $this->view('auth/login');
    }

    /**
     * Process the login form submission.
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Sanitize POST data
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            $errors = [];

            // Validate input
            if (empty($usernameOrEmail)) {
                $errors['username_or_email_err'] = 'Please enter username or email.';
            }
            if (empty($password)) {
                $errors['password_err'] = 'Please enter your password.';
            }

            // If no validation errors, proceed to check credentials
            if (empty($errors)) {
                $user = $this->userModel->findUserByUsernameOrEmail($usernameOrEmail);

                if ($user) {
                    // DEBUG BLOCK ADDED
                    error_log("Auth: User found. Submitted password: " . $password);
                    error_log("Auth: Hashed password from DB: " . $user['user_pass']);
                    
                    // User found, now verify password
                    // Assumes 'user_pass' column in your 'users' table stores hashed passwords
                    if (password_verify($password, $user['user_pass'])) {
                        error_log("Auth: Password verification SUCCESSFUL."); // DEBUG LINE ADDED
                        // Password is correct, start session and store user data
                        $this->createUserSession($user);
                        redirect('dashboard'); // Redirect to dashboard on successful login
                    } else {
                        error_log("Auth: Password verification FAILED."); // DEBUG LINE ADDED
                        // Password is not valid
                        $errors['login_err'] = 'Invalid username/email or password.';
                        $this->view('auth/login', ['errors' => $errors, 'username_or_email' => $usernameOrEmail]);
                    }
                } else {
                    // No user found with that username/email
                    $errors['login_err'] = 'Invalid username/email or password.'; // Generic error for security
                    $this->view('auth/login', ['errors' => $errors, 'username_or_email' => $usernameOrEmail]);
                }
            } else {
                // Validation errors, show login form again with errors
                $this->view('auth/login', ['errors' => $errors, 'username_or_email' => $usernameOrEmail]);
            }

        } else {
            // Not a POST request, redirect to login page
            redirect('auth/login');
        }
    }

    /**
     * Create user session variables.
     *
     * @param array $user User data from the database.
     */
    private function createUserSession($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_login'] = $user['user_login'];
        $_SESSION['user_email'] = $user['user_email'];
        $_SESSION['display_name'] = $user['display_name'];
        // You can add more session variables if needed, e.g., user role
    }

    /**
     * Log the user out by destroying the session.
     */
    public function logout() {
        // Unset all session values
        $_SESSION = array();

        // Get session parameters
        $params = session_get_cookie_params();

        // Delete the actual cookie.
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );

        // Destroy session
        session_destroy();

        redirect('auth/login'); // Redirect to login page after logout
    }

    /**
     * Load a view file.
     *
     * @param string $view The view file name (e.g., 'auth/login').
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
}
