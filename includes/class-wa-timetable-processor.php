<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Handles all data processing, including timezone conversion and event grouping.
 */
class WA_Timetable_Processor
{
  /**
   * Processes raw data into a structured format grouped by date and session.
   *
   * @param object $data The decoded JSON data.
   * @return array The processed data, grouped by day and session.
   */
  public function process($data)
  {
    $event_timetable = $data->props->pageProps->eventTimetable;
    $grouped_data = [];
    $tokyo_timezone = new DateTimeZone('Asia/Tokyo');
    $jamaica_timezone = new DateTimeZone('America/Jamaica');

    usort($event_timetable, function ($a, $b) {
      $dateA = new DateTime($a->phaseDateAndTime ?? '');
      $dateB = new DateTime($b->phaseDateAndTime ?? '');
      return $dateA <=> $dateB;
    });

    $current_date = '';
    $day_number = 1;

    foreach ($event_timetable as $event) {
      if (isset($event->unitTypeName) && $event->unitTypeName === 'Group' && isset($event->unitName)) {
        $event->phaseName .= ' - Group ' . $event->unitName;
      }

      $event_datetime_tokyo = new DateTime($event->phaseDateAndTime ?? '', $tokyo_timezone);
      $event_datetime_jamaica = clone $event_datetime_tokyo;
      $event_datetime_jamaica->sub(new DateInterval('PT14H'));
      $event->jamaica_datetime_object = $event_datetime_jamaica;

      if (isset($event->phaseEndDateAndTime) && !empty($event->phaseEndDateAndTime)) {
        $event_end_datetime_tokyo = new DateTime($event->phaseEndDateAndTime, $tokyo_timezone);
        $event->jamaica_end_datetime_object = clone $event_end_datetime_tokyo;
        $event->jamaica_end_datetime_object->sub(new DateInterval('PT14H'));
      } else {
        $event->jamaica_end_datetime_object = null;
      }

      $event_date_key = $event_datetime_jamaica->format('Y-m-d');
      $session_name = $event->phaseSessionName ?? 'No Session';

      if ($session_name === 'Morning Session') {
        $session_name = 'Evening Session';
      } elseif ($session_name === 'Evening Session') {
        $session_name = 'Morning Session';
      }

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
