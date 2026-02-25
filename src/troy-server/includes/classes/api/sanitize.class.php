<?php
/**
 * @package Troy\Server\API
 * @api
 */

namespace Troy\Server\API;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\API; // We explicitly prefix API methods, possibly easing adoption.

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
 * Holds sanitization methods.
 *
 * @since 0.0.1184
 */
final class Sanitize {

	/**
	 * Sanitizes a SemVer version string.
	 *
	 * This function extracts the SemVer version from a given string, removing any leading or trailing characters.
	 * It uses a regular expression to match the SemVer format, which consists of major, minor, and patch versions,
	 * optionally followed by pre-release and build metadata.
	 *
	 * Three-part versioning is required (major.minor.patch). Two-part versions like 1.0 are not supported.
	 *
	 * The SemVer format is defined as:
	 * - Major version: 0 or a positive integer
	 * - Minor version: 0 or a positive integer
	 * - Patch version: 0 or a positive integer
	 * - Pre-release version: optional, starts with a hyphen followed by a series of dot-separated identifiers
	 * - Build metadata: optional, starts with a plus sign followed by a series of dot-separated identifiers
	 *
	 * @since 0.0.1184
	 *
	 * @param string $version The version string to sanitize.
	 * @return string The sanitized SemVer version, or empty string if no valid version found.
	 */
	public static function semver( $version ) {

		preg_match(
			'/^.*((0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?).*$/',
			trim( $version ),
			$matches,
		);

		return $matches[1] ?? '';
	}

	/**
	 * Sanitizes a SQL date.
	 *
	 * This function takes a date string and returns it in the format 'Y-m-d'.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $date The date to sanitize.
	 * @return string The sanitized date in 'Y-m-d' format.
	 */
	public static function sql_date( $date ) {
		return new \DateTime(
			\is_string( $date ) ? $date : 'now',
			new \DateTimeZone( 'UTC' ),
		)->format( 'Y-m-d' );
	}

	/**
	 * Sanitizes a slug by converting it to lowercase, replacing non-alphanumeric
	 * characters with hyphens, collapsing multiple consecutive hyphens into a single
	 * hyphen, trimming leading zeroes and hyphens, and trimming trailing hyphens.
	 * Finally, it limits the slug to a maximum length of 191 characters.
	 *
	 * Also works to sanitize path names.
	 *
	 * @since 0.0.1184
	 * @see PluginSlugControl in JS.
	 *
	 * @param string $slug The slug to sanitize.
	 * @return string The sanitized slug.
	 */
	public static function slug( $slug ) {
		return substr(
			rtrim(
				ltrim(
					preg_replace(
						'/-{2,}/',
						'-',
						preg_replace(
							'/[^a-z0-9-]/',
							'',
							preg_replace(
								'/\s+/',
								'-',
								strtolower( $slug ?? '' ),
							),
						),
					),
					'0-',
				),
				'-',
			),
			0,
			191,
		);
	}

	/**
	 * Sanitizes a file path.
	 *
	 * Windows's MAX_PATH is 260 characters, so the character limit imposed by
	 * slug() is helpful (at 191 characters) to avoid issues.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $file_path The file path to sanitize.
	 * @return string The sanitized file path.
	 */
	public static function file_path( $file_path ) {
		return ltrim( self::slug( $file_path ), '0' );
	}

	/**
	 * Sanitizes a value for use in docblocks by removing characters that could break docblock syntax.
	 *
	 * @since 0.0.1184
	 * @since 1.4.1184 No longer removes slashes and asterisks inside the content.
	 *
	 * @param string $value The value to sanitize.
	 * @return string The sanitized value with * and / removed.
	 */
	public static function docblock_content( $value ) {
		return preg_replace(
			'~(/\*+)|(\*+/)~',
			'',
			preg_replace( '/[\s\r\n\v\t]+/u', ' ', trim( $value ) ),
		);
	}

