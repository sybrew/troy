<?php
/**
 * @package Troy\Server\Upgrade
 * @access  private
 */

namespace Troy\Server\Upgrade;

\defined( 'Troy\Server\ABSPATH' ) or die;

// phpcs:disable TSF.Performance.Opcodes.ShouldHaveNamespaceEscape -- Too many scoped funcs. Test me once in a while.

use const Troy\Server\DB_VERSION;

use Troy\Server\{
	Admin,
	API,
	Settings,
};

/**
 * Troy Server
 *
 * Copyright (c) 2025 - 2026 Sybre Waaijer, CyberWire B.V.
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
 * @since 1.8.1184 Clears a recorded database block on shutdown when the version is current.
 */
function upgrade() {

	if ( \wp_doing_ajax() )
		return;

	$timeout = \MINUTE_IN_SECONDS; // Stale lock after a crashed run; not WP core's 5-minute update_core() window.

	$lock = set_upgrade_lock( $timeout );
	// Lock failed to create--probably because it was already locked (or the database failed us).
	if ( ! $lock )
		return;

	register_shutdown_function( 'Troy\Server\Upgrade\on_upgrade_shutdown' );

	\wp_raise_memory_limit( 'troy-server-upgrade' );

	$ini_max_execution_time = (int) ini_get( 'max_execution_time' );
	if ( 0 !== $ini_max_execution_time && \function_exists( 'set_time_limit' ) )
		set_time_limit( max( $ini_max_execution_time, $timeout ) );

	\wp_cache_flush();
	\wp_cache_delete( 'alloptions', 'options' );

	$previous_version = API\Server::get_db_version();

	if ( ! \get_option( 'troy_server_initial_db_version' ) )
		\update_option( 'troy_server_initial_db_version', DB_VERSION, false );

	if ( $previous_version > DB_VERSION ) {
		downgrade_from( $previous_version );
	} else {
		upgrade_from( $previous_version );
	}

	if ( API\Server::get_db_version() !== DB_VERSION ) return;

	clear_database_block();
	register_database_version_notice( $previous_version );
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
		$lock_time = (int) \get_option( 'troy_server_upgrade.lock' );

		if ( $lock_time > ( time() - $release_timeout ) )
			return false;

		release_upgrade_lock();

		return set_upgrade_lock( $release_timeout );
	}

	\update_option( 'troy_server_upgrade.lock', time(), true );

	return true;
}

/**
 * Releases the upgrade lock and clears a recorded database block when the
 * schema version is already current.
 *
 * @since 1.8.1184
 */
function on_upgrade_shutdown() {

	release_upgrade_lock();

	if ( API\Server::get_db_version() === DB_VERSION )
		clear_database_block();
}

/**
 * Releases the upgrade lock.
 *
 * @since 0.0.1184
 */
function release_upgrade_lock() {
	\delete_option( 'troy_server_upgrade.lock' );
}

/**
 * Clears a recorded database block and its persistent notice.
 *
 * @since 1.8.1184
 */
function clear_database_block() {

	\delete_option( 'troy_server_database_block' );
	Admin\Notice\Persistent::clear_notice( 'database-block' );
}

/**
 * Registers a one-time notice when the database reaches the current version.
 *
 * @since 1.8.1184
 *
 * @param int $previous_version The database version before this run.
 */
function register_database_version_notice( $previous_version ) {

	if ( $previous_version > DB_VERSION ) {
		/* translators: %s: Database version */
		$lead = \__( 'Database downgraded to version %s.', 'troy-server' );
	} else {
		/* translators: %s: Database version */
		$lead = \__( 'Database updated to version %s.', 'troy-server' );
	}

	Admin\Notice\Persistent::register_notice(
		\sprintf(
			'<p><strong>Troy Server:</strong> %s</p>',
			\esc_html( \sprintf( $lead, DB_VERSION ) ),
		),
		'database-updated',
		[
			'type'   => 'success',
			'escape' => false,
		],
		[
			'count' => 1,
		],
	);
}

