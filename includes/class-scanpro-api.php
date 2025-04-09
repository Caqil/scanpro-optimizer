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

        $response = wp_remote_post($this->api_url . 'compress/universal', array(
            'headers' => $headers,
            'body' => $payload
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['success']) || !$body['success']) {
            return new WP_Error('api_error', isset($body['error']) ? $body['error'] : __('API Error', 'scanpro-optimizer'));
        }

        // Download the compressed file
        $download_url = $body['fileUrl'];
        $temp_file = download_url($download_url);

        if (is_wp_error($temp_file)) {
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

        // Add input format if needed
        $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
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

        $response = wp_remote_post($this->api_url . 'convert', array(
            'headers' => $headers,
            'body' => $payload
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['success']) || !$body['success']) {
            return new WP_Error('api_error', isset($body['error']) ? $body['error'] : __('API Error', 'scanpro-optimizer'));
        }

        // Download the converted file
        $download_url = $body['fileUrl'];
        $temp_file = download_url($download_url);

        if (is_wp_error($temp_file)) {
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