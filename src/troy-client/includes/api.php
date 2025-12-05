<?php
/**
 * @package Troy\Client
 * @access  public
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
 * Converts a plugin file to a slug.
 *
 * @since 0.0.1184
 *
 * @param string $plugin_file The plugin file relative to the plugins directory.
 * @return string The plugin's slug.
 */
function get_plugin_slug( $plugin_file ) {
	return strtolower(
		str_contains( $plugin_file, '/' )
			? \dirname( $plugin_file )
			: str_replace( '.php', '', $plugin_file ),
	);
}

/**
 * Returns the site's unique ID.
 * This is used to keep track of usage without tracking domains.
 *
 * @since 0.0.1184
 *
 * @return string The site's unique ID.
 */
function get_site_unique_id() {

	static $uuid;

	if ( isset( $uuid ) )
		return $uuid;

	$uuid = \get_option( 'troy_client_site_unique_id', '' );

	// This is the timeout for the UUID: 1 week. int-casting floors.
	$epoch = (int) ( time() / \WEEK_IN_SECONDS );

	if ( ! $uuid || (int) strtok( $uuid, '-' ) < $epoch ) {
		// We don't use an rfc9562 UUID because we need to be able to extract a custom epoch.
		$uuid = "$epoch-" . bin2hex( random_bytes( 32 ) );

		// Not autoloaded -- we only use if when we make a request.
		\update_option( 'troy_client_site_unique_id', $uuid, false );
	}

	return $uuid;
}

/**
 * Returns a memoized list of all Troy dependencies with their slugs and repositories.
 *
 * A dependency may be annotated as follows (example plugin headers):
 * - Troy Dependency: plugin-slug
 *   _This gets the dependency from the plugin's "Troy: " header._
 * - Troy Dependency: plugin-slug <repo-uri>
 *   _This gets the dependency from the <repo-uri> (<angle brackets> are required)_
 * - Troy Dependencies: plugin-slug <repo-uri>, second-plugin-slug
 *   _This lists multiple dependencies, where the second gets it from the plugin's "Troy:" header._
 *
 * The Troy and Troy Dependencies headers may not exceed 191 characters.
 * This is to improve Troy Server indexing performance.
 * The character limit is not enforced here, but on the server.
 *
 * There may not be more than 5 dependencies per plugin; this part is enforced here.
 *
 * We assume the Troy and Troy Dependency registrations are correctly formed.
 * We do not validate them here. Use `get_troy_plugin_repos_per_slug()` instead.
 *
 * @since 0.0.1184
 *
 * @return array[] {
 *     An array of dependencies, associative, keyed by the plugin file (a.k.a. basename).
 *
 *     @type string $slug         The plugin's slug.
 *     @type string $repo         The plugin's Troy header.
 *     @type array  $dependencies {
 *         An array of dependencies of the plugin, associative.
 *
 *         @type string $slug The dependency's slug.
 *         @type string $repo The dependency's repository.
 *     }
 * }
 */
function get_troy_plugin_dependencies() {

	static $dependencies;

	if ( isset( $dependencies ) )
		return $dependencies;

	$plugins      = get_troy_plugins();
	$dependencies = [];

	foreach ( $plugins as $file => $plugin ) {
		// Skip dependencies if the plugin repo is disable-all-communications.
		if ( empty( $plugin['dependencies'] ) || 'disable-all-communications' === $plugin['repo'] )
			continue;

		$dependencies[ $file ] = [
			'slug'         => $plugin['slug'],
			'dependencies' => array_filter( array_map(
				function ( $dependency ) use ( $plugin ) {
					[ $slug, $repo ] = array_pad(
						explode( '<', $dependency ),
						2,
						$plugin['repo'] ?? null, // fill with plugin's default Troy repository
					);

					if ( ! $repo ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions -- This should be resolved by the developer before it reaches production.
						trigger_error( \esc_html( "Troy Dependency '$slug' is missing a repository URL in '{$plugin['slug']}'" ), \E_USER_WARNING );
						return null;
					}

					return [
						'slug' => trim( $slug ),
						'repo' => trim( $repo, ' >' ),
					];
				},
				\array_slice( explode( ',', $plugin['dependencies'] ), 0, 5 ), // Limit to 5 dependencies per plugin.
			) ),
		];
	}

	return $dependencies;
}

/**
 * Returns a list of all Troy-supported repos with their plugin slugs.
 *
 * @since 0.0.1184
 *
 * @return array[] An array of supported slugs, associative, keyed by the repository.
 */
function get_troy_plugin_slugs_per_repo() {

	$repos = get_troy_plugin_repos_per_slug();
	$slugs = [];

	foreach ( $repos as $slug => $repo )
		$slugs[ $repo ][] = $slug;

	return $slugs;
}

