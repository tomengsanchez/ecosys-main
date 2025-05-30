<?php

/**
 * AdminController
 *
 * Handles administrative tasks and pages.
 */
class AdminController {
    private $pdo;
    private $userModel; // Add UserModel instance

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo); // Instantiate UserModel

        // --- Admin Access Control ---
        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) { // Assuming user_id 1 is admin
            $_SESSION['error_message'] = "You do not have permission to access the admin area.";
            redirect('dashboard');
        }
    }

    /**
     * Display the main admin dashboard.
     */
    public function index() {
        $data = [
            'pageTitle' => 'Admin Dashboard',
            'welcomeMessage' => 'Welcome to the Admin Panel, ' . htmlspecialchars($_SESSION['display_name'] ?? 'Admin') . '!'
        ];
        $this->view('admin/index', $data);
    }

    /**
     * List all users.
     */
    public function users() {
        $users = $this->userModel->getAllUsers();
        $data = [
            'pageTitle' => 'Manage Users',
            'users' => $users
        ];
        $this->view('admin/users', $data); // View to list users
    }

    /**
     * Display form to add a new user OR process adding a new user.
     */
    public function addUser() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Process the form
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $data = [
                'user_login' => trim($_POST['user_login'] ?? ''),
                'user_email' => trim($_POST['user_email'] ?? ''),
                'display_name' => trim($_POST['display_name'] ?? ''),
                'user_pass' => trim($_POST['user_pass'] ?? ''),
                'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
                'user_status' => isset($_POST['user_status']) ? (int)$_POST['user_status'] : 0,
                'pageTitle' => 'Add New User',
                'errors' => []
            ];

            // Validation
            if (empty($data['user_login'])) $data['errors']['user_login_err'] = 'Username is required.';
            if (empty($data['user_email'])) $data['errors']['user_email_err'] = 'Email is required.';
            elseif (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) $data['errors']['user_email_err'] = 'Invalid email format.';
            if (empty($data['display_name'])) $data['errors']['display_name_err'] = 'Display name is required.';
            if (empty($data['user_pass'])) $data['errors']['user_pass_err'] = 'Password is required.';
            elseif (strlen($data['user_pass']) < 6) $data['errors']['user_pass_err'] = 'Password must be at least 6 characters.';
            if ($data['user_pass'] !== $data['confirm_pass']) $data['errors']['confirm_pass_err'] = 'Passwords do not match.';
            
            // Check if username or email already exists
            if (empty($data['errors']['user_login_err']) && $this->userModel->findUserByUsernameOrEmail($data['user_login'])) {
                $data['errors']['user_login_err'] = 'Username already taken.';
            }
            if (empty($data['errors']['user_email_err']) && $this->userModel->findUserByUsernameOrEmail($data['user_email'])) {
                $data['errors']['user_email_err'] = 'Email already registered.';
            }


            if (empty($data['errors'])) {
                // Attempt to create user
                $userId = $this->userModel->createUser(
                    $data['user_login'],
                    $data['user_email'],
                    $data['user_pass'],
                    $data['display_name'],
                    $data['user_login'], // nicename defaults to user_login
                    $data['user_status']
                );

                if ($userId) {
                    $_SESSION['admin_message'] = 'User created successfully!';
                    redirect('admin/users');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not create user.';
                    $this->view('admin/user_form', $data);
                }
            } else {
                // Show form with errors
                $this->view('admin/user_form', $data);
            }

        } else {
            // Display the empty form
            $data = [
                'pageTitle' => 'Add New User',
                'user_login' => '', 'user_email' => '', 'display_name' => '', 'user_status' => 0,
                'errors' => []
            ];
            $this->view('admin/user_form', $data); // View for add/edit user form
        }
    }

    /**
     * Display form to edit an existing user OR process updating an existing user.
     * @param int $userId The ID of the user to edit.
     */
    public function editUser($userId = null) {
        if ($userId === null) {
            redirect('admin/users'); // No ID provided
        }
        $userId = (int)$userId;
        $user = $this->userModel->findUserById($userId);

        if (!$user) {
            $_SESSION['admin_message'] = 'User not found.';
            redirect('admin/users');
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $data = [
                'user_id' => $userId,
                'user_login' => trim($_POST['user_login'] ?? ''),
                'user_email' => trim($_POST['user_email'] ?? ''),
                'display_name' => trim($_POST['display_name'] ?? ''),
                'user_pass' => trim($_POST['user_pass'] ?? ''), // Optional: for changing password
                'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
                'user_status' => isset($_POST['user_status']) ? (int)$_POST['user_status'] : (int)$user['user_status'],
                'pageTitle' => 'Edit User',
                'user' => $user, // Pass existing user data to prefill form
                'errors' => []
            ];

            // Validation (similar to addUser, but consider existing values)
            if (empty($data['user_login'])) $data['errors']['user_login_err'] = 'Username is required.';
            if (empty($data['user_email'])) $data['errors']['user_email_err'] = 'Email is required.';
            elseif (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) $data['errors']['user_email_err'] = 'Invalid email format.';
            if (empty($data['display_name'])) $data['errors']['display_name_err'] = 'Display name is required.';

            // Password validation only if a new password is provided
            if (!empty($data['user_pass'])) {
                if (strlen($data['user_pass']) < 6) $data['errors']['user_pass_err'] = 'Password must be at least 6 characters.';
                if ($data['user_pass'] !== $data['confirm_pass']) $data['errors']['confirm_pass_err'] = 'Passwords do not match.';
            }
            
            // Check if new username or email already exists (and doesn't belong to current user)
            $existingUserByLogin = $this->userModel->findUserByUsernameOrEmail($data['user_login']);
            if ($existingUserByLogin && $existingUserByLogin['user_id'] != $userId) {
                 $data['errors']['user_login_err'] = 'Username already taken by another user.';
            }
            $existingUserByEmail = $this->userModel->findUserByUsernameOrEmail($data['user_email']);
            if ($existingUserByEmail && $existingUserByEmail['user_id'] != $userId) {
                 $data['errors']['user_email_err'] = 'Email already registered by another user.';
            }


            if (empty($data['errors'])) {
                $updateData = [
                    'user_login' => $data['user_login'],
                    'user_email' => $data['user_email'],
                    'display_name' => $data['display_name'],
                    'user_status' => $data['user_status']
                    // 'user_nicename' could be updated too if you have a field for it
                ];
                if (!empty($data['user_pass'])) {
                    $updateData['user_pass'] = $data['user_pass']; // UserModel will hash it
                }

                if ($this->userModel->updateUser($userId, $updateData)) {
                    $_SESSION['admin_message'] = 'User updated successfully!';
                    redirect('admin/users');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not update user.';
                    $this->view('admin/user_form', $data);
                }
            } else {
                $this->view('admin/user_form', $data);
            }

        } else {
            // Display the form prefilled with user data
            $data = [
                'pageTitle' => 'Edit User',
                'user_id' => $user['user_id'],
                'user_login' => $user['user_login'],
                'user_email' => $user['user_email'],
                'display_name' => $user['display_name'],
                'user_status' => (int)$user['user_status'],
                'user' => $user,
                'errors' => []
            ];
            $this->view('admin/user_form', $data);
        }
    }

    /**
     * Delete a user.
     * @param int $userId The ID of the user to delete.
     */
    public function deleteUser($userId = null) {
        if ($userId === null) {
            redirect('admin/users');
        }
        $userId = (int)$userId;

        // Add CSRF token check here for POST requests if you implement it
        // For simplicity, we'll allow GET for deletion for now, but POST with CSRF is better.
        
        if ($userId == $_SESSION['user_id']) {
             $_SESSION['admin_message'] = 'Error: You cannot delete your own account.';
        } elseif ($userId == 1) { // Super admin cannot be deleted
            $_SESSION['admin_message'] = 'Error: The super administrator account cannot be deleted.';
        } elseif ($this->userModel->deleteUser($userId)) {
            $_SESSION['admin_message'] = 'User deleted successfully.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete user.';
        }
        redirect('admin/users');
    }


    /**
     * Load a view file for the admin area.
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
