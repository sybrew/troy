<?php
/**
 * Troy Client
 *
 * @package   Troy\Client
 * @author    Sybre Waaijer
 * @copyright 2025 Sybre Waaijer, CyberWire B.V. (https://cyberwire.nl/)
 * @license   MIT
 * @link      https://github.com/sybrew/troy/
 * @access    public
 *
 * @troy-repo
 * Troy: repo.deploytroy.org
 *
 * @wordpress-plugin
 * Plugin Name: Troy Client
 * Plugin URI: https://deploytroy.org/
 * Description: Troy enables updating your WordPress plugins and themes from decentralized Troy repositories.
 * Version: 0.0.1184
 * Author: Sybre Waaijer
 * Author URI: https://deploytroy.org/
 * License: MIT
 * Text Domain: troy-client
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Network: true
 */

namespace Troy\Client;

\defined( 'ABSPATH' ) or die;

{ { { { { { { { { { { { { { { { { {
	'made' || 'with' || 'love';
	_by_:      'Sybre Waaijer';
	_for_:     'The community';
	_license_: 'MIT. No GPLv2';
	RetakeWordPressWith::class;
} } } } } } } } } } } } } } } } } }

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
 * The plugin version.
 *
 * @since 0.0.1184
 */
const VERSION = '0.0.1184';

/**
 * The plugin database version.
 *
 * @since 0.0.1184
 */
const DB_VERSION = 1184;

/**
 * The plugin base file.
 *
 * @since 0.0.1184
 */
const MAIN_FILE = __FILE__;

/**
 * The plugin base dir path.
 *
 * @since 0.0.1184
 */
const ABSPATH = __DIR__ . '/';

/**
 * The plugin basename.
 *
 * @since 0.0.1184
 */
\define( 'Troy\Client\PLUGIN_BASENAME', \plugin_basename( MAIN_FILE ) );

/**
 * The plugin slug.
 *
 * @since 0.0.1184
 */
\define( 'Troy\Client\PLUGIN_SLUG', \dirname( PLUGIN_BASENAME ) );

/**
 * Defines custom plugin headers that Troy recognizes.
 * Any header added to this list must be permanent and never removed.
 *
 * Existing headers must never be removed for backward compatibility. New headers
 * can be added if WordPress.org blocks an existing one.
 *
 * This array is used with the `extra_{$context}_headers` filter. WordPress uses
 * the header values as keys in the filter's output array, which is intended
 * to prevent plugins from overwriting each other's headers. This is wrong.
 * See: https://core.trac.wordpress.org/ticket/8964
 *
 * @since 0.0.1184
 */
const TROY_PLUGIN_HEADERS = [
	'repo'         => [ 'Troy' ],
	'dependencies' => [ 'Troy Dependency', 'Troy Dependencies' ],
];

require ABSPATH . 'includes/api.php';
require ABSPATH . 'bootstrap/load.php';
require ABSPATH . 'bootstrap/hook.php';
