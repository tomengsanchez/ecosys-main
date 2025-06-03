<?php

/**
 * RolePermissionModel
 *
 * Manages the mapping between roles and capabilities in the 'role_permissions' table.
 */
class RolePermissionModel {
    private $pdo;

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all capabilities assigned to a specific role.
     *
     * @param string $roleName The name of the role.
     * @return array An array of capability keys. Returns an empty array if role not found or no capabilities.
     */
    public function getCapabilitiesForRole($roleName) {
        try {
            $sql = "SELECT capability_key FROM role_permissions WHERE role_name = :role_name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':role_name' => $roleName]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN); // Fetch a single column from all rows
            return $results ?: []; // Return empty array if no results
        } catch (PDOException $e) {
            error_log("Error in RolePermissionModel::getCapabilitiesForRole({$roleName}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Assign a single capability to a role.
     *
     * @param string $roleName
     * @param string $capabilityKey
     * @return bool True on success, false on failure (e.g., if already exists or DB error).
     */
    public function assignCapabilityToRole($roleName, $capabilityKey) {
        try {
            $sql = "INSERT INTO role_permissions (role_name, capability_key) VALUES (:role_name, :capability_key)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':role_name' => $roleName,
                ':capability_key' => $capabilityKey
            ]);
        } catch (PDOException $e) {
            // Error code 23000 is for integrity constraint violation (duplicate entry)
            if ($e->getCode() != 23000) {
                error_log("Error in RolePermissionModel::assignCapabilityToRole({$roleName}, {$capabilityKey}): " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Revoke a single capability from a role.
     *
     * @param string $roleName
     * @param string $capabilityKey
     * @return bool True on success, false on failure.
     */
    public function revokeCapabilityFromRole($roleName, $capabilityKey) {
        try {
            $sql = "DELETE FROM role_permissions WHERE role_name = :role_name AND capability_key = :capability_key";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':role_name' => $roleName,
                ':capability_key' => $capabilityKey
            ]);
        } catch (PDOException $e) {
            error_log("Error in RolePermissionModel::revokeCapabilityFromRole({$roleName}, {$capabilityKey}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set all capabilities for a given role.
     * This will typically involve deleting all existing capabilities for the role
     * and then inserting the new set.
     *
     * @param string $roleName The role to update.
     * @param array $capabilityKeys An array of capability keys to assign to the role.
     * @return bool True if all operations succeeded, false otherwise.
     */
    public function setRoleCapabilities($roleName, array $capabilityKeys) {
        try {
            $this->pdo->beginTransaction();

            // Delete existing capabilities for this role
            $sqlDelete = "DELETE FROM role_permissions WHERE role_name = :role_name";
            $stmtDelete = $this->pdo->prepare($sqlDelete);
            $stmtDelete->execute([':role_name' => $roleName]);

            // Insert new capabilities
            if (!empty($capabilityKeys)) {
                $sqlInsert = "INSERT INTO role_permissions (role_name, capability_key) VALUES (:role_name, :capability_key)";
                $stmtInsert = $this->pdo->prepare($sqlInsert);
                foreach ($capabilityKeys as $capabilityKey) {
                    // Validate if capabilityKey is a defined one (optional, but good practice)
                    // if (!array_key_exists($capabilityKey, CAPABILITIES)) { continue; } // Assuming CAPABILITIES is accessible
                    
                    $stmtInsert->execute([
                        ':role_name' => $roleName,
                        ':capability_key' => $capabilityKey
                    ]);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error in RolePermissionModel::setRoleCapabilities({$roleName}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a role has a specific capability.
     *
     * @param string $roleName
     * @param string $capabilityKey
     * @return bool
     */
    public function roleHasCapability($roleName, $capabilityKey) {
        try {
            $sql = "SELECT COUNT(*) FROM role_permissions 
                    WHERE role_name = :role_name AND capability_key = :capability_key";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':role_name' => $roleName,
                ':capability_key' => $capabilityKey
            ]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in RolePermissionModel::roleHasCapability({$roleName}, {$capabilityKey}): " . $e->getMessage());
            return false;
        }
    }
}
