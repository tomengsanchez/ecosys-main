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

            $orderBy = $args['orderby'] ?? 'object_date';
            $orderDir = strtoupper($args['orderdir'] ?? 'DESC');
            if (!in_array($orderBy, ['object_id', 'object_title', 'object_date', 'menu_order', 'object_modified'])) {
                $orderBy = 'object_date';
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
            error_log("Error in ObjectModel::getObjectsByType({$objectType}): " . $e->getMessage());
            return false;
        }
    }
    
    public function getObjectsByConditions($objectType, array $conditions = [], array $args = []) {
        try {
            $sql = "SELECT * FROM objects WHERE object_type = :object_type";
            $params = [':object_type' => $objectType];
            $whereClauses = [];

            foreach ($conditions as $field => $value) {
                if (is_array($value)) { 
                    $placeholders = [];
                    foreach ($value as $idx => $val) {
                        $paramName = ":{$field}_{$idx}";
                        $placeholders[] = $paramName;
                        $params[$paramName] = $val;
                    }
                    $whereClauses[] = "{$field} IN (" . implode(',', $placeholders) . ")";
                } else { 
                    $paramName = ":{$field}";
                    $whereClauses[] = "{$field} = {$paramName}";
                    $params[$paramName] = $value;
                }
            }

            if (!empty($whereClauses)) {
                $sql .= " AND " . implode(" AND ", $whereClauses);
            }

            $orderBy = $args['orderby'] ?? 'object_date';
            $orderDir = strtoupper($args['orderdir'] ?? 'DESC');
            $allowedOrderBy = ['object_id', 'object_title', 'object_date', 'menu_order', 'object_modified', 'object_status']; 
            if (!in_array($orderBy, $allowedOrderBy)) {
                $orderBy = 'object_date';
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
            error_log("Error in ObjectModel::getObjectsByConditions({$objectType}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get conflicting reservations for a given room and time slot.
     * Assumes reservation start/end datetimes are stored in objectmeta
     * with keys 'reservation_start_datetime' and 'reservation_end_datetime'
     * in a format comparable as strings (e.g., 'YYYY-MM-DD HH:MM:SS' or 'YYYY-MM-DDTHH:MM').
     *
     * @param int $roomId The ID of the room (object_parent for reservation).
     * @param string $startTime The start datetime of the potential reservation.
     * @param string $endTime The end datetime of the potential reservation.
     * @param array $statuses Array of statuses to check against (e.g., ['approved']).
     * @param int|null $excludeReservationId An optional reservation ID to exclude from the check (useful when updating an existing reservation).
     * @return array|false An array of conflicting reservation objects (object_id only, or full objects), or false on error.
     */
    public function getConflictingReservations($roomId, $startTime, $endTime, $statuses = ['approved'], $excludeReservationId = null) {
        try {
            // Construct the IN clause for statuses
            $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
            
            $sql = "SELECT o.object_id, o.object_title, o.object_status,
                           oms.meta_value as reservation_start_datetime, 
                           ome.meta_value as reservation_end_datetime
                    FROM objects o
                    JOIN objectmeta oms ON o.object_id = oms.object_id AND oms.meta_key = 'reservation_start_datetime'
                    JOIN objectmeta ome ON o.object_id = ome.object_id AND ome.meta_key = 'reservation_end_datetime'
                    WHERE o.object_type = 'reservation'
                      AND o.object_parent = ? 
                      AND o.object_status IN ({$statusPlaceholders})
                      AND oms.meta_value < ? 
                      AND ome.meta_value > ? ";

            $params = [$roomId];
            foreach ($statuses as $status) {
                $params[] = $status;
            }
            $params[] = $endTime;   // For oms.meta_value < :endTime
            $params[] = $startTime; // For ome.meta_value > :startTime

            if ($excludeReservationId !== null) {
                $sql .= " AND o.object_id != ? ";
                $params[] = $excludeReservationId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Further enhance with full meta if needed, or just return IDs/basic info
            // For now, returning fetched columns is sufficient for conflict detection.
            // If full object data is needed, it would require another loop and getObjectById.
            return $conflicts;

        } catch (PDOException $e) {
            error_log("Error in ObjectModel::getConflictingReservations(): " . $e->getMessage());
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
