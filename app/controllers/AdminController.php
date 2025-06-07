<?php

/**
 * AdminController
 *
 * Handles administrative tasks and pages.
 */
class AdminController extends BaseController {
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
            $this->setFlashMessage('error', "You do not have permission to access the admin panel.");
            redirect('Dashboard'); 
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
            $this->setFlashMessage('error', 'You do not have permission to manage users.');
            redirect('admin'); 
        }
        $data = [
            'pageTitle' => 'Manage Users',
            'breadcrumbs' => [ 
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Users']
            ]
        ];
        $this->view('admin/users', $data); 
    }

    public function ajaxGetUsers() {
        header('Content-Type: application/json');
        if (!isLoggedIn() || !userHasCapability('MANAGE_USERS')) {
            echo json_encode(["draw" => intval($_POST['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not authorized"]);
            return;
        }

        $draw = intval($_POST['draw'] ?? 0);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $searchValue = $_POST['search']['value'] ?? '';

        $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
        $orderColumnName = $_POST['columns'][$orderColumnIndex]['data'] ?? 'user_id';
        $orderDir = $_POST['order'][0]['dir'] ?? 'asc';

        // Mapping for UserModel's getAllUsers method (adjust if UserModel changes)
        $columnMapping = [
            'user_id' => 'u.user_id',
            'user_login' => 'u.user_login',
            'user_email' => 'u.user_email',
            'display_name' => 'u.display_name',
            'user_role_display' => 'u.user_role',
            'department_name' => 'd.department_name',
            'user_registered_formatted' => 'u.user_registered',
        ];
        $dbOrderColumn = $columnMapping[$orderColumnName] ?? 'u.user_id';

        $usersData = $this->userModel->getAllUsers($searchValue, $dbOrderColumn, $orderDir, $start, $length);
        $users = $usersData['data'];
        $totalRecords = $usersData['totalRecords'];
        $totalFilteredRecords = $usersData['totalFilteredRecords'];

        $dataOutput = [];

        if ($users) {
            foreach ($users as $user) {
                $statusHtml = ($user['user_status'] == 0) ? 
                              '<span class="badge bg-success">Active</span>' : 
                              '<span class="badge bg-warning text-dark">Inactive/Banned</span>';

                $actionsHtml = '<a href="' . BASE_URL . 'admin/editUser/' . htmlspecialchars($user['user_id']) . '" class="btn btn-sm btn-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                if ($user['user_id'] != $_SESSION['user_id'] && !($user['user_id'] == 1 && $user['user_role'] === 'admin')) {
                    $actionsHtml .= ' <a href="' . BASE_URL . 'admin/deleteUser/' . htmlspecialchars($user['user_id']) . '" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm(\'Are you sure you want to delete this user? This action cannot be undone.\');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>';
                } else {
                     $actionsHtml .= ' <button class="btn btn-sm btn-danger" title="Cannot delete self or primary admin" disabled><i class="fas fa-trash-alt"></i></button>';
                }

                $dataOutput[] = [
                    "user_id" => htmlspecialchars($user['user_id']),
                    "user_login" => htmlspecialchars($user['user_login']),
                    "user_email" => htmlspecialchars($user['user_email']),
                    "display_name" => htmlspecialchars($user['display_name']),
                    "user_role_display" => htmlspecialchars(ucfirst($user['user_role'])),
                    "department" => htmlspecialchars($user['department_name'] ?? 'N/A'),
                    "user_registered_formatted" => htmlspecialchars(format_datetime_for_display($user['user_registered'])),
                    "status_html" => $statusHtml,
                    "actions_html" => $actionsHtml
                ];
            }
        }

        $output = [
            "draw"            => $draw,
            "recordsTotal"    => $totalRecords,
            "recordsFiltered" => $totalFilteredRecords,
            "data"            => $dataOutput
        ];
        echo json_encode($output);
    }


    public function addUser() {
        if (!userHasCapability('MANAGE_USERS')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to add users.');
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
                    $data['user_login'], $data['user_email'], $data['user_pass'],
                    $data['display_name'], $data['user_role'], $data['department_id'], 
                    $data['user_login'], $data['user_status']
                );

                if ($userId) {
                    $this->setFlashMessage('success', 'User created successfully!');
                    redirect('admin/users');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not create user.';
                    $this->view('admin/user_form', $data);
                }
            } else { $this->view('admin/user_form', $data); }
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
            $this->setFlashMessage('error', 'Error: You do not have permission to edit users.');
            redirect('admin/users');
        }
        if ($userId === null) redirect('admin/users');
        $userId = (int)$userId;
        $user = $this->userModel->findUserById($userId); 
        $departments = $this->departmentModel->getAllDepartments();
        if (!$user) {
            $this->setFlashMessage('error', 'User not found.');
            redirect('admin/users');
        }
        $commonData = [
            'pageTitle' => 'Edit User', 'departments' => $departments, 'user' => $user, 
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
                'user_id' => $userId, 'user_login' => trim($_POST['user_login'] ?? ''),
                'user_email' => trim($_POST['user_email'] ?? ''), 'display_name' => trim($_POST['display_name'] ?? ''),
                'user_pass' => trim($_POST['user_pass'] ?? ''), 'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
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
                    'user_login' => $data['user_login'], 'user_email' => $data['user_email'],
                    'display_name' => $data['display_name'], 'user_role' => $data['user_role'], 
                    'department_id' => $data['department_id'], 'user_status' => $data['user_status']
                ];
                if (!empty($data['user_pass'])) { $updateData['user_pass'] = $data['user_pass']; }
                if ($this->userModel->updateUser($userId, $updateData)) { 
                    $this->setFlashMessage('success', 'User updated successfully!');
                    redirect('admin/users');
                } else {
                    $data['errors']['form_err'] = 'Something went wrong. Could not update user.';
                    $this->view('admin/user_form', $data);
                }
            } else { $this->view('admin/user_form', $data); }
        } else {
             $data = array_merge($commonData, [
                'user_id' => $user['user_id'], 'user_login' => $user['user_login'],
                'user_email' => $user['user_email'], 'display_name' => $user['display_name'],
                'user_role' => $user['user_role'], 'department_id' => $user['department_id'] ?? null, 
                'user_status' => (int)$user['user_status'], 'errors' => []
            ]);
            $this->view('admin/user_form', $data);
        }
    }

    public function deleteUser($userId = null) {
        if (!userHasCapability('MANAGE_USERS')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to delete users.');
            redirect('admin/users');
        }
        if ($userId === null) redirect('admin/users');
        $userId = (int)$userId;
        $userToDelete = $this->userModel->findUserById($userId);
        if (!$userToDelete) {
            $this->setFlashMessage('error', 'Error: User not found.');
        } elseif ($userId == $_SESSION['user_id']) {
            $this->setFlashMessage('error', 'Error: You cannot delete your own account.');
        } elseif ($userToDelete['user_role'] === 'admin' && $userId == 1) {
            $this->setFlashMessage('error', 'Error: The primary super administrator account (ID 1) cannot be deleted.');
        } elseif ($this->userModel->deleteUser($userId)) {
            $this->setFlashMessage('success', 'User deleted successfully.');
        } else {
            $this->setFlashMessage('error', 'Error: Could not delete user.');
        }
        redirect('admin/users');
    }


    // --- Department Management Methods ---
    public function departments() {
        if (!userHasCapability('MANAGE_DEPARTMENTS')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to manage departments.');
            redirect('admin');
        }
        $data = [
            'pageTitle' => 'Manage Departments',
            'breadcrumbs' => [ 
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Departments']
            ]
        ];
        $this->view('admin/departments', $data); 
    }

    public function ajaxGetDepartments() {
        header('Content-Type: application/json');
        if (!isLoggedIn() || !userHasCapability('MANAGE_DEPARTMENTS')) {
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not authorized"]);
            return;
        }

        $departments = $this->departmentModel->getAllDepartments();
        $data = [];

        if ($departments) {
            foreach ($departments as $department) {
                $userCount = $this->departmentModel->getUserCountByDepartment($department['department_id']);
                
                $actionsHtml = '<a href="' . BASE_URL . 'admin/editDepartment/' . htmlspecialchars($department['department_id']) . '" class="btn btn-sm btn-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                $actionsHtml .= ' <a href="' . BASE_URL . 'admin/deleteDepartment/' . htmlspecialchars($department['department_id']) . '" 
                                   class="btn btn-sm btn-danger" title="Delete"
                                   onclick="return confirm(\'Are you sure you want to delete the department &quot;' . htmlspecialchars(addslashes($department['department_name'])) . '&quot;? Users in this department will be unassigned.\');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>';
                
                $data[] = [
                    "id" => htmlspecialchars($department['department_id']),
                    "name" => htmlspecialchars($department['department_name']),
                    "description" => nl2br(htmlspecialchars($department['department_description'] ?? 'N/A')),
                    "user_count" => htmlspecialchars($userCount),
                    "created_at" => htmlspecialchars(format_datetime_for_display($department['created_at'])),
                    "actions" => $actionsHtml
                ];
            }
        }
        $output = [
            "draw"            => intval($_GET['draw'] ?? 0),
            "recordsTotal"    => count($data),
            "recordsFiltered" => count($data),
            "data"            => $data
        ];
        echo json_encode($output);
    }

    public function addDepartment() {
        if (!userHasCapability('MANAGE_DEPARTMENTS')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to add departments.');
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
            if (empty($data['department_name'])) $data['errors']['department_name_err'] = 'Department name is required.';
            if (empty($data['errors'])) {
                if ($this->departmentModel->createDepartment($data['department_name'], $data['department_description'])) {
                    $this->setFlashMessage('success', 'Department created successfully!');
                    redirect('admin/departments');
                } else {
                    $data['errors']['form_err'] = 'Could not create department. Name might already exist.';
                    $this->view('admin/department_form', $data); 
                }
            } else { $this->view('admin/department_form', $data); }
        } else {
            $data = array_merge($commonData, [
                'department_name' => '', 'department_description' => '', 'errors' => []
            ]);
            $this->view('admin/department_form', $data);
        }
    }

    public function editDepartment($departmentId = null) {
        if (!userHasCapability('MANAGE_DEPARTMENTS')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to edit departments.');
            redirect('admin/departments');
        }
        if ($departmentId === null) redirect('admin/departments');
        $departmentId = (int)$departmentId;
        $department = $this->departmentModel->getDepartmentById($departmentId);
        if (!$department) {
            $this->setFlashMessage('error', 'Department not found.');
            redirect('admin/departments');
        }
        $commonData = [
            'pageTitle' => 'Edit Department', 'department' => $department,
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
            if (empty($data['department_name'])) $data['errors']['department_name_err'] = 'Department name is required.';
            if (empty($data['errors'])) {
                if ($this->departmentModel->updateDepartment($departmentId, $data['department_name'], $data['department_description'])) {
                    $this->setFlashMessage('success', 'Department updated successfully!');
                    redirect('admin/departments');
                } else {
                    $data['errors']['form_err'] = 'Could not update department. Name might already exist.';
                    $this->view('admin/department_form', $data);
                }
            } else { $this->view('admin/department_form', $data); }
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
            $this->setFlashMessage('error', 'Error: You do not have permission to delete departments.');
            redirect('admin/departments');
        }
        if ($departmentId === null) redirect('admin/departments');
        $departmentId = (int)$departmentId;
        if ($this->departmentModel->deleteDepartment($departmentId)) {
            $this->setFlashMessage('success', 'Department deleted successfully. Users in this department are now unassigned.');
        } else {
            $this->setFlashMessage('error', 'Error: Could not delete department.');
        }
        redirect('admin/departments');
    }

    // --- Site Settings Method ---
    public function siteSettings() {
        if (!userHasCapability('MANAGE_SITE_SETTINGS')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to manage site settings.');
            redirect('admin');
        }
        $manageableOptions = [
            'site_name' => ['label' => 'Site Name', 'default' => 'My Awesome Site', 'type' => 'text'],
            'site_tagline' => ['label' => 'Site Tagline', 'default' => 'The best site ever', 'type' => 'text'],
            'admin_email' => ['label' => 'Administrator Email (General)', 'default' => 'admin@example.com', 'type' => 'email', 'help' => 'Primary contact for site administration.'], 
            'items_per_page' => ['label' => 'Items Per Page', 'default' => 10, 'type' => 'number', 'help' => 'Number of items to show on paginated lists.'],
            'site_description' => ['label' => 'Site Description', 'default' => '', 'type' => 'textarea'],
            'maintenance_mode' => ['label' => 'Maintenance Mode', 'default' => 'off', 'type' => 'select', 'options' => ['on' => 'On', 'off' => 'Off']],
            'site_time_format' => [ 
                'label' => 'Site Time Format', 'default' => DEFAULT_TIME_FORMAT, 'type' => 'select',
                'options' => [ 
                    'Y-m-d H:i:s' => date('Y-m-d H:i:s') . ' (YYYY-MM-DD HH:MM:SS - 24hr)', 
                    'Y-m-d H:i'   => date('Y-m-d H:i') . ' (YYYY-MM-DD HH:MM - 24hr)',   
                    'd/m/Y H:i'   => date('d/m/Y H:i') . ' (DD/MM/YYYY HH:MM - 24hr)',   
                    'm/d/Y h:i A' => date('m/d/Y h:i A') . ' (MM/DD/YYYY hh:mm AM/PM)',  
                    'F j, Y, g:i a' => date('F j, Y, g:i a') . ' (Month D, YYYY, h:mm am/pm)', 
                    'g:i a' => date('g:i a') . ' (hh:mm am/pm - Time only)', 'H:i' => date('H:i') . ' (HH:MM - 24hr Time only)'             
                ], 'help' => 'Select the default time format for displaying dates and times across the site.'
            ],
            'site_email_notifications_enabled' => ['label' => 'Enable Email Notifications', 'default' => DEFAULT_EMAIL_NOTIFICATIONS_ENABLED, 'type' => 'select', 'options' => ['on' => 'On (Enabled)', 'off' => 'Off (Disabled)'], 'help' => 'Master switch to enable or disable all system email notifications.'],
            'site_email_from' => ['label' => 'System "From" Email Address', 'default' => DEFAULT_SITE_EMAIL_FROM, 'type' => 'email', 'help' => 'The email address system notifications will appear to be sent from.'],
            'site_admin_email_notifications' => ['label' => 'Admin Notification Email', 'default' => DEFAULT_ADMIN_EMAIL_NOTIFICATIONS, 'type' => 'email', 'help' => 'Email address where notifications for administrators are sent.']
        ];
        $optionKeys = array_keys($manageableOptions);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $settingsToSave = []; $errors = [];
            foreach ($optionKeys as $key) {
                if (isset($_POST[$key])) {
                    $value = trim($_POST[$key]);
                    if (($key === 'site_email_from' || $key === 'site_admin_email_notifications' || $key === 'admin_email') && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$key . '_err'] = 'Please enter a valid email address for ' . $manageableOptions[$key]['label'] . '.';
                    } else { $settingsToSave[$key] = $value; }
                }
            }
            if (empty($errors)) {
                if ($this->optionModel->saveOptions($settingsToSave)) {
                    $this->setFlashMessage('success', 'Site settings updated successfully!');
                } else {
                    $this->setFlashMessage('error', 'Error: Could not save all site settings.');
                }
                redirect('admin/siteSettings');
            } else {
                $currentSettings = [];
                 foreach ($manageableOptions as $key => $details) { $currentSettings[$key] = $_POST[$key] ?? ($this->optionModel->getOption($key, $details['default'] ?? '')); }
                $data = [
                    'pageTitle' => 'Site Settings', 'settings' => $currentSettings, 
                    'manageableOptions' => $manageableOptions, 'errors' => $errors, 
                    'breadcrumbs' => [['label' => 'Admin Panel', 'url' => 'admin'], ['label' => 'Site Settings']]
                ];
                $this->view('admin/site_settings', $data); return; 
            }
        }
        $currentSettings = [];
        $dbOptions = $this->optionModel->getOptions($optionKeys);
        foreach ($manageableOptions as $key => $details) { $currentSettings[$key] = $dbOptions[$key] ?? ($details['default'] ?? ''); }
        $data = [
            'pageTitle' => 'Site Settings', 'settings' => $currentSettings,
            'manageableOptions' => $manageableOptions, 'errors' => [], 
            'breadcrumbs' => [['label' => 'Admin Panel', 'url' => 'admin'], ['label' => 'Site Settings']]
        ];
        $this->view('admin/site_settings', $data); 
    }

    // --- Role Access Settings Method ---
    public function roleAccessSettings() {
        if (!userHasCapability('MANAGE_ROLES_PERMISSIONS')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to manage role permissions.');
            redirect('admin');
        }
        $definedRoles = getDefinedRoles(); $allCapabilities = CAPABILITIES; 
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $submittedPermissions = $_POST['permissions'] ?? []; $success = true;
            $flashMessageSet = false;
            foreach (array_keys($definedRoles) as $roleName) {
                $roleCapabilities = $submittedPermissions[$roleName] ?? [];
                $validRoleCapabilities = array_intersect($roleCapabilities, array_keys($allCapabilities));
                if (!$this->rolePermissionModel->setRoleCapabilities($roleName, $validRoleCapabilities)) {
                    $success = false;
                    $this->setFlashMessage('error', "Error updating permissions for role: " . htmlspecialchars($roleName));
                    $flashMessageSet = true;
                    break; 
                }
            }
            if (!$flashMessageSet) {
                if ($success) {
                    $this->setFlashMessage('success', 'Role permissions updated successfully!');
                } else { // This case might not be hit if the loop breaks and sets a message
                    $this->setFlashMessage('error', 'An unspecified error occurred while updating permissions.');}
            }
            redirect('admin/roleAccessSettings'); 
        }
        $currentRoleCapabilities = [];
        foreach (array_keys($definedRoles) as $roleName) { $currentRoleCapabilities[$roleName] = $this->rolePermissionModel->getCapabilitiesForRole($roleName); }
        $data = [
            'pageTitle' => 'Role Access Settings', 'definedRoles' => $definedRoles, 
            'allCapabilities' => $allCapabilities, 'currentRoleCapabilities' => $currentRoleCapabilities, 
            'breadcrumbs' => [['label' => 'Admin Panel', 'url' => 'admin'], ['label' => 'Role Access Settings']]
        ];
        $this->view('admin/role_access_settings', $data); 
    }

    // --- Role Management Methods ---
    public function listRoles() {
        if (!userHasCapability('MANAGE_ROLES')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to manage roles.');
            redirect('admin');
        }
        // Data will be fetched by DataTables via AJAX
        $data = [
            'pageTitle' => 'Manage Roles',
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Roles']
            ]
        ];
        $this->view('admin/roles_list', $data); 
    }

    public function ajaxGetRoles() {
        header('Content-Type: application/json');
        if (!isLoggedIn() || !userHasCapability('MANAGE_ROLES')) {
            echo json_encode(["draw" => intval($_GET['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Not authorized"]);
            return;
        }

        $roles = $this->roleModel->getAllRoles();
        $data = [];

        if ($roles) {
            foreach ($roles as $role) {
                $systemRoleHtml = $role['is_system_role'] ? 
                                  '<span class="badge bg-info text-dark">Yes</span>' : 
                                  '<span class="badge bg-secondary">No</span>';
                
                $actionsHtml = '<a href="' . BASE_URL . 'admin/editRole/' . htmlspecialchars($role['role_id']) . '" class="btn btn-sm btn-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                if (!$role['is_system_role']) {
                    $actionsHtml .= ' <a href="' . BASE_URL . 'admin/deleteRole/' . htmlspecialchars($role['role_id']) . '" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm(\'Are you sure you want to delete the role &quot;' . htmlspecialchars(addslashes($role['role_name'])) . '&quot;? This will also remove its permissions and reassign users to the default role.\');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>';
                } else {
                    $actionsHtml .= ' <button class="btn btn-sm btn-danger" disabled title="System roles cannot be deleted"><i class="fas fa-trash-alt"></i></button>';
                }

                $data[] = [
                    "id" => htmlspecialchars($role['role_id']),
                    "key" => '<code>' . htmlspecialchars($role['role_key']) . '</code>',
                    "name" => htmlspecialchars($role['role_name']),
                    "description" => nl2br(htmlspecialchars($role['role_description'] ?? 'N/A')),
                    "is_system" => $systemRoleHtml,
                    "created_at" => htmlspecialchars(format_datetime_for_display($role['created_at'])),
                    "actions" => $actionsHtml
                ];
            }
        }
        $output = [
            "draw"            => intval($_GET['draw'] ?? 0),
            "recordsTotal"    => count($data),
            "recordsFiltered" => count($data),
            "data"            => $data
        ];
        echo json_encode($output);
    }


    public function addRole() {
        if (!userHasCapability('MANAGE_ROLES')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to add roles.');
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
                'role_key' => trim($_POST['role_key'] ?? ''), 'role_name' => trim($_POST['role_name'] ?? ''),
                'role_description' => trim($_POST['role_description'] ?? ''),
                'is_system_role' => isset($_POST['is_system_role']) ? 1 : 0, 'errors' => []
            ];
            $data = array_merge($commonData, $formData);
            if (empty($data['role_key'])) $data['errors']['role_key_err'] = 'Role Key is required (e.g., new_role_key).';
            elseif (!preg_match('/^[a-z0-9_]+$/', $data['role_key'])) $data['errors']['role_key_err'] = 'Role Key can only contain lowercase letters, numbers, and underscores.';
            if (empty($data['role_name'])) $data['errors']['role_name_err'] = 'Role Name is required.';
            if ($this->roleModel->getRoleByKey($data['role_key'])) $data['errors']['role_key_err'] = 'This Role Key already exists.';
            if (empty($data['errors'])) {
                if ($this->roleModel->createRole($data['role_key'], $data['role_name'], $data['role_description'], $data['is_system_role'])) {
                    $this->setFlashMessage('success', 'Role created successfully!');
                    redirect('admin/listRoles');
                } else {
                    $data['errors']['form_err'] = 'Could not create role. Key might already exist.';
                    $this->view('admin/role_form', $data); 
                }
            } else { $this->view('admin/role_form', $data); }
        } else {
            $data = array_merge($commonData, [
                'role_key' => '', 'role_name' => '', 'role_description' => '', 'is_system_role' => 0, 'errors' => []
            ]);
            $this->view('admin/role_form', $data);
        }
    }

    public function editRole($roleId = null) {
        if (!userHasCapability('MANAGE_ROLES')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to edit roles.');
            redirect('admin/listRoles');
        }
        if ($roleId === null) redirect('admin/listRoles');
        $roleId = (int)$roleId;
        $role = $this->roleModel->getRoleById($roleId);
        if (!$role) {
            $this->setFlashMessage('error', 'Role not found.');
            redirect('admin/listRoles');
        }
        $commonData = [
            'pageTitle' => 'Edit Role', 'role' => $role, 
            'breadcrumbs' => [
                ['label' => 'Admin Panel', 'url' => 'admin'],
                ['label' => 'Manage Roles', 'url' => 'admin/listRoles'],
                ['label' => 'Edit Role: ' . htmlspecialchars($role['role_name'])]
            ]
        ];
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formData = [
                'role_id' => $roleId, 'role_name' => trim($_POST['role_name'] ?? ''),
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
                    $this->setFlashMessage('success', 'Role updated successfully!');
                    redirect('admin/listRoles');
                } else {
                    $data['errors']['form_err'] = 'Could not update role.';
                    $this->view('admin/role_form', $data);
                }
            } else { $this->view('admin/role_form', $data); }
        } else {
            $data = array_merge($commonData, [
                'role_id' => $role['role_id'], 'role_key' => $role['role_key'], 
                'role_name' => $role['role_name'], 'role_description' => $role['role_description'],
                'is_system_role' => $role['is_system_role'], 'errors' => []
            ]);
            $this->view('admin/role_form', $data);
        }
    }

    public function deleteRole($roleId = null) {
        if (!userHasCapability('MANAGE_ROLES')) {
            $this->setFlashMessage('error', 'Error: You do not have permission to delete roles.');
            redirect('admin/listRoles');
        }
        if ($roleId === null) redirect('admin/listRoles');
        $roleId = (int)$roleId;
        $role = $this->roleModel->getRoleById($roleId);
        if (!$role) {
            $this->setFlashMessage('error', 'Error: Role not found.');
        } elseif ($role['is_system_role']) {
            $this->setFlashMessage('error', 'Error: System roles (like "'.htmlspecialchars($role['role_name']).'") cannot be deleted.');
        } elseif ($this->roleModel->deleteRole($roleId)) {
            $this->setFlashMessage('success', 'Role "'.htmlspecialchars($role['role_name']).'" deleted successfully.');
        } else {
            $this->setFlashMessage('error', 'Error: Could not delete role "'.htmlspecialchars($role['role_name']).'".');
        }
        redirect('admin/listRoles');
    }

}
