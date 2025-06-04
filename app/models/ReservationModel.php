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
                    WHERE o.object_type = ? 
                      AND o.object_parent = ? 
                      AND o.object_status IN ({$statusPlaceholders})
                      AND oms.meta_value < ? 
                      AND ome.meta_value > ? "; 

            $params = [$objectType, $parentId]; 
            foreach ($statuses as $status) {
                $params[] = $status; 
            }
            $params[] = $endTime;   
            $params[] = $startTime; 

            if ($excludeReservationId !== null && is_numeric($excludeReservationId)) {
                $sql .= " AND o.object_id != ? ";
                $params[] = (int)$excludeReservationId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
        if (empty($reservationObjectType)) {
            error_log("ReservationModel::getAllReservationsOfType called with empty reservationObjectType.");
            return false;
        }
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
        if (empty($reservationObjectType)) {
            error_log("ReservationModel::getReservationsByUserId called with empty reservationObjectType for user ID {$userId}.");
            return false;
        }
        $conditions = ['o.object_author' => $userId]; 
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
        if (empty($reservationObjectType)) {
            error_log("ReservationModel::getReservationsByParentId called with empty reservationObjectType for parent ID {$parentId}.");
            return false;
        }
        $conditions['o.object_parent'] = $parentId; 
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
        // ** ADDED: Check if reservationObjectType is provided **
        if (empty($reservationObjectType)) {
            error_log("ReservationModel::getReservationsInDateRange called with empty reservationObjectType.");
            return false; // Or handle as appropriate, e.g., throw an exception
        }
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

            // Ensure the date range query is inclusive of events that START within the range,
            // or END within the range, or SPAN the entire range, or are CONTAINED within the range.
            // The condition (ExistingStart < RangeEnd) AND (ExistingEnd > RangeStart) covers all overlaps.
            $sql .= " AND oms.meta_value < :rangeEndDateTime 
                      AND ome.meta_value > :rangeStartDateTime";
            $params[':rangeStartDateTime'] = $rangeStartDateTime;
            $params[':rangeEndDateTime'] = $rangeEndDateTime;
            
            $orderBy = $args['orderby'] ?? 'oms.meta_value'; // Default order by reservation start time
            $orderDir = strtoupper($args['orderdir'] ?? 'ASC'); // Default to ascending for chronological order
            $allowedOrderBy = ['o.object_id', 'o.object_title', 'o.object_date', 'o.object_status', 'oms.meta_value', 'ome.meta_value'];
            if (!in_array(strtolower($orderBy), array_map('strtolower', $allowedOrderBy))) $orderBy = 'oms.meta_value';
            if (!in_array($orderDir, ['ASC', 'DESC'])) $orderDir = 'ASC';
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
                    // We already selected reservation_start_datetime and reservation_end_datetime
                    // If other meta fields are needed, fetch them here.
                    // For performance, if only start/end are needed for the calendar,
                    // this full getAllObjectMeta might be overkill.
                    // However, the current DashboardController uses it for 'purpose' etc.
                    $objects[$key]['meta'] = $this->getAllObjectMeta($object['object_id']);
                }
            }
            return $objects;

        } catch (PDOException $e) {
            error_log("Error in ReservationModel::getReservationsInDateRange(): " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            return false;
        }
    }

    public function getReservationsByRoomId($roomId, array $conditions = [], array $args = []) {
        return $this->getReservationsByParentId($roomId, 'reservation', $conditions, $args);
    }
}
