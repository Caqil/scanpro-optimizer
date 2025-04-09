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
    private $api_usage_url = 'https://scanpro.cc/api/usage';

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
        // We'll use the existing API key for authentication
        $api_key = get_option('scanpro_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('empty_key', __('API key is not set', 'scanpro-optimizer'));
        }

        // Make request to ScanPro API to track usage
        $response = wp_remote_post($this->api_usage_url . '/track', array(
            'headers' => array(
                'x-api-key' => $api_key
            ),
            'body' => array(
                'operation' => $operation,
                'file_type' => $file_type,
                'file_size' => $file_size,
                'timestamp' => current_time('mysql', true)
            ),
            'timeout' => 5 // Short timeout since this is non-critical
        ));

        if (is_wp_error($response)) {
            // Log error but don't return it to avoid interrupting the user's workflow
            error_log('ScanPro API Usage Tracking Error: ' . $response->get_error_message());
            return false;
        }

        return true;
    }
}