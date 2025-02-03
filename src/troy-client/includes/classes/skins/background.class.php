<?php
/**
 * @package Troy\Client\Skins
 * @access  private
 */

namespace Troy\Client\Skins;

\defined( 'Troy\Client\ABSPATH' ) or die;

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
 * A custom installer skin for the Troy Installer.
 * It silences all output and only logs errors -- a background installer.
 *
 * @since 0.0.1184
 */
class Background extends \stdClass {

	/**
	 * @since 0.0.1184
	 *
	 * @var array[string|WP_Error] The errors that occurred during installation.
	 */
	public $errors = [];

	/**
	 * Handles installation error messages.
	 *
	 * @since 0.0.1184
	 *
	 * @param string|WP_Error $error The error message or \WP_Error object.
	 */
	public function error( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * @since 0.0.1184
	 * @param string $name      The method name.
	 * @param array  $arguments The method arguments.
	 * @return mixed|void
	 */
	public function __call( $name, $arguments ) { // phpcs:ignore, VariableAnalysis.CodeAnalysis.VariableAnalysis
		return null;
	}

	/**
	 * @since 0.0.1184
	 * @param string $name      The method name.
	 * @param array  $arguments The method arguments.
	 * @return mixed|void
	 */
	public static function __callStatic( $name, $arguments ) {  // phpcs:ignore, VariableAnalysis.CodeAnalysis.VariableAnalysis
		return null;
	}
}
