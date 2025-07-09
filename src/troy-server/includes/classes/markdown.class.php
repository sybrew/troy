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
 * Class Troy\Server\Markdown.
 *
 * Also handles autoloading for the Markdown library classes.
 *
 * @since 0.0.1184
 */
class Markdown {

	/**
	 * Processes the given content as Markdown.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $content The content to process.
	 * @param string $type    The type of content to process. Optional.
	 *                        Default is 'common'. Accepts 'common' and 'github'.
	 * @param array  $ops     {
	 *     Options for processing the content. Optional.
	 *
	 *     @type bool   $convert_wordpress       Whether to convert WordPress-specific
	 *                                           Markdown syntax. Default is true.
	 *     @type string $html_input              How to handle HTML input.
	 *                                           Default is 'strip'. Accepts 'strip', 'allow', or 'escape'.
	 *     @type bool   $allow_unsafe_links      Whether to allow unsafe links.
	 *                                           Default is false.
	 *     @type int    $max_nesting_level       Maximum nesting level for Markdown elements.
	 *                                           Default is PHP_INT_MAX.
	 *     @type int    $max_delimiters_per_line Maximum number of delimiters per line.
	 *                                           Default is PHP_INT_MAX.
	 *     @type array  $renderer                {
	 *         Renderer options, such as block and inner separators.
	 *
	 *         @type string $block_separator The separator between blocks.
	 *                                       Default is "\n".
	 *         @type string $inner_separator The separator between inner elements.
	 *                                       Default is "\n".
	 *         @type string $soft_break      The soft break character.
	 *                                       Default is "\n".
	 *     }
	 *     @type array  $slug_normalizer         {
	 *         Slug normalizer options.
	 *
	 *         @type object $instance   The instance of the slug normalizer to use.
	 *         @type int    $max_length The maximum length of the slug.
	 *                                  Default is 255.
	 *         @type string $unique     The uniqueness of the slug. Can be
	 *                                  'disabled', 'per_environment', or 'per_document'.
	 *                                  Default is 'per_document'.
	 *     }
	 * }
	 * @return string The processed content.
	 *                WARNING: Assume it isn't safe for output!
	 *                Use wp_kses_post() or similar to sanitize it before outputting.
	 */
	public static function process( $content, $type = 'common', $ops = [] ) {

		static::autoload_dependencies();

		if ( $ops['convert_wordpress'] ?? true ) {
			// Convert `= title =` to `## title`. That's it for WP Markdown.
			$content = preg_replace( '/^= (.+?) =$/m', '## $1', $content );
		}

		$class = match ( $type ) {
			'github' => 'League\CommonMark\GithubFlavoredMarkdownConverter',
			default  => 'League\CommonMark\CommonMarkConverter',
		};

		return new $class( $ops )->convert( $content );
	}

	/**
	 * Autoloads the dependencies required for the Markdown processing.
	 *
	 * @since 0.0.1184
	 */
	private static function autoload_dependencies() {

		static $loaded_depencencies;

		if ( $loaded_depencencies )
			return;

		$base_dir = ABSPATH . 'vendor/markdown/';

		$prefix_map = [
			'dflydev\dotaccessdata\\' => "{$base_dir}dflydev-dflydev-dot-access-data/src/",
			'league\commonmark\\'     => "{$base_dir}thephpleague-commonmark/src/",
			'league\config\\'         => "{$base_dir}thephpleague-config/src/",
			'nette\schema\\'          => "{$base_dir}nette-schema/src/Schema/",
			'nette\\'                 => "{$base_dir}nette-utils/src/", // Note it's after the schema namespace.
			'psr\eventdispatcher\\'   => "{$base_dir}php-fig-event-dispatcher/src/",
		];

		// These cannot feasibly be autoloaded -- too many classes in one file.
		require "{$base_dir}nette-utils/src/exceptions.php";

		spl_autoload_register(
			function ( $class ) use ( $prefix_map ) {
				$class = strtolower( $class );

				foreach ( $prefix_map as $prefix => $path ) {
					// Check if the class uses the namespace prefix.
					if ( ! str_starts_with( $class, $prefix ) )
						continue;

					// Get the relative class name.
					$relative_class = substr( $class, strlen( $prefix ) );

					// Replace the namespace prefix with the base directory, replace
					// namespace separators with directory separators, and append .php
					$file = $path . str_replace( '\\', '/', $relative_class ) . '.php';

					require $file;
					break;
				}
			},
		);

		$loaded_depencencies = true;
	}
}
