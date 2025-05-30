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
        error_log("Attempting to find user: " . $usernameOrEmail); // DEBUG LINE (keep for now)
        try {
            // Prepare SQL statement to find user by user_login (username) or user_email
            // The users table structure is based on your database_structure.sql
            // MODIFIED: Using two distinct placeholders
            $sql = "SELECT user_id, user_login, user_pass, user_email, display_name, user_status 
                    FROM users 
                    WHERE user_login = :user_login_identifier OR user_email = :user_email_identifier 
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            
            // MODIFIED: Pass both parameters in the array to execute()
            $stmt->execute([
                ':user_login_identifier' => $usernameOrEmail,
                ':user_email_identifier' => $usernameOrEmail
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) { 
                error_log("User found: " . print_r($user, true)); // DEBUG LINE (keep for now)
            } else {
                error_log("User NOT found with identifier: " . $usernameOrEmail); // DEBUG LINE (keep for now)
            }

            return $user ? $user : false;

        } catch (PDOException $e) {
            // Log error or handle it as per your application's error handling strategy
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
            $sql = "SELECT user_id, user_login, user_email, display_name, user_status 
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
     * (Optional) Create a new user.
     * This is a basic example. You'll want to add more validation, password hashing, etc.
     *
     * @param string $username
     * @param string $email
     * @param string $password Plain text password (should be hashed before storing)
     * @param string $displayName
     * @return int|false The ID of the newly created user, or false on failure.
     */
    public function createUser($username, $email, $password, $displayName) {
        // IMPORTANT: Always hash passwords before storing them!
        // Use password_hash() for this.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO users (user_login, user_email, user_pass, user_nicename, display_name, user_registered, user_status) 
                    VALUES (:username, :email, :password, :nicename, :displayname, NOW(), 0)"; // user_status 0 = active
            
            $stmt = $this->pdo->prepare($sql);
            
            // Parameters for createUser
            $params = [
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':nicename' => $username, // Often same as username initially
                ':displayname' => $displayName
            ];
            
            if ($stmt->execute($params)) { 
                return $this->pdo->lastInsertId();
            } else {
                return false;
            }
        } catch (PDOException $e) {
            // Check for duplicate entry (error code 23000 for SQLSTATE)
            if ($e->getCode() == 23000) {
                error_log("Error in UserModel::createUser(): Duplicate entry for username or email. " . $e->getMessage());
            } else {
                error_log("Error in UserModel::createUser(): " . $e->getMessage());
            }
            return false;
        }
    }
}
