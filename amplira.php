<?php
/*
Plugin Name: Amplira
Description: Content amplification tool
Version: 0.0.2
Author: Your Name
Text Domain: amplira
Licence: GPLv2 or later
*/

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/eyeonllc-wp-plugins/amplira',
    __FILE__,
    'amplira'
);
$myUpdateChecker->setBranch('master'); 

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AMPLIRA_VERSION', '0.0.1');
define('AMPLIRA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMPLIRA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add these function definitions
function activate_amplira() {
    // Activation code here
    // For example, create custom tables, set default options, etc.
    add_option('amplira_version', AMPLIRA_VERSION);
}

function deactivate_amplira() {
    // Deactivation code here
    // For example, clean up temporary data
}

// Load dependencies in correct order
require_once AMPLIRA_PLUGIN_DIR . 'includes/helpers/functions.php';
require_once AMPLIRA_PLUGIN_DIR . 'includes/class-amplira-ai.php';
require_once AMPLIRA_PLUGIN_DIR . 'includes/class-amplira-shortcode.php';
require_once AMPLIRA_PLUGIN_DIR . 'admin/class-amplira-admin.php';

// $ai = new Amplira_AI();
// $suggestion = $ai->find_nearby_cities('California', 6);
// amplira_debug($suggestion, false);

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'activate_amplira');
register_deactivation_hook(__FILE__, 'deactivate_amplira');

/**
 * Begins execution of the plugin
 */
function run_amplira() {
    $plugin = new Amplira_Admin();
    $plugin->run();
}

// Start the plugin
run_amplira(); 