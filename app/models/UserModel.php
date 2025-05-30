<?php

/**
 * UserModel
 *
 * Handles database operations related to users.
 */
class UserModel {
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
     * Find a user by their username or email.
     *
     * @param string $usernameOrEmail The username or email to search for.
     * @return array|false The user data as an associative array if found, otherwise false.
     */
    public function findUserByUsernameOrEmail($usernameOrEmail) {
        // error_log("Attempting to find user: " . $usernameOrEmail); 
        try {
            // Added user_role to the SELECT statement
            $sql = "SELECT user_id, user_login, user_pass, user_email, display_name, user_role, user_status 
                    FROM users 
                    WHERE user_login = :user_login_identifier OR user_email = :user_email_identifier 
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_login_identifier' => $usernameOrEmail,
                ':user_email_identifier' => $usernameOrEmail
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            // if ($user) { error_log("User found: " . print_r($user, true)); } else { error_log("User NOT found with identifier: " . $usernameOrEmail); }
            return $user ? $user : false;
        } catch (PDOException $e) {
            error_log("Error in UserModel::findUserByUsernameOrEmail(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find a user by their ID.
     *
     * @param int $userId The ID of the user.
     * @return array|false The user data as an associative array if found, otherwise false.
     */
    public function findUserById($userId) {
        try {
            // Added user_role to the SELECT statement
            $sql = "SELECT user_id, user_login, user_email, user_nicename, display_name, user_role, user_status 
                    FROM users 
                    WHERE user_id = :user_id 
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ? $user : false;
        } catch (PDOException $e) {
            error_log("Error in UserModel::findUserById(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users from the database.
     *
     * @return array|false An array of user objects (associative arrays) or false on failure.
     */
    public function getAllUsers() {
        try {
            // Added user_role to the SELECT statement
            $sql = "SELECT user_id, user_login, user_email, display_name, user_role, user_registered, user_status 
                    FROM users 
                    ORDER BY user_registered DESC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in UserModel::getAllUsers(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new user (can be used by admin or registration).
     *
     * @param string $username
     * @param string $email
     * @param string $password Plain text password (will be hashed)
     * @param string $displayName
     * @param string $userRole (Optional, defaults to 'user')
     * @param string $nicename (Optional, defaults to username)
     * @param int $status (Optional, defaults to 0 for active)
     * @return int|false The ID of the newly created user, or false on failure.
     */
    public function createUser($username, $email, $password, $displayName, $userRole = 'user', $nicename = null, $status = 0) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        if ($nicename === null) {
            $nicename = $username;
        }
        try {
            // Added user_role to INSERT statement
            $sql = "INSERT INTO users (user_login, user_email, user_pass, user_nicename, display_name, user_role, user_registered, user_status) 
                    VALUES (:username, :email, :password, :nicename, :displayname, :user_role, NOW(), :status)";
            $stmt = $this->pdo->prepare($sql);
            $params = [
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':nicename' => $nicename,
                ':displayname' => $displayName,
                ':user_role' => $userRole, // Added user_role parameter
                ':status' => $status
            ];
            if ($stmt->execute($params)) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error in UserModel::createUser(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing user's details.
     *
     * @param int $userId The ID of the user to update.
     * @param array $data Associative array of data to update.
     * @return bool True on success, false on failure.
     */
    public function updateUser($userId, $data) {
        // Added 'user_role' to allowed fields
        $allowedFields = ['user_login', 'user_email', 'user_nicename', 'display_name', 'user_role', 'user_status'];
        $sqlParts = [];
        $params = [':user_id' => $userId];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $sqlParts[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (isset($data['user_pass']) && !empty($data['user_pass'])) {
            $params[':user_pass'] = password_hash($data['user_pass'], PASSWORD_DEFAULT);
            $sqlParts[] = "user_pass = :user_pass";
        }
        
        if (empty($sqlParts)) {
            return true; 
        }
        
        $sql = "UPDATE users SET " . implode(', ', $sqlParts) . " WHERE user_id = :user_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error in UserModel::updateUser(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a user from the database.
     *
     * @param int $userId The ID of the user to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteUser($userId) {
        if ($userId == 1) {
            error_log("Attempt to delete super admin (user_id 1) prevented.");
            return false; 
        }
        try {
            $sql = "DELETE FROM users WHERE user_id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error in UserModel::deleteUser(): " . $e->getMessage());
            return false;
        }
    }
}
