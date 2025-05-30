<?php

/**
 * AdminController
 *
 * Handles administrative tasks and pages.
 */
class AdminController {
    private $pdo;
    private $userModel; 
    private $departmentModel; 
    private $optionModel; // ADDED: OptionModel instance

    /**
     * Constructor
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo); 
        $this->departmentModel = new DepartmentModel($this->pdo); 
        $this->optionModel = new OptionModel($this->pdo); // ADDED: Instantiate OptionModel

        if (!isLoggedIn()) {
            redirect('auth/login');
        }
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
            'welcomeMessage' => 'Welcome to the Admin Panel, ' . htmlspecialchars($_SESSION['display_name'] ?? 'Admin') . '!',
            'breadcrumbs' => [ 
                ['label' => 'Admin Panel']
            ]
        ];
        $this->view('admin/index', $data); // This view needs a link/card for Site Settings
    }

    // --- User Management Methods ---
    // ... (users, addUser, editUser, deleteUser methods remain here, unchanged for this step) ...
    public function users() {
        $users = $this->userModel->getAllUsers();
        $data = [
            'pageTitle' => 'Manage Users',
            'users' => $users,
            'breadcrumbs' => [ 
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Users']
            ]
        ];
        $this->view('admin/users', $data); 
    }

    public function addUser() {
        $departments = $this->departmentModel->getAllDepartments(); 
        $commonData = [
            'pageTitle' => 'Add New User',
            'departments' => $departments,
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Users', 'url' => 'admin/users'],
                ['label' => 'Add User']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'user_login' => trim($_POST['user_login'] ?? ''),
                'user_email' => trim($_POST['user_email'] ?? ''),
                'display_name' => trim($_POST['display_name'] ?? ''),
                'user_pass' => trim($_POST['user_pass'] ?? ''),
                'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
                'user_role' => trim($_POST['user_role'] ?? 'user'), 
                'department_id' => isset($_POST['department_id']) && !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
                'user_status' => isset($_POST['user_status']) ? (int)$_POST['user_status'] : 0,
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);


            if (empty($data['user_login'])) $data['errors']['user_login_err'] = 'Username is required.';
            if (empty($data['user_email'])) $data['errors']['user_email_err'] = 'Email is required.';
            elseif (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) $data['errors']['user_email_err'] = 'Invalid email format.';
            if (empty($data['display_name'])) $data['errors']['display_name_err'] = 'Display name is required.';
            if (empty($data['user_pass'])) $data['errors']['user_pass_err'] = 'Password is required.';
            elseif (strlen($data['user_pass']) < 6) $data['errors']['user_pass_err'] = 'Password must be at least 6 characters.';
            if ($data['user_pass'] !== $data['confirm_pass']) $data['errors']['confirm_pass_err'] = 'Passwords do not match.';
            
            $allowedRoles = ['admin', 'editor', 'user'];
            if (!in_array($data['user_role'], $allowedRoles)) {
                $data['errors']['user_role_err'] = 'Invalid user role selected.';
            }
            if ($data['department_id'] !== null && !$this->departmentModel->getDepartmentById($data['department_id'])) {
                $data['errors']['department_id_err'] = 'Invalid department selected.';
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
                    $data['user_role'], 
                    $data['department_id'], 
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
            $data = array_merge($commonData, [
                'user_login' => '', 'user_email' => '', 'display_name' => '', 
                'user_role' => 'user', 'department_id' => null, 'user_status' => 0,
                'errors' => []
            ]);
            $this->view('admin/user_form', $data); 
        }
    }
    
    public function editUser($userId = null) {
        if ($userId === null) redirect('admin/users');
        $userId = (int)$userId;
        $user = $this->userModel->findUserById($userId); 
        $departments = $this->departmentModel->getAllDepartments();

        if (!$user) {
            $_SESSION['admin_message'] = 'User not found.';
            redirect('admin/users');
        }
        
        $commonData = [
            'pageTitle' => 'Edit User',
            'departments' => $departments,
            'user' => $user,
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Users', 'url' => 'admin/users'],
                ['label' => 'Edit User: ' . htmlspecialchars($user['display_name'])]
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'user_id' => $userId,
                'user_login' => trim($_POST['user_login'] ?? ''),
                'user_email' => trim($_POST['user_email'] ?? ''),
                'display_name' => trim($_POST['display_name'] ?? ''),
                'user_pass' => trim($_POST['user_pass'] ?? ''), 
                'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
                'user_role' => trim($_POST['user_role'] ?? $user['user_role']), 
                'department_id' => isset($_POST['department_id']) && !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
                'user_status' => isset($_POST['user_status']) ? (int)$_POST['user_status'] : (int)$user['user_status'],
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            $data['user'] = array_merge($user, $formData); 

            if (empty($data['user_login'])) $data['errors']['user_login_err'] = 'Username is required.';
            if (empty($data['user_email'])) $data['errors']['user_email_err'] = 'Email is required.';
            elseif (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) $data['errors']['user_email_err'] = 'Invalid email format.';
            if (empty($data['display_name'])) $data['errors']['display_name_err'] = 'Display name is required.';
            if (!empty($data['user_pass'])) {
                if (strlen($data['user_pass']) < 6) $data['errors']['user_pass_err'] = 'Password must be at least 6 characters.';
                if ($data['user_pass'] !== $data['confirm_pass']) $data['errors']['confirm_pass_err'] = 'Passwords do not match.';
            }
            
            $allowedRoles = ['admin', 'editor', 'user'];
            if (!in_array($data['user_role'], $allowedRoles)) $data['errors']['user_role_err'] = 'Invalid user role selected.';
            if ($userId == 1 && $data['user_role'] !== 'admin') {
                 $data['errors']['user_role_err'] = 'The super administrator role cannot be changed.';
                 $data['user_role'] = 'admin'; 
            }
            if ($data['department_id'] !== null && !$this->departmentModel->getDepartmentById($data['department_id'])) {
                $data['errors']['department_id_err'] = 'Invalid department selected.';
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
                    'user_role' => $data['user_role'], 
                    'department_id' => $data['department_id'], 
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
            $data = array_merge($commonData, [
                'user_id' => $user['user_id'], 
                'user_login' => $user['user_login'],
                'user_email' => $user['user_email'],
                'display_name' => $user['display_name'],
                'user_role' => $user['user_role'], 
                'department_id' => $user['department_id'] ?? null, 
                'user_status' => (int)$user['user_status'],
                'errors' => []
            ]);
            $this->view('admin/user_form', $data);
        }
    }

    public function deleteUser($userId = null) {
        if ($userId === null) redirect('admin/users');
        $userId = (int)$userId;
        $userToDelete = $this->userModel->findUserById($userId);

        if (!$userToDelete) {
            $_SESSION['admin_message'] = 'Error: User not found.';
        } elseif ($userId == $_SESSION['user_id']) {
             $_SESSION['admin_message'] = 'Error: You cannot delete your own account.';
        } elseif ($userToDelete['user_role'] === 'admin' && $userId == 1) { 
            $_SESSION['admin_message'] = 'Error: The primary super administrator account (ID 1) cannot be deleted.';
        } elseif ($this->userModel->deleteUser($userId)) {
            $_SESSION['admin_message'] = 'User deleted successfully.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete user.';
        }
        redirect('admin/users');
    }


    // --- Department Management Methods ---
    // ... (departments, addDepartment, editDepartment, deleteDepartment methods remain here) ...
    public function departments() {
        $departments = $this->departmentModel->getAllDepartments();
        if ($departments) {
            foreach ($departments as &$dept) { 
                $dept['user_count'] = $this->departmentModel->getUserCountByDepartment($dept['department_id']);
            }
            unset($dept); 
        }
        
        $data = [
            'pageTitle' => 'Manage Departments',
            'departments' => $departments,
            'breadcrumbs' => [ 
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Departments']
            ]
        ];
        $this->view('admin/departments', $data); 
    }

    public function addDepartment() {
        $commonData = [
            'pageTitle' => 'Add New Department',
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Departments', 'url' => 'admin/departments'],
                ['label' => 'Add Department']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'department_name' => trim($_POST['department_name'] ?? ''),
                'department_description' => trim($_POST['department_description'] ?? ''),
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            if (empty($data['department_name'])) {
                $data['errors']['department_name_err'] = 'Department name is required.';
            }

            if (empty($data['errors'])) {
                if ($this->departmentModel->createDepartment($data['department_name'], $data['department_description'])) {
                    $_SESSION['admin_message'] = 'Department created successfully!';
                    redirect('admin/departments');
                } else {
                    $data['errors']['form_err'] = 'Could not create department. Name might already exist.';
                    $this->view('admin/department_form', $data); 
                }
            } else {
                $this->view('admin/department_form', $data);
            }
        } else {
            $data = array_merge($commonData, [
                'department_name' => '', 'department_description' => '',
                'errors' => []
            ]);
            $this->view('admin/department_form', $data);
        }
    }

    public function editDepartment($departmentId = null) {
        if ($departmentId === null) redirect('admin/departments');
        $departmentId = (int)$departmentId;
        $department = $this->departmentModel->getDepartmentById($departmentId);

        if (!$department) {
            $_SESSION['admin_message'] = 'Department not found.';
            redirect('admin/departments');
        }

        $commonData = [
            'pageTitle' => 'Edit Department',
            'department' => $department,
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Departments', 'url' => 'admin/departments'],
                ['label' => 'Edit Department: ' . htmlspecialchars($department['department_name'])]
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'department_id' => $departmentId,
                'department_name' => trim($_POST['department_name'] ?? ''),
                'department_description' => trim($_POST['department_description'] ?? ''),
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            $data['department'] = array_merge($department, $formData); 

            if (empty($data['department_name'])) {
                $data['errors']['department_name_err'] = 'Department name is required.';
            }

            if (empty($data['errors'])) {
                if ($this->departmentModel->updateDepartment($departmentId, $data['department_name'], $data['department_description'])) {
                    $_SESSION['admin_message'] = 'Department updated successfully!';
                    redirect('admin/departments');
                } else {
                    $data['errors']['form_err'] = 'Could not update department. Name might already exist.';
                    $this->view('admin/department_form', $data);
                }
            } else {
                $this->view('admin/department_form', $data);
            }
        } else {
            $data = array_merge($commonData, [
                'department_id' => $department['department_id'], 
                'department_name' => $department['department_name'],
                'department_description' => $department['department_description'],
                'errors' => []
            ]);
            $this->view('admin/department_form', $data);
        }
    }

    public function deleteDepartment($departmentId = null) {
        if ($departmentId === null) redirect('admin/departments');
        $departmentId = (int)$departmentId;

        if ($this->departmentModel->deleteDepartment($departmentId)) {
            $_SESSION['admin_message'] = 'Department deleted successfully. Users in this department are now unassigned.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete department.';
        }
        redirect('admin/departments');
    }

    // --- Site Settings Method ---
    public function siteSettings() {
        // Define which options are manageable through the UI
        $manageableOptions = [
            'site_name' => 'Default Site Name',
            'site_tagline' => 'Just another Mainsystem site',
            'admin_email' => 'admin@example.com',
            'items_per_page' => 10,
            // Add more settings as needed, e.g., 'maintenance_mode' => 'off'
        ];
        $optionKeys = array_keys($manageableOptions);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $settingsToSave = [];
            foreach ($optionKeys as $key) {
                if (isset($_POST[$key])) {
                    // Basic sanitization, you might need more specific validation per option
                    $settingsToSave[$key] = trim($_POST[$key]);
                }
            }

            if ($this->optionModel->saveOptions($settingsToSave)) {
                $_SESSION['admin_message'] = 'Site settings updated successfully!';
            } else {
                $_SESSION['admin_message'] = 'Error: Could not save all site settings.';
            }
            // Redirect to refresh the page and show message (or stay and show message)
            redirect('admin/siteSettings');
        }

        // GET request: Load current settings
        $currentSettings = [];
        $dbOptions = $this->optionModel->getOptions($optionKeys);
        foreach ($manageableOptions as $key => $defaultValue) {
            $currentSettings[$key] = $dbOptions[$key] ?? $defaultValue;
        }
        
        $data = [
            'pageTitle' => 'Site Settings',
            'settings' => $currentSettings,
            'manageableOptions' => $manageableOptions, // To know which fields to display
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Site Settings']
            ]
        ];
        $this->view('admin/site_settings', $data); // New view: admin/site_settings.php
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
