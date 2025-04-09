<?php
/**
 * Handles all API interactions with ScanPro.
 *
 * @since      1.0.0
 */
class ScanPro_API
{

    /**
     * The API key.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_key    The API key for ScanPro service.
     */
    private $api_key;

    /**
     * The base URL for ScanPro API.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_url    The base URL for the API.
     */
    private $api_url = 'https://scanpro.cc/api/';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->api_key = get_option('scanpro_api_key', '');
        $this->usage_tracker = new ScanPro_API_Usage();
    }

    /**
     * Validate API key with ScanPro service.
     *
     * @since    1.0.0
     * @return   boolean|WP_Error    True if valid, WP_Error if not.
     */
    public function validate_api_key($api_key = null)
    {
        $key = $api_key ? $api_key : $this->api_key;

        if (empty($key)) {
            return new WP_Error('empty_key', __('API key is empty', 'scanpro-optimizer'));
        }

        $response = wp_remote_get($this->api_url . 'validate-key', array(
            'headers' => array(
                'x-api-key' => $key
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['valid']) || !$body['valid']) {
            return new WP_Error('invalid_key', isset($body['error']) ? $body['error'] : __('Invalid API key', 'scanpro-optimizer'));
        }

        return true;
    }


    /**
     * Compress an image using ScanPro API.
     *
     * @since    1.0.0
     * @param    string    $file_path    The path to the image file.
     * @param    string    $quality      The compression quality (low, medium, high).
     * @return   array|WP_Error          Compressed file details or WP_Error.
     */
    public function compress_image($file_path, $quality = 'medium')
    {
        if (empty($this->api_key)) {
            return new WP_Error('empty_key', __('API key is not set', 'scanpro-optimizer'));
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('File not found', 'scanpro-optimizer'));
        }

        $file_mime = wp_check_filetype($file_path)['type'];
        if (!in_array($file_mime, array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'))) {
            return new WP_Error('invalid_file_type', __('Unsupported file type', 'scanpro-optimizer'));
        }

        // Check file size - don't try to process files over 10MB
        $file_size = filesize($file_path);
        if ($file_size > 20 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File is too large (max 10MB)', 'scanpro-optimizer'));
        }

        $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
        $this->usage_tracker->track_operation('compress', $file_extension, $file_size);

        $boundary = wp_generate_password(24, false);
        $headers = array(
            'content-type' => 'multipart/form-data; boundary=' . $boundary,
            'x-api-key' => $this->api_key
        );

        $payload = '';

        // Add file to payload
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . "\r\n";
        $payload .= 'Content-Type: ' . $file_mime . "\r\n";
        $payload .= "\r\n";
        $payload .= file_get_contents($file_path);
        $payload .= "\r\n";

        // Add quality to payload
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="quality"' . "\r\n\r\n";
        $payload .= $quality;
        $payload .= "\r\n";

        // Close payload
        $payload .= '--' . $boundary . '--';

        // Log request for debugging
        error_log('ScanPro API Image Compression Request: ' . basename($file_path) . ', Size: ' . $file_size . ' bytes');

        $response = wp_remote_post($this->api_url . 'compress/universal', array(
            'headers' => $headers,
            'body' => $payload,
            'timeout' => 120, // Increase timeout to 120 seconds
            'httpversion' => '1.1'
        ));

        if (is_wp_error($response)) {
            error_log('ScanPro API Error: ' . $response->get_error_message());
            return $response;
        }

        // Check HTTP response code first
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $error_message = 'HTTP Error: ' . $http_code . ' ' . wp_remote_retrieve_response_message($response);
            error_log('ScanPro API Error: ' . $error_message);
            return new WP_Error('http_error', $error_message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Log the entire response for debugging
        error_log('ScanPro API Response: ' . wp_remote_retrieve_body($response));

        if (!isset($body['success']) || !$body['success']) {
            $error_message = isset($body['error']) ? $body['error'] : __('API Error', 'scanpro-optimizer');
            error_log('ScanPro API Error: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        // Validate the response contains the required fields
        if (!isset($body['fileUrl']) || empty($body['fileUrl'])) {
            error_log('ScanPro API Error: Missing or empty fileUrl in response');
            return new WP_Error('invalid_response', __('API returned an invalid response: missing file URL', 'scanpro-optimizer'));
        }

        // Make sure we have a complete URL
        $download_url = $body['fileUrl'];

        // If URL is relative, convert to absolute
        if (strpos($download_url, 'http') !== 0) {
            // Extract domain from API URL
            $api_parts = parse_url($this->api_url);
            $base_url = $api_parts['scheme'] . '://' . $api_parts['host'];

            // Remove leading slash if present for clean concatenation
            $download_url = ltrim($download_url, '/');
            $download_url = $base_url . '/' . $download_url;
        }

        error_log('ScanPro Download URL: ' . $download_url);

        // Custom download with increased timeout
        $temp_file = $this->download_file_with_timeout($download_url, 60);

        if (is_wp_error($temp_file)) {
            error_log('ScanPro Download Error: ' . $temp_file->get_error_message());
            return $temp_file;
        }

        // Verify that the downloaded file exists and has content
        if (!file_exists($temp_file) || filesize($temp_file) === 0) {
            error_log('ScanPro Download Error: Downloaded file is empty or does not exist');
            return new WP_Error('download_error', __('Downloaded file is empty or could not be created', 'scanpro-optimizer'));
        }

        return array(
            'path' => $temp_file,
            'url' => $download_url,
            'size' => isset($body['compressedSize']) ? $body['compressedSize'] : filesize($temp_file),
            'original_size' => isset($body['originalSize']) ? $body['originalSize'] : $file_size,
            'savings_percentage' => isset($body['compressionRatio']) ? $body['compressionRatio'] : '0%',
        );
    }

    /**
     * Custom file download function with configurable timeout.
     *
     * @param string $url      The URL to download.
     * @param int    $timeout  The timeout in seconds.
     * @return string|WP_Error Path to downloaded file or WP_Error.
     */
    private function download_file_with_timeout($url, $timeout = 60)
    {
        // Validate URL
        if (empty($url)) {
            error_log('ScanPro Download Error: Empty URL provided');
            return new WP_Error('invalid_url', __('A valid URL was not provided', 'scanpro-optimizer'));
        }

        // Log the download attempt
        error_log('ScanPro: Downloading file from: ' . $url);

        // Create a unique temporary file
        $temp_file = wp_tempnam();

        // Add our API key to the request headers if downloading from our API
        $headers = array();
        if (strpos($url, $this->api_url) === 0 || strpos($url, parse_url($this->api_url, PHP_URL_HOST)) !== false) {
            $headers['x-api-key'] = $this->api_key;
        }

        $args = array(
            'timeout' => $timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'stream' => true,
            'filename' => $temp_file,
            'headers' => $headers,
            'sslverify' => false, // Sometimes needed for development environments
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            @unlink($temp_file);
            error_log('ScanPro Download Error: ' . $response->get_error_message());
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);

        error_log('ScanPro Download Response Code: ' . $http_code);

        // Check for HTTP errors
        if ($http_code !== 200) {
            @unlink($temp_file);
            $error_message = 'HTTP Error: ' . $http_code . ' ' . wp_remote_retrieve_response_message($response);
            error_log('ScanPro Download Error: ' . $error_message);
            return new WP_Error('http_error', $error_message);
        }

        // Verify file was created and has contents
        if (!file_exists($temp_file) || filesize($temp_file) === 0) {
            @unlink($temp_file);
            error_log('ScanPro Download Error: Downloaded file is empty or does not exist');
            return new WP_Error('empty_file', __('Downloaded file is empty or could not be created', 'scanpro-optimizer'));
        }

        error_log('ScanPro Download Success: File saved to ' . $temp_file . ' (' . filesize($temp_file) . ' bytes)');
        return $temp_file;
    }

    /**
     * Convert a PDF file using ScanPro API.
     *
     * @since    1.0.0
     * @param    string    $file_path      The path to the PDF file.
     * @param    string    $output_format  The output format.
     * @return   array|WP_Error            Converted file details or WP_Error.
     */
    public function convert_pdf($file_path, $output_format = 'docx')
    {
        if (empty($this->api_key)) {
            return new WP_Error('empty_key', __('API key is not set', 'scanpro-optimizer'));
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('File not found', 'scanpro-optimizer'));
        }

        // Get file info more reliably
        $file_info = wp_check_filetype(basename($file_path), null);
        $file_mime = $file_info['type'];
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $file_size = filesize($file_path);

        // Track API usage before making the request
        $this->usage_tracker->track_operation('convert', $file_extension, $file_size);

        // Log file info for debugging
        error_log('ScanPro Convert - File Path: ' . $file_path);
        error_log('ScanPro Convert - File Extension: ' . $file_extension);
        error_log('ScanPro Convert - Detected MIME: ' . $file_mime);

        // Check if input format is valid
        $valid_input_formats = array('pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png');
        if (!in_array($file_extension, $valid_input_formats)) {
            error_log('ScanPro Convert - Invalid input format: ' . $file_extension);
            return new WP_Error('invalid_input_format', __('Invalid or unsupported input format: ' . $file_extension, 'scanpro-optimizer'));
        }

        // Normalize jpg/jpeg to just jpg for the API
        $input_format = ($file_extension === 'jpeg') ? 'jpg' : $file_extension;

        // Check if output format is valid
        $valid_output_formats = array('pdf', 'docx', 'xlsx', 'jpg', 'png', 'txt');
        if (!in_array($output_format, $valid_output_formats)) {
            error_log('ScanPro Convert - Invalid output format: ' . $output_format);
            return new WP_Error('invalid_output_format', __('Invalid or unsupported output format', 'scanpro-optimizer'));
        }

        // Check for valid conversion paths
        $valid_conversions = array(
            'pdf' => array('docx', 'xlsx', 'jpg', 'png', 'txt'),
            'docx' => array('pdf'),
            'xlsx' => array('pdf'),
            'pptx' => array('pdf'),
            'jpg' => array('pdf'),
            'png' => array('pdf')
        );

        if (!isset($valid_conversions[$input_format]) || !in_array($output_format, $valid_conversions[$input_format])) {
            error_log('ScanPro Convert - Invalid conversion path: ' . $input_format . ' to ' . $output_format);
            return new WP_Error('invalid_conversion', __('This conversion path is not supported', 'scanpro-optimizer'));
        }

        $boundary = wp_generate_password(24, false);
        $headers = array(
            'content-type' => 'multipart/form-data; boundary=' . $boundary,
            'x-api-key' => $this->api_key
        );

        $payload = '';

        // Add file to payload
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . "\r\n";
        $payload .= 'Content-Type: ' . $file_mime . "\r\n";
        $payload .= "\r\n";
        $payload .= file_get_contents($file_path);
        $payload .= "\r\n";

        // Add input format
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="inputFormat"' . "\r\n\r\n";
        $payload .= $input_format;
        $payload .= "\r\n";

        // Add output format
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="outputFormat"' . "\r\n\r\n";
        $payload .= $output_format;
        $payload .= "\r\n";

        // Close payload
        $payload .= '--' . $boundary . '--';

        // Log request for debugging
        error_log('ScanPro API Request: ' . $this->api_url . 'convert');
        error_log('ScanPro API Input Format: ' . $input_format);
        error_log('ScanPro API Output Format: ' . $output_format);

        $response = wp_remote_post($this->api_url . 'convert', array(
            'headers' => $headers,
            'body' => $payload,
            'timeout' => 120, // Increased timeout to 120 seconds
        ));

        if (is_wp_error($response)) {
            error_log('ScanPro API Error: ' . $response->get_error_message());
            return $response;
        }

        // Check HTTP response code first
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $error_message = 'HTTP Error: ' . $http_code . ' ' . wp_remote_retrieve_response_message($response);
            error_log('ScanPro API Error: ' . $error_message);
            return new WP_Error('http_error', $error_message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Log response for debugging
        error_log('ScanPro API Response: ' . wp_remote_retrieve_body($response));

        if (!isset($body['success']) || !$body['success']) {
            $error_message = isset($body['error']) ? $body['error'] : __('API Error', 'scanpro-optimizer');
            error_log('ScanPro API Error: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        // Validate the response contains the required fields
        if (!isset($body['fileUrl']) || empty($body['fileUrl'])) {
            error_log('ScanPro API Error: Missing or empty fileUrl in response');
            return new WP_Error('invalid_response', __('API returned an invalid response: missing file URL', 'scanpro-optimizer'));
        }

        // Make sure we have a complete URL
        $download_url = $body['fileUrl'];

        // If URL is relative, convert to absolute
        if (strpos($download_url, 'http') !== 0) {
            // Extract domain from API URL
            $api_parts = parse_url($this->api_url);
            $base_url = $api_parts['scheme'] . '://' . $api_parts['host'];

            // Remove leading slash if present for clean concatenation
            $download_url = ltrim($download_url, '/');
            $download_url = $base_url . '/' . $download_url;
        }

        error_log('ScanPro Download URL: ' . $download_url);

        // Custom download with increased timeout
        $temp_file = $this->download_file_with_timeout($download_url, 120);

        if (is_wp_error($temp_file)) {
            error_log('ScanPro Download Error: ' . $temp_file->get_error_message());
            return $temp_file;
        }

        // Verify that the downloaded file exists and has content
        if (!file_exists($temp_file) || filesize($temp_file) === 0) {
            error_log('ScanPro Download Error: Downloaded file is empty or does not exist');
            return new WP_Error('download_error', __('Downloaded file is empty or could not be created', 'scanpro-optimizer'));
        }

        return array(
            'path' => $temp_file,
            'url' => $download_url,
            'filename' => isset($body['filename']) ? $body['filename'] : basename($download_url),
            'originalName' => isset($body['originalName']) ? $body['originalName'] : basename($file_path),
        );
    }
}