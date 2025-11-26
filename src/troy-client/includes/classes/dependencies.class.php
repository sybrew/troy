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
 * Class Troy\Client\Dependencies.
 *
 * @since 0.0.1184
 */
final class Dependencies {

	/**
	 * Sets the recheck flag on plugin activation.
	 *
	 * @hook activated_plugin 10
	 * @hook upgrader_process_complete 10
	 * @since 0.0.1184
	 */
	public static function set_recheck_flag() {
		recheck_dependencies( 'yes' );
	}

	/**
	 * Installs Troy Dependencies on plugin activation.
	 *
	 * @hook admin_init 10
	 * @since 0.0.1184
	 */
	public static function install_troy_dependencies() {

		// Only run this on actual admin pages, when the user can install plugins.
		if (
			   ! \current_user_can( 'install_plugins' )
			&& ! \wp_doing_ajax()
			&& ! \wp_doing_cron()
		) {
			return;
		}

		if ( (int) \get_site_option( 'troy_client_recheck_timeout' ) > time() )
			return;

		// get_troy_plugins() loads get_plugins() requirements. So switch these up.
		$troy_plugins = get_troy_plugins();
		$plugin_slugs = array_map(
			'Troy\Client\get_plugin_slug',
			array_keys( \get_plugins() ),
		);

		$installed     = [];
		$not_installed = [];

		require_once \ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$plugin_url = ''; // Ref. Do not optimize.
		$slug       = ''; // Ref. Do not optimize.

		\add_filter(
			'http_headers_useragent',
			function ( $user_agent, $url ) use ( &$plugin_url, &$slug ) {
				return $url === $plugin_url ? "Troy Dependencies/$slug" : $user_agent;
			},
			10,
			2,
		);

		foreach ( get_troy_plugin_dependencies() as $file => $plugin ) {
			foreach ( $plugin['dependencies'] as $dependency ) {
				$slug = $dependency['slug'];

				// Don't install the dependency if the plugin is already installed.
				if ( \in_array( $slug, $plugin_slugs, true ) )
					continue;

				$repo = make_fully_qualified_repo_url( $dependency['repo'] );

				// Write to $plugin_url so that the filter above can use it. This is a referenced variable.
				$plugin_url = "{$repo}plugin/get/zip/{$slug}/"; // Ref. Do not optimize.

				$skin     = new Skins\Background;
				$upgrader = new \Plugin_Upgrader( $skin );

				$result = $upgrader->install(
					$plugin_url,
					[
						'overwrite_package' => true,
					],
				);

				if ( true === $result ) {
					$installed[ $file ][] = [
						'name' => $upgrader->new_plugin_data['Name'] ?? $slug,
					];
				} else {
					$not_installed[ $file ][] = [
						'slug'   => $slug,
						'errors' => $skin->errors,
					];
				}
			}
		}

		if ( $installed ) {
			foreach ( $installed as $file => $details ) {
				$names = array_column( $details, 'name' );

				self::prepare_admin_message(
					\wp_sprintf(
						/* translators: 1: plugin name, 2: list of installed plugins */
						\_n(
							'The following plugin dependency was installed for %1$s: %2$l',
							'The following plugin dependencies were installed for %1$s: %2$l',
							\count( $names ),
							'troy-client',
						),
						$troy_plugins[ $file ]['name'],
						$names,
					),
					'success',
				);
			}

			\wp_clean_plugins_cache();
		}
		if ( $not_installed ) {
			foreach ( $not_installed as $file => $details ) {
				$slugs = array_column( $details, 'slug' );

				self::prepare_admin_message(
					\wp_sprintf(
						/* translators: 1: plugin name, 2: list of not-installed plugins */
						\_n(
							'The following plugin dependency could not be installed for %1$s: %2$l. This will be retried in 1 minute.',
							'The following plugin dependencies could not be installed for %1$s: %2$l. This will be retried in 1 minute.',
							\count( $slugs ),
							'troy-client',
						),
						$troy_plugins[ $file ]['name'],
						$slugs,
					),
					'warning',
				);
			}

			\update_site_option( 'troy_client_recheck_timeout', time() + 60 );
		}

		if ( ! $installed && ! $not_installed ) {
			/**
			 * Perhaps a dependency requires another dependency.
			 * So, we only unset the flag if no new dependencies were tried to install.
			 */
			recheck_dependencies( 'no' );

			// Delete the setting. This is only present in edge-cases, anyway.
			\delete_site_option( 'troy_client_recheck_timeout' );
		}
	}

	/**
	 * Prepares an admin message for display.
	 *
	 * @param string $message The message to display.
	 * @param string $type    The message type.
	 *                        Accepts 'success', 'error', 'warning', 'info'.
	 */
	private static function prepare_admin_message( $message, $type ) {
		\add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				\printf(
					'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
					\esc_attr( $type ),
					\esc_html( $message ),
				);
			}
		);
	}
}
