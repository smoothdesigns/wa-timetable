=== WA Timetable (Tokyo 2025) ===

Contributors: smoothdesigns
Tags: timetable, athletics, sports, schedule, events, tokyo 2025, world championships
Requires at least: 5.3
Tested up to: 6.8.2
Requires PHP: 7.2
Stable tag: 3.9.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WA Timetable is a lightweight and user-friendly WordPress plugin designed to display the official timetable for the World Athletics Championships in Tokyo. Whether you're a sports fan or a professional event organizer, this plugin provides a simple way to showcase schedules and events in a clear, accessible format.

== Description ==

This plugin fetches live data from the World Athletics website to display an accurate, up-to-the-minute timetable for the 2025 Championships in Tokyo. It automatically converts event times to Jamaican time, providing a seamless experience for a Jamaican audience. The timetable is fully responsive and organized into daily tabs and collapsible sessions for easy navigation.

Features:

* **Live Data Feed**: Automatically updates with the latest schedule and results.
* **Jamaica Time Conversion**: All times are converted from Tokyo to Jamaican time (GMT-5).
* **Responsive Design**: Timetables look great on any device, from desktop computers to mobile phones.
* **Session Organization**: Events are grouped by daily sessions (Morning/Evening) for clarity.
* **Status Indicators**: Events show live status (`LIVE`), or link to `Results` or `Startlist`.
* **Shortcode Integration**: Easily embed the timetable on any page or post using the `[wa_timetable]` shortcode.
* **Automatic GitHub Updates**: The plugin automatically checks for and installs updates from the GitHub repository.

== Installation ==

1.  Upload the `wa-timetable` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Add the shortcode `[wa_timetable]` to any page, post, or widget where you want the timetable to appear.
4.  That's it! The timetable will automatically appear.

== Screenshots ==

1. The responsive timetable view with daily tabs.
2. The timetable showing a live session with event status.
3. An individual event section with live badges and links.

== Changelog ==

= 3.9.4 =
* **FIXED:** Corrected the GitHub updater URL to `raw.githubusercontent.com` to resolve the "Plugin not found" error when viewing plugin details.
* **FIXED:** Updated the code to properly handle the `reset()` function, fixing the "Only variables can be passed by reference" fatal error on modern PHP versions.

= 3.9.3 =
* Initial release of the WA Timetable (Tokyo 2025) plugin.