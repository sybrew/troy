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
 * Class Troy\Client\PluginsAPI.
 *
 * @since 0.0.1184
 */
final class PluginsAPI {

	/**
	 * Cleans up the cache after an upgrade.
	 *
	 * @hook upgrader_process_complete 10
	 * @since 0.0.1184
	 */
	public static function cleanup_after_upgrade() {
		// Clear the cache on any plugin/theme/core update.
		\update_site_option( 'troy_client_api_request_cache', [] );
	}

	/**
	 * Modifies the plugin API to bind to Troy's embedded services.
	 *
	 * The transient is prepared by WordPress to prevent multiple requests, so
	 * this method gets called twice. But the second call may not happen when the
	 * WordPress plugin API is blocked. So, we will populate the transient values
	 * in the first call, so that Troy can still provide updates.
	 * When the second call happens, the first call's values are gone, so we simply
	 * repopulate them again.
	 *
	 * @hook pre_set_site_transient_update_plugins 10
	 * @since 0.0.1184
	 *
	 * @param object $value The site transient update_plugins.
	 * @return object The filtered site transient update_plugins.
	 */
	public static function filter_update_plugins_transient( $value ) {

		// $value is broken by some plugin. We can't fix this. Bail.
		if ( ! \is_object( $value ) )
			return $value;

		// The filter may be invoked before these are set. We can safely prepopulate them.
		$value->checked      ??= [];
		$value->no_update    ??= [];
		$value->response     ??= [];
		$value->translations ??= [];

		$troy_plugins = get_troy_plugins();

		// Clear Troy plugins from value before applying memoized results, which
		// may not contain the all plugin data due to failed requests.
		// Use the plugin filenames (keys of $troy_plugins) to clear from WordPress transients
		$value->checked   = array_diff_key( $value->checked, $troy_plugins );
		$value->response  = array_diff_key( $value->response, $troy_plugins );
		$value->no_update = array_diff_key( $value->no_update, $troy_plugins );

		static $memo;

		if ( isset( $memo ) )
			goto apply_memo;

		// Initialize the updates object for first-time use
		$memo = (object) [
			'no_update'    => [],
			'translations' => [],
			'update'       => [],
		];

		$troy_plugins_slugs   = array_column( $troy_plugins, 'slug' );
		$troy_plugins_by_slug = array_combine( $troy_plugins_slugs, $troy_plugins );
		$active_slugs_keyed   = array_flip( array_map(
			'Troy\Client\get_plugin_slug',
			(array) \get_option( 'active_plugins' ),
		) );

		// Create a mapping from slug to plugin filename for Troy plugins
		$filename_by_slug = array_combine( $troy_plugins_slugs, array_keys( $troy_plugins ) );

		$translations = \wp_get_installed_translations( 'plugins' );

		$locales = array_unique(
			/**
			 * Filters the locales requested for plugin translations.
			 *
			 * This is taken from WordPress Core.
			 *
			 * @since WP 3.7.0
			 * @since WP 4.5.0 The default value of the `$locales` parameter changed to include all locales.
			 * @param string[] $locales Plugin locales. Default is all available locales of the site.
			 */
			\apply_filters(
				'plugins_update_check_locales',
				array_values( \get_available_languages() ),
			),
		);

		// Include an unmodified $wp_version
		include \ABSPATH . \WPINC . '/version.php';

		$php_version = phpversion();

		// Privacy by design: do not share plugin data from other repos.
		foreach ( get_troy_plugin_slugs_per_repo() as $repo => $slugs ) {
			$active_plugins_data   = [];
			$inactive_plugins_data = [];
			$repo_translations     = [];

			// Skip communications for disabled repos.
			if ( 'disable-all-communications' === $repo )
				continue;

			foreach ( $slugs as $slug ) {
				// The slug might be of a dependency that isn't a Troy plugin or isn't a installed -- either way, don't leak.
				if ( ! isset( $troy_plugins_by_slug[ $slug ] ) )
					continue;

				$plugin_data = $troy_plugins_by_slug[ $slug ];
				$textdomain  = $plugin_data['textdomain'];

				if ( isset( $active_slugs_keyed[ $slug ] ) ) {
					$active_plugins_data[ $slug ] = $plugin_data['version'];
				} else {
					$inactive_plugins_data[ $slug ] = $plugin_data['version'];
				}

				$repo_translations[ $textdomain ] = $translations[ $textdomain ] ?? [];
			}

			// Skip: no Troy plugins are installed from this repo. This can only happen for unavailable dependencies.
			if ( empty( $active_plugins_data ) && empty( $inactive_plugins_data ) )
				continue;

			$request = make_troy_api_request_cached(
				"update_plugins-$repo",
				"{$repo}plugin/get/updates/",
				[
					'active_plugins'   => $active_plugins_data,
					'inactive_plugins' => $inactive_plugins_data,
					'locales'          => $locales,
					'translations'     => $repo_translations,
					'php_version'      => $php_version,
					'wp_version'       => $wp_version, // phpcs:ignore VariableAnalysis.CodeAnalysis -- included above.
					'troy_version'     => VERSION,
				],
			);

			// Skip failed requests. It won't fall back to WordPress's API.
			if ( \is_wp_error( $request ) )
				continue;

			$res = json_decode( \wp_remote_retrieve_body( $request ), true );

			if ( \is_array( $res ) ) {
				// Object casting is required in order to match the info/1.0 format.
				$res = (object) $res;
			} elseif ( ! \is_object( $res ) || isset( $res->error ) ) {
				continue;
			}

			$memo->translations[] = (array) ( $res->translations ?? [] );

			foreach ( [ 'no_update', 'update' ] as $key ) {
				foreach ( $res->$key ?? [] as $slug => $plugin_data ) {
					if ( empty( $filename_by_slug[ $slug ] ) )
						continue;

					$memo->{$key}[ $filename_by_slug[ $slug ] ] = (object) $plugin_data;
				}
			}
		}

		apply_memo:
			foreach ( [
				'no_update'    => 'no_update',
				'translations' => 'translations',
				'update'       => 'response', // WP uses 'response', probably backwards compatibility.
			] as $key => $wp_key )
				$value->$wp_key = array_merge( $value->$wp_key, $memo->$key );

		return $value;
	}