	/**
	 * Sanitizes a variable for use in evaluatable PHP code.
	 *
	 * This function converts a given value into its PHP code representation,
	 * suitable for inclusion in generated PHP files.
	 *
	 * It handles strings, integers, doubles, booleans, null, and arrays.
	 * Resources and objects are not supported and will be converted to null.
	 *
	 * Note: No opening or closing PHP tags are added.
	 * Note: This function writes no separating semicolons.
	 *
	 * @since 0.0.1184
	 *
	 * @param mixed $value     The value to sanitize.
	 * @param int   $tab_count The tab-count to use for nested structures.
	 * @return string The evaluatable PHP code representation of the value.
	 */
	public static function var_export( $value, $tab_count = 0 ) {

		$tab = str_repeat( "\t", $tab_count );

		switch ( \gettype( $value ) ) {
			case 'string':
				$escaped = strtr(
					$value,
					[
						'\\' => '\\\\',
						"'"  => "\\'",
					],
				);
				return "$tab'$escaped'";

			case 'integer':
			case 'double':
				return "$tab$value";

			case 'boolean':
				return $tab . ( $value ? 'true' : 'false' );

			case 'NULL':
				return "{$tab}null";

			case 'array':
				if ( ! $value )
					return "{$tab}[]";

				$is_list = array_is_list( $value );
				$entries = [];

				foreach ( $value as $k => $v ) {
					if ( $is_list ) {
						$entries[] = self::var_export( $v, $tab_count + 1 );
					} else {
						$entry_key   = self::var_export( $k, $tab_count + 1 );
						$entry_value = \is_scalar( $v )
							? self::var_export( $v, 0 )
							: trim( self::var_export( $v, $tab_count + 1 ), "\t" );

						$entries[] = "$entry_key => $entry_value";
					}
				}

				$entries = implode( ",\n", $entries );

				return "{$tab}[\n{$entries},\n{$tab}]";

			default:
				return "{$tab}null";
		}
	}

	/**
	 * Sanitizes a PHP namespace.
	 *
	 * This function ensures that the provided namespace is valid by:
	 * - Replacing invalid characters with backslashes.
	 * - Collapsing multiple consecutive backslashes into a single backslash.
	 * - Capitalizing the first letter of each namespace segment.
	 * - Removing any leading digits from each segment.
	 * - Filtering out any empty segments.
	 * Finally, it joins the sanitized segments back together with backslashes.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $ns The namespace to sanitize.
	 * @return string The sanitized namespace.
	 */
	public static function php_namespace( $ns ) {
		return implode(
			'\\',
			array_filter( array_map(
				fn( $part ) => ucfirst( preg_replace( '/^\d+/', '', $part ) ),
				explode(
					'\\',
					trim(
						preg_replace(
							'/\\\\{2,}/',
							'\\',
							preg_replace(
								'/[^a-zA-Z0-9_\\\\]/',
								'\\',
								$ns ?? '',
							),
						),
						'\\',
					),
				),
			) ),
		);
	}

	/**
	 * Sanitizes a version requirement string in the format 'X.Y' or 'X.Y.Z'.
	 *
	 * Max length is 20 characters.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $version The version requirement string to sanitize.
	 * @return string The sanitized version requirement, or an empty string if the input does not match the expected format.
	 */
	public static function tested_version( $version ) {
		return preg_match(
			'/(\d+\.\d+(\.\d+)?)/',
			trim( $version ),
			$matches,
		)
			? substr( $matches[1], 0, 20 )
			: '';
	}

	/**
	 * Sanitizes a user ID.
	 *
	 * This function retrieves the user ID from a given user object or user ID.
	 * If the user does not exist, it returns 0.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $user The user ID to sanitize.
	 * @return int The sanitized user ID, or 0 if the user does not exist.
	 */
	public static function user_id( $user ) {
		return ( \get_userdata( (int) $user ) ?: null )?->ID ?: 0;
	}

