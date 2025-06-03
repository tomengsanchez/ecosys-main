<?php

/**
 * BaseObjectModel
 *
 * Handles generic database operations for the 'objects' and 'objectmeta' tables.
 * This class is intended to be extended by more specific object type models.
 */
class BaseObjectModel {
    protected $pdo; // Changed to protected to allow access by child classes

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Create a new object (e.g., room, event).
     *
     * @param array $data Associative array containing object data.
     * Required: 'object_author', 'object_title', 'object_type'.
     * Optional: 'object_content', 'object_excerpt', 'object_status', 
     * 'object_name' (slug), 'object_parent', 'menu_order'.
     * Meta fields: 'meta_fields' => ['key1' => 'value1', 'key2' => 'value2']
     * @return int|false The ID of the newly created object, or false on failure.
     */
    public function createObject(array $data) {
        if (empty($data['object_author']) || empty($data['object_title']) || empty($data['object_type'])) {
            error_log("BaseObjectModel::createObject: Missing required fields (author, title, or type).");
            return false;
        }

        if (empty($data['object_name'])) {
            $data['object_name'] = $this->generateSlug($data['object_title']);
        }

        try {
            $sql = "INSERT INTO objects (object_author, object_title, object_type, object_content, object_excerpt, object_status, object_name, object_parent, menu_order, object_date, object_date_gmt, object_modified, object_modified_gmt)
                    VALUES (:object_author, :object_title, :object_type, :object_content, :object_excerpt, :object_status, :object_name, :object_parent, :menu_order, NOW(), NOW(), NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $params = [
                ':object_author' => $data['object_author'],
                ':object_title' => $data['object_title'],
                ':object_type' => $data['object_type'],
                ':object_content' => $data['object_content'] ?? null,
                ':object_excerpt' => $data['object_excerpt'] ?? '',
                ':object_status' => $data['object_status'] ?? 'publish',
                ':object_name' => $data['object_name'],
                ':object_parent' => $data['object_parent'] ?? 0,
                ':menu_order' => $data['menu_order'] ?? 0,
            ];

            if ($stmt->execute($params)) {
                $objectId = $this->pdo->lastInsertId();
                if (!empty($data['meta_fields']) && is_array($data['meta_fields'])) {
                    foreach ($data['meta_fields'] as $metaKey => $metaValue) {
                        $this->addOrUpdateObjectMeta($objectId, $metaKey, $metaValue);
                    }
                }
                return $objectId;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::createObject(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get an object by its ID, including its metadata.
     *
     * @param int $objectId The ID of the object.
     * @return array|false The object data as an associative array (with a 'meta' sub-array), or false if not found.
     */
    public function getObjectById($objectId) {
        try {
            $sql = "SELECT o.* FROM objects o WHERE o.object_id = :object_id LIMIT 1"; // Added alias 'o'
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':object_id' => $objectId]);
            $object = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($object) {
                $object['meta'] = $this->getAllObjectMeta($objectId);
            }
            return $object ? $object : false;
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::getObjectById({$objectId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all objects of a specific type, optionally with their metadata.
     *
     * @param string $objectType The type of objects to retrieve (e.g., 'room').
     * @param array $args Optional arguments (e.g., 'limit', 'offset', 'orderby', 'orderdir', 'include_meta').
     * @return array|false An array of object arrays, or false on failure.
     */
    public function getObjectsByType($objectType, array $args = []) {
        try {
            // Use the more generic getObjectsByConditions method
            return $this->getObjectsByConditions($objectType, [], $args);
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::getObjectsByType({$objectType}): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get objects by various conditions, including an optional search term.
     *
     * @param string $objectType The type of objects to retrieve.
     * @param array $conditions Associative array of field => value conditions. Supports array values for IN clauses.
     * Field names in conditions should be prefixed with table alias 'o.' if they are from 'objects' table.
     * @param array $args Optional arguments (limit, offset, orderby, orderdir, include_meta).
     * Orderby field should be prefixed with table alias 'o.' if from 'objects' table.
     * @param string $searchTerm Optional search term to filter by (searches object_title, object_content, object_id).
     * @return array|false An array of object arrays, or false on failure.
     */
    public function getObjectsByConditions($objectType, array $conditions = [], array $args = [], $searchTerm = '') {
        try {
            $sql = "SELECT o.* FROM objects o WHERE o.object_type = :object_type"; // Added alias 'o'
            $params = [':object_type' => $objectType];
            $whereClauses = [];

            foreach ($conditions as $field => $value) {
                // Field name should already include alias if needed, e.g., 'o.object_status'
                if (is_array($value)) { 
                    $placeholders = [];
                    foreach ($value as $idx => $val) {
                        $paramName = ":" . str_replace('.', '_', $field) . "_{$idx}"; // Ensure unique param names, replace dot with underscore
                        $placeholders[] = $paramName;
                        $params[$paramName] = $val;
                    }
                    $whereClauses[] = "{$field} IN (" . implode(',', $placeholders) . ")";
                } else { 
                    $paramName = ":" . str_replace('.', '_', $field); // Replace dot with underscore for param name
                    $whereClauses[] = "{$field} = {$paramName}";
                    $params[$paramName] = $value;
                }
            }

            // Add search term condition
            if (!empty($searchTerm)) {
                $searchParam = ":searchTerm";
                $searchClauses = [];
                $searchClauses[] = "o.object_title LIKE " . $searchParam;
                $searchClauses[] = "o.object_content LIKE " . $searchParam;
                if (is_numeric($searchTerm)) { // Also search by ID if term is numeric
                    $searchClauses[] = "o.object_id = :searchIdNumeric";
                    $params[':searchIdNumeric'] = (int)$searchTerm;
                }
                $whereClauses[] = "(" . implode(" OR ", $searchClauses) . ")";
                $params[$searchParam] = "%" . $searchTerm . "%";
            }


            if (!empty($whereClauses)) {
                $sql .= " AND " . implode(" AND ", $whereClauses);
            }

            // Order by - ensure field includes alias 'o.' if from objects table
            $orderBy = $args['orderby'] ?? 'o.object_date'; // Default to 'o.object_date'
            $orderDir = strtoupper($args['orderdir'] ?? 'DESC');
            // Basic validation for orderBy to prevent obvious SQL injection, more robust validation might be needed
            // if $orderBy comes from less trusted sources.
            $allowedOrderByColumns = ['o.object_id', 'o.object_title', 'o.object_date', 'o.menu_order', 'o.object_modified', 'o.object_status'];
            if (!in_array(strtolower($orderBy), array_map('strtolower', $allowedOrderByColumns))) {
                $orderBy = 'o.object_date'; // Default to a safe column
            }
            if (!in_array($orderDir, ['ASC', 'DESC'])) {
                $orderDir = 'DESC';
            }
            $sql .= " ORDER BY {$orderBy} {$orderDir}";

            if (isset($args['limit']) && is_numeric($args['limit'])) {
                $sql .= " LIMIT " . (int)$args['limit'];
                if (isset($args['offset']) && is_numeric($args['offset'])) {
                    $sql .= " OFFSET " . (int)$args['offset'];
                }
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $objects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($objects && ($args['include_meta'] ?? true)) {
                foreach ($objects as $key => $object) {
                    $objects[$key]['meta'] = $this->getAllObjectMeta($object['object_id']);
                }
            }
            return $objects;

        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::getObjectsByConditions({$objectType}): " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            return false;
        }
    }

    /**
     * Count objects by various conditions, including an optional search term.
     *
     * @param string $objectType The type of objects to count.
     * @param array $conditions Associative array of field => value conditions.
     * Field names in conditions should be prefixed with table alias 'o.' if they are from 'objects' table.
     * @param string $searchTerm Optional search term to filter by.
     * @return int|false The total count of matching objects, or false on failure.
     */
    public function countObjectsByConditions($objectType, array $conditions = [], $searchTerm = '') {
        try {
            $sql = "SELECT COUNT(o.object_id) FROM objects o WHERE o.object_type = :object_type"; // Added alias 'o'
            $params = [':object_type' => $objectType];
            $whereClauses = [];

            foreach ($conditions as $field => $value) {
                 // Field name should already include alias if needed, e.g., 'o.object_status'
                if (is_array($value)) {
                    $placeholders = [];
                    foreach ($value as $idx => $val) {
                        $paramName = ":" . str_replace('.', '_', $field) . "_{$idx}";
                        $placeholders[] = $paramName;
                        $params[$paramName] = $val;
                    }
                    $whereClauses[] = "{$field} IN (" . implode(',', $placeholders) . ")";
                } else {
                    $paramName = ":" . str_replace('.', '_', $field);
                    $whereClauses[] = "{$field} = {$paramName}";
                    $params[$paramName] = $value;
                }
            }
            
            // Add search term condition
            if (!empty($searchTerm)) {
                $searchParam = ":searchTerm";
                $searchClauses = [];
                $searchClauses[] = "o.object_title LIKE " . $searchParam;
                $searchClauses[] = "o.object_content LIKE " . $searchParam;
                 if (is_numeric($searchTerm)) { // Also search by ID if term is numeric
                    $searchClauses[] = "o.object_id = :searchIdNumeric";
                    $params[':searchIdNumeric'] = (int)$searchTerm;
                }
                $whereClauses[] = "(" . implode(" OR ", $searchClauses) . ")";
                $params[$searchParam] = "%" . $searchTerm . "%";
            }

            if (!empty($whereClauses)) {
                $sql .= " AND " . implode(" AND ", $whereClauses);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::countObjectsByConditions({$objectType}): " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            return false;
        }
    }


    /**
     * Update an existing object's details and its metadata.
     */
    public function updateObject($objectId, array $data) {
        $allowedFields = ['object_title', 'object_content', 'object_excerpt', 'object_status', 'object_name', 'object_parent', 'menu_order'];
        $sqlParts = [];
        $params = [':object_id' => $objectId];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $sqlParts[] = "{$key} = :{$key}";
                if ($key === 'object_excerpt' && $value === null) {
                    $params[":{$key}"] = '';
                } else {
                    $params[":{$key}"] = $value;
                }
            }
        }
        
        if (!empty($sqlParts) || isset($data['meta_fields'])) { 
            $sqlParts[] = "object_modified = NOW()";
            $sqlParts[] = "object_modified_gmt = NOW()";
        }

        if (empty($sqlParts) && empty($data['meta_fields'])) {
             error_log("BaseObjectModel::updateObject: No fields to update for object ID {$objectId}.");
            return true; 
        }

        try {
            if (!empty($sqlParts)) {
                $sql = "UPDATE objects SET " . implode(', ', $sqlParts) . " WHERE object_id = :object_id";
                $stmt = $this->pdo->prepare($sql);
                if (!$stmt->execute($params)) {
                    return false; 
                }
            }

            if (!empty($data['meta_fields']) && is_array($data['meta_fields'])) {
                foreach ($data['meta_fields'] as $metaKey => $metaValue) {
                    $this->addOrUpdateObjectMeta($objectId, $metaKey, $metaValue);
                }
            }
            return true;
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::updateObject({$objectId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an object and all its associated metadata.
     */
    public function deleteObject($objectId) {
        try {
            $this->pdo->beginTransaction();

            $sqlMeta = "DELETE FROM objectmeta WHERE object_id = :object_id";
            $stmtMeta = $this->pdo->prepare($sqlMeta);
            $stmtMeta->execute([':object_id' => $objectId]);

            $sqlObject = "DELETE FROM objects WHERE object_id = :object_id";
            $stmtObject = $this->pdo->prepare($sqlObject);
            $stmtObject->execute([':object_id' => $objectId]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error in BaseObjectModel::deleteObject({$objectId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add or update a piece of metadata for an object.
     */
    public function addOrUpdateObjectMeta($objectId, $metaKey, $metaValue) {
        try {
            $sqlCheck = "SELECT meta_id FROM objectmeta WHERE object_id = :object_id AND meta_key = :meta_key LIMIT 1";
            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->execute([':object_id' => $objectId, ':meta_key' => $metaKey]);
            $existingMeta = $stmtCheck->fetch();

            if ($existingMeta) {
                $sql = "UPDATE objectmeta SET meta_value = :meta_value WHERE meta_id = :meta_id";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([':meta_value' => $metaValue, ':meta_id' => $existingMeta['meta_id']]);
            } else {
                $sql = "INSERT INTO objectmeta (object_id, meta_key, meta_value) VALUES (:object_id, :meta_key, :meta_value)";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([':object_id' => $objectId, ':meta_key' => $metaKey, ':meta_value' => $metaValue]);
            }
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::addOrUpdateObjectMeta({$objectId}, {$metaKey}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a specific piece of metadata for an object.
     */
    public function getObjectMeta($objectId, $metaKey) {
        try {
            $sql = "SELECT meta_value FROM objectmeta WHERE object_id = :object_id AND meta_key = :meta_key LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':object_id' => $objectId, ':meta_key' => $metaKey]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['meta_value'] : null;
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::getObjectMeta({$objectId}, {$metaKey}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all metadata for a specific object.
     */
    public function getAllObjectMeta($objectId) {
        $metaArray = [];
        try {
            $sql = "SELECT meta_key, meta_value FROM objectmeta WHERE object_id = :object_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':object_id' => $objectId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                $metaArray[$row['meta_key']] = $row['meta_value'];
            }
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::getAllObjectMeta({$objectId}): " . $e->getMessage());
        }
        return $metaArray;
    }

    /**
     * Delete a specific piece of metadata for an object.
     */
    public function deleteObjectMeta($objectId, $metaKey) {
        try {
            $sql = "DELETE FROM objectmeta WHERE object_id = :object_id AND meta_key = :meta_key";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':object_id' => $objectId, ':meta_key' => $metaKey]);
        } catch (PDOException $e) {
            error_log("Error in BaseObjectModel::deleteObjectMeta({$objectId}, {$metaKey}): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a URL-friendly slug from a string.
     * Changed to protected to be accessible by child classes if needed, or can be private if only used here.
     */
    protected function generateSlug($text) {
        $text = str_replace("'", "", $text); // Remove apostrophes
        $text = preg_replace('~[^\pL\d]+~u', '-', $text); // Replace non-letter/non-digit with -
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // Transliterate to ASCII
        $text = preg_replace('~[^-\w]+~', '', $text); // Remove unwanted characters
        $text = trim($text, '-'); // Trim hyphens from start/end
        $text = preg_replace('~-+~', '-', $text); // Replace multiple hyphens with single
        $text = strtolower($text); // Convert to lowercase
        if (empty($text)) {
            return 'n-a-' . time(); // Fallback for empty slugs
        }
        return $text;
    }
}
