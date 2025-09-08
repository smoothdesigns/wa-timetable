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
 * Description:       Displays the official 2025 World Athletics Championships timetable from Tokyo, Japan. Times are converted by default from Tokyo to Jamaican time, with options for more time zones in the settings page.
 * Version:           2.0.2
 * Requires at least: 5.3
 * Requires PHP:      7.2
 * Author:            Thomas Mirmo
 * Author URI:        https://github.com/smoothdesigns
 * Text Domain:       wa-timetable
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles the GitHub-based plugin updates.
 * This class fetches update information from a `info.json` file on the GitHub repository.
 * It hooks into WordPress's plugin update transient to check for and apply updates.
 */
class WAGitHubUpdater
{

	private $github_api_url;
	private $plugin_file;
	private $plugin_slug;

	/**
	 * Constructor to initialize the updater.
	 *
	 * @param string $plugin_file The main plugin file path.
	 * @param string $github_repo_url The URL of the GitHub repository.
	 */
	public function __construct($plugin_file, $github_repo_url)
	{
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = basename(dirname($plugin_file));
		$this->github_api_url = $github_repo_url;

		// Add a filter to modify the plugins update transient.
		add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);

		// Add a filter to handle the plugin information display.
		add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
	}

	/**
	 * Checks for updates by fetching the `info.json` file from the GitHub repository.
	 *
	 * @param object $transient The plugins update transient.
	 * @return object The modified transient object.
	 */
	public function check_for_updates($transient)
	{
		// Check if the transient is already set or if the plugin is not active.
		if (empty($transient->checked) || !is_object($transient)) {
			return $transient;
		}

		// Get the current version of the plugin.
		$plugin_info = get_plugin_data($this->plugin_file);
		$current_version = $plugin_info['Version'];

		// Fetch the latest release information from GitHub.
		$response = wp_remote_get(add_query_arg('callback', '?', $this->github_api_url . '/info.json'), ['sslverify' => false]);
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			return $transient;
		}

		$json = wp_remote_retrieve_body($response);
		$data = json_decode($json, true);

		if ($data && version_compare($current_version, $data['version'], '<')) {
			// If a newer version is available, add it to the transient.
			$transient->response[$this->plugin_slug . '/' . basename($this->plugin_file)] = (object) [
				'slug' => $this->plugin_slug,
				'new_version' => $data['version'],
				'url' => $this->github_api_url,
				'package' => $data['download_url'],
			];
		}

		return $transient;
	}

	/**
	 * Provides detailed plugin information for the update screen.
	 *
	 * @param false|object|array $result The result object or false.
	 * @param string $action The API action.
	 * @param object $args The API arguments.
	 * @return false|object|array The result object or false.
	 */
	public function plugin_info($result, $action, $args)
	{
		// Check if this is the correct action and slug.
		if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->plugin_slug) {
			return $result;
		}

		// Fetch the info.json file from the GitHub repository.
		$response = wp_remote_get(add_query_arg('callback', '?', $this->github_api_url . '/info.json'));

		// --- DEBUG LOGGING ADDED ---
		error_log('WA Timetable Plugin Debug: Fetching info.json from ' . $this->github_api_url . '/info.json');
		if (is_wp_error($response)) {
			error_log('WA Timetable Plugin Debug: wp_remote_get failed. Error: ' . $response->get_error_message());
			return $result;
		}

		$json = wp_remote_retrieve_body($response);
		error_log('WA Timetable Plugin Debug: Raw JSON fetched: ' . $json);

		// Decode into an associative array for easier manipulation
		$data = json_decode($json, true);

		// If the data is not a valid array, return the original result.
		if (!is_array($data)) {
			error_log('WA Timetable Plugin Debug: Failed to decode JSON into an array.');
			return $result;
		}

		error_log('WA Timetable Plugin Debug: Decoded data (array): ' . print_r($data, true));

		// Fetch the last updated date from the GitHub API.
		$last_updated = '';
		$commit_api_url = 'https://api.github.com/repos/smoothdeisgns/wa-timetable/commits?path=info.json&per_page=1';
		$commit_response = wp_remote_get($commit_api_url);
		if (!is_wp_error($commit_response) && wp_remote_retrieve_response_code($commit_response) === 200) {
			$commit_data = json_decode(wp_remote_retrieve_body($commit_response), true);
			if (is_array($commit_data) && !empty($commit_data)) {
				$date_string = $commit_data[0]['commit']['author']['date'];
				$last_updated = date('Y-m-d H:i:s', strtotime($date_string));
			}
		}

		// Prepare sections and screenshots
		$sections_array = isset($data['sections']) ? $data['sections'] : [];
		$screenshots_array = isset($sections_array['screenshots']) ? $sections_array['screenshots'] : [];

		// Remove screenshots from sections before casting
		if (isset($sections_array['screenshots'])) {
			unset($sections_array['screenshots']);
		}

		// FIX: The WordPress API expects the 'sections' property to be an object, not an array.
		// Explicitly cast sections to an object.
		$sections_object = (object) $sections_array;

		// Explicitly cast screenshots to an array of objects.
		$screenshots_objects = array_map(function ($screenshot) {
			return (object) $screenshot;
		}, $screenshots_array);


		// Create a new object to match the expected format for WordPress.
		$new_result = (object) [
			'slug' => $data['slug'] ?? '',
			'plugin_name' => $data['name'] ?? '',
			'name' => $data['name'] ?? '',
			'version' => $data['version'] ?? '',
			'author' => $data['author'] ?? '',
			'author_profile' => $data['author_profile'] ?? '',
			'requires' => $data['requires'] ?? '',
			'tested' => $data['tested'] ?? '',
			'requires_php' => $data['requires_php'] ?? '',
			'download_link' => $data['download_url'] ?? '',
			'trunk' => $data['download_url'] ?? '',
			'last_updated' => $last_updated,
			'sections' => $sections_object,
			'screenshots' => $screenshots_objects,
			'banners' => (object) [
				'low' => 'https://raw.githubusercontent.com/smoothdeisgns/wa-timetable/main/assets/banner-772x250.png',
				'high' => 'https://raw.githubusercontent.com/smoothdeisgns/wa-timetable/main/assets/banner-1544x500.png',
			],
		];

		// --- DEBUG LOGGING ADDED ---
		error_log('WA Timetable Plugin Debug: Final object being returned: ' . print_r($new_result, true));

		return $new_result;
	}
}

