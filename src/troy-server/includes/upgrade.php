<?php
/**
 * @package Troy\Server\Upgrade
 * @access  private
 */

namespace Troy\Server\Upgrade;

\defined( 'Troy\Server\ABSPATH' ) or die;

// phpcs:disable TSF.Performance.Opcodes.ShouldHaveNamespaceEscape -- Too many scoped funcs. Test me once in a while.

use const Troy\Server\DB_VERSION;

use Troy\Server\API;

/**
 * Troy Server
 *
 * Copyright (c) 2025 Sybre Waaijer, CyberWire B.V.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

// Upgrade outside of the global scope.
upgrade();

/**
 * Upgrades the database.
 *
 * @since 0.0.1184
 */
function upgrade() {

	if ( \wp_doing_ajax() ) return;

	$timeout = 5 * \MINUTE_IN_SECONDS; // Same as WP Core, function update_core().

	$lock = set_upgrade_lock( $timeout );
	// Lock failed to create--probably because it was already locked (or the database failed us).
	if ( ! $lock ) return;

	register_shutdown_function( 'Troy\Server\Upgrade\release_upgrade_lock' );

	\wp_raise_memory_limit( 'troy-server-upgrade' );

	$ini_max_execution_time = (int) ini_get( 'max_execution_time' );
	if ( 0 !== $ini_max_execution_time && \function_exists( 'set_time_limit' ) )
		set_time_limit( max( $ini_max_execution_time, $timeout ) );

	\wp_cache_flush();
	\wp_cache_delete( 'alloptions', 'options' );

	$previous_version = API\Server::get_db_version();

	if ( ! \get_option( 'troy_server_initial_db_version' ) )
		\update_option( 'troy_server_initial_db_version', DB_VERSION, false );

	$success = $previous_version > DB_VERSION
		? downgrade_from( $previous_version )
		: upgrade_from( $previous_version );

	$success and \update_option( 'troy_server_db_version', DB_VERSION, true );
}

/**
 * Creates the upgrade lock.
 *
 * @since 0.0.1184
 * @global \wpdb $wpdb
 *
 * @param int $release_timeout The timeout of the lock.
 * @return bool False if a lock couldn't be created or if the lock is still valid. True otherwise.
 */
function set_upgrade_lock( $release_timeout ) {

	global $wpdb;

	// WP 6.6+: we use 'off' instead of 'no' for autoload.
	$lock = $wpdb->query( $wpdb->prepare(
		"INSERT ignore INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'off') /* LOCK */",
		'troy_server_upgrade.lock',
		time(),
	) );

	if ( ! $lock ) {
		$lock = \get_option( 'troy_server_upgrade.lock' );

		if ( ! $lock )
			return false;

		if ( $lock > ( time() - $release_timeout ) )
			return false;

		release_upgrade_lock();

		return set_upgrade_lock( $release_timeout );
	}

	\update_option( 'troy_server_upgrade.lock', time(), true );

	return true;
}

/**
 * Releases the upgrade lock on shutdown.
 * When the upgrader halts, timeouts, or crashes for any reason, this will run.
 *
 * @since 0.0.1184
 */
function release_upgrade_lock() {
	\delete_option( 'troy_server_upgrade.lock' );
}

/**
 * Downgrades the database from a specific version.
 *
 * @since 0.0.1184
 *
 * @param int $version The version to downgrade to.
 */
function downgrade_from( $version ) {
	// Nothing to consider reverting yet.
	\update_option( 'troy_server_db_version', $version, true );
}

/**
 * Upgrades the Troy Server database to a specific version.
 *
 * @since 0.0.1184
 * @global \wpdb $wpdb
 *
 * @param int $version The version to upgrade to.
 */
function upgrade_from( $version ) {

	switch ( true ) {
		case $version < 1184:
			global $wpdb;

			// dbDelta is unreliable; it works sporadically using case-sensitive regex.
			foreach ( get_initial_db_schema_queries() as $query ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input.
				$wpdb->query( $query );
			}

			// Register the initial settings.
			\add_option( 'troy_server_settings', [], '', true );

			\update_option( 'troy_server_db_version', 1184, true );
			// Fall through.
	}
}

