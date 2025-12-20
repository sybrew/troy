<?php
/**
 * @package Troy\Client
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
 * Holds sanitization methods.
 *
 * @since 1.1.1184
 */
final class Sanitize {

	/**
	 * Sanitizes a URL and forces it to become fully qualified.
	 *
	 * Per IETF RFC 3986, a URI with an authority but empty path is normalized to have a `/` path.
	 *
	 * @since 1.1.1184
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
	 * Sanitizes a static image URL.
	 *
	 * Detects animated images and returns an empty string if found.
	 * Detects SVGs with dangerous content (scripts, event handlers, animations) and blocks them.
	 *
	 * This method is a client-side mirror of Troy Server's sanitization to protect against
	 * rogue or compromised Troy Servers forwarding malicious image URLs.
	 *
	 * @since 1.1.1184
	 * @since 1.6.1184 Explicitly set the user agent for the image request.
	 *
	 * @param string $url The URL to sanitize.
	 * @return string The sanitized URL or empty string.
	 */
	public static function static_image_url( $url ) {

		$sanitized_url = self::url_qualified( $url );

		if ( empty( $sanitized_url ) )
			return '';

		$body = trim( \wp_remote_retrieve_body( \wp_safe_remote_get(
			$sanitized_url,
			[
				'timeout'    => 3, // Image should be locally hosted, or at worst at a CDN.
				'headers'    => [
					'Range'      => 'bytes=0-20480',
					'User-Agent' => 'Troy Client/' . VERSION, // See WP_Http_Curl::request()
				],
				'user-agent' => 'Troy Client/' . VERSION, // See WP_Http::request()
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
}
