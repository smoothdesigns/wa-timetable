<?php

/**
 * WA Timetable (Tokyo 2025)
 *
 * @package 		WA-Timetable
 * @author 		Thomas Mirmo
 * @copyright 2025 Thomas Mirmo
 * @license 		GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: 		WA Timetable (Tokyo 2025)
 * Plugin URI: 	https://github.com/smoothdesigns/wa-timetable
 * Description: 		Displays the official 2025 World Athletics Championships timetable from Tokyo, Japan. Times are converted by default from Tokyo to Jamaican time.
 * Version: 		3.9.4
 * Requires at least: 		5.3
 * Tested up to: 6.8.2
 * Requires PHP: 7.2
 * Author: 	Thomas Mirmo
 * Author URI: 	 https://github.com/smoothdesigns
 * Text Domain: 		wa-timetable
 * License: 		GPL v2 or later
 * License URI: 		http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly to prevent direct file access.
if (!defined('ABSPATH')) {
	exit;
}

// Include the GitHub updater class for self-updates.
if (!class_exists('WAGitHubUpdater')) {
	require_once __DIR__ . '/includes/class-wa-github-updater.php';
}

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
		// Register the shortcode for displaying the timetable.
		add_shortcode('wa_timetable', [$this, 'render_shortcode']);

		// Enqueue public-facing styles and scripts.
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

		// Add integrity and crossorigin attributes to the Font Awesome stylesheet for security.
		add_filter('style_loader_tag', [$this, 'add_integrity_and_crossorigin'], 10, 2);

		// Instantiate the updater to enable automatic plugin updates from GitHub.
		// CORRECTED URL: Changed from `github.com/smoothdesigns/wa-timetable/trunk/` to `raw.githubusercontent.com/smoothdesigns/wa-timetable/main/`
		new WAGitHubUpdater(__FILE__, 'https://raw.githubusercontent.com/smoothdesigns/wa-timetable/main/');
	}

	/**
	 * Enqueues external and local CSS and JavaScript files.
	 *
	 * Checks for existing enqueued dependencies to prevent conflicts with other themes or plugins.
	 */
	public function enqueue_scripts()
	{
		// Enqueue Bootstrap 5 CSS if it's not already loaded.
		if (!wp_style_is('bootstrap', 'enqueued')) {
			wp_enqueue_style(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
				[],
				'5.3.8'
			);
		}

		// Enqueue Font Awesome for icons if it's not already loaded.
		if (!wp_style_is('font-awesome') || !wp_style_is('fontawesome')) {
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				[],
				'5.15.4'
			);
		}

		// Enqueue the custom plugin stylesheet with a filemtime-based version for cache busting.
		wp_enqueue_style('wa-timetable-style', plugin_dir_url(__FILE__) . 'assets/css/wa-timetable-styles.css', [], filemtime(plugin_dir_path(__FILE__) . 'assets/css/wa-timetable-styles.css'));

		// Enqueue Bootstrap 5 JS if it's not already loaded.
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
	 *
	 * @param string $tag The HTML link tag.
	 * @param string $handle The stylesheet handle.
	 * @return string The modified HTML link tag with integrity attributes.
	 */
	public function add_integrity_and_crossorigin($tag, $handle)
	{
		if ('font-awesome' === $handle) {
			$integrity = 'sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==';
			$new_attributes = ' integrity="' . $integrity . '" crossorigin="anonymous"';
			// Use a regular expression to insert attributes before the closing bracket.
			$tag = preg_replace('/(\/>|\>)/', $new_attributes . '$1', $tag);
		}
		return $tag;
	}

	/**
	 * Renders the timetable by initiating the data extraction, processing, and view generation.
	 *
	 * @return string The complete HTML output for the timetable.
	 */
	public function render_shortcode()
	{
		// Step 1: Data Extraction
		$extractor = new WA_Timetable_Data_Extractor();
		$data = $extractor->extract();

		if (is_wp_error($data)) {
			return '<div class="wa-timetable-error">Error: ' . esc_html($data->get_error_message()) . '</div>';
		}

		if (empty($data)) {
			return '<div class="wa-timetable-message">Could not find timetable data.</div>';
		}

		// Step 2: Data Processing (grouping by date and session)
		$processor = new WA_Timetable_Processor();
		$processed_data = $processor->process($data);

		// Step 3: View Generation (Bootstrap tabs and custom layout)
		$view = new WA_Timetable_View();
		return $view->generate_html($processed_data, $data);
	}
}