// Instantiate the updater class.
new WAGitHubUpdater(__FILE__, 'https://raw.githubusercontent.com/smoothdeisgns/wa-timetable/main');

// Settings page function
add_action('admin_menu', 'wa_timetable_settings_page');
/**
 * Adds the settings page to the WordPress admin menu.
 */
function wa_timetable_settings_page()
{
	add_options_page(
		'WA Timetable Settings',
		'WA Timetable',
		'manage_options',
		'wa-timetable-settings',
		'wa_timetable_settings_page_html'
	);
}

// Add settings link to the plugin actions
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wa_timetable_add_settings_link');
/**
 * Adds a "Settings" link to the plugin's action links on the plugins page.
 *
 * @param array $links The array of plugin action links.
 * @return array The modified array of links.
 */
function wa_timetable_add_settings_link($links)
{
	$settings_link = '<a href="options-general.php?page=wa-timetable-settings">' . __('Settings') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}

add_filter('plugin_row_meta', 'wa_timetable_add_plugin_row_meta', 10, 2);
/**
 * Adds a "View details" link to the plugin's row on the plugins page.
 *
 * @param array  $links       The array of plugin row links.
 * @param string $plugin_file The plugin file name.
 * @return array The modified array of links.
 */
function wa_timetable_add_plugin_row_meta($links, $plugin_file)
{
	if (plugin_basename(__FILE__) === $plugin_file) {
		$links[] = '<a href="' . network_admin_url('plugin-install.php?tab=plugin-information&plugin=wa-timetable&TB_iframe=true&width=600&height=550') . '" class="thickbox open-plugin-details-modal">View details</a>';
	}
	return $links;
}

// Settings page HTML
/**
 * Renders the HTML for the plugin's settings page.
 */
