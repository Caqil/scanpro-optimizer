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
        if ($file_size > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File is too large (max 10MB)', 'scanpro-optimizer'));
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

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['success']) || !$body['success']) {
            $error_message = isset($body['error']) ? $body['error'] : __('API Error', 'scanpro-optimizer');
            error_log('ScanPro API Error: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        // Download the compressed file with increased timeout
        $download_url = $body['fileUrl'];

        // Custom download with increased timeout
        $temp_file = $this->download_file_with_timeout($download_url, 60);

        if (is_wp_error($temp_file)) {
            error_log('ScanPro Download Error: ' . $temp_file->get_error_message());
            return $temp_file;
        }

        return array(
            'path' => $temp_file,
            'url' => $download_url,
            'size' => $body['compressedSize'],
            'original_size' => $body['originalSize'],
            'savings_percentage' => $body['compressionRatio'],
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
        $temp_file = wp_tempnam();

        $args = array(
            'timeout' => $timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'stream' => true,
            'filename' => $temp_file,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            @unlink($temp_file);
            return $response;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            @unlink($temp_file);
            return new WP_Error('http_404', trim(wp_remote_retrieve_response_message($response)));
        }

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

        $file_mime = wp_check_filetype($file_path)['type'];

        // Get the file extension properly
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        // Check if input format is valid
        $valid_input_formats = array('pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png');
        if (!in_array($file_extension, $valid_input_formats)) {
            return new WP_Error('invalid_input_format', __('Invalid or unsupported input format', 'scanpro-optimizer'));
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
        $payload .= $file_extension;
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
        error_log('ScanPro API Input Format: ' . $file_extension);
        error_log('ScanPro API Output Format: ' . $output_format);

        $response = wp_remote_post($this->api_url . 'convert', array(
            'headers' => $headers,
            'body' => $payload,
            'timeout' => 60, // Increased timeout to 60 seconds
        ));

        if (is_wp_error($response)) {
            error_log('ScanPro API Error: ' . $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Log response for debugging
        error_log('ScanPro API Response: ' . wp_remote_retrieve_body($response));

        if (!isset($body['success']) || !$body['success']) {
            $error_message = isset($body['error']) ? $body['error'] : __('API Error', 'scanpro-optimizer');
            error_log('ScanPro API Error: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        // Download the converted file
        $download_url = $body['fileUrl'];
        $temp_file = download_url($download_url);

        if (is_wp_error($temp_file)) {
            error_log('ScanPro Download Error: ' . $temp_file->get_error_message());
            return $temp_file;
        }

        return array(
            'path' => $temp_file,
            'url' => $download_url,
            'filename' => $body['filename'],
            'originalName' => $body['originalName'],
        );
    }
}