/**
 * Returns a list of queries for the default Troy Server database schema.
 *
 * We use 191 because InnoDB has a limit of 767 bytes per index, with utf8mb4 that's 191 characters (191*4=764).
 * We expect URLs not to exceed 191 characters; even though technically allowed, it'd be ludicrous.
 *
 * We make plugin versions 20 characters long because we haven't found plugins with a longer version.
 *
 * An epoch is 1 week long. Its identifier is calculated by flooring ( current UNIX timestamp / 604_800 seconds ).
 * You can get the current epoch via `Troy\Server\API\Utils::get_epoch()`, and previous epoch via `Troy\Server\API\Utils::get_epoch( 'last' )`.
 *
 * We partition the update request stats by epoch because we expect a lot of data.
 * Partitioning allows accessing data by epoch, which is useful for calculating the active install count quickly.
 * It also allows for quick deletion of old data.
 *
 * We partition the download stats by day because we expect a lot of data.
 * Again, partitioning allows for quickly counting data by day, and deleting old data.
 *
 * We composited indexes:
 * - `plugin_id_user_id` for the contributors table to force unique contributors per plugin.
 * - `plugin_id_locale` for the infos table to force unique info per locale per plugin.
 * - `plugin_id_version` for the snapshots table to force unique snapshots per plugin per version.
 * - `plugin_id_package_version` for the integration_queue and integration_failures tables to force unique entries per package version.
 * - `plugin_id_type` for the integration_logs table because direct access for these is common.
 * - `plugin_id_version_locale` for the translations table to force unique translations per locale per version.
 * - `plugin_id_user_id` for the ratings table to force unique ratings per user.
 * - `plugin_id` for the stats_totals table to force unique stats per plugin.
 * - `plugin_id_date` for the stats_totals_to_date table to force unique stats per plugin per day.
 * - `plugin_id_version_origin_url` for the stats table to force unique stats per plugin per version per origin URL.
 * - `plugin_id_version_date` for the stats_to_date table because direct access for these is common.
 * - `plugin_id_version_date_origin_url` for the stats_to_date table to force unique stats per plugin per version per day per origin URL.
 * - `plugin_id_version` for the view_stats table to force unique views per plugin per version.
 * - `plugin_id_version_origin_url` for the view_stats table to force unique stats per plugin per version per origin URL.
 * - `plugin_id_is_active` for the update_request_stats table because direct access for these is common.
 * - `plugin_id_epoch_version_is_active` for the update_request_stats table to force unique stats per plugin per epoch per version per active state.
 * - `plugin_id_epoch_version_locale` for the update_request_locales_stats table to force unique stats per plugin per epoch per version per locale.
 * - `plugin_id_epoch` for the update_request_stats_live table because direct access for these is common.
 *
 * Default values are set only for direct database queries. These may differ from the defaults we use in the plugin.
 * Still, we fully rely on the created_at and updated_at fields to be set automatically.
 *
 * | Table Name                                  | Purpose                                                             |
 * |---------------------------------------------|---------------------------------------------------------------------|
 * | troy_plugins                                | The main plugins table.                                             |
 * | troy_plugin_slug_transfers                  | Transfers of plugin slugs (for slug changes).                       |
 * | troy_plugin_metas                           | Meta data for plugins (for plugin cards).                           |
 * | troy_plugin_contributors                    | Contributors of the plugins (for plugin search and details).        |
 * | troy_plugin_infos                           | Parsed information of plugins (for plugin info page/tickbox).       |
 * | troy_plugin_snapshots                       | Snapshots of plugin data by version (for future restore feature).   |
 * | troy_plugin_integrations                    | Integration settings for plugins (for automated releases).          |
 * | troy_plugin_integration_queue               | Queue for integration processing (for automated releases).          |
 * | troy_plugin_integration_failures            | Failed integration attempts (for debugging and retry).              |
 * | troy_plugin_integration_logs                | Logs for integration events (for debugging and audit).              |
 * | troy_plugin_zips                            | ZIP locations for plugins (for plugin update/download).             |
 * | troy_plugin_translations                    | Translation locations for plugins (for download).                   |
 * | troy_plugin_data_caches                     | Cached data for plugins (for search/archives/ranking).              |
 * | troy_plugin_ratings                         | Ratings for plugins (for plugin page, review page).                 |
 * | troy_plugin_stats_totals                    | Total stats for plugins (accumulated over all time).                |
 * | troy_plugin_stats_totals_daily              | Total stats for plugins by day (historical).                        |
 * |                                             | This table can get partitioned (e.g., by year).                     |
 * | troy_plugin_stats_versions                  | Stats by version for plugins (accumulated over all time).           |
 * | troy_plugin_stats_versions_daily            | Stats by version for plugins by day (historical).                   |
 * |                                             | This table can get partitioned (e.g., by year).                     |
 * | troy_plugin_stats_views                     | View stats for plugins (for accumulation in stats).                 |
 * | troy_plugin_stats_views_live                | Live view stats for plugins (for accumulation in view_stats).       |
 * |                                             | This table can get partitioned (e.g., by week).                     |
 * | troy_plugin_stats_downloads                 | Download stats for plugins.                                         |
 * | troy_plugin_stats_downloads_live            | Live download stats for plugins.                                    |
 * |                                             | This table can get partitioned (e.g., by week).                     |
 * | troy_plugin_stats_requests                  | Update request stats for plugins.                                   |
 * | troy_plugin_stats_locales                   | Update request stats by locales for plugins.                        |
 * | troy_plugin_stats_php                       | Update request stats by PHP version for plugins.                    |
 * | troy_plugin_stats_wp                        | Update request stats by WordPress version for plugins.              |
 * | troy_plugin_stats_requests_live             | Live update request stats for plugins.                              |
 * |                                             | This table can get partitioned (e.g., by day).                      |
 * |---------------------------------------------|---------------------------------------------------------------------|
 * | troy_packages                               | The main packages table.                                            |
 * | troy_package_metas                          | Meta data for packages (for installer generation).                  |
 * | troy_package_stats_totals                   | Total stats for packages (accumulated over all time).               |
 * | troy_package_stats_totals_daily             | Total stats for packages by day (historical).                       |
 * |                                             | This table can get partitioned (e.g., by year).                     |
 * | troy_package_stats_downloads                | Download stats for packages.                                        |
 * | troy_package_stats_downloads_live           | Live download stats for packages.                                   |
 * |                                             | This table can get partitioned (e.g., by week).                     |
 * |---------------------------------------------|---------------------------------------------------------------------
 *
 * @since 0.0.1184
 * @global \wpdb $wpdb
 *
 * @return string[] The initial database queries.
 */
