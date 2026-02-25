<?php
/**
 * @package Troy\Server\Settings
 * @access  private
 */

namespace Troy\Server\Settings;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	Admin_Scripts,
	Template,
};

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
	 * Returns the menu icon SVG with a given fill color.
	 *
	 * @since 1.6.1184
	 *
	 * @param string $fill The SVG fill color.
	 * @return string The SVG markup.
	 */
	public static function get_icon_svg( $fill = 'currentColor' ) {

		$fill = \esc_attr( $fill );

		return <<<XML
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="$fill"><path d="M19.44,6.29c-.53-.6-1.17-1.7-1.24-2.19s-.29-.67-1.03-1.44c0,0,.4-.33.29-.76,0,0-.05.29-.8.36-.49.05-.67.21-.67.21,0,0,0,0,0,0-.19-.14-.64-.14-1.12.02-.71.12-1.37.46-1.88.97-.65.65-1.02,1.53-1.02,2.45,0,.96-.78,1.75-1.74,1.75h-5.22c-.96,0-1.74.78-1.74,1.74v8.7h1.74v-4.35c.46,0,.9-.18,1.23-.51.33-.33.51-.77.51-1.23v-.46c.96.86,2.2,1.33,3.48,1.33.59,0,1.18-.11,1.74-.31v5.53h1.74v-6.56c1.1-.99,1.74-2.4,1.74-3.88h0v-1.74s.04,0,.06,0c.09.22.36.58,1.22.75,1.28.26,1.21.64,1.28.9.1.41.78.4.95.09.07-.13.27,0,.37-.15.05-.08.03-.23.14-.32.3-.24.41-.44,0-.91Z"/><path d="M.29,9.4v5.22h1.74v-6.96h0c-.96,0-1.74.78-1.74,1.74Z"/><path d="M14.92,12.54h1.41c0,.37-.15.73-.41.99-.26.26-.62.41-.99.41v1.4c.74,0,1.45-.29,1.97-.82.52-.52.82-1.23.82-1.97v-1.4h-1.41c-.77,0-1.4.63-1.4,1.4Z"/><rect x="6.28" y="14.62" width="1.74" height="3.48"/></svg>
			XML;
	}

	/**
	 * Register the Troy Settings admin menu.
	 *
	 * @hook admin_menu 10
	 * @since 0.0.1184
	 */
	public static function register_admin_menu() {

		$name = \__( 'Troy Server', 'troy-server' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affects color scheme detection only.
		$on_settings = ( $_GET['page'] ?? '' ) === self::SETTINGS_PAGE_SLUG;
		$fill        = match ( Admin_Scripts::get_admin_color_scheme() ) {
			'fresh' => $on_settings ? '#ccc' : '#9da1a7',
			'light' => $on_settings ? '#fff' : '#999',
			default => '#fff',
		};

		$page = \add_menu_page(
			$name,
			$name,
			self::REQUIRED_CAPABILITY,
			self::SETTINGS_PAGE_SLUG,
			[ __CLASS__, 'render_admin_menu' ],
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- We want inline SVG here.
			'data:image/svg+xml;base64,' . \base64_encode( self::get_icon_svg( $fill ) ),
			3.1184,
		);

		\add_action( "load-$page", [ __CLASS__, 'init_admin_page' ] );
		\add_action( 'troy_server_settings_notices', [ __CLASS__, 'output_saved_notice' ] );
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
					true,
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
	 * Outputs the saved response notice.
	 *
	 * @hook troy_server_settings_notices 10
	 * @since 0.0.1184
	 */
	public static function output_saved_notice() {

		// phpcs:ignore WordPress.Security.NonceVerification -- Affects output view only.
		[ $notice_type, $message ] = match ( (int) ( $_GET[ self::SAVED_RESPONSE ] ?? -1 ) ) {
			0 => [
				'error',
				\__( 'Settings failed to save.', 'troy-server' ),
			],
			1 => [
				'success',
				\__( 'Settings saved.', 'troy-server' ),
			],
			2 => [
				'info',
				\__( 'No settings were changed.', 'troy-server' ),
			],
			default => [ '', '' ],
		};

		if ( $notice_type )
			printf(
				'<div id=message class="notice notice-%s is-dismissible inline"><p>%s</p></div>',
				\esc_attr( $notice_type ),
				\esc_html( $message ),
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
			? (int) \update_option( 'troy_server_settings', $settings, true )
			: 2;

		// Using safe: referer header is user-controlled
		\wp_safe_redirect( \add_query_arg( self::SAVED_RESPONSE, $result, \wp_get_referer() ) );
		exit;
	}
}
