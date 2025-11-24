<?php
/**
 * This is not a plugin, but a snippet for installing and activating the
 * Troy Client plugin.
 *
 * A silencing upgrade skin is used to prevent messages from being displayed in
 * the admin area. This is because the default skin disables the output buffer,
 * meaning that message suppression via ob_* functions is not possible.
 *
 * Troy Installer is a much more advanced version of this snippet, which
 * intelligently handles the installation and activation of the Troy Client
 * plugin, like considering existing plugins. It also comes with a custom skin
 * to hint users about the installation process. Therefore, we recommend using
 * Troy Installer instead of this snippet.
 *
 * @package Troy\Embed
 */

add_action(
	'admin_init',
	function () {

		$plugin_file = 'troy-client/troy-client.php';
		$client_url  = 'https://repo.deploytroy.org/plugin/get/zip/troy-client/';

		if ( ! is_plugin_active( $plugin_file ) ) {

			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			add_filter(
				'http_headers_useragent',
				fn( $user_agent, $url ) => $url === $client_url ? 'Troy Embed' : $user_agent,
				10,
				2,
			);

			$result = ( new Plugin_Upgrader(
				new class extends stdClass {
					/**
					 * @param string $name      The method name.
					 * @param array  $arguments The method arguments.
					 * @return mixed|void
					 */
					public function __call( $name, $arguments ) {} // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis
					/**
					 * @param string $name      The method name.
					 * @param array  $arguments The method arguments.
					 * @return mixed|void
					 */
					public static function __callStatic( $name, $arguments ) {} // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis
				}
			) )->install( $client_url, [ 'overwrite_package' => true ] );

			if ( true === $result ) {
				wp_clean_plugins_cache();
				activate_plugin( $plugin_file, '', is_multisite(), true );
			}
		}
	}
);
