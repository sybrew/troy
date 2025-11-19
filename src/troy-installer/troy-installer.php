<?php
/**
 * Troy Installer
 *
 * @package   Troy\Installer
 * @author    Sybre Waaijer
 * @copyright 2025 Sybre Waaijer, CyberWire B.V. (https://cyberwire.nl/)
 * @license   MIT
 * @link      https://github.com/sybrew/troy/
 *
 * @troy-generator * plugin-header
 * @wordpress-plugin
 * Plugin Name: Troy Installer
 * Plugin URI: https://deploytroy.org/
 * Description: Troy Installer installs "Troy Client" and vendor plugins.
 * Version: 0.0.1184
 * Author: Sybre Waaijer
 * Author URI: https://deploytroy.org/
 * License: MIT
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Network: true
 * @troy-generator * plugin-header
 */

namespace Troy\Installer;

\defined( 'ABSPATH' ) or die;

{ { { { { { { { { { { { { { { { { {
	'made' || 'with' || 'love';
	_by_:      'Sybre Waaijer';
	_for_:     'The community';
	_license_: 'MIT. No GPLv2';
	RetakeWordPressWith::class;
} } } } } } } } } } } } } } } } } }

/**
 * Troy Installer
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

// =============================================================================
// Start editing here. =========================================================
// =============================================================================

const PLUGIN_NAME = 'Troy Installer'; // @troy-generator plugin-name

/**
 * The options for the installer.
 *
 * @since 0.0.1184
 *
 * @var array $options {
 *    An array of options for the installer.
 *
 *    @type int  $install_timeout          The timeout for the installer.
 *    @type bool $deactivate_on_completion Whether to deactivate the installer plugin after completion.
 *    @type bool $delete_on_completion     Whether to delete the installer plugin after completion.
 *    @type bool $notice_severity          The severity of the notice. Accepts 'summary', 'detailed', 'verbose', and 'silent'.
 * }
 */
// @troy-generator * plugin-options
const OPTIONS = [
	'install_timeout'          => 30,
	'deactivate_on_completion' => true,
	'delete_on_completion'     => false,
	'notice_severity'          => 'detailed',
];
// @troy-generator * plugin-options

/**
 * The plugins to install.
 *
 * @since 0.0.1184
 *
 * @var array $install {
 *     An array of plugins to install.
 *
 *     @type string $slug The plugin slug.
 *     @type array  $conf {
 *         An array of installation configuration arguments.
 *
 *         @type string $repo           Required. The repository URI.
 *         @type string $name           Required. The plugin name.
 *         @type string $version        Required. The plugin version. Set to 'latest' to get the latest version.
 *         @type bool   $activate       Whether to instantly activate the plugin.
 *                                      Default true.
 *         @type bool   $network        Whether the plugin is to be network-activated (multisite).
 *                                      Default false.
 *         @type bool   $overwrite      Whether to overwrite any existing plugin with the same slug.
 *                                      We recommend setting this to true, so that the plugin can be updated.
 *                                      If you set this to false, and the plugin already exists and is inactive,
 *                                      the user will get an error. The plugin will also not be activated.
 *                                      Default false.
 *         @type bool   $overwrite_troy Whether to overwrite any existing Troy plugin with the same slug.
 *                                      Default false.
 *     }
 * }
 *
 * phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
 */
// @troy-generator * plugin-install
const INSTALL = [
	'test-plugin' => [
		'name'           => 'Test Plugin',
		'repo'           => 'https://repo.deploytroy.org/',
		'version'        => 'latest',
		'activate'       => true,
		'network'        => true,
		'overwrite'      => true,
		'overwrite_troy' => false,
	],
];
// @troy-generator * plugin-install

// =============================================================================
// Stop editing here. ==========================================================
// =============================================================================

