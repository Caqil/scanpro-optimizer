<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 */
class ScanPro_Admin
{

    /**
     * The API client instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      ScanPro_API    $api    The API client instance.
     */
    private $api;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->api = new ScanPro_API();
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style('scanpro-admin', SCANPRO_PLUGIN_URL . 'admin/css/scanpro-admin.css', array(), SCANPRO_VERSION, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script('scanpro-admin', SCANPRO_PLUGIN_URL . 'admin/js/scanpro-admin.js', array('jquery'), SCANPRO_VERSION, false);

        wp_localize_script('scanpro-admin', 'scanpro_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scanpro-ajax-nonce'),
            'i18n' => array(
                'compress_success' => __('Image compressed successfully!', 'scanpro-optimizer'),
                'compress_error' => __('Error compressing image.', 'scanpro-optimizer'),
                'convert_success' => __('File converted successfully!', 'scanpro-optimizer'),
                'convert_error' => __('Error converting file.', 'scanpro-optimizer'),
                'api_validation_success' => __('API key validated successfully!', 'scanpro-optimizer'),
                'api_validation_error' => __('Invalid API key.', 'scanpro-optimizer'),
            )
        ));
    }

    /**
     * Add menu items for plugin settings.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu()
    {
        // Main menu item
        add_menu_page(
            __('ScanPro PDF & Image Optimizer', 'scanpro-optimizer'),
            __('ScanPro', 'scanpro-optimizer'),
            'manage_options',
            'scanpro-settings',
            array($this, 'display_settings_page'),
            'dashicons-format-image',
            100
        );

        // Settings submenu
        add_submenu_page(
            'scanpro-settings',
            __('Settings', 'scanpro-optimizer'),
            __('Settings', 'scanpro-optimizer'),
            'manage_options',
            'scanpro-settings'
        );

        // PDF Tools submenu
        add_submenu_page(
            'scanpro-settings',
            __('PDF Tools', 'scanpro-optimizer'),
            __('PDF Tools', 'scanpro-optimizer'),
            'manage_options',
            'scanpro-pdf-tools',
            array($this, 'display_pdf_tools_page')
        );

        // Bulk Optimizer submenu
        add_submenu_page(
            'scanpro-settings',
            __('Bulk Optimizer', 'scanpro-optimizer'),
            __('Bulk Optimizer', 'scanpro-optimizer'),
            'manage_options',
            'scanpro-bulk-optimizer',
            array($this, 'display_bulk_optimizer_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings()
    {
        register_setting(
            'scanpro_settings',
            'scanpro_api_key',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        register_setting(
            'scanpro_settings',
            'scanpro_auto_compress',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'yes',
            )
        );

        register_setting(
            'scanpro_settings',
            'scanpro_compression_quality',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'medium',
            )
        );

        add_settings_section(
            'scanpro_settings_section',
            __('API Settings', 'scanpro-optimizer'),
            array($this, 'settings_section_callback'),
            'scanpro_settings'
        );

        add_settings_field(
            'scanpro_api_key',
            __('API Key', 'scanpro-optimizer'),
            array($this, 'api_key_field_callback'),
            'scanpro_settings',
            'scanpro_settings_section'
        );

        add_settings_field(
            'scanpro_auto_compress',
            __('Auto-Compress Uploads', 'scanpro-optimizer'),
            array($this, 'auto_compress_field_callback'),
            'scanpro_settings',
            'scanpro_settings_section'
        );

        add_settings_field(
            'scanpro_compression_quality',
            __('Compression Quality', 'scanpro-optimizer'),
            array($this, 'compression_quality_field_callback'),
            'scanpro_settings',
            'scanpro_settings_section'
        );
    }

    /**
     * Settings section description.
     *
     * @since    1.0.0
     */
    public function settings_section_callback()
    {
        echo '<p>' . __('Enter your ScanPro API key to enable image compression and PDF conversion. Don\'t have a key? Visit <a href="https://scanpro.cc" target="_blank">scanpro.cc</a> to get one.', 'scanpro-optimizer') . '</p>';
    }

    /**
     * API key field callback.
     *
     * @since    1.0.0
     */
    public function api_key_field_callback()
    {
        $api_key = get_option('scanpro_api_key', '');
        ?>
        <input type="text" id="scanpro_api_key" name="scanpro_api_key" value="<?php echo esc_attr($api_key); ?>"
            class="regular-text" />
        <button type="button" id="scanpro_validate_api_key"
            class="button button-secondary"><?php _e('Validate Key', 'scanpro-optimizer'); ?></button>
        <p class="description"><?php _e('Enter your ScanPro API key here.', 'scanpro-optimizer'); ?></p>
        <div id="scanpro_api_key_validation_result"></div>
        <?php
    }

    /**
     * Auto-compress field callback.
     *
     * @since    1.0.0
     */
    public function auto_compress_field_callback()
    {
        $auto_compress = get_option('scanpro_auto_compress', 'yes');
        ?>
        <select id="scanpro_auto_compress" name="scanpro_auto_compress">
            <option value="yes" <?php selected($auto_compress, 'yes'); ?>><?php _e('Yes', 'scanpro-optimizer'); ?></option>
            <option value="no" <?php selected($auto_compress, 'no'); ?>><?php _e('No', 'scanpro-optimizer'); ?></option>
        </select>
        <p class="description">
            <?php _e('Automatically compress images when uploaded to the media library.', 'scanpro-optimizer'); ?>
        </p>
        <?php
    }

    /**
     * Compression quality field callback.
     *
     * @since    1.0.0
     */
    public function compression_quality_field_callback()
    {
        $compression_quality = get_option('scanpro_compression_quality', 'medium');
        ?>
        <select id="scanpro_compression_quality" name="scanpro_compression_quality">
            <option value="low" <?php selected($compression_quality, 'low'); ?>>
                <?php _e('Low - Maximum Compression', 'scanpro-optimizer'); ?>
            </option>
            <option value="medium" <?php selected($compression_quality, 'medium'); ?>>
                <?php _e('Medium - Balanced', 'scanpro-optimizer'); ?>
            </option>
            <option value="high" <?php selected($compression_quality, 'high'); ?>>
                <?php _e('High - Better Quality', 'scanpro-optimizer'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Select compression quality for uploaded images.', 'scanpro-optimizer'); ?></p>
        <?php
    }

    /**
     * Display the settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form submitted
        if (isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'scanpro-settings')) {
            update_option('scanpro_api_key', sanitize_text_field($_POST['scanpro_api_key']));
            update_option('scanpro_auto_compress', sanitize_text_field($_POST['scanpro_auto_compress']));
            update_option('scanpro_compression_quality', sanitize_text_field($_POST['scanpro_compression_quality']));

            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'scanpro-optimizer') . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="">
                <?php
                settings_fields('scanpro_settings');
                do_settings_sections('scanpro_settings');
                wp_nonce_field('scanpro-settings');
                submit_button();
                ?>
            </form>

            <?php if (get_option('scanpro_api_key')): ?>
                <div class="scanpro-stats-card">
                    <h2><?php _e('ScanPro Stats', 'scanpro-optimizer'); ?></h2>
                    <?php
                    $total_images = $this->get_optimized_images_count();
                    $total_saved = $this->get_total_bytes_saved();
                    ?>
                    <div class="scanpro-stats-grid">
                        <div class="scanpro-stat-item">
                            <span class="scanpro-stat-number"><?php echo number_format_i18n($total_images); ?></span>
                            <span class="scanpro-stat-label"><?php _e('Images Optimized', 'scanpro-optimizer'); ?></span>
                        </div>
                        <div class="scanpro-stat-item">
                            <span class="scanpro-stat-number"><?php echo size_format($total_saved, 2); ?></span>
                            <span class="scanpro-stat-label"><?php _e('Total Space Saved', 'scanpro-optimizer'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Display the PDF tools page.
     *
     * @since    1.0.0
     */
    public function display_pdf_tools_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key = get_option('scanpro_api_key', '');
        if (empty($api_key)) {
            echo '<div class="wrap"><h1>' . __('PDF Tools', 'scanpro-optimizer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . sprintf(__('Please enter your ScanPro API key in the <a href="%s">settings page</a> first.', 'scanpro-optimizer'), admin_url('admin.php?page=scanpro-settings')) . '</p></div></div>';
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="scanpro-tool-grid">
                <div class="scanpro-tool-card">
                    <h2><?php _e('Convert PDF to Word', 'scanpro-optimizer'); ?></h2>
                    <p><?php _e('Convert PDF documents to editable Word documents (.docx format).', 'scanpro-optimizer'); ?></p>
                    <form method="post" enctype="multipart/form-data" class="scanpro-pdf-form" data-output-format="docx">
                        <div class="scanpro-form-group">
                            <label for="pdf_file_to_word" class="scanpro-file-label">
                                <span class="dashicons dashicons-upload"></span>
                                <span
                                    class="scanpro-file-label-text"><?php _e('Choose PDF File', 'scanpro-optimizer'); ?></span>
                            </label>
                            <input type="file" name="pdf_file" id="pdf_file_to_word" accept=".pdf" required
                                class="scanpro-file-input" />
                            <div class="scanpro-selected-file"></div>
                        </div>
                        <div class="scanpro-form-group">
                            <button type="submit" class="button button-primary scanpro-convert-button">
                                <?php _e('Convert to Word', 'scanpro-optimizer'); ?>
                            </button>
                        </div>
                        <div class="scanpro-conversion-result"></div>
                    </form>
                </div>

                <div class="scanpro-tool-card">
                    <h2><?php _e('Convert PDF to Excel', 'scanpro-optimizer'); ?></h2>
                    <p><?php _e('Extract tables from PDF documents to Excel spreadsheets (.xlsx format).', 'scanpro-optimizer'); ?>
                    </p>
                    <form method="post" enctype="multipart/form-data" class="scanpro-pdf-form" data-output-format="xlsx">
                        <div class="scanpro-form-group">
                            <label for="pdf_file_to_excel" class="scanpro-file-label">
                                <span class="dashicons dashicons-upload"></span>
                                <span
                                    class="scanpro-file-label-text"><?php _e('Choose PDF File', 'scanpro-optimizer'); ?></span>
                            </label>
                            <input type="file" name="pdf_file" id="pdf_file_to_excel" accept=".pdf" required
                                class="scanpro-file-input" />
                            <div class="scanpro-selected-file"></div>
                        </div>
                        <div class="scanpro-form-group">
                            <button type="submit" class="button button-primary scanpro-convert-button">
                                <?php _e('Convert to Excel', 'scanpro-optimizer'); ?>
                            </button>
                        </div>
                        <div class="scanpro-conversion-result"></div>
                    </form>
                </div>

                <div class="scanpro-tool-card">
                    <h2><?php _e('Convert PDF to JPG', 'scanpro-optimizer'); ?></h2>
                    <p><?php _e('Convert PDF documents to JPG images.', 'scanpro-optimizer'); ?></p>
                    <form method="post" enctype="multipart/form-data" class="scanpro-pdf-form" data-output-format="jpg">
                        <div class="scanpro-form-group">
                            <label for="pdf_file_to_jpg" class="scanpro-file-label">
                                <span class="dashicons dashicons-upload"></span>
                                <span
                                    class="scanpro-file-label-text"><?php _e('Choose PDF File', 'scanpro-optimizer'); ?></span>
                            </label>
                            <input type="file" name="pdf_file" id="pdf_file_to_jpg" accept=".pdf" required
                                class="scanpro-file-input" />
                            <div class="scanpro-selected-file"></div>
                        </div>
                        <div class="scanpro-form-group">
                            <button type="submit" class="button button-primary scanpro-convert-button">
                                <?php _e('Convert to JPG', 'scanpro-optimizer'); ?>
                            </button>
                        </div>
                        <div class="scanpro-conversion-result"></div>
                    </form>
                </div>

                <div class="scanpro-tool-card">
                    <h2><?php _e('Convert to PDF', 'scanpro-optimizer'); ?></h2>
                    <p><?php _e('Convert various file formats (Word, Excel, PowerPoint, images) to PDF.', 'scanpro-optimizer'); ?>
                    </p>
                    <form method="post" enctype="multipart/form-data" class="scanpro-pdf-form" data-output-format="pdf">
                        <div class="scanpro-form-group">
                            <label for="file_to_pdf" class="scanpro-file-label">
                                <span class="dashicons dashicons-upload"></span>
                                <span class="scanpro-file-label-text"><?php _e('Choose File', 'scanpro-optimizer'); ?></span>
                            </label>
                            <input type="file" name="pdf_file" id="file_to_pdf" accept=".docx,.xlsx,.pptx,.jpg,.jpeg,.png"
                                required class="scanpro-file-input" />
                            <div class="scanpro-selected-file"></div>
                        </div>
                        <div class="scanpro-form-group">
                            <button type="submit" class="button button-primary scanpro-convert-button">
                                <?php _e('Convert to PDF', 'scanpro-optimizer'); ?>
                            </button>
                        </div>
                        <div class="scanpro-conversion-result"></div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display the bulk optimizer page.
     *
     * @since    1.0.0
     */
    public function display_bulk_optimizer_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key = get_option('scanpro_api_key', '');
        if (empty($api_key)) {
            echo '<div class="wrap"><h1>' . __('Bulk Optimizer', 'scanpro-optimizer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . sprintf(__('Please enter your ScanPro API key in the <a href="%s">settings page</a> first.', 'scanpro-optimizer'), admin_url('admin.php?page=scanpro-settings')) . '</p></div></div>';
            return;
        }

        // Get unoptimized images
        $unoptimized_images = $this->get_unoptimized_images();
        $total_unoptimized = count($unoptimized_images);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="scanpro-stats-card">
                <h2><?php _e('Optimization Summary', 'scanpro-optimizer'); ?></h2>
                <?php
                $total_images = $this->get_optimized_images_count();
                $total_saved = $this->get_total_bytes_saved();
                ?>
                <div class="scanpro-stats-grid">
                    <div class="scanpro-stat-item">
                        <span class="scanpro-stat-number"><?php echo number_format_i18n($total_images); ?></span>
                        <span class="scanpro-stat-label"><?php _e('Images Optimized', 'scanpro-optimizer'); ?></span>
                    </div>
                    <div class="scanpro-stat-item">
                        <span class="scanpro-stat-number"><?php echo size_format($total_saved, 2); ?></span>
                        <span class="scanpro-stat-label"><?php _e('Total Space Saved', 'scanpro-optimizer'); ?></span>
                    </div>
                    <div class="scanpro-stat-item">
                        <span class="scanpro-stat-number"><?php echo number_format_i18n($total_unoptimized); ?></span>
                        <span class="scanpro-stat-label"><?php _e('Images Remaining', 'scanpro-optimizer'); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($total_unoptimized > 0): ?>
                <div class="scanpro-bulk-actions">
                    <button type="button" id="scanpro-bulk-optimize" class="button button-primary">
                        <?php _e('Optimize All Images', 'scanpro-optimizer'); ?>
                    </button>

                    <div id="scanpro-bulk-progress" style="display: none;">
                        <div class="scanpro-progress-bar-container">
                            <div class="scanpro-progress-bar"></div>
                        </div>
                        <div class="scanpro-progress-text">
                            <span id="scanpro-progress-current">0</span> / <span
                                id="scanpro-progress-total"><?php echo $total_unoptimized; ?></span>
                            (<span id="scanpro-progress-percent">0%</span>)
                        </div>
                    </div>
                </div>

                <table class="widefat striped" id="scanpro-unoptimized-images">
                    <thead>
                        <tr>
                            <th><?php _e('Image', 'scanpro-optimizer'); ?></th>
                            <th><?php _e('Size', 'scanpro-optimizer'); ?></th>
                            <th><?php _e('Actions', 'scanpro-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unoptimized_images as $image): ?>
                            <tr data-id="<?php echo esc_attr($image['id']); ?>">
                                <td>
                                    <div class="scanpro-image-info">
                                        <img src="<?php echo esc_url($image['thumbnail']); ?>"
                                            alt="<?php echo esc_attr($image['title']); ?>" width="60" height="60" />
                                        <div>
                                            <strong><?php echo esc_html($image['title']); ?></strong>
                                            <div><?php echo esc_html($image['filename']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo size_format($image['filesize'], 2); ?></td>
                                <td>
                                    <button type="button" class="button scanpro-optimize-single"
                                        data-id="<?php echo esc_attr($image['id']); ?>">
                                        <?php _e('Optimize', 'scanpro-optimizer'); ?>
                                    </button>
                                    <span class="scanpro-optimization-result"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><?php _e('All images are optimized!', 'scanpro-optimizer'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     * @param    array    $links    Plugin action links.
     * @return   array              Updated action links.
     */
    public function add_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=scanpro-settings') . '">' . __('Settings', 'scanpro-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add a compress button to the media library.
     *
     * @since    1.0.0
     * @param    array    $form_fields    An array of attachment form fields.
     * @param    WP_Post  $post           The WP_Post attachment object.
     * @return   array                    The filtered form fields.
     */
    public function add_compress_button($form_fields, $post)
    {
        if (!wp_attachment_is_image($post->ID) && get_post_mime_type($post->ID) !== 'application/pdf') {
            return $form_fields;
        }

        $is_optimized = get_post_meta($post->ID, '_scanpro_optimized', true);

        if ($is_optimized) {
            $original_size = get_post_meta($post->ID, '_scanpro_original_size', true);
            $compressed_size = get_post_meta($post->ID, '_scanpro_compressed_size', true);
            $savings_percentage = get_post_meta($post->ID, '_scanpro_savings_percentage', true);

            if ($original_size && $compressed_size) {
                $saved = $original_size - $compressed_size;

                $form_fields['scanpro_optimizer'] = array(
                    'label' => __('ScanPro', 'scanpro-optimizer'),
                    'input' => 'html',
                    'html' => sprintf(
                        '<div class="scanpro-optimized-info">
                            <div class="scanpro-optimization-badge">
                                <span class="dashicons dashicons-yes-alt"></span> %s
                            </div>
                            <div class="scanpro-optimization-details">
                                <div>%s: %s</div>
                                <div>%s: %s (%s%%)</div>
                            </div>
                            <button type="button" class="button scanpro-reoptimize-button" data-id="%d">%s</button>
                        </div>',
                        __('Optimized', 'scanpro-optimizer'),
                        __('Saved', 'scanpro-optimizer'),
                        size_format($saved, 2),
                        __('Reduced', 'scanpro-optimizer'),
                        size_format($compressed_size, 2),
                        $savings_percentage,
                        $post->ID,
                        __('Re-optimize', 'scanpro-optimizer')
                    )
                );
            } else {
                $form_fields['scanpro_optimizer'] = array(
                    'label' => __('ScanPro', 'scanpro-optimizer'),
                    'input' => 'html',
                    'html' => sprintf(
                        '<div class="scanpro-optimized-info">
                            <div class="scanpro-optimization-badge">
                                <span class="dashicons dashicons-yes-alt"></span> %s
                            </div>
                            <button type="button" class="button scanpro-reoptimize-button" data-id="%d">%s</button>
                        </div>',
                        __('Optimized', 'scanpro-optimizer'),
                        $post->ID,
                        __('Re-optimize', 'scanpro-optimizer')
                    )
                );
            }
        } else {
            $form_fields['scanpro_optimizer'] = array(
                'label' => __('ScanPro', 'scanpro-optimizer'),
                'input' => 'html',
                'html' => sprintf(
                    '<button type="button" class="button scanpro-optimize-button" data-id="%d">%s</button>
                    <span class="scanpro-optimize-status"></span>',
                    $post->ID,
                    __('Optimize', 'scanpro-optimizer')
                )
            );
        }

        return $form_fields;
    }

    /**
     * Handle AJAX request to compress an image.
     *
     * @since    1.0.0
     */
    public function ajax_compress_image()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scanpro-ajax-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'scanpro-optimizer')));
        }

        // Check for attachment ID
        if (!isset($_POST['attachment_id']) || !$_POST['attachment_id']) {
            wp_send_json_error(array('message' => __('No attachment ID provided.', 'scanpro-optimizer')));
        }

        $attachment_id = intval($_POST['attachment_id']);
        $attachment = get_post($attachment_id);

        if (!$attachment) {
            wp_send_json_error(array('message' => __('Invalid attachment ID.', 'scanpro-optimizer')));
        }

        // Get file path
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(array('message' => __('File not found.', 'scanpro-optimizer')));
        }

        // Get compression quality
        $quality = isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : get_option('scanpro_compression_quality', 'medium');

        // Compress the image
        $result = $this->api->compress_image($file_path, $quality);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Replace the original file with the compressed version
        $original_size = filesize($file_path);

        if (copy($result['path'], $file_path)) {
            // Delete temporary file
            @unlink($result['path']);

            // Update metadata
            update_post_meta($attachment_id, '_scanpro_optimized', true);
            update_post_meta($attachment_id, '_scanpro_original_size', $original_size);
            update_post_meta($attachment_id, '_scanpro_compressed_size', $result['size']);
            update_post_meta($attachment_id, '_scanpro_savings_percentage', $result['savings_percentage']);

            // Regenerate thumbnails if it's an image
            if (wp_attachment_is_image($attachment_id)) {
                $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $metadata);
            }

            $saved = $original_size - $result['size'];
            $savings_percentage = str_replace('%', '', $result['savings_percentage']);

            wp_send_json_success(array(
                'message' => __('Image compressed successfully!', 'scanpro-optimizer'),
                'original_size' => size_format($original_size, 2),
                'compressed_size' => size_format($result['size'], 2),
                'saved' => size_format($saved, 2),
                'savings_percentage' => $savings_percentage,
            ));
        } else {
            // Delete temporary file
            @unlink($result['path']);

            wp_send_json_error(array('message' => __('Failed to replace original file with compressed version.', 'scanpro-optimizer')));
        }
    }
    /**
     * Handle AJAX request to convert a PDF.
     *
     * @since    1.0.0
     */
    public function ajax_convert_pdf()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scanpro-ajax-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'scanpro-optimizer')));
        }

        // Check for file
        if (!isset($_FILES['pdf_file']) || !$_FILES['pdf_file']) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'scanpro-optimizer')));
        }

        // Check for output format
        if (!isset($_POST['output_format']) || !$_POST['output_format']) {
            wp_send_json_error(array('message' => __('No output format specified.', 'scanpro-optimizer')));
        }

        $output_format = sanitize_text_field($_POST['output_format']);

        // Handle file upload
        $file = $_FILES['pdf_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            );

            $error_message = isset($upload_error_messages[$file['error']])
                ? $upload_error_messages[$file['error']]
                : 'Unknown upload error';

            error_log('ScanPro Upload Error: ' . $error_message);
            wp_send_json_error(array('message' => __('File upload failed: ', 'scanpro-optimizer') . $error_message));
        }

        // Check file size
        if ($file['size'] > 25 * 1024 * 1024) { // 15MB limit
            error_log('ScanPro: File too large - ' . $file['size'] . ' bytes');
            wp_send_json_error(array('message' => __('File is too large (max 15MB)', 'scanpro-optimizer')));
        }

        // Validate file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $valid_extensions = array('pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png');

        if (!in_array($file_extension, $valid_extensions)) {
            error_log('ScanPro: Invalid file extension - ' . $file_extension);
            wp_send_json_error(array('message' => __('Invalid file type. Allowed types: PDF, DOCX, XLSX, PPTX, JPG, PNG', 'scanpro-optimizer')));
        }

        // Check if converting to the same format
        if ($file_extension === $output_format) {
            error_log('ScanPro: Same input and output format - ' . $file_extension);
            wp_send_json_error(array('message' => __('Input and output formats cannot be the same', 'scanpro-optimizer')));
        }

        // Create a temporary file WITH THE CORRECT EXTENSION
        $temp_file = wp_tempnam($file['name']);
        $temp_file_with_ext = $temp_file . '.' . $file_extension;

        // Log file information
        error_log('ScanPro: Original filename: ' . $file['name']);
        error_log('ScanPro: Temp file path: ' . $temp_file);
        error_log('ScanPro: Temp file with extension: ' . $temp_file_with_ext);

        // Move uploaded file to temporary location WITH EXTENSION
        if (!move_uploaded_file($file['tmp_name'], $temp_file_with_ext)) {
            error_log('ScanPro: Failed to move uploaded file');
            wp_send_json_error(array('message' => __('Failed to process uploaded file', 'scanpro-optimizer')));
        }

        // Log conversion attempt
        error_log('ScanPro: Converting file - ' . $file['name'] . ' (' . $file_extension . ' to ' . $output_format . ')');

        // Convert the file - USING THE FILE WITH EXTENSION
        $result = $this->api->convert_pdf($temp_file_with_ext, $output_format);

        // Delete the temporary files
        @unlink($temp_file);
        @unlink($temp_file_with_ext);

        if (is_wp_error($result)) {
            error_log('ScanPro Conversion Error: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Get file details
        $file_url = $result['url'];
        $file_path = $result['path'];
        $file_name = $result['filename'];

        // If we want to add the file to the media library
        if (isset($_POST['add_to_media']) && $_POST['add_to_media'] === 'yes') {
            // Add to media library
            $attachment_id = $this->add_file_to_media_library($file_path, $result['originalName']);

            if (is_wp_error($attachment_id)) {
                @unlink($file_path);
                error_log('ScanPro: Failed to add file to media library - ' . $attachment_id->get_error_message());
                wp_send_json_error(array('message' => $attachment_id->get_error_message()));
            }

            $file_url = wp_get_attachment_url($attachment_id);
            @unlink($file_path);

            wp_send_json_success(array(
                'message' => __('File converted and added to media library!', 'scanpro-optimizer'),
                'file_url' => $file_url,
                'attachment_id' => $attachment_id,
            ));
        } else {
            // Provide direct download link
            wp_send_json_success(array(
                'message' => __('File converted successfully!', 'scanpro-optimizer'),
                'file_url' => $file_url,
                'file_name' => $file_name,
            ));
        }
    }

    /**
     * Handle AJAX request to validate API key.
     *
     * @since    1.0.0
     */
    public function ajax_validate_api_key()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scanpro-ajax-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'scanpro-optimizer')));
        }

        // Check for API key
        if (!isset($_POST['api_key']) || !$_POST['api_key']) {
            wp_send_json_error(array('message' => __('No API key provided.', 'scanpro-optimizer')));
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        // Validate API key
        $result = $this->api->validate_api_key($api_key);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('API key is valid!', 'scanpro-optimizer')));
    }

    /**
     * Compress an image when it's uploaded to the media library.
     *
     * @since    1.0.0
     * @param    array    $file    The uploaded file data.
     * @return   array             The file data, possibly modified.
     */
    public function compress_uploaded_image($file)
    {
        // Skip compression if auto-compress is disabled
        if (get_option('scanpro_auto_compress', 'yes') !== 'yes') {
            return $file;
        }

        // Skip if not an image or PDF
        $mime_type = $file['type'];
        if (!in_array($mime_type, array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'))) {
            return $file;
        }

        // Skip if API key is not set
        $api_key = get_option('scanpro_api_key', '');
        if (empty($api_key)) {
            return $file;
        }

        // Skip if file doesn't exist
        if (!file_exists($file['file'])) {
            return $file;
        }

        // Get compression quality
        $quality = get_option('scanpro_compression_quality', 'medium');

        // Compress the image
        $result = $this->api->compress_image($file['file'], $quality);

        if (is_wp_error($result)) {
            error_log('ScanPro optimization error: ' . $result->get_error_message());
            return $file;
        }

        // Replace the original file with the compressed version
        $original_size = filesize($file['file']);

        if (copy($result['path'], $file['file'])) {
            // Delete temporary file
            @unlink($result['path']);

            // Store original size for later use
            update_option('scanpro_last_upload_original_size', $original_size);
            update_option('scanpro_last_upload_compressed_size', $result['size']);
            update_option('scanpro_last_upload_savings_percentage', $result['savings_percentage']);
        } else {
            // Delete temporary file
            @unlink($result['path']);
            error_log('ScanPro: Failed to replace original file with compressed version.');
        }

        return $file;
    }

    /**
     * Get the count of optimized images.
     *
     * @since    1.0.0
     * @return   int    The number of optimized images.
     */
    private function get_optimized_images_count()
    {
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_query' => array(
                array(
                    'key' => '_scanpro_optimized',
                    'value' => true,
                    'compare' => '='
                )
            ),
            'fields' => 'ids',
            'posts_per_page' => -1
        );

        $query = new WP_Query($args);
        return $query->post_count;
    }

    /**
     * Get the total bytes saved by optimization.
     *
     * @since    1.0.0
     * @return   int    The total bytes saved.
     */
    private function get_total_bytes_saved()
    {
        global $wpdb;

        $original_sizes = $wpdb->get_col(
            "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_scanpro_original_size'"
        );

        $compressed_sizes = $wpdb->get_col(
            "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_scanpro_compressed_size'"
        );

        $total_original = array_sum($original_sizes);
        $total_compressed = array_sum($compressed_sizes);

        return $total_original - $total_compressed;
    }

    /**
     * Get unoptimized images from the media library.
     *
     * @since    1.0.0
     * @return   array    Array of unoptimized image details.
     */
    private function get_unoptimized_images()
    {
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_scanpro_optimized',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $query = new WP_Query($args);
        $images = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $file_path = get_attached_file($id);

                if (!$file_path || !file_exists($file_path)) {
                    continue;
                }

                $filesize = filesize($file_path);
                $filename = basename($file_path);

                $images[] = array(
                    'id' => $id,
                    'title' => get_the_title(),
                    'filename' => $filename,
                    'filesize' => $filesize,
                    'thumbnail' => wp_get_attachment_image_url($id, 'thumbnail')
                );
            }
        }

        wp_reset_postdata();

        return $images;
    }

    /**
     * Add a file to the WordPress media library.
     *
     * @since    1.0.0
     * @param    string    $file_path     Path to the file.
     * @param    string    $original_name Original file name.
     * @return   int|WP_Error             Attachment ID or WP_Error.
     */
    private function add_file_to_media_library($file_path, $original_name)
    {
        // Get file type
        $file_type = wp_check_filetype(basename($file_path), null);

        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $original_name),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert attachment into the database
        $attach_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        // Generate metadata
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }
}