<?php
/**
 * @package Troy\Client\Bootstrap
 * @access private
 */

namespace Troy\Client\Bootstrap\Load;

\defined( 'Troy\Client\ABSPATH' ) or die;

use const Troy\Client\{
	ABSPATH,
	MAIN_FILE,
};

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

// Register autoloader.
spl_autoload_register( 'Troy\Client\Bootstrap\Load\autoload_classes', true, true );

// Handle plugin activation and deactivation.
\add_action( 'activate_' . MAIN_FILE, 'Troy\Client\Bootstrap\Load\on_plugin_activation' );
\add_action( 'deactivate_' . MAIN_FILE, 'Troy\Client\Bootstrap\Load\on_plugin_deactivation' );

/**
 * Autoloads all class files. To be used when requiring access to all or any of
 * the plugin classes.
 *
 * @since 0.0.1184
 *
 * @param string $class The class name.
 * @return void Early if the class is not within the current namespace.
 */
function autoload_classes( $class ) {

	$class = strtolower( $class );

	if ( ! str_starts_with( $class, 'troy\client\\' ) ) return;

	$class = strtr(
		substr( $class, 12 ), // remove the "troy\client\"
		[
			'\\' => \DIRECTORY_SEPARATOR,
			'_'  => '-',
		],
	);

	require ABSPATH . "includes/classes/$class.class.php";
}

/**
 * Runs on plugin activation.
 *
 * @hook activate_{\Troy\Client\PLUGIN_BASENAME} 10
 * @since 0.0.1184
 *
 * @param bool $network_wide Whether the plugin is being activated network-wide.
 */
function on_plugin_activation( $network_wide ) {
	require ABSPATH . 'bootstrap/on-plugin-activation.php';
}

/**
 * Runs on plugin deactivation.
 *
 * @hook deactivate_{\Troy\Client\PLUGIN_BASENAME} 10
 * @since 0.0.1184
 *
 * @param bool $network_wide Whether the plugin is being activated network-wide.
 */
function on_plugin_deactivation( $network_wide ) {
	require ABSPATH . 'bootstrap/on-plugin-deactivation.php';
}
