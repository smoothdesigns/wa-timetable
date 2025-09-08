<?php

/**
 * WA Timetable (Tokyo 2025)
 *
 * @package             WA-Timetable
 * @author              Thomas Mirmo
 * @copyright           2025 Thomas Mirmo
 * @license             GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:         WA Timetable (Tokyo 2025)
 * Plugin URI:          https://github.com/smoothdesigns/wa-timetable
 * Description:         Displays the official 2025 World Athletics Championships timetable from Tokyo, Japan. Times are converted by default from Tokyo to Jamaican time, with options for more time zones in the settings page.
 * Version:             2.0.2
 * Requires at least:   5.3
 * Tested up to:        6.8.2
 * Requires PHP:        7.2
 * Author:              Thomas Mirmo
 * Author URI:          https://github.com/smoothdesigns
 * Text Domain:         wa-timetable
 * License:             GPL v2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('WA_TIMETABLE_VERSION', '2.0.2');
define('WA_TIMETABLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WA_TIMETABLE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the necessary files
require_once WA_TIMETABLE_PLUGIN_DIR . 'includes/class-wa-github-updater.php';
require_once WA_TIMETABLE_PLUGIN_DIR . 'includes/wa-timetable-helpers.php';
require_once WA_TIMETABLE_PLUGIN_DIR . 'includes/class-wa-timetable-shortcode.php';

// Check if in the admin dashboard and include admin files
if (is_admin()) {
	require_once WA_TIMETABLE_PLUGIN_DIR . 'admin/class-wa-timetable-settings.php';
}

/**
 * Main plugin loader class.
 */
class WATimetable
{
	/**
	 * Constructor to set up the plugin.
	 */
	public function __construct()
	{
		// Instantiate the GitHub Updater
		new WAGitHubUpdater(__FILE__, 'https://raw.githubusercontent.com/smoothdesigns/wa-timetable/main');

		// Instantiate the shortcode class
		new WATimetableShortcode();

		// Settings page hooks (only if in admin)
		if (is_admin()) {
			new WATimetableSettings();
		}

		// Enqueue scripts and styles
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	/**
	 * Enqueues the necessary CSS and JavaScript files for the frontend.
	 */
	public function enqueue_scripts()
	{
		// Enqueue Bootstrap CSS (if not already enqueued)
		if (!wp_style_is('bootstrap', 'enqueued') && !wp_style_is('bootstrap', 'registered')) {
			wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', [], '5.3.0');
		}

		// Enqueue custom styles
		wp_enqueue_style('wa-timetable-styles', WA_TIMETABLE_PLUGIN_URL . 'css/wa-timetable-styles.css', ['bootstrap'], WA_TIMETABLE_VERSION);

		// Enqueue jQuery (if not already enqueued)
		if (!wp_script_is('jquery', 'enqueued') && !wp_script_is('jquery', 'registered')) {
			wp_enqueue_script('jquery', includes_url('/js/jquery/jquery.min.js'), [], null, true);
		}

		// Enqueue Bootstrap JS (if not already enqueued)
		if (!wp_script_is('bootstrap', 'enqueued') && !wp_script_is('bootstrap', 'registered')) {
			wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', ['jquery'], '5.3.0', true);
		}

		// Enqueue custom script for dynamic loading and functionality
		wp_enqueue_script('wa-timetable', WA_TIMETABLE_PLUGIN_URL . 'js/wa-timetable.js', ['jquery', 'bootstrap'], WA_TIMETABLE_VERSION, true);

		// Enqueue dynamic script if needed
		wp_enqueue_script('wa-timetable-dynamic', WA_TIMETABLE_PLUGIN_URL . 'js/wa-timetable-dynamic.js', ['jquery'], WA_TIMETABLE_VERSION, true);
	}
}

// Kick off the plugin
new WATimetable();
