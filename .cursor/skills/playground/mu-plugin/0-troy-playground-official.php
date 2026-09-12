<?php
/**
 * @package Troy\Playground
 */

defined( 'ABSPATH' ) or die;

/**
 * Troy
 *
 * Copyright (c) 2026 Sybre Waaijer, CyberWire B.V.
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
 * @hook pre_http_request 0
 */
add_filter(
	'pre_http_request',
	'troy_playground_official_request',
	0,
	3,
);

/**
 * Intercepts repo.deploytroy.org. Never falls through to the network.
 *
 * Faux bodies match Troy Server's public API as it stands. Maintain this
 * file when that API changes. Swallow UUID and stats from the request body.
 *
 * @hook pre_http_request 0
 * @since 1.8.1184
 *
 * @param false|array|WP_Error $preempt     A preemptive return value.
 * @param array                $parsed_args HTTP request arguments.
 * @param string               $url         The request URL.
 * @return array|false|WP_Error
 */
function troy_playground_official_request( $preempt, $parsed_args, $url ) {

	$host = parse_url( $url, PHP_URL_HOST );

	if ( 'repo.deploytroy.org' !== strtolower( (string) $host ) )
		return $preempt;

	$path   = trim(
		(string) parse_url( $url, PHP_URL_PATH ),
		'/',
	);
	$method = strtoupper(
		(string) ( $parsed_args['method'] ?? 'GET' ),
	);
	$body   = troy_playground_official_body( $parsed_args['body'] ?? '' );

	if ( 'OPTIONS' === $method )
		return troy_playground_official_http( '', 204 );

	if ( 'ping' === $path ) {
		if ( 'GET' !== $method )
			return troy_playground_official_error( 'Method not allowed', 405 );

		return troy_playground_official_json( [
			'status'  => 'ok',
			'message' => 'pong',
			'time'    => current_time( 'mysql', true ),
		] );
	}

	if ( 'plugin/get/updates' === $path ) {
		if ( 'POST' !== $method )
			return troy_playground_official_error( 'Method not allowed', 405 );

		if ( ! is_array( $body ) )
			return troy_playground_official_error( 'Invalid JSON input', 400 );

		return troy_playground_official_updates( $body );
	}

	if ( 'plugin/get/info' === $path ) {
		if ( 'POST' !== $method )
			return troy_playground_official_error( 'Method not allowed', 405 );

		if ( ! is_array( $body ) )
			return troy_playground_official_error( 'Invalid JSON input', 400 );

		return troy_playground_official_info( $body );
	}

	if ( 'plugin/get/stats' === $path ) {
		if ( 'POST' !== $method )
			return troy_playground_official_error( 'Method not allowed', 405 );

		if ( ! is_array( $body ) )
			return troy_playground_official_error( 'Invalid JSON input', 400 );

		return troy_playground_official_stats_many( $body );
	}

	if ( str_starts_with( $path, 'plugin/get/stats/' ) ) {
		if ( 'GET' !== $method )
			return troy_playground_official_error( 'Method not allowed', 405 );

		$parts = array_values( array_filter( explode( '/', $path ) ) );

		if ( count( $parts ) < 4 )
			return troy_playground_official_error( 'Invalid slug', 400 );

		return troy_playground_official_stats_one( $parts[3] );
	}

	if ( str_starts_with( $path, 'plugin/get/tags/' ) ) {
		if ( 'GET' !== $method )
			return troy_playground_official_error( 'Method not allowed', 405 );

		$parts = array_values( array_filter( explode( '/', $path ) ) );

		if ( count( $parts ) < 4 )
			return troy_playground_official_error( 'Invalid slug', 400 );

		return troy_playground_official_tags( $parts[3] );
	}

	if ( str_starts_with( $path, 'plugin/get/zip/' ) ) {
		if ( 'GET' !== $method )
			return troy_playground_official_error( 'Method not allowed', 405 );

		$parts = array_values( array_filter( explode( '/', $path ) ) );

		if ( count( $parts ) < 4 )
			return troy_playground_official_error( 'Plugin not found', 404 );

		return troy_playground_official_zip( $parts[3], $parsed_args );
	}

	if (
		   str_starts_with( $path, 'package/get/zip/' )
		|| str_starts_with( $path, 'installer/get/zip/' )
		|| str_starts_with( $path, 'composer/get/' )
	) {
		return troy_playground_official_error( 'Plugin not found', 404 );
	}

	return troy_playground_official_error( 'Plugin not found', 404 );
}

