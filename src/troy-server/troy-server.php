<?php
/**
 * Troy Server
 *
 * @package   Troy\Server
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
 * Plugin Name: Troy Server
 * Plugin URI: https://deploytroy.org/docs/troy-server/
 * Description: Troy Server allows you to distribute WordPress plugins from your independent update repository.
 * Version: 1.7.1184-dev-4
 *
 * Author: Sybre Waaijer
 * Author URI: https://deploytroy.org/
 * License: MIT
 * Text Domain: troy-server
 * Requires at least: 6.8
 * Tested up to: 7.0
 * Requires PHP: 8.4
 */

namespace Troy\Server;

\defined( 'ABSPATH' ) or die;

{ { { { { { { { { { { { { { { { { {
	'made' || 'with' || 'love';
	_by_:      'Sybre Waaijer';
	_for_:     'The community';
	_license_: 'MIT. No GPLv2';
	ExtendWordPressWith::class;
} } } } } } } } } } } } } } } } } }

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
 * The plugin version.
 *
 * @since 0.0.1184
 */
const VERSION = '1.7.1184';

/**
 * The plugin database version.
 *
 * @since 0.0.1184
 */
const DB_VERSION = 1_7_1184;

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
\define( 'Troy\Server\PLUGIN_BASENAME', \plugin_basename( MAIN_FILE ) );

/**
 * The plugin slug.
 *
 * @since 0.0.1184
 */
\define( 'Troy\Server\PLUGIN_SLUG', \dirname( PLUGIN_BASENAME ) );

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
 * The "Tested up to" header is included for forward compatibility, anticipating
 * its standardization in WordPress core. We will adapt if the official key differs.
 * For now, "Tested up to" is used as the key, while WordPress may use "tested" in the future.
 * This is future-compatible.
 *
 * @since 0.0.1184
 */
const TROY_PLUGIN_HEADERS = [
	'repo'         => [ 'Troy' ],
	'dependencies' => [ 'Troy Dependency', 'Troy Dependencies' ],
	'tested_wp'    => [ 'Tested up to' ],
];

require ABSPATH . 'includes/constants.php';
require ABSPATH . 'bootstrap/load.php';

// Prepare plugin upgrader before the plugin boots.
API\Server::get_db_version() !== DB_VERSION
	and require ABSPATH . 'includes/upgrade.php';

if ( \is_admin() )
	require ABSPATH . 'bootstrap/hook-admin.php';

require ABSPATH . 'bootstrap/hook.php';
