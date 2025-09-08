<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Handles the plugin's settings page and related functionality.
 */
class WATimetableSettings
{
  /**
   * Constructor to set up the settings page hooks.
   */
  public function __construct()
  {
    add_action('admin_menu', [$this, 'add_settings_page']);
    add_filter('plugin_action_links_' . plugin_basename(WA_TIMETABLE_PLUGIN_DIR . 'wa-timetable.php'), [$this, 'add_settings_link']);
  }

  /**
   * Adds the settings page to the WordPress admin menu.
   */
  public function add_settings_page()
  {
    add_options_page(
      'WA Timetable Settings',
      'WA Timetable',
      'manage_options',
      'wa-timetable-settings',
      [$this, 'settings_page_html']
    );
  }

  /**
   * Adds a "Settings" link to the plugin's action links on the plugins page.
   *
   * @param array $links The array of plugin action links.
   * @return array The modified array of links.
   */
  public function add_settings_link($links)
  {
    $settings_link = '<a href="options-general.php?page=wa-timetable-settings">' . __('Settings', 'wa-timetable') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
  }

  /**
   * Renders the HTML for the plugin's settings page.
   */
  public function settings_page_html()
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
      $headers = array_map('sanitize_text_field', (array) $_POST['headers']);
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
      echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'wa-timetable') . '</p></div>';
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
      <form method="post">
        <?php wp_nonce_field('wa_timetable_settings_action', 'wa_timetable_settings_nonce'); ?>
        <table class="form-table">
          <tr valign="top">
            <th scope="row"><label for="timetable_url"><?php _e('Timetable URL', 'wa-timetable'); ?></label></th>
            <td><input type="text" id="timetable_url" name="timetable_url" value="<?php echo esc_attr($timetable_url); ?>" class="large-text" style="width: 100%;" /></td>
          </tr>
          <tr valign="top">
            <th scope="row"><label for="timeout"><?php _e('Timeout (seconds)', 'wa-timetable'); ?></label></th>
            <td><input type="number" id="timeout" name="timeout" value="<?php echo esc_attr($timeout); ?>" style="width: 80px;" /></td>
          </tr>
          <tr valign="top">
            <th scope="row"><label for="timetable_timezone"><?php _e('Timetable Timezone', 'wa-timetable'); ?></label></th>
            <td>
              <select id="timetable_timezone" name="timetable_timezone">
                <?php echo wa_timezone_options($timetable_timezone); ?>
              </select>
              <p class="description"><?php _e('The timezone of the timetable data (e.g., Asia/Tokyo).', 'wa-timetable'); ?></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label for="conversion_timezone"><?php _e('Conversion Timezone', 'wa-timetable'); ?></label></th>
            <td>
              <select id="conversion_timezone" name="conversion_timezone">
                <?php echo wa_timezone_options($conversion_timezone); ?>
              </select>
              <p class="description"><?php _e('The timezone to convert the timetable times to (e.g., America/Jamaica).', 'wa-timetable'); ?></p>
            </td>
          </tr>
          <?php foreach ($headers as $index => $header) : ?>
            <tr valign="top">
              <th scope="row"><label for="header_<?php echo $index; ?>"><?php _e('Header', 'wa-timetable'); ?> <?php echo $index + 1; ?></label></th>
              <td><input type="text" id="header_<?php echo $index; ?>" name="headers[]" value="<?php echo esc_attr($header); ?>" class="regular-text" /></td>
            </tr>
          <?php endforeach; ?>
          <tr valign="top">
            <th scope="row"><label for="morning_session_name"><?php _e('Morning Session Name', 'wa-timetable'); ?></label></th>
            <td><input type="text" id="morning_session_name" name="morning_session_name" value="<?php echo esc_attr($morning_session_name); ?>" class="regular-text" />
              <p class="description"><?php _e("The name to display for the 'Morning Session' after conversion.", 'wa-timetable'); ?></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label for="evening_session_name"><?php _e('Evening Session Name', 'wa-timetable'); ?></label></th>
            <td><input type="text" id="evening_session_name" name="evening_session_name" value="<?php echo esc_attr($evening_session_name); ?>" class="regular-text" />
              <p class="description"><?php _e("The name to display for the 'Evening Session' after conversion.", 'wa-timetable'); ?></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label for="afternoon_session_name"><?php _e('Afternoon Session Name', 'wa-timetable'); ?></label></th>
            <td><input type="text" id="afternoon_session_name" name="afternoon_session_name" value="<?php echo esc_attr($afternoon_session_name); ?>" class="regular-text" />
              <p class="description"><?php _e("The name to display for the 'Afternoon Session' after conversion (leave blank to hide).", 'wa-timetable'); ?></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><?php _e('Links Base URL', 'wa-timetable'); ?></th>
            <td> https://worldathletics.org/en/competitions/world-athletics-championships/ </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
<?php
  }
}
