<?php

/**
 * ObjectModel
 *
 * Handles generic database operations for the 'objects' and 'objectmeta' tables.
 */
class ObjectModel {
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
            error_log("ObjectModel::createObject: Missing required fields (author, title, or type).");
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
            error_log("Error in ObjectModel::createObject(): " . $e->getMessage());
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
            $sql = "SELECT * FROM objects WHERE object_id = :object_id LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':object_id' => $objectId]);
            $object = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($object) {
                $object['meta'] = $this->getAllObjectMeta($objectId);
            }
            return $object ? $object : false;
        } catch (PDOException $e) {
            error_log("Error in ObjectModel::getObjectById({$objectId}): " . $e->getMessage());
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
            $sql = "SELECT * FROM objects WHERE object_type = :object_type";
            $params = [':object_type' => $objectType];

            // Order by
            $orderBy = $args['orderby'] ?? 'object_date';
            $orderDir = strtoupper($args['orderdir'] ?? 'DESC');
            if (!in_array($orderBy, ['object_id', 'object_title', 'object_date', 'menu_order', 'object_modified'])) {
                $orderBy = 'object_date';
            }
            if (!in_array($orderDir, ['ASC', 'DESC'])) {
                $orderDir = 'DESC';
            }
            $sql .= " ORDER BY {$orderBy} {$orderDir}";

            // Limit and Offset for pagination
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
            error_log("Error in ObjectModel::getObjectsByType({$objectType}): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get objects based on type and other conditions.
     *
     * @param string $objectType The type of objects to retrieve.
     * @param array $conditions Associative array of conditions (e.g., ['object_status' => 'pending'] or ['object_status' => ['pending', 'approved']]).
     * @param array $args Optional arguments like 'orderby', 'orderdir', 'include_meta'.
     * @return array|false An array of object arrays, or false on failure.
     */
    public function getObjectsByConditions($objectType, array $conditions = [], array $args = []) {
        try {
            $sql = "SELECT * FROM objects WHERE object_type = :object_type";
            $params = [':object_type' => $objectType];
            $whereClauses = [];

            foreach ($conditions as $field => $value) {
                if (is_array($value)) { // Handle IN clause for multiple values (e.g., status in ('pending', 'approved'))
                    $placeholders = [];
                    foreach ($value as $idx => $val) {
                        $paramName = ":{$field}_{$idx}";
                        $placeholders[] = $paramName;
                        $params[$paramName] = $val;
                    }
                    $whereClauses[] = "{$field} IN (" . implode(',', $placeholders) . ")";
                } else { // Handle single value equality
                    $paramName = ":{$field}";
                    $whereClauses[] = "{$field} = {$paramName}";
                    $params[$paramName] = $value;
                }
            }

            if (!empty($whereClauses)) {
                $sql .= " AND " . implode(" AND ", $whereClauses);
            }

            // Order by
            $orderBy = $args['orderby'] ?? 'object_date';
            $orderDir = strtoupper($args['orderdir'] ?? 'DESC');
            $allowedOrderBy = ['object_id', 'object_title', 'object_date', 'menu_order', 'object_modified', 'object_status']; // Add more if needed
            if (!in_array($orderBy, $allowedOrderBy)) {
                $orderBy = 'object_date';
            }
            if (!in_array($orderDir, ['ASC', 'DESC'])) {
                $orderDir = 'DESC';
            }
            $sql .= " ORDER BY {$orderBy} {$orderDir}";

            // Limit and Offset for pagination
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
            error_log("Error in ObjectModel::getObjectsByConditions({$objectType}): " . $e->getMessage());
            return false;
        }
    }


    /**
     * Update an existing object's details and its metadata.
     *
     * @param int $objectId The ID of the object to update.
     * @param array $data Associative array of data to update. Can include 'meta_fields'.
     * @return bool True on success, false on failure.
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
        
        if (!empty($sqlParts) || isset($data['meta_fields'])) { // Only update modified time if there's something to change in main table or meta
            $sqlParts[] = "object_modified = NOW()";
            $sqlParts[] = "object_modified_gmt = NOW()";
        }


        if (empty($sqlParts) && empty($data['meta_fields'])) {
             error_log("ObjectModel::updateObject: No fields to update for object ID {$objectId}.");
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
            error_log("Error in ObjectModel::updateObject({$objectId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an object and all its associated metadata.
     *
     * @param int $objectId The ID of the object to delete.
     * @return bool True on success, false on failure.
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
            error_log("Error in ObjectModel::deleteObject({$objectId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add or update a piece of metadata for an object.
     *
     * @param int $objectId The object ID.
     * @param string $metaKey The meta key.
     * @param mixed $metaValue The meta value.
     * @return bool True on success, false on failure.
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
            error_log("Error in ObjectModel::addOrUpdateObjectMeta({$objectId}, {$metaKey}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a specific piece of metadata for an object.
     *
     * @param int $objectId The object ID.
     * @param string $metaKey The meta key.
     * @return mixed|null The meta value, or null if not found or on error.
     */
    public function getObjectMeta($objectId, $metaKey) {
        try {
            $sql = "SELECT meta_value FROM objectmeta WHERE object_id = :object_id AND meta_key = :meta_key LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':object_id' => $objectId, ':meta_key' => $metaKey]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['meta_value'] : null;
        } catch (PDOException $e) {
            error_log("Error in ObjectModel::getObjectMeta({$objectId}, {$metaKey}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all metadata for a specific object.
     *
     * @param int $objectId The object ID.
     * @return array An associative array of meta_key => meta_value.
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
            error_log("Error in ObjectModel::getAllObjectMeta({$objectId}): " . $e->getMessage());
        }
        return $metaArray;
    }

    /**
     * Delete a specific piece of metadata for an object.
     *
     * @param int $objectId The object ID.
     * @param string $metaKey The meta key to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteObjectMeta($objectId, $metaKey) {
        try {
            $sql = "DELETE FROM objectmeta WHERE object_id = :object_id AND meta_key = :meta_key";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':object_id' => $objectId, ':meta_key' => $metaKey]);
        } catch (PDOException $e) {
            error_log("Error in ObjectModel::deleteObjectMeta({$objectId}, {$metaKey}): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a URL-friendly slug from a string.
     *
     * @param string $text The text to slugify.
     * @return string The slug.
     */
    private function generateSlug($text) {
        $text = str_replace("'", "", $text);
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a-' . time(); 
        }
        return $text;
    }
}
