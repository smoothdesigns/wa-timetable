<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the GitHub-based plugin updates by parsing a standard readme.txt file.
 * This class fetches update information from a `readme.txt` file on the GitHub repository.
 * It hooks into WordPress's plugin update transient to check for and apply updates.
 */
class WAGitHubUpdater
{
    private $github_api_url;
    private $plugin_file;
    private $plugin_slug;
    private $json_url;

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
        $this->github_api_url = trailingslashit($github_repo_url);
        $this->json_url = $this->github_api_url . 'plugin-info.json';

        // Add a filter to modify the plugins update transient.
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);

        // Add a filter to handle the plugin information display.
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);

        // Add a filter to add "View details" link to the plugins page.
        add_filter('plugin_row_meta', [$this, 'add_plugin_row_meta'], 10, 2);

        // Add a hook to handle the image proxy request.
        add_action('wp_ajax_wa_timetable_proxy', [$this, 'do_banner_proxy']);
        add_action('wp_ajax_nopriv_wa_timetable_proxy', [$this, 'do_banner_proxy']);
    }

    /**
     * Checks for updates by fetching the `plugin-info.json` file.
     *
     * @param object $transient The plugins update transient.
     * @return object The modified transient object.
     */
    public function check_for_updates($transient)
    {
        if (empty($transient->checked) || !is_object($transient)) {
            return $transient;
        }

        $plugin_info = get_plugin_data($this->plugin_file);
        $current_version = $plugin_info['Version'];

        $remote_info = $this->get_remote_json_info();
        if ($remote_info && version_compare($current_version, $remote_info->version, '<')) {
            $transient->response[$this->plugin_slug . '/' . basename($this->plugin_file)] = (object) [
                'slug' => $this->plugin_slug,
                'new_version' => $remote_info->version,
                'url' => $this->github_api_url,
                'package' => $remote_info->download_link,
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
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $remote_info = $this->get_remote_json_info();
        if ($remote_info) {
            // Modify banner URLs to point to our proxy.
            if (isset($remote_info->banners) && is_array($remote_info->banners)) {
                foreach ($remote_info->banners as $key => $url) {
                    $remote_info->banners[$key] = admin_url('admin-ajax.php?action=wa_timetable_proxy&url=' . urlencode($url));
                }
            }
            return $remote_info;
        }

        return $result;
    }

    /**
     * Adds a "View details" link to the plugin's row on the plugins page.
     *
     * @param array $links The array of plugin row links.
     * @param string $plugin_file The plugin file name.
     * @return array The modified array of links.
     */
    public function add_plugin_row_meta($links, $plugin_file)
    {
        if (plugin_basename($this->plugin_file) === $plugin_file) {
            $links[] = '<a href="' . network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $this->plugin_slug . '&TB_iframe=true&width=600&height=550') . '" class="thickbox open-plugin-details-modal">View details</a>';
        }
        return $links;
    }

    /**
     * Acts as an image proxy to serve external images.
     */
    public function do_banner_proxy()
    {
        if (!isset($_GET['url'])) {
            wp_send_json_error('URL parameter missing.');
        }

        $url = urldecode($_GET['url']);
        $image_data = wp_remote_get($url);

        if (is_wp_error($image_data) || wp_remote_retrieve_response_code($image_data) !== 200) {
            status_header(404);
            die();
        }

        $headers = wp_remote_retrieve_headers($image_data);
        $body = wp_remote_retrieve_body($image_data);

        // Set the content type header from the fetched data.
        $content_type = isset($headers['content-type']) ? $headers['content-type'] : 'application/octet-stream';
        header('Content-Type: ' . $content_type);

        // Set the content length.
        header('Content-Length: ' . strlen($body));

        echo $body;
        die();
    }

    /**
     * Fetches plugin information from the GitHub repository's `plugin-info.json`.
     *
     * @return object|false The plugin info object, or false on failure.
     */
    private function get_remote_json_info()
    {
        $response = wp_remote_get($this->json_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $info = json_decode($body);

        if ($info === null || !is_object($info)) {
            return false;
        }

        // Cast key properties to array to prevent a fatal error.
        if (isset($info->sections) && is_object($info->sections)) {
            $info->sections = (array) $info->sections;
        }
        if (isset($info->banners) && is_object($info->banners)) {
            $info->banners = (array) $info->banners;
        }
        if (isset($info->screenshots) && is_object($info->screenshots)) {
            $info->screenshots = (array) $info->screenshots;
        }

        return $info;
    }
}