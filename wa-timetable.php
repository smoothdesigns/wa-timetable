<?php
/**
 * WA Timetable (Tokyo 2025)
 *
 * @package             WA-Timetable
 * @author              Thomas Mirmo (I am Mr Smooth)
 * @copyright           2025 Thomas Mirmo
 * @license             GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:         WA Timetable (Tokyo 2025)
 * Plugin URI:          #
 * Description:         Displays the official 2025 World Athletics Championships timetable from Tokyo, Japan. Times are converted by default from Tokyo to Jamaican time, with options for more time zones in the settings page.
 * Version:             1.8.2
 * Requires at least:   5.3
 * Requires PHP:        7.2
 * Author:              Thomas Mirmo (I am Mr Smooth)
 * Author URI:          #
 * Text Domain:         wa-timetable
 * License:             GPL v2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
  exit;
}
// Settings page function
add_action('admin_menu', 'wa_timetable_settings_page');
function wa_timetable_settings_page() {

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
function wa_timetable_add_settings_link($links) {

  $settings_link = '<a href="options-general.php?page=wa-timetable-settings">' . __('Settings') . '</a>';
  array_unshift($links, $settings_link);

  return $links;
}

// Settings page HTML
function wa_timetable_settings_page_html() {

  // Check if the current user has the 'manage_options' capability.
  // If not, it means they don't have permission to access the settings page, so the function returns.
  if (!current_user_can('manage_options')) {
    return;
  }

  // Check if the form has been submitted and the nonce is valid.
  if (isset($_POST['wa_timetable_settings_nonce']) && wp_verify_nonce($_POST['wa_timetable_settings_nonce'], 'wa_timetable_settings_action')) {

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

    echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
  }

  // Retrieve the current settings from the WordPress options, or use default values.
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
function wa_timezone_options($selected = '') {

  $timezones = timezone_identifiers_list();
  $options = '';
  foreach ($timezones as $timezone) {
    $options .= '<option value="' . esc_attr($timezone) . '"' . selected($selected, $timezone, false) . '>' . esc_html(str_replace('_', ' ', $timezone)) . '</option>';
  }
  return $options;
}

add_shortcode('wa_timetable', 'wa_timetable_shortcode');
function wa_timetable_shortcode() {

  // Retrieve the timetable URL and timeout settings from the WordPress options.
  // If the options are not set, it uses default values.
  $timetable_url = get_option('wa_timetable_url', 'https://worldathletics.org/competitions/world-athletics-championships/tokyo25/timetable');
  $timeout = get_option('wa_timetable_timeout', 30);

  // Set up arguments for the wp_remote_get function, including timeout and SSL verification.
  $args = array(
    'timeout' => $timeout,
    'sslverify' => false,
  );

  // Fetch the content of the timetable URL using wp_remote_get.
  $response = wp_remote_get($timetable_url, $args);

  // Check if there was an error fetching the URL. If so, return an error message.
  if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
    return '<div class="alert alert-danger">Error fetching timetable data: ' . esc_html($error_message) . '</div>';
  }

  // Retrieve the body of the response.
  $body = wp_remote_retrieve_body($response);

  // Check if the response body is empty. If so, return a warning message.
  if (empty($body)) {
    return '<div class="alert alert-warning">Could not retrieve timetable content.</div>';
  }

  // Create a new DOMDocument object to parse the HTML.
  $dom = new DOMDocument();

  // Suppress libxml errors to prevent warnings from being displayed.
  libxml_use_internal_errors(true);

  // Load the HTML content into the DOMDocument object.
  $dom->loadHTML($body);

  // Clear libxml errors.
  libxml_clear_errors();

  // Create a new DOMXPath object to query the DOM.
  $xpath = new DOMXPath($dom);

  // Query the DOM for the script tag with the ID "__NEXT_DATA__".
  $script_tag = $xpath->query('//script[@id="__NEXT_DATA__"]')->item(0);

  // Check if the script tag was found. If not, return a warning message.
  if (!$script_tag) {
    return '<div class="alert alert-warning">__NEXT_DATA__ script tag not found.</div>';
  }

  // Retrieve the content of the script tag, which is JSON data.
  $json_data = $script_tag->textContent;

  // Decode the JSON data into an associative array.
  $data = json_decode($json_data, true);

  // Check if the JSON data was successfully decoded and if the eventTimetable data exists.
  if (!$data || !isset($data['props']['pageProps']['eventTimetable'])) {
    // If the timetable data is not available, enqueue the dynamic JavaScript and display a loading message.
    wp_enqueue_script('wa-timetable-dynamic', plugin_dir_url(__FILE__) . 'js/wa-timetable-dynamic.js', array('jquery'), '1.0.0', true);
    return '<div id="wa-timetable-container"><div class="alert alert-info">Loading timetable data...</div><div id="wa-timetable-data" style="display:none;"></div></div>';
  } else {
    // If the timetable data is available, retrieve the nameUrlSlug from the event object.
    $event_name_url_slug = $data['props']['pageProps']['page']['event']['nameUrlSlug'];

    // Call the process_event_timetable_data function to process the data and generate the HTML.
    return process_event_timetable_data($data, $event_name_url_slug);
  }
}

function process_event_timetable_data($data, $event_name_url_slug) {

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
            $startlist_link = $event['isStartlistPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/startlist">Startlist</a>' : '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/startlist" target="_blank"></a>';
            $result_link = $event['isResultPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/result">Result</a>' : '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/result" target="_blank"></a>';
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
function wa_timetable_enqueue_scripts() {

  // Check if Bootstrap CSS is enqueued or registered.
  if (!wp_style_is('bootstrap', 'enqueued') && !wp_style_is('bootstrap', 'registered')) {
    // Load Bootstrap CSS in the header.
    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
  }

  // Enqueue custom styles from the 'css' folder
  wp_enqueue_style('wa-timetable-styles', plugin_dir_url(__FILE__) . 'css/wa-timetable-styles.css', array('bootstrap'), '1.0.0');

  // Check if jQuery is already enqueued or registered.
  if (!wp_script_is('jquery', 'enqueued') && !wp_script_is('jquery', 'registered')) {
    // Enqueue WordPress's built-in jQuery in the footer.
    wp_enqueue_script('jquery', get_site_url() . '/wp-includes/js/jquery/jquery.min.js', array(), null, true);
  }

  // Check if Bootstrap JS is enqueued or registered.
  if (!wp_script_is('bootstrap', 'enqueued') && !wp_script_is('bootstrap', 'registered')) {
    // Load Bootstrap JS in the footer, ensuring jQuery is a dependency.
    wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
  }

  //wp_enqueue_style('color-modes', plugin_dir_url(__FILE__) . 'css/color-modes.css');

  wp_enqueue_script('wa-timetable', plugin_dir_url(__FILE__) . 'js/wa-timetable.js', array('jquery', 'bootstrap'), null, true);
  //wp_enqueue_script('color-modes', plugin_dir_url(__FILE__) . 'js/color-modes.js', array('jquery', 'bootstrap'), null, true);

}

/**
 * Handles automatic updates for the plugin from a GitHub repository.
 *
 * This code block is a self-contained updater that allows WordPress to check a
 * GitHub repository for new releases and handle the update process.
 *
 */

