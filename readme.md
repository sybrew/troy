<p align="center">
	<a href="https://deploytroy.org/">
		<img src="https://github.com/sybrew/troy/blob/main/assets/logo-github-outline-192.png?raw=true" height="96">
		<h3 align="center">Troy</h3>
	</a>
</p>

<p align="center">

</p>

<p align="center">
	<a href="#"><strong>Documentation (TBA)</strong></a> ·
	<a href="changelog.md"><strong>Changelog</strong></a>
</p>

<br>

## Troy Server

Troy Server is a WordPress plugin that allows you to host your own plugin repository, which can be used to distribute plugins and their dependencies to your clients. Themes will be supported in the future.

You can manage plugins via the admin interface, fully integrated with the WordPress admin UI, like list views, the Block Editor, and a modern settings page.

TODO: Statistics are collected about the plugins registered on the server, such as the number of downloads, active installs, and more.

TODO: The server provides an interface to generate a Troy Installer, which is a ZIP file that contains the Troy Client and with instructions to install plugins. This installer can be distributed to your clients, allowing them to easily install and activate the Troy Client and its dependencies, starting with only a tiny package.

TODO: Translation files are also supported, allowing you to distribute translations for your plugins. The server will automatically generate the translation files for your plugins and themes, and you can manage them via the admin interface. You do not need to bundle the translation files with your plugins, as they will be fetched from the server when needed, saving space and bandwidth.
Providing translations can be done via Polyglots, or by uploading the translation files directly to the server.

It's best to run Troy Server on a standalone WordPress instance, this can even be a Multisite subdomain or subdirectory. Multilingual plugins will never be supported by Troy Server and may interfere with the repo URL generation. Keep in mind that a repo URL is limited to 191 characters.

Troy Server has "up-to-date" server requirements, but nothing too special. You must use MySQL 8.0.13 or higher, PHP 8.4 or higher, and WordPress 6.8 or higher. We recommend running this on a server that can handle Pong. The update service is ridiculously optimized and (TODO) can scale horizontally.

Note that the server will exclusively serve via HTTPS. You must have `mbstring` and `ZipArchive` enabled in your PHP configuration. The server user must be owner of the WordPress instance and folder, and the user must be able to write to the `wp-content/` and system temp file directories.

## Troy Client

Troy Client is a WordPress plugin that enables updating plugins, plugin translations, and plugin dependencies from any Troy Server.

It also overrides the plugins API to allow getting information about plugins from the plugin's registered Troy Server. You can view the API connection status on the Site Health page.

If a plugin has registered dependencies, future updates for those dependencies will be fetched from the registered Troy Server instead of WordPress.org.

Moreover, Troy Client will remove information plugin information from requests made to WordPress.org, including subsequent translation update requests.

Lastly, it overrides the WordPress.org plugin-search results when a plugin's slug is registered with Troy Client, so that the plugin's information is fetched from the Troy Server instead of WordPress.org.

Troy Client looks for updates for itself from the Troy Server `repo.deploytroy.org`.

## Troy Client Daemon

Troy Client Daemon is a must-use plugin that enforces Troy Client on a site and prevents data leaks to WordPress.org.

The daemon installs Troy Client automatically if missing, and activates it if inactive. It blocks WordPress.org update APIs until Troy Client is active.

To use it, place `troy-client-daemon.php` directly in `/wp-content/mu-plugins/` (not in a subfolder); no activation is required — WordPress loads MU plugins automatically.

## Troy Installer

Troy Installer installs the Troy Client and vendor plugins on your WordPress site.
Troy Server will be able to generate this installer for you to distribute to your clients.

## Troy Client Hide (snippet)

Troy Client Hide is a constant snippet that you can put in your wp-config.php file or a custom plugin to hide the Troy Client from the WordPress admin. This is meant to be implemented by the site owner or manager only, not a plugin or theme developer.

## Troy Horse (snippet)

Troy Horse is an example snippet that can be added to your WordPress plugins. It installs and activates the Troy Client silently. Once the Troy Client is installed and activated, it'll look for any plugin's Troy and Troy Dependencies headers for updates.
