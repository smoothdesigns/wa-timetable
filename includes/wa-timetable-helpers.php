<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Generates HTML <option> tags for all available timezones.
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