/**
 * Returns a memoized and sanitized list of all Troy-supported plugins with their endpoints.
 *
 * "Troy:" repository headers will supercede "Troy Dependencies:" if a plugin is also a dependency.
 *
 * When a Troy plugin registers Troy Dependencies, and one of the dependency slug exists,
 * the slug will be redirected to the registered dependency repository.
 *
 * @since 0.0.1184
 *
 * @return string[] An array of filtered plugin repos, associative, keyed by the plugin slug.
 *                  The reason we use a slug is because we also list dependencies, about which
 *                  we don't know the plugin file.
 */
function get_troy_plugin_repos_per_slug() {

	static $repos;

	if ( isset( $repos ) )
		return $repos;

	$repos = [];

	foreach ( get_troy_plugins() as $plugin ) {
		if ( ! $plugin['repo'] ) continue;
		$repos[ $plugin['slug'] ] = $plugin['repo'];
	}

	foreach ( get_troy_plugin_dependencies() as $plugin )
		foreach ( $plugin['dependencies'] as $dependency )
			$repos[ $dependency['slug'] ] ??= $dependency['repo']; // Only override if not already set.

	return $repos = array_map(
		fn( $r ) => 'disable-all-communications' === $r
			? 'disable-all-communications'
			: make_fully_qualified_repo_url( $r ),
		$repos,
	);
}

/**
 * Returns a memoized list of all Troy-supported plugins with their Troy headers.
 * This includes plugins that only list Troy Dependencies without a Troy header.
 *
 * @since 0.0.1184
 *
 * @return array[] {
 *     An array of supported plugins, associative, keyed by the plugin file (a.k.a. basename).
 *
 *     @type string $slug         The plugin's slug.
 *     @type string $version      The plugin's version.
 *     @type string $textdomain   The plugin's textdomain.
 *     @type string $name         The plugin's name.
 *     @type string $repo         The plugin's Troy repository header value. Unfiltered.
 *     @type string $dependencies The plugin's Troy dependencies header value. Unfiltered.
 *                                See `get_troy_plugin_dependencies()` for the parsed dependencies.
 * }
 */
function get_troy_plugins() {

	static $plugins;

	if ( isset( $plugins ) )
		return $plugins;

	if ( ! \function_exists( 'get_plugins' ) )
		require_once \ABSPATH . 'wp-admin/includes/plugin.php';

	/**
	 * Invalidate the plugins cache if it was populated before we registered
	 * extra_plugin_headers, so WordPress re-reads plugin files with our headers.
	 */
	if ( ! Headers::is_filtered() )
		\wp_cache_delete( 'plugins', 'plugins' );

	$plugins = [];

	foreach ( \get_plugins() as $file => $data ) {

		$slug = get_plugin_slug( $file );

		// Troy cannot support root plugins.
		if ( '.' === $slug )
			continue;

		// Reset.
		$repo = $dependencies = null;

		foreach ( TROY_PLUGIN_HEADERS['repo'] as $header ) {
			if ( $data[ $header ] ) {
				$repo = $data[ $header ];
				break;
			}
		}

		/**
		 * Although we do not need the dependencies every request, we might as
		 * well grab them here. Parsing the dependencies is done later when requested.
		 * See `get_troy_plugin_dependencies()`.
		 */
		foreach ( TROY_PLUGIN_HEADERS['dependencies'] as $header ) {
			if ( $data[ $header ] ) {
				$dependencies = $data[ $header ];
				// The first supported dependency header found is used: break.
				break;
			}
		}

		// If nothing is registered, then the plugin does not support Troy.
		if ( ! $repo && ! $dependencies )
			continue;

		$plugins[ $file ] = [
			'slug'         => $slug,
			'name'         => $data['Name'],
			'version'      => $data['Version'],
			'textdomain'   => $data['TextDomain'], // falls back to slug automatically
			'repo'         => $repo,
			'dependencies' => $dependencies,
		];
	}

	return $plugins;
}

/**
 * Returns whether the site has Troy-supported plugins, sans itself.
 *
 * @since 0.0.1184
 *
 * @return bool Whether the site has Troy-supported plugins.
 */
function has_troy_plugins() {

	$plugins = get_troy_plugins();

	unset( $plugins[ PLUGIN_BASENAME ] );

	return (bool) $plugins;
}

/**
 * Makes a repository URL fully qualified.
 *
 * A repository URI may be formatted as following:
 * - (subdomain.)?(domain)?(:port)?(/path/to/repo)?(/)?
 *
 * Examples:
 * - example.com
 * - sub.example.com/
 * - sub.sub.example.com/repo/path
 * - localhost/repo
 * - 85.10.155.47/repo/
 * - [2a01:7c8:bb0b:34d:0:b00b:69:47]:443/path/to/repo
 *
 * Schemes are supported in the URI but they're replaced. So don't bother, it's fluff.
 *
 * Do not use uppercase letters in the URI unless absolutely necessary.
 * They won't be collapsed with different cases from different plugins, leading to unneccessary requests.
 *
 * All URIs will be slashed. Troy Servers must enable trailing slashes for consistency and improved performance.
 * All URIs will be converted to HTTPS only. Troy Servers must have a valid TLS certificate.
 * All URIs should only be the Troy Server base interfacing endpoint. Troy Client will append necessary paths.
 *
 * E.g., `example.com/repo` for plugin slug `troy-client` will resolve to `https://example.com/repo/plugin/troy-client/`.
 *
 * @since 0.0.1184
 *
 * @param string $repo The repository URL to make fully qualified.
 * @return string The fully qualified repository URL.
 */
