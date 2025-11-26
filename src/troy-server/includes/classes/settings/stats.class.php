<?php
/**
 * @package Troy\Server\Settings
 * @access  private
 */

namespace Troy\Server\Settings;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	REST_NS,
	VERSION,
	MAIN_FILE,
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
 * Class Troy\Server\Settings\Stats.
 *
 * Provides dashboard statistics data for the Plugin Stats settings tab.
 *
 * @since 0.0.1184
 */
final class Stats {

	/**
	 * Enqueues stats page assets.
	 *
	 * @since 0.0.1184
	 */
	public static function enqueue_assets() {

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_enqueue_style(
			'troy-server-settings-stats-css',
			"{$dir_url}library/css/settings/stats{$min}.css",
			[ 'troy-server-settings-css' ],
			VERSION,
		);

		\wp_enqueue_script(
			'troy-server-settings-stats-js',
			"{$dir_url}library/js/settings/stats{$min}.js",
			[
				'troy-server-settings-js',
				'wp-api-fetch',
				'troy-server-escape',
				'troy-server-sanitize',
			],
			VERSION,
			true,
		);

		\wp_localize_script(
			'troy-server-settings-stats-js',
			'troyServerStats',
			[
				'restBase'     => \rest_url( REST_NS['stats_dashboard']['namespace'] . '/' . REST_NS['stats_dashboard']['base'] ),
				'nonce'        => \wp_create_nonce( 'wp_rest' ),
				'currentEpoch' => API\Utils::get_epoch(),
				'i18n'         => [
					'loading'            => \__( 'Loading...', 'troy-server' ),
					'error'              => \__( 'Failed to load stats.', 'troy-server' ),
					'noData'             => \__( 'No data available.', 'troy-server' ),
					'increase'           => \__( 'increase', 'troy-server' ),
					'decrease'           => \__( 'decrease', 'troy-server' ),
					'details'            => \__( 'Details', 'troy-server' ),
					'activeInstalls'     => \__( 'Active Installs', 'troy-server' ),
					'inactiveInstalls'   => \__( 'Inactive Installs', 'troy-server' ),
					'notReported'        => \__( 'Not reported', 'troy-server' ),
					'downloadsByVersion' => \__( 'Downloads by Version', 'troy-server' ),
					'downloadsByType'    => \__( 'Downloads by Type', 'troy-server' ),
					'topLocales'         => \__( 'Top Locales', 'troy-server' ),
					'phpVersions'        => \__( 'PHP Versions', 'troy-server' ),
					'wpVersions'         => \__( 'WordPress Versions', 'troy-server' ),
					'currentVersion'     => \__( 'Current Version', 'troy-server' ),
					'totalDownloads'     => \__( 'Total Downloads', 'troy-server' ),
				],
			],
		);
	}

