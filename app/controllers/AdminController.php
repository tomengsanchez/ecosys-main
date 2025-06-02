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
    private $optionModel; 
    private $rolePermissionModel; 
    private $roleModel; 

    /**
     * Constructor
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo); 
        $this->departmentModel = new DepartmentModel($this->pdo); 
        $this->optionModel = new OptionModel($this->pdo); 
        $this->rolePermissionModel = new RolePermissionModel($this->pdo); 
        $this->roleModel = new RoleModel($this->pdo); 


        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        if (!userHasCapability('ACCESS_ADMIN_PANEL')) { 
            $_SESSION['error_message'] = "You do not have permission to access this area.";
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
        $this->view('admin/index', $data); 
    }

    // --- User Management Methods ---
    public function users() {
        if (!userHasCapability('MANAGE_USERS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to manage users.';
            redirect('admin'); 
        }
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
        if (!userHasCapability('MANAGE_USERS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to add users.';
            redirect('admin/users');
        }
        $departments = $this->departmentModel->getAllDepartments(); 
        $commonData = [
            'pageTitle' => 'Add New User',
            'departments' => $departments,
            'definedRoles' => getDefinedRoles(), 
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
            
            $allowedRoles = array_keys(getDefinedRoles()); 
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
        if (!userHasCapability('MANAGE_USERS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to edit users.';
            redirect('admin/users');
        }
        
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
            'definedRoles' => getDefinedRoles(), 
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
            
            $allowedRoles = array_keys(getDefinedRoles()); 
            if (!in_array($data['user_role'], $allowedRoles)) $data['errors']['user_role_err'] = 'Invalid user role selected.';
            if ($userId == 1 && $user['user_role'] === 'admin' && $data['user_role'] !== 'admin') {
                 $data['errors']['user_role_err'] = 'The primary super administrator role (ID 1) cannot be changed from "admin".';
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
        if (!userHasCapability('MANAGE_USERS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to delete users.';
            redirect('admin/users');
        }
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
    public function departments() {
        if (!userHasCapability('MANAGE_DEPARTMENTS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to manage departments.';
            redirect('admin');
        }
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
        if (!userHasCapability('MANAGE_DEPARTMENTS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to add departments.';
            redirect('admin/departments');
        }
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
        if (!userHasCapability('MANAGE_DEPARTMENTS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to edit departments.';
            redirect('admin/departments');
        }
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
        if (!userHasCapability('MANAGE_DEPARTMENTS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to delete departments.';
            redirect('admin/departments');
        }
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
        if (!userHasCapability('MANAGE_SITE_SETTINGS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to manage site settings.';
            redirect('admin');
        }
        
        $manageableOptions = [
            'site_name' => ['label' => 'Site Name', 'default' => 'My Awesome Site', 'type' => 'text'],
            'site_tagline' => ['label' => 'Site Tagline', 'default' => 'The best site ever', 'type' => 'text'],
            'admin_email' => ['label' => 'Administrator Email (General)', 'default' => 'admin@example.com', 'type' => 'email', 'help' => 'Primary contact for site administration.'], 
            'items_per_page' => ['label' => 'Items Per Page', 'default' => 10, 'type' => 'number', 'help' => 'Number of items to show on paginated lists.'],
            'site_description' => ['label' => 'Site Description', 'default' => '', 'type' => 'textarea'],
            'maintenance_mode' => [
                'label' => 'Maintenance Mode', 
                'default' => 'off', 
                'type' => 'select', 
                'options' => ['on' => 'On', 'off' => 'Off']
            ],
            'site_time_format' => [ 
                'label' => 'Site Time Format',
                'default' => DEFAULT_TIME_FORMAT, 
                'type' => 'select',
                'options' => [ 
                    'Y-m-d H:i:s' => date('Y-m-d H:i:s') . ' (YYYY-MM-DD HH:MM:SS - 24hr)', 
                    'Y-m-d H:i'   => date('Y-m-d H:i') . ' (YYYY-MM-DD HH:MM - 24hr)',   
                    'd/m/Y H:i'   => date('d/m/Y H:i') . ' (DD/MM/YYYY HH:MM - 24hr)',   
                    'm/d/Y h:i A' => date('m/d/Y h:i A') . ' (MM/DD/YYYY hh:mm AM/PM)',  
                    'F j, Y, g:i a' => date('F j, Y, g:i a') . ' (Month D, YYYY, h:mm am/pm)', 
                    'g:i a'         => date('g:i a') . ' (hh:mm am/pm - Time only)',          
                    'H:i'           => date('H:i') . ' (HH:MM - 24hr Time only)'             
                ],
                'help' => 'Select the default time format for displaying dates and times across the site.'
            ],
            // --- New Email Settings ---
            'site_email_notifications_enabled' => [
                'label' => 'Enable Email Notifications',
                'default' => DEFAULT_EMAIL_NOTIFICATIONS_ENABLED, // From config.php
                'type' => 'select',
                'options' => ['on' => 'On (Enabled)', 'off' => 'Off (Disabled)'],
                'help' => 'Master switch to enable or disable all system email notifications.'
            ],
            'site_email_from' => [
                'label' => 'System "From" Email Address',
                'default' => DEFAULT_SITE_EMAIL_FROM, // From config.php
                'type' => 'email',
                'help' => 'The email address system notifications will appear to be sent from.'
            ],
            'site_admin_email_notifications' => [
                'label' => 'Admin Notification Email',
                'default' => DEFAULT_ADMIN_EMAIL_NOTIFICATIONS, // From config.php
                'type' => 'email',
                'help' => 'Email address where notifications for administrators (e.g., new user registration, new reservation requests) are sent.'
            ]
        ];
        $optionKeys = array_keys($manageableOptions);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $settingsToSave = [];
            $errors = [];

            foreach ($optionKeys as $key) {
                if (isset($_POST[$key])) {
                    $value = trim($_POST[$key]);
                    // Basic validation for email fields
                    if (($key === 'site_email_from' || $key === 'site_admin_email_notifications' || $key === 'admin_email') && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$key . '_err'] = 'Please enter a valid email address for ' . $manageableOptions[$key]['label'] . '.';
                    } else {
                        $settingsToSave[$key] = $value;
                    }
                }
            }

            if (empty($errors)) {
                if ($this->optionModel->saveOptions($settingsToSave)) {
                    $_SESSION['admin_message'] = 'Site settings updated successfully!';
                } else {
                    $_SESSION['admin_message'] = 'Error: Could not save all site settings.';
                }
                redirect('admin/siteSettings');
            } else {
                // If errors, pass them to the view
                // Repopulate settings with POST data to show user's input
                $currentSettings = [];
                 foreach ($manageableOptions as $key => $details) {
                    $currentSettings[$key] = $_POST[$key] ?? ($this->optionModel->getOption($key, $details['default'] ?? ''));
                }
                $data = [
                    'pageTitle' => 'Site Settings',
                    'settings' => $currentSettings, // Use POSTed values for repopulation
                    'manageableOptions' => $manageableOptions, 
                    'errors' => $errors, // Pass errors to the view
                    'breadcrumbs' => [
                        ['label' => 'Admin Panel', 'url' => 'admin'],
                        ['label' => 'Site Settings']
                    ]
                ];
                $this->view('admin/site_settings', $data);
                return; // Stop further processing
            }
        }

        // For GET request
        $currentSettings = [];
        $dbOptions = $this->optionModel->getOptions($optionKeys);
        foreach ($manageableOptions as $key => $details) {
             $currentSettings[$key] = $dbOptions[$key] ?? ($details['default'] ?? '');
        }
        
        $data = [
            'pageTitle' => 'Site Settings',
            'settings' => $currentSettings,
            'manageableOptions' => $manageableOptions, 
            'errors' => [], // No errors on GET
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Site Settings']
            ]
        ];
        $this->view('admin/site_settings', $data); 
    }

    // --- Role Access Settings Method ---
    public function roleAccessSettings() {
        if (!userHasCapability('MANAGE_ROLES_PERMISSIONS')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to manage role permissions.';
            redirect('admin');
        }

        $definedRoles = getDefinedRoles(); 
        $allCapabilities = CAPABILITIES; 

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $submittedPermissions = $_POST['permissions'] ?? [];
            $success = true;

            foreach (array_keys($definedRoles) as $roleName) {
                if ($roleName === 'admin') { 
                    continue; 
                }
                $roleCapabilities = $submittedPermissions[$roleName] ?? [];
                $validRoleCapabilities = array_intersect($roleCapabilities, array_keys($allCapabilities));
                
                if (!$this->rolePermissionModel->setRoleCapabilities($roleName, $validRoleCapabilities)) {
                    $success = false;
                    $_SESSION['admin_message'] = "Error updating permissions for role: " . htmlspecialchars($roleName);
                    break; 
                }
            }

            if ($success && !isset($_SESSION['admin_message'])) {
                $_SESSION['admin_message'] = 'Role permissions updated successfully!';
            } elseif (!$success && !isset($_SESSION['admin_message'])) {
                 $_SESSION['admin_message'] = 'An unspecified error occurred while updating permissions.';
            }
            redirect('admin/roleAccessSettings'); 
        }

        $currentRoleCapabilities = [];
        foreach (array_keys($definedRoles) as $roleName) {
            $currentRoleCapabilities[$roleName] = $this->rolePermissionModel->getCapabilitiesForRole($roleName);
        }
        
        $data = [
            'pageTitle' => 'Role Access Settings',
            'definedRoles' => $definedRoles, 
            'allCapabilities' => $allCapabilities, 
            'currentRoleCapabilities' => $currentRoleCapabilities, 
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Role Access Settings']
            ]
        ];
        $this->view('admin/role_access_settings', $data); 
    }

    // --- Role Management Methods ---
    public function listRoles() {
        if (!userHasCapability('MANAGE_ROLES')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to manage roles.';
            redirect('admin');
        }
        $roles = $this->roleModel->getAllRoles();
        $data = [
            'pageTitle' => 'Manage Roles',
            'roles' => $roles,
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Roles']
            ]
        ];
        $this->view('admin/roles_list', $data); 
    }

    public function addRole() {
        if (!userHasCapability('MANAGE_ROLES')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to add roles.';
            redirect('admin/listRoles');
        }
        $commonData = [
            'pageTitle' => 'Add New Role',
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Roles', 'url' => 'admin/listRoles'],
                ['label' => 'Add Role']
            ]
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'role_key' => trim($_POST['role_key'] ?? ''),
                'role_name' => trim($_POST['role_name'] ?? ''),
                'role_description' => trim($_POST['role_description'] ?? ''),
                'is_system_role' => isset($_POST['is_system_role']) ? 1 : 0,
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);

            if (empty($data['role_key'])) $data['errors']['role_key_err'] = 'Role Key is required (e.g., new_role_key).';
            elseif (!preg_match('/^[a-z0-9_]+$/', $data['role_key'])) $data['errors']['role_key_err'] = 'Role Key can only contain lowercase letters, numbers, and underscores.';
            if (empty($data['role_name'])) $data['errors']['role_name_err'] = 'Role Name is required.';
            if ($this->roleModel->getRoleByKey($data['role_key'])) $data['errors']['role_key_err'] = 'This Role Key already exists.';


            if (empty($data['errors'])) {
                if ($this->roleModel->createRole($data['role_key'], $data['role_name'], $data['role_description'], $data['is_system_role'])) {
                    $_SESSION['admin_message'] = 'Role created successfully!';
                    redirect('admin/listRoles');
                } else {
                    $data['errors']['form_err'] = 'Could not create role. Key might already exist.';
                    $this->view('admin/role_form', $data); 
                }
            } else {
                $this->view('admin/role_form', $data);
            }
        } else {
            $data = array_merge($commonData, [
                'role_key' => '', 'role_name' => '', 'role_description' => '', 'is_system_role' => 0,
                'errors' => []
            ]);
            $this->view('admin/role_form', $data);
        }
    }

    public function editRole($roleId = null) {
        if (!userHasCapability('MANAGE_ROLES')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to edit roles.';
            redirect('admin/listRoles');
        }
        if ($roleId === null) redirect('admin/listRoles');
        $roleId = (int)$roleId;
        $role = $this->roleModel->getRoleById($roleId);

        if (!$role) {
            $_SESSION['admin_message'] = 'Role not found.';
            redirect('admin/listRoles');
        }

        $commonData = [
            'pageTitle' => 'Edit Role',
            'role' => $role, 
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Roles', 'url' => 'admin/listRoles'],
                ['label' => 'Edit Role: ' . htmlspecialchars($role['role_name'])]
            ]
        ];
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'role_id' => $roleId,
                'role_name' => trim($_POST['role_name'] ?? ''),
                'role_description' => trim($_POST['role_description'] ?? ''),
                'is_system_role' => ($role['role_key'] === 'admin') ? $role['is_system_role'] : (isset($_POST['is_system_role']) ? 1 : 0),
                'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            $data['role'] = array_merge($role, $formData); 

            if (empty($data['role_name'])) $data['errors']['role_name_err'] = 'Role Name is required.';
            
            if ($role['role_key'] === 'admin' && !$data['is_system_role']) {
                $data['errors']['is_system_role_err'] = 'The "admin" role must remain a system role.';
                $data['is_system_role'] = true; 
            }


            if (empty($data['errors'])) {
                if ($this->roleModel->updateRole($roleId, $data['role_name'], $data['role_description'], $data['is_system_role'])) {
                    $_SESSION['admin_message'] = 'Role updated successfully!';
                    redirect('admin/listRoles');
                } else {
                    $data['errors']['form_err'] = 'Could not update role.';
                    $this->view('admin/role_form', $data);
                }
            } else {
                $this->view('admin/role_form', $data);
            }
        } else {
            $data = array_merge($commonData, [
                'role_id' => $role['role_id'],
                'role_key' => $role['role_key'], 
                'role_name' => $role['role_name'],
                'role_description' => $role['role_description'],
                'is_system_role' => $role['is_system_role'],
                'errors' => []
            ]);
            $this->view('admin/role_form', $data);
        }
    }

    public function deleteRole($roleId = null) {
        if (!userHasCapability('MANAGE_ROLES')) {
            $_SESSION['admin_message'] = 'Error: You do not have permission to delete roles.';
            redirect('admin/listRoles');
        }
        if ($roleId === null) redirect('admin/listRoles');
        $roleId = (int)$roleId;
        $role = $this->roleModel->getRoleById($roleId);

        if (!$role) {
            $_SESSION['admin_message'] = 'Error: Role not found.';
        } elseif ($role['is_system_role']) {
            $_SESSION['admin_message'] = 'Error: System roles (like "'.htmlspecialchars($role['role_name']).'") cannot be deleted.';
        } elseif ($this->roleModel->deleteRole($roleId)) {
            $_SESSION['admin_message'] = 'Role "'.htmlspecialchars($role['role_name']).'" deleted successfully. Associated permissions removed, and users reassigned to default role if applicable.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete role "'.htmlspecialchars($role['role_name']).'".';
        }
        redirect('admin/listRoles');
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
