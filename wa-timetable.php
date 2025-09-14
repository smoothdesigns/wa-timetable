<?php

/**
 * WA Timetable (Tokyo 2025)
 *
 * @package  WA-Timetable
 * @author   Thomas Mirmo
 * @copyright 2025 Thomas Mirmo
 * @license  GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:  WA Timetable (Tokyo 2025)
 * Plugin URI:   https://github.com/smoothdesigns/wa-timetable
 * Description:  Displays the official 2025 World Athletics Championships timetable from Tokyo, Japan. Times are converted by default from Tokyo to Jamaican time.
 * Version:  3.0.0
 * Requires at least:  5.3
 * Tested up to: 6.8.2
 * Requires PHP: 7.2
 * Author:  Thomas Mirmo
 * Author URI:   https://github.com/smoothdesigns
 * Text Domain:  wa-timetable
 * License:  GPL v2 or later
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Include the updater class.
if (!class_exists('WAGitHubUpdater')) {
	require_once __DIR__ . '/includes/class-wa-github-updater.php';
}

/**
 * Main class for the WA Timetable Plugin.
 */
class WA_Timetable_Main_Plugin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register the shortcode
		add_shortcode('wa_timetable', [$this, 'render_shortcode']);
		// Enqueue styles and scripts
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
		// Add integrity and crossorigin attributes to Font Awesome stylesheet
		add_filter('style_loader_tag', [$this, 'add_integrity_and_crossorigin'], 10, 2);

		// Instantiate the updater unconditionally. The updater's own logic will handle the version comparison.
		new WAGitHubUpdater(__FILE__, 'https://github.com/smoothdesigns/wa-timetable/trunk/');
	}

	/**
	 * Enqueues Bootstrap CSS and JS.
	 */
	public function enqueue_scripts() {
		// Check if Bootstrap 5 is already enqueued to avoid conflicts
		if (!wp_style_is('bootstrap', 'enqueued')) {
			wp_enqueue_style(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
				[],
				'5.3.8'
			);
		}

		// Check if Font Awesome is already enqueued to avoid conflicts
		if (!wp_style_is('font-awesome') || !wp_style_is('fontawesome')) {
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				[],
				'5.15.4'
			);
		}

		// Enqueue custom plugin styles with a dynamic version
		wp_enqueue_style('wa-timetable-style', plugin_dir_url(__FILE__) . 'assets/css/wa-timetable-styles.css', [], filemtime(plugin_dir_path(__FILE__) . 'assets/css/wa-timetable-styles.css'));

		// Check if Bootstrap 5 JS is already enqueued to avoid conflicts
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
	 * Adds integrity and crossorigin attributes to the Font Awesome stylesheet.
	 *
	 * @param string $tag   The HTML link tag for the stylesheet.
	 * @param string $handle  The stylesheet handle.
	 * @return string The modified HTML link tag.
	 */
	public function add_integrity_and_crossorigin($tag, $handle) {
		if ('font-awesome' === $handle) {
			$integrity = 'sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==';
			$new_attributes = ' integrity="' . $integrity . '" crossorigin="anonymous"';
			// Use a regular expression to find the closing bracket and insert the attributes.
			$tag = preg_replace('/(\/>|\>)/', $new_attributes . '$1', $tag);
		}
		return $tag;
	}

	/**
	 * Renders the timetable by fetching, processing, and displaying the data.
	 *
	 * @return string The rendered HTML output.
	 */
	public function render_shortcode() {
		// 1. Data Extraction
		$extractor = new WA_Timetable_Data_Extractor();
		$data = $extractor->extract();

		if (is_wp_error($data)) {
			return '<div class="wa-timetable-error">Error: ' . esc_html($data->get_error_message()) . '</div>';
		}

		if (empty($data)) {
			return '<div class="wa-timetable-message">Could not find timetable data.</div>';
		}

		// 2. Data Processing (grouping by date and session)
		$processor = new WA_Timetable_Processor();
		$processed_data = $processor->process($data);

		// 3. View Generation (Bootstrap tabs and custom layout)
		$view = new WA_Timetable_View();
		return $view->generate_html($processed_data, $data);
	}

}

