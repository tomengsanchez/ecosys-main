<?php

/**
 * ObjectModel
 *
 * This class now serves as a compatibility layer or can be phased out.
 * It extends BaseObjectModel. For new code, consider using BaseObjectModel directly
 * or specific models like RoomModel, ReservationModel.
 */
// Ensure BaseObjectModel is loaded if not using a sophisticated autoloader that handles it
// require_once __DIR__ . '/BaseObjectModel.php'; // Autoloader should handle this

class ObjectModel extends BaseObjectModel {
    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo); // Call parent constructor
    }

    // If there were any methods in the original ObjectModel that were NOT moved to BaseObjectModel
    // and are NOT specific enough for a derived class (like ReservationModel), they could remain here.
    // However, the goal is for BaseObjectModel to be comprehensive for generic tasks.

    // For backward compatibility, if any controller was directly calling a method that is now
    // specific to ReservationModel (like getConflictingReservations), this ObjectModel
    // would either need to implement it (and perhaps delegate to a ReservationModel instance)
    // or controllers must be updated to use the correct model.
    // For this refactoring, we will update controllers directly.
}