	/**
	 * Filters the plugin API to bind to Troy's embedded services.
	 *
	 * @hook plugins_api PHP_INT_MAX 3
	 * @since 0.0.1184
	 *
	 * @param false|object|array $res    The result object or array. Default false.
	 * @param string             $action API action to perform: 'query_plugins', 'plugin_information',
	 *                                   'hot_tags' or 'hot_categories'.
	 * @param array|object       $args   Array or object of arguments to serialize for the Plugin Info API.
	 *                                   See {@see plugins_api()} for accepted arguments.
	 * @return false|object|array The result object on success. On failure, it'll be an empty object.
	 *                            If we return a WP_Error object, then WordPress will assume it's from their API.
	 *                            Default false (no action taken).
	 */
	public static function filter_plugins_api( $res, $action, $args ) {

		if ( 'plugin_information' === $action ) {
			if ( isset( $args->slug ) ) {
				$res = isset( get_troy_plugin_repos_per_slug()[ $args->slug ] )
					? static::get_plugin_information(
						$args->slug,
						$args->locale,
						$args->fields ?? [],
					)
					: $res;
			}
		}

		return $res;
	}

	/**
	 * Filters the plugin API to bind to The SEO Framework's own updater service.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug   The plugin slug.
	 * @param string $locale The locale.
	 * @param array  $fields {
	 *     Array of fields to include or exclude in the response. If not set, get all fields.
	 *
	 *     @type bool $short_description Include the plugin short description.
	 *     @type bool $description       Include the full plugin description.
	 *     @type bool $sections          Include the plugin readme sections: description, installation, FAQ, screenshots,
	 *                                   other notes, and changelog.
	 *     @type bool $tested            Include the 'Compatible up to' value.
	 *     @type bool $requires          Include the required WordPress version.
	 *     @type bool $requires_php      Include the required PHP version.
	 *     @type bool $rating            Include the rating in percent and total number of ratings.
	 *     @type bool $ratings           Include the number of ratings for each star (1-5).
	 *     @type bool $downloaded        Include the download count.
	 *     @type bool $downloadlink      Include the download link for the package.
	 *     @type bool $last_updated      Include the date of the last update.
	 *     @type bool $added             Include the date when the plugin was added to the WordPress.org repository.
	 *     @type bool $tags              Include the assigned tags.
	 *     @type bool $compatibility     Include the WordPress compatibility list.
	 *     @type bool $homepage          Include the plugin homepage link.
	 *     @type bool $versions          Include the list of all available versions.
	 *     @type bool $donate_link       Include the donation link.
	 *     @type bool $reviews           Include the plugin reviews.
	 *     @type bool $banners           Include the banner images links.
	 *     @type bool $icons             Include the icon links.
	 *     @type bool $active_installs   Include the number of active installations.
	 *     @type bool $group             Include the assigned group.
	 *     @type bool $contributors      Include the list of contributors.
	 *     @type bool $language_packs    Include language packs.
	 * }
	 * @return object The result object on success, and empty object on failure.
	 */
	private static function get_plugin_information( $slug, $locale, $fields ) {

		$plugin_name = $slug;

		foreach ( get_troy_plugins() as $plugin ) {
			if ( $plugin['slug'] === $slug ) {
				$plugin_name = $plugin['name'];
				break;
			}
		}

		if ( ! \wp_http_supports( [ 'ssl' ] ) )
			return new \WP_Error(
				'plugins_api_failed',
				\sprintf(
					/* translators: %s = plugin name */
					\__( 'This website does not support secure connections. This means "%s" can not be updated.', 'troy-client' ),
					$plugin_name,
				),
			);

		$repo = get_troy_plugin_repos_per_slug()[ $slug ];

		if ( 'disable-all-communications' === $repo )
			return new \WP_Error(
				'plugins_api_failed',
				\sprintf(
					/* translators: %s = plugin name */
					\__( 'This plugin ("%s") is set to disable all communications, including updates.', 'troy-client' ),
					$plugin_name,
				),
			);

		$request = make_troy_api_request_cached(
			"get_plugin_information-$repo-$slug",
			"{$repo}plugin/get/info/",
			[
				'fields' => $fields,
				'slug'   => $slug,
				'screen' => 'thickbox',
			],
		);

		if ( \is_wp_error( $request ) ) {
			$res = new \WP_Error(
				'plugins_api_failed',
				\sprintf(
					/* translators: %s: repo API URL */
					\__( 'An unexpected error occurred. Something may be wrong with %s or this server&#8217;s configuration.', 'troy-client' ),
					$repo,
				),
				$request->get_error_message(),
			);
		} else {
			// TODO this can fail and then the WP_Error doesn't do much useful.
			$res = json_decode( \wp_remote_retrieve_body( $request ), true );

			if ( empty( $res ) ) {
				$res = new \WP_Error(
					'plugins_api_failed',
					\sprintf(
						/* translators: %s: repo API URL */
						\__( 'An unexpected error occurred. Something may be wrong with %s or this server&#8217;s configuration.', 'troy-client' ),
						$repo,
					),
					\wp_remote_retrieve_body( $request ),
				);
			} else {
				$res = (object) $res;
			}

			if ( isset( $res->error ) )
				$res = new \WP_Error( 'plugins_api_failed', $res->error );
		}

		return $res;
	}

	/**
	 * Conditionally filters the WordPress.org Plugin Installation API arguments.
	 *
	 * In this, we filter out Troy plugins from the API requests, so that WordPress's API doesn't hijack them.
	 * We can always provide installed plugins later.
	 *
	 * @hook plugins_api_args PHP_INT_MAX 1
	 * @since 0.0.1184
	 *
	 * @param object $args Plugin API arguments.
	 * @return object The filtered arguments.
	 */
	public static function filter_plugins_api_args( $args ) {

		// Only available on $action 'query_plugins' (tab 'recommended'), but this isset check is fastest.
		if ( isset( $args->installed_plugins ) ) {
			$args->installed_plugins = array_diff(
				$args->installed_plugins,
				// We don't use get_troy_plugin_repos_per_slug() because we need only to filter installed plugins.
				array_column( get_troy_plugins(), 'slug' ),
			);
		}

		return $args;
	}
}