	/**
	 * Gets the overall stats overview.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param ?string $start_date Optional. Start date in Y-m-d format.
	 * @param ?string $end_date   Optional. End date in Y-m-d format.
	 * @return array {
	 *     Overview statistics.
	 *
	 *     @type int $total_downloads   Total plugin downloads.
	 *     @type int $total_views       Total plugin views.
	 *     @type int $active_installs   Total active installations.
	 *     @type int $inactive_installs Total inactive installations.
	 *     @type int $current_epoch     Current epoch number.
	 *     @type int $total_plugins     Total number of plugins.
	 *     @type int $total_packages    Total number of packages.
	 * }
	 */
	public static function get_overview( $start_date = null, $end_date = null ) {

		global $wpdb;

		$current_epoch = API\Utils::get_epoch();

		if ( $start_date && $end_date ) {
			// Use date-range query from stats_to_date
			$totals = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					COALESCE(SUM(downloads), 0) as total_downloads,
					COALESCE(SUM(views), 0) as total_views
				 FROM {$wpdb->prefix}troy_plugin_stats_versions_daily
				 WHERE date BETWEEN %s AND %s",
				$start_date,
				$end_date,
			) );
		} else {
			// Use totals table for all-time stats
			$totals = $wpdb->get_row(
				"SELECT
					 COALESCE(SUM(downloads), 0) as total_downloads,
					 COALESCE(SUM(views), 0) as total_views
				 FROM {$wpdb->prefix}troy_plugin_stats_totals",
			);
		}

		// Get install counts from stats_totals.
		// Total installs = max of current or previous epoch (since current epoch may be incomplete).
		// Active installs = current epoch count.
		$installs = $wpdb->get_row(
			"SELECT
				 COALESCE(SUM(installations_current_epoch), 0) as active,
				 COALESCE(SUM(GREATEST(installations_current_epoch, installations_previous_epoch)), 0) as total
			 FROM {$wpdb->prefix}troy_plugin_stats_totals",
		);

		$active_installs   = (int) ( $installs->active ?? 0 );
		$total_installs    = (int) ( $installs->total ?? 0 );
		$inactive_installs = max( 0, $total_installs - $active_installs );

		$total_plugins = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}troy_plugins WHERE status = 'public'",
		);

		$total_packages = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}troy_packages WHERE status = 'active'",
		);

		return [
			'total_downloads'   => (int) ( $totals->total_downloads ?? 0 ),
			'total_views'       => (int) ( $totals->total_views ?? 0 ),
			'active_installs'   => $active_installs,
			'inactive_installs' => $inactive_installs,
			'current_epoch'     => $current_epoch,
			'total_plugins'     => $total_plugins,
			'total_packages'    => $total_packages,
		];
	}

	/**
	 * Gets top plugins by downloads.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Number of plugins to return.
	 * @return array[] {
	 *     Array of top plugins.
	 *
	 *     @type int    $plugin_id         The plugin ID.
	 *     @type string $slug              The plugin slug.
	 *     @type string $name              The plugin name.
	 *     @type int    $downloads         Total downloads.
	 *     @type int    $views             Total views.
	 *     @type int    $active_installs   Active installations.
	 *     @type int    $inactive_installs Inactive installations.
	 * }
	 */
	public static function get_top_plugins( $limit = 10 ) {

		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT
				 p.id as plugin_id,
				 p.slug,
				 pm.name,
				 COALESCE(SUM(s.downloads), 0) as downloads,
				 COALESCE(SUM(s.views), 0) as views,
				 COALESCE(SUM(s.installations_current_epoch), 0) as active_installs,
				 COALESCE(SUM(GREATEST(s.installations_current_epoch, s.installations_previous_epoch)), 0) as total_installs
			 FROM {$wpdb->prefix}troy_plugins p
			 LEFT JOIN {$wpdb->prefix}troy_plugins_metas pm ON p.id = pm.plugin_id
			 LEFT JOIN {$wpdb->prefix}troy_plugin_stats_totals s ON p.id = s.plugin_id
			 WHERE p.status = 'public'
			 GROUP BY p.id, p.slug, pm.name
			 ORDER BY downloads DESC
			 LIMIT %d",
			$limit,
		) );

		if ( ! $results )
			return [];

		return array_map(
			fn( $row ) => [
				'plugin_id'         => (int) $row->plugin_id,
				'slug'              => $row->slug,
				'name'              => $row->name ?: $row->slug,
				'downloads'         => (int) $row->downloads,
				'views'             => (int) $row->views,
				'active_installs'   => (int) $row->active_installs,
				'inactive_installs' => max( 0, (int) $row->total_installs - (int) $row->active_installs ),
			],
			$results,
		);
	}

	/**
	 * Gets package download summary.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Number of packages to return.
	 * @return array[] {
	 *     Array of packages with download stats.
	 *
	 *     @type int    $package_id The package ID.
	 *     @type string $slug       The package slug.
	 *     @type string $name       The package name.
	 *     @type string $version    The current package version.
	 *     @type int    $downloads  Total downloads.
	 * }
	 */
	public static function get_packages_summary( $limit = 10 ) {

		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT
				p.id as package_id,
				p.slug,
				pm.name,
				pm.version,
				COALESCE(SUM(s.downloads), 0) as downloads
			 FROM {$wpdb->prefix}troy_packages p
			 LEFT JOIN {$wpdb->prefix}troy_packages_metas pm ON p.id = pm.package_id
			 LEFT JOIN {$wpdb->prefix}troy_package_stats_downloads s ON p.id = s.package_id
			 WHERE p.status = 'active'
			 GROUP BY p.id, p.slug, pm.name, pm.version
			 ORDER BY downloads DESC
			 LIMIT %d",
			$limit,
		) );

		if ( ! $results )
			return [];

		return array_map(
			fn( $row ) => [
				'package_id' => (int) $row->package_id,
				'slug'       => $row->slug,
				'name'       => $row->name ?: $row->slug,
				'version'    => $row->version,
				'downloads'  => (int) $row->downloads,
			],
			$results,
		);
	}

	/**
	 * Gets epoch comparison statistics.
	 *
	 * Compares current epoch with previous epoch for growth metrics.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return array {
	 *     Epoch comparison data.
	 *
	 *     @type int   $current_epoch           Current epoch number.
	 *     @type int   $previous_epoch          Previous epoch number.
	 *     @type int   $current_requests        Request count in current epoch.
	 *     @type int   $previous_requests       Request count in previous epoch.
	 *     @type float $requests_change_percent Percentage change in requests.
	 *     @type int   $current_installs        Active installs from current epoch.
	 *     @type int   $previous_installs       Active installs from previous epoch.
	 *     @type float $installs_change_percent Percentage change in installs.
	 * }
	 */
	public static function get_epoch_comparison() {

		global $wpdb;

		$current_epoch  = API\Utils::get_epoch();
		$previous_epoch = $current_epoch - 1;

		// Request counts by epoch
		$current_requests = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(request_count), 0)
			 FROM {$wpdb->prefix}troy_plugin_stats_requests
			 WHERE epoch = %d",
			$current_epoch,
		) );

		$previous_requests = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(request_count), 0)
			 FROM {$wpdb->prefix}troy_plugin_stats_requests
			 WHERE epoch = %d",
			$previous_epoch,
		) );

		// Installation counts by epoch
		$current_installs = (int) $wpdb->get_var(
			"SELECT COALESCE(SUM(installations_current_epoch), 0)
			 FROM {$wpdb->prefix}troy_plugin_stats_totals",
		);

		$previous_installs = (int) $wpdb->get_var(
			"SELECT COALESCE(SUM(installations_previous_epoch), 0)
			 FROM {$wpdb->prefix}troy_plugin_stats_totals",
		);

		return [
			'current_epoch'           => $current_epoch,
			'previous_epoch'          => $previous_epoch,
			'current_requests'        => $current_requests,
			'previous_requests'       => $previous_requests,
			'requests_change_percent' => $previous_requests
				? round( ( ( $current_requests - $previous_requests ) / $previous_requests ) * 100, 1 )
				: ( $current_requests ? INF : 0.0 ),
			'current_installs'        => $current_installs,
			'previous_installs'       => $previous_installs,
			'installs_change_percent' => $previous_installs
				? round( ( ( $current_installs - $previous_installs ) / $previous_installs ) * 100, 1 )
				: ( $current_installs ? INF : 0.0 ),
		];
	}

	/**
	 * Gets detailed stats for a single plugin.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $plugin_id The plugin ID.
	 * @return ?array Plugin details or null if not found.
	 */
	public static function get_plugin_details( $plugin_id ) {

		global $wpdb;

		$plugin = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.*, pm.name
			 FROM {$wpdb->prefix}troy_plugins p
			 LEFT JOIN {$wpdb->prefix}troy_plugins_metas pm ON p.id = pm.plugin_id
			 WHERE p.id = %d",
			$plugin_id,
		) );

		if ( ! $plugin )
			return null;

		$data = new \Troy\Server\Plugins\Data( $plugin_id );

		// Version breakdown
		$versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT version, SUM(downloads) as downloads
				 FROM {$wpdb->prefix}troy_plugin_stats_downloads
				 WHERE plugin_id = %d
				 GROUP BY version
				 ORDER BY downloads DESC",
				$plugin_id,
			),
			\ARRAY_A,
		);

		// Download types breakdown
		$download_types = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT type, SUM(downloads) as downloads
				 FROM {$wpdb->prefix}troy_plugin_stats_downloads
				 WHERE plugin_id = %d
				 GROUP BY type",
				$plugin_id,
			),
			\ARRAY_A,
		);

		// Locale breakdown from update requests
		$locales = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT locale, SUM(install_count) as count
				 FROM {$wpdb->prefix}troy_plugin_stats_locales
				 WHERE plugin_id = %d
				 GROUP BY locale
				 ORDER BY count DESC
				 LIMIT 10",
				$plugin_id,
			),
			\ARRAY_A,
		);

		$cache = $data->get_data_caches_row();

		// Get install counts from stats_totals.
		// Total installs = max of current or previous epoch (since current epoch may be incomplete).
		// Active installs = current epoch count.
		$installs = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				 COALESCE(SUM(installations_current_epoch), 0) as active,
				 COALESCE(SUM(GREATEST(installations_current_epoch, installations_previous_epoch)), 0) as total
			 FROM {$wpdb->prefix}troy_plugin_stats_totals
			 WHERE plugin_id = %d",
			$plugin_id,
		) );

		$active_installs   = (int) ( $installs->active ?? 0 );
		$total_installs    = (int) ( $installs->total ?? 0 );
		$inactive_installs = max( 0, $total_installs - $active_installs );

		// PHP version breakdown from update requests
		$php_versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT php_version as version, SUM(install_count) as count
				 FROM {$wpdb->prefix}troy_plugin_stats_php
				 WHERE plugin_id = %d
				 GROUP BY php_version
				 ORDER BY count DESC
				 LIMIT 10",
				$plugin_id,
			),
			\ARRAY_A,
		);

		// WP version breakdown from update requests
		$wp_versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT wp_version as version, SUM(install_count) as count
				 FROM {$wpdb->prefix}troy_plugin_stats_wp
				 WHERE plugin_id = %d
				 GROUP BY wp_version
				 ORDER BY count DESC
				 LIMIT 10",
				$plugin_id,
			),
			\ARRAY_A,
		);

		return [
			'plugin_id'         => $plugin_id,
			'slug'              => $plugin->slug,
			'name'              => $plugin->name ?: $plugin->slug,
			'status'            => $plugin->status,
			'active_installs'   => $active_installs,
			'inactive_installs' => $inactive_installs,
			// 'average_rating'  => (float) ( $cache->average_rating ?? 0 ),
			// 'rating_count'    => (int) ( $cache->rating_count ?? 0 ),
			'versions'          => $versions,
			'download_types'    => $download_types,
			'locales'           => $locales,
			'php_versions'      => $php_versions,
			'wp_versions'       => $wp_versions,
		];
	}

	/**
	 * Gets detailed stats for a single package.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $package_id The package ID.
	 * @return ?array Package details or null if not found.
	 */
	public static function get_package_details( $package_id ) {

		global $wpdb;

		$package = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.*, pm.name, pm.version
			 FROM {$wpdb->prefix}troy_packages p
			 LEFT JOIN {$wpdb->prefix}troy_packages_metas pm ON p.id = pm.package_id
			 WHERE p.id = %d",
			$package_id,
		) );

		if ( ! $package )
			return null;

		// Version breakdown
		$versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT version, SUM(downloads) as downloads
				 FROM {$wpdb->prefix}troy_package_stats_downloads
				 WHERE package_id = %d
				 GROUP BY version
				 ORDER BY downloads DESC",
				$package_id,
			),
			\ARRAY_A,
		);

		$total_downloads = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(downloads), 0)
			 FROM {$wpdb->prefix}troy_package_stats_downloads
			 WHERE package_id = %d",
			$package_id,
		) );

		return [
			'package_id'      => $package_id,
			'slug'            => $package->slug,
			'name'            => $package->name ?: $package->slug,
			'status'          => $package->status,
			'current_version' => $package->version,
			'total_downloads' => $total_downloads,
			'versions'        => $versions,
		];
	}

	/**
	 * Gets global PHP version usage statistics.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Number of versions to return.
	 * @return array[] {
	 *     Array of PHP versions with usage counts.
	 *
	 *     @type string $version The PHP version.
	 *     @type int    $count   Number of installations using this version.
	 * }
	 */
	public static function get_php_version_stats( $limit = 20 ) {

		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT php_version as version, SUM(install_count) as count
			 FROM {$wpdb->prefix}troy_plugin_stats_php
			 GROUP BY php_version
			 ORDER BY count DESC
			 LIMIT %d",
			$limit,
		) );

		if ( ! $results )
			return [];

		return array_map(
			fn( $row ) => [
				'version' => $row->version,
				'count'   => (int) $row->count,
			],
			$results,
		);
	}

	/**
	 * Gets global WordPress version usage statistics.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Number of versions to return.
	 * @return array[] {
	 *     Array of WP versions with usage counts.
	 *
	 *     @type string $version The WordPress version.
	 *     @type int    $count   Number of installations using this version.
	 * }
	 */
	public static function get_wp_version_stats( $limit = 20 ) {

		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT wp_version as version, SUM(install_count) as count
			 FROM {$wpdb->prefix}troy_plugin_stats_wp
			 GROUP BY wp_version
			 ORDER BY count DESC
			 LIMIT %d",
			$limit,
		) );

		if ( ! $results )
			return [];

		return array_map(
			fn( $row ) => [
				'version' => $row->version,
				'count'   => (int) $row->count,
			],
			$results,
		);
	}

	/**
	 * Gets global locale usage statistics.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Number of locales to return.
	 * @return array[] {
	 *     Array of locales with usage counts.
	 *
	 *     @type string $locale The locale code.
	 *     @type int    $count  Number of installations using this locale.
	 * }
	 */
	public static function get_locale_stats( $limit = 20 ) {

		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT locale, SUM(install_count) as count
			 FROM {$wpdb->prefix}troy_plugin_stats_locales
			 GROUP BY locale
			 ORDER BY count DESC
			 LIMIT %d",
			$limit,
		) );

		if ( ! $results )
			return [];

		return array_map(
			fn( $row ) => [
				'locale' => $row->locale,
				'count'  => (int) $row->count,
			],
			$results,
		);
	}
}
