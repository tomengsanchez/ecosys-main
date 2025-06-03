<?php

/**
 * DepartmentModel
 *
 * Handles database operations related to departments.
 */
class DepartmentModel {
    private $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Create a new department.
     *
     * @param string $name The name of the department.
     * @param string|null $description The description of the department.
     * @return int|false The ID of the newly created department, or false on failure.
     */
    public function createDepartment($name, $description = null) {
        try {
            $sql = "INSERT INTO departments (department_name, department_description) 
                    VALUES (:name, :description)";
            $stmt = $this->pdo->prepare($sql);
            $params = [
                ':name' => $name,
                ':description' => $description
            ];
            if ($stmt->execute($params)) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            // Check for duplicate entry (error code 23000 for SQLSTATE, specific to unique key violation)
            if ($e->getCode() == 23000) {
                error_log("Error in DepartmentModel::createDepartment(): Duplicate department name. " . $e->getMessage());
            } else {
                error_log("Error in DepartmentModel::createDepartment(): " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Get a single department by its ID.
     *
     * @param int $departmentId The ID of the department.
     * @return array|false The department data as an associative array, or false if not found.
     */
    public function getDepartmentById($departmentId) {
        try {
            $sql = "SELECT * FROM departments WHERE department_id = :department_id LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in DepartmentModel::getDepartmentById(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all departments from the database.
     *
     * @param string $orderBy Column to order by (default: 'department_name').
     * @param string $orderDirection Sort direction (default: 'ASC').
     * @return array|false An array of department objects (associative arrays) or false on failure.
     */
    public function getAllDepartments($orderBy = 'department_name', $orderDirection = 'ASC') {
        // Validate orderBy to prevent SQL injection if it were dynamic from user input
        // For now, we'll assume it's hardcoded or from a safe source.
        $allowedOrderBy = ['department_id', 'department_name', 'created_at'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'department_name'; // Default to a safe column
        }
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        try {
            $sql = "SELECT * FROM departments ORDER BY {$orderBy} {$orderDirection}";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in DepartmentModel::getAllDepartments(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing department's details.
     *
     * @param int $departmentId The ID of the department to update.
     * @param string $name The new name of the department.
     * @param string|null $description The new description of the department.
     * @return bool True on success, false on failure.
     */
    public function updateDepartment($departmentId, $name, $description = null) {
        try {
            $sql = "UPDATE departments 
                    SET department_name = :name, department_description = :description 
                    WHERE department_id = :department_id";
            $stmt = $this->pdo->prepare($sql);
            $params = [
                ':department_id' => $departmentId,
                ':name' => $name,
                ':description' => $description
            ];
            return $stmt->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                error_log("Error in DepartmentModel::updateDepartment(): Duplicate department name. " . $e->getMessage());
            } else {
                error_log("Error in DepartmentModel::updateDepartment(): " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Delete a department from the database.
     *
     * @param int $departmentId The ID of the department to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteDepartment($departmentId) {
        try {
            // The foreign key constraint `ON DELETE SET NULL` on users.department_id
            // will handle setting users' department_id to NULL automatically.
            $sql = "DELETE FROM departments WHERE department_id = :department_id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':department_id' => $departmentId]);
        } catch (PDOException $e) {
            // If ON DELETE RESTRICT was used, a 23000 error (Integrity constraint violation)
            // might occur if users are still assigned to this department.
            error_log("Error in DepartmentModel::deleteDepartment(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the count of users in a specific department.
     *
     * @param int $departmentId The ID of the department.
     * @return int The number of users in the department.
     */
    public function getUserCountByDepartment($departmentId) {
        try {
            $sql = "SELECT COUNT(*) as user_count FROM users WHERE department_id = :department_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':department_id' => $departmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['user_count'] : 0;
        } catch (PDOException $e) {
            error_log("Error in DepartmentModel::getUserCountByDepartment(): " . $e->getMessage());
            return 0;
        }
    }
}
