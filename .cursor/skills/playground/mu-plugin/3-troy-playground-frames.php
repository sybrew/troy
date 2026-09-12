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

add_filter(
	'wp_plugin_regression_frame',
	'troy_playground_apply_frame',
	10,
	2,
);

/**
 * Handles the engine capture frame. Troy's catalog is `/` and `/sample-page/`.
 * Capture always applies `blog`; without this filter it replies ok:false.
 *
 * @since 1.8.1184
 *
 * @param bool   $handled Whether a consumer already applied the frame.
 * @param string $name    Frame name.
 * @return bool
 */
function troy_playground_apply_frame( $handled, $name ) {

	if ( $handled ) return $handled;

	if ( 'blog' !== $name ) return $handled;

	if ( ! function_exists( 'is_blog_installed' ) || ! is_blog_installed() )
		return $handled;

	update_option( 'show_on_front', 'posts' );

	return true;
}