/**
 * Decodes a request body. Arrays stay arrays. Invalid JSON becomes null.
 *
 * @since 1.8.1184
 *
 * @param mixed $body Request body.
 * @return array|string|null
 */
function troy_playground_official_body( $body ) {

	if ( is_array( $body ) )
		return $body;

	if ( ! is_string( $body ) || '' === $body )
		return $body;

	$decoded = json_decode( $body, true );

	return is_array( $decoded ) ? $decoded : null;
}

/**
 * Returns a faux HTTP response.
 *
 * @since 1.8.1184
 *
 * @param string $body    Response body.
 * @param int    $status  HTTP status.
 * @param array  $headers Extra headers.
 * @return array
 */
function troy_playground_official_http( $body, $status = 200, $headers = [] ) {
	return [
		'headers'  => $headers,
		'body'     => $body,
		'response' => [
			'code'    => $status,
			'message' => 200 === $status ? 'OK' : 'Error',
		],
		'cookies'  => [],
	];
}

/**
 * Returns a faux JSON response.
 *
 * @since 1.8.1184
 *
 * @param mixed $data   Payload.
 * @param int   $status HTTP status.
 * @return array
 */
function troy_playground_official_json( $data, $status = 200 ) {
	return troy_playground_official_http(
		json_encode( $data, JSON_UNESCAPED_SLASHES ),
		$status,
		[ 'content-type' => 'application/json; charset=utf-8' ],
	);
}

/**
 * Returns a faux JSON error.
 *
 * @since 1.8.1184
 *
 * @param string $message Error message.
 * @param int    $status  HTTP status.
 * @return array
 */
function troy_playground_official_error( $message, $status = 400 ) {
	return troy_playground_official_json(
		[ 'error' => $message ],
		$status,
	);
}

/**
 * Loads plugin.php when get_plugins() is not available.
 *
 * @since 1.8.1184
 */
