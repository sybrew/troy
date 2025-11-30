<?php
/**
 * @package Troy\Server\Stats
 * @access  private
 */

namespace Troy\Server\Stats;

\defined( 'Troy\Server\ABSPATH' ) or die;

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
 *
 * @since 0.0.1184
 */
final class Aggregator {

	/**
	 * The number of seconds after an epoch ends before we finalize it.
	 * 48 hours = 172800 seconds.
	 *
	 * @since 0.0.1184
	 */
	private const EPOCH_FINALIZE_DELAY = 48 * \HOUR_IN_SECONDS;

	/**
	 * Snapshots update request stats from live table to aggregated tables.
	 *
	 * Aggregates request counts by plugin_id, epoch, version, is_active.
	 * Also aggregates locale, PHP version, and WordPress version stats.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function snapshot_update_requests() {

		global $wpdb;

		$current_epoch  = API\Utils::get_epoch();
		$previous_epoch = $current_epoch - 1;

		// Fetch all raw rows from live table - minimal SQL work.
		$live_rows = $wpdb->get_results(
			"SELECT plugin_id, epoch, version, is_active, uuid, locales, php_version, wp_version
			FROM {$wpdb->prefix}troy_plugin_stats_requests_live",
		);

		if ( ! $live_rows )
			return;

		// Aggregate in PHP.
		$request_stats      = [];
		$locale_uuids       = [];
		$php_uuids          = [];
		$wp_uuids           = [];
		$installation_uuids = [];

		// Global stats - track unique UUIDs across all plugins.
		$global_locale_uuids = [];
		$global_php_uuids    = [];
		$global_wp_uuids     = [];

		foreach ( $live_rows as $row ) {
			// Request counts.
			$request_key = "$row->plugin_id|$row->epoch|$row->version|$row->is_active";

			$request_stats[ $request_key ] ??= [
				'plugin_id'     => $row->plugin_id,
				'epoch'         => $row->epoch,
				'version'       => $row->version,
				'is_active'     => $row->is_active,
				'request_count' => 0,
			];

			++$request_stats[ $request_key ]['request_count'];

			// Locale stats - track unique UUIDs per locale.
			$locales = json_decode( $row->locales, true ) ?: [];

			foreach ( $locales as $locale ) {
				if ( ! \is_string( $locale ) or '' === $locale )
					continue;

				// Per-plugin locale stats.
				$locale_key = "$row->plugin_id|$row->epoch|$locale";

				$locale_uuids[ $locale_key ]             ??= [];
				$locale_uuids[ $locale_key ][ $row->uuid ] = true;

				// Global locale stats.
				$global_locale_key = "$row->epoch|$locale";

				$global_locale_uuids[ $global_locale_key ]             ??= [];
				$global_locale_uuids[ $global_locale_key ][ $row->uuid ] = true;
			}

			// PHP version stats - track unique UUIDs per PHP version.
			if ( '' !== $row->php_version ) {
				// Per-plugin PHP stats.
				$php_key = "$row->plugin_id|$row->epoch|$row->php_version";

				$php_uuids[ $php_key ]             ??= [];
				$php_uuids[ $php_key ][ $row->uuid ] = true;

				// Global PHP stats.
				$global_php_key = "$row->epoch|$row->php_version";

				$global_php_uuids[ $global_php_key ]             ??= [];
				$global_php_uuids[ $global_php_key ][ $row->uuid ] = true;
			}

			// WordPress version stats - track unique UUIDs per WP version.
			if ( '' !== $row->wp_version ) {
				// Per-plugin WP stats.
				$wp_key = "$row->plugin_id|$row->epoch|$row->wp_version";

				$wp_uuids[ $wp_key ]             ??= [];
				$wp_uuids[ $wp_key ][ $row->uuid ] = true;

				// Global WP stats.
				$global_wp_key = "$row->epoch|$row->wp_version";

				$global_wp_uuids[ $global_wp_key ]             ??= [];
				$global_wp_uuids[ $global_wp_key ][ $row->uuid ] = true;
			}

			// Track unique UUIDs for installation counts (only active installs in relevant epochs).
			if (
				   $row->is_active
				&& (
					(int) $row->epoch === $current_epoch || (int) $row->epoch === $previous_epoch
				)
			) {
				$uuid_key = "$row->plugin_id|$row->version|$row->epoch";

				$installation_uuids[ $uuid_key ]             ??= [];
				$installation_uuids[ $uuid_key ][ $row->uuid ] = true;
			}
		}

		// Write request stats.
		foreach ( $request_stats as $row ) {
			$wpdb->replace(
				"{$wpdb->prefix}troy_plugin_stats_requests",
				[
					'plugin_id'     => $row['plugin_id'],
					'epoch'         => $row['epoch'],
					'version'       => $row['version'],
					'is_active'     => $row['is_active'],
					'request_count' => $row['request_count'],
				],
				[ '%d', '%d', '%s', '%d', '%d' ],
			);
		}

		// Write locale stats - count unique UUIDs.
		foreach ( $locale_uuids as $key => $uuids ) {
			[ $plugin_id, $epoch, $locale ] = explode( '|', $key );

			$wpdb->replace(
				"{$wpdb->prefix}troy_plugin_stats_locales",
				[
					'plugin_id'     => $plugin_id,
					'epoch'         => $epoch,
					'locale'        => $locale,
					'install_count' => \count( $uuids ),
				],
				[ '%d', '%d', '%s', '%d' ],
			);
		}

		// Write PHP version stats - count unique UUIDs.
		foreach ( $php_uuids as $key => $uuids ) {
			[ $plugin_id, $epoch, $php_version ] = explode( '|', $key );

			$wpdb->replace(
				"{$wpdb->prefix}troy_plugin_stats_php",
				[
					'plugin_id'     => $plugin_id,
					'epoch'         => $epoch,
					'php_version'   => $php_version,
					'install_count' => \count( $uuids ),
				],
				[ '%d', '%d', '%s', '%d' ],
			);
		}

		// Write WP version stats - count unique UUIDs.
		foreach ( $wp_uuids as $key => $uuids ) {
			[ $plugin_id, $epoch, $wp_version ] = explode( '|', $key );

			$wpdb->replace(
				"{$wpdb->prefix}troy_plugin_stats_wp",
				[
					'plugin_id'     => $plugin_id,
					'epoch'         => $epoch,
					'wp_version'    => $wp_version,
					'install_count' => \count( $uuids ),
				],
				[ '%d', '%d', '%s', '%d' ],
			);
		}

		// Write global locale stats - count unique UUIDs across all plugins.
		foreach ( $global_locale_uuids as $key => $uuids ) {
			[ $epoch, $locale ] = explode( '|', $key );

			$wpdb->replace(
				"{$wpdb->prefix}troy_stats_locales",
				[
					'epoch'         => $epoch,
					'locale'        => $locale,
					'install_count' => \count( $uuids ),
				],
				[ '%d', '%s', '%d' ],
			);
		}

		// Write global PHP version stats - count unique UUIDs across all plugins.
		foreach ( $global_php_uuids as $key => $uuids ) {
			[ $epoch, $php_version ] = explode( '|', $key );

			$wpdb->replace(
				"{$wpdb->prefix}troy_stats_php",
				[
					'epoch'         => $epoch,
					'php_version'   => $php_version,
					'install_count' => \count( $uuids ),
				],
				[ '%d', '%s', '%d' ],
			);
		}

		// Write global WP version stats - count unique UUIDs across all plugins.
		foreach ( $global_wp_uuids as $key => $uuids ) {
			[ $epoch, $wp_version ] = explode( '|', $key );

			$wpdb->replace(
				"{$wpdb->prefix}troy_stats_wp",
				[
					'epoch'         => $epoch,
					'wp_version'    => $wp_version,
					'install_count' => \count( $uuids ),
				],
				[ '%d', '%s', '%d' ],
			);
		}

		// Calculate installation counts from unique UUIDs.
		$installation_stats = [];

		foreach ( $installation_uuids as $key => $uuids ) {
			[ $plugin_id, $version, $epoch ] = explode( '|', $key );

			$stats_key = "$plugin_id|$version";

			$installation_stats[ $stats_key ] ??= [
				'plugin_id'                    => $plugin_id,
				'version'                      => $version,
				'installations_current_epoch'  => 0,
				'installations_previous_epoch' => 0,
			];

			$count = \count( $uuids );

			if ( (int) $epoch === $current_epoch ) {
				$installation_stats[ $stats_key ]['installations_current_epoch'] = $count;
			} else {
				$installation_stats[ $stats_key ]['installations_previous_epoch'] = $count;
			}
		}

		// Collect totals per plugin while updating per-version stats.
		$plugin_totals = [];

		foreach ( $installation_stats as $row ) {
			// Update per-version stats.
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, downloads, views
					FROM {$wpdb->prefix}troy_plugin_stats_versions
					WHERE plugin_id = %d AND version = %s",
					$row['plugin_id'],
					$row['version'],
				),
			);

			if ( $existing ) {
				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_stats_versions",
					[
						'installations_current_epoch'  => $row['installations_current_epoch'],
						'installations_previous_epoch' => $row['installations_previous_epoch'],
					],
					[ 'id' => $existing->id ],
					[ '%d', '%d' ],
					[ '%d' ],
				);
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugin_stats_versions",
					[
						'plugin_id'                    => $row['plugin_id'],
						'version'                      => $row['version'],
						'origin_url'                   => '',
						'downloads'                    => 0,
						'views'                        => 0,
						'installations_current_epoch'  => $row['installations_current_epoch'],
						'installations_previous_epoch' => $row['installations_previous_epoch'],
					],
					[ '%d', '%s', '%s', '%d', '%d', '%d', '%d' ],
				);
			}

			// Accumulate for totals.
			$plugin_totals[ $row['plugin_id'] ] ??= [
				'installations_current_epoch'  => 0,
				'installations_previous_epoch' => 0,
			];

			$plugin_totals[ $row['plugin_id'] ]['installations_current_epoch']  += $row['installations_current_epoch'];
			$plugin_totals[ $row['plugin_id'] ]['installations_previous_epoch'] += $row['installations_previous_epoch'];
		}

		// Update totals.
		foreach ( $plugin_totals as $plugin_id => $totals ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, downloads, views
					FROM {$wpdb->prefix}troy_plugin_stats_totals
					WHERE plugin_id = %d",
					$plugin_id,
				),
			);

			if ( $existing ) {
				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_stats_totals",
					[
						'installations_current_epoch'  => $totals['installations_current_epoch'],
						'installations_previous_epoch' => $totals['installations_previous_epoch'],
					],
					[ 'id' => $existing->id ],
					[ '%d', '%d' ],
					[ '%d' ],
				);
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugin_stats_totals",
					[
						'plugin_id'                    => $plugin_id,
						'downloads'                    => 0,
						'views'                        => 0,
						'installations_current_epoch'  => $totals['installations_current_epoch'],
						'installations_previous_epoch' => $totals['installations_previous_epoch'],
					],
					[ '%d', '%d', '%d', '%d', '%d' ],
				);
			}
		}
	}

	/**
	 * Snapshots view stats from live table to aggregated tables.
	 *
	 * Note: All aggregation happens in PHP memory via associative arrays keyed by origin_url.
	 * For horizontal scaling with multiple workers, you would need database-level aggregation
	 * (e.g., INSERT ... ON DUPLICATE KEY UPDATE) or a distributed aggregation mechanism.
	 * Currently, origin_url data accumulates without collision because we run on a single server.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function snapshot_views() {

		global $wpdb;

		// Fetch all raw rows - no SQL aggregation.
		$live_rows = $wpdb->get_results(
			"SELECT plugin_id, version, screen, locale, origin_url
			FROM {$wpdb->prefix}troy_plugin_stats_views_live",
		);

		if ( ! $live_rows )
			return;

		// Aggregate in PHP.
		$view_stats = [];
		$stats_agg  = [];
		$totals_agg = [];

		foreach ( $live_rows as $row ) {
			// View stats (full granularity).
			$view_key = "$row->plugin_id|$row->version|$row->screen|$row->locale|$row->origin_url";

			$view_stats[ $view_key ] ??= [
				'plugin_id'  => $row->plugin_id,
				'version'    => $row->version,
				'screen'     => $row->screen,
				'locale'     => $row->locale,
				'origin_url' => $row->origin_url,
				'views'      => 0,
			];

			++$view_stats[ $view_key ]['views'];

			// Stats aggregate.
			$stats_key = "$row->plugin_id|$row->version|$row->origin_url";

			$stats_agg[ $stats_key ] ??= [
				'plugin_id'  => $row->plugin_id,
				'version'    => $row->version,
				'origin_url' => $row->origin_url,
				'views'      => 0,
			];

			++$stats_agg[ $stats_key ]['views'];

			// Totals aggregate.
			$totals_key = "$row->plugin_id";

			$totals_agg[ $totals_key ] ??= [
				'plugin_id' => $row->plugin_id,
				'views'     => 0,
			];

			++$totals_agg[ $totals_key ]['views'];
		}

		// Write view_stats.
		foreach ( $view_stats as $row ) {
			$wpdb->replace(
				"{$wpdb->prefix}troy_plugin_stats_views",
				[
					'plugin_id'  => $row['plugin_id'],
					'version'    => $row['version'],
					'screen'     => $row['screen'],
					'locale'     => $row['locale'],
					'origin_url' => $row['origin_url'],
					'views'      => $row['views'],
				],
				[ '%d', '%s', '%s', '%s', '%s', '%d' ],
			);
		}

		// Update stats table.
		foreach ( $stats_agg as $row ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$wpdb->prefix}troy_plugin_stats_versions
					WHERE plugin_id = %d AND version = %s AND origin_url = %s",
					$row['plugin_id'],
					$row['version'],
					$row['origin_url'],
				),
			);

			if ( $existing ) {
				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_stats_versions",
					[ 'views' => $row['views'] ],
					[ 'id' => $existing ],
					[ '%d' ],
					[ '%d' ],
				);
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugin_stats_versions",
					[
						'plugin_id'                    => $row['plugin_id'],
						'version'                      => $row['version'],
						'origin_url'                   => $row['origin_url'],
						'downloads'                    => 0,
						'views'                        => $row['views'],
						'installations_current_epoch'  => 0,
						'installations_previous_epoch' => 0,
					],
					[ '%d', '%s', '%s', '%d', '%d', '%d', '%d' ],
				);
			}
		}

		// Update stats_totals table.
		foreach ( $totals_agg as $row ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$wpdb->prefix}troy_plugin_stats_totals
					WHERE plugin_id = %d",
					$row['plugin_id'],
				),
			);

			if ( $existing ) {
				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_stats_totals",
					[ 'views' => $row['views'] ],
					[ 'id' => $existing ],
					[ '%d' ],
					[ '%d' ],
				);
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugin_stats_totals",
					[
						'plugin_id'                    => $row['plugin_id'],
						'downloads'                    => 0,
						'views'                        => $row['views'],
						'installations_current_epoch'  => 0,
						'installations_previous_epoch' => 0,
					],
					[ '%d', '%d', '%d', '%d', '%d' ],
				);
			}
		}
	}

	/**
	 * Snapshots download stats from live table to aggregated tables.
	 *
	 * Note: All aggregation happens in PHP memory via associative arrays keyed by origin_url.
	 * For horizontal scaling with multiple workers, you would need database-level aggregation
	 * (e.g., INSERT ... ON DUPLICATE KEY UPDATE) or a distributed aggregation mechanism.
	 * Currently, origin_url data accumulates without collision because we run on a single server.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function snapshot_downloads() {

		global $wpdb;

		// Fetch all raw plugin download rows - no SQL aggregation.
		$live_rows = $wpdb->get_results(
			"SELECT plugin_id, version, type, origin_url
			FROM {$wpdb->prefix}troy_plugin_stats_downloads_live",
		);

		if ( $live_rows ) {
			// Aggregate in PHP.
			$download_stats = [];
			$stats_agg      = [];
			$totals_agg     = [];

			foreach ( $live_rows as $row ) {
				// Download stats (full granularity).
				$dl_key = "$row->plugin_id|$row->version|$row->type|$row->origin_url";

				$download_stats[ $dl_key ] ??= [
					'plugin_id'  => $row->plugin_id,
					'version'    => $row->version,
					'type'       => $row->type,
					'origin_url' => $row->origin_url,
					'downloads'  => 0,
				];

				++$download_stats[ $dl_key ]['downloads'];

				// Stats aggregate.
				$stats_key = "$row->plugin_id|$row->version|$row->origin_url";

				$stats_agg[ $stats_key ] ??= [
					'plugin_id'  => $row->plugin_id,
					'version'    => $row->version,
					'origin_url' => $row->origin_url,
					'downloads'  => 0,
				];

				++$stats_agg[ $stats_key ]['downloads'];

				// Totals aggregate.
				$totals_key = "$row->plugin_id";

				$totals_agg[ $totals_key ] ??= [
					'plugin_id' => $row->plugin_id,
					'downloads' => 0,
				];

				++$totals_agg[ $totals_key ]['downloads'];
			}

			// Write download_stats.
			foreach ( $download_stats as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_plugin_stats_downloads",
					[
						'plugin_id'  => $row['plugin_id'],
						'version'    => $row['version'],
						'type'       => $row['type'],
						'origin_url' => $row['origin_url'],
						'downloads'  => $row['downloads'],
					],
					[ '%d', '%s', '%s', '%s', '%d' ],
				);
			}

			// Update stats table.
			foreach ( $stats_agg as $row ) {
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id
						FROM {$wpdb->prefix}troy_plugin_stats_versions
						WHERE plugin_id = %d AND version = %s AND origin_url = %s",
						$row['plugin_id'],
						$row['version'],
						$row['origin_url'],
					),
				);

				if ( $existing ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_plugin_stats_versions",
						[ 'downloads' => $row['downloads'] ],
						[ 'id' => $existing ],
						[ '%d' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_plugin_stats_versions",
						[
							'plugin_id'                    => $row['plugin_id'],
							'version'                      => $row['version'],
							'origin_url'                   => $row['origin_url'],
							'downloads'                    => $row['downloads'],
							'views'                        => 0,
							'installations_current_epoch'  => 0,
							'installations_previous_epoch' => 0,
						],
						[ '%d', '%s', '%s', '%d', '%d', '%d', '%d' ],
					);
				}
			}

			// Update stats_totals table.
			foreach ( $totals_agg as $row ) {
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id
						FROM {$wpdb->prefix}troy_plugin_stats_totals
						WHERE plugin_id = %d",
						$row['plugin_id'],
					),
				);

				if ( $existing ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_plugin_stats_totals",
						[ 'downloads' => $row['downloads'] ],
						[ 'id' => $existing ],
						[ '%d' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_plugin_stats_totals",
						[
							'plugin_id'                    => $row['plugin_id'],
							'downloads'                    => $row['downloads'],
							'views'                        => 0,
							'installations_current_epoch'  => 0,
							'installations_previous_epoch' => 0,
						],
						[ '%d', '%d', '%d', '%d', '%d' ],
					);
				}
			}
		}

		// Aggregate package downloads separately.
		$package_live_rows = $wpdb->get_results(
			"SELECT package_id, version, type, origin_url
			FROM {$wpdb->prefix}troy_package_stats_downloads_live",
		);

		if ( $package_live_rows ) {
			$package_stats = [];

			foreach ( $package_live_rows as $row ) {
				$key = "$row->package_id|$row->version|$row->type|$row->origin_url";

				$package_stats[ $key ] ??= [
					'package_id' => $row->package_id,
					'version'    => $row->version,
					'type'       => $row->type,
					'origin_url' => $row->origin_url,
					'downloads'  => 0,
				];

				++$package_stats[ $key ]['downloads'];
			}

			foreach ( $package_stats as $row ) {
				$wpdb->replace(
					"{$wpdb->prefix}troy_package_stats_downloads",
					[
						'package_id' => $row['package_id'],
						'version'    => $row['version'],
						'type'       => $row['type'],
						'origin_url' => $row['origin_url'],
						'downloads'  => $row['downloads'],
					],
					[ '%d', '%s', '%s', '%s', '%d' ],
				);
			}

			// Aggregate package totals by package_id.
			$package_totals = [];

			foreach ( $package_stats as $row ) {
				$key = "{$row['package_id']}";

				$package_totals[ $key ] ??= [
					'package_id' => $row['package_id'],
					'downloads'  => 0,
				];

				$package_totals[ $key ]['downloads'] += $row['downloads'];
			}

			// Update package stats_totals table.
			foreach ( $package_totals as $row ) {
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id
						FROM {$wpdb->prefix}troy_package_stats_totals
						WHERE package_id = %d",
						$row['package_id'],
					),
				);

				if ( $existing ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_package_stats_totals",
						[ 'downloads' => $row['downloads'] ],
						[ 'id' => $existing ],
						[ '%d' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_package_stats_totals",
						[
							'package_id' => $row['package_id'],
							'downloads'  => $row['downloads'],
						],
						[ '%d', '%d' ],
					);
				}
			}
		}
	}

	/**
	 * Updates the active_install_count in data_caches table.
	 *
	 * Active installs = max(current_epoch, previous_epoch) per version, summed.
	 * We use max because UUIDs rotate each epoch, so we can't track sites across
	 * epoch boundaries. The higher of the two epochs gives the best estimate.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function update_active_install_counts() {

		global $wpdb;

		$install_counts = $wpdb->get_results(
			"SELECT
				plugin_id,
				SUM(
					GREATEST(installations_current_epoch, installations_previous_epoch)
				) as active_install_count
			FROM {$wpdb->prefix}troy_plugin_stats_totals
			GROUP BY plugin_id",
		);

		foreach ( $install_counts as $row ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$wpdb->prefix}troy_plugin_data_caches
					WHERE plugin_id = %d",
					$row->plugin_id,
				),
			);

			if ( $existing ) {
				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_data_caches",
					[ 'active_install_count' => $row->active_install_count ],
					[ 'id' => $existing ],
					[ '%d' ],
					[ '%d' ],
				);
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugin_data_caches",
					[
						'plugin_id'            => $row->plugin_id,
						'active_install_count' => $row->active_install_count,
					],
					[ '%d', '%d' ],
				);
			}
		}
	}

	/**
	 * Creates daily snapshots for historical comparison.
	 *
	 * Copies current stats to _to_date tables for the current date.
	 * This enables "compare past months" functionality.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function snapshot_to_date() {

		global $wpdb;

		$today = \current_time( 'Y-m-d' );

		// Get all current stats.
		$stats = $wpdb->get_results(
			"SELECT
				plugin_id,
				version,
				origin_url,
				downloads,
				views,
				installations_current_epoch,
				installations_previous_epoch
			FROM {$wpdb->prefix}troy_plugin_stats_versions",
		);

		foreach ( $stats as $row ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$wpdb->prefix}troy_plugin_stats_versions_daily_snapshots
					WHERE plugin_id = %d AND version = %s AND date = %s AND origin_url = %s",
					$row->plugin_id,
					$row->version,
					$today,
					$row->origin_url,
				),
			);

			if ( $existing ) {
				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_stats_versions_daily_snapshots",
					[
						'downloads'                    => $row->downloads,
						'views'                        => $row->views,
						'installations_current_epoch'  => $row->installations_current_epoch,
						'installations_previous_epoch' => $row->installations_previous_epoch,
					],
					[ 'id' => $existing ],
					[ '%d', '%d', '%d', '%d' ],
					[ '%d' ],
				);
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugin_stats_versions_daily_snapshots",
					[
						'plugin_id'                    => $row->plugin_id,
						'version'                      => $row->version,
						'date'                         => $today,
						'origin_url'                   => $row->origin_url,
						'downloads'                    => $row->downloads,
						'views'                        => $row->views,
						'installations_current_epoch'  => $row->installations_current_epoch,
						'installations_previous_epoch' => $row->installations_previous_epoch,
					],
					[ '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ],
				);
			}
		}

		// Get all current stats_totals.
		$stats_totals = $wpdb->get_results(
			"SELECT
				plugin_id,
				downloads,
				views,
				installations_current_epoch,
				installations_previous_epoch
			FROM {$wpdb->prefix}troy_plugin_stats_totals",
		);

		foreach ( $stats_totals as $row ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$wpdb->prefix}troy_plugin_stats_totals_daily_snapshots
					WHERE plugin_id = %d AND date = %s",
					$row->plugin_id,
					$today,
				),
			);

			if ( $existing ) {
				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_stats_totals_daily_snapshots",
					[
						'downloads'                    => $row->downloads,
						'views'                        => $row->views,
						'installations_current_epoch'  => $row->installations_current_epoch,
						'installations_previous_epoch' => $row->installations_previous_epoch,
					],
					[ 'id' => $existing ],
					[ '%d', '%d', '%d', '%d' ],
					[ '%d' ],
				);
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugin_stats_totals_daily_snapshots",
					[
						'plugin_id'                    => $row->plugin_id,
						'date'                         => $today,
						'downloads'                    => $row->downloads,
						'views'                        => $row->views,
						'installations_current_epoch'  => $row->installations_current_epoch,
						'installations_previous_epoch' => $row->installations_previous_epoch,
					],
					[ '%d', '%s', '%d', '%d', '%d', '%d' ],
				);
			}
		}
	}

	/**
	 * Finalizes old epochs by deleting live data.
	 *
	 * Deletes data from epochs older than the current one, but only after
	 * EPOCH_FINALIZE_DELAY (48 hours) has passed since the current epoch started.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function finalize_old_epochs() {

		global $wpdb;

		$current_epoch       = API\Utils::get_epoch();
		$current_epoch_start = $current_epoch * 604_800;

		// Only finalize if we're at least 48 hours into the current epoch.
		if ( time() < $current_epoch_start + self::EPOCH_FINALIZE_DELAY )
			return;

		// Delete all live data from previous epochs.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}troy_plugin_stats_requests_live
				WHERE epoch < %d",
				$current_epoch,
			),
		);
	}
}
