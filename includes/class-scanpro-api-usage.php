<?php
/**
 * Handles API usage tracking and retrieval for ScanPro.
 *
 * @since      1.0.0
 */
class ScanPro_API_Usage
{

    /**
     * The base URL for ScanPro API usage endpoint.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_usage_url    The base URL for the API usage endpoint.
     */
    private $api_usage_url = 'https://scanpro.cc/api/track-usage';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        // Empty constructor to avoid circular dependencies
    }

    /**
     * Get API usage statistics from the ScanPro service.
     *
     * @since    1.0.0
     * @param    string    $period    The period to get stats for ('today', 'week', 'month', 'year').
     * @return   array|WP_Error       Usage statistics or WP_Error if request failed.
     */
    public function get_usage_stats($period = 'month')
    {
        // Check if API key exists
        $api_key = get_option('scanpro_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('empty_key', __('API key is not set', 'scanpro-optimizer'));
        }

        // Make request to ScanPro API for usage statistics
        $response = wp_remote_get($this->api_usage_url, array(
            'headers' => array(
                'x-api-key' => $api_key
            ),
            'body' => array(
                'period' => $period
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['success']) || !$body['success']) {
            $error_message = isset($body['error']) ? $body['error'] : __('Failed to retrieve API usage data', 'scanpro-optimizer');
            return new WP_Error('api_error', $error_message);
        }

        return $body;
    }

    /**
     * Track an API operation by sending anonymous usage data to ScanPro service.
     *
     * @since    1.0.0
     * @param    string    $operation    The operation being performed (compress, convert).
     * @param    string    $file_type    The type of file being processed.
     * @param    int       $file_size    The size of the file in bytes.
     * @return   boolean|WP_Error        True if tracked successfully, WP_Error if not.
     */
    public function track_operation($operation, $file_type, $file_size)
    {
        // Add more robust logging and error handling
        error_log('ScanPro Tracking Operation: ' . $operation);
        error_log('File Type: ' . $file_type);
        error_log('File Size: ' . $file_size);

        $api_key = get_option('scanpro_api_key', '');
        $user_id = get_current_user_id() ?: 'anonymous';

        $response = wp_remote_post($this->api_usage_url . '/track', array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key
            ),
            'body' => json_encode([
                'userId' => (string) $user_id,
                'operation' => $operation,
                'timestamp' => current_time('mysql', true)
            ]),
            'timeout' => 15
        ));

        // Detailed error handling
        if (is_wp_error($response)) {
            error_log('ScanPro API Tracking Error: ' . $response->get_error_message());
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('ScanPro Tracking Response Code: ' . $http_code);
        error_log('ScanPro Tracking Response Body: ' . $response_body);

        if ($http_code !== 200) {
            error_log('ScanPro Tracking Failed - HTTP ' . $http_code);
            return false;
        }

        return true;
    }
}