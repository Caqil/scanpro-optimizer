<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 */
class ScanPro_Optimizer
{

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      ScanPro_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        require_once SCANPRO_PLUGIN_DIR . 'includes/class-scanpro-loader.php';
        require_once SCANPRO_PLUGIN_DIR . 'includes/class-scanpro-api.php';
        require_once SCANPRO_PLUGIN_DIR . 'includes/class-scanpro-api-usage.php';
        require_once SCANPRO_PLUGIN_DIR . 'admin/class-scanpro-admin.php';
        $this->loader = new ScanPro_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new ScanPro_Admin();

        // Admin menu and settings
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');

        // Enqueue admin styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Add settings link to plugins page
        $this->loader->add_filter('plugin_action_links_' . plugin_basename(SCANPRO_PLUGIN_DIR . 'scanpro-optimizer.php'), $plugin_admin, 'add_action_links');

        // Media library integration
        $this->loader->add_filter('attachment_fields_to_edit', $plugin_admin, 'add_compress_button', 10, 2);

        // AJAX handlers
        $this->loader->add_action('wp_ajax_scanpro_compress_image', $plugin_admin, 'ajax_compress_image');
        $this->loader->add_action('wp_ajax_scanpro_convert_pdf', $plugin_admin, 'ajax_convert_pdf');
        $this->loader->add_action('wp_ajax_scanpro_validate_api_key', $plugin_admin, 'ajax_validate_api_key');
        $this->loader->add_action('wp_ajax_scanpro_get_usage_stats', $plugin_admin, 'ajax_get_usage_stats');
        // Automatic image compression on upload
        $this->loader->add_filter('wp_handle_upload', $plugin_admin, 'compress_uploaded_image');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        // Public hooks if needed in the future
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }
}