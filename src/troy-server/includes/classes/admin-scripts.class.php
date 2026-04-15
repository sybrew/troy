<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server;

\defined( 'Troy\Server\ABSPATH' ) or die;

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

/**
 * Class Troy\Server\Admin_Scripts.
 *
 * Handles global admin script registration for the Troy Server plugin.
 *
 * @since 0.0.1184
 */
final class Admin_Scripts {

	/**
	 * Returns the current user's effective admin color scheme.
	 *
	 * Falls back to 'modern' for WP 7.0+ and 'fresh' for older versions
	 * when the user has no explicit color preference.
	 *
	 * @since 1.6.1184
	 *
	 * @return string The admin color scheme slug.
	 */
	public static function get_admin_color_scheme() {
		return \get_user_option( 'admin_color' ) ?: (
			// WP 7.0+ 'modern', fallback to 'fresh' for older versions
			\version_compare( \get_bloginfo( 'version' ), '7.0', '<' )
				? 'fresh'
				: 'modern'
		);
	}

	/**
	 * Registers global admin main scripts and styles.
	 *
	 * These scripts are registered early and unconditionally in admin so they
	 * can be enqueued as dependencies by other scripts.
	 *
	 * Also registers CSS custom properties at :root{} for consistent admin theming:
	 * - --troy-server-theme-bg
	 * - --troy-server-theme-bg-accent
	 * - --troy-server-theme-color
	 * - --troy-server-theme-color-accent
	 * - --troy-server-green
	 * - --troy-server-red
	 *
	 * @hook admin_init 10
	 * @since 0.0.1184
	 */
	public static function register_main_scripts() {

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_register_script(
			'troy-server-escape',
			"{$dir_url}library/js/utils/escape{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-sanitize',
			"{$dir_url}library/js/utils/sanitize{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-sort',
			"{$dir_url}library/js/utils/sort{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-timing',
			"{$dir_url}library/js/utils/timing{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-format',
			"{$dir_url}library/js/utils/format{$min}.js",
			[ 'wp-i18n' ],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-assign',
			"{$dir_url}library/js/utils/assign{$min}.js",
			[],
			VERSION,
			true,
		);

		$colors = $GLOBALS['_wp_admin_css_colors'][ self::get_admin_color_scheme() ]->colors ?? null;

		if ( ! \is_array( $colors ) || \count( $colors ) < 3 )
			$colors = [ '#222', '#333', '#0073aa', '#00a0d2' ];

		$colors[3] ??= $colors[2]; // Some schemes don't have an accent color, so we fallback to the regular color.

		\wp_add_inline_style(
			'common',
			<<<CSS
			:root {
				--troy-server-theme-bg:{$colors[0]};
				--troy-server-theme-bg-accent:{$colors[1]};
				--troy-server-theme-color:{$colors[2]};
				--troy-server-theme-color-accent:{$colors[3]};
				--troy-server-green:#00a32a;
				--troy-server-red:#d63638;
			}
			CSS,
		);
	}

	/**
	 * Registers Troy Mode styles and scripts inline.
	 *
	 * Troy Mode allows users to hide non-essential admin menu items,
	 * showing only Dashboard, Troy Server items, Plugins, Tools, and Settings.
	 * State is persisted in localStorage for instant client-side toggling.
	 *
	 * Inlined after admin-menu to prevent flash of unstyled content (FOUC).
	 *
	 * @hook admin_enqueue_scripts 1
	 * @since 0.0.1184
	 */
	public static function register_troy_mode() {

		$dir = ABSPATH . 'library';
		$min = \SCRIPT_DEBUG ? '' : '.min';

		// phpcs:ignore TSF.Performance, WordPress.WP.AlternativeFunctions -- Local trusted file.
		\wp_add_inline_style( 'admin-menu', file_get_contents( "$dir/css/admin/troy-mode$min.css" ) );
		// phpcs:ignore TSF.Performance, WordPress.WP.AlternativeFunctions -- Local trusted file.
		\wp_add_inline_script( 'common', file_get_contents( "$dir/js/admin/troy-mode$min.js" ) );

		// We could've done WP User settings, but let's not burden the database for something this silly.
		// Prevent FOUC by printing script inline to set body class before anything else renders.
		\add_action(
			'in_admin_header',
			function () {
				echo '<script>',
					<<<'JS'
					'1' === localStorage.getItem( 'troyServerModeActive' ) && document.body.classList.add( 'troy-server-mode-active' )
					JS,
					'</script>';
			},
		);
	}
}
