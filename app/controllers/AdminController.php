<?php

/**
 * AdminController
 *
 * Handles administrative tasks and pages.
 */
class AdminController {
    private $pdo;
    private $userModel; 
    private $departmentModel; // ADDED: DepartmentModel instance

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo); 
        $this->departmentModel = new DepartmentModel($this->pdo); // ADDED: Instantiate DepartmentModel

        // --- Admin Access Control ---
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
            'welcomeMessage' => 'Welcome to the Admin Panel, ' . htmlspecialchars($_SESSION['display_name'] ?? 'Admin') . '!'
        ];
        $this->view('admin/index', $data); // This view needs a link/card for Departments
    }

    // --- User Management Methods ---
    public function users() {
        $users = $this->userModel->getAllUsers();
        $data = [
            'pageTitle' => 'Manage Users',
            'users' => $users
        ];
        $this->view('admin/users', $data); 
    }

    public function addUser() {
        $departments = $this->departmentModel->getAllDepartments(); // Get departments for the form
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $data = [
                'user_login' => trim($_POST['user_login'] ?? ''),
                'user_email' => trim($_POST['user_email'] ?? ''),
                'display_name' => trim($_POST['display_name'] ?? ''),
                'user_pass' => trim($_POST['user_pass'] ?? ''),
                'confirm_pass' => trim($_POST['confirm_pass'] ?? ''),
                'user_role' => trim($_POST['user_role'] ?? 'user'), 
                'department_id' => isset($_POST['department_id']) && !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null, // ADDED department_id
                'user_status' => isset($_POST['user_status']) ? (int)$_POST['user_status'] : 0,
                'pageTitle' => 'Add New User',
                'departments' => $departments, // Pass departments to the view
                'errors' => []
            ];

            // Validation (add department_id validation if needed, e.g., ensure it exists)
            if (empty($data['user_login'])) $data['errors']['user_login_err'] = 'Username is required.';
            // ... (other user validations remain the same) ...
            $allowedRoles = ['admin', 'editor', 'user'];
            if (!in_array($data['user_role'], $allowedRoles)) {
                $data['errors']['user_role_err'] = 'Invalid user role selected.';
            }
            // Check if department_id is valid (exists in departments table)
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
                // UserModel's createUser will need to be updated to accept department_id
                $userId = $this->userModel->createUser(
                    $data['user_login'],
                    $data['user_email'],
                    $data['user_pass'],
                    $data['display_name'],
                    $data['user_role'], 
                    $data['user_login'], 
                    $data['user_status']
                    // We'll update UserModel to handle department_id separately or pass it here
                );

                if ($userId) {
                    // Now update the user with department_id if set
                    if ($data['department_id'] !== null) {
                        $this->userModel->updateUser($userId, ['department_id' => $data['department_id']]);
                    }
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
                'user_role' => 'user', 'department_id' => null, 'user_status' => 0,
                'departments' => $departments, // Pass departments to the view
                'errors' => []
            ];
            $this->view('admin/user_form', $data); 
        }
    }
    
    public function editUser($userId = null) {
        if ($userId === null) redirect('admin/users');
        $userId = (int)$userId;
        $user = $this->userModel->findUserById($userId); // UserModel->findUserById needs to fetch department_id
        $departments = $this->departmentModel->getAllDepartments();

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
                'user_role' => trim($_POST['user_role'] ?? $user['user_role']), 
                'department_id' => isset($_POST['department_id']) && !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null, // ADDED department_id
                'user_status' => isset($_POST['user_status']) ? (int)$_POST['user_status'] : (int)$user['user_status'],
                'pageTitle' => 'Edit User',
                'user' => $user, 
                'departments' => $departments,
                'errors' => []
            ];

            // Validation (add department_id validation if needed)
            // ... (other user validations remain the same) ...
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
                    'department_id' => $data['department_id'], // ADDED department_id
                    'user_status' => $data['user_status']
                ];
                if (!empty($data['user_pass'])) {
                    $updateData['user_pass'] = $data['user_pass']; 
                }

                if ($this->userModel->updateUser($userId, $updateData)) { // UserModel->updateUser needs to handle department_id
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
                'user_role' => $user['user_role'], 
                'department_id' => $user['department_id'] ?? null, // ADDED department_id
                'user_status' => (int)$user['user_status'],
                'user' => $user,
                'departments' => $departments,
                'errors' => []
            ];
            $this->view('admin/user_form', $data);
        }
    }

    public function deleteUser($userId = null) {
        // ... (deleteUser method remains largely the same for now) ...
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

    /**
     * List all departments.
     */
    public function departments() {
        $departments = $this->departmentModel->getAllDepartments();
        // Optionally, get user count for each department
        if ($departments) {
            foreach ($departments as &$dept) { // Use reference to modify array directly
                $dept['user_count'] = $this->departmentModel->getUserCountByDepartment($dept['department_id']);
            }
            unset($dept); // Unset reference
        }
        
        $data = [
            'pageTitle' => 'Manage Departments',
            'departments' => $departments
        ];
        $this->view('admin/departments', $data); // New view: admin/departments.php
    }

    /**
     * Display form to add a new department OR process adding a new department.
     */
    public function addDepartment() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $data = [
                'department_name' => trim($_POST['department_name'] ?? ''),
                'department_description' => trim($_POST['department_description'] ?? ''),
                'pageTitle' => 'Add New Department',
                'errors' => []
            ];

            if (empty($data['department_name'])) {
                $data['errors']['department_name_err'] = 'Department name is required.';
            }

            if (empty($data['errors'])) {
                if ($this->departmentModel->createDepartment($data['department_name'], $data['department_description'])) {
                    $_SESSION['admin_message'] = 'Department created successfully!';
                    redirect('admin/departments');
                } else {
                    $data['errors']['form_err'] = 'Could not create department. Name might already exist.';
                    $this->view('admin/department_form', $data); // New view: admin/department_form.php
                }
            } else {
                $this->view('admin/department_form', $data);
            }
        } else {
            $data = [
                'pageTitle' => 'Add New Department',
                'department_name' => '', 'department_description' => '',
                'errors' => []
            ];
            $this->view('admin/department_form', $data);
        }
    }

    /**
     * Display form to edit an existing department OR process updating an existing department.
     * @param int $departmentId The ID of the department to edit.
     */
    public function editDepartment($departmentId = null) {
        if ($departmentId === null) redirect('admin/departments');
        $departmentId = (int)$departmentId;
        $department = $this->departmentModel->getDepartmentById($departmentId);

        if (!$department) {
            $_SESSION['admin_message'] = 'Department not found.';
            redirect('admin/departments');
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $data = [
                'department_id' => $departmentId,
                'department_name' => trim($_POST['department_name'] ?? ''),
                'department_description' => trim($_POST['department_description'] ?? ''),
                'pageTitle' => 'Edit Department',
                'department' => $department,
                'errors' => []
            ];

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
            $data = [
                'pageTitle' => 'Edit Department',
                'department_id' => $department['department_id'],
                'department_name' => $department['department_name'],
                'department_description' => $department['department_description'],
                'department' => $department,
                'errors' => []
            ];
            $this->view('admin/department_form', $data);
        }
    }

    /**
     * Delete a department.
     * @param int $departmentId The ID of the department to delete.
     */
    public function deleteDepartment($departmentId = null) {
        if ($departmentId === null) redirect('admin/departments');
        $departmentId = (int)$departmentId;

        // Optional: Check if department is empty before deleting if ON DELETE RESTRICT was used
        // $userCount = $this->departmentModel->getUserCountByDepartment($departmentId);
        // if ($userCount > 0) {
        //     $_SESSION['admin_message'] = 'Error: Cannot delete department. It has users assigned to it.';
        //     redirect('admin/departments');
        // }

        if ($this->departmentModel->deleteDepartment($departmentId)) {
            $_SESSION['admin_message'] = 'Department deleted successfully. Users in this department are now unassigned.';
        } else {
            $_SESSION['admin_message'] = 'Error: Could not delete department.';
        }
        redirect('admin/departments');
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
