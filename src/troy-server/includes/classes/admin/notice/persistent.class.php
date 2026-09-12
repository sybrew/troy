<?php
/**
 * @package Troy\Server\Admin\Notice
 * @access  private
 */

namespace Troy\Server\Admin\Notice;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	MAIN_FILE,
	REST_NS,
	VERSION,
};

use Troy\Server\{
	Settings,
	Template,
};

/**
 * Troy Server
 *
 * Copyright (c) 2026 Sybre Waaijer, CyberWire B.V.
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
 * Holds persistent admin notices.
 *
 * Notices respawn on page load until dismissed, counted out, or expired.
 *
 * @source Modeled after The SEO Framework by Sybre Waaijer, CyberWire B.V.
 * @since 1.8.1184
 */
final class Persistent {

	/**
	 * Registers a dismissible persistent notice.
	 *
	 * @since 1.8.1184
	 *
	 * @param string $message    The notice message. Expected to be escaped if $args['escape'] is false.
	 *                           When the message contains HTML, it must start with a <p> tag,
	 *                           or it will be added for you.
	 * @param string $key        The notice key. Must be unique. Prevents double-registering and allows clearing.
	 * @param array  $args       {
	 *     The notice creation arguments.
	 *
	 *     @type string $type   Optional. Accepts 'success', 'updated', 'warning', 'info', and 'error'.
	 *                          Default 'success'.
	 *     @type bool   $escape Optional. Whether to escape the $message. Default true.
	 * }
	 * @param array  $conditions {
	 *     The notice output conditions.
	 *
	 *     @type string $capability   Required. The user capability required for the notice to display.
	 *                                Defaults to 'manage_options'.
	 *     @type array  $screens      Optional. The screen bases the notice may be displayed on.
	 *                                When left empty, it outputs on any admin page.
	 *     @type array  $excl_screens Optional. The screen bases the notice may not be displayed on.
	 *     @type int    $user         Optional. The user ID to display the notice for. Capability is still required.
	 *     @type int    $count        Optional. Times the notice may appear for everyone allowed to see it.
	 *                                Set to -1 for unlimited. Default 1.
	 *     @type int    $timeout      Optional. Seconds the notice remains valid. Set to -1 to disable. Default -1.
	 *                                When the timeout is below -1, the notice is not registered.
	 * }
	 */
	public static function register_notice( $message, $key, $args = [], $conditions = [] ) {

		if ( ! \is_scalar( $key ) || ! \strlen( $key ) ) return;

		$key = \sanitize_key( $key );

		$args += [
			'type'   => 'success',
			'escape' => true,
		];

		$conditions += [
			'screens'      => [],
			'excl_screens' => [],
			'capability'   => REST_NS['notices']['access_cap'],
			'user'         => 0,
			'count'        => 1,
			'timeout'      => -1,
		];

		if ( ! $conditions['capability'] ) return;

		if ( $conditions['timeout'] < -1 ) return;

		if ( $conditions['timeout'] > -1 )
			$conditions['timeout'] += time();

		$notices         = Settings\Data::get_server_cache( 'persistent_notices' ) ?? [];
		$notices[ $key ] = compact( 'message', 'args', 'conditions' );

		Settings\Data::update_server_cache( 'persistent_notices', $notices );
	}

	/**
	 * Lowers the persistent notice display count.
	 * When the threshold is reached, the notice is deleted.
	 *
	 * @since 1.8.1184
	 *
	 * @param string $key   The notice key.
	 * @param int    $count The number of counts the notice has left.
	 *                      When -1 (permanent notice), nothing happens.
	 */
	public static function count_down_notice( $key, $count ) {

		if ( $count < 0 ) return;

		--$count;

		if ( ! $count ) {
			self::clear_notice( $key );

			return;
		}

		$notices = Settings\Data::get_server_cache( 'persistent_notices' );

		if ( isset( $notices[ $key ]['conditions']['count'] ) ) {
			$notices[ $key ]['conditions']['count'] = $count;
			Settings\Data::update_server_cache( 'persistent_notices', $notices );
		} else {
			self::clear_notice( $key );
		}
	}

