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
            if (empty($statuses)) { // Should not happen if called correctly, but good to check
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
            
            // Optionally, fetch full meta for each conflict if needed, but for conflict detection, this is usually enough.
            // if ($conflicts) {
            //     foreach ($conflicts as &$conflict) {
            //         $conflict['meta'] = $this->getAllObjectMeta($conflict['object_id']);
            //     }
            //     unset($conflict);
            // }
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
        $conditions = ['object_author' => $userId];
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
        $conditions['object_parent'] = $roomId;
        return $this->getAllReservations($conditions, $args);
    }
    
    // You can add more reservation-specific methods here, e.g.:
    // - createReservation(array $data) // Could call parent::createObject with specific defaults or validation
    // - updateReservationStatus($reservationId, $newStatus)
}
