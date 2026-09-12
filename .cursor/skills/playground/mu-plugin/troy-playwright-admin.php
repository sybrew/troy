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

add_action(
	'set_auth_cookie',
	'troy_playground_playwright_sync_auth_cookie',
	10,
	5,
);
add_action(
	'set_logged_in_cookie',
	'troy_playground_playwright_sync_logged_in_cookie',
);
add_action(
	'init',
	'troy_playground_playwright_admin_login',
	0,
);

/**
 * Copies the auth cookie into $_COOKIE so auth_redirect() passes on this request.
 *
 * @since 1.8.1184
 *
 * @param string $cookie     Cookie value.
 * @param int    $expire     Cookie expire.
 * @param int    $expiration Session expire.
 * @param int    $user_id    User ID.
 * @param string $scheme     Cookie scheme.
 */
function troy_playground_playwright_sync_auth_cookie( $cookie, $expire, $expiration, $user_id, $scheme ) {
	$_COOKIE[ 'secure_auth' === $scheme ? SECURE_AUTH_COOKIE : AUTH_COOKIE ] = $cookie;
}

/**
 * Copies the logged-in cookie into $_COOKIE for this request.
 *
 * @since 1.8.1184
 *
 * @param string $cookie Cookie value.
 */
function troy_playground_playwright_sync_logged_in_cookie( $cookie ) {
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
}

/**
 * Returns the current request path.
 *
 * @since 1.8.1184
 *
 * @return string
 */
function troy_playground_playwright_request_path() {

	$uri = $_SERVER['REQUEST_URI'] ?? '';

	return (string) parse_url( $uri, PHP_URL_PATH );
}

/**
 * Whether this request should receive a Playwright admin session.
 *
 * Front-end HTML stays logged-out. HTTP capture does not send the header.
 *
 * @since 1.8.1184
 *
 * @return bool
 */
function troy_playground_playwright_wants_admin() {

	if ( defined( 'WP_ADMIN' ) && WP_ADMIN )
		return true;

	$path = troy_playground_playwright_request_path();

	if ( str_ends_with( $path, '/wp-login.php' ) )
		return true;

	return str_contains( $path, '/wp-json/' );
}

/**
 * Returns the Playwright admin header value.
 *
 * Prefers X-Troy-Playground-Admin. A user-level Cursor MCP config may still
 * send X-TSF-Playground-Admin when this repo is open beside TSF.
 *
 * @since 1.8.1184
 *
 * @return string
 */
function troy_playground_playwright_admin_header() {

	if ( ! empty( $_SERVER['HTTP_X_TROY_PLAYGROUND_ADMIN'] ) )
		return (string) $_SERVER['HTTP_X_TROY_PLAYGROUND_ADMIN'];

	if ( ! empty( $_SERVER['HTTP_X_TSF_PLAYGROUND_ADMIN'] ) )
		return (string) $_SERVER['HTTP_X_TSF_PLAYGROUND_ADMIN'];

	return '';
}

/**
 * Logs in the Playground admin when Playwright sends the admin header.
 *
 * @since 1.8.1184
 */
function troy_playground_playwright_admin_login() {

	if ( '1' !== troy_playground_playwright_admin_header() ) return;

	if ( ! troy_playground_playwright_wants_admin() ) return;

	if ( ! is_user_logged_in() ) {
		$user = get_user_by( 'login', 'admin' );

		if ( ! $user ) return;

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
	}

	if ( ! str_ends_with( troy_playground_playwright_request_path(), '/wp-login.php' ) )
		return;

	wp_safe_redirect( admin_url() );
	exit;
}