/**
 * Records a database block from the last query when one is present.
 *
 * Redundant "already applied" DDL errors are ignored so a retried upgrade can
 * continue. Classifies those by the driver's numeric error code when the
 * connection handle exposes one (`errno`, or PDO `errorInfo()[1]`). MySQL and
 * MariaDB codes are stable across `lc_messages`; the English strings are not.
 * wpdb does not expose errno; read it before any later query.
 * On failure, stores the error and registers a persistent admin notice.
 *
 * @since 1.8.1184
 * @global \wpdb $wpdb
 *
 * @param int $version The database version step being applied.
 * @return true|void True when a block is recorded.
 */
function record_database_block( $version ) {

	global $wpdb;

	if ( ! $wpdb->last_error ) return;

	$dbh   = $wpdb->dbh;
	$errno = 0;

	if ( \is_object( $dbh ) ) {
		if ( isset( $dbh->errno ) ) {
			$errno = (int) $dbh->errno; // mysqli
		} elseif ( method_exists( $dbh, 'errorInfo' ) ) {
			$errno = (int) ( $dbh->errorInfo()[1] ?? 0 ); // PDO
		}
	}

	switch ( $errno ) {
		case 1050: // ER_TABLE_EXISTS_ERROR
		case 1060: // ER_DUP_FIELDNAME
		case 1061: // ER_DUP_KEYNAME
		case 1091: // ER_CANT_DROP_FIELD_OR_KEY
			return;
	}

	$error = $wpdb->last_error;

	\update_option(
		'troy_server_database_block',
		[
			'version' => $version,
			'error'   => $error,
		],
		true,
	);

	// Cap at 32767 characters.
	if ( strlen( $error ) > 0x7FFF )
		$error = substr( $error, 0, 0x7FFF ) . ' [...]';

	Admin\Notice\Persistent::register_notice(
		\sprintf(
			'<p><strong>Troy Server:</strong> %s</p><p>%s</p><p><samp>%s</samp></p>',
			\esc_html( \sprintf(
				/* translators: %s: Database version */
				\__( 'Database update failed at version %s.', 'troy-server' ),
				$version,
			) ),
			\sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s<span class="screen-reader-text"> %s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
				\esc_url( 'https://deploytroy.org/docs/troy-server/troubleshooting#install-or-update-failed' ),
				\esc_html( \__( 'Learn how to fix install or update failures', 'troy-server' ) ),
				\esc_html(
					/* translators: Hidden accessibility text. */
					\__( '(opens in a new tab)', 'default' ),
				),
			),
			\esc_html( $error ),
		),
		'database-block',
		[
			'type'   => 'error',
			'escape' => false,
		],
		[
			'count' => -1, // Unlimited.
		],
	);

	return true;
}

/**
 * Downgrades the database from a specific version.
 *
 * @since 0.0.1184
 * @since 1.8.1184 Now stamps the running plugin's database version.
 *                 Renamed parameter `$version` to `$previous_version`.
 *
 * @param int $previous_version The previous version the site downgraded from.
 */
function downgrade_from( $previous_version ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	// Nothing to consider reverting yet.
	\update_option( 'troy_server_db_version', DB_VERSION, true );
}

/**
 * Upgrades the Troy Server database to a specific version.
 *
 * @since 0.0.1184
 * @since 1.8.1184 Renamed parameter `$version` to `$previous_version`.
 * @global \wpdb $wpdb
 *
 * @param int $previous_version The previous version the site upgraded from.
 */
