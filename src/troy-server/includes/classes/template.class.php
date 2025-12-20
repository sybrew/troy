<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\ABSPATH;

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
 * Holds API interfaces for view templates.
 *
 * @since 0.0.1184
 * @access private
 */
final class Template {

	/**
	 * @since 0.0.1184
	 * @var ?string $secret The include secret.
	 */
	private static $secret;

	/**
	 * Outputs a view template.
	 *
	 * Adds a `$secret` to the file to prevent including without this class.
	 * This makes file inclusions difficult when the plugin is dormant (deactivated).
	 *
	 * @since 0.0.1184
	 * @access private
	 *
	 * @param string $file         The relative view file name.
	 * @param array  ...$view_args The arguments to be supplied to the file.
	 */
	public static function output_view( $file, ...$view_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis, Generic.CodeAnalysis -- includes.

		$secret = self::$secret = uniqid( '', true );

		require self::get_view_location( $file );
	}

	/**
	 * Gets view location. Forces a path on our views folder.
	 *
	 * @since 0.0.1184
	 * @access private
	 *
	 * @param string $file The file name.
	 * @return ?string The view location. Null on failure.
	 */
	public static function get_view_location( $file ) {

		static $real_view;

		$real_view ??= realpath( ABSPATH . 'includes/views' );
		$path        = realpath( "$real_view/$file.php" );

		if ( $path && str_starts_with( $path, $real_view ) )
			return $path;

		return null;
	}

	/**
	 * Verifies view secret.
	 *
	 * @since 0.0.1184
	 * @access private
	 *
	 * @param string $value The value to match against secret.
	 * @return bool
	 */
	public static function verify_secret( $value ) {
		return isset( $value ) && self::$secret === $value;
	}
}
