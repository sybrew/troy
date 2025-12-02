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
				'restBase'  => \rest_url( REST_NS['stats_dashboard']['namespace'] . '/' . REST_NS['stats_dashboard']['base'] ),
				'nonce'     => \wp_create_nonce( 'wp_rest' ),
				'thisEpoch' => API\Utils::get_epoch(),
				'i18n'      => [
					'loading'            => \__( 'Loading...', 'troy-server' ),
					'error'              => \__( 'Failed to load stats.', 'troy-server' ),
					'noData'             => \__( 'No data available.', 'troy-server' ),
					'increase'           => \__( 'increase', 'troy-server' ),
					'decrease'           => \__( 'decrease', 'troy-server' ),
					'details'            => \__( 'Details', 'troy-server' ),
					'installations'      => \__( 'Installations', 'troy-server' ),
					'activeInstalls'     => \__( 'Active Installations', 'troy-server' ),
					'inactiveInstalls'   => \__( 'Inactive Installations', 'troy-server' ),
					'notReported'        => \__( 'Not reported', 'troy-server' ),
					'downloadsByVersion' => \__( 'Downloads by Version', 'troy-server' ),
					'downloadsByType'    => \__( 'Downloads by Type', 'troy-server' ),
					'locales'            => \__( 'Locales', 'troy-server' ),
					'phpVersions'        => \__( 'PHP Versions', 'troy-server' ),
					'wpVersions'         => \__( 'WordPress Versions', 'troy-server' ),
					'currentVersion'     => \__( 'Current Version', 'troy-server' ),
					'totalDownloads'     => \__( 'Total Downloads', 'troy-server' ),
					'epochHint'          => \__( 'Publicly shown value uses the highest count between this and last epoch.', 'troy-server' ),
					'epochComparison'    => \__( 'Epoch Comparison', 'troy-server' ),
					'installsByVersion'  => \__( 'Installations by Version', 'troy-server' ),
					'detailsPerVersion'  => \__( 'Details per Version', 'troy-server' ),
					'total'              => \__( 'Total', 'troy-server' ),
					'metric'             => \__( 'Metric', 'troy-server' ),
					'version'            => \__( 'Version', 'troy-server' ),
					// translators: %d is the epoch number
					'lastEpochHeader'    => \__( 'Last Epoch (%d)', 'troy-server' ),
					// translators: %d is the epoch number
					'thisEpochHeader'    => \__( 'This Epoch (%d)', 'troy-server' ),
					'totalInstallations' => \__( 'Total Installations', 'troy-server' ),
					'updateRequests'     => \__( 'Update Requests', 'troy-server' ),
					'change'             => \__( 'Change', 'troy-server' ),
					'lastSnapshot'       => \__( 'Last Snapshot', 'troy-server' ),
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
	 *     @type int    $total_downloads   Total plugin downloads.
	 *     @type int    $total_views       Total plugin views.
	 *     @type int    $total_installs    Total installations (highest of this/last epoch).
	 *     @type int    $active_installs   Active installations of this epoch.
	 *     @type int    $inactive_installs Inactive installations of this epoch.
	 *     @type int    $this_epoch        This epoch number.
	 *     @type int    $last_epoch        The last epoch number.
	 *     @type int    $total_plugins     Total number of plugins.
	 *     @type int    $total_packages    Total number of packages.
	 *     @type string $last_snapshot     Last snapshot timestamp.
	 *     @type array  $epoch_installs    Per-epoch active/inactive breakdown.
	 * }
	 */
	public static function get_overview( $start_date = null, $end_date = null ) {

		global $wpdb;

		$this_epoch = API\Utils::get_epoch();

		if ( $start_date && $end_date ) {
			// stats_versions_daily_snapshots stores daily deltas; SUM gives period total.
			$totals = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					COALESCE(SUM(downloads), 0) as total_downloads,
					COALESCE(SUM(views), 0) as total_views
				FROM {$wpdb->prefix}troy_plugin_stats_versions_daily_snapshots
				WHERE date BETWEEN %s AND %s",
				$start_date,
				$end_date,
			) );
		} else {
			$totals = $wpdb->get_row(
				"SELECT
					COALESCE(SUM(downloads), 0) as total_downloads,
					COALESCE(SUM(views), 0) as total_views
				FROM {$wpdb->prefix}troy_plugin_stats_totals",
			);
		}

		$last_epoch = $this_epoch - 1;

		// Total installs = highest of this or last epoch (since this epoch may be incomplete).
		// This is the number shown publicly via plugin info APIs.
		$total_installs = (int) $wpdb->get_var(
			"SELECT COALESCE(
				SUM(GREATEST(total_installs_this_epoch, total_installs_last_epoch)),
				0
			)
			FROM {$wpdb->prefix}troy_plugin_stats_totals",
		);

		// Active/inactive breakdown from stats_totals.
		$epoch_stats = $wpdb->get_row(
			"SELECT
				COALESCE(SUM(active_installs_this_epoch), 0) as this_active,
				COALESCE(SUM(total_installs_this_epoch - active_installs_this_epoch), 0) as this_inactive,
				COALESCE(SUM(active_installs_last_epoch), 0) as last_active,
				COALESCE(SUM(total_installs_last_epoch - active_installs_last_epoch), 0) as last_inactive
			FROM {$wpdb->prefix}troy_plugin_stats_totals",
		);

		$this_active   = (int) ( $epoch_stats->this_active ?? 0 );
		$this_inactive = (int) ( $epoch_stats->this_inactive ?? 0 );
		$last_active   = (int) ( $epoch_stats->last_active ?? 0 );
		$last_inactive = (int) ( $epoch_stats->last_inactive ?? 0 );

		$total_plugins = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}troy_plugins WHERE status IN ('public', 'unlisted', 'protected')",
		);

		$total_packages = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}troy_packages WHERE status = 'active'",
		);

		$last_snapshot = $wpdb->get_var(
			"SELECT MAX(updated_at)
			FROM {$wpdb->prefix}troy_plugin_stats_totals_daily_snapshots",
		);

		return [
			'total_downloads'   => (int) ( $totals->total_downloads ?? 0 ),
			'total_views'       => (int) ( $totals->total_views ?? 0 ),
			'total_installs'    => $total_installs,
			'active_installs'   => $this_active,
			'inactive_installs' => $this_inactive,
			'this_epoch'        => $this_epoch,
			'last_epoch'        => $last_epoch,
			'total_plugins'     => $total_plugins,
			'total_packages'    => $total_packages,
			'last_snapshot'     => $last_snapshot,
			'epoch_installs'    => [
				$this_epoch => [
					'active'   => $this_active,
					'inactive' => $this_inactive,
				],
				$last_epoch => [
					'active'   => $last_active,
					'inactive' => $last_inactive,
				],
			],
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
	 *     @type int    $total_installs    Total installations (highest of this/last epoch).
	 *     @type int    $active_installs   Active installations of this epoch.
	 *     @type int    $inactive_installs Inactive installations of this epoch.
	 * }
	 */
	public static function get_top_plugins( $limit = 10 ) {

		global $wpdb;

		$this_epoch = API\Utils::get_epoch();

		// Get basic plugin data with totals.
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT
				p.id as plugin_id,
				p.slug,
				pm.name,
				COALESCE(SUM(s.downloads), 0) as downloads,
				COALESCE(SUM(s.views), 0) as views,
				COALESCE(
					SUM(GREATEST(s.total_installs_this_epoch, s.total_installs_last_epoch)),
					0
				) as total_installs,
				COALESCE(SUM(s.active_installs_this_epoch), 0) as active_installs,
				COALESCE(
					SUM(s.total_installs_this_epoch - s.active_installs_this_epoch),
					0
				) as inactive_installs
			FROM {$wpdb->prefix}troy_plugins p
			LEFT JOIN {$wpdb->prefix}troy_plugin_metas pm
				ON p.id = pm.plugin_id
			LEFT JOIN {$wpdb->prefix}troy_plugin_stats_totals s
				ON p.id = s.plugin_id
			WHERE p.status IN ('public', 'unlisted', 'protected')
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
				'total_installs'    => (int) $row->total_installs,
				'active_installs'   => (int) $row->active_installs,
				'inactive_installs' => (int) $row->inactive_installs,
			],
			$results,
		);
	}

	/**
	 * Gets the package stats overview.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return array {
	 *     Overview statistics.
	 *
	 *     @type int    $total_packages  Total number of active packages.
	 *     @type int    $total_downloads Total package downloads.
	 *     @type string $last_snapshot   Last snapshot timestamp.
	 * }
	 */
	public static function get_package_overview() {

		global $wpdb;

		$total_packages = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}troy_packages WHERE status = 'active'",
		);

		$total_downloads = (int) $wpdb->get_var(
			"SELECT COALESCE(SUM(downloads), 0) FROM {$wpdb->prefix}troy_package_stats_downloads",
		);

		$last_snapshot = $wpdb->get_var(
			"SELECT MAX(updated_at) FROM {$wpdb->prefix}troy_package_stats_downloads",
		);

		return [
			'total_packages'  => $total_packages,
			'total_downloads' => $total_downloads,
			'last_snapshot'   => $last_snapshot,
		];
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
			LEFT JOIN {$wpdb->prefix}troy_package_metas pm
				ON p.id = pm.package_id
			LEFT JOIN {$wpdb->prefix}troy_package_stats_downloads s
				ON p.id = s.package_id
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
	 * Compares this epoch with the last epoch for growth metrics.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return array {
	 *     Epoch comparison data.
	 *
	 *     @type int   $this_epoch               This epoch number.
	 *     @type int   $last_epoch               The last epoch number.
	 *     @type int   $this_requests            Request count of this epoch.
	 *     @type int   $last_requests            Request count of the last epoch.
	 *     @type float $requests_change_percent  Percentage change in requests.
	 *     @type int   $this_active_installs     Active installs of this epoch.
	 *     @type int   $this_inactive_installs   Inactive installs of this epoch.
	 *     @type int   $last_active_installs     Active installs of the last epoch.
	 *     @type int   $last_inactive_installs   Inactive installs of the last epoch.
	 *     @type float $active_change_percent    Percentage change in active installs.
	 *     @type float $inactive_change_percent  Percentage change in inactive installs.
	 * }
	 */
	public static function get_epoch_comparison() {

		global $wpdb;

		$this_epoch = API\Utils::get_epoch();
		$last_epoch = $this_epoch - 1;

		// Request counts by epoch (independent of is_active).
		$this_requests = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(request_count), 0)
			FROM {$wpdb->prefix}troy_plugin_stats_requests
			WHERE epoch = %d",
			$this_epoch,
		) );

		$last_requests = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(request_count), 0)
			FROM {$wpdb->prefix}troy_plugin_stats_requests
			WHERE epoch = %d",
			$last_epoch,
		) );

		// Active/inactive breakdown from stats_totals.
		$epoch_stats = $wpdb->get_row(
			"SELECT
				COALESCE(SUM(active_installs_this_epoch), 0) as this_active,
				COALESCE(SUM(total_installs_this_epoch - active_installs_this_epoch), 0) as this_inactive,
				COALESCE(SUM(active_installs_last_epoch), 0) as last_active,
				COALESCE(SUM(total_installs_last_epoch - active_installs_last_epoch), 0) as last_inactive
			FROM {$wpdb->prefix}troy_plugin_stats_totals",
		);

		$this_active   = (int) ( $epoch_stats->this_active ?? 0 );
		$this_inactive = (int) ( $epoch_stats->this_inactive ?? 0 );
		$last_active   = (int) ( $epoch_stats->last_active ?? 0 );
		$last_inactive = (int) ( $epoch_stats->last_inactive ?? 0 );

		return [
			'this_epoch'              => $this_epoch,
			'last_epoch'              => $last_epoch,
			'this_requests'           => $this_requests,
			'last_requests'           => $last_requests,
			'requests_change_percent' => $last_requests
				? round( ( ( $this_requests - $last_requests ) / $last_requests ) * 100, 1 )
				: ( $this_requests ? \INF : 0.0 ),
			'this_active_installs'    => $this_active,
			'this_inactive_installs'  => $this_inactive,
			'last_active_installs'    => $last_active,
			'last_inactive_installs'  => $last_inactive,
			'active_change_percent'   => $last_active
				? round( ( ( $this_active - $last_active ) / $last_active ) * 100, 1 )
				: ( $this_active ? \INF : 0.0 ),
			'inactive_change_percent' => $last_inactive
				? round( ( ( $this_inactive - $last_inactive ) / $last_inactive ) * 100, 1 )
				: ( $this_inactive ? \INF : 0.0 ),
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
			"SELECT
				p.*,
				pm.name
			FROM {$wpdb->prefix}troy_plugins p
			LEFT JOIN {$wpdb->prefix}troy_plugin_metas pm
				ON p.id = pm.plugin_id
			WHERE p.id = %d",
			$plugin_id,
		) );

		if ( ! $plugin )
			return null;

		$data = new \Troy\Server\Plugins\Data( $plugin_id );

		// Download types breakdown
		$download_types = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					type,
					SUM(downloads) as downloads
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
				"SELECT
					locale,
					SUM(install_count) as count
				FROM {$wpdb->prefix}troy_plugin_stats_locales
				WHERE plugin_id = %d
				GROUP BY locale
				ORDER BY count DESC
				LIMIT 10",
				$plugin_id,
			),
			\ARRAY_A,
		);

		$this_epoch = API\Utils::get_epoch();
		$last_epoch = $this_epoch - 1;

		// Request counts by epoch for this plugin.
		$request_stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COALESCE(SUM(CASE WHEN epoch = %d THEN request_count ELSE 0 END), 0) as this_requests,
				COALESCE(SUM(CASE WHEN epoch = %d THEN request_count ELSE 0 END), 0) as last_requests
			FROM {$wpdb->prefix}troy_plugin_stats_requests
			WHERE plugin_id = %d
				AND epoch IN (%d, %d)",
			$this_epoch,
			$last_epoch,
			$plugin_id,
			$this_epoch,
			$last_epoch,
		) );

		$this_requests = (int) ( $request_stats->this_requests ?? 0 );
		$last_requests = (int) ( $request_stats->last_requests ?? 0 );

		// Get installation stats from stats_totals.
		$install_stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				GREATEST(total_installs_this_epoch, total_installs_last_epoch) as total_installs,
				active_installs_this_epoch as this_active,
				total_installs_this_epoch - active_installs_this_epoch as this_inactive,
				active_installs_last_epoch as last_active,
				total_installs_last_epoch - active_installs_last_epoch as last_inactive
			FROM {$wpdb->prefix}troy_plugin_stats_totals
			WHERE plugin_id = %d",
			$plugin_id,
		) );

		$total_installs = (int) ( $install_stats->total_installs ?? 0 );
		$this_active    = (int) ( $install_stats->this_active ?? 0 );
		$this_inactive  = (int) ( $install_stats->this_inactive ?? 0 );
		$last_active    = (int) ( $install_stats->last_active ?? 0 );
		$last_inactive  = (int) ( $install_stats->last_inactive ?? 0 );

		// PHP version breakdown from update requests
		$php_versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					php_version as version,
					SUM(install_count) as count
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
				"SELECT
					wp_version as version,
					SUM(install_count) as count
				FROM {$wpdb->prefix}troy_plugin_stats_wp
				WHERE plugin_id = %d
				GROUP BY wp_version
				ORDER BY count DESC
				LIMIT 10",
				$plugin_id,
			),
			\ARRAY_A,
		);

		$total_downloads = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(downloads), 0)
			FROM {$wpdb->prefix}troy_plugin_stats_totals
			WHERE plugin_id = %d",
			$plugin_id,
		) );

		$last_snapshot = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(updated_at)
			FROM {$wpdb->prefix}troy_plugin_stats_totals_daily_snapshots
			WHERE plugin_id = %d",
			$plugin_id,
		) );

		// Version details from all-time stats
		$version_details = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					version,
					SUM(downloads) as downloads,
					SUM(total_installs) as total_installs,
					SUM(active_installs) as active_installs
				FROM {$wpdb->prefix}troy_plugin_stats_versions
				WHERE plugin_id = %d
				GROUP BY version
				ORDER BY downloads DESC
				LIMIT 7",
				$plugin_id,
			),
			\ARRAY_A,
		);

		return [
			'plugin_id'         => $plugin_id,
			'slug'              => $plugin->slug,
			'name'              => $plugin->name ?: $plugin->slug,
			'status'            => $plugin->status,
			'total_downloads'   => $total_downloads,
			'total_installs'    => $total_installs,
			'active_installs'   => $this_active,
			'inactive_installs' => $this_inactive,
			'this_epoch'        => $this_epoch,
			'last_epoch'        => $last_epoch,
			'epoch_installs'    => [
				$this_epoch => [
					'requests' => $this_requests,
					'active'   => $this_active,
					'inactive' => $this_inactive,
				],
				$last_epoch => [
					'requests' => $last_requests,
					'active'   => $last_active,
					'inactive' => $last_inactive,
				],
			],
			'download_types'    => $download_types,
			'locales'           => $locales,
			'php_versions'      => $php_versions,
			'wp_versions'       => $wp_versions,
			'version_details'   => $version_details,
			'last_snapshot'     => $last_snapshot,
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
			"SELECT
				p.*,
				pm.name,
				pm.version
			FROM {$wpdb->prefix}troy_packages p
			LEFT JOIN {$wpdb->prefix}troy_package_metas pm
				ON p.id = pm.package_id
			WHERE p.id = %d",
			$package_id,
		) );

		if ( ! $package )
			return null;

		// Version breakdown
		$versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					version,
					SUM(downloads) as downloads
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

		$last_snapshot = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(updated_at)
			FROM {$wpdb->prefix}troy_package_stats_downloads
			WHERE package_id = %d",
			$package_id,
		) );

		return [
			'package_id'      => $package_id,
			'slug'            => $package->slug,
			'name'            => $package->name ?: $package->slug,
			'status'          => $package->status,
			'total_downloads' => $total_downloads,
			'last_snapshot'   => $last_snapshot,
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
			"SELECT
				php_version as version,
				SUM(install_count) as count
			FROM {$wpdb->prefix}troy_stats_php
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
			"SELECT
				wp_version as version,
				SUM(install_count) as count
			FROM {$wpdb->prefix}troy_stats_wp
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
			"SELECT
				locale,
				SUM(install_count) as count
			FROM {$wpdb->prefix}troy_stats_locales
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
