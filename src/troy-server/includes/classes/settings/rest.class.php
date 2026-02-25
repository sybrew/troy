<?php
/**
 * @package Troy\Server\Settings
 * @access  private
 */

namespace Troy\Server\Settings;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\REST_NS;

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
 * Class Troy\Server\Settings\REST.
 *
 * Handles all settings-related REST API endpoints.
 *
 * @since 0.0.1184
 */
final class REST {

	/**
	 * Register REST routes for settings.
	 *
	 * @hook rest_api_init 10
	 * @since 0.0.1184
	 */
	public static function register_rest_routes() {

		self::register_stats_routes();
		self::register_logs_routes();
	}

	/**
	 * Register stats-related REST routes.
	 *
	 * @since 0.0.1184
	 * @since 1.5.1184 Added package-overview route.
	 */
	private static function register_stats_routes() {

		$namespace = REST_NS['stats_dashboard']['namespace'];
		$base      = REST_NS['stats_dashboard']['base'];

		$permission_cb = fn() => \current_user_can( Main::REQUIRED_CAPABILITY );

		$class = self::class;

		foreach (
			[
				'overview'            => [ \WP_REST_Server::READABLE, 'get_overview' ],
				'package-overview'    => [ \WP_REST_Server::READABLE, 'get_package_overview' ],
				'top-plugins'         => [ \WP_REST_Server::READABLE, 'get_top_plugins' ],
				'packages'            => [ \WP_REST_Server::READABLE, 'get_packages' ],
				'epoch-comparison'    => [ \WP_REST_Server::READABLE, 'get_epoch_comparison' ],
				'php-versions'        => [ \WP_REST_Server::READABLE, 'get_php_versions' ],
				'wp-versions'         => [ \WP_REST_Server::READABLE, 'get_wp_versions' ],
				'locales'             => [ \WP_REST_Server::READABLE, 'get_locales' ],
				'plugin/(?P<id>\d+)'  => [ \WP_REST_Server::READABLE, 'get_plugin_details' ],
				'package/(?P<id>\d+)' => [ \WP_REST_Server::READABLE, 'get_package_details' ],
			]
			as $route => [ $methods, $cb ]
		) {
			\register_rest_route(
				$namespace,
				"$base/$route",
				[
					'methods'             => $methods,
					'callback'            => [ $class, $cb ],
					'permission_callback' => $permission_cb,
				],
			);
		}
	}

	/**
	 * Register logs-related REST routes.
	 *
	 * @since 0.0.1184
	 */
	private static function register_logs_routes() {

		$namespace = REST_NS['logs_dashboard']['namespace'];
		$base      = REST_NS['logs_dashboard']['base'];

		$permission_cb = fn() => \current_user_can( Main::REQUIRED_CAPABILITY );

		$class = self::class;

		foreach (
			[
				'integrations-history'      => [ \WP_REST_Server::READABLE, 'get_integration_history' ],
				'integrations'              => [ \WP_REST_Server::READABLE, 'get_integration_logs' ],
				'clear/integration-history' => [ \WP_REST_Server::CREATABLE, 'clear_integration_history' ],
				'clear/integration-logs'    => [ \WP_REST_Server::CREATABLE, 'clear_integration_logs' ],
			]
			as $route => [ $methods, $cb ]
		) {
			\register_rest_route(
				$namespace,
				"$base/$route",
				[
					'methods'             => $methods,
					'callback'            => [ $class, $cb ],
					'permission_callback' => $permission_cb,
				],
			);
		}
	}

