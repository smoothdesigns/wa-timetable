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
    private $readme_url;

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
        $this->readme_url = $this->github_api_url . 'readme.txt';

        // Add a filter to modify the plugins update transient.
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);

        // Add a filter to handle the plugin information display.
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);

        // Add a filter to add "View details" link on the plugins page.
        add_filter('plugin_row_meta', [$this, 'add_plugin_row_meta'], 10, 2);
    }

    /**
     * Checks for updates by fetching the `readme.txt` file.
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

        $remote_info = $this->get_remote_readme_info();
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

        $remote_info = $this->get_remote_readme_info();
        if ($remote_info) {
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
     * Fetches plugin information from the GitHub repository's `readme.txt`.
     *
     * @return object|false The plugin info object, or false on failure.
     */
    private function get_remote_readme_info()
    {
        $response = wp_remote_get($this->readme_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $contents = wp_remote_retrieve_body($response);

        // Parse the readme contents to get plugin info and sections.
        preg_match('/^Stable tag:\s*(\S+)/im', $contents, $matches);
        $version = isset($matches[1]) ? $matches[1] : '';

        preg_match('/^Requires at least:\s*(\S+)/im', $contents, $matches);
        $requires = isset($matches[1]) ? $matches[1] : '';

        preg_match('/^Tested up to:\s*(\S+)/im', $contents, $matches);
        $tested = isset($matches[1]) ? $matches[1] : '';

        preg_match('/^Requires PHP:\s*(\S+)/im', $contents, $matches);
        $requires_php = isset($matches[1]) ? $matches[1] : '';

        $sections = [];
        preg_match_all('/==\s*([^=]+?)\s*==\s*(.*?)(\n\n|$)/s', $contents, $sections_matches, PREG_SET_ORDER);
        foreach ($sections_matches as $match) {
            $title = trim($match[1]);
            $content = trim($match[2]);
            $sections[sanitize_title($title)] = $content;
        }

        $banners = [];
        if (preg_match_all('/^= Banners: (\d+x\d+) =\s*(.*?)(\n\n|$)/im', $contents, $banner_matches, PREG_SET_ORDER)) {
            foreach ($banner_matches as $match) {
                $size = trim($match[1]);
                $url = trim($match[2]);
                $banners[$size] = $url;
            }
        } else {
             preg_match_all('/^= Banners =\s*(.*?)(\n\n|$)/s', $contents, $banner_matches, PREG_SET_ORDER);
             if (isset($banner_matches[0][1])) {
                 $urls = explode("\n", trim($banner_matches[0][1]));
                 if (count($urls) === 2) {
                     $banners['772x250'] = trim($urls[0]);
                     $banners['1544x500'] = trim($urls[1]);
                 }
             }
        }

        $info = (object) [
            'slug' => $this->plugin_slug,
            'plugin_name' => 'WA Timetable (Tokyo 2025)',
            'name' => 'WA Timetable (Tokyo 2025)',
            'version' => $version,
            'author' => 'Thomas Mirmo',
            'author_profile' => 'https://github.com/smoothdesigns',
            'last_updated' => gmdate('Y-m-d H:i:s'),
            'homepage' => 'https://github.com/smoothdesigns/wa-timetable',
            'download_link' => $this->github_api_url . 'main.zip',
            'requires' => $requires,
            'tested' => $tested,
            'requires_php' => $requires_php,
            'sections' => $sections,
            'banners' => (object) $banners,
        ];

        return $info;
    }
}