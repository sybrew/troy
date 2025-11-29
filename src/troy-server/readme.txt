=== Troy Server ===
Homepage URL: https://deploytroy.org/docs/troy-server/how-it-works/
Support URI: https://github.com/sybrew/troy/discussions
Locale: en_US
Short Description: Troy Server allows you to distribute WordPress plugins from your own update repository.

== Description ==

Troy Server is a WordPress plugin that transforms your site into a plugin update server for sites running Troy Client.

With Troy Server, you can:

* Host your own plugin repository independent from WordPress.org
* Distribute plugin updates through native WordPress update mechanisms
* Create installer packages that bundle multiple plugins together
* Collect anonymous usage statistics (active installs, PHP/WP versions, locales)
* Manage multiple plugin versions and their metadata
* Upload plugins via ZIP files using the WordPress Block Editor
* Connect external sources (GitHub, WordPress.org) for automated releases
* View integration logs and processing history

= How It Works =

Troy Server provides a complete admin interface integrated with WordPress:

* **List views** — Manage your plugins and packages with familiar WordPress list tables, including bulk actions and filtering
* **Block Editor** — Create and edit plugin entries using the WordPress Block Editor with custom components for metadata, version management, and integrations
* **Settings page** — Configure your repository through a modern settings interface with multiple tabs for general settings, statistics, and logs
* **REST API** — Internal REST endpoints for the admin interface and external update endpoints for Troy Client

To upload a plugin, create a new plugin post, fill in the required fields, and upload a ZIP file or enter a ZIP URL. Troy Server automatically extracts plugin information from the main plugin file and readme.txt, including version, description, requirements, contributors, and changelog.

= Automated Integrations =

Troy Server can connect to external sources to automate plugin uploads:

* **GitHub repositories** — Connect public or private repositories using a Personal Access Token. Automatically fetch release ZIP files when new tags are pushed.
* **WordPress.org** — Mirror plugins from the official repository to redistribute through your own server.

Once connected, Troy Server can automatically fetch new releases by tag type (stable tags, beta versions, or all), or process them manually. Integration history is logged with status tracking, retry attempts, and error reasons.

= Troy Packages =

Troy Server can generate installer packages — small ZIP files containing a tiny installer plugin. This installer sets up Troy Client and your selection of plugins on any WordPress site. Distribute these packages via a simple download link to help clients easily install everything they need.

Packages can include:

* Troy Client for automatic updates
* Any plugins hosted on your repository
* Custom configurations

= Statistics =

Troy Server collects anonymous statistics about registered plugins:

* Download counts and active installations per version
* PHP and WordPress version distribution
* Locale distribution across installations
* Request counts per epoch (weekly periods)

All data is anonymized via rotating unique identifiers (refreshed weekly) to protect user privacy. Statistics are aggregated from live request data into summary tables for efficient querying.

= Logs =

Troy Server maintains logs for monitoring and debugging:

* **Integration history** — Track GitHub and WordPress.org sync operations with status, error messages, and retry counts
* **Auto-refresh** — Monitor logs in real-time with automatic refresh

= Plugin Status =

Plugins can have different visibility statuses:

* **Public** — Available to all Troy Client users
* **Unlisted** — Updates served only to users who already have the plugin
* **Protected** — Reserved for future access control features
* **Pending** — Not yet available for distribution
* **Disabled** — Blocked from all update requests

= Updating =

Troy Server checks for updates via Troy Client via the Troy Server at [repo.deploytroy.org](https://repo.deploytroy.org/).

== Installation ==

Troy Server is not available on WordPress.org.

1. Download the latest package from [repo.deploytroy.org](https://repo.deploytroy.org/package/get/zip/troy-server-installer).
1. Upload the ZIP file via Plugins > Add New > Upload Plugin.
1. Activate the package through the Plugins menu.
1. The package installs and activates Troy Client and Troy Server, then deactivates itself.
1. Configure your repository settings under Settings > Troy Server.

= Requirements =

* WordPress 6.8 or higher
* PHP 8.4 or higher
* MySQL 8.0.13 or higher
* PHP extensions: `mbstring`, `ZipArchive`
* HTTPS required

= Recommendations =

It's best to run Troy Server on a standalone WordPress instance. This can also be a Multisite subdomain or subdirectory. Note that multilingual plugins are not supported and may interfere with repository URL generation. Repository URLs are limited to 191 characters.

== Changelog ==

= 0.0.1184 =

* Initial proof of concept.
