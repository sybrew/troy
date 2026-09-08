<?php
/**
 * @package Troy\Server\Views\Notice
 */

namespace Troy\Server\Views\Notice;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- This isn't the global scope.

/**
 * Troy Server
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

[ $message, $key, $args ] = $view_args;

if ( ! $message ) return;

$type = $args['type'] ?? 'success';

if ( 'updated' === $type )
	$type = 'success';

$dismiss_title_i18n = \__( 'Dismiss this notice', 'default' );

vprintf(
	'<div class="notice notice-%s is-dismissible troy-server-notice">%s%s</div>',
	[
		\esc_attr( $type ),
		\sprintf(
			! $args['escape'] && 0 === stripos( $message, '<p' )
				? '%s'
				: '<p>%s</p>',
			$args['escape']
				? \esc_html( $message )
				: $message, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the invoker should be mindful.
		),
		\sprintf(
			'<button type=button class="notice-dismiss troy-server-notice-dismiss" data-key="%s" title="%s"><span class=screen-reader-text>%s</span></button>',
			\sanitize_key( $key ), // Practically escapes.
			\esc_attr( $dismiss_title_i18n ),
			\esc_html( $dismiss_title_i18n ),
		),
	],
);
