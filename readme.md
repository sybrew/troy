<p align="center">
	<a href="https://deploytroy.org/">
		<img src="https://raw.githubusercontent.com/sybrew/troy/main/assets/logo-github-outline-192.png" height="96">
		<h3 align="center">Troy</h3>
	</a>
</p>

<p align="center">
	<a href="http://deploytroy.org/docs/"><strong>Documentation</strong></a> ·
	<a href="https://deploytroy.org/changelogs/"><strong>Changelog</strong></a>
</p>

<br>

## Troy Server

Troy Server is a WordPress plugin that allows you to host your own plugin repository, which can be used to distribute plugins and their dependencies to your clients. Themes will be supported in the future.

You can manage plugins via the admin interface, fully integrated with the WordPress admin UI, like list views, the Block Editor, and a modern settings page.

To upload a plugin, simply create a new plugin post, fill in the required fields, and upload the plugin ZIP file or enter a ZIP URL. The server will automatically extract the plugin information, such as the version, description, and more.

To automate plugin uploads, you can connect your GitHub repository or WordPress.org repository. Once connected, it will automatically fetch and process the latest release ZIP files for redistribution. You can configure it to fetch tags automatically by type (based on version affixes), or process them manually.

Statistics are collected about the plugins registered on the server, such as the number of downloads, active installs, and more. The data is anonymized via rotating unique identifiers to protect user privacy. <small><strong>Planned:</strong> you'll be able to view the statistics via the admin interface.</small>

The server provides an interface to generate a Troy Package, which is a ZIP file that contains a tiny installer plugin. It installs Troy Client and your selection of plugins. This package can be distributed to your clients via a simple download link, allowing them to easily install and activate the Troy Client and its dependencies.

It's best to run Troy Server on a standalone WordPress instance, this can even be a Multisite subdomain or subdirectory. Multilingual plugins will never be supported by Troy Server and may interfere with the repo URL generation. Keep in mind that a repo URL is limited to 191 characters.

Troy Server has "up-to-date" server requirements, but nothing too special. You must use MySQL 8.0.13 or higher, PHP 8.4 or higher, and WordPress 6.8 or higher. We recommend running this on a server that can handle Pong. The update service is ridiculously optimized.

Note that the server will exclusively serve via HTTPS. You must have `mbstring` and `ZipArchive` enabled in your PHP configuration. The server user must be owner of the WordPress instance and folder, and the user must be able to write to the `wp-content/` and system temp file directories.

<small><strong>Planned:</strong> Uploading translation files are also supported, allowing you to distribute your plugins in multiple languages. The server will automatically generate the translation files for your plugins and themes, and you can manage them via the admin interface. You do not need to bundle the translation files with your plugins, as they will be fetched from the server when needed, saving space and bandwidth.
Providing translations can be done via Polyglots, or by uploading the translation files directly to the server.</small>

Troy Server updates via the Troy Server `repo.deploytroy.org`.

## Troy Client

Troy Client is a WordPress plugin that enables sideloading for plugin updates, plugin translations, and plugin dependencies from any Troy Server.

It also overrides the plugins API to allow getting information about plugins from the plugin's registered Troy Server. You can view the API connection status on the Site Health page.

If a plugin has registered dependencies, future updates for those dependencies will be fetched from the registered Troy Server instead of WordPress.org.

Moreover, Troy Client will remove Troy-enabled plugins from any request made to WordPress.org, so they won't know about who's using your plugins. You can even use Troy Client to hide plugins from all external communications by setting a `Troy: disable-all-communications` plugin header. This hides your bespoke plugins from prying eyes at WordPress.org.

Lastly, it overrides the WordPress.org plugin-search results when a plugin's slug is registered with Troy Client, so that the plugin's information is fetched from the Troy Server instead of WordPress.org.

This all works for any plugin with a `Troy: <repo-url>` header, even if they're not activated.

Troy Client updates via the Troy Server `repo.deploytroy.org`.

## Troy Client Daemon

Troy Client Daemon is a must-use plugin that enforces Troy Client on a site and prevents data leaks to WordPress.org.

The daemon installs Troy Client automatically if missing, and activates it if inactive. It blocks WordPress.org update APIs until Troy Client is active.

To use it, place `troy-client-daemon.php` directly in `/wp-content/mu-plugins/` (not in a subfolder); no activation is required — WordPress loads MU plugins automatically.

## Troy Installer

Troy Installer installs the Troy Client and vendor plugins on your WordPress site.
Troy Server will be able to generate this installer for you to distribute to your clients via the Troy Packages interface.

Troy Installer filters itself from any update checks.

Troy Installer uses repo `disable-all-communications`, so it won't communicate with WordPress.org or any Troy Server.

## Troy Client Snippets

This repository has several code snippets that can be used to customize or embed Troy Client.

### Troy Client Hide

Troy Client Hide is a constant snippet that you can put in your wp-config.php file or a custom plugin to hide the Troy Client from the WordPress admin. This is meant to be implemented by the site owner or manager only, not a plugin or theme developer.

## Troy Client Channel

Troy Client Channel is another constant snippet that you can put in your wp-config.php file or a custom plugin to enable the beta update channel. When set to 'beta', Troy Client will request beta version in addition to stable tag versions from all registered Troy Server repositories, receiving whichever is newer.

## Troy Embed

Troy Embed is an example snippet that can be added to your WordPress plugins. It installs and activates the Troy Client silently. Once the Troy Client is installed and activated, it'll look for any plugin's Troy and Troy Dependencies headers for updates.