class WA_Timetable_Updater {

    private $plugin_file;
    private $github_username;
    private $github_repo_name;
    private $update_json_url;

    public function __construct($plugin_file, $github_username, $github_repo_name) {
        $this->plugin_file = $plugin_file;
        $this->github_username = $github_username;
        $this->github_repo_name = $github_repo_name;
        $this->update_json_url = 'https://raw.githubusercontent.com/' . $github_username . '/' . $github_repo_name . '/main/update.json';
    }

    public function init() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update_for_plugin_upgrades']);
        add_filter('plugins_api', [$this, 'add_plugin_details_info'], 10, 3);
    }

    public function check_update_for_plugin_upgrades($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $plugin_info = get_plugin_data($this->plugin_file);
        $current_version = $plugin_info['Version'];

        $remote_info = $this->get_remote_plugin_info();

        if ($remote_info && version_compare($current_version, $remote_info->version, '<')) {
            $new_update = new stdClass();
            $new_update->slug = dirname(plugin_basename($this->plugin_file));
            $new_update->new_version = $remote_info->version;
            $new_update->url = $remote_info->homepage;
            $new_update->package = $remote_info->download_url;
            $transient->response[plugin_basename($this->plugin_file)] = $new_update;
        }

        return $transient;
    }

    public function add_plugin_details_info($res, $action, $args) {
        if ('plugin_information' !== $action) {
            return $res;
        }

        if (empty($args->slug)) {
            return $res;
        }

        if ($args->slug !== dirname(plugin_basename($this->plugin_file))) {
            return $res;
        }

        $remote_info = $this->get_remote_plugin_info();

        if ($remote_info) {
            $res = new stdClass();
            $res->slug = $remote_info->slug;
            $res->plugin = plugin_basename($this->plugin_file);
            $res->name = $remote_info->name;
            $res->version = $remote_info->version;
            $res->author = $remote_info->author;
            $res->author_profile = $remote_info->author_profile;
            $res->last_updated = $remote_info->last_updated;
            $res->homepage = $remote_info->homepage;
            $res->requires = $remote_info->requires;
            $res->tested = $remote_info->tested;
            $res->requires_php = $remote_info->requires_php;
            $res->download_link = $remote_info->download_url;
            $res->banners = $remote_info->banners;
            $res->sections = $remote_info->sections;
        }

        return $res;
    }

    private function get_remote_plugin_info() {
        $transient_key = 'wa_timetable_github_info';
        $cached_info = get_transient($transient_key);

        if ($cached_info) {
            return $cached_info;
        }

        $response = wp_remote_get($this->update_json_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $remote_info = json_decode(wp_remote_retrieve_body($response));

        if ($remote_info) {
            set_transient($transient_key, $remote_info, DAY_IN_SECONDS);
        }

        return $remote_info;
    }
}

$github_updater = new WA_Timetable_Updater(__FILE__, 'smoothdeisgns', 'wa-timetable');
$github_updater->init();


