<?php
/**
 * @package Troy\Server
 * @access  private
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
 * The plugin custom post type.
 *
 * @since 0.0.1184
 */
const PLUGINS_CPT = 'troy-server-plugins';

/**
 * The package custom post type.
 *
 * @since 0.0.1184
 */
const PACKAGES_CPT = 'troy-server-packages';

/**
 * The plugin REST API namespaces and bases.
 *
 * @since 0.0.1184
 */
const REST_NS = [
	'plugins_manage'       => [
		'namespace'  => 'troy-server/v1',
		'base'       => 'plugins/manage',
		'access_cap' => 'edit_pages', // TODO shouldn't this be an option?
	],
	'plugins_integrations' => [
		'namespace'  => 'troy-server/v1',
		'base'       => 'plugins/integrations',
		'access_cap' => 'edit_pages',
	],
	'stats_dashboard'      => [
		'namespace'  => 'troy-server/v1',
		'base'       => 'stats',
		'access_cap' => 'manage_options',
	],
];
