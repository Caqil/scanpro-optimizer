<?php
/**
 * Plugin Name: ScanPro PDF & Image Optimizer
 * Plugin URI: https://scanpro.cc
 * Description: Compress images and convert PDF files using ScanPro API
 * Version: 1.0.0
 * Author: scanpro.cc
 * Author URI: https://scanpro.cc
 * Text Domain: scanpro-optimizer
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('SCANPRO_VERSION', '1.0.0');
define('SCANPRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCANPRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCANPRO_ADMIN_URL', admin_url('admin.php?page=scanpro-settings'));

/**
 * The code that runs during plugin activation.
 */
function activate_scanpro_optimizer()
{
    require_once SCANPRO_PLUGIN_DIR . 'includes/class-scanpro-activator.php';
    ScanPro_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_scanpro_optimizer()
{
    require_once SCANPRO_PLUGIN_DIR . 'includes/class-scanpro-deactivator.php';
    ScanPro_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_scanpro_optimizer');
register_deactivation_hook(__FILE__, 'deactivate_scanpro_optimizer');

/**
 * The core plugin class.
 */
require SCANPRO_PLUGIN_DIR . 'includes/class-scanpro-optimizer.php';

/**
 * Begins execution of the plugin.
 */
function run_scanpro_optimizer()
{
    $plugin = new ScanPro_Optimizer();
    $plugin->run();
}
run_scanpro_optimizer();