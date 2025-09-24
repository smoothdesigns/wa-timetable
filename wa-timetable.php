<?php

/**
 * WA Timetable (Tokyo 2025)
 *
 * @package           WA-Timetable
 * @author            Thomas Mirmo
 * @copyright         2025 Thomas Mirmo
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WA Timetable (Tokyo 2025)
 * Plugin URI:        https://github.com/smoothdesigns/wa-timetable
 * Description:       Displays the official 2025 World Athletics Championships timetable from Tokyo, Japan. Times are converted by default from Tokyo to Jamaican time. The plugin also updates automatically from a GitHub repository.
 * Version:           3.9.5
 * Requires at least: 5.3
 * Tested up to:      6.8.2
 * Requires PHP:      7.2
 * Author:            Thomas Mirmo
 * Author URI:        https://github.com/smoothdesigns
 * Text Domain:       wa-timetable
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Include the GitHub updater class for self-updates.
if (!class_exists('WAGitHubUpdater')) {
	require_once __DIR__ . '/includes/class-wa-github-updater.php';
}

// Include the new, separated classes.
require_once __DIR__ . '/includes/class-wa-timetable-data-extractor.php';
require_once __DIR__ . '/includes/class-wa-timetable-processor.php';
require_once __DIR__ . '/includes/class-wa-timetable-view.php';


/**
 * Main class for the WA Timetable Plugin.
 *
 * Orchestrates the plugin's core functionality, including shortcode registration,
 * script enqueuing, and the overall data flow.
 */
class WA_Timetable_Main_Plugin
{
	/**
	 * Constructor.
	 * Registers essential WordPress hooks.
	 */
	public function __construct()
	{
		add_shortcode('wa_timetable', [$this, 'render_shortcode']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_filter('style_loader_tag', [$this, 'add_integrity_and_crossorigin'], 10, 2);
		new WAGitHubUpdater(__FILE__, 'https://raw.githubusercontent.com/smoothdesigns/wa-timetable/main/');
	}

	/**
	 * Enqueues external and local CSS and JavaScript files.
	 */
	public function enqueue_scripts()
	{
		if (!wp_style_is('bootstrap', 'enqueued')) {
			wp_enqueue_style(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
				[],
				'5.3.8'
			);
		}

		if (!wp_style_is('font-awesome') || !wp_style_is('fontawesome')) {
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				[],
				'5.15.4'
			);
		}

		wp_enqueue_style('wa-timetable-style', plugin_dir_url(__FILE__) . 'assets/css/wa-timetable-styles.css', [], filemtime(plugin_dir_path(__FILE__) . 'assets/css/wa-timetable-styles.css'));

		if (!wp_script_is('bootstrap', 'enqueued')) {
			wp_enqueue_script(
				'bootstrap-js',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
				[],
				'5.3.8',
				true
			);
		}
	}

	/**
	 * Adds `integrity` and `crossorigin` attributes to the Font Awesome stylesheet link tag.
	 */
	public function add_integrity_and_crossorigin($tag, $handle)
	{
		if ('font-awesome' === $handle) {
			$integrity = 'sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==';
			$new_attributes = ' integrity="' . $integrity . '" crossorigin="anonymous"';
			$tag = preg_replace('/(\/>|\>)/', $new_attributes . '$1', $tag);
		}
		return $tag;
	}

	/**
	 * Renders the timetable by initiating the data extraction, processing, and view generation.
	 */
	public function render_shortcode()
	{
		$extractor = new WA_Timetable_Data_Extractor();
		$data = $extractor->extract();

		if (is_wp_error($data)) {
			return '<div class="wa-timetable-error">Error: ' . esc_html($data->get_error_message()) . '</div>';
		}

		if (empty($data)) {
			return '<div class="wa-timetable-message">Could not find timetable data.</div>';
		}

		$processor = new WA_Timetable_Processor();
		$processed_data = $processor->process($data);

		$view = new WA_Timetable_View();
		return $view->generate_html($processed_data);
	}
}

// Instantiate the main plugin class to begin execution.
new WA_Timetable_Main_Plugin();