	/**
	 * Sanitizes the contributors data.
	 *
	 * This function processes an array of contributors, ensuring that each contributor has a valid user ID
	 * and a non-empty role. It filters out any invalid entries.
	 *
	 * @since 0.0.1184
	 * @TODO defunct? This function is too specific.
	 *
	 * @param array $contributors {
	 *     The contributors data to sanitize.
	 *
	 *     @type int    $user_id The user ID of the contributor.
	 *     @type string $role    The role of the contributor.
	 * }
	 * @return array The sanitized contributors data.
	 */
	public static function contributors( $contributors ) {
		return array_filter(
			array_map(
				fn( $item ) => [
					'user_id' => self::user_id( $item['user_id'] ?? 0 ),
					'role'    => self::contributor_role( $item['role'] ?? 'contributor' ),
				],
				$contributors ?? [],
			),
			fn( $item ) => $item['user_id'] && $item['role'],
		);
	}

	/**
	 * Sanitizes a contributor role.
	 *
	 * This function converts the role to lowercase and ensures it is one of the allowed roles.
	 * If the role is not recognized, it defaults to 'contributor'.
	 *
	 * @todo Add more roles once we expand functionality (founder, translator, support, etc.)
	 *
	 * @since 0.0.1184
	 *
	 * @param string $role The role to sanitize.
	 * @return string The sanitized role, defaulting to 'contributor' if not recognized.
	 */
	public static function contributor_role( $role ) {

		$role = strtolower( $role );

		return match ( $role ) {
			'contributor' => $role,
			default => 'contributor',
		};
	}

	/**
	 * Sanitizes the version type.
	 *
	 * This function converts the version type to lowercase and ensures it is one of the allowed types.
	 * If the version type is not recognized, it defaults to 'unreleased'.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $version_type The version type to sanitize.
	 * @return string The sanitized version type.
	 */
	public static function version_type( $version_type ) {

		$version_type = strtolower( $version_type );

		return match ( $version_type ) {
			'unreleased', 'beta', 'tag' => $version_type,
			default => 'unreleased',
		};
	}

	/**
	 * Sanitizes the update channel.
	 *
	 * This function converts the channel to lowercase and ensures it is one of the allowed values.
	 * If the channel is not recognized, it defaults to 'tag'.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $channel The channel to sanitize.
	 * @return string The sanitized channel, either 'tag' or 'beta'.
	 */
	public static function channel( $channel ) {

		$channel = strtolower( $channel );

		return match ( $channel ) {
			'tag', 'beta' => $channel,
			default => 'tag',
		};
	}

	/**
	 * Sanitizes the auto_process setting.
	 *
	 * This function converts the auto_process setting to lowercase and ensures it is one of the allowed values.
	 * If the setting is not recognized, it defaults to 'all'.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $auto_process The auto_process setting to sanitize.
	 * @return string The sanitized auto_process setting.
	 */
	public static function auto_process( $auto_process ) {

		$auto_process = strtolower( $auto_process );

		return match ( $auto_process ) {
			'all', 'tag', 'beta', 'none' => $auto_process,
			default => 'all',
		};
	}

	/**
	 * Sanitizes an upgrade notice.
	 *
	 * This function ensures that the upgrade notice is a string, removes excessive whitespace,
	 * and truncates it to a maximum of 191 characters.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $notice The upgrade notice to sanitize.
	 * @return string The sanitized upgrade notice, truncated to 191 characters.
	 */
	public static function upgrade_notice( $notice ) {
		return preg_replace(
			'/^(.{0,191}).*$/u',
			'$1',
			preg_replace(
				'/\s+/u',
				' ',
				\wp_kses(
					$notice,
					[
						'a'      => [
							'href'   => true,
							'title'  => true,
							'target' => true,
							'rel'    => true,
						],
						'strong' => [],
						'em'     => [],
						'b'      => [],
						'br'     => [],
					],
				),
			),
		);
	}

	/**
	 * Sanitizes a WordPress locale.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $locale The locale to sanitize.
	 */
	public static function wp_locale( $locale = '' ) {

		if ( $locale ) {
			$available_locales = API\Info::get_available_locales();

			if ( isset( $available_locales[ $locale ] ) )
				return $locale;

			// If the locale is not in the available locales, grab the first part.
			$locale = preg_replace( '/[^a-z0-9_]/', '', $locale );

			if ( isset( $available_locales[ $locale ] ) )
				return $locale;

			// Try the first part of the locale, e.g. 'en_US' becomes 'en'.
			$locale = strtok( $locale, '_' );

			if ( isset( $available_locales[ $locale ] ) )
				return $locale;
		}

		return \get_locale();
	}

