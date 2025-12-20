<?php
/**
 * @package Troy\Server\Classes
 */

namespace Troy\Server;

\defined( 'Troy\Server\ABSPATH' ) or die;

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
 * Handles plugin table modifications.
 *
 * @since 1.6.1184
 */
final class Plugin_Table {

	/**
	 * Adds a privacy policy link to the plugin row meta.
	 *
	 * @hook plugin_row_meta 10 2
	 * @since 1.6.1184
	 *
	 * @param string[] $plugin_meta An array of the plugin's metadata.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return string[] The modified plugin metadata.
	 */
	public static function add_row_meta( $plugin_meta, $plugin_file ) {

		if ( PLUGIN_BASENAME !== $plugin_file )
			return $plugin_meta;

		$plugin_meta['troy-server-privacy'] = \sprintf(
			'<a href="https://deploytroy.org/privacy/" target="_blank" rel="noopener">%s</a>',
			\esc_html__( 'Privacy', 'troy-server' ),
		);

		return $plugin_meta;
	}
}