	/**
	 * Clears a persistent notice by key.
	 *
	 * @since 1.8.1184
	 *
	 * @param string $key The notice key.
	 * @return bool True on success, false on failure.
	 */
	public static function clear_notice( $key ) {

		$notices = Settings\Data::get_server_cache( 'persistent_notices' ) ?? [];

		unset( $notices[ $key ] );

		return Settings\Data::update_server_cache( 'persistent_notices', $notices );
	}

	/**
	 * Clears all registered persistent notices.
	 *
	 * @since 1.8.1184
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_all_notices() {
		return Settings\Data::update_server_cache( 'persistent_notices', [] );
	}

	/**
	 * Registers the notice dismiss REST route.
	 *
	 * Hooked from hook.php before the database-block return so REST requests
	 * (which are not is_admin) can dismiss while the plugin is blocked.
	 *
	 * @hook rest_api_init 10
	 * @since 1.8.1184
	 */
	public static function register_rest_routes() {
		\register_rest_route(
			REST_NS['notices']['namespace'],
			REST_NS['notices']['base'] . '/(?P<key>[a-z0-9_-]+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ self::class, 'dismiss_notice' ],
				'permission_callback' => fn() => \current_user_can( REST_NS['notices']['access_cap'] ),
			],
		);
	}

	/**
	 * Enqueues the notice dismiss script when persistent notices are stored.
	 *
	 * @hook admin_enqueue_scripts 10
	 * @since 1.8.1184
	 */
	public static function enqueue_dismiss_script() {

		if ( ! Settings\Data::get_server_cache( 'persistent_notices' ) ) return;

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_enqueue_script(
			'troy-server-notice-dismiss',
			"{$dir_url}library/js/admin/notice-dismiss{$min}.js",
			[ 'wp-api-fetch' ],
			VERSION,
			true,
		);

		\wp_localize_script(
			'troy-server-notice-dismiss',
			'troyServerNotices',
			[
				'restBase' => \rest_url(
					REST_NS['notices']['namespace'] . '/' . REST_NS['notices']['base'],
				),
			],
		);
	}

	/**
	 * Dismisses a persistent notice via REST.
	 *
	 * @rest troy-server/v1/notices/{key} DELETE
	 * @since 1.8.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function dismiss_notice( $request ) {

		$key     = \sanitize_key( $request->get_param( 'key' ) );
		$notices = Settings\Data::get_server_cache( 'persistent_notices' ) ?? [];

		if ( empty( $notices[ $key ]['conditions']['capability'] ) )
			return new \WP_Error(
				'troy_server_notice_not_found',
				'Notice not found.',
				[ 'status' => 404 ],
			);

		if ( ! \current_user_can( $notices[ $key ]['conditions']['capability'] ) )
			return new \WP_Error(
				'troy_server_notice_forbidden',
				'Sorry, you are not allowed to dismiss this notice.',
				[ 'status' => 403 ],
			);

		self::clear_notice( $key );

		return new \WP_REST_Response( [ 'dismissed' => true ], 200 );
	}

	/**
	 * Outputs registered persistent notices.
	 *
	 * @hook admin_notices 10
	 * @since 1.8.1184
	 */
	public static function output_notices() {

		$notices     = Settings\Data::get_server_cache( 'persistent_notices' ) ?? [];
		$screen_base = \get_current_screen()->base ?? '';

		foreach ( $notices as $key => $notice ) {
			$cond = $notice['conditions'];

			if (
				   ! \current_user_can( $cond['capability'] )
				|| ( $cond['user'] && (int) \get_current_user_id() !== (int) $cond['user'] )
				|| ( $cond['screens'] && ! \in_array( $screen_base, $cond['screens'], true ) )
				|| ( $cond['excl_screens'] && \in_array( $screen_base, $cond['excl_screens'], true ) )
			) {
				continue;
			}

			if ( -1 !== $cond['timeout'] && $cond['timeout'] < time() ) {
				self::clear_notice( $key );
				continue;
			}

			Template::output_view(
				'notice/persistent',
				$notice['message'],
				$key,
				$notice['args'],
			);

			self::count_down_notice( $key, $cond['count'] );
		}
	}
}
