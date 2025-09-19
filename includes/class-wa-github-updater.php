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
		error_log("WA Timetable Updater: plugins_api called. Action: {$action}, Slug: " . ($args->slug ?? 'N/A'));

		// Ensure this is the correct API call and slug.
		if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->plugin_slug) {
			error_log("WA Timetable Updater: Mismatch in API call or slug. Returning original result.");
			return $result;
		}

		// Fetch the remote info from our JSON file.
		$remote_info = $this->get_remote_json_info();

		// If no remote info is found, return the original result to prevent a "not found" error.
		if (!$remote_info) {
			error_log("WA Timetable Updater: Failed to get remote info. Returning original result.");
			return $result;
		}

		error_log("WA Timetable Updater: Successfully fetched remote info. Building response.");

		// The API expects a properly formatted object. Create one and populate it with data from our JSON.
		$new_info = new stdClass();

		// Populate required fields.
		$new_info->name = $remote_info->name ?? 'WA Timetable'; // Fallback name
		$new_info->slug = $remote_info->slug ?? $this->plugin_slug;
		$new_info->version = $remote_info->version;
		$new_info->author = $remote_info->author ?? 'Thomas Mirmo';
		$new_info->requires = $remote_info->requires ?? '5.3';
		$new_info->tested = $remote_info->tested ?? '6.8.2';
		$new_info->homepage = $remote_info->homepage ?? $this->github_api_url;
		$new_info->download_link = $remote_info->download_link;

		// Populate optional sections.
		$new_info->sections = [];
		if (isset($remote_info->sections) && is_array($remote_info->sections)) {
			$new_info->sections = $remote_info->sections;
		}

		// Handle banners by proxying them through WordPress's admin-ajax.php to avoid mixed content errors.
		$new_info->banners = [];
		if (isset($remote_info->banners) && is_array($remote_info->banners)) {
			foreach ($remote_info->banners as $key => $url) {
				$new_info->banners[$key] = admin_url('admin-ajax.php?action=wa_timetable_proxy&url=' . urlencode($url));
			}
		}

		error_log("WA Timetable Updater: Returning new info object.");
		// error_log('API Response Object: ' . print_r($new_info, true));

		return $new_info;
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
		error_log("WA Timetable Updater: Attempting to fetch JSON from: {$this->json_url}");
		$response = wp_remote_get($this->json_url);

		// Check for valid response and successful status code.
		if (is_wp_error($response)) {
			error_log('WA Timetable Updater: WP_Error on remote get: ' . $response->get_error_message());
			return false;
		}

		$http_code = wp_remote_retrieve_response_code($response);
		if ($http_code !== 200) {
			error_log("WA Timetable Updater: HTTP request failed with status code: {$http_code}");
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$info = json_decode($body);

		if ($info === null || !is_object($info)) {
			error_log('WA Timetable Updater: Failed to decode JSON or JSON is not an object. Body: ' . substr($body, 0, 100) . '...');
			return false;
		}

		// Ensure key properties are arrays to prevent fatal errors later on.
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
