<?php

/**
 * AdminController
 *
 * Handles administrative tasks and pages.
 */
class AdminController {
    private $pdo;
    private $userModel; 

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo); 

        // --- Admin Access Control ---
        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        // MODIFIED: Check for 'admin' role instead of user_id == 1
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { 
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
        $this->view('admin/users', $data); 
    }

    /**
     * Display form to add a new user OR process adding a new user.
     */
    public function addUser() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $data = [
                'user_login' => trim($_POST['user_login'] ?? ''),
                'user_email' => trim($_POST['user_email'] ?? ''),
                'display_name' => trim($_POST['display_name'] ?? ''),
                'user_pass' => trim($_POST['user_pass'] ?? ''),
                'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
                'user_role' => trim($_POST['user_role'] ?? 'user'), // ADDED: Get user_role from form
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
            // ADDED: Validate user_role (e.g., ensure it's one of the allowed roles)
            $allowedRoles = ['admin', 'editor', 'user'];
            if (!in_array($data['user_role'], $allowedRoles)) {
                $data['errors']['user_role_err'] = 'Invalid user role selected.';
            }
            
            $existingUserByLogin = $this->userModel->findUserByUsernameOrEmail($data['user_login']);
            if ($existingUserByLogin) {
                $data['errors']['user_login_err'] = 'Username already taken.';
            }
            $existingUserByEmail = $this->userModel->findUserByUsernameOrEmail($data['user_email']);
            if ($existingUserByEmail) {
                 $data['errors']['user_email_err'] = 'Email already registered.';
            }

            if (empty($data['errors'])) {
                $userId = $this->userModel->createUser(
                    $data['user_login'],
                    $data['user_email'],
                    $data['user_pass'],
                    $data['display_name'],
                    $data['user_role'], // Pass user_role to createUser
                    $data['user_login'], 
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
                $this->view('admin/user_form', $data);
            }

        } else {
            $data = [
                'pageTitle' => 'Add New User',
                'user_login' => '', 'user_email' => '', 'display_name' => '', 
                'user_role' => 'user', // Default role for new user form
                'user_status' => 0,
                'errors' => []
            ];
            $this->view('admin/user_form', $data); 
        }
    }

    /**
     * Display form to edit an existing user OR process updating an existing user.
     * @param int $userId The ID of the user to edit.
     */
    public function editUser($userId = null) {
        if ($userId === null) redirect('admin/users');
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
                'user_pass' => trim($_POST['user_pass'] ?? ''), 
                'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
                'user_role' => trim($_POST['user_role'] ?? $user['user_role']), // ADDED: Get user_role
                'user_status' => isset($_POST['user_status']) ? (int)$_POST['user_status'] : (int)$user['user_status'],
                'pageTitle' => 'Edit User',
                'user' => $user, 
                'errors' => []
            ];

            // Validation
            if (empty($data['user_login'])) $data['errors']['user_login_err'] = 'Username is required.';
            if (empty($data['user_email'])) $data['errors']['user_email_err'] = 'Email is required.';
            elseif (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) $data['errors']['user_email_err'] = 'Invalid email format.';
            if (empty($data['display_name'])) $data['errors']['display_name_err'] = 'Display name is required.';
            if (!empty($data['user_pass'])) {
                if (strlen($data['user_pass']) < 6) $data['errors']['user_pass_err'] = 'Password must be at least 6 characters.';
                if ($data['user_pass'] !== $data['confirm_pass']) $data['errors']['confirm_pass_err'] = 'Passwords do not match.';
            }
            // ADDED: Validate user_role
            $allowedRoles = ['admin', 'editor', 'user'];
            if (!in_array($data['user_role'], $allowedRoles)) {
                $data['errors']['user_role_err'] = 'Invalid user role selected.';
            }
            // Prevent changing the role of user_id 1 (super admin) away from 'admin' by other admins
            if ($userId == 1 && $data['user_role'] !== 'admin') {
                 $data['errors']['user_role_err'] = 'The super administrator role cannot be changed.';
                 $data['user_role'] = 'admin'; // Force it back
            }
            
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
                    'user_role' => $data['user_role'], // Pass user_role to updateUser
                    'user_status' => $data['user_status']
                ];
                if (!empty($data['user_pass'])) {
                    $updateData['user_pass'] = $data['user_pass']; 
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
            $data = [
                'pageTitle' => 'Edit User',
                'user_id' => $user['user_id'],
                'user_login' => $user['user_login'],
                'user_email' => $user['user_email'],
                'display_name' => $user['display_name'],
                'user_role' => $user['user_role'], // Pass current role to the form
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
        if ($userId === null) redirect('admin/users');
        $userId = (int)$userId;
        
        $userToDelete = $this->userModel->findUserById($userId);

        if (!$userToDelete) {
            $_SESSION['admin_message'] = 'Error: User not found.';
        } elseif ($userId == $_SESSION['user_id']) {
             $_SESSION['admin_message'] = 'Error: You cannot delete your own account.';
        } elseif ($userToDelete['user_role'] === 'admin' && $userId == 1) { // Specifically protect user_id 1 if they are admin
            $_SESSION['admin_message'] = 'Error: The primary super administrator account (ID 1) cannot be deleted.';
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