// phpcs:enable, WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
register_admin_message(
	\sprintf(
		INSTALL
			? 'Plugin "%s" is active. This plugin is installing "Troy Client" and vendor plugins.'
			: 'Plugin "%s" is active. This plugin is installing "Troy Client."',
		PLUGIN_NAME,
	),
	'info',
);

// phpcs:ignore WordPress.Security.NonceVerification -- no data is being handled.
if ( isset( $_GET['activate'] ) && ( OPTIONS['deactivate_on_completion'] || OPTIONS['delete_on_completion'] ) )
	\add_filter( 'wp_admin_notice_markup', 'Troy\Installer\suppress_activation_notice' );

\add_action( 'admin_notices', 'Troy\Installer\output_registered_install_notices' );
\add_action( 'admin_init', 'Troy\Installer\install_plugins' );

/**
 * Suppresses the activation notice if this plugin is to be deactivated or deleted immediately after activation.
 * This is to mitigate confusion where the user is seeing a conflicting activation notice (ours and WordPress's).
 *
 * There's no better way to do this, as the activation notice is hardcoded in `wp-admin/plugins.php`,
 * without referencing the plugin activated.
 *
 * @since 0.0.1184
 *
 * @param string $markup The HTML markup for the admin notice.
 */
function suppress_activation_notice( $markup ) {
	return str_contains( $markup, \__( 'Plugin activated.', 'default' ) ) ? '' : $markup;
}

/**
 * Outputs the registered installation notices.
 *
 * @since 0.0.1184
 */
