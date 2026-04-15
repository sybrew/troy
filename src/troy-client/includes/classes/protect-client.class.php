<?php
/**
 * @package Troy\Client
 * @access  private
 */

namespace Troy\Client;

\defined( 'Troy\Client\ABSPATH' ) or die;

/**
 * Troy Client
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
 * Class Troy\Client\Protect_Client.
 *
 * @since 0.0.1184
 */
final class Protect_Client {

	/**
	 * Filters the primitive capabilities required of the user to deactivate the plugin.
	 *
	 * @hook map_meta_cap 10
	 * @since 0.0.1184
	 * @ignore Unused. Triggers too often. Maybe later.
	 *
	 * @param string[] $caps    Primitive capabilities required of the user.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id The user ID.
	 * @param array    $args    Adds context to the capability check, typically
	 *                          starting with an object ID.
	 * @return string[] The filtered primitive capabilities required of the user.
	 */
	public static function filter_deactivation_capability( $caps, $cap, $user_id, $args ) {

		if (
			   'deactivate_plugin' === $cap
			&& isset( $args[0] ) && PLUGIN_BASENAME === $args[0]
			&& has_troy_plugins()
		) {
			return [ 'do_not_allow' ];
		}

		return $caps;
	}

	/**
	 * Hides the plugin from every plugins list when the HIDE constant is set.
	 *
	 * @hook plugins_list PHP_INT_MAX
	 * @since 0.0.1184
	 *
	 * @param array[] $plugins An array of arrays of plugin data, keyed by context.
	 */
	public static function hide_plugin( $plugins ) {

		if ( \defined( 'Troy\Client\HIDE' ) && HIDE )
			foreach ( $plugins as &$_plugins )
				unset( $_plugins[ PLUGIN_BASENAME ] );

		return $plugins;
	}

	/**
	 * Blocks plugin deactivation if necessary.
	 *
	 * @hook pre_update_option_active_plugins PHP_INT_MIN 2
	 * @hook pre_update_site_option_active_sitewide_plugins PHP_INT_MIN 2
	 * @since 0.0.1184
	 *
	 * @param ?array $value     The new, unserialized option value.
	 * @param array  $old_value The old option value.
	 * @return array The new value of the option.
	 */
	public static function block_plugin_deactivation( $value, $old_value ) {

		// Nothing changed. This is handled after this function's hook.
		if ( $value === $old_value )
			return $value;

		// Only record if this plugin got removed from the active plugins.
		// The plugin may be activated via different means, hence we test if it was activated at all.
		if (
			   \in_array( MAIN_FILE, (array) $old_value, true ) // The old value may have been deleted.
			&& ! \in_array( MAIN_FILE, $value, true )
			&& has_troy_plugins()
		) {
			\wp_die( \esc_html__( 'Troy is required by other plugins, so it cannot be deactivated.', 'troy-client' ) );
		}

		return $value;
	}

	/**
	 * Removes the deactivate link from the plugin row if there are plugins requiring Troy.
	 *
	 * @hook plugin_action_links_{\Troy\Client\PLUGIN_BASENAME} 10
	 * @hook network_admin_plugin_action_links_{\Troy\Client\PLUGIN_BASENAME} 10
	 * @since 0.0.1184
	 *
	 * @param array $actions The plugin row actions.
	 * @return array The modified plugin row actions.
	 */
	public static function remove_deactivate_link( $actions ) {

		if ( has_troy_plugins() )
			unset( $actions['deactivate'] );

		return $actions;
	}

	/**
	 * Hides the checkbox for the Troy Client plugin for no-JS, and deletes it
	 * on JS so it cannot be bulk-deactivated.
	 * The plugin is forced active, so the checkbox is redundant, and will only
	 * cause a loop of uselessness.
	 *
	 * @hook after_plugin_row_{\Troy\Client\PLUGIN_BASENAME} 10
	 * @since 0.0.1184
	 */
	public static function hide_action_checkbox() {

		if ( ! has_troy_plugins() ) return;

		// Redundancy escape. JS escapes also work for quoted CSS.
		$slug = \esc_js( PLUGIN_SLUG );

		// phpcs:disable WordPress.Security.EscapeOutput.HeredocOutputNotEscaped -- it is.
		echo <<<HTML
		<style>
			#the-list [data-slug="{$slug}"] .check-column :where(label,input) {
				display: none
			}
		</style>
		<script>
			document.querySelectorAll( '#the-list [data-slug="{$slug}"] .check-column :where(label,input)' )
				.forEach( e => e.remove() );
		</script>
		HTML;
		// phpcs:enable WordPress.Security.EscapeOutput
	}

	/**
	 * Lists all plugins that depend on Troy.
	 * If Troy Client Daemon is active, listing the dependents is redundant.
	 *
	 * @hook after_plugin_row_meta 10
	 * @since 0.0.1184
	 *
	 * @param string $plugin_file The plugin file.
	 */
	public static function list_troy_dependents( $plugin_file ) {

		if (
			   PLUGIN_BASENAME !== $plugin_file
			|| ! has_troy_plugins()
			|| ! \current_user_can( 'deactivate_plugin', $plugin_file )
		) {
			return;
		}

		if ( \defined( 'Troy\Client\Daemon\ACTIVE' ) ) {
			printf(
				'<p><span class="dashicons dashicons-lock"></span> <strong>%s</strong></p>',
				\esc_html( \__( 'Troy Client cannot be deactivated.', 'troy-client' ) ),
			);
		} else {
			$plugins = get_troy_plugins();
			unset( $plugins[ PLUGIN_BASENAME ] );

			printf(
				'<p><span class="dashicons dashicons-lock"></span> <strong>%s</strong></p>',
				\esc_html( \wp_sprintf(
					/* translators: %l = list of plugins */
					\__( 'Troy Client cannot be deactivated because the following plugins depend on Troy: %l.', 'troy-client' ),
					array_column( $plugins, 'name' ),
				) ),
			);
		}
	}
}
