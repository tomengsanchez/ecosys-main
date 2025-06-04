<?php

/**
 * ReservationModel
 *
 * Handles database operations specific to 'reservation' objects.
 * Extends BaseObjectModel for generic object functionalities.
 * This model can be used for different types of reservations by specifying
 * the object_type (e.g., 'reservation' for rooms, 'vehicle_reservation' for vehicles).
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
     * Get conflicting reservations for a given parent object (e.g., room or vehicle) and time slot.
     * Assumes reservation start/end datetimes are stored in objectmeta
     * with keys 'reservation_start_datetime' and 'reservation_end_datetime'.
     *
     * @param int $parentId The ID of the parent object (e.g., room_id or vehicle_id). This corresponds to object_parent.
     * @param string $startTime The start datetime of the potential reservation (YYYY-MM-DD HH:MM:SS).
     * @param string $endTime The end datetime of the potential reservation (YYYY-MM-DD HH:MM:SS).
     * @param array $statuses Array of statuses to check against (e.g., ['approved']).
     * @param int|null $excludeReservationId An optional reservation ID to exclude from the check (e.g., when editing an existing reservation).
     * @param string $objectType The type of reservation to check for (e.g., 'reservation' for rooms, 'vehicle_reservation' for vehicles).
     * @return array|false An array of conflicting reservation objects, or false on error.
     */
    public function getConflictingReservations($parentId, $startTime, $endTime, $statuses = ['approved'], $excludeReservationId = null, $objectType = 'reservation') {
        try {
            // Construct the IN clause for statuses
            if (empty($statuses)) { 
                return []; // No statuses to check against, so no conflicts by status.
            }
            $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
            
            $sql = "SELECT o.object_id, o.object_title, o.object_status, o.object_author, o.object_content,
                           oms.meta_value as reservation_start_datetime, 
                           ome.meta_value as reservation_end_datetime
                    FROM objects o
                    JOIN objectmeta oms ON o.object_id = oms.object_id AND oms.meta_key = 'reservation_start_datetime'
                    JOIN objectmeta ome ON o.object_id = ome.object_id AND ome.meta_key = 'reservation_end_datetime'
                    WHERE o.object_type = ? -- Use placeholder for object_type
                      AND o.object_parent = ? 
                      AND o.object_status IN ({$statusPlaceholders})
                      AND oms.meta_value < ? -- Existing reservation starts before new one ends
                      AND ome.meta_value > ? "; //-- Existing reservation ends after new one starts

            // Prepare parameters for the query
            $params = [$objectType, $parentId]; // Add objectType and parentId first
            foreach ($statuses as $status) {
                $params[] = $status; // Add each status for the IN clause
            }
            $params[] = $endTime;   // For oms.meta_value < :endTime
            $params[] = $startTime; // For ome.meta_value > :startTime

            // If an existing reservation ID is provided, exclude it from the conflict check
            if ($excludeReservationId !== null && is_numeric($excludeReservationId)) {
                $sql .= " AND o.object_id != ? ";
                $params[] = (int)$excludeReservationId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Enrich with full meta if needed, though start/end are already selected
            // if ($conflicts) {
            //     foreach ($conflicts as $key => $conflict) {
            //         $conflicts[$key]['meta'] = $this->getAllObjectMeta($conflict['object_id']);
            //     }
            // }
            
            return $conflicts;

        } catch (PDOException $e) {
            error_log("Error in ReservationModel::getConflictingReservations(): " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            return false;
        }
    }

    /**
     * Get all reservations of a specific type, optionally filtered by conditions.
     * This is a convenience method.
     *
     * @param string $reservationObjectType The specific type of reservation (e.g., 'reservation', 'vehicle_reservation').
     * @param array $conditions Associative array of field => value conditions.
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false An array of reservation objects or false on failure.
     */
    public function getAllReservationsOfType($reservationObjectType, array $conditions = [], array $args = []) {
        // The getObjectsByConditions method from BaseObjectModel takes object_type as the first parameter.
        return $this->getObjectsByConditions($reservationObjectType, $conditions, $args);
    }

    /**
     * Get reservations made by a specific user for a specific reservation type.
     *
     * @param int $userId
     * @param string $reservationObjectType The type of reservation.
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false
     */
    public function getReservationsByUserId($userId, $reservationObjectType = 'reservation', array $args = []) {
        $conditions = ['o.object_author' => $userId]; // Assuming 'o.' alias is used in BaseObjectModel
        return $this->getAllReservationsOfType($reservationObjectType, $conditions, $args);
    }

    /**
     * Get reservations for a specific parent object (e.g., room or vehicle).
     *
     * @param int $parentId The ID of the parent object (room_id or vehicle_id).
     * @param string $reservationObjectType The type of reservation.
     * @param array $conditions Additional conditions (e.g., status).
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false
     */
    public function getReservationsByParentId($parentId, $reservationObjectType = 'reservation', array $conditions = [], array $args = []) {
        $conditions['o.object_parent'] = $parentId; // Assuming 'o.' alias
        return $this->getAllReservationsOfType($reservationObjectType, $conditions, $args);
    }
    
    /**
     * Get reservations of a specific type within a specific date range and with given statuses.
     *
     * @param string $reservationObjectType The type of reservation.
     * @param string $rangeStartDateTime Start of the date range (YYYY-MM-DD HH:MM:SS).
     * @param string $rangeEndDateTime End of the date range (YYYY-MM-DD HH:MM:SS).
     * @param array $statuses Array of reservation statuses to include (e.g., ['pending', 'approved']).
     * @param array $args Optional arguments for ordering, limit, etc. (passed to getObjectsByConditions).
     * @return array|false An array of reservation objects or false on failure.
     */
    public function getReservationsInDateRange($reservationObjectType, $rangeStartDateTime, $rangeEndDateTime, array $statuses = ['pending', 'approved'], array $args = []) {
        try {
            $sql = "SELECT o.* FROM objects o
                    INNER JOIN objectmeta oms ON o.object_id = oms.object_id AND oms.meta_key = 'reservation_start_datetime'
                    INNER JOIN objectmeta ome ON o.object_id = ome.object_id AND ome.meta_key = 'reservation_end_datetime'
                    WHERE o.object_type = :object_type";
            
            $params = [':object_type' => $reservationObjectType];

            if (!empty($statuses)) {
                $statusPlaceholders = [];
                foreach ($statuses as $idx => $status) {
                    $paramName = ":status_{$idx}";
                    $statusPlaceholders[] = $paramName;
                    $params[$paramName] = $status;
                }
                $sql .= " AND o.object_status IN (" . implode(',', $statusPlaceholders) . ")";
            }

            $sql .= " AND oms.meta_value < :rangeEndDateTime 
                      AND ome.meta_value > :rangeStartDateTime";
            $params[':rangeStartDateTime'] = $rangeStartDateTime;
            $params[':rangeEndDateTime'] = $rangeEndDateTime;
            
            $orderBy = $args['orderby'] ?? 'o.object_date';
            $orderDir = strtoupper($args['orderdir'] ?? 'DESC');
            $allowedOrderBy = ['o.object_id', 'o.object_title', 'o.object_date', 'o.object_status', 'oms.meta_value'];
            if (!in_array(strtolower($orderBy), array_map('strtolower', $allowedOrderBy))) $orderBy = 'o.object_date';
            if (!in_array($orderDir, ['ASC', 'DESC'])) $orderDir = 'DESC';
            $sql .= " ORDER BY {$orderBy} {$orderDir}";

            if (isset($args['limit']) && is_numeric($args['limit'])) {
                $sql .= " LIMIT " . (int)$args['limit'];
                if (isset($args['offset']) && is_numeric($args['offset'])) $sql .= " OFFSET " . (int)$args['offset'];
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
            error_log("Error in ReservationModel::getReservationsInDateRange(): " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            return false;
        }
    }

    // Renamed original getReservationsByRoomId to getReservationsByParentId for clarity
    // If you still need a specific getReservationsByRoomId, it can call getReservationsByParentId
    public function getReservationsByRoomId($roomId, array $conditions = [], array $args = []) {
        // Assuming 'reservation' is the object_type for rooms
        return $this->getReservationsByParentId($roomId, 'reservation', $conditions, $args);
    }
}
