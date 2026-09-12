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
 * Playground mounts mu-plugins before install.php. Daemon's init hook
 * would query wp_options before that table exists. Skip until installed.
 *
 * Client directory mounts are --mount-dir overlays. The main file is
 * already hardlinked into persist. Daemon activate_plugin would 255 on
 * includes/api.php until that overlay exists. Daemon-only still loads:
 * Client is not under plugins/ until the ZIP install.
 */
if ( wp_installing() ) return;

$client_main = WP_PLUGIN_DIR . '/troy-client/troy-client.php';
$client_api  = WP_PLUGIN_DIR . '/troy-client/includes/api.php';

if ( is_file( $client_main ) && ! is_file( $client_api ) ) return;

require WP_CONTENT_DIR . '/mu-plugins/troy-client-daemon/troy-client-daemon.php';