function troy_playground_official_load_plugin_php() {

	if ( function_exists( 'get_plugins' ) ) return;

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/**
 * Loads file.php when wp_tempnam() is not available.
 *
 * @since 1.8.1184
 */
function troy_playground_official_load_file_php() {

	if ( function_exists( 'wp_tempnam' ) ) return;

	require_once ABSPATH . 'wp-admin/includes/file.php';
}

/**
 * Resolves a plugin from the installed list or the zip-source tree.
 *
 * @since 1.8.1184
 *
 * @param string $slug Plugin slug.
 * @return array|null Plugin headers plus `_dir`.
 */
function troy_playground_official_plugin( $slug ) {

	if ( ! $slug )
		return null;

	troy_playground_official_load_plugin_php();

	foreach ( get_plugins() as $file => $data ) {
		$dir = dirname( $file );

		if (
			   $slug !== $dir
			&& $slug !== str_replace( '.php', '', $file )
		) {
			continue;
		}

		$data['_dir'] = '.' === $dir ? WP_PLUGIN_DIR : WP_PLUGIN_DIR . "/$dir";

		return $data;
	}

	$src  = WP_CONTENT_DIR . "/uploads/troy-playground-src/$slug";
	$main = "$src/$slug.php";

	if ( ! is_file( $main ) )
		return null;

	$data         = get_plugin_data( $main, false, false );
	$data['_dir'] = $src;

	return $data;
}

/**
 * Builds the default no_update / update plugin object.
 *
 * @since 1.8.1184
 *
 * @param string $slug Plugin slug.
 * @param array  $data Plugin headers.
 * @return array
 */
function troy_playground_official_update_item( $slug, $data ) {
	return [
		'id'               => 'https://repo.deploytroy.org/',
		'slug'             => $slug,
		'new_version'      => null,
		'url'              => $data['PluginURI'] ?? '',
		'package'          => '',
		'icons'            => [ '1x' => '' ],
		'banners'          => [],
		'banners_rtl'      => [],
		'requires'         => $data['RequiresWP'] ?? '',
		'tested'           => $data['TestedUpTo'] ?? '',
		'requires_php'     => $data['RequiresPHP'] ?? '',
		'requires_plugins' => [],
		'compatibility'    => [],
		'upgrade_notice'   => '',
		'autoupdate'       => false,
	];
}

/**
 * Faux POST plugin/get/updates.
 *
 * @since 1.8.1184
 *
 * @param array $body Request body.
 * @return array
 */
function troy_playground_official_updates( $body ) {

	$active   = (array) ( $body['active_plugins'] ?? [] );
	$inactive = (array) ( $body['inactive_plugins'] ?? [] );
	$response = [
		'no_update'    => [],
		'translations' => [],
		'update'       => [],
	];

	foreach ( array_merge( $active, $inactive ) as $slug => $version ) {
		unset( $version );

		if ( ! is_string( $slug ) || ! $slug ) continue;

		$plugin = troy_playground_official_plugin( $slug );

		if ( ! $plugin ) continue;

		$response['no_update'][ $slug ] = troy_playground_official_update_item( $slug, $plugin );
	}

	return troy_playground_official_json( $response );
}

/**
 * Faux POST plugin/get/info.
 *
 * @since 1.8.1184
 *
 * @param array $body Request body.
 * @return array
 */
function troy_playground_official_info( $body ) {

	$slug = (string) ( $body['slug'] ?? '' );

	if ( ! $slug )
		return troy_playground_official_error(
			'Missing required parameter: slug',
			400,
		);

	$plugin = troy_playground_official_plugin( $slug );

	if ( ! $plugin )
		return troy_playground_official_error( 'Plugin not found', 404 );

	$response = [
		'name'            => $plugin['Name'] ?? $slug,
		'slug'            => $slug,
		'version'         => $plugin['Version'] ?? '',
		'author'          => $plugin['Author'] ?? '',
		'contributors'    => [],
		'requires'        => $plugin['RequiresWP'] ?? '',
		'tested'          => $plugin['TestedUpTo'] ?? '',
		'requires_php'    => $plugin['RequiresPHP'] ?? '',
		'downloaded'      => 0,
		'active_installs' => 0,
		'last_updated'    => '',
		'added'           => '',
		'homepage'        => $plugin['PluginURI'] ?? '',
		'download_link'   => "https://repo.deploytroy.org/plugin/get/zip/$slug/",
		'sections'        => [
			'description' => $plugin['Description'] ?? '',
		],
		'donate_link'     => '',
		'banners'         => [],
	];

	$fields = (array) ( $body['fields'] ?? [] );

	if ( $fields ) {
		$fields = array_merge(
			$fields,
			[
				'name'    => true,
				'slug'    => true,
				'version' => true,
			],
		);

		$filtered = [];

		foreach ( $fields as $field => $include )
			if ( $include && isset( $response[ $field ] ) )
				$filtered[ $field ] = $response[ $field ];

		$response = $filtered;
	}

	return troy_playground_official_json( $response );
}

/**
 * Zeroed public stats object.
 *
 * @since 1.8.1184
 *
 * @param string $slug Plugin slug.
 * @return array
 */
function troy_playground_official_stats_item( $slug ) {
	return [
		'slug'            => $slug,
		'downloads'       => 0,
		'active_installs' => 0,
		'_comment'        => 'Ratings are not yet implemented, so they return zero values.',
		'rating'          => 0,
		'num_ratings'     => 0,
		'ratings'         => [
			5 => 0,
			4 => 0,
			3 => 0,
			2 => 0,
			1 => 0,
		],
	];
}

/**
 * Faux GET plugin/get/stats/{slug}.
 *
 * @since 1.8.1184
 *
 * @param string $slug Plugin slug.
 * @return array
 */
function troy_playground_official_stats_one( $slug ) {

	if ( ! troy_playground_official_plugin( $slug ) )
		return troy_playground_official_error( 'Plugin not found', 404 );

	return troy_playground_official_json(
		troy_playground_official_stats_item( $slug ),
	);
}

/**
 * Faux POST plugin/get/stats.
 *
 * @since 1.8.1184
 *
 * @param array $body Request body.
 * @return array
 */
function troy_playground_official_stats_many( $body ) {

	$slugs = $body['slugs'] ?? [];

	if ( ! is_array( $slugs ) )
		return troy_playground_official_error( 'Invalid slugs parameter', 400 );

	if ( count( $slugs ) > 69 )
		return troy_playground_official_error(
			'Too many slugs, maximum is 69',
			400,
		);

	$response = [];

	foreach ( $slugs as $raw ) {
		if ( ! is_string( $raw ) || ! $raw ) continue;

		if ( ! troy_playground_official_plugin( $raw ) ) continue;

		$response[] = troy_playground_official_stats_item( $raw );
	}

	return troy_playground_official_json( $response );
}

/**
 * Faux GET plugin/get/tags/{slug}.
 *
 * @since 1.8.1184
 *
 * @param string $slug Plugin slug.
 * @return array
 */
function troy_playground_official_tags( $slug ) {

	$plugin = troy_playground_official_plugin( $slug );

	if ( ! $plugin )
		return troy_playground_official_error( 'Plugin not found', 404 );

	$version = $plugin['Version'] ?? '';

	if ( ! $version )
		return troy_playground_official_json( [] );

	return troy_playground_official_json( [
		[
			'version'      => $version,
			'zip_url'      => "https://repo.deploytroy.org/plugin/get/zip/$slug/$version/",
			'file_size'    => 0,
			'tested_wp'    => $plugin['TestedUpTo'] ?? '',
			'requires_wp'  => $plugin['RequiresWP'] ?? '',
			'requires_php' => $plugin['RequiresPHP'] ?? '',
			'checksums'    => null,
			'updated_at'   => '',
		],
	] );
}

/**
 * Faux GET plugin/get/zip/{slug}/.
 *
 * Builds the archive from a mounted plugin or the zip-source tree.
 * Writes streamed downloads to the request filename so download_url()
 * and Plugin_Upgrader see a real zip.
 *
 * @since 1.8.1184
 *
 * @param string $slug        Plugin slug.
 * @param array  $parsed_args HTTP request arguments.
 * @return array
 */
function troy_playground_official_zip( $slug, $parsed_args = [] ) {

	$plugin = troy_playground_official_plugin( $slug );

	if ( ! $plugin || empty( $plugin['_dir'] ) || ! is_dir( $plugin['_dir'] ) )
		return troy_playground_official_error( 'Plugin not found', 404 );

	if ( ! class_exists( 'ZipArchive' ) )
		return troy_playground_official_error( 'ZipArchive missing', 500 );

	troy_playground_official_load_file_php();

	$tmp = wp_tempnam( "$slug.zip" );
	$zip = new ZipArchive();

	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		unlink( $tmp );

		return troy_playground_official_error( 'Could not create zip', 500 );
	}

	troy_playground_official_zip_add( $zip, $plugin['_dir'], $slug );
	$zip->close();

	$bytes = file_get_contents( $tmp );
	unlink( $tmp );

	if ( false === $bytes )
		return troy_playground_official_error( 'Could not create zip', 500 );

	$filename = $parsed_args['filename'] ?? '';

	if ( ! empty( $parsed_args['stream'] ) && $filename ) {
		if ( false === file_put_contents( $filename, $bytes ) )
			return troy_playground_official_error( 'Could not create zip', 500 );
	}

	return troy_playground_official_http(
		$bytes,
		200,
		[
			'content-type'        => 'application/zip',
			'content-disposition' => "attachment; filename=\"$slug.zip\"",
		],
	);
}

/**
 * Adds a directory tree to a zip under $prefix.
 *
 * @since 1.8.1184
 *
 * @param ZipArchive $zip    Archive.
 * @param string     $dir    Host directory.
 * @param string     $prefix Path prefix inside the archive.
 */
function troy_playground_official_zip_add( $zip, $dir, $prefix ) {

	$items = scandir( $dir );

	if ( ! $items ) return;

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) continue;

		$full  = "$dir/$item";
		$local = "$prefix/$item";

		if ( is_dir( $full ) ) {
			$zip->addEmptyDir( $local );
			troy_playground_official_zip_add( $zip, $full, $local );
			continue;
		}

		$zip->addFile( $full, $local );
	}
}
