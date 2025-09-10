WA Timetable (Tokyo 2025)
Contributors: smoothdesigns
Tags: timetable, sports, world athletics, tokyo 2025, world championships, track and field
Requires at least: 5.3
Tested up to: 6.8.2
Requires PHP: 7.2
Stable tag: 2.0.5
License: GPL-2.0-or-later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Banners
https://raw.githubusercontent.com/smoothdesigns/wa-timetable/main/assets/low-res-banner.png
https://raw.githubusercontent.com/smoothdesigns/wa-timetable/main/assets/high-res-banner.png

Displays the official 2025 World Athletics Championships timetable from Tokyo, Japan. Times are converted by default from Tokyo to Jamaican time, with options for more time zones in the settings page.

Description
WA Timetable is a lightweight and user-friendly WordPress plugin designed to display stylish timetables on your website. Whether you're a sports fan or a professional event organizer, this plugin provides a simple way to showcase schedules and events in a clear, accessible format.

Fully Responsive: Timetables look great on any device, from desktop computers to mobile phones.
Customizable: Easily change the look and feel of your timetable to match your website's theme via the plugin settings.
Shortcode Integration: Simply use a shortcode to embed the timetable on any page or post.

Installation

Download the plugin ZIP file.

In your WordPress admin dashboard, go to Plugins > Add New.

Click the "Upload Plugin" button.

Choose the downloaded ZIP file and click "Install Now".

After the installation is complete, click "Activate Plugin".

Screenshots

This is a screenshot of the timetable displayed on a page.

Changelog
2.0.5
Refactored core plugin file to introduce a new constant for the assets folder.
Updated all enqueue URLs to use the new asset constant for better file path management.

2.0.4
Relocated CSS and JS folders to the assets directory, and adjusted the enqueue URLs accordingly.

2.0.3
Resolved "Plugin not found" error by fixing an object-to-array conversion issue.
Added support for banners by updating readme.txt format and PHP parsing logic.
Corrected text formatting and list display issues in the "View details" page.
Eliminated the duplicate "View details" link.
Updated GitHub username from smoothdeisgns to smoothdesigns.

2.0.2
Switched to self-contained GitHub updater class.
Removed external dependency to plugin-update-checker library.

2.0.1
Fixed a bug where a non-existent timetable URL would break the plugin.

2.0.0
Major release to support the 2025 World Athletics Championships in Tokyo.
Timetable data is now fetched and parsed dynamically from the official website.
Added settings page for customization of timezones and session names.

Contribution
We welcome contributions! If you
