<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Handles all view logic and HTML generation for the timetable display.
 */
class WA_Timetable_View
{
  /**
   * Generates the complete HTML output for the timetable.
   *
   * @param array $phases The processed timetable data, grouped by date.
   * @return string The generated HTML.
   */
  public function generate_html($phases)
  {
    $jamaica_timezone = new DateTimeZone('America/Jamaica');
    $current_date_jamaica = new DateTime('now', $jamaica_timezone);
    $current_date_formatted = $current_date_jamaica->format('M d');
    $current_timestamp = $current_date_jamaica->getTimestamp();

    $active_tab_id = null;
    foreach (array_keys($phases) as $date_label) {
      $date_part = explode(' - ', $date_label)[1] ?? '';
      if ($date_part === $current_date_formatted) {
        $active_tab_id = sanitize_title($date_label);
        break;
      }
    }

    if (is_null($active_tab_id)) {
      $date_labels = array_keys($phases);
      $first_date_label = reset($date_labels);
      $active_tab_id = sanitize_title($first_date_label);
    }

    $output = '<div class="wa-timetable-container">';

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

    $output .= '<div class="tab-content mt-3">';
    foreach ($phases as $date_label => $sessions_for_date) {
      $tab_id = sanitize_title($date_label);
      $active_class = ($tab_id === $active_tab_id) ? 'show active' : '';
      $output .= '<div class="tab-pane fade ' . $active_class . '" id="' . $tab_id . '" role="tabpanel" aria-labelledby="' . $tab_id . '-tab">';
      $output .= '<div class="accordion" id="accordion-' . $tab_id . '">';
      foreach ($sessions_for_date as $session_name => $events_for_session) {
        $session_id = sanitize_title($date_label . '-' . $session_name);
        $show_class = 'show';
        $expanded_state = 'true';
        $output .= '<div class="accordion-item border-0">';
        $output .= '<h2 class="accordion-header p-0 my-0" id="heading-' . $session_id . '">';
        $output .= '<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $session_id . '" aria-expanded="' . $expanded_state . '" aria-controls="collapse-' . $session_id . '">';
        $output .= '<div class="session-wrapper d-flex flex-column lh-1 gap-1">';
        $output .= '<span class="session-name" style="font-size: 20px; text-transform: uppercase;">' . esc_html($session_name);
        $all_results_published = true;
        foreach ($events_for_session as $event) {
          if (!$this->check_units_for_status($event, 'isResultPublished')) {
            $all_results_published = false;
            break;
          }
        }
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
          $disciplineName = $event->discipline->name ?? 'N/A';
          $phaseName = $event->phaseName ?? 'N/A';
          $event_id = $event->id ?? uniqid();
          $phase_order = $event->phaseOrder ?? uniqid();
          $sex_label = '';
          if (!empty($event->sexNameUrlSlug)) {
            $sex_label = ucfirst($event->sexNameUrlSlug);
            if ($sex_label === 'Men' || $sex_label === 'Women') {
              $sex_label .= '\'s';
            }
          } else if (!empty($event->sexCode)) {
            switch ($event->sexCode) {
              case 'M':
                $sex_label = 'Men\'s';
                break;
              case 'W':
                $sex_label = 'Women\'s';
                break;
              case 'X':
                $sex_label = 'Mixed';
                break;
              default:
                $sex_label = 'N/A';
            }
          } else {
            $sex_label = 'N/A';
          }

          $base_url = 'https://worldathletics.org/competitions/world-athletics-championships/tokyo25/results/';
          $sex_slug = $event->sexNameUrlSlug ?? '';
          $discipline_slug = $event->discipline->nameUrlSlug ?? '';
          $phase_slug = $event->phaseNameUrlSlug ?? '';
          $is_multievent = strpos($event->phaseName, 'Decathlon') !== false || strpos($event->phaseName, 'Heptathlon') !== false;

          if ($is_multievent) {
            $url_path = $sex_slug . '/' . $phase_slug . '/' . $discipline_slug;
          } else {
            $url_path = $sex_slug . '/' . $discipline_slug . '/' . $phase_slug;
          }

          $startlist_url = $base_url . $url_path . '/startlist';
          $results_url = $base_url . $url_path . '/results';
          $summary_url = $base_url . $url_path . '/summary';
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

          $output .= '<div class="event-item" id="event-' . esc_attr($event_id) . '-' . esc_attr($phase_order) . '">';
          $output .= '<div class="event-item-content">';
          $output .= '<div class="event-details-left">';
          $output .= '<div class="event-name">' . esc_html($sex_label) . ' ' . esc_html($disciplineName) . '</div>';
          $output .= '<div class="event-phase-container"><span style="border-radius: 4px; padding: 2px 6px; line-height: 1; background-color: ' . esc_attr($bgColor) . ';">' . esc_html($phaseName) . '</span></div>';
          $output .= '</div>';
          $output .= '<div class="event-details-right">';
          $output .= '<div class="event-livetime-wrapper">';

          $all_units_have_results = $this->check_units_for_status($event, 'isResultPublished');
          $event_start_timestamp = $event->jamaica_datetime_object->getTimestamp();
          $should_show_live_badge = ($current_timestamp >= ($event_start_timestamp - 300)) && !$all_units_have_results;

          if ($should_show_live_badge) {
            $output .= '<div class="live-badge"><div class="pulse-circle"></div><span>LIVE</span></div>';
          }

          $output .= '<div class="event-time">' . esc_html($event->jamaica_datetime_object->format('g:i A')) . '</div>';
          $output .= '</div>';
          $output .= '<div class="event-links">';
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

  private function check_units_for_status($event, $status_key)
  {
    if (empty($event->units) || !is_array($event->units)) {
      return $event->{$status_key} ?? false;
    }

    foreach ($event->units as $unit) {
      if (!($unit->{$status_key} ?? false)) {
        return false;
      }
    }

    return true;
  }
}
