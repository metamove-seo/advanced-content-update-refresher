<?php
/**
 * Plugin Name: Advanced Content Update Refresher
 * Plugin URI: https://www.metamove.de
 * Description: Automatically refresh and update WordPress post publish or modified dates on flexible schedules to support structured content maintenance and SEO freshness signals.
 * Version: 1.0.0
 * Author: Metamove GmbH
 * Author URI: https://www.metamove.de
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-content-update-refresher
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Sicherheit
}

// Plugin-Konstanten
define('ACUR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACUR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Dateien laden
require_once ACUR_PLUGIN_DIR . 'includes/post-types.php';
require_once ACUR_PLUGIN_DIR . 'admin/settings.php';
require_once ACUR_PLUGIN_DIR . 'includes/cron-handler.php';

// Aktivierung & Deaktivierung
function acur_activate()
{
    acur_schedule_crons();
}
register_activation_hook(__FILE__, 'acur_activate');

function acur_deactivate() {
    acur_clear_crons();
}
register_deactivation_hook(__FILE__, 'acur_deactivate');

function acur_load_textdomain() {
    load_plugin_textdomain('advanced-content-update-refresher', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

add_action('plugins_loaded', 'acur_load_textdomain');
add_filter( 'cron_schedules', 'acur_add_schedules' );
add_action( 'init', 'acur_schedule_crons', 1000);

add_filter( 'rank_math/sitemap/enable_caching', '__return_false');
