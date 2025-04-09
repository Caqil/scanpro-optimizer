<?php
/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 */
class ScanPro_Activator
{

    /**
     * Activate the plugin.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $scanpro_dir = $upload_dir['basedir'] . '/scanpro';

        if (!file_exists($scanpro_dir)) {
            wp_mkdir_p($scanpro_dir);
        }

        // Add default settings if they don't exist
        if (!get_option('scanpro_api_key')) {
            add_option('scanpro_api_key', '');
        }

        if (!get_option('scanpro_auto_compress')) {
            add_option('scanpro_auto_compress', 'yes');
        }

        if (!get_option('scanpro_compression_quality')) {
            add_option('scanpro_compression_quality', 'medium');
        }
    }
}