	/**
	 * Encodes the given data to JSON for database storage.
	 *
	 * @since 0.0.1184
	 * @access private
	 *         This function will be moved to a more appropriate location in the future.
	 *
	 * @param mixed $data The data to encode.
	 * @return string The JSON encoded data.
	 */
	public static function json_encode_db( $data ) {
		return json_encode(
			$data,
			  \JSON_UNESCAPED_SLASHES
			| \JSON_UNESCAPED_UNICODE
			| \JSON_INVALID_UTF8_IGNORE
			| \JSON_PRESERVE_ZERO_FRACTION
			| \JSON_THROW_ON_ERROR, // Pernicious. Good. May prevent data loss.
		);
	}

	/**
	 * Sanitizes a URL and forces it to become fully qualified.
	 *
	 * Per IETF RFC 3986, a URI with an authority but empty path is normalized to have a `/` path.
	 *
	 * @since 0.0.1184
	 * @todo PHP 8.5+ support: Use `new Uri\Rfc3986\Uri( $url )->withPath('/')->toString()`.
	 *
	 * @param string $url The URL to sanitize.
	 * @return string The sanitized URL.
	 */
	public static function url_qualified( $url ) {

		if ( ! $url )
			return '';

		$url = \sanitize_url(
			preg_replace(
				'/^(?:\w*:)?(?:\/\/)?(.*?)$/',
				'https://$1',
				$url,
			),
			[ 'https' ],
		);

		$parsed = parse_url( $url );

		// Enforce trailing slash on domain-only URLs per IETF RFC 3986.
		if ( empty( $parsed['path'] ) ) {
			$host     = $parsed['host'];
			$port     = isset( $parsed['port'] ) ? ":{$parsed['port']}" : '';
			$query    = isset( $parsed['query'] ) ? "?{$parsed['query']}" : '';
			$fragment = isset( $parsed['fragment'] ) ? "#{$parsed['fragment']}" : '';

			$url = "https://{$host}{$port}/{$query}{$fragment}";
		}

		return $url;
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
	 * They won't be collapsed with different cases from different plugins, leading to unnecessary requests.
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
	public static function fully_qualified_repo_url( $repo ) {
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
	 * Returns a bare repository URL, containing only domain/path/query.
	 *
	 * This mirrors the JS `sanitize.bareRepoUrl()` function for consistent comparisons.
	 * Returns a normalized format like `domain.tld/path` without scheme or trailing slash.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $repo The repository URL to strip.
	 * @return string The bare repository URL (domain/path only).
	 */
	public static function bare_repo_url( $repo ) {

		$repo = trim( $repo ?? '' );

		if ( ! $repo )
			return '';

		// Remove scheme and leading slashes, normalize to domain/path format
		$stripped = preg_replace(
			'/^(?:\w*:)?(?:\/\/)?(.*?)$/',
			'$1',
			trim( $repo, ' \\\/' ),
		);

		return rtrim( $stripped, '/' );
	}

	/**
	 * Sanitizes a static image URL.
	 *
	 * Detects animated images and returns an empty string if found.
	 * Detects SVGs with dangerous content (scripts, event handlers, animations) and blocks them.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $url The URL to sanitize.
	 * @return string The sanitized URL or empty string.
	 */
	public static function static_image_url( $url ) {

		$sanitized_url = self::url_qualified( $url );

		if ( empty( $sanitized_url ) )
			return '';

		$body = trim( \wp_remote_retrieve_body( \wp_remote_get( // No safe: URL returned on failure below anyway
			$sanitized_url,
			[
				'timeout'    => 3, // Image should be locally hosted, or at worst at a CDN.
				'headers'    => [
					'Range'      => 'bytes=0-20480',
					'User-Agent' => 'Troy Server/' . VERSION, // See WP_Http_Curl::request()
				],
				'user-agent' => 'Troy Server/' . VERSION, // See WP_Http::request()
			],
		) ) );

		// Body becomes empty on error via wp_remote_retrieve_body().
		// Assume a fluke and immediately return the sanitized URL.
		if ( empty( $body ) )
			return $sanitized_url;

		$header = substr( $body, 0, 20 );

		switch ( true ) {
			case str_starts_with( $header, "\xFF\xD8\xFF" ): // JPEG/JFIF/Exif
			case str_starts_with( $header, "\xFF\xD9" ):     // JPEG (rare EOF-first)
				// JPEG is always static, no further validation needed.
				break;

			case str_starts_with( $header, "\x89PNG" ):
				// Animated PNG detection: acTL chunk (animation).
				if ( str_contains( $body, 'acTL' ) )
					return '';
				break;

			case str_starts_with( $header, 'RIFF' ) && str_contains( $header, 'WEBP' ):
				// Animated WebP detection: ANIM chunk (animation).
				if ( str_contains( $body, 'ANIM' ) )
					return '';
				break;

			case str_starts_with( $header, 'GIF' ):
				// Animated GIF detection: multiple graphic control extensions (animation).
				if ( preg_match_all( '#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $body ) > 1 )
					return '';
				break;

			case str_starts_with( $header, '<svg' ):
			case str_starts_with( $header, '<?xml' ):
				// SVG revalidation: must contain <svg element.
				if ( ! str_contains( $body, '<svg' ) )
					return '';

				// SVG validation: block dangerous content.
				if ( preg_match(
					  '/<(?:\w+:)?('                     // Element with optional namespace prefix.
					. 'set|animate'                      // SMIL animation elements.
					. '|script|handler'                  // Script elements.
					. '|foreignObject'                   // Foreign content container.
					. '|iframe|object|embed'             // Embedded content elements.
					. '|use|image|link|feImage'          // External resource loaders.
					. '|mpath|discard|tref'              // Motion path, discard, and text reference.
					. ')\b'
					. '|<!--'                            // XML comments (may hide malicious content).
					. '|\bon\w+\s*='                     // Event handlers.
					. '|(java|vb)script\s*:'             // Script protocols.
					. '|data:(?!image\/(png|jpe?g)[;,])' // Data URIs not pointing to safe static image types.
					. '|&#'                              // Encoded entities.
					. '|@import'                         // CSS imports.
					. '|url\s*\('                        // CSS url() function.
					. '|-moz-binding'                    // Firefox XBL binding.
					. '/i',
					$body,
				) )
					return '';
				break;

			default:
				// Reject unrecognized image types.
				return '';
		}

		return $sanitized_url;
	}

	/**
	 * Sanitizes an object of tags from integration sources.
	 *
	 * @since 0.0.1184
	 *
	 * @param iterable $tags Raw tags object. {
	 *     Tags indexed by package version string.
	 *
	 *     @type string $download_url The download URL.
	 *     @type string $type         Optional. The version type, either 'tag' or 'beta'.
	 *                                If not provided, it will be determined based on version pattern.
	 *     @type string $revision_id  Optional. The revision ID.
	 * }
	 * @return object {
	 *     Sanitized tags object indexed by sanitized package version string.
	 *
	 *     @type string $download_url The download URL.
	 *     @type string $type         The version type, either 'tag' or 'beta'.
	 *     @type string $revision_id  The revision ID.
	 * }
	 */
	public static function tags( $tags ) {

		$sanitized = [];

		foreach ( $tags as $package_version => $data ) {
			$data = (object) $data;

			$version      = self::semver( $package_version );
			$download_url = \sanitize_url( $data->download_url );

			if ( ! $version || ! $download_url )
				continue;

			$sanitized[ $version ] = (object) [
				'download_url' => $download_url,
				'type'         => \in_array(
					$data->type ?? '',
					[ 'beta', 'tag' ],
					true,
				)
					? $data->type
					: API\Utils::get_version_type( $version ),
				'revision_id'  => $data->revision_id ?? '',
			];
		}

		return (object) $sanitized;
	}
}
