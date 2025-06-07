<?php

/**
 * AuthController
 *
 * Handles user authentication (login, logout).
 */
class AuthController extends BaseController {
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
        if (isLoggedIn()) {
            redirect('dashboard'); 
        }
        $this->view('auth/login');
    }

    /**
     * Process the login form submission.
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
                $this->setFlashMessage('error', 'CSRF token validation failed. Please try again.');
                redirect('auth/login');
            }

            $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $errors = [];

            if (empty($usernameOrEmail)) $errors['username_or_email_err'] = 'Please enter username or email.';
            if (empty($password)) $errors['password_err'] = 'Please enter your password.';

            if (empty($errors)) {
                $user = $this->userModel->findUserByUsernameOrEmail($usernameOrEmail);

                if ($user) {
                    // error_log("Auth: User found. Submitted password: " . $password);
                    // error_log("Auth: Hashed password from DB: " . $user['user_pass']);
                    
                    if (password_verify($password, $user['user_pass'])) {
                        // error_log("Auth: Password verification SUCCESSFUL.");
                        $this->createUserSession($user); // This will now also store the role
                        redirect('dashboard'); 
                    } else {
                        // error_log("Auth: Password verification FAILED.");
                        $errors['login_err'] = 'Invalid username/email or password.';
                        $this->view('auth/login', ['errors' => $errors, 'username_or_email' => $usernameOrEmail]);
                    }
                } else {
                    $errors['login_err'] = 'Invalid username/email or password.';
                    $this->view('auth/login', ['errors' => $errors, 'username_or_email' => $usernameOrEmail]);
                }
            } else {
                $this->view('auth/login', ['errors' => $errors, 'username_or_email' => $usernameOrEmail]);
            }
        } else {
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
        $_SESSION['user_role'] = $user['user_role']; // ADDED: Store user role in session
    }

    /**
     * Log the user out by destroying the session.
     */
    public function logout() {
        $_SESSION = array();
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
        session_destroy();
        redirect('auth/login'); 
    }
}
