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
 * Class Troy\Client\SiteHealth.
 *
 * @since 0.0.1184
 */
final class SiteHealth {

	/**
	 * Registers the site status tests for Troy Client.
	 *
	 * @hook site_status_tests 10
	 * @since 0.0.1184
	 *
	 * @param array[] $tests {
	 *     An associative array of direct and asynchronous tests.
	 *
	 *     @type array[] $direct {
	 *         An array of direct tests.
	 *
	 *         @type array ...$identifier {
	 *             `$identifier` should be a unique identifier for the test. Plugins and themes are encouraged to
	 *             prefix test identifiers with their slug to avoid collisions between tests.
	 *
	 *             @type string   $label     The friendly label to identify the test.
	 *             @type callable $test      The callback function that runs the test and returns its result.
	 *             @type bool     $skip_cron Whether to skip this test when running as cron.
	 *         }
	 *     }
	 *     @type array[] $async {
	 *         An array of asynchronous tests.
	 *
	 *         @type array ...$identifier {
	 *             `$identifier` should be a unique identifier for the test. Plugins and themes are encouraged to
	 *             prefix test identifiers with their slug to avoid collisions between tests.
	 *
	 *             @type string   $label             The friendly label to identify the test.
	 *             @type string   $test              An admin-ajax.php action to be called to perform the test, or
	 *                                               if `$has_rest` is true, a URL to a REST API endpoint to perform
	 *                                               the test.
	 *             @type bool     $has_rest          Whether the `$test` property points to a REST API endpoint.
	 *             @type bool     $skip_cron         Whether to skip this test when running as cron.
	 *             @type callable $async_direct_test A manner of directly calling the test marked as asynchronous,
	 *                                               as the scheduled event can not authenticate, and endpoints
	 *                                               may require authentication.
	 *         }
	 *     }
	 * }
	 * @return array The modified site status tests.
	 */
	public static function register_site_status_tests( $tests ) {

		$tests['async']['troy_client_repo_communications'] = [
			'label'     => \__( 'Troy Client &mdash; Dependent Plugins', 'troy-client' ),
			'test'      => \rest_url( 'troy-client/v1/site-health/test-communications' ),
			'has_rest'  => true,
			'skip_cron' => false,
		];

		return $tests;
	}

