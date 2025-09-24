<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

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

    $pattern = '/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s';
    $matches = [];

    if (preg_match($pattern, $body, $matches) && isset($matches[1])) {
      $json_string = $matches[1];
      $data = json_decode($json_string);

      if ($data === null) {
        return new WP_Error('json_decode_error', 'Failed to decode the JSON data.');
      }

      if (!isset($data->props->pageProps->eventTimetable)) {
        return new WP_Error('data_path_invalid', 'The expected data path within the JSON is invalid.');
      }

      return $data;
    } else {
      return new WP_Error('json_not_found', 'The data script tag was not found in the page source.');
    }
  }
}
