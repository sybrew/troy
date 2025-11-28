<?php
/**
 * @package Troy\Server\Settings
 * @access  private
 */

namespace Troy\Server\Settings;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\Template;

use const Troy\Server\{
	MAIN_FILE,
	VERSION,
};

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
 * Class Troy\Server\Settings\Main.
 *
 * @since 0.0.1184
 */
final class Main {

	/**
	 * The settings page slug.
	 *
	 * @since 0.0.1184
	 */
	public const SETTINGS_PAGE_SLUG = 'troy-server-settings';

	/**
	 * The required options capability.
	 *
	 * @since 0.0.1184
	 */
	public const REQUIRED_CAPABILITY = 'manage_options';

	/**
	 * The settings save action.
	 *
	 * @since 0.0.1184
	 */
	public const SAVE_ACTION = 'troy_server_settings_save';

	/**
	 * The settings save action.
	 *
	 * @since 0.0.1184
	 */
	public const SAVED_RESPONSE = 'troy_server_settings_updated';

	/**
	 * The settings save nonce.
	 *
	 * @since 0.0.1184
	 */
	public const SAVE_NONCE = [
		'name'   => '_troy_server_settings_save_nonce',
		'action' => '_troy_server_settings_save',
	];

	/**
	 * Register the Troy Settings admin menu.
	 *
	 * @hook admin_menu 10
	 * @since 0.0.1184
	 */
	public static function register_admin_menu() {

		$name = \__( 'Troy Server', 'troy-server' );

		$page = \add_menu_page(
			$name,
			$name,
			self::REQUIRED_CAPABILITY,
			self::SETTINGS_PAGE_SLUG,
			[ __CLASS__, 'render_admin_menu' ],
			'dashicons-admin-generic',
			3.1184,
		);

		\add_action( "load-$page", [ __CLASS__, 'init_admin_page' ] );
	}

	/**
	 * Render the Troy Settings admin menu.
	 *
	 * @since 0.0.1184
	 */
	public static function render_admin_menu() {

		if ( ! \current_user_can( self::REQUIRED_CAPABILITY ) )
			\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'troy-server' ) );

		Template::output_view( 'settings/main' );
	}

	/**
	 * Initializes the Troy Settings pages.
	 *
	 * @since 0.0.1184
	 * @hook load-{self::SETTINGS_PAGE_SLUG} 10
	 */
	public static function init_admin_page() {

		if ( ! \current_user_can( self::REQUIRED_CAPABILITY ) )
			\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'troy-server' ) );

		\add_filter(
			'admin_body_class',
			/**
			 * Adds a class to the body HTML tag.
			 *
			 * @since 0.0.1184
			 *
			 * @param string $body_class The body class string.
			 * @return string The modified body class string.
			 */
			fn( $body_class ) => "$body_class troy-server-settings ",
		);

		\add_filter(
			'removable_query_args',
			/**
			 * Adds the saved response query arg to the list of removable query args.
			 *
			 * @since 0.0.1184
			 *
			 * @param array $args The list of removable query args.
			 * @return array The modified list of removable query args.
			 */
			fn( $args ) => array_merge( $args, [ self::SAVED_RESPONSE ] ),
		);

		\add_action(
			'admin_enqueue_scripts',
			/**
			 * Enqueues the Troy Server settings page scripts and styles.
			 *
			 * @since 0.0.1184
			 */
			function () {
				$dir_url = \plugin_dir_url( MAIN_FILE );
				$min     = \SCRIPT_DEBUG ? '' : '.min';

				\wp_enqueue_style(
					'troy-server-settings-css',
					"{$dir_url}library/css/settings/main{$min}.css",
					[ 'dashicons', 'common', 'forms' ],
					VERSION,
				);
				\wp_enqueue_script(
					'troy-server-settings-js',
					"{$dir_url}library/js/settings/main{$min}.js",
					[],
					VERSION,
					true
				);

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affects asset loading only.
				$tab = $_GET['tab'] ?? 'plugin-stats';

				// Enqueue stats assets on plugin-stats and package-stats tabs.
				if ( \in_array( $tab, [ 'plugin-stats', 'package-stats' ], true ) )
					Stats::enqueue_assets();

				// Enqueue logs assets on logs tab.
				if ( 'logs' === $tab )
					Logs::enqueue_assets();
			}
		);

		\add_action(
			'troy_server_settings_tab_content',
			/**
			 * Outputs the content of the current Troy Server settings page.
			 *
			 * @since 0.0.1184
			 *
			 * @param string $current_tab The current settings tab.
			 */
			function ( $current_tab ) {
				Template::output_view( "settings/tab-$current_tab" );
			},
		);
	}

	/**
	 * Processes the settings submission.
	 *
	 * @hook admin_post_{self::SAVE_ACTION} 10
	 * @since 0.0.1184
	 */
	public static function process_settings_submission() {

		\check_admin_referer( self::SAVE_NONCE['action'], self::SAVE_NONCE['name'] );

		if ( ! \current_user_can( self::REQUIRED_CAPABILITY ) )
			\wp_die(
				\esc_html__( 'You do not have sufficient permissions to modify the settings.', 'troy-server' ),
				403,
			);

		$settings = $_POST['troy_server_settings'] ?? [];

		// TODO: Sanitize.

		$result = \get_option( 'troy_server_settings', $settings ) !== $settings
			? (int) \update_option( 'troy_server_settings', $settings )
			: 2;

		\wp_safe_redirect( \add_query_arg( self::SAVED_RESPONSE, $result, \wp_get_referer() ) );
		exit;
	}
}