function upgrade_from( $previous_version ) {

	$is_install = $previous_version < 1_1184;

	switch ( true ) {
		case $previous_version < 1_1184:
			global $wpdb;

			// dbDelta is unreliable; it works sporadically using case-sensitive regex.
			foreach ( get_initial_db_schema_queries() as $query ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input.
				$wpdb->query( $query );

				if ( record_database_block( 1_1184 ) ) return;
			}

			// Register the initial settings, prefilled with defaults.
			\add_option( 'troy_server_settings', Settings\Data::get_default_settings(), '', true );

			\update_option( 'troy_server_db_version', 1_1184, true ); // Always update to prevent re-running on crash.

			// Fall through.
		case $previous_version < 1_6_1184:
			if ( ! $is_install ) {
				global $wpdb;

				$wpdb->query(
					"ALTER TABLE `{$wpdb->prefix}troy_package_metas`
						ADD COLUMN `network_activation` varchar(20) NOT null DEFAULT 'block'
							AFTER `notice_severity`",
				);

				if ( record_database_block( 1_6_1184 ) ) return;
			}

			\update_option( 'troy_server_db_version', 1_6_1184, true ); // Always update to prevent re-running on crash.
			// Fall through.
		case $previous_version < 1_7_1184:
			if ( ! $is_install ) {
				global $wpdb;

				// Migrate checksum columns to a single JSON checksums column.
				foreach ( [ 'troy_plugin_zips', 'troy_plugin_translations' ] as $table ) {

					$wpdb->query( $wpdb->prepare(
						"ALTER TABLE %i
							ADD COLUMN `checksums` longtext NOT null DEFAULT ''
								AFTER `origin_url`",
						"{$wpdb->prefix}{$table}",
					) );

					if ( record_database_block( 1_7_1184 ) ) return;

					$wpdb->query( $wpdb->prepare(
						"UPDATE %i
							SET `checksums` = JSON_OBJECT( `checksum_version`, `checksum` )
							WHERE `checksum` != ''",
						"{$wpdb->prefix}{$table}",
					) );

					if ( record_database_block( 1_7_1184 ) ) return;

					$wpdb->query( $wpdb->prepare(
						'ALTER TABLE %i
							DROP COLUMN `checksum`,
							DROP COLUMN `checksum_version`,
							DROP COLUMN `checksum_origin`',
						"{$wpdb->prefix}{$table}",
					) );

					if ( record_database_block( 1_7_1184 ) ) return;
				}

				// Merge `network` boolean into `network_activation` dropdown.
				$wpdb->query(
					"UPDATE `{$wpdb->prefix}troy_package_metas`
						SET `network_activation` = 'require'
						WHERE `network` = 1",
				);

				if ( record_database_block( 1_7_1184 ) ) return;

				$wpdb->query(
					"ALTER TABLE `{$wpdb->prefix}troy_package_metas`
						DROP COLUMN `network`",
				);

				if ( record_database_block( 1_7_1184 ) ) return;
			}

			// Register the server cache option.
			\add_option( 'troy_server_cache', [], '', true );

			// Backfill composer_vendor for existing sites so it's locked in.
			if ( ! $is_install )
				Settings\Data::update_server_settings(
					'composer_vendor',
					API\Server::get_site_slug(),
				);

			\update_option( 'troy_server_db_version', 1_7_1184, true ); // Always update to prevent re-running on crash.
			// Fall through.
		case $previous_version < 1_8_1184:
			if ( ! $is_install ) {
				global $wpdb;

				foreach ( [
					"{$wpdb->prefix}troy_plugins",
					"{$wpdb->prefix}troy_plugin_slug_transfers",
					"{$wpdb->prefix}troy_plugin_metas",
					"{$wpdb->prefix}troy_plugin_contributors",
					"{$wpdb->prefix}troy_plugin_infos",
					"{$wpdb->prefix}troy_plugin_snapshots",
					"{$wpdb->prefix}troy_plugin_integrations",
					"{$wpdb->prefix}troy_plugin_integration_queue",
					"{$wpdb->prefix}troy_plugin_integration_history",
					"{$wpdb->prefix}troy_plugin_integration_logs",
					"{$wpdb->prefix}troy_plugin_zips",
					"{$wpdb->prefix}troy_plugin_translations",
					"{$wpdb->prefix}troy_plugin_data_caches",
					"{$wpdb->prefix}troy_plugin_ratings",
					"{$wpdb->prefix}troy_plugin_stats_totals",
					"{$wpdb->prefix}troy_plugin_stats_totals_daily_snapshots",
					"{$wpdb->prefix}troy_plugin_stats_versions",
					"{$wpdb->prefix}troy_plugin_stats_versions_daily_snapshots",
					"{$wpdb->prefix}troy_plugin_stats_views",
					"{$wpdb->prefix}troy_plugin_stats_views_live",
					"{$wpdb->prefix}troy_plugin_stats_downloads",
					"{$wpdb->prefix}troy_plugin_stats_downloads_live",
					"{$wpdb->prefix}troy_plugin_stats_requests",
					"{$wpdb->prefix}troy_plugin_stats_locales",
					"{$wpdb->prefix}troy_plugin_stats_php",
					"{$wpdb->prefix}troy_plugin_stats_wp",
					"{$wpdb->prefix}troy_plugin_stats_requests_live",
					"{$wpdb->prefix}troy_stats_locales",
					"{$wpdb->prefix}troy_stats_php",
					"{$wpdb->prefix}troy_stats_wp",
					"{$wpdb->prefix}troy_packages",
					"{$wpdb->prefix}troy_package_metas",
					"{$wpdb->prefix}troy_package_stats_totals",
					"{$wpdb->prefix}troy_package_stats_totals_daily_snapshots",
					"{$wpdb->prefix}troy_package_stats_downloads",
					"{$wpdb->prefix}troy_package_stats_downloads_live",
				] as $table ) {
					$status = $wpdb->get_row(
						$wpdb->prepare(
							'SHOW TABLE STATUS WHERE `Name` = %s',
							$table,
						),
						\ARRAY_A,
					);

					if ( ! $status )
						continue;

					if ( 'InnoDB' === $status['Engine'] )
						continue;

					$wpdb->query( $wpdb->prepare(
						'ALTER TABLE %i ENGINE=InnoDB',
						$table,
					) );

					if ( record_database_block( 1_8_1184 ) ) return;
				}
			}

			\update_option( 'troy_server_db_version', 1_8_1184, true );

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
 * An epoch is 1 week long. Its identifier is calculated by flooring ( current UNIX timestamp / \WEEK_IN_SECONDS ).
 * You can get this epoch via `Troy\Server\API\Utils::get_epoch()`, and last epoch via `Troy\Server\API\Utils::get_epoch( 'last' )`.
 *
 * We composited indexes:
 * - `plugin_id_user_id` for the contributors table to force unique contributors per plugin.
 * - `plugin_id_locale` for the infos table to force unique info per locale per plugin.
 * - `plugin_id_version` for the snapshots table to force unique snapshots per plugin per version.
 * - `plugin_id_package_version` for the integration_queue and integration_history tables to force unique entries per package version.
 * - `plugin_id_status` for the integration_history table because direct access for these is common.
 * - `plugin_id_type` for the integration_logs table because direct access for these is common.
 * - `plugin_id_version` for the zips table to force unique zips per plugin per version.
 * - `plugin_id_version_locale` for the translations table to force unique translations per locale per version.
 * - `plugin_id_user_id` for the ratings table to force unique ratings per user.
 * - `plugin_id` for the stats_totals table to force unique stats per plugin.
 * - `plugin_id_date` for the stats_totals_daily_snapshots table to force unique stats per plugin per day.
 * - `plugin_id_version_origin_url` for the stats_versions table to force unique stats per plugin per version per origin URL.
 * - `plugin_id_version_date` for the stats_versions_daily_snapshots table because direct access for these is common.
 * - `plugin_id_version_date_origin_url` for the stats_versions_daily_snapshots table to force unique stats per plugin per version per day per origin URL.
 * - `plugin_id_version` for the stats_views table because direct access for these is common.
 * - `plugin_id_version_screen_locale_origin_url` for the stats_views table to force unique stats per plugin per version per screen per locale per origin URL.
 * - `plugin_id_version_type_origin_url` for the stats_downloads table to force unique stats per plugin per version per type per origin URL.
 * - `plugin_id_is_active` for the stats_requests table because direct access for these is common.
 * - `plugin_id_epoch_version_is_active` for the stats_requests table to force unique stats per plugin per epoch per version per active state.
 * - `plugin_id_epoch_locale` for the stats_locales table to force unique stats per plugin per epoch per locale.
 * - `plugin_id_epoch_php_version` for the stats_php table to force unique stats per plugin per epoch per PHP version.
 * - `plugin_id_epoch_wp_version` for the stats_wp table to force unique stats per plugin per epoch per WordPress version.
 * - `epoch_locale` for the global stats_locales table to force unique stats per epoch per locale.
 * - `epoch_php_version` for the global stats_php table to force unique stats per epoch per PHP version.
 * - `epoch_wp_version` for the global stats_wp table to force unique stats per epoch per WordPress version.
 * - `plugin_id_epoch` for the stats_requests_live table because direct access for these is common.
 * - `plugin_id_epoch_uuid_origin_url` for the stats_requests_live table to force unique stats per plugin per epoch per UUID per origin URL.
 * - `package_id` for the package_stats_totals table to force unique stats per package.
 * - `package_id_date` for the package_stats_totals_daily_snapshots table to force unique stats per package per day.
 * - `package_id_version_type_origin_url` for the package_stats_downloads table to force unique stats per package per version per type per origin URL.
 *
 * Default values are set only for direct database queries. These may differ from the defaults we use in the plugin.
 * Still, we fully rely on the created_at and updated_at fields to be set automatically.
 *
 *
 * | Table Name                                  | Purpose                                                             |
 * |---------------------------------------------|---------------------------------------------------------------------|
 * | troy_plugins                                | The main plugins table.                                             |
 * | troy_plugin_slug_transfers                  | Transfers of plugin slugs (for slug changes).                       |
 * | troy_plugin_metas                           | Meta data for plugins (for plugin cards).                           |
 * | troy_plugin_contributors                    | Contributors of the plugins (for plugin search and details).        |
 * | troy_plugin_infos                           | Parsed information of plugins (for plugin info page/thickbox).      |
 * | troy_plugin_snapshots                       | Snapshots of plugin data by version (for future restore feature).   |
 * | troy_plugin_integrations                    | Integration settings for plugins (for automated releases).          |
 * | troy_plugin_integration_queue               | Queue for integration processing (for automated releases).          |
 * | troy_plugin_integration_history             | Integration processing history (success and failure tracking).      |
 * | troy_plugin_integration_logs                | Logs for integration events (for debugging and audit).              |
 * | troy_plugin_zips                            | ZIP locations for plugins (for plugin update/download).             |
 * | troy_plugin_translations                    | Translation locations for plugins (for download).                   |
 * | troy_plugin_data_caches                     | Cached data for plugins (for search/archives/ranking).              |
 * | troy_plugin_ratings                         | Ratings for plugins (for plugin page, review page).                 |
 * | troy_plugin_stats_totals                    | Total stats for plugins (accumulated over all time).                |
 * | troy_plugin_stats_totals_daily_snapshots    | Daily snapshots of total stats for plugins (cumulative).            |
 * |                                             | Stores frozen copy of stats_totals at end of day (for graphs).      |
 * |                                             | This table can get partitioned (e.g., by year).                     |
 * | troy_plugin_stats_versions                  | Stats by version for plugins (accumulated over all time).           |
 * | troy_plugin_stats_versions_daily_snapshots  | Daily snapshots of stats by version for plugins (cumulative).       |
 * |                                             | Stores frozen copy of stats_versions at end of day (for graphs).    |
 * |                                             | This table can get partitioned (e.g., by year).                     |
 * | troy_plugin_stats_views                     | View stats for plugins (for accumulation in stats).                 |
 * | troy_plugin_stats_views_live                | Live view stats for plugins (for accumulation in view_stats).       |
 * | troy_plugin_stats_downloads                 | Download stats for plugins.                                         |
 * | troy_plugin_stats_downloads_live            | Live download stats for plugins.                                    |
 * | troy_plugin_stats_requests                  | Update request stats for plugins.                                   |
 * | troy_plugin_stats_locales                   | Update request stats by locales for plugins.                        |
 * | troy_plugin_stats_php                       | Update request stats by PHP version for plugins.                    |
 * | troy_plugin_stats_wp                        | Update request stats by WordPress version for plugins.              |
 * | troy_plugin_stats_requests_live             | Live update request stats for plugins.                              |
 * |---------------------------------------------|---------------------------------------------------------------------|
 * | troy_stats_locales                          | Global update request stats by locale (unique sites).               |
 * | troy_stats_php                              | Global update request stats by PHP version (unique sites).          |
 * | troy_stats_wp                               | Global update request stats by WordPress version (unique sites).    |
 * |---------------------------------------------|---------------------------------------------------------------------|
 * | troy_packages                               | The main packages table.                                            |
 * | troy_package_metas                          | Meta data for packages (for installer generation).                  |
 * | troy_package_stats_totals                   | Total stats for packages (accumulated over all time).               |
 * | troy_package_stats_totals_daily_snapshots   | Daily snapshots of total stats for packages (cumulative).           |
 * |                                             | Stores frozen copy of package_stats_totals at end of day (graphs).  |
 * |                                             | This table can get partitioned (e.g., by year).                     |
 * | troy_package_stats_downloads                | Download stats for packages.                                        |
 * | troy_package_stats_downloads_live           | Live download stats for packages.                                   |
 * |---------------------------------------------|---------------------------------------------------------------------|
 *
 * @since 0.0.1184
 * @global \wpdb $wpdb
 *
 * @return string[] The initial database queries.
 */
function get_initial_db_schema_queries() {

	global $wpdb;

	$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
	$collate = trim( "ENGINE=InnoDB $collate" );

	$dbprefix = $wpdb->prefix;

	return [
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugins` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_slug_transfers` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`old_slug` varchar(191) NOT null,
			`new_slug` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `old_slug` (`old_slug`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_metas` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_contributors` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_infos` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_snapshots` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_integrations` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_integration_queue` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`package_version` varchar(20) NOT null,
			`mode` varchar(20) NOT null,
			`download_url` text NOT null,
			`revision_id` varchar(64) NOT null DEFAULT '',
			`type` varchar(20),
			`retry_after` datetime DEFAULT current_timestamp,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			unique index `plugin_id_package_version` (`plugin_id`, `package_version`),
			index `retry_after` (`retry_after`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_integration_history` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`package_version` varchar(50) NOT null,
			`version` varchar(20) NOT null DEFAULT '',
			`revision_id` varchar(64) NOT null DEFAULT '',
			`mode` varchar(20) NOT null,
			`status` varchar(20) NOT null DEFAULT 'failed',
			`reason` varchar(50) NOT null DEFAULT '',
			`details` text NOT null,
			`attempts` smallint unsigned NOT null DEFAULT 1,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id_package_version` (`plugin_id`, `package_version`),
			index `plugin_id_status` (`plugin_id`, `status`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_integration_logs` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_zips` (
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
			`checksums` longtext NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `version` (`version`),
			unique index `plugin_id_version` (`plugin_id`, `version`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_translations` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`locale` varchar(15) NOT null,
			`file_size` bigint unsigned NOT null,
			`origin_url` varchar(191) NOT null,
			`checksums` longtext NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version` (`plugin_id`, `version`),
			unique index `plugin_id_version_locale` (`plugin_id`, `version`, `locale`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_data_caches` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_ratings` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_totals` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`total_installs_this_epoch` bigint unsigned NOT null,
			`total_installs_last_epoch` bigint unsigned NOT null,
			`active_installs_this_epoch` bigint unsigned NOT null,
			`active_installs_last_epoch` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id` (`plugin_id`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_totals_daily_snapshots` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`date` date NOT null DEFAULT (current_date),
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`total_installs_this_epoch` bigint unsigned NOT null,
			`total_installs_last_epoch` bigint unsigned NOT null,
			`active_installs_this_epoch` bigint unsigned NOT null,
			`active_installs_last_epoch` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `plugin_id_date` (`plugin_id`, `date`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_versions` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`total_installs` bigint unsigned NOT null DEFAULT 0,
			`active_installs` bigint unsigned NOT null DEFAULT 0,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version` (`plugin_id`, `version`),
			unique index `plugin_id_version_origin_url` (`plugin_id`, `version`, `origin_url`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_versions_daily_snapshots` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`version` varchar(20) NOT null,
			`date` date NOT null DEFAULT (current_date),
			`origin_url` varchar(191) NOT null,
			`downloads` bigint unsigned NOT null,
			`views` bigint unsigned NOT null,
			`total_installs` bigint unsigned NOT null,
			`active_installs` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_version_date` (`plugin_id`, `version`, `date`),
			unique index `plugin_id_version_date_origin_url` (`plugin_id`, `version`, `date`, `origin_url`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_views` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_views_live` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`version` varchar(20) NOT null,
			`screen` varchar(20) NOT null,
			`locale` varchar(15) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_epoch` (`plugin_id`, `epoch`),
			index `epoch` (`epoch`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_downloads` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_downloads_live` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`version` varchar(20) NOT null,
			`type` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			index `plugin_id_epoch` (`plugin_id`, `epoch`),
			index `epoch` (`epoch`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_requests` (
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
			index `epoch_is_active` (`epoch`, `is_active`),
			unique index `plugin_id_epoch_version_is_active` (`plugin_id`, `epoch`, `version`, `is_active`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_locales` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`locale` varchar(15) NOT null,
			`install_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id` (`plugin_id`),
			unique index `plugin_id_epoch_locale` (`plugin_id`, `epoch`, `locale`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_php` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_wp` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_plugin_stats_requests_live` (
			`id` bigint unsigned NOT null auto_increment,
			`plugin_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`version` varchar(20) NOT null,
			`is_active` boolean NOT null DEFAULT 0,
			`uuid` varchar(100) NOT null,
			`request_count` int unsigned NOT null DEFAULT 1,
			`locales` longtext NOT null,
			`origin_url` varchar(191) NOT null,
			`php_version` varchar(20) NOT null,
			`wp_version` varchar(20) NOT null,
			`client_version` varchar(20) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			index `plugin_id_is_active` (`plugin_id`, `is_active`),
			index `plugin_id_epoch` (`plugin_id`, `epoch`),
			unique index `plugin_id_epoch_uuid_origin_url` (`plugin_id`, `epoch`, `uuid`, `origin_url`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_stats_locales` (
			`id` bigint unsigned NOT null auto_increment,
			`epoch` smallint unsigned NOT null,
			`locale` varchar(15) NOT null,
			`install_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `epoch_locale` (`epoch`, `locale`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_stats_php` (
			`id` bigint unsigned NOT null auto_increment,
			`epoch` smallint unsigned NOT null,
			`php_version` varchar(20) NOT null,
			`install_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `epoch_php_version` (`epoch`, `php_version`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_stats_wp` (
			`id` bigint unsigned NOT null auto_increment,
			`epoch` smallint unsigned NOT null,
			`wp_version` varchar(20) NOT null,
			`install_count` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `epoch_wp_version` (`epoch`, `wp_version`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_packages` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_package_metas` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`plugin_uri` varchar(250) NOT null,
			`name` varchar(191) NOT null,
			`description` varchar(191) NOT null,
			`version` varchar(20) NOT null,
			`author` varchar(191) NOT null,
			`author_uri` varchar(191) NOT null,
			`requires_wp` varchar(20) NOT null,
			`requires_php` varchar(20) NOT null,
			`install_timeout` int unsigned NOT null DEFAULT 30,
			`deactivate_on_completion` boolean NOT null DEFAULT 1,
			`delete_on_completion` boolean NOT null DEFAULT 0,
			`network_activation` varchar(20) NOT null DEFAULT 'block',
			`notice_severity` varchar(20) NOT null DEFAULT 'detailed',
			`plugins` longtext NOT null,
			`themes` longtext NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `package_id` (`package_id`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_package_stats_totals` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`downloads` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `package_id` (`package_id`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_package_stats_totals_daily_snapshots` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`date` date NOT null DEFAULT (current_date),
			`downloads` bigint unsigned NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			`updated_at` datetime DEFAULT current_timestamp on update current_timestamp,
			primary key (`id`),
			unique index `package_id_date` (`package_id`, `date`)
		) $collate",
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_package_stats_downloads` (
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
		"CREATE table IF NOT EXISTS `{$dbprefix}troy_package_stats_downloads_live` (
			`id` bigint unsigned NOT null auto_increment,
			`package_id` bigint unsigned NOT null,
			`epoch` smallint unsigned NOT null,
			`version` varchar(20) NOT null,
			`type` varchar(20) NOT null,
			`origin_url` varchar(191) NOT null,
			`created_at` datetime DEFAULT current_timestamp,
			primary key (`id`),
			index `package_id` (`package_id`),
			index `package_id_epoch` (`package_id`, `epoch`),
			index `epoch` (`epoch`)
		) $collate",
	];
}