function get_initial_db_schema_queries() {

	global $wpdb;

	$collate  = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
	$dbprefix = $wpdb->prefix;

	return [
		"CREATE table `{$dbprefix}troy_plugins` (
			`id` bigint unsigned NOT null auto_increment,
			`post_id` bigint unsigned NOT null,
			`slug` varchar(191) NOT null,
			`status` varchar(20) NOT null DEFAULT 'pending',
			`origin_url` varchar(191) NOT null,
			`database_version` int unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `post_id` (`post_id`),
			unique index `slug` (`slug`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_slug_transfers` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`old_slug` varchar(191) NOT null,
			`new_slug` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `old_slug` (`old_slug`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_metas` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`name` varchar(191) NOT null,
			`author_id` bigint unsigned NOT null,
			`short_description` varchar(191) NOT null,
			`permalink` varchar(191) NOT null,
			`support_uri` varchar(191) NOT null,
			`donate_uri` varchar(191) NOT null,
			`logo_uri` varchar(191) NOT null,
			`builder_type` varchar(20) NOT null DEFAULT 'readme',
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id` (`plugin_id`),
			index `author_id` (`author_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_contributors` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`user_id` bigint unsigned NOT null,
			`role` varchar(20) NOT null DEFAULT 'contributor',
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `user_id` (`user_id`),
			unique index `plugin_id_user_id` (`plugin_id`, `user_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_infos` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`locale` varchar(15) NOT null DEFAULT 'en_US',
			`latest_version` varchar(20) NOT null,
			`banner_uri` varchar(191) NOT null,
			`contents` longtext NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id_locale` (`plugin_id`, `locale`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_snapshots` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`values` longtext NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			unique index `plugin_id_version` (`plugin_id`, `version`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_integrations` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`mode` varchar(20) NOT null,
			`settings` longtext NOT null,
			`auth` longtext DEFAULT null,
			`tags` longtext NOT null,
			`tags_refreshed` datetime DEFAULT null,
			`auto_process` varchar(20) NOT null DEFAULT 'all',
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id` (`plugin_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_integration_queue` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`package_version` varchar(20) NOT null,
			`mode` varchar(20) NOT null,
			`download_url` text NOT null,
			`revision_id` varchar(64) NOT null DEFAULT '',
			`type` varchar(20),
			`status` varchar(20) NOT null DEFAULT 'pending',
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			unique index `plugin_id_package_version` (`plugin_id`, `package_version`),
			index `status` (`status`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_integration_failures` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`package_version` varchar(50) NOT null,
			`mode` varchar(20) NOT null,
			`reason` varchar(50) NOT null,
			`details` text NOT null,
			`attempts` smallint unsigned NOT null DEFAULT 1,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id_package_version` (`plugin_id`, `package_version`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_integration_logs` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`type` varchar(20) NOT null,
			`message` longtext NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_type` (`plugin_id`, `type`),
			index `created_at` (`created_at`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_zips` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`type` varchar(20) NOT null default 'unreleased',
			`file_size` bigint unsigned NOT null,
			`tested_wp` varchar(20) NOT null,
			`requires_wp` varchar(20) NOT null,
			`requires_php` varchar(20) NOT null,
			`repo` varchar(191) NOT null,
			`dependencies` varchar(191) NOT null,
			`upgrade_notice` varchar(191) NOT null,
			`origin_url` varchar(191) NOT null,
			`checksum` varchar(191) NOT null,
			`checksum_version` varchar(20) NOT null,
			`checksum_origin` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `version` (`version`),
			unique index `plugin_id_version` (`plugin_id`, `version`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_translations` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`locale` varchar(15) NOT null,
			`file_size` bigint unsigned NOT null,
			`origin_url` varchar(191) NOT null,
			`checksum` varchar(191) NOT null,
			`checksum_version` varchar(20) NOT null,
			`checksum_origin` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version` (`plugin_id`, `version`),
			unique index `plugin_id_version_locale` (`plugin_id`, `version`, `locale`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_data_caches` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`average_rating` tinyint NOT null DEFAULT 0,
			`rating_count` bigint unsigned NOT null DEFAULT 0,
			`recent_average_rating` tinyint NOT null DEFAULT 0,
			`recent_rating_count` bigint unsigned NOT null DEFAULT 0,
			`active_install_count` bigint unsigned NOT null DEFAULT 0,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id` (`plugin_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_ratings` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`user_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`rating` tinyint NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			unique index `plugin_id_user_id` (`plugin_id`, `user_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_totals` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`installations_current_epoch` bigint unsigned NOT null,
			`installations_previous_epoch` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id` (`plugin_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_totals_daily` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`date` date NOT null DEFAULT (current_date),
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`installations_current_epoch` bigint unsigned NOT null,
			`installations_previous_epoch` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id_date` (`plugin_id`, `date`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_versions` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`installations_current_epoch` bigint unsigned NOT null,
			`installations_previous_epoch` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version` (`plugin_id`, `version`),
			unique index `plugin_id_version_origin_url` (`plugin_id`, `version`, `origin_url`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_versions_daily` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`date` date NOT null DEFAULT (current_date),
			`origin_url` varchar(191) NOT null,
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`installations_current_epoch` bigint unsigned NOT null,
			`installations_previous_epoch` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version_date` (`plugin_id`, `version`, `date`),
			unique index `plugin_id_version_date_origin_url` (`plugin_id`, `version`, `date`, `origin_url`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_views` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`views` bigint unsigned NOT null,
			`screen` varchar(20) NOT null,
			`locale` varchar(15) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version` (`plugin_id`, `version`),
			unique index `plugin_id_version_screen_locale_origin_url` (`plugin_id`, `version`, `screen`, `locale`, `origin_url`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_views_live` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`screen` varchar(20) NOT null,
			`locale` varchar(15) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version` (`plugin_id`, `version`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_downloads` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`downloads` bigint unsigned NOT null,
			`type` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			unique index `plugin_id_version_type_origin_url` (`plugin_id`, `version`, `type`, `origin_url`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_downloads_live` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`type` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_requests` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`version` varchar(20) NOT null,
			`is_active` boolean NOT null DEFAULT 0,
			`request_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id_is_active` (`plugin_id`, `is_active`),
			unique index `plugin_id_epoch_version_is_active` (`plugin_id`, `epoch`, `version`, `is_active`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_locales` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`version` varchar(20) NOT null,
			`locale` varchar(15) NOT null,
			`install_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			unique index `plugin_id_epoch_version_locale` (`plugin_id`, `epoch`, `version`, `locale`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_php` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`php_version` varchar(20) NOT null,
			`install_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			unique index `plugin_id_epoch_php_version` (`plugin_id`, `epoch`, `php_version`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_wp` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`wp_version` varchar(20) NOT null,
			`install_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			unique index `plugin_id_epoch_wp_version` (`plugin_id`, `epoch`, `wp_version`)
		) $collate",
		"CREATE table `{$dbprefix}troy_plugin_stats_requests_live` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`version` varchar(20) NOT null,
			`is_active` boolean NOT null DEFAULT 0,
			`uuid` varchar(100) NOT null,
			`request_count` int unsigned NOT null,
			`locales` longtext NOT null,
			`php_version` varchar(20) NOT null,
			`wp_version` varchar(20) NOT null,
			`client_version` varchar(20) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id_is_active` (`plugin_id`, `is_active`),
			index `plugin_id_epoch` (`plugin_id`, `epoch`),
			unique index `plugin_id_epoch_version_is_active_uuid` (`plugin_id`, `epoch`, `version`, `is_active`, `uuid`)
		) $collate",
		"CREATE table `{$dbprefix}troy_packages` (
			`id` bigint unsigned NOT null auto_increment,
			`post_id` bigint unsigned NOT null,
			`slug` varchar(191) NOT null,
			`status` varchar(20) NOT null DEFAULT 'pending',
			`origin_url` varchar(191) NOT null,
			`database_version` int unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `post_id` (`post_id`),
			unique index `slug` (`slug`)
		) $collate",
		"CREATE table `{$dbprefix}troy_package_metas` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`plugin_uri` varchar(191) NOT null,
			`name` varchar(191) NOT null,
			`description` varchar(191) NOT null,
			`version` varchar(20) NOT null,
			`author` varchar(191) NOT null,
			`author_uri` varchar(191) NOT null,
			`requires_wp` varchar(20) NOT null,
			`requires_php` varchar(20) NOT null,
			`network` boolean NOT null DEFAULT 0,
			`install_timeout` int unsigned NOT null DEFAULT 30,
			`deactivate_on_completion` boolean NOT null DEFAULT 1,
			`delete_on_completion` boolean NOT null DEFAULT 0,
			`notice_severity` varchar(20) NOT null DEFAULT 'detailed',
			`plugins` longtext NOT null,
			`themes` longtext NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `package_id` (`package_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_package_stats_totals` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`downloads` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `package_id` (`package_id`)
		) $collate",
		"CREATE table `{$dbprefix}troy_package_stats_totals_daily` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`date` date NOT null DEFAULT (current_date),
			`downloads` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `package_id_date` (`package_id`, `date`)
		) $collate",
		"CREATE table `{$dbprefix}troy_package_stats_downloads` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`downloads` bigint unsigned NOT null,
			`type` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `package_id` (`package_id`),
			unique index `package_id_version_type_origin_url` (`package_id`, `version`, `type`, `origin_url`)
		) $collate",
		"CREATE table `{$dbprefix}troy_package_stats_downloads_live` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`type` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			index `package_id` (`package_id`)
		) $collate",
	];
}
