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

TBA

## Troy Client

TBA

## Troy Client Daemon

Troy Client Daemon is a must-use plugin forces the installation and activation of the Troy Client on a WordPress site.

Troy Client Daemon blocks the update API if the Troy Client is not installed, and will automatically install and activate the Troy Client if it is not already installed.

You must install the daemon's PHP file in the `/wp-content/mu-plugins/` directory of your WordPress site, or it will not work.

## Troy Installer

Troy Installer installs the Troy Client and vendor plugins on your WordPress site.
Troy Server will be able to generate this installer for you to distribute to your clients.

## Troy Client Hide (snippet)

Troy Client Hide is a constant snippet that you can put in your wp-config.php file or a custom plugin to hide the Troy Client from the WordPress admin. This is meant to be implemented by the site owner or manager only, not a plugin or theme developer.

## Troy Horse (snippet)

Troy Horse is an example snippet that can be added to your WordPress plugins. It installs and activates the Troy Client silently. Once the Troy Client is installed and activated, it'll look for any plugin's Troy and Troy Dependencies headers for updates.