/**
 * Handles all data fetching and extraction logic.
 */
class WA_Timetable_Data_Extractor {
	/**
	 * Extracts the __NEXT_DATA__ JSON from the website's HTML.
	 *
	 * @return object|WP_Error The decoded JSON object or a WP_Error on failure.
	 */
	public function extract() {
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

		// New: Use a regular expression to find the script tag and extract its content.
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

/**
 * Handles all data processing and formatting.
 */
class WA_Timetable_Processor {
	/**
	 * Processes raw data into a structured format grouped by date and session.
	 *
	 * @param object $data The decoded JSON data.
	 * @return array The processed data, grouped by date.
	 */
	public function process($data) {
		$event_timetable = $data->props->pageProps->phases;

		$expanded_timetable = [];
		$processed_qualifications = [];

		foreach ($event_timetable as $event) {
			// Check if the event has units and is a qualification event
			if (!empty($event->units) && isset($event->units[0]->unitType) && $event->units[0]->unitType === 'G') {

				// Generate a unique key for the discipline to check for concurrency
				$discipline_key = $event->disciplineName . '_' . $event->sexName;

				// Group units by their start time
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

					// Add a new property for a clean URL slug, separate from the display name
					$unit_event->phaseUrlSlug = sanitize_title($event->phaseName);

					// If multiple units share the same start time, it's concurrent
					if (count($units) > 1) {
						$unit_event->phaseName = $event->phaseName; // No group label for concurrent groups
					} else {
						$unit_event->phaseName = $event->phaseName . ' - Group ' . ($units[0]->unitName ?? '');
					}

					$expanded_timetable[] = $unit_event;
				}
			} else {
				// If it's not a grouped qualification event, add the original phase.
				$expanded_timetable[] = $event;
			}
		}

		// Now, process the new expanded list of events.
		$event_timetable = $expanded_timetable;

		$grouped_data = [];

		// Calculate the time difference between Tokyo (JST) and Jamaica (EST/EDT)
		$tokyo_timezone = new DateTimeZone('Asia/Tokyo');
		$jamaica_timezone = new DateTimeZone('America/Jamaica');
		$tokyo_datetime = new DateTime('now', $tokyo_timezone);
		$jamaica_offset = $jamaica_timezone->getOffset($tokyo_datetime);
		$tokyo_offset = $tokyo_timezone->getOffset($tokyo_datetime);
		$time_difference_in_seconds = $tokyo_offset - $jamaica_offset;

		// Sort by date and time first to ensure events are in order
		usort($event_timetable, function ($a, $b) {
			$dateA = new DateTime($a->phaseDateAndTime ?? '');
			$dateB = new DateTime($b->phaseDateAndTime ?? '');
			return $dateA <=> $dateB;
		});

		// Group events by day and session
		$current_date = '';
		$day_number = 1;

		foreach ($event_timetable as $event) {
			// Convert time from Tokyo to Jamaica
			$event_datetime = new DateTime($event->phaseDateAndTime ?? '', $tokyo_timezone);
			$event_datetime->setTimestamp($event_datetime->getTimestamp() - $time_difference_in_seconds);

			$event->jamaica_time = $event_datetime->format('g:i A');
			$event->jamaica_start_time = $event_datetime->getTimestamp();

			// Add the end time to the event object for accurate live checking
			if (isset($event->phaseEndDateAndTime) && !empty($event->phaseEndDateAndTime)) {
				$event_end_datetime = new DateTime($event->phaseEndDateAndTime, $tokyo_timezone);
				$event_end_datetime->setTimestamp($event_end_datetime->getTimestamp() - $time_difference_in_seconds);
				$event->jamaica_end_time = $event_end_datetime->getTimestamp();
			} else {
				$event->jamaica_end_time = null;
			}

			$event_date_key = $event_datetime->format('Y-m-d');

			// Replace 'Morning' with 'Evening' and vice versa
			$session_name = $event->phaseSessionName ?? 'No Session';
			if ($session_name === 'Morning Session') {
				$session_name = 'Evening Session';
			} elseif ($session_name === 'Evening Session') {
				$session_name = 'Morning Session';
			}

			if ($event_date_key !== $current_date) {
				$current_date = $event_date_key;

				// Update day labels based on the new date
				$start_date = new DateTime('2025-09-12', $jamaica_timezone);
				$event_date_obj = new DateTime($event_date_key, $jamaica_timezone);
				$interval = $start_date->diff($event_date_obj);
				$day_number = $interval->days + 1;

				$day_label = 'Day ' . $day_number . ' - ' . $event_datetime->format('M d');
			}

			$grouped_data[$day_label][$session_name][] = $event;
		}

		return $grouped_data;
	}

}

/**
 * Handles all view logic and HTML generation.
 */
class WA_Timetable_View {
	/**
	 * Generates the HTML for the timetable using Bootstrap tabs and a custom layout.
	 *
	 * @param array $phases The processed timetable data, grouped by date.
	 * @param object $full_data The full data object from __NEXT_DATA__.
	 * @return string The HTML output.
	 */
	public function generate_html($phases, $full_data) {
		// Determine the current date in Jamaica to set the active tab
		$jamaica_timezone = new DateTimeZone('America/Jamaica');
		$current_date_jamaica = new DateTime('now', $jamaica_timezone);
		$current_date_formatted = $current_date_jamaica->format('M d');

		// Determine the active tab ID once before generating the HTML.
		$active_tab_id = null;
		foreach (array_keys($phases) as $date_label) {
			$parts = explode(' - ', $date_label);
			$date_part = isset($parts[1]) ? $parts[1] : '';

			if ($date_part === $current_date_formatted) {
				$active_tab_id = sanitize_title($date_label);
				break; // Found the current day, no need to check further.
			}
		}

		// If no tab matches the current day, default to the first one.
		if (is_null($active_tab_id)) {
			// Fix: Capture the result of array_keys() in a variable before passing to reset().
			$date_labels = array_keys($phases);
			$first_date_label = reset($date_labels);
			$active_tab_id = sanitize_title($first_date_label);
		}

		$output = '<div class="wa-timetable-container">';

		// Tabs navigation
		$output .= '<ul class="nav nav-pills nav-justified my-0" id="timetableTabs" role="tablist">';
		foreach (array_keys($phases) as $date_label) {
			$tab_id = sanitize_title($date_label);

			$parts = explode(' - ', $date_label);
			$date_part = isset($parts[1]) ? $parts[1] : '';

			$active_class = ($tab_id === $active_tab_id) ? 'active' : '';

			$day_part = isset($parts[0]) ? $parts[0] : '';
			if ($date_part === $current_date_formatted) {
				$day_part = 'TODAY';
			}

			$output .= '<li class="nav-item day-item my-0">';
			$output .= '<a class="nav-link ' . $active_class . '" id="' . $tab_id . '-tab" data-bs-toggle="tab" data-bs-target="#' . $tab_id . '" type="button" role="tab" aria-controls="' . $tab_id . '" aria-selected="' . ($active_class === 'active' ? 'true' : 'false') . '">';
			$output .= '<div class="d-flex flex-column">';
			$output .= '<span style="font-size: 10px; font-weight: normal; text-transform: uppercase;">' . esc_html($day_part) . '</span>';
			$output .= '<span style="font-size: 14px; font-weight: bold;">' . esc_html($date_part) . '</span>';
			$output .= '</div>';
			$output .= '</a>';
			$output .= '</li>';
		}
		$output .= '</ul>';

		// Tab content panes
		$output .= '<div class="tab-content mt-3">';
		foreach ($phases as $date_label => $sessions_for_date) {
			$tab_id = sanitize_title($date_label);
			$active_class = ($tab_id === $active_tab_id) ? 'show active' : '';

			$output .= '<div class="tab-pane fade ' . $active_class . '" id="' . $tab_id . '" role="tabpanel" aria-labelledby="' . $tab_id . '-tab">';

			// Accordion for sessions within the day
			$output .= '<div class="accordion" id="accordion-' . $tab_id . '">';
			foreach ($sessions_for_date as $session_name => $events_for_session) {
				$session_id = sanitize_title($date_label . '-' . $session_name);
				$show_class = 'show'; // All accordions are open by default.

				// Check if the session is finished.
				$all_results_published = true;
				foreach ($events_for_session as $event) {
					if (!$this->check_units_for_status($event, 'isResultPublished')) {
						$all_results_published = false;
						break;
					}
				}

				$output .= '<div class="accordion-item border-0">';
				$output .= '<h2 class="accordion-header p-0 my-0" id="heading-' . $session_id . '">';
				$output .= '<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $session_id . '" aria-expanded="true" aria-controls="collapse-' . $session_id . '">';
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
					$startlist_url = $base_url . $sanitized_sexName . '/' . $discipline_slug . '/' . $sanitized_phase_url_slug . '/startlist';
					$results_url = $base_url . $sanitized_sexName . '/' . $discipline_slug . '/' . $sanitized_phase_url_slug . '/results';
					$summary_url = $base_url . $sanitized_sexName . '/' . $discipline_slug . '/' . $sanitized_phase_url_slug . '/summary';

					$output .= '<div class="event-item" id="event-' . esc_attr($event_id) . '-' . esc_attr($phase_order) . '">';
					$output .= '<div class="event-item-content">';
					$output .= '<div class="event-details-left">';
					$output .= '<div class="event-name">' . esc_html($sexName) . ' ' . esc_html($disciplineName) . '</div>';
					$output .= '<div class="event-phase-container"><span style="border-radius: 4px; padding: 2px 6px; line-height: 1; background-color: ' . $bgColor . ';">' . esc_html($phaseName) . '</span></div>';
					$output .= '</div>';
					$output .= '<div class="event-details-right">';
					$output .= '<div class="event-livetime-wrapper" style="display: flex; align-items: center; gap: 10px;">';

					$is_results_published_by_units = $this->check_units_for_status($event, 'isResultPublished');

					// Get current time in Jamaica
					$jamaica_timezone = new DateTimeZone('America/Jamaica');
					$now = new DateTime('now', $jamaica_timezone);

					// Check if the current time is between the event's start and end times
					$is_currently_live = false;
					if (isset($event->jamaica_end_time) && $event->jamaica_end_time !== null) {
						$event_start_datetime = new DateTime('@' . $event->jamaica_start_time, $jamaica_timezone);
						$event_end_datetime = new DateTime('@' . $event->jamaica_end_time, $jamaica_timezone);
						if ($now >= $event_start_datetime && $now <= $event_end_datetime) {
							$is_currently_live = true;
						}
					}

					if ($is_currently_live && !$is_results_published_by_units) {
						$output .= '<div class="live-badge">';
						$output .= '<div class="pulse-circle"></div>';
						$output .= '<span>LIVE</span>';
						$output .= '</div>';
					}

					$output .= '<div class="event-time">' . esc_html($event->jamaica_time) . '</div>';
					$output .= '</div>';
					$output .= '<div class="event-links">';

					$is_startlist_published_by_units = $this->check_units_for_status($event, 'isStartlistPublished');
					$is_summary_published_by_units = $this->check_units_for_status($event, 'isPhaseSummaryPublished');

					if ($is_startlist_published_by_units && !$is_results_published_by_units) {
						$output .= '<a href="' . esc_url($startlist_url) . '" target="_blank"><span class="startlist-link">Startlist <i class="fas fa-angle-right"></i></span></a>';
					}
					if ($is_results_published_by_units) {
						$output .= '<a href="' . esc_url($results_url) . '" target="_blank"><span class="results-link">Results <i class="fas fa-angle-right"></i></span></a>';
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
	 * @param object $event   The event object.
	 * @param string $status_key The key of the status property to check (e.g., 'isResultPublished').
	 * @return bool True if all units have the status as true, or if the main event has it.
	 */
	private function check_units_for_status($event, $status_key) {
		if (empty($event->units) || !is_array($event->units)) {
			return $event->{$status_key} ?? false;
		}

		foreach ($event->units as $unit) {
			if (!($unit->{$status_key} ?? false)) {
				return false; // Found a unit with false status, so the phase status is false.
			}
		}

		return true; // All units have the status as true.
	}

}

// Instantiate the main plugin class to get things started.
new WA_Timetable_Main_Plugin();
