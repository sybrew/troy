=== Troy Client ===
Homepage URL: https://deploytroy.org/docs/advanced/troy-client-daemon/
Support URI: https://github.com/sybrew/troy/discussions
Locale: en_US
Short Description: Troy Client Daemon forces installation and activation of Troy Client and blocks WordPress.org update API if Troy Client is not active.

== Description ==

This is not a regular plugin. Troy Client Daemon is a must-use plugin that enforces Troy Client on a site and prevents data leaks to WordPress.org.

The daemon installs Troy Client automatically if missing, and activates it if inactive. It blocks WordPress.org update APIs until Troy Client is active. When using the `-skip-plugins` flag during updates via WP CLI, the daemon can halt the update process.

To use it, place `troy-client-daemon.php` directly in `/wp-content/mu-plugins/` (not in a subfolder); no activation is required — WordPress loads MU plugins automatically.

== Changelog ==

= 1.7.1184 =

* Added an install lock to prevent concurrent or rapid-fire install attempts when Troy Client installation fails.
* Fixed `http_headers_useragent` callbacks crashing when third-party plugins apply the filter without the URL parameter.

= 1.6.1184 =

* Added a safeguard to deactivate itself and throw an error if activated normally instead of as an MU plugin.
* Fixed an issue where translation files were loaded too early during the installation of Troy Client.

= 1.3.1184 =

* Monorepo version bump. Nothing changed for Troy Client.

= 0.0.1184 =

* Initial proof of concept.
