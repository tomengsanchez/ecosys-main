<?php

/**
 * ReservationModel
 *
 * Handles database operations specific to 'reservation' objects.
 * Extends BaseObjectModel for generic object functionalities.
 */
class ReservationModel extends BaseObjectModel {

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo); // Call parent constructor
    }

    /**
     * Get conflicting reservations for a given room and time slot.
     * Assumes reservation start/end datetimes are stored in objectmeta
     * with keys 'reservation_start_datetime' and 'reservation_end_datetime'.
     *
     * @param int $roomId The ID of the room (object_parent for reservation).
     * @param string $startTime The start datetime of the potential reservation (YYYY-MM-DD HH:MM:SS).
     * @param string $endTime The end datetime of the potential reservation (YYYY-MM-DD HH:MM:SS).
     * @param array $statuses Array of statuses to check against (e.g., ['approved']).
     * @param int|null $excludeReservationId An optional reservation ID to exclude from the check.
     * @return array|false An array of conflicting reservation objects, or false on error.
     */
    public function getConflictingReservations($roomId, $startTime, $endTime, $statuses = ['approved'], $excludeReservationId = null) {
        try {
            // Construct the IN clause for statuses
            if (empty($statuses)) { 
                return [];
            }
            $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
            
            $sql = "SELECT o.object_id, o.object_title, o.object_status, o.object_author, o.object_content,
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

            if ($excludeReservationId !== null && is_numeric($excludeReservationId)) {
                $sql .= " AND o.object_id != ? ";
                $params[] = (int)$excludeReservationId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $conflicts;

        } catch (PDOException $e) {
            error_log("Error in ReservationModel::getConflictingReservations(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all reservations, optionally filtered by conditions.
     * This is a convenience method.
     *
     * @param array $conditions Associative array of field => value conditions.
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false An array of reservation objects or false on failure.
     */
    public function getAllReservations(array $conditions = [], array $args = []) {
        // Ensure the object_type is always 'reservation' for this model's specific methods
        return $this->getObjectsByConditions('reservation', $conditions, $args);
    }

    /**
     * Get reservations made by a specific user.
     *
     * @param int $userId
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false
     */
    public function getReservationsByUserId($userId, array $args = []) {
        $conditions = ['o.object_author' => $userId]; // Assuming 'o.' alias is used in BaseObjectModel
        return $this->getAllReservations($conditions, $args);
    }

    /**
     * Get reservations for a specific room (object_parent).
     *
     * @param int $roomId
     * @param array $conditions Additional conditions (e.g., status).
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false
     */
    public function getReservationsByRoomId($roomId, array $conditions = [], array $args = []) {
        $conditions['o.object_parent'] = $roomId; // Assuming 'o.' alias
        return $this->getAllReservations($conditions, $args);
    }
    
    /**
     * Get reservations within a specific date range and with given statuses.
     *
     * @param string $rangeStartDateTime Start of the date range (YYYY-MM-DD HH:MM:SS).
     * @param string $rangeEndDateTime End of the date range (YYYY-MM-DD HH:MM:SS).
     * @param array $statuses Array of reservation statuses to include (e.g., ['pending', 'approved']).
     * @param array $args Optional arguments for ordering, limit, etc. (passed to getObjectsByConditions).
     * @return array|false An array of reservation objects or false on failure.
     */
    public function getReservationsInDateRange($rangeStartDateTime, $rangeEndDateTime, array $statuses = ['pending', 'approved'], array $args = []) {
        try {
            // Base conditions
            $conditions = [];
            if (!empty($statuses)) {
                $conditions['o.object_status'] = $statuses;
            }

            // We need to build a more complex query here because the date range
            // applies to meta values, not direct columns in the 'objects' table.
            // So, we'll construct the SQL directly for this specific need.

            $sql = "SELECT o.* FROM objects o
                    INNER JOIN objectmeta oms ON o.object_id = oms.object_id AND oms.meta_key = 'reservation_start_datetime'
                    INNER JOIN objectmeta ome ON o.object_id = ome.object_id AND ome.meta_key = 'reservation_end_datetime'
                    WHERE o.object_type = :object_type";
            
            $params = [':object_type' => 'reservation'];

            // Add status conditions
            if (!empty($statuses)) {
                $statusPlaceholders = [];
                foreach ($statuses as $idx => $status) {
                    $paramName = ":status_{$idx}";
                    $statusPlaceholders[] = $paramName;
                    $params[$paramName] = $status;
                }
                $sql .= " AND o.object_status IN (" . implode(',', $statusPlaceholders) . ")";
            }

            // Add date range condition:
            // An event is within the range if:
            // (event_start < range_end) AND (event_end > range_start)
            $sql .= " AND oms.meta_value < :rangeEndDateTime 
                      AND ome.meta_value > :rangeStartDateTime";
            $params[':rangeStartDateTime'] = $rangeStartDateTime;
            $params[':rangeEndDateTime'] = $rangeEndDateTime;
            
            // Add ordering from $args if provided
            $orderBy = $args['orderby'] ?? 'o.object_date';
            $orderDir = strtoupper($args['orderdir'] ?? 'DESC');
            $allowedOrderBy = ['o.object_id', 'o.object_title', 'o.object_date', 'o.object_status', 'oms.meta_value']; // oms.meta_value for start time sort
            if (!in_array(strtolower($orderBy), array_map('strtolower', $allowedOrderBy))) {
                $orderBy = 'o.object_date';
            }
            if (!in_array($orderDir, ['ASC', 'DESC'])) {
                $orderDir = 'DESC';
            }
            $sql .= " ORDER BY {$orderBy} {$orderDir}";

            // Add limit/offset from $args if provided
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
                    // Fetch all meta for each object if not already included or if needed separately
                    // Since we joined for start/end time, we might already have them.
                    // But to be consistent with getObjectsByConditions, we can call getAllObjectMeta.
                    $objects[$key]['meta'] = $this->getAllObjectMeta($object['object_id']);
                }
            }
            return $objects;

        } catch (PDOException $e) {
            error_log("Error in ReservationModel::getReservationsInDateRange(): " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            return false;
        }
    }
}