function wa_timetable_settings_page_html()
{
	// Check if the current user has the "manage_options" capability.
	if (!current_user_can('manage_options')) {
		return;
	}
	// Check if the form was submitted and the nonce is valid.
	if (isset($_POST['wa_timetable_settings_nonce']) && wp_verify_nonce($_POST['wa_timetable_settings_nonce'], 'wa_timetable_settings_action')) {
		// Sanitize and save the form data.
		$timetable_url = sanitize_url($_POST['timetable_url']);
		$timeout = intval($_POST['timeout']);
		$headers = array_map('sanitize_text_field', $_POST['headers']);
		$timetable_timezone = sanitize_text_field($_POST['timetable_timezone']);
		$conversion_timezone = sanitize_text_field($_POST['conversion_timezone']);
		$morning_session_name = sanitize_text_field($_POST['morning_session_name']);
		$evening_session_name = sanitize_text_field($_POST['evening_session_name']);
		$afternoon_session_name = sanitize_text_field($_POST['afternoon_session_name']);

		update_option('wa_timetable_url', $timetable_url);
		update_option('wa_timetable_timeout', $timeout);
		update_option('wa_timetable_headers', $headers);
		update_option('wa_timetable_timezone', $timetable_timezone);
		update_option('wa_conversion_timezone', $conversion_timezone);
		update_option('wa_morning_session_name', $morning_session_name);
		update_option('wa_evening_session_name', $evening_session_name);
		update_option('wa_afternoon_session_name', $afternoon_session_name);

		// Display a success message.
		echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
	}
	// Retrieve saved settings or use default values.
	$timetable_url = get_option('wa_timetable_url', 'https://worldathletics.org/competitions/world-athletics-championships/tokyo25/timetable');
	$timeout = get_option('wa_timetable_timeout', 30);
	$headers = get_option('wa_timetable_headers', ['Time', 'Sex', 'Event', 'Round']);
	$timetable_timezone = get_option('wa_timetable_timezone', 'Asia/Tokyo');
	$conversion_timezone = get_option('wa_conversion_timezone', 'America/Jamaica');
	$morning_session_name = get_option('wa_morning_session_name', 'Morning Session (Jamaica)');
	$evening_session_name = get_option('wa_evening_session_name', 'Evening Session (Jamaica)');
	$afternoon_session_name = get_option('wa_afternoon_session_name', '');

?>
	<div class="wrap">
		<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
		<form method="post"> <?php wp_nonce_field('wa_timetable_settings_action', 'wa_timetable_settings_nonce'); ?> <table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="timetable_url">Timetable URL</label></th>
					<td><input type="text" id="timetable_url" name="timetable_url" value="<?php echo esc_attr($timetable_url); ?>" class="large-text" style="width: 100%;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="timeout">Timeout (seconds)</label></th>
					<td><input type="number" id="timeout" name="timeout" value="<?php echo esc_attr($timeout); ?>" style="width: 80px;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="timetable_timezone">Timetable Timezone</label></th>
					<td>
						<select id="timetable_timezone" name="timetable_timezone"> <?php echo wa_timezone_options($timetable_timezone); ?> </select>
						<p class="description">The timezone of the timetable data (e.g., Asia/Tokyo).</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="conversion_timezone">Conversion Timezone</label></th>
					<td>
						<select id="conversion_timezone" name="conversion_timezone"> <?php echo wa_timezone_options($conversion_timezone); ?> </select>
						<p class="description">The timezone to convert the timetable times to (e.g., America/Jamaica).</p>
					</td>
				</tr> <?php foreach ($headers as $index => $header) : ?> <tr valign="top">
						<th scope="row"><label for="header_<?php echo $index; ?>">Header <?php echo $index + 1; ?></label></th>
						<td><input type="text" id="header_<?php echo $index; ?>" name="headers[]" value="<?php echo esc_attr($header); ?>" class="regular-text" /></td>
					</tr> <?php endforeach; ?> <tr valign="top">
					<th scope="row"><label for="morning_session_name">Morning Session Name</label></th>
					<td><input type="text" id="morning_session_name" name="morning_session_name" value="<?php echo esc_attr($morning_session_name); ?>" class="regular-text" />
						<p class="description">The name to display for the 'Morning Session' after conversion.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="evening_session_name">Evening Session Name</label></th>
					<td><input type="text" id="evening_session_name" name="evening_session_name" value="<?php echo esc_attr($evening_session_name); ?>" class="regular-text" />
						<p class="description">The name to display for the 'Evening Session' after conversion.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="afternoon_session_name">Afternoon Session Name</label></th>
					<td><input type="text" id="afternoon_session_name" name="afternoon_session_name" value="<?php echo esc_attr($afternoon_session_name); ?>" class="regular-text" />
						<p class="description">The name to display for the 'Afternoon Session' after conversion (leave blank to hide).</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Links Base URL</th>
					<td> https://worldathletics.org/en/competitions/world-athletics-championships/ </td>
				</tr>
			</table> <?php submit_button(); ?> </form>
	</div> <?php
				}

				// Helper function to generate timezone options for the select dropdown
				/**
				 * Generates HTML `<option>` tags for all available timezones.
				 *
				 * @param string $selected The currently selected timezone.
				 * @return string The HTML string for the timezone options.
				 */
				function wa_timezone_options($selected = '')
				{
					$timezones = timezone_identifiers_list();
					$options = '';
					foreach ($timezones as $timezone) {
						$options .= '<option value="' . esc_attr($timezone) . '"' . selected($selected, $timezone, false) . '>' . esc_html(str_replace('_', ' ', $timezone)) . '</option>';
					}
					return $options;
				}

				add_shortcode('wa_timetable', 'wa_timetable_shortcode');
				/**
				 * Shortcode to display the timetable. Fetches and processes data from World Athletics.
				 *
				 * @return string The HTML output for the timetable.
				 */
				function wa_timetable_shortcode()
				{
					$timetable_url = get_option('wa_timetable_url', 'https://worldathletics.org/competitions/world-athletics-championships/tokyo25/timetable');
					$timeout = get_option('wa_timetable_timeout', 30);
					$args = array(
						'timeout' => $timeout,
						'sslverify' => false,
					);
					$response = wp_remote_get($timetable_url, $args);
					if (is_wp_error($response)) {
						$error_message = $response->get_error_message();
						return '<div class="alert alert-danger">Error fetching timetable data: ' . esc_html($error_message) . '</div>';
					}
					$body = wp_remote_retrieve_body($response);
					if (empty($body)) {
						return '<div class="alert alert-warning">Could not retrieve timetable content.</div>';
					}
					$dom = new DOMDocument();
					libxml_use_internal_errors(true);
					$dom->loadHTML($body);
					libxml_clear_errors();
					$xpath = new DOMXPath($dom);
					$script_tag = $xpath->query('//script[@id="__NEXT_DATA__"]')->item(0);
					if (!$script_tag) {
						wp_enqueue_script('wa-timetable-dynamic', plugin_dir_url(__FILE__) . 'js/wa-timetable-dynamic.js', array('jquery'), '1.0.0', true);
						return '<div id="wa-timetable-container"><div class="alert alert-info">Loading timetable data...</div><div id="wa-timetable-data" style="display:none;"></div></div>';
					} else {
						$json_data = $script_tag->textContent;
						$data = json_decode($json_data, true);
						if (!$data || !isset($data['props']['pageProps']['eventTimetable'])) {
							wp_enqueue_script('wa-timetable-dynamic', plugin_dir_url(__FILE__) . 'js/wa-timetable-dynamic.js', array('jquery'), '1.0.0', true);
							return '<div id="wa-timetable-container"><div class="alert alert-info">Loading timetable data...</div><div id="wa-timetable-data" style="display:none;"></div></div>';
						} else {
							$event_name_url_slug = $data['props']['pageProps']['page']['event']['nameUrlSlug'];
							return process_event_timetable_data($data, $event_name_url_slug);
						}
					}
				}

				/**
				 * Processes the event timetable data and generates the HTML output.
				 *
				 * @param array $data The timetable data from the World Athletics website.
				 * @param string $event_name_url_slug The URL slug for the event.
				 * @return string The formatted HTML output.
				 */
				function process_event_timetable_data($data, $event_name_url_slug)
				{
					$event_timetable = $data['props']['pageProps']['eventTimetable'];
					$headers = get_option('wa_timetable_headers', ['Time', 'Sex', 'Event', 'Round']);
					$timetable_timezone_string = get_option('wa_timetable_timezone', 'Asia/Tokyo');
					$conversion_timezone_string = get_option('wa_conversion_timezone', 'America/Jamaica');
					$morning_session_name_option = get_option('wa_morning_session_name', 'Morning Session (Jamaica)');
					$evening_session_name_option = get_option('wa_evening_session_name', 'Evening Session (Jamaica)');
					$afternoon_session_name_option = get_option('wa_afternoon_session_name', '');
					$days = [];
					$day_keys = [];
					foreach ($event_timetable as $event) {
						$tokyo_time_string = $event['phaseDateAndTime'];
						try {
							$timetable_timezone = new DateTimeZone($timetable_timezone_string);
							$conversion_timezone = new DateTimeZone($conversion_timezone_string);
							$datetime_original = DateTime::createFromFormat('Y-m-d\TH:i:s.vZ', $tokyo_time_string, $timetable_timezone);
							if (!$datetime_original) {
								error_log("Error parsing original time: " . $tokyo_time_string);
								continue;
							}
							$datetime_converted = clone $datetime_original;
							$datetime_converted->setTimezone($conversion_timezone);
							$date = $datetime_converted->format('d M');
							$time_12_hour = $datetime_converted->format('g:i A');
						} catch (Exception $e) {
							error_log("General error with DateTime: " . $e->getMessage() . " for time: " . $tokyo_time_string . " - " . $e->getMessage());
							continue;
						}
						$phase_display = $event['phaseName'];
						if (!empty($event['unitTypeName']) && !empty($event['unitName'])) {
							$phase_display .= ' - ' . $event['unitTypeName'] . ' ' . $event['unitName'];
						}
						$session_name_japan = $event['phaseSessionName'];
						$session_name = $session_name_japan;
						if ($session_name_japan === 'Evening Session') {
							$session_name = $evening_session_name_option;
						} elseif ($session_name_japan === 'Morning Session') {
							$session_name = $morning_session_name_option;
						} elseif ($session_name_japan === 'Afternoon Session') {
							$session_name = $afternoon_session_name_option;
						}
						if (!isset($days[$date])) {
							$days[$date] = [];
							$day_keys[] = $date;
						}
						if (!isset($days[$date][$session_name])) {
							$days[$date][$session_name] = [];
						}
						$event_data = [];
						$event_data[$headers[0]] = $time_12_hour;
						$event_data[$headers[1]] = $event['sexCode'];
						$event_data[$headers[2]] = $event['discipline']['name'];
						$event_data[$headers[3]] = $phase_display;
						$event_data['sexNameUrlSlug'] = $event['sexNameUrlSlug'];
						$event_data['nameUrlSlug'] = $event['discipline']['nameUrlSlug'];
						$event_data['phaseNameUrlSlug'] = $event['phaseNameUrlSlug'];
						$event_data['isStartlistPublished'] = $event['isStartlistPublished'];
						$event_data['isResultPublished'] = $event['isResultPublished'];
						$event_data['isPhaseSummaryPublished'] = $event['isPhaseSummaryPublished'];
						$days[$date][$session_name][] = $event_data;
					}
					$output = '<div id="wa-timetable-tabs">';
					$output .= '<nav class="nav nav-underline flex-sm-wrap flex-md-row row-gap-1 column-gap-3" id="timetableTabs" role="tablist">';
					$today = date('d M Y');
					$active_tab_found = false;
					foreach ($day_keys as $index => $day_key) {
						$tab_id = 'day-' . ($index + 1);
						$day_number = date('d', strtotime($day_key));
						$day_month = date('M', strtotime($day_key));
						$is_active = (date('d M Y', strtotime($day_key)) === $today);
						if ($is_active) {
							$active_tab_found = true;
						}
						$active_class = $is_active || (!$active_tab_found && $index === 0) ? 'active' : '';
						$selected = $is_active || (!$active_tab_found && $index === 0) ? 'true' : 'false';
						$output .= '<a class="nav-link text-sm-center link-success d-flex flex-column flex-grow-1 lh-sm ' . $active_class . '" id="' . $tab_id . '-tab" data-bs-toggle="tab" href="#' . $tab_id . '" role="tab" aria-controls="' . $tab_id . '" aria-selected="' . $selected . '">';
						$output .= '<span>DAY ' . ($index + 1) . '</span>';
						$output .= '<span class="small">' . $day_number . ' ' . $day_month . '</span>';
						$output .= '</a>';
					}
					$output .= '</nav><div class="tab-content" id="timetableTabsContent">';
					foreach ($day_keys as $index => $day_key) {
						$tab_id = 'day-' . ($index + 1);
						$active_class = !$active_tab_found && $index === 0 ? 'show active' : '';
						if (date('d M Y', strtotime($day_key)) === $today) {
							$active_class = 'show active';
						}
						$output .= '<div class="tab-pane fade ' . $active_class . '" id="' . $tab_id . '" role="tabpanel" aria-labelledby="' . $tab_id . '-tab">';
						$output .= '<div class="day-banner bg-warning-subtle text-warning-emphasis w-75 border-bottom border-2 border-black p-2 m-0">';
						$output .= '<p class="fs-6 lh-1 mb-0">DAY ' . ($index + 1) . ' - ' . $day_key . '</p>';
						$output .= '</div>';
						$day_sessions = $days[$day_key];
						if (!empty($day_sessions)) {
							$accordion_id = 'accordion-' . str_replace(' ', '-', $day_key);
							$output .= '<div class="accordion accordion-flush" id="' . $accordion_id . '">';
							$session_counter = 0;
							foreach ($day_sessions as $session_name => $session_events) {
								if ($session_name !== '') { // Only display if the session name is not empty
									$session_accordion_id = 'session-collapse-' . str_replace(' ', '-', $session_name . '-' . $index . '-' . $session_counter);
									$session_heading_id = 'heading-' . str_replace(' ', '-', $session_name . '-' . $index . '-' . $session_counter);
									$output .= '<div class="accordion-item border-0">';
									$output .= '<h2 class="accordion-header pb-0" id="' . $session_heading_id . '">';
									$output .= '<button class="accordion-button rounded-0 text-uppercase fs-4 p-2" type="button" data-bs-toggle="collapse" data-bs-target="#' . $session_accordion_id . '" aria-expanded="true" aria-controls="' . $session_accordion_id . '">';
									$output .= $session_name;
									$output .= '</button></h2>';
									$output .= '<div id="' . $session_accordion_id . '" class="accordion-collapse collapse show" aria-labelledby="' . $session_heading_id . '">';
									$output .= '<div class="accordion-body p-0">';
									$output .= '<div class="table-responsive shadow">';
									$output .= '<table class="table table-striped table-hover table-sm mb-0"><thead><tr>';
									foreach ($headers as $header) {
										$output .= '<th scope="col">' . $header . '</th>';
									}
									$output .= '<th scope="col"></th><th scope="col"></th><th scope="col"></th></tr></thead><tbody>';
									foreach ($session_events as $event) {
										$output .= '<tr scope="row">';
										foreach ($headers as $header) {
											$output .= '<td>' . $event[$header] . '</td>';
										}
										$base_url = 'https://worldathletics.org/en/competitions/world-athletics-championships/' . $event_name_url_slug . '/results/';
										$startlist_link = $event['isStartlistPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/startlist">Startlist</a>' : '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/startlist" target="_blank"></a>';
										$result_link = $event['isResultPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/result">Result</a>' : '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/result" target="_blank"></a>';
										$summary_link = $event['isPhaseSummaryPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/summary">Summary</a>' : '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/summary" target="_blank"></a>';
										$output .= '<td>' . $startlist_link . '</td>';
										$output .= '<td>' . $result_link . '</td>';
										$output .= '<td>' . $summary_link . '</td>';
										$output .= '</tr>';
									}
									$output .= '</tbody></table></div></div></div></div>';
									$session_counter++;
								}
							}
							$output .= '</div>';
						} else {
							$output .= '<p>No events scheduled for this day.</p>';
						}
						$output .= '</div>';
					}
					$output .= '</div></div>';
					return $output;
				}

				add_action('wp_enqueue_scripts', 'wa_timetable_enqueue_scripts');
				/**
				 * Enqueues the necessary CSS and JavaScript files for the plugin's frontend.
				 */
				function wa_timetable_enqueue_scripts()
				{
					if (!wp_style_is('bootstrap', 'enqueued') && !wp_style_is('bootstrap', 'registered')) {
						wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
					}
					wp_enqueue_style('wa-timetable-styles', plugin_dir_url(__FILE__) . 'css/wa-timetable-styles.css', array('bootstrap'), '1.0.0');
					if (!wp_script_is('jquery', 'enqueued') && !wp_script_is('jquery', 'registered')) {
						wp_enqueue_script('jquery', get_site_url() . '/wp-includes/js/jquery/jquery.min.js', array(), null, true);
					}
					if (!wp_script_is('bootstrap', 'enqueued') && !wp_script_is('bootstrap', 'registered')) {
						wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
					}
					wp_enqueue_script('wa-timetable', plugin_dir_url(__FILE__) . 'js/wa-timetable.js', array('jquery', 'bootstrap'), null, true);
				}
