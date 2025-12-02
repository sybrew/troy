<?php
/**
 * @package Troy\Server\Stats
 * @access  private
 */

namespace Troy\Server\Stats;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	STATS_AGGREGATOR_BATCH_SIZE,
	STATS_AGGREGATOR_EPOCH_FINALIZE_DELAY,
};

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

/**
 * Class Troy\Server\Stats\Aggregator.
 *
 * Handles aggregation of live stats data into summary tables.
 * Processes plugins and packages in batches of 100 IDs per cron run.
 *
 * @since 0.0.1184
 */
final class Aggregator {

	/**
	 * Snapshots plugin stats for a batch of plugin IDs.
	 *
	 * Processes plugins in batches of 100 IDs, tracking progress via options.
	 * Uses SQL GROUP BY for all aggregation to minimize memory usage.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return bool True if there are more plugins to process, false if cycle complete.
	 */
	public static function snapshot_plugins() {

		global $wpdb;

		$last_id = (int) \get_option( 'troy_server_cron_batch_last_id_plugins', 0 );

		// Get batch of plugin IDs.
		$plugin_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id
			FROM {$wpdb->prefix}troy_plugins
			WHERE id > %d
			ORDER BY id
			LIMIT %d",
			$last_id,
			STATS_AGGREGATOR_BATCH_SIZE,
		) );

		if ( empty( $plugin_ids ) ) {
			// Cycle complete, reset for next cycle.
			\update_option( 'troy_server_cron_batch_last_id_plugins', 0, false );
			return false;
		}

		$plugin_ids_str = implode( ',', array_map( 'intval', $plugin_ids ) );
		$this_epoch     = API\Utils::get_epoch();
		$last_epoch     = $this_epoch - 1;

		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- No love for goto.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $plugin_ids_str is sanitized

		aggregate_request_counts: {

			$rows = $wpdb->get_results(
				"SELECT
					plugin_id,
					epoch,
					version,
					is_active,
					SUM(request_count) as request_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE plugin_id IN ($plugin_ids_str)
				GROUP BY plugin_id, epoch, version, is_active",
			);

			foreach ( $rows as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_plugin_stats_requests",
					[
						'plugin_id'     => $row->plugin_id,
						'epoch'         => $row->epoch,
						'version'       => $row->version,
						'is_active'     => $row->is_active,
						'request_count' => $row->request_count,
					],
					[ '%d', '%d', '%s', '%d', '%d' ],
				);
			}
		}

		aggregate_locale_stats: {

			$rows = $wpdb->get_results(
				"SELECT
					r.plugin_id,
					r.epoch,
					jt.locale,
					COUNT(DISTINCT r.uuid) as install_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live r,
					JSON_TABLE(
						r.locales,
						'$[*]' COLUMNS(locale VARCHAR(15) PATH '$')
					) jt
				WHERE r.plugin_id IN ($plugin_ids_str)
					AND jt.locale IS NOT null
					AND jt.locale != ''
				GROUP BY r.plugin_id, r.epoch, jt.locale",
			);

			foreach ( $rows as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_plugin_stats_locales",
					[
						'plugin_id'     => $row->plugin_id,
						'epoch'         => $row->epoch,
						'locale'        => $row->locale,
						'install_count' => $row->install_count,
					],
					[ '%d', '%d', '%s', '%d' ],
				);
			}
		}

		aggregate_php_stats: {

			$rows = $wpdb->get_results(
				"SELECT
					plugin_id,
					epoch,
					php_version,
					COUNT(DISTINCT uuid) as install_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE plugin_id IN ($plugin_ids_str)
					AND php_version != ''
				GROUP BY plugin_id, epoch, php_version",
			);

			foreach ( $rows as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_plugin_stats_php",
					[
						'plugin_id'     => $row->plugin_id,
						'epoch'         => $row->epoch,
						'php_version'   => $row->php_version,
						'install_count' => $row->install_count,
					],
					[ '%d', '%d', '%s', '%d' ],
				);
			}
		}

		aggregate_wp_stats: {

			$rows = $wpdb->get_results(
				"SELECT
					plugin_id,
					epoch,
					wp_version,
					COUNT(DISTINCT uuid) as install_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE plugin_id IN ($plugin_ids_str)
					AND wp_version != ''
				GROUP BY plugin_id, epoch, wp_version",
			);

			foreach ( $rows as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_plugin_stats_wp",
					[
						'plugin_id'     => $row->plugin_id,
						'epoch'         => $row->epoch,
						'wp_version'    => $row->wp_version,
						'install_count' => $row->install_count,
					],
					[ '%d', '%d', '%s', '%d' ],
				);
			}
		}

		aggregate_install_counts: {

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT
					plugin_id,
					version,
					origin_url,
					epoch,
					is_active,
					COUNT(DISTINCT uuid) as install_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE plugin_id IN ($plugin_ids_str)
					AND epoch IN (%d, %d)
				GROUP BY plugin_id, version, origin_url, epoch, is_active",
				$this_epoch,
				$last_epoch,
			) );

			$plugin_totals  = [];
			$version_totals = [];

			foreach ( $rows as $row ) {

				$plugin_totals[ $row->plugin_id ] ??= [
					'total_installs_this_epoch'  => 0,
					'total_installs_last_epoch'  => 0,
					'active_installs_this_epoch' => 0,
					'active_installs_last_epoch' => 0,
				];

				$version_key = "{$row->plugin_id}|{$row->version}|{$row->origin_url}";

				$version_totals[ $version_key ] ??= [
					'plugin_id'                  => $row->plugin_id,
					'version'                    => $row->version,
					'origin_url'                 => $row->origin_url,
					'total_installs_this_epoch'  => 0,
					'total_installs_last_epoch'  => 0,
					'active_installs_this_epoch' => 0,
					'active_installs_last_epoch' => 0,
				];

				$epoch_suffix = (int) $row->epoch === $this_epoch ? 'this_epoch' : 'last_epoch';

				$plugin_totals[ $row->plugin_id ][ "total_installs_{$epoch_suffix}" ] += (int) $row->install_count;
				$version_totals[ $version_key ][ "total_installs_{$epoch_suffix}" ]   += (int) $row->install_count;

				if ( $row->is_active ) {
					$plugin_totals[ $row->plugin_id ][ "active_installs_{$epoch_suffix}" ] += (int) $row->install_count;
					$version_totals[ $version_key ][ "active_installs_{$epoch_suffix}" ]   += (int) $row->install_count;
				}
			}

			foreach ( $plugin_totals as $plugin_id => $totals ) {

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_totals
						(plugin_id, downloads, views, total_installs_this_epoch, total_installs_last_epoch, active_installs_this_epoch, active_installs_last_epoch)
					VALUES (%d, 0, 0, %d, %d, %d, %d)
					ON DUPLICATE KEY UPDATE
						total_installs_this_epoch  = VALUES(total_installs_this_epoch),
						total_installs_last_epoch  = VALUES(total_installs_last_epoch),
						active_installs_this_epoch = VALUES(active_installs_this_epoch),
						active_installs_last_epoch = VALUES(active_installs_last_epoch)",
					$plugin_id,
					$totals['total_installs_this_epoch'],
					$totals['total_installs_last_epoch'],
					$totals['active_installs_this_epoch'],
					$totals['active_installs_last_epoch'],
				) );

				$active_install_count = max(
					$totals['active_installs_this_epoch'],
					$totals['active_installs_last_epoch'],
				);

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_data_caches
						(plugin_id, active_install_count)
					VALUES (%d, %d)
					ON DUPLICATE KEY UPDATE
						active_install_count = VALUES(active_install_count)",
					$plugin_id,
					$active_install_count,
				) );
			}

			foreach ( $version_totals as $totals ) {

				$total_installs  = max(
					$totals['total_installs_this_epoch'],
					$totals['total_installs_last_epoch'],
				);
				$active_installs = max(
					$totals['active_installs_this_epoch'],
					$totals['active_installs_last_epoch'],
				);

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_versions
						(plugin_id, version, origin_url, downloads, views, total_installs, active_installs)
					VALUES (%d, %s, %s, 0, 0, %d, %d)
					ON DUPLICATE KEY UPDATE
						total_installs  = VALUES(total_installs),
						active_installs = VALUES(active_installs)",
					$totals['plugin_id'],
					$totals['version'],
					$totals['origin_url'],
					$total_installs,
					$active_installs,
				) );
			}
		}

		aggregate_views: {

			$wpdb->query( 'START TRANSACTION' );

			$view_rows = $wpdb->get_results(
				"SELECT
					plugin_id,
					version,
					screen,
					locale,
					origin_url,
					COUNT(*) as views
				FROM {$wpdb->prefix}troy_plugin_stats_views_live
				WHERE plugin_id IN ($plugin_ids_str)
				GROUP BY plugin_id, version, screen, locale, origin_url",
			);

			$versions_agg = [];
			$totals_agg   = [];

			foreach ( $view_rows as $row ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_views
						(plugin_id, version, screen, locale, origin_url, views)
					VALUES (%d, %s, %s, %s, %s, %d)
					ON DUPLICATE KEY UPDATE
						views = views + VALUES(views)",
					$row->plugin_id,
					$row->version,
					$row->screen,
					$row->locale,
					$row->origin_url,
					$row->views,
				) );

				$version_key = "{$row->plugin_id}|{$row->version}|{$row->origin_url}";

				$versions_agg[ $version_key ] ??= [
					'plugin_id'  => $row->plugin_id,
					'version'    => $row->version,
					'origin_url' => $row->origin_url,
					'views'      => 0,
				];

				$versions_agg[ $version_key ]['views'] += (int) $row->views;

				$totals_agg[ $row->plugin_id ] ??= 0;
				$totals_agg[ $row->plugin_id ]  += (int) $row->views;
			}

			foreach ( $versions_agg as $row ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_versions
						(plugin_id, version, origin_url, downloads, views)
					VALUES (%d, %s, %s, 0, %d)
					ON DUPLICATE KEY UPDATE
						views = views + VALUES(views)",
					$row['plugin_id'],
					$row['version'],
					$row['origin_url'],
					$row['views'],
				) );
			}

			foreach ( $totals_agg as $plugin_id => $views ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_totals
						(plugin_id, downloads, views, total_installs_this_epoch, total_installs_last_epoch, active_installs_this_epoch, active_installs_last_epoch)
					VALUES (%d, 0, %d, 0, 0, 0, 0)
					ON DUPLICATE KEY UPDATE
						views = views + VALUES(views)",
					$plugin_id,
					$views,
				) );
			}

			// Delete aggregated rows from live table.
			$delete_result = $wpdb->query(
				"DELETE FROM {$wpdb->prefix}troy_plugin_stats_views_live
				WHERE plugin_id IN ($plugin_ids_str)",
			);

			if ( false === $delete_result ) {
				$wpdb->query( 'ROLLBACK' );
			} else {
				$wpdb->query( 'COMMIT' );
			}
		}

		aggregate_downloads: {

			$wpdb->query( 'START TRANSACTION' );

			$download_rows = $wpdb->get_results(
				"SELECT
					plugin_id,
					version,
					type,
					origin_url,
					COUNT(*) as downloads
				FROM {$wpdb->prefix}troy_plugin_stats_downloads_live
				WHERE plugin_id IN ($plugin_ids_str)
				GROUP BY plugin_id, version, type, origin_url",
			);

			$versions_agg = [];
			$totals_agg   = [];

			foreach ( $download_rows as $row ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_downloads
						(plugin_id, version, type, origin_url, downloads)
					VALUES (%d, %s, %s, %s, %d)
					ON DUPLICATE KEY UPDATE
						downloads = downloads + VALUES(downloads)",
					$row->plugin_id,
					$row->version,
					$row->type,
					$row->origin_url,
					$row->downloads,
				) );

				$version_key = "{$row->plugin_id}|{$row->version}|{$row->origin_url}";

				$versions_agg[ $version_key ] ??= [
					'plugin_id'  => $row->plugin_id,
					'version'    => $row->version,
					'origin_url' => $row->origin_url,
					'downloads'  => 0,
				];

				$versions_agg[ $version_key ]['downloads'] += (int) $row->downloads;

				$totals_agg[ $row->plugin_id ] ??= 0;
				$totals_agg[ $row->plugin_id ]  += (int) $row->downloads;
			}

			foreach ( $versions_agg as $row ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_versions
						(plugin_id, version, origin_url, downloads, views)
					VALUES (%d, %s, %s, %d, 0)
					ON DUPLICATE KEY UPDATE
						downloads = downloads + VALUES(downloads)",
					$row['plugin_id'],
					$row['version'],
					$row['origin_url'],
					$row['downloads'],
				) );
			}

			foreach ( $totals_agg as $plugin_id => $downloads ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_totals
						(plugin_id, downloads, views, total_installs_this_epoch, total_installs_last_epoch, active_installs_this_epoch, active_installs_last_epoch)
					VALUES (%d, %d, 0, 0, 0, 0, 0)
					ON DUPLICATE KEY UPDATE
						downloads = downloads + VALUES(downloads)",
					$plugin_id,
					$downloads,
				) );
			}

			// Delete aggregated rows from live table.
			$delete_result = $wpdb->query(
				"DELETE FROM {$wpdb->prefix}troy_plugin_stats_downloads_live
				WHERE plugin_id IN ($plugin_ids_str)",
			);

			if ( false === $delete_result ) {
				$wpdb->query( 'ROLLBACK' );
			} else {
				$wpdb->query( 'COMMIT' );
			}
		}

		// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$max_id = max( $plugin_ids );
		\update_option( 'troy_server_cron_batch_last_id_plugins', $max_id, false );

		return true;
	}

	/**
	 * Snapshots package stats for a batch of package IDs.
	 *
	 * Processes packages in batches of 100 IDs, tracking progress via options.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return bool True if there are more packages to process, false if cycle complete.
	 */
	public static function snapshot_packages() {

		global $wpdb;

		$last_id = (int) \get_option( 'troy_server_cron_batch_last_id_packages', 0 );

		$package_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id
			FROM {$wpdb->prefix}troy_packages
			WHERE id > %d
			ORDER BY id
			LIMIT %d",
			$last_id,
			STATS_AGGREGATOR_BATCH_SIZE,
		) );

		if ( empty( $package_ids ) ) {
			\update_option( 'troy_server_cron_batch_last_id_packages', 0, false );
			return false;
		}

		$package_ids_str = implode( ',', array_map( 'intval', $package_ids ) );

		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- No love for goto.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $package_ids_str is sanitized

		aggregate_downloads: {

			$wpdb->query( 'START TRANSACTION' );

			$download_rows = $wpdb->get_results(
				"SELECT
					package_id,
					version,
					type,
					origin_url,
					COUNT(*) as downloads
				FROM {$wpdb->prefix}troy_package_stats_downloads_live
				WHERE package_id IN ($package_ids_str)
				GROUP BY package_id, version, type, origin_url",
			);

			$totals_agg = [];

			foreach ( $download_rows as $row ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_package_stats_downloads
						(package_id, version, type, origin_url, downloads)
					VALUES (%d, %s, %s, %s, %d)
					ON DUPLICATE KEY UPDATE
						downloads = downloads + VALUES(downloads)",
					$row->package_id,
					$row->version,
					$row->type,
					$row->origin_url,
					$row->downloads,
				) );

				$totals_agg[ $row->package_id ] ??= 0;
				$totals_agg[ $row->package_id ]  += (int) $row->downloads;
			}

			foreach ( $totals_agg as $package_id => $downloads ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_package_stats_totals
						(package_id, downloads)
					VALUES (%d, %d)
					ON DUPLICATE KEY UPDATE
						downloads = downloads + VALUES(downloads)",
					$package_id,
					$downloads,
				) );
			}

			// Delete aggregated rows from live table.
			$delete_result = $wpdb->query(
				"DELETE FROM {$wpdb->prefix}troy_package_stats_downloads_live
				WHERE package_id IN ($package_ids_str)",
			);

			if ( false === $delete_result ) {
				$wpdb->query( 'ROLLBACK' );
			} else {
				$wpdb->query( 'COMMIT' );
			}
		}

		// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$max_id = max( $package_ids );
		\update_option( 'troy_server_cron_batch_last_id_packages', $max_id, false );

		return true;
	}

	/**
	 * Snapshots global stats (locales, PHP, WP versions across all plugins).
	 *
	 * Should be called after all plugin batches complete (full cycle).
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function snapshot_global_stats() {

		global $wpdb;

		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- No love for goto.

		aggregate_locales: {

			$locale_rows = $wpdb->get_results(
				"SELECT
					r.epoch,
					jt.locale,
					COUNT(DISTINCT r.uuid) as install_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live r,
					JSON_TABLE(
						r.locales,
						'$[*]' COLUMNS(locale VARCHAR(15) PATH '$')
					) jt
				WHERE jt.locale IS NOT null
					AND jt.locale != ''
				GROUP BY r.epoch, jt.locale",
			);

			foreach ( $locale_rows as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_stats_locales",
					[
						'epoch'         => $row->epoch,
						'locale'        => $row->locale,
						'install_count' => $row->install_count,
					],
					[ '%d', '%s', '%d' ],
				);
			}
		}

		aggregate_php: {

			$php_rows = $wpdb->get_results(
				"SELECT
					epoch,
					php_version,
					COUNT(DISTINCT uuid) as install_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE php_version != ''
				GROUP BY epoch, php_version",
			);

			foreach ( $php_rows as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_stats_php",
					[
						'epoch'         => $row->epoch,
						'php_version'   => $row->php_version,
						'install_count' => $row->install_count,
					],
					[ '%d', '%s', '%d' ],
				);
			}
		}

		aggregate_wp: {

			$wp_rows = $wpdb->get_results(
				"SELECT
					epoch,
					wp_version,
					COUNT(DISTINCT uuid) as install_count
				FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE wp_version != ''
				GROUP BY epoch, wp_version",
			);

			foreach ( $wp_rows as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_stats_wp",
					[
						'epoch'         => $row->epoch,
						'wp_version'    => $row->wp_version,
						'install_count' => $row->install_count,
					],
					[ '%d', '%s', '%d' ],
				);
			}
		}

		// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
	}

	/**
	 * Creates daily snapshots for historical comparison.
	 *
	 * Table stats_totals_daily_snapshots: Cumulative (frozen copy of stats_totals).
	 * Table stats_versions_daily_snapshots: Daily delta from live tables (that day's activity).
	 *
	 * NOTE: When a plugin has no activity on a given day, there will be no entry for it
	 * in stats_versions_daily_snapshots for that day. This is expected behavior.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function snapshot_to_date() {

		global $wpdb;

		$today      = \current_time( 'Y-m-d' );
		$this_epoch = API\Utils::get_epoch();
		$last_epoch = $this_epoch - 1;

		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- No love for goto.

		snapshot_totals: {

			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}troy_plugin_stats_totals_daily_snapshots
					(plugin_id, date, downloads, views, total_installs_this_epoch, total_installs_last_epoch, active_installs_this_epoch, active_installs_last_epoch)
				SELECT
					plugin_id,
					%s,
					downloads,
					views,
					total_installs_this_epoch,
					total_installs_last_epoch,
					active_installs_this_epoch,
					active_installs_last_epoch
				FROM {$wpdb->prefix}troy_plugin_stats_totals
				ON DUPLICATE KEY UPDATE
					downloads                  = VALUES(downloads),
					views                      = VALUES(views),
					total_installs_this_epoch  = VALUES(total_installs_this_epoch),
					total_installs_last_epoch  = VALUES(total_installs_last_epoch),
					active_installs_this_epoch = VALUES(active_installs_this_epoch),
					active_installs_last_epoch = VALUES(active_installs_last_epoch)",
				$today,
			) );
		}

		snapshot_versions: {

			$download_counts = [];

			$download_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT plugin_id, version, origin_url, COUNT(*) as downloads
				FROM {$wpdb->prefix}troy_plugin_stats_downloads_live
				WHERE DATE(created_at) = %s
				GROUP BY plugin_id, version, origin_url",
				$today,
			) );

			foreach ( $download_rows as $row ) {
				$key                     = "{$row->plugin_id}|{$row->version}|{$row->origin_url}";
				$download_counts[ $key ] = (int) $row->downloads;
			}

			$view_counts = [];

			$view_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT plugin_id, version, origin_url, COUNT(*) as views
				FROM {$wpdb->prefix}troy_plugin_stats_views_live
				WHERE DATE(created_at) = %s
				GROUP BY plugin_id, version, origin_url",
				$today,
			) );

			foreach ( $view_rows as $row ) {
				$key                 = "{$row->plugin_id}|{$row->version}|{$row->origin_url}";
				$view_counts[ $key ] = (int) $row->views;
			}

			// Get install counts from pre-aggregated stats_versions table.
			$install_counts = [];

			$install_rows = $wpdb->get_results(
				"SELECT plugin_id, version, origin_url, total_installs, active_installs
				FROM {$wpdb->prefix}troy_plugin_stats_versions",
			);

			foreach ( $install_rows as $row ) {
				$key                    = "{$row->plugin_id}|{$row->version}|{$row->origin_url}";
				$install_counts[ $key ] = [
					'total'  => (int) $row->total_installs,
					'active' => (int) $row->active_installs,
				];
			}

			$all_keys = array_unique( array_merge(
				array_keys( $download_counts ),
				array_keys( $view_counts ),
				array_keys( $install_counts ),
			) );

			foreach ( $all_keys as $key ) {

				[ $plugin_id, $version, $origin_url ] = explode( '|', $key, 3 );

				$downloads       = $download_counts[ $key ] ?? 0;
				$views           = $view_counts[ $key ] ?? 0;
				$installs        = $install_counts[ $key ] ?? [
					'total'  => 0,
					'active' => 0,
				];
				$total_installs  = $installs['total'];
				$active_installs = $installs['active'];

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}troy_plugin_stats_versions_daily_snapshots
						(plugin_id, version, date, origin_url, downloads, views, total_installs, active_installs)
					VALUES (%d, %s, %s, %s, %d, %d, %d, %d)
					ON DUPLICATE KEY UPDATE
						downloads       = VALUES(downloads),
						views           = VALUES(views),
						total_installs  = VALUES(total_installs),
						active_installs = VALUES(active_installs)",
					$plugin_id,
					$version,
					$today,
					$origin_url,
					$downloads,
					$views,
					$total_installs,
					$active_installs,
				) );
			}
		}

		// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
	}

	/**
	 * Finalizes old epochs by deleting live data.
	 *
	 * Deletes data from epochs older than this one, but only after
	 * STATS_AGGREGATOR_EPOCH_FINALIZE_DELAY (48 hours) has passed since this epoch started.
	 * Cleans all live tables: requests, views, downloads (plugin and package).
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function finalize_old_epochs() {

		global $wpdb;

		$this_epoch       = API\Utils::get_epoch();
		$this_epoch_start = $this_epoch * \WEEK_IN_SECONDS;

		// If we're 2 days into this epoch, it's safe to finalize last epochs.
		if ( time() < $this_epoch_start + STATS_AGGREGATOR_EPOCH_FINALIZE_DELAY )
			return;

		delete_plugin_requests: {

			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE epoch < %d",
				$this_epoch,
			) );
		}

		delete_plugin_views: {

			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}troy_plugin_stats_views_live
				WHERE epoch < %d",
				$this_epoch,
			) );
		}

		delete_plugin_downloads: {

			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}troy_plugin_stats_downloads_live
				WHERE epoch < %d",
				$this_epoch,
			) );
		}

		delete_package_downloads: {

			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}troy_package_stats_downloads_live
				WHERE epoch < %d",
				$this_epoch,
			) );
		}
	}
}
