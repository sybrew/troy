=== Troy Client ===
Homepage URL: https://deploytroy.org/docs/troy-client/
Support URI: https://github.com/sybrew/troy/discussions
Locale: en_US
Short Description: Troy Client enables sideloading plugin updates and dependencies from any Troy Server.

== Description ==

Troy Client is a WordPress plugin that enables sideloading for plugin updates and plugin dependencies from any Troy Server.

Troy Client works by reading special plugin headers (`Troy:` and `Troy Dependencies:`) from installed plugins — even if they're not activated. When a plugin declares a Troy repository in its headers, Troy Client automatically handles update checks and installations from that repository.

With Troy Client, you can:

* Receive automatic plugin updates from any Troy Server repository
* Automatically install and update plugin dependencies declared via Troy headers
* Monitor repository communication status via the Site Health page
* Filter Troy-enabled plugins from WordPress.org update requests
* Completely hide bespoke plugins from all external communications

= How It Works =

Plugins that support Troy include headers like:

* `Troy: repo.example.org` — Declares the Troy repository for the plugin
* `Troy Dependencies: plugin-slug <repo.example.org>` — Declares dependencies to be auto-installed
* `Troy: disable-all-communications` — Hides the plugin from all external update requests

Troy Client reads these headers and communicates with the specified Troy Server repositories to check for updates, download new versions, and install dependencies automatically.

If a plugin has registered dependencies, future updates for those dependencies will be fetched from the registered Troy Server instead of WordPress.org.

= Privacy =

Troy Client is designed with privacy in mind:

* Troy-enabled plugins are automatically removed from requests to WordPress.org
* Plugin data is only shared with the specific repository that hosts each plugin — never cross-shared between repositories
* A rotating anonymous site identifier (refreshed weekly) is used for update requests
* Use `Troy: disable-all-communications` to hide bespoke plugins from all external communications, including WordPress.org

= Self-Checking =

Troy Client checks for updates for itself via the Troy Server at repo.deploytroy.org.

== Installation ==

Troy Client is not available on WordPress.org. To install Troy Client:

1. Download the latest version from [repo.deploytroy.org](https://repo.deploytroy.org/plugin/get/zip/troy-client).
1. Upload the ZIP file via Plugins > Add New > Upload Plugin.
1. Activate the plugin through the Plugins menu.

Once activated, Troy Client will automatically detect any installed plugins with Troy headers and manage their updates.

= Requirements =

* WordPress 6.7 or higher
* PHP 7.4 or higher

= Multisite =

Troy Client requires network activation on WordPress multisite installations.

== Changelog ==

= 1.6.1184 =

* Added user agent filtering for all HTTP requests to Troy Servers to prevent site URL leakage.
* Added privacy policy link to the plugin row meta.
* Fixed image validation to explicitly set anonymous user agent.
* Fixed plugin data caching ignoring field requirements, causing sporadic empty fields in plugin info.

= 1.4.1184 =

* Fixed PHP warning for Shiny Updates where a required update header was missing.

= 1.3.1184 =

* Monorepo version bump. Nothing changed for Troy Client.

= 1.2.1184 =

* Shed proof of concept. We're running out of farm buildings!

= 1.1.1184 =

* Barn proof of concept.

= 1.0.1184 =

* Stable proof of concept.

= 0.0.1184 =

* Initial proof of concept.
