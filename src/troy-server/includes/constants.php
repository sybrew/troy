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
 * Copyright (c) 2025 - 2026 Sybre Waaijer, CyberWire B.V.
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
	'packages_manage'      => [
		'namespace'  => 'troy-server/v1',
		'base'       => 'packages/manage',
		'access_cap' => 'edit_pages',
	],
	'stats_dashboard'      => [
		'namespace'  => 'troy-server/v1',
		'base'       => 'stats',
		'access_cap' => 'manage_options',
	],
	'logs_dashboard'       => [
		'namespace'  => 'troy-server/v1',
		'base'       => 'logs',
		'access_cap' => 'manage_options',
	],
	'settings'             => [
		'namespace'  => 'troy-server/v1',
		'base'       => 'settings',
		'access_cap' => 'manage_options',
	],
];

/**
 * The number of seconds after an epoch ends before we finalize it.
 * 48 hours = 172800 seconds.
 *
 * @since 0.0.1184
 */
const STATS_AGGREGATOR_EPOCH_FINALIZE_DELAY = 48 * \HOUR_IN_SECONDS;

/**
 * The number of IDs to process per batch.
 *
 * With STATS_AGGREGATOR_EPOCH_FINALIZE_DELAY at 48 hours and cron running every 10 minutes,
 * we can process up to 28_800 plugins/packages before risking data loss (6 runs/hour × 48 hours × 100).
 *
 * @since 0.0.1184
 */
const STATS_AGGREGATOR_BATCH_SIZE = 100;
