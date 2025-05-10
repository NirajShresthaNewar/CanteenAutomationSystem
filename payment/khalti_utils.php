<?php
require_once '../config/khalti.php';
require_once 'khalti_errors.log.php';

// Function to make Khalti API request
function makeKhaltiRequest($url, $data) {
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Key ' . KHALTI_SECRET_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => true
        ]);

        // Create a temporary file to store curl debug output
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);

        // Get verbose information
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        fclose($verbose);

        // Log request details
        logKhaltiError("API Request", [
            'url' => $url,
            'request_data' => $data,
            'response_code' => $status_code,
            'response' => $response,
            'verbose_log' => $verboseLog
        ]);

        curl_close($ch);

        if ($err) {
            throw new Exception("Connection error: " . $err);
        }

        $response_data = json_decode($response, true);
        if ($status_code !== 200) {
            throw new Exception($response_data['detail'] ?? 'API request failed');
        }

        return [
            'response' => $response_data,
            'status_code' => $status_code
        ];
    } catch (Exception $e) {
        logKhaltiError("API Request Failed", [
            'error' => $e->getMessage(),
            'url' => $url,
            'request_data' => $data
        ]);
        throw $e;
    }
} 