	/**
	 * Registers the debug information route for Troy Client.
	 *
	 * @hook rest_api_init 100
	 * @since 0.0.1184
	 */
	public static function register_async_rest_routes() {
		\register_rest_route(
			'troy-client/v1',
			'site-health/test-communications',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ static::class, 'test_troy_repo_communications' ],
					'permission_callback' => fn() => \current_user_can( 'view_site_health_checks' ),
				],
			],
		);
	}

	/**
	 * Tests whether Troy Client can communicate with the registered Troy Server repositories.
	 *
	 * @since 0.0.1184
	 *
	 * @return array The test results.
	 */
	public static function test_troy_repo_communications() {

		if ( ! get_troy_plugin_repos_per_slug() )
			return [
				'label'   => \__( 'No Troy repositories found.', 'troy-client' ),
				'status'  => 'good',
				'message' => \__( 'No Troy repositories have been registered, so communication can not be tested.', 'troy-client' ),
			];

		$result = [
			'badge'       => [
				'label' => \__( 'Security', 'default' ),
				'color' => 'blue',
			],
			'status'      => 'good',
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Troy Client communicates with the Troy repositories to check for new plugin versions.', 'troy-client' )
			),
			'test'        => 'troy_client_repo_communications',
		];

		$i18n = [
			/* translators: %s: The repository URL */
			'communication_failed' => \__( 'Communication with this repository failed: %s.', 'troy-client' ),
			/* translators: %l: List of plugins affected (just one). */
			'plugin_affected'      => \__( 'This plugin is affected: %l.', 'troy-client' ),
			/* translators: %l: List of plugins affected. */
			'plugins_affected'     => \__( 'These plugins are affected: %l.', 'troy-client' ),
			/* translators: %s: The error message. */
			'error_returned'       => \__( 'This error was returned: %s', 'troy-client' ),
			/* translators: %1$s: The plugin name, %2$s: The plugin slug. */
			'plugin_name_slug'     => \__( '%1$s (slug: %2$s)', 'troy-client' ),
			/* translators: %s: The plugin name and slug. */
			'plugin_depdendency'   => \__( 'Pending plugin dependency', 'troy-client' ),
		];

		$plugins    = get_troy_plugins();
		$has_errors = false;

		foreach ( get_troy_plugin_slugs_per_repo() as $repo => $slugs ) {
			// WordPress features requiring processing.
			$ping = make_troy_api_request( "{$repo}ping/", '', 'GET' );

			if ( \is_wp_error( $ping ) ) {
				$has_errors = true;

				// Memoize a list of plugin names for the error message. This is late for performance optimization.
				$_plugin_names ??= array_combine(
					array_column( $plugins, 'slug' ),
					array_column( $plugins, 'name' ),
				);

				$result['description'] .= \sprintf(
					'<p>%s<br>%s<br>%s</p>',
					\sprintf(
						'<span class="dashicons error"><span class="screen-reader-text">%s</span></span> %s',
						/* translators: Hidden accessibility text. */
						\__( 'Error', 'default' ),
						\sprintf(
							$i18n['communication_failed'],
							"<code>$repo</code>",
						),
					),
					\sprintf(
						'<span class="dashicons warning"></span> %s',
						\wp_sprintf(
							1 === \count( $slugs )
								? $i18n['plugin_affected']
								: $i18n['plugins_affected'],
							array_map(
								fn( $slug ) => \sprintf(
									$i18n['plugin_name_slug'],
									$_plugin_names[ $slug ] ?? $i18n['plugin_depdendency'],
									$slug,
								),
								$slugs,
							),
						),
					),
					\sprintf(
						'<span class="dashicons info"></span> %s',
						\sprintf(
							/* translators: 1: The IP address WordPress.org resolves to. 2: The error returned by the lookup. */
							$i18n['error_returned'],
							$ping->get_error_message(),
						),
					),
				);
			}
		}

		if ( $has_errors ) {
			$result = array_merge(
				$result,
				[
					'label'   => \__( 'Could not reach registered Troy repositories', 'troy-client' ),
					'status'  => 'critical',
					'actions' => \sprintf(
						'<p><a href="%s" target="_blank" rel="noopener">%s<span class="screen-reader-text"> %s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
						'https://deploytroy.org/docs/troy-client/fixing-communication-issues/',
						\__( 'Learn how to fix communication issues', 'troy-client' ),
						/* translators: Hidden accessibility text. */
						\__( '(opens in a new tab)', 'default' ),
					),
				],
			);
		} else {
			$result['label'] = \__( 'Can communicate with all registered Troy repositories', 'troy-client' );
		}

		return $result;
	}

	/**
	 * Blocks plugin deactivation if necessary.
	 *
	 * @hook debug_information 10
	 * @since 0.0.1184
	 *
	 * @param array $info {
	 *     The debug information to be added to the core information page.
	 *
	 *     This is an associative multi-dimensional array, up to three levels deep.
	 *     The topmost array holds the sections, keyed by section ID.
	 *
	 *     @type array ...$0 {
	 *         Each section has a `$fields` associative array (see below), and each `$value` in `$fields`
	 *         can be another associative array of name/value pairs when there is more structured data
	 *         to display.
	 *
	 *         @type string $label       Required. The title for this section of the debug output.
	 *         @type string $description Optional. A description for your information section which
	 *                                   may contain basic HTML markup, inline tags only as it is
	 *                                   outputted in a paragraph.
	 *         @type bool   $show_count  Optional. If set to `true`, the amount of fields will be included
	 *                                   in the title for this section. Default false.
	 *         @type bool   $private     Optional. If set to `true`, the section and all associated fields
	 *                                   will be excluded from the copied data. Default false.
	 *         @type array  $fields {
	 *             Required. An associative array containing the fields to be displayed in the section,
	 *             keyed by field ID.
	 *
	 *             @type array ...$0 {
	 *                 An associative array containing the data to be displayed for the field.
	 *
	 *                 @type string $label    Required. The label for this piece of information.
	 *                 @type mixed  $value    Required. The output that is displayed for this field.
	 *                                        Text should be translated. Can be an associative array
	 *                                        that is displayed as name/value pairs.
	 *                                        Accepted types: `string|int|float|(string|int|float)[]`.
	 *                 @type string $debug    Optional. The output that is used for this field when
	 *                                        the user copies the data. It should be more concise and
	 *                                        not translated. If not set, the content of `$value`
	 *                                        is used. Note that the array keys are used as labels
	 *                                        for the copied data.
	 *                 @type bool   $private  Optional. If set to `true`, the field will be excluded
	 *                                        from the copied data, allowing you to show, for example,
	 *                                        API keys here. Default false.
	 *             }
	 *         }
	 *     }
	 * }
	 * @return array The modified debug information.
	 */
	public static function add_debug_info( $info ) {

		$info['troy-client-plugins'] = [
			'label'       => \__( 'Troy Client &mdash; Dependent Plugins', 'troy-client' ),
			'description' => \__( 'The following plugins rely on Troy Client.', 'troy-client' ),
			'fields'      => static::get_enabled_plugins_list(),
			'show_count'  => true,
		];

		$info['troy-client-communications'] = [
			'label'       => \__( 'Troy Client &mdash; Communications', 'troy-client' ),
			'description' => \__( 'Shows whether Troy Client can communicate with external resources for updates and dependencies.', 'troy-client' ),
			'fields'      => static::get_external_blocking_list(),
		];

		return $info;
	}

	/**
	 * Returns a list of plugins that rely on Troy for updates.
	 *
	 * @since 0.0.1184
	 *
	 * @return string The list of plugins that rely on Troy for updates.
	 */
	private static function get_enabled_plugins_list() {

		$fields = [];
		$i18n   = [
			/* translators: %s: The repository URL. */
			'update_repository'       => \__( 'Update repository: %s', 'troy-client' ),
			/* translators: %s: The repository URL. */
			'update_repository_debug' => \__( 'update repository %s', 'troy-client' ),
			/* translators: %l: A list of dependencies. */
			'with_dependencies'       => \__( 'With dependencies %l', 'troy-client' ),
			/* translators: %l: A list of dependencies. */
			'with_dependencies_debug' => \__( 'with dependencies %l', 'troy-client' ),
			/* translators: %1$s: The dependency repository URL, %2$s: The dependency plugin slug. */
			'dependency_slug_url'     => \__( '%1$s (slug: %2$s)', 'troy-client' ),
			/* translators: %1$s: The plugin name, %2$s: The plugin slug. */
			'plugin_name_slug'        => \__( '%1$s (slug: %2$s)', 'troy-client' ),
		];

		$dependencies = get_troy_plugin_dependencies();

		foreach ( get_troy_plugins() as $file => $plugin ) {

			$repo = $plugin['repo']
				? make_fully_qualified_repo_url( $plugin['repo'] )
				: \__( 'Not set', 'troy-client' );

			$fields[ $file ] = [
				'label' => \sprintf( $i18n['plugin_name_slug'], $plugin['name'], $plugin['slug'] ),
				'value' => \sprintf( $i18n['update_repository'], $repo ),
				'debug' => \sprintf( $i18n['update_repository_debug'], $repo ),
			];

			if ( isset( $dependencies[ $file ]['dependencies'] ) ) {
				$deps = array_map(
					fn( $dep ) => \sprintf(
						$i18n['dependency_slug_url'],
						make_fully_qualified_repo_url( $dep['repo'] ),
						$dep['slug'],
					),
					$dependencies[ $file ]['dependencies'],
				);

				$fields[ $file ]['value'] .= ' | ' . \wp_sprintf( $i18n['with_dependencies'], $deps );
				$fields[ $file ]['debug'] .= ', ' . \wp_sprintf( $i18n['with_dependencies_debug'], $deps );
			}
		}

		return $fields;
	}

	/**
	 * Returns a list of plugins that block Troy from accessing external resources.
	 *
	 * @since 0.0.1184
	 *
	 * @return string The list of plugins that block Troy from accessing external resources.
	 */
	private static function get_external_blocking_list() {

		if ( empty( get_troy_plugin_repos_per_slug() ) ) {
			// This should never happen, because this very plugin is dependent on Troy.
			// But perhaps this can be used for debugging.
			$message = \__( 'No plugins indicate support for Troy.', 'troy-client' );

			return [
				'no-troy-repos' => [
					'label' => \__( 'No Troy repositories found.', 'troy-client' ),
					'value' => $message,
					'debug' => $message,
				],
			];
		}

		$fields = [];
		$i18n   = [
			/* translators: %l: The plugin slug. */
			'communication_for_slug'  => \__( 'Communication for plugin slug: %s', 'troy-client' ),
			/* translators: %l: A list of plugin slugs. */
			'communication_for_slugs' => \__( 'Communication for plugin slugs: %l', 'troy-client' ),
			/* translators: %s: The repository URL. */
			'repo_is_reachable'       => \__( '%s is reachable', 'troy-client' ),
			/* translators: 1: The repository URL, 2: The error message. */
			'unable_to_reach'         => \__( 'Unable to reach %1$s: %2$s', 'troy-client' ),
		];

		$fields = [];

		foreach ( get_troy_plugin_slugs_per_repo() as $repo => $slugs ) {
			// WordPress features requiring processing.
			$ping = make_troy_api_request( "{$repo}ping/", '', 'GET' );

			$label = 1 === \count( $slugs )
				? \sprintf( $i18n['communication_for_slug'], $slugs[0] )
				: \wp_sprintf( $i18n['communication_for_slugs'], $slugs );

			if ( \is_wp_error( $ping ) ) {
				$error_message = $ping->get_error_message();

				$fields[ $repo ] = [
					'label' => $label,
					'value' => \sprintf( $i18n['unable_to_reach'], $repo, $error_message ),
					'debug' => "$error_message",
				];
			} else {
				$fields[ $repo ] = [
					'label' => $label,
					'value' => \sprintf( $i18n['repo_is_reachable'], $repo ),
					'debug' => true,
				];
			}
		}

		return $fields;
	}
}