	/**
	 * Gets the overall stats overview.
	 *
	 * @rest troy-server/v1/stats/overview GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_overview( $request ) {

		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		if ( $start_date )
			$start_date = API\Sanitize::sql_date( $start_date );

		if ( $end_date )
			$end_date = API\Sanitize::sql_date( $end_date );

		return new \WP_REST_Response(
			Stats::get_overview( $start_date, $end_date ),
			200,
		);
	}

	/**
	 * Gets top plugins by downloads.
	 *
	 * @rest troy-server/v1/stats/top-plugins GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_top_plugins( $request ) {

		$limit = min( (int) ( $request->get_param( 'limit' ) ?: 10 ), 50 );

		return new \WP_REST_Response(
			Stats::get_top_plugins( $limit ),
			200,
		);
	}

	/**
	 * Gets package download summary.
	 *
	 * @rest troy-server/v1/stats/packages GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_packages( $request ) {

		$limit = min( (int) ( $request->get_param( 'limit' ) ?: 10 ), 50 );

		return new \WP_REST_Response(
			Stats::get_packages_summary( $limit ),
			200,
		);
	}

	/**
	 * Gets package overview statistics.
	 *
	 * @rest troy-server/v1/stats/package-overview GET
	 * @since 1.5.1184
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_package_overview() {
		return new \WP_REST_Response(
			Stats::get_package_overview(),
			200,
		);
	}

	/**
	 * Gets epoch comparison statistics.
	 *
	 * @rest troy-server/v1/stats/epoch-comparison GET
	 * @since 0.0.1184
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_epoch_comparison() {
		return new \WP_REST_Response(
			Stats::get_epoch_comparison(),
			200,
		);
	}

	/**
	 * Gets global PHP version usage statistics.
	 *
	 * @rest troy-server/v1/stats/php-versions GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_php_versions( $request ) {

		$limit = min( (int) ( $request->get_param( 'limit' ) ?: 20 ), 50 );

		return new \WP_REST_Response(
			Stats::get_php_version_stats( $limit ),
			200,
		);
	}

	/**
	 * Gets global WordPress version usage statistics.
	 *
	 * @rest troy-server/v1/stats/wp-versions GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_wp_versions( $request ) {

		$limit = min( (int) ( $request->get_param( 'limit' ) ?: 20 ), 50 );

		return new \WP_REST_Response(
			Stats::get_wp_version_stats( $limit ),
			200,
		);
	}

	/**
	 * Gets global locale usage statistics.
	 *
	 * @rest troy-server/v1/stats/locales GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_locales( $request ) {

		$limit = min( (int) ( $request->get_param( 'limit' ) ?: 20 ), 50 );

		return new \WP_REST_Response(
			Stats::get_locale_stats( $limit ),
			200,
		);
	}

	/**
	 * Gets detailed stats for a single plugin.
	 *
	 * @rest troy-server/v1/stats/plugin/(?P<id>\d+) GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_plugin_details( $request ) {

		$plugin_id = (int) $request->get_param( 'id' );
		$details   = Stats::get_plugin_details( $plugin_id );

		if ( ! $details )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Plugin not found.', 'troy-server' ) ],
				404,
			);

		return new \WP_REST_Response( $details, 200 );
	}

	/**
	 * Gets detailed stats for a single package.
	 *
	 * @rest troy-server/v1/stats/package/(?P<id>\d+) GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_package_details( $request ) {

		$package_id = (int) $request->get_param( 'id' );
		$details    = Stats::get_package_details( $package_id );

		if ( ! $details )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Package not found.', 'troy-server' ) ],
				404,
			);

		return new \WP_REST_Response( $details, 200 );
	}

	/**
	 * Gets integration history.
	 *
	 * @rest troy-server/v1/logs/integrations-history GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_integration_history( $request ) {

		$limit = min( (int) ( $request->get_param( 'limit' ) ?: 100 ), 500 );

		return new \WP_REST_Response(
			Logs::get_integration_history( $limit ),
			200,
		);
	}

	/**
	 * Gets integration logs.
	 *
	 * @rest troy-server/v1/logs/integrations GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_integration_logs( $request ) {

		$limit = min( (int) ( $request->get_param( 'limit' ) ?: 100 ), 500 );

		return new \WP_REST_Response(
			Logs::get_integration_logs( $limit ),
			200,
		);
	}

	/**
	 * Clears integration history.
	 *
	 * @rest troy-server/v1/logs/clear/integration-history POST
	 * @since 0.0.1184
	 *
	 * @return \WP_REST_Response
	 */
	public static function clear_integration_history() {

		Logs::clear_integration_history();

		return new \WP_REST_Response(
			[ 'success' => true ],
			200,
		);
	}

	/**
	 * Clears integration logs.
	 *
	 * @rest troy-server/v1/logs/clear/integration-logs POST
	 * @since 0.0.1184
	 *
	 * @return \WP_REST_Response
	 */
	public static function clear_integration_logs() {

		Logs::clear_integration_logs();

		return new \WP_REST_Response(
			[ 'success' => true ],
			200,
		);
	}
}
