<?php

/**
 * OptionModel
 *
 * Handles database operations related to the 'options' table (site settings).
 */
class OptionModel {
    private $pdo;
    private $loadedOptions = []; // Cache for loaded options

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // Optionally, load all 'autoload' options here if needed frequently
        // $this->loadAutoloadOptions();
    }

    /**
     * Get a specific option value by its name.
     *
     * @param string $optionName The name of the option.
     * @param mixed $defaultValue The default value to return if the option is not found.
     * @return mixed The value of the option, or the default value.
     */
    public function getOption($optionName, $defaultValue = null) {
        if (array_key_exists($optionName, $this->loadedOptions)) {
            return $this->loadedOptions[$optionName];
        }

        try {
            $sql = "SELECT option_value FROM options WHERE option_name = :option_name LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':option_name' => $optionName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Potentially unserialize if the value could be an array/object
                // For simplicity, assuming string values for now or that serialization is handled elsewhere.
                $this->loadedOptions[$optionName] = $result['option_value'];
                return $result['option_value'];
            }
            return $defaultValue;
        } catch (PDOException $e) {
            error_log("Error in OptionModel::getOption({$optionName}): " . $e->getMessage());
            return $defaultValue;
        }
    }

    /**
     * Get multiple options.
     *
     * @param array $optionNames Array of option names to retrieve.
     * @return array Associative array of option_name => option_value.
     */
    public function getOptions(array $optionNames) {
        $options = [];
        // Check cache first
        $namesToFetch = [];
        foreach ($optionNames as $name) {
            if (array_key_exists($name, $this->loadedOptions)) {
                $options[$name] = $this->loadedOptions[$name];
            } else {
                $namesToFetch[] = $name;
            }
        }

        if (empty($namesToFetch)) {
            return $options;
        }

        try {
            // Create placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($namesToFetch), '?'));
            $sql = "SELECT option_name, option_value FROM options WHERE option_name IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($namesToFetch);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $this->loadedOptions[$row['option_name']] = $row['option_value'];
                $options[$row['option_name']] = $row['option_value'];
            }
            
            // For any names not found in DB, they won't be in $options
            return $options;

        } catch (PDOException $e) {
            error_log("Error in OptionModel::getOptions(): " . $e->getMessage());
            return $options; // Return what was fetched or from cache
        }
    }


    /**
     * Add a new option. Fails if the option already exists.
     *
     * @param string $optionName The name of the option.
     * @param mixed $optionValue The value of the option.
     * @param string $autoload Whether to autoload this option ('yes' or 'no').
     * @return bool True on success, false on failure.
     */
    public function addOption($optionName, $optionValue, $autoload = 'yes') {
        try {
            $sql = "INSERT INTO options (option_name, option_value, autoload) 
                    VALUES (:option_name, :option_value, :autoload)";
            $stmt = $this->pdo->prepare($sql);
            $params = [
                ':option_name' => $optionName,
                ':option_value' => $optionValue, // Consider serialization for arrays/objects
                ':autoload' => $autoload
            ];
            $success = $stmt->execute($params);
            if ($success) {
                $this->loadedOptions[$optionName] = $optionValue;
            }
            return $success;
        } catch (PDOException $e) {
            // Error code 23000 is for integrity constraint violation (e.g., duplicate option_name)
            if ($e->getCode() != 23000) {
                 error_log("Error in OptionModel::addOption({$optionName}): " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Update an existing option. If the option does not exist, it can be added.
     *
     * @param string $optionName The name of the option.
     * @param mixed $optionValue The new value of the option.
     * @param string|null $autoload If provided, updates the autoload status.
     * @return bool True on success, false on failure.
     */
    public function updateOption($optionName, $optionValue, $autoload = null) {
        // Check if option exists
        $existingValue = $this->getOption($optionName, '___OPTION_DOES_NOT_EXIST___');

        if ($existingValue === '___OPTION_DOES_NOT_EXIST___') {
            // Option doesn't exist, so add it
            return $this->addOption($optionName, $optionValue, $autoload ?? 'yes');
        }

        // Option exists, update it
        try {
            $sqlParts = ["option_value = :option_value"];
            $params = [
                ':option_name' => $optionName,
                ':option_value' => $optionValue // Consider serialization
            ];

            if ($autoload !== null) {
                $sqlParts[] = "autoload = :autoload";
                $params[':autoload'] = $autoload;
            }

            $sql = "UPDATE options SET " . implode(', ', $sqlParts) . " WHERE option_name = :option_name";
            $stmt = $this->pdo->prepare($sql);
            
            $success = $stmt->execute($params);
            if ($success) {
                $this->loadedOptions[$optionName] = $optionValue;
            }
            return $success;

        } catch (PDOException $e) {
            error_log("Error in OptionModel::updateOption({$optionName}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save multiple options at once.
     *
     * @param array $options Associative array of option_name => option_value.
     * @return bool True if all options were saved successfully, false otherwise.
     */
    public function saveOptions(array $options) {
        $allSucceeded = true;
        foreach ($options as $name => $value) {
            if (!$this->updateOption($name, $value)) {
                $allSucceeded = false;
                // Optionally log which option failed
                error_log("Failed to save option: {$name}");
            }
        }
        return $allSucceeded;
    }

    /**
     * Delete an option.
     *
     * @param string $optionName The name of the option to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteOption($optionName) {
        try {
            $sql = "DELETE FROM options WHERE option_name = :option_name";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([':option_name' => $optionName]);
            if ($success) {
                unset($this->loadedOptions[$optionName]);
            }
            return $success;
        } catch (PDOException $e) {
            error_log("Error in OptionModel::deleteOption({$optionName}): " . $e->getMessage());
            return false;
        }
    }
}