function make_fully_qualified_repo_url( $repo ) {
	return \sanitize_url(
		preg_replace(
			'/^(?:\w*:)?(?:\/\/)?(.*?)$/',
			'https://$1/',
			trim( $repo, ' \\/' ),
		),
		[ 'https' ],
	);
}

/**
 * Makes a qualified Troy API call to a Troy Server.
 *
 * @since 0.0.1184
 *
 * @param string       $repo   The Troy Server endpoint.
 * @param array|string $body   The request body. Use array to send as JSON, string for raw body content.
 * @param string       $method The request method. Accepts 'GET', 'POST'. Default 'POST'.
 * @return array|\WP_Error Array containing 'headers', 'body', 'response', 'cookies', and/or 'filename'.
 *                         A \WP_Error instance upon error.
 */
function make_troy_api_request( $repo, $body = '', $method = 'POST' ) {

	if ( \is_array( $body ) ) {
		$body         = json_encode( $body );
		$content_type = 'application/json';
	}

	return \wp_remote_request(
		$repo,
		[
			'method'      => $method,
			'headers'     => [
				'Content-Type'     => $content_type ?? 'text/plain',
				'charset'          => 'UTF-8',
				'X-Troy-Client-Id' => get_site_unique_id(),
				'User-Agent'       => 'Troy Client/' . VERSION, // See WP_Http_Curl::request()
			],
			'user-agent'  => 'Troy Client/' . VERSION, // See WP_Http::request()
			'body'        => $body,
			'timeout'     => 7,
			'redirection' => 2, // Stringently few. We actually aim for 0, but can imagine a new server URL.
		],
	);
}

/**
 * Makes a qualified Troy API call to a Troy Server, with caching.
 *
 * The cache expires every 10 minutes.
 * It is reset when it's expired or containing 20 significant plugins in length.
 * This is to prevent the cache from growing too large.
 *
 * Errors are not cached long-term, but are memoized to block rapid re-requests.
 *
 * @since 0.0.1184
 *
 * @param string       $key    The cache key. Underscore-prefix is reserved for internal use.
 * @param string       $repo   The Troy Server endpoint.
 * @param array|string $body   The request body. Use array to send as JSON, string for raw body content.
 * @param string       $method The request method. Accepts 'GET', 'POST'. Default 'POST'.
 * @return array|\WP_Error Array containing 'headers', 'body', 'response', 'cookies', and/or 'filename'.
 *                         A \WP_Error instance upon error.
 */
function make_troy_api_request_cached( $key, $repo, $body = '', $method = 'POST' ) {

	static $memo;

	if ( isset( $memo[ $key ] ) )
		return $memo[ $key ];

	if ( ! isset( $memo ) ) {
		$memo = \get_site_option( 'troy_client_api_request_cache' ) ?: [];

		if (
			   empty( $memo['_timeout'] )
			|| $memo['_timeout'] < time()
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- we're not going to unserialize.
			|| \strlen( serialize( $memo ) ) > 333_000 // ~ holds 20 plugins
		) {
			$memo = [
				'_timeout' => time() + max(
					30, // Hardcoded 30 seconds minimum
					\defined( 'Troy\Client\API_TIMEOUT' )
						? API_TIMEOUT
						: 600, // 10 minutes default.
				),
			];
		} elseif ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}
	}

	static $err_memo = [];

	if ( isset( $err_memo[ $key ] ) )
		return $err_memo[ $key ];

	$res = make_troy_api_request( $repo, $body, $method );

	// WordPress's API will resolve redirects until no more redirects are available,
	// but we limit this to 2 redirects. So, when we hit another, we assume the request failed.
	if ( \is_wp_error( $res ) || \wp_remote_retrieve_response_code( $res ) >= 300 )
		return $err_memo[ $key ] = $res;

	$memo[ $key ] = $res;

	\update_site_option( 'troy_client_api_request_cache', $memo );

	return $res;
}

/**
 * Returns whether the site should recheck dependencies.
 *
 * @since 0.0.1184
 *
 * @param ?string $recheck 'yes' or 'no'. Leave null to check the current value.
 *                         If set, the value will be set in the database.
 *                         This is used to toggle the recheck of dependencies.
 * @return bool Whether the site should recheck dependencies.
 */
function recheck_dependencies( $recheck = null ) {

	if ( null === $recheck ) {
		$recheck = (
			\is_multisite()
				? \get_site_option( 'troy_client_recheck_dependencies', 'yes' )
				: \get_option( 'troy_client_recheck_dependencies', 'yes' )
		);
	} else {
		\is_multisite()
			? \update_site_option( 'troy_client_recheck_dependencies', $recheck )
			: \update_option( 'troy_client_recheck_dependencies', $recheck );
	}

	return 'yes' === $recheck;
}