//================================================================================
// DATA EXTRACTION
//================================================================================

/**
 * Handles all data fetching and extraction logic from the source website.
 */
class WA_Timetable_Data_Extractor
{
	/**
	 * Extracts the '__NEXT_DATA__' JSON from the source website's HTML.
	 *
	 * @return object|WP_Error The decoded JSON object or a WP_Error on failure.
	 */
	public function extract()
	{
		$url = 'https://worldathletics.org/competitions/world-athletics-championships/tokyo25/timetable';

		$response = wp_remote_get($url, [
			'timeout' => 15,
			'sslverify' => false,
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$body = wp_remote_retrieve_body($response);
		if (empty($body)) {
			return new WP_Error('empty_body', 'Could not retrieve timetable content from the URL.');
		}

		// Use a regular expression to find the script tag and extract its content.
		$pattern = '/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s';
		$matches = [];

		if (preg_match($pattern, $body, $matches) && isset($matches[1])) {
			$json_string = $matches[1];
			$data = json_decode($json_string);

			if ($data === null) {
				return new WP_Error('json_decode_error', 'Failed to decode the JSON data.');
			}

			if (!isset($data->props->pageProps->phases)) {
				return new WP_Error('data_path_invalid', 'The expected data path within the JSON is invalid.');
			}

			return $data;
		} else {
			return new WP_Error('json_not_found', 'The data script tag was not found in the page source.');
		}
	}
}

//================================================================================
// DATA PROCESSING
//================================================================================

/**
 * Handles all data processing, including timezone conversion and event grouping.
 */
class WA_Timetable_Processor
{
	/**
	 * Processes raw data into a structured format grouped by date and session.
	 *
	 * This method handles grouped qualification events, timezone conversions, and sorts events.
	 *
	 * @param object $data The decoded JSON data.
	 * @return array The processed data, grouped by day and session.
	 */
	public function process($data)
	{
		$event_timetable = $data->props->pageProps->phases;

		$expanded_timetable = [];

		// Handle grouped qualification events
		foreach ($event_timetable as $event) {
			// Check if the event has units and is a grouped qualification event.
			if (!empty($event->units) && isset($event->units[0]->unitType) && $event->units[0]->unitType === 'G') {
				// Group units by their start time.
				$units_by_time = [];
				foreach ($event->units as $unit) {
					$start_time = $unit->startDateTime;
					if (!isset($units_by_time[$start_time])) {
						$units_by_time[$start_time] = [];
					}
					$units_by_time[$start_time][] = $unit;
				}

				foreach ($units_by_time as $start_time => $units) {
					$unit_event = clone $event;
					$unit_event->phaseDateAndTime = $start_time;
					$unit_event->phaseUrlSlug = sanitize_title($event->phaseName);

					// If multiple units start at the same time, it's a concurrent event.
					if (count($units) > 1) {
						$unit_event->phaseName = $event->phaseName;
					} else {
						$unit_event->phaseName = $event->phaseName . ' - Group ' . ($units[0]->unitName ?? '');
					}

					$expanded_timetable[] = $unit_event;
				}
			} else {
				// If it's not a grouped event, add the original phase.
				$expanded_timetable[] = $event;
			}
		}

		// Reassign the processed list to event_timetable for the next steps.
		$event_timetable = $expanded_timetable;
		$grouped_data = [];

		// Define the timezones for conversion.
		$tokyo_timezone = new DateTimeZone('Asia/Tokyo');
		$jamaica_timezone = new DateTimeZone('America/Jamaica');

		// Sort events by date and time to ensure a correct chronological display.
		usort($event_timetable, function ($a, $b) {
			$dateA = new DateTime($a->phaseDateAndTime ?? '');
			$dateB = new DateTime($b->phaseDateAndTime ?? '');
			return $dateA <=> $dateB;
		});

		// Group events by day and session.
		$current_date = '';
		$day_number = 1;

		foreach ($event_timetable as $event) {
			// Create a DateTime object in the source (Tokyo) timezone.
			$event_datetime_tokyo = new DateTime($event->phaseDateAndTime ?? '', $tokyo_timezone);

			// Convert to the target (Jamaica) timezone.
			$event_datetime_jamaica = $event_datetime_tokyo->setTimezone($jamaica_timezone);
			$event->jamaica_datetime_object = $event_datetime_jamaica;

			// Convert end time for live checking, if available.
			if (isset($event->phaseEndDateAndTime) && !empty($event->phaseEndDateAndTime)) {
				$event_end_datetime_tokyo = new DateTime($event->phaseEndDateAndTime, $tokyo_timezone);
				$event->jamaica_end_datetime_object = $event_end_datetime_tokyo->setTimezone($jamaica_timezone);
			} else {
				$event->jamaica_end_datetime_object = null;
			}

			$event_date_key = $event_datetime_jamaica->format('Y-m-d');

			// Swap session names to match the new timezone.
			$session_name = $event->phaseSessionName ?? 'No Session';
			if ($session_name === 'Morning Session') {
				$session_name = 'Evening Session';
			} elseif ($session_name === 'Evening Session') {
				$session_name = 'Morning Session';
			}

			// Update day labels for the tabs.
			if ($event_date_key !== $current_date) {
				$current_date = $event_date_key;

				$start_date = new DateTime('2025-09-12', $jamaica_timezone);
				$event_date_obj = new DateTime($event_date_key, $jamaica_timezone);
				$interval = $start_date->diff($event_date_obj);
				$day_number = $interval->days + 1;

				$day_label = 'Day ' . $day_number . ' - ' . $event_datetime_jamaica->format('M d');
			}

			$grouped_data[$day_label][$session_name][] = $event;
		}

		return $grouped_data;
	}
}

//================================================================================
// VIEW GENERATION
//================================================================================

/**
 * Handles all view logic and HTML generation for the timetable display.
 */
class WA_Timetable_View
{
	/**
	 * Generates the complete HTML output for the timetable.
	 *
	 * @param array $phases The processed timetable data, grouped by date.
	 * @param object $full_data The full data object from __NEXT_DATA__ for slug lookups.
	 * @return string The generated HTML.
	 */
	public function generate_html($phases, $full_data)
	{
		// Determine the current date in Jamaica to set the active tab.
		$jamaica_timezone = new DateTimeZone('America/Jamaica');
		$current_date_jamaica = new DateTime('now', $jamaica_timezone);
		$current_date_formatted = $current_date_jamaica->format('M d');
		$current_timestamp = $current_date_jamaica->getTimestamp();

		// Find the active tab ID based on the current date.
		$active_tab_id = null;
		foreach (array_keys($phases) as $date_label) {
			$date_part = explode(' - ', $date_label)[1] ?? '';
			if ($date_part === $current_date_formatted) {
				$active_tab_id = sanitize_title($date_label);
				break;
			}
		}

		// Default to the first tab if the current day isn't found.
		if (is_null($active_tab_id)) {
			$date_labels = array_keys($phases);
			$first_date_label = reset($date_labels);
			$active_tab_id = sanitize_title($first_date_label);
		}

		$output = '<div class="wa-timetable-container">';

		// Generate the Bootstrap tabs navigation.
		$output .= '<ul class="nav nav-pills nav-justified my-0" id="timetableTabs" role="tablist">';
		foreach (array_keys($phases) as $date_label) {
			$tab_id = sanitize_title($date_label);
			$parts = explode(' - ', $date_label);
			$date_part = $parts[1] ?? '';
			$day_part = $parts[0] ?? '';

			$active_class = ($tab_id === $active_tab_id) ? 'active' : '';
			$day_class = '';

			if ($date_part === $current_date_formatted) {
				$day_part = 'TODAY';
				$day_class = 'today-text';
			}

			$output .= '<li class="nav-item day-item my-0">';
			$output .= '<a class="nav-link ' . $active_class . '" id="' . $tab_id . '-tab" data-bs-toggle="tab" data-bs-target="#' . $tab_id . '" type="button" role="tab" aria-controls="' . $tab_id . '" aria-selected="' . ($active_class === 'active' ? 'true' : 'false') . '">';
			$output .= '<div class="d-flex flex-column">';
			$output .= '<span class="' . esc_attr($day_class) . '" style="font-size: 10px; font-weight: bold; text-transform: uppercase;">' . esc_html($day_part) . '</span>';
			$output .= '<span style="font-size: 14px; font-weight: bold;">' . esc_html($date_part) . '</span>';
			$output .= '</div>';
			$output .= '</a>';
			$output .= '</li>';
		}
		$output .= '</ul>';

		// Generate the tab content panes.
		$output .= '<div class="tab-content mt-3">';
		foreach ($phases as $date_label => $sessions_for_date) {
			$tab_id = sanitize_title($date_label);
			$active_class = ($tab_id === $active_tab_id) ? 'show active' : '';

			$output .= '<div class="tab-pane fade ' . $active_class . '" id="' . $tab_id . '" role="tabpanel" aria-labelledby="' . $tab_id . '-tab">';

			// Accordion for sessions within the day.
			$output .= '<div class="accordion" id="accordion-' . $tab_id . '">';
			foreach ($sessions_for_date as $session_name => $events_for_session) {
				$session_id = sanitize_title($date_label . '-' . $session_name);

				// Determine if the session is finished.
				$all_results_published = true;
				foreach ($events_for_session as $event) {
					if (!$this->check_units_for_status($event, 'isResultPublished')) {
						$all_results_published = false;
						break;
					}
				}

				$show_class = $all_results_published ? '' : 'show';
				$expanded_state = $all_results_published ? 'false' : 'true';

				$output .= '<div class="accordion-item border-0">';
				$output .= '<h2 class="accordion-header p-0 my-0" id="heading-' . $session_id . '">';
				$output .= '<button class="accordion-button ' . ($all_results_published ? 'collapsed' : '') . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $session_id . '" aria-expanded="' . $expanded_state . '" aria-controls="collapse-' . $session_id . '">';
				$output .= '<div class="session-wrapper d-flex flex-column lh-1 gap-1">';
				$output .= '<span class="session-name" style="font-size: 20px; text-transform: uppercase;">' . esc_html($session_name);
				if ($all_results_published) {
					$output .= '<span style="background-color: #dc3545; color: white; padding: 2px 8px; font-size: 10px; border-radius: 9999px; font-weight: bold; margin-left: 8px;">ENDED</span>';
				}
				$output .= '</span>';
				$output .= '<span class="event-count" style="font-size: 14px;"><i class="far fa-calendar-alt me-2"></i>' . count($events_for_session) . ' event sections</span>';
				$output .= '</div>';
				$output .= '</button>';
				$output .= '</h2>';
				$output .= '<div id="collapse-' . $session_id . '" class="accordion-collapse collapse ' . $show_class . '" aria-labelledby="heading-' . $session_id . '">';
				$output .= '<div class="accordion-body p-0">';
				$output .= '<div class="events-list">';

				foreach ($events_for_session as $event) {
					$disciplineName = $event->disciplineName ?? 'N/A';
					$sexName = $event->sexName ?? 'N/A';
					$phaseName = $event->phaseName ?? 'N/A';
					$event_id = $event->id ?? uniqid();
					$phase_order = $event->phaseOrder ?? uniqid();

					if ($sexName === 'Men' || $sexName === 'Women') {
						$sexName = $sexName . '\'s';
					}

					$bgColor = 'transparent';
					$is_final = strpos($phaseName, 'Final');
					$is_qualification = strpos($phaseName, 'Qualification');
					$is_preliminary = strpos($phaseName, 'Preliminary');
					$is_decathlon = strpos($phaseName, 'Decathlon');
					$is_heptathlon = strpos($phaseName, 'Heptathlon');
					$is_heats = strpos($phaseName, 'Heats');

					if ($is_final !== false) {
						$bgColor = '#fbd1bb';
					} elseif ($is_qualification !== false || $is_preliminary !== false || $is_decathlon !== false || $is_heptathlon !== false) {
						$bgColor = '#dfd0fa';
					} elseif ($is_heats !== false) {
						$bgColor = '#c2e9ed';
					}

					$discipline_slug = '';
					if (isset($full_data->props->apolloState->data->{'Discipline:' . $event->disciplineCode})) {
						$discipline_slug = $full_data->props->apolloState->data->{'Discipline:' . $event->disciplineCode}->Slug;
					}

					$sanitized_sexName = strtolower(str_replace('\'s', '', $sexName));
					$sanitized_phase_url_slug = $event->phaseUrlSlug ?? sanitize_title($phaseName);

					$base_url = 'https://worldathletics.org/competitions/world-athletics-championships/tokyo25/results/';

					// Conditionally build URL based on the event phase type (decathlon/heptathlon have a different slug structure).
					if ($is_decathlon !== false || $is_heptathlon !== false) {
						$url_path = $sanitized_sexName . '/' . $sanitized_phase_url_slug . '/' . $discipline_slug;
					} else {
						$url_path = $sanitized_sexName . '/' . $discipline_slug . '/' . $sanitized_phase_url_slug;
					}

					$startlist_url = $base_url . $url_path . '/startlist';
					$results_url = $base_url . $url_path . '/results';
					$summary_url = $base_url . $url_path . '/summary';

					$output .= '<div class="event-item" id="event-' . esc_attr($event_id) . '-' . esc_attr($phase_order) . '">';
					$output .= '<div class="event-item-content">';
					$output .= '<div class="event-details-left">';
					$output .= '<div class="event-name">' . esc_html($sexName) . ' ' . esc_html($disciplineName) . '</div>';
					$output .= '<div class="event-phase-container"><span style="border-radius: 4px; padding: 2px 6px; line-height: 1; background-color: ' . esc_attr($bgColor) . ';">' . esc_html($phaseName) . '</span></div>';
					$output .= '</div>';
					$output .= '<div class="event-details-right">';
					$output .= '<div class="event-livetime-wrapper">';

					// Live badge logic: check if the event is live based on time and results status.
					$all_units_have_results = $this->check_units_for_status($event, 'isResultPublished');
					$event_start_timestamp = $event->jamaica_datetime_object->getTimestamp();
					$should_show_live_badge = ($current_timestamp >= ($event_start_timestamp - 300)) && !$all_units_have_results;

					if ($should_show_live_badge) {
						$output .= '<div class="live-badge"><div class="pulse-circle"></div><span>LIVE</span></div>';
					}

					$output .= '<div class="event-time">' . esc_html($event->jamaica_datetime_object->format('g:i A')) . '</div>';
					$output .= '</div>';
					$output .= '<div class="event-links">';

					// Display links based on the status of each link type.
					$is_results_published_by_units = $this->check_units_for_status($event, 'isResultPublished');
					$is_startlist_published_by_units = $this->check_units_for_status($event, 'isStartlistPublished');
					$is_summary_published_by_units = $this->check_units_for_status($event, 'isPhaseSummaryPublished');

					if ($is_results_published_by_units) {
						$output .= '<a href="' . esc_url($results_url) . '" target="_blank"><span class="results-link">Results <i class="fas fa-angle-right"></i></span></a>';
					} elseif ($is_startlist_published_by_units) {
						$output .= '<a href="' . esc_url($startlist_url) . '" target="_blank"><span class="startlist-link">Startlist <i class="fas fa-angle-right"></i></span></a>';
					}

					if ($is_summary_published_by_units) {
						$output .= '<a href="' . esc_url($summary_url) . '" target="_blank"><span class="summary-link">Summary <i class="fas fa-angle-right"></i></span></a>';
					}
					$output .= '</div>';
					$output .= '</div>';
					$output .= '</div>';
					$output .= '</div>';
				}
				$output .= '</div>';
				$output .= '</div>';
				$output .= '</div>';
				$output .= '</div>';
			}
			$output .= '</div>';
			$output .= '</div>';
		}
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Checks the status of a property across all units within an event.
	 *
	 * This function returns true only if the specified status key is true for ALL units.
	 * This is a stricter check than just checking the parent event's status.
	 *
	 * @param object $event      The event object.
	 * @param string $status_key The key of the status property to check (e.g., 'isResultPublished').
	 * @return bool True if all units have the status as true, otherwise false.
	 */
	private function check_units_for_status($event, $status_key)
	{
		// If the event has no units, rely on the main event's status.
		if (empty($event->units) || !is_array($event->units)) {
			return $event->{$status_key} ?? false;
		}

		// Iterate through each unit and return false if any unit does not have the status.
		foreach ($event->units as $unit) {
			if (!($unit->{$status_key} ?? false)) {
				return false;
			}
		}

		// All units have the status as true.
		return true;
	}
}

// Instantiate the main plugin class to begin execution.
new WA_Timetable_Main_Plugin();
