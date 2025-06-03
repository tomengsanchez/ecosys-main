<?php

/**
 * RoleModel
 *
 * Handles database operations related to the 'roles' table.
 */
class RoleModel {
    private $pdo;

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Create a new role.
     *
     * @param string $roleKey The unique key for the role (e.g., 'new_role').
     * @param string $roleName The display name for the role.
     * @param string|null $roleDescription Optional description for the role.
     * @param bool $isSystemRole Whether this is a system role (cannot be deleted by UI).
     * @return int|false The ID of the newly created role, or false on failure.
     */
    public function createRole($roleKey, $roleName, $roleDescription = null, $isSystemRole = false) {
        try {
            $sql = "INSERT INTO roles (role_key, role_name, role_description, is_system_role) 
                    VALUES (:role_key, :role_name, :role_description, :is_system_role)";
            $stmt = $this->pdo->prepare($sql);
            $params = [
                ':role_key' => $roleKey,
                ':role_name' => $roleName,
                ':role_description' => $roleDescription,
                ':is_system_role' => (int)$isSystemRole // Store boolean as 0 or 1
            ];
            if ($stmt->execute($params)) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            // Error code 23000 is for integrity constraint violation (e.g., duplicate role_key)
            if ($e->getCode() == 23000) {
                error_log("Error in RoleModel::createRole(): Duplicate role key '{$roleKey}'. " . $e->getMessage());
            } else {
                error_log("Error in RoleModel::createRole(): " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Get a single role by its ID.
     *
     * @param int $roleId The ID of the role.
     * @return array|false The role data as an associative array, or false if not found.
     */
    public function getRoleById($roleId) {
        try {
            $sql = "SELECT * FROM roles WHERE role_id = :role_id LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':role_id' => $roleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($role) {
                $role['is_system_role'] = (bool)$role['is_system_role']; // Cast to boolean
            }
            return $role;
        } catch (PDOException $e) {
            error_log("Error in RoleModel::getRoleById({$roleId}): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a single role by its key.
     *
     * @param string $roleKey The unique key of the role.
     * @return array|false The role data as an associative array, or false if not found.
     */
    public function getRoleByKey($roleKey) {
        try {
            $sql = "SELECT * FROM roles WHERE role_key = :role_key LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':role_key' => $roleKey]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($role) {
                $role['is_system_role'] = (bool)$role['is_system_role']; // Cast to boolean
            }
            return $role;
        } catch (PDOException $e) {
            error_log("Error in RoleModel::getRoleByKey({$roleKey}): " . $e->getMessage());
            return false;
        }
    }


    /**
     * Get all roles from the database.
     *
     * @param string $orderBy Column to order by (default: 'role_name').
     * @param string $orderDirection Sort direction (default: 'ASC').
     * @return array|false An array of role objects (associative arrays) or false on failure.
     */
    public function getAllRoles($orderBy = 'role_name', $orderDirection = 'ASC') {
        $allowedOrderBy = ['role_id', 'role_key', 'role_name', 'created_at'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'role_name';
        }
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        try {
            $sql = "SELECT * FROM roles ORDER BY {$orderBy} {$orderDirection}";
            $stmt = $this->pdo->query($sql);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($roles as &$role) {
                $role['is_system_role'] = (bool)$role['is_system_role']; // Cast to boolean
            }
            return $roles;
        } catch (PDOException $e) {
            error_log("Error in RoleModel::getAllRoles(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing role's details.
     * The role_key cannot be updated.
     *
     * @param int $roleId The ID of the role to update.
     * @param string $roleName The new display name for the role.
     * @param string|null $roleDescription The new description for the role.
     * @param bool|null $isSystemRole (Optional) Update the system role flag. Be cautious with this.
     * @return bool True on success, false on failure.
     */
    public function updateRole($roleId, $roleName, $roleDescription = null, $isSystemRole = null) {
        // Prevent changing 'admin' role from being a system role if it's role_id 1 or key 'admin'
        $currentRole = $this->getRoleById($roleId);
        if (!$currentRole) return false;

        if ($currentRole['role_key'] === 'admin' && $isSystemRole === false) {
            error_log("Attempt to change 'admin' role from being a system role prevented.");
            // Optionally set an error message or just force it back
            $isSystemRole = true; 
        }


        try {
            $sqlParts = [];
            $params = [':role_id' => $roleId];

            if ($roleName !== null) {
                $sqlParts[] = "role_name = :role_name";
                $params[':role_name'] = $roleName;
            }
            if ($roleDescription !== null) { // Allow setting description to empty string
                $sqlParts[] = "role_description = :role_description";
                $params[':role_description'] = $roleDescription;
            }
            if ($isSystemRole !== null) {
                $sqlParts[] = "is_system_role = :is_system_role";
                $params[':is_system_role'] = (int)$isSystemRole;
            }
            
            if (empty($sqlParts)) { // Nothing to update
                return true;
            }

            $sql = "UPDATE roles SET " . implode(', ', $sqlParts) . " WHERE role_id = :role_id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error in RoleModel::updateRole({$roleId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a role from the database.
     * Prevents deletion of system roles.
     * Also, ensure associated permissions in 'role_permissions' are handled (e.g., deleted).
     * And consider what happens to users assigned to this role.
     *
     * @param int $roleId The ID of the role to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteRole($roleId) {
        $role = $this->getRoleById($roleId);
        if (!$role) {
            error_log("Attempt to delete non-existent role ID: {$roleId}");
            return false; // Role not found
        }
        if ($role['is_system_role']) {
            error_log("Attempt to delete system role '{$role['role_key']}' prevented.");
            return false; // Prevent deletion of system roles
        }

        try {
            $this->pdo->beginTransaction();

            // Step 1: Delete associated permissions from role_permissions
            $sqlPerms = "DELETE FROM role_permissions WHERE role_name = :role_key";
            $stmtPerms = $this->pdo->prepare($sqlPerms);
            $stmtPerms->execute([':role_key' => $role['role_key']]);

            // Step 2: Update users assigned to this role to a default role (e.g., 'user')
            // Or prevent deletion if users are assigned, or set their role to NULL if allowed by DB schema.
            // For simplicity, let's reassign them to the 'user' role if it exists.
            $defaultRoleKey = 'user'; 
            $defaultRole = $this->getRoleByKey($defaultRoleKey);
            if ($defaultRole) {
                 $sqlUpdateUsers = "UPDATE users SET user_role = :default_role_key WHERE user_role = :deleted_role_key";
                 $stmtUpdateUsers = $this->pdo->prepare($sqlUpdateUsers);
                 $stmtUpdateUsers->execute([
                     ':default_role_key' => $defaultRoleKey,
                     ':deleted_role_key' => $role['role_key']
                 ]);
            } else {
                // Handle case where default 'user' role doesn't exist - might be an error or set to NULL
                $sqlUpdateUsersToNull = "UPDATE users SET user_role = NULL WHERE user_role = :deleted_role_key";
                $stmtUpdateUsersToNull = $this->pdo->prepare($sqlUpdateUsersToNull);
                $stmtUpdateUsersToNull->execute([':deleted_role_key' => $role['role_key']]);
                error_log("Default role '{$defaultRoleKey}' not found when deleting role '{$role['role_key']}'. Users assigned to it had their role set to NULL.");
            }


            // Step 3: Delete the role itself
            $sqlRole = "DELETE FROM roles WHERE role_id = :role_id";
            $stmtRole = $this->pdo->prepare($sqlRole);
            $stmtRole->execute([':role_id' => $roleId]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error in RoleModel::deleteRole({$roleId}): " . $e->getMessage());
            return false;
        }
    }
}
