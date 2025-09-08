<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Handles the [wa_timetable] shortcode functionality.
 */
class WATimetableShortcode
{
  /**
   * Constructor to register the shortcode.
   */
  public function __construct()
  {
    add_shortcode('wa_timetable', [$this, 'render_shortcode']);
  }

  /**
   * Shortcode to display the timetable. Fetches and processes data from World Athletics.
   *
   * @return string The HTML output for the timetable.
   */
  public function render_shortcode()
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
      wp_enqueue_script('wa-timetable-dynamic');
      return '<div id="wa-timetable-container"><div class="alert alert-info">Loading timetable data...</div><div id="wa-timetable-data" style="display:none;"></div></div>';
    } else {
      $json_data = $script_tag->textContent;
      $data = json_decode($json_data, true);

      if (!$data || !isset($data['props']['pageProps']['eventTimetable'])) {
        wp_enqueue_script('wa-timetable-dynamic');
        return '<div id="wa-timetable-container"><div class="alert alert-info">Loading timetable data...</div><div id="wa-timetable-data" style="display:none;"></div></div>';
      } else {
        $event_name_url_slug = $data['props']['pageProps']['page']['event']['nameUrlSlug'];
        return $this->process_event_timetable_data($data, $event_name_url_slug);
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
  private function process_event_timetable_data($data, $event_name_url_slug)
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
      $output .= '<span>' . __('DAY', 'wa-timetable') . ' ' . ($index + 1) . '</span>';
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
      $output .= '<p class="fs-6 lh-1 mb-0">' . __('DAY', 'wa-timetable') . ' ' . ($index + 1) . ' - ' . $day_key . '</p>';
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
              $startlist_link = $event['isStartlistPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/startlist">' . __('Startlist', 'wa-timetable') . '</a>' : '';
              $result_link = $event['isResultPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/result">' . __('Result', 'wa-timetable') . '</a>' : '';
              $summary_link = $event['isPhaseSummaryPublished'] ? '<a href="' . $base_url . $event['sexNameUrlSlug'] . '/' . $event['nameUrlSlug'] . '/' . $event['phaseNameUrlSlug'] . '/summary">' . __('Summary', 'wa-timetable') . '</a>' : '';

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
        $output .= '<p>' . __('No events scheduled for this day.', 'wa-timetable') . '</p>';
      }
      $output .= '</div>';
    }
    $output .= '</div></div>';
    return $output;
  }
}