function output_registered_install_notices() {

	if ( ! \current_user_can( 'install_plugins' ) )
		return;

	$messages = register_admin_message( '', '', true );

	if ( ! $messages )
		return;

	$dashicons   = [
		'info'    => [ 'editor-help', '#2271b1' ],
		'success' => [ 'yes', '#00a32a' ],
		'warning' => [ 'flag', '#ffae00' ],
		'error'   => [ 'no', '#d63638' ],
	];
	$allowedtags = [
		'a'       => [
			'href'   => [],
			'title'  => [],
			'target' => [],
		],
		'abbr'    => [ 'title' => [] ],
		'acronym' => [ 'title' => [] ],
		'code'    => [],
		'pre'     => [],
		'em'      => [],
		'strong'  => [],
		'div'     => [ 'class' => [] ],
		'span'    => [ 'class' => [] ],
		'p'       => [],
		'br'      => [],
		'ul'      => [],
		'ol'      => [],
		'li'      => [],
	];

	$notice = '';

	foreach ( $messages as [ $message, $type ] ) {
		[ $dashicon, $color ] = $dashicons[ $type ];

		// `\wp_kses_post()` in `\wp_admin_notice()` is too stringent and loose.
		$message = \wp_kses( $message, $allowedtags );

		$notice .= <<<HTML
			<div style="display:flex;align-items:center;gap:1ch;padding:.5ch;">
				<div class="dashicons dashicons-$dashicon" style="padding-inline-end:1ch;padding-block-end:1ch;color:$color"></div>
				<p>$message</p>
			</div>
		HTML;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput -- already escaped.
	echo \wp_get_admin_notice(
		$notice,
		[
			'id'             => 'troy-installer-message',
			'type'           => 'info',
			'paragraph_wrap' => false,
		],
	);
}

/**
 * Installs the Troy Client and vendor plugins.
 * Registers an admin message if the installation fails.
 *
 * The Troy Client is a forced dependency.
 * This is because, without the Troy Client, the default plugin repository (api.wordpress.org)
 * will be used to fetch plugin information. This is not desired and can lead to a supply chain attack.
 *
 * @since 0.0.1184
 */
function install_plugins() {

	if (
		   ! \current_user_can( 'install_plugins' )
		|| \wp_doing_ajax()
		|| \wp_doing_cron()
	) {
		return;
	}

	$plugins   = \get_plugins();
	$installer = \plugin_basename( __FILE__ );
	$installed = [];
	$activated = [];

	\wp_raise_memory_limit( 'troy-installer' );

	$ini_max_execution_time = (int) ini_get( 'max_execution_time' );

	if ( 0 !== $ini_max_execution_time && \function_exists( 'set_time_limit' ) )
		set_time_limit( max( $ini_max_execution_time, OPTIONS['install_timeout'] ) );

	install_troy:
	if ( \function_exists( 'Troy\Client\get_troy_plugin_repos_per_slug' ) )
		goto install_deps;

	if ( isset( $plugins['troy-client/troy-client.php'] ) )
		goto activate_troy;

	require_once \ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$client_url = 'https://repo.deploytroy.org/plugin/get/zip/troy-client/';

	\add_filter(
		'http_headers_useragent',
		fn( $user_agent, $url ) => $url === $client_url ? 'Troy Installer/' . PLUGIN_NAME : $user_agent,
		10,
		2,
	);

	$skin   = new Troy_Installer_Skin;
	$result = ( new \Plugin_Upgrader( $skin ) )->install(
		$client_url,
		[ 'overwrite_package' => true ],
	);

	if ( true !== $result ) {
		$notice_args = [
			'before' => \sprintf(
				'Plugin "Troy Client" could not be installed. This will be retried until you deactivate plugin "%s."',
				$plugins[ $installer ]['Name'],
			),
			'type'   => 'error',
		];
		register_skin_messages( $skin, $notice_args );
		return;
	}

	$installed[] = 'Troy Client';

	activate_troy:
	require_once \ABSPATH . 'wp-admin/includes/plugin.php';

	\activate_plugin( 'troy-client/troy-client.php', '', \is_multisite(), true );

	if ( ! \function_exists( 'Troy\Client\get_troy_plugin_repos_per_slug' ) ) {
		register_admin_message(
			\sprintf(
				'Plugin "Troy Client" could not be activated. This will be retried until you deactivate plugin "%s."',
				$plugins[ $installer ]['Name'],
			),
			'error',
		);
		return;
	}

	$activated[] = 'Troy Client';

	// Clear the plugin cache, so Troy Client can enable Troy plugin headers.
	\wp_clean_plugins_cache();

	install_deps:
	$slug_repos   = \Troy\Client\get_troy_plugin_repos_per_slug();
	$is_multisite = \is_multisite();

	$install    = INSTALL;
	$to_install = [];

	$install_defaults = [
		'activate'       => true,
		'network'        => false,
		'overwrite'      => false,
		'overwrite_troy' => false,
	];

	$not_installed = [];
	$not_activated = [];

	foreach ( $install as $slug => &$conf ) {
		$conf = array_merge( $install_defaults, $conf );

		$conf['network'] = $is_multisite ? $conf['network'] : false;

		if ( isset( $slug_repos[ $slug ] ) && empty( $conf['overwrite_troy'] ) )
			continue;

		$to_install[ $slug ] = $conf;
	}

	require_once \ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$plugin_url = ''; // Ref.

	\add_filter(
		'http_headers_useragent',
		function ( $user_agent, $url ) use ( &$plugin_url ) {
			return $url === $plugin_url ? 'Troy Installer/' . PLUGIN_NAME : $user_agent;
		},
		10,
		2,
	);

	foreach ( $to_install as $slug => $conf ) {
		// Write to $plugin_url so that the filter above can use it. This is a referenced variable.
		$plugin_url = "{$conf['repo']}plugin/get/zip/$slug/{$conf['version']}/"; // Ref.

		$result = ( new \Plugin_Upgrader( $skin ) )->install(
			$plugin_url,
			[
				'overwrite_package' => $conf['overwrite'] ?? false,
			],
		);

		if ( true === $result ) {
			$installed[] = $conf['name'];
		} else {
			$not_installed[] = $conf['name'];
			$notice_args     = [
				'before' => \sprintf(
					'Plugin "%s" could not be installed. This will be retried until you deactivate plugin "%s."',
					$conf['name'],
					$plugins[ $installer ]['Name'],
				),
				'type'   => 'error',
			];
			register_skin_messages( $skin, $notice_args ?? [] );
		}
	}

	if ( $installed ) {
		register_admin_message(
			\sprintf(
				\count( $installed ) > 1
					? 'The following plugins were installed: %s'
					: 'The following plugin was installed: %s',
				list_items( $installed ),
			),
			'success',
		);

		\wp_clean_plugins_cache();
	}
	if ( $not_installed ) {
		register_admin_message(
			\sprintf(
				\count( $not_installed ) > 1
					? 'The following plugins were not installed: %s'
					: 'The following plugin was not installed: %s',
				list_items( $not_installed ),
			),
			'warning',
		);
	}

	foreach ( \get_plugins() as $file => $plugin ) {
		$install_args = $install[ \dirname( $file ) ] ?? null;

		if ( empty( $install_args['activate'] ) )
			continue;

		if ( \is_plugin_active( $file ) ) {
			// If we had to overwrite, it'll remain activated. Tell it's "been" activated.
			if ( $install_args['overwrite'] )
				$activated[] = $plugin['Name'];

			continue;
		}

		if ( \is_wp_error( \activate_plugin( $file, '', $install_args['network'], true ) ) ) {
			$not_activated[] = $plugin['Name'];
		} else {
			$activated[] = $plugin['Name'];
		}
	}

	if ( $activated ) {
		register_admin_message(
			\sprintf(
				\count( $activated ) > 1
					? 'The following plugins were activated: %s'
					: 'The following plugin was activated: %s',
				list_items( $activated ),
			),
			'success',
		);
	}
	if ( $not_activated ) {
		register_admin_message(
			\sprintf(
				\count( $not_activated ) > 1
					? 'The following plugins failed activating: %s'
					: 'The following plugin failed activating: %s',
				list_items( $not_activated ),
			),
			'warning',
		);
	}

	if ( empty( $not_installed ) && empty( $not_activated ) ) {
		if ( OPTIONS['delete_on_completion'] ) {
			\deactivate_plugins( $installer, true, \is_multisite() );
			\delete_plugins( [ $installer ] );
			register_admin_message(
				\sprintf(
					'Installation has completed successfully. Plugin "%s" was deactivated and deleted automatically. Refresh to see the changes.',
					PLUGIN_NAME,
				),
				'info',
			);
		} elseif ( OPTIONS['deactivate_on_completion'] ) {
			\deactivate_plugins( $installer, true, \is_multisite() );
			register_admin_message(
				\sprintf(
					'Installation has completed successfully. Plugin "%s" has been deactivated automatically.',
					PLUGIN_NAME,
				),
				'info',
			);
		}
	}
}

/**
 * Lists items in a human-readable format.
 *
 * This function is a port of the `wp_sprintf_l()` function from WordPress.
 * But theirs does not adhere to proper English quotation marking.
 *
 * @since 0.0.1184
 *
 * @param string[] $items The items to list.
 * @return string The human-readable list.
 */
function list_items( $items ) {

	if ( ! $items )
		return '';

	$l = [
		'single'   => '"%s"',
		'only_two' => '"%1$s" and "%2$s"',
		'over_two' => [
			'any'  => '"%1$s," ',
			'last' => 'and "%s"',
		],
	];

	switch ( \count( $items ) ) {
		case 1:
			return \sprintf( $l['single'], $items[0] );
		case 2:
			return \sprintf( $l['only_two'], $items[0], $items[1] );
		default:
			$i = \count( $items );

			$list = '';
			$any  = $l['over_two']['any'];

			while ( --$i ) // phpcs:ignore WordPress.WhiteSpace.ControlStructureSpacing -- phpcs bug
				$list .= \sprintf( $any, array_shift( $items ) );

			return $list . \sprintf(
				$l['over_two']['last'],
				array_shift( $items ),
			);
	}
}

/**
 * Registers an admin message for later display.
 *
 * @since 0.0.1184
 *
 * @param string $message The message to register.
 * @param array  $type    The type of message to register. Accepts 'error', 'success', 'warning', 'info'. Default 'info'.
 *                        The highest severity will be memoized.
 * @param bool   $get     Whether to return the messages
 * @return ?array Void if silent or on set, array otherwise. {
 *     An array of registered messages.
 *
 *     @type string $message The message.
 *     @type string $type    The type of message.
 * }
 */
function register_admin_message( $message, $type = 'info', $get = false ) {

	if ( 'silent' === OPTIONS['notice_severity'] )
		return;

	static $messages = [];

	if ( $get )
		return $messages;

	if ( ! \in_array( $message, array_column( $messages, 0 ), true ) )
		$messages[] = [ $message, $type ];
}

/**
 * Extracts the error message from a WP_Error object.
 *
 * @since 0.0.1184
 *
 * @param Troy_Installer_Skin $skin The installer skin.
 * @param array               $args {
 *     An array of notice arguments.
 *
 *     @type string $before The message to prepend to the error message.
 *     @type string $type   The type of notice to output. Accepts 'error', 'success', 'warning', 'info'. Default 'info'.
 *     @type string $after  The message to append to the error message.
 * }
 */
function register_skin_messages( $skin, $args = [] ) {

	if ( ! \in_array( OPTIONS['notice_severity'], [ 'detailed', 'verbose' ], true ) )
		return;

	$args = array_merge(
		[
			'before' => '',
			'type'   => 'info',
			'after'  => '',
		],
		$args,
	);

	$errors   = $skin->errors;
	$messages = [];

	if ( 'verbose' === OPTIONS['notice_severity'] ) foreach ( $errors as $error ) {
		if ( \is_string( $error ) ) {
			$messages[] = $error;
		} elseif ( \is_wp_error( $error ) && $error->has_errors() ) {
			$error_data = $error->get_error_data() ?: '';

			if ( \is_string( $error_data ) )
				$error_data = ' ' . \esc_html( \wp_strip_all_tags( $error_data ) );

			foreach ( $error->get_error_messages() as $message )
				$messages[] = "{$message}{$error_data}<br>";
		}
	}

	if ( $messages ) {
		$args['type'] = 'error';
		$message      = \sprintf(
			\count( $messages ) > 1
				? 'The following installlation errors were given:<br>%s'
				: 'The following installlation error was given: %s',
			implode( '<br>', $messages ),
		);
	}

	$notice = implode(
		'<br>',
		array_filter(
			[
				$args['before'],
				$message ?? '',
				$args['after'],
			],
			'strlen',
		),
	);

	\strlen( $notice )
		and register_admin_message( $notice, $args['type'] );
}

/**
 * A custom installer skin for the Troy Installer.
 * It silences all output and only logs errors.
 *
 * @since 0.0.1184
 */
class Troy_Installer_Skin extends \stdClass { // phpcs:ignore -- This plugin must be single-file.

	/**
	 * @since 0.0.1184
	 *
	 * @var array[string|WP_Error] The errors that occurred during installation.
	 */
	public $errors = [];

	/**
	 * Handles installation error messages.
	 *
	 * @since 0.0.1184
	 *
	 * @param string|WP_Error $error The error message or \WP_Error object.
	 */
	public function error( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * @since 0.0.1184
	 * @param string $name      The method name.
	 * @param array  $arguments The method arguments.
	 * @return mixed|void
	 */
	public function __call( $name, $arguments ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis
		return null;
	}

	/**
	 * @since 0.0.1184
	 * @param string $name      The method name.
	 * @param array  $arguments The method arguments.
	 * @return mixed|void
	 */
	public static function __callStatic( $name, $arguments ) {  // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis
		return null;
	}
}
