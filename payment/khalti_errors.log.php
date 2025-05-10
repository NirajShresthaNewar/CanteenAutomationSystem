<?php
/**
 * Log Khalti payment errors
 * @param string $error_message The error message
 * @param array $context Additional context data
 * @return bool Returns true if logging was successful, false otherwise
 */
function logKhaltiError($error_message, $context = []) {
    try {
        // Define log directory and file
        $log_dir = __DIR__ . '/logs';
        $log_file = $log_dir . '/khalti_errors.log';

        // Create logs directory if it doesn't exist
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }

        // Format the log message
        $log_entry = sprintf(
            "[%s] %s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $error_message,
            json_encode($context, JSON_PRETTY_PRINT),
            str_repeat('-', 80)
        );

        // Write to log file
        return file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false;
    } catch (Exception $e) {
        error_log("Failed to write to Khalti error log: " . $e->getMessage());
        return false;
    }
}

/**
 * Read the Khalti error log
 * @return string The contents of the error log
 */
function readKhaltiErrorLog() {
    $log_file = __DIR__ . '/logs/khalti_errors.log';
    return file_exists($log_file) ? file_get_contents($log_file) : '';
}

// Function to clear the error log
function clearKhaltiErrorLog() {
    $log_file = __DIR__ . '/logs/khalti_errors.log';
    if (file_exists($log_file)) {
        return file_put_contents($log_file, '');
    }
    return true;
}
?> 