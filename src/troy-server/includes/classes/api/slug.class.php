<?php
/**
 * @package Troy\Server\API
 * @api
 */

namespace Troy\Server\API;

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

// TEMP: PHPCS bugged with PHP 8.4 asymmetric visibility and property hooks.
// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable, Squiz.Commenting.VariableComment.Missing
// phpcs:disable PSR2.Classes.PropertyDeclaration.ScopeMissing, PSR2.Classes.PropertyDeclaration.Multiple
// phpcs:disable PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess.Found, Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Squiz.Commenting.VariableComment.WrongStyle

/**
 * Handles slug conflict detection and resolution across plugins and packages.
 *
 * Unlike most API classes, this one is instantiated to provide context-aware
 * slug validation between the troy_plugins and troy_packages tables.
 *
 * No one asked your opinion, you filthy little mud-blood.
 *
 * @since 0.0.1184
 */
final class Slug {

	/**
	 * @since 0.0.1184
	 * @var ?string $conflict_type The conflict type, lazily computed.
	 *                             'plugin', 'package', or null if no conflict.
	 */
	public private(set) ?string $conflict_type {
		get {
			if ( isset( $this->conflict_type ) )
				return $this->conflict_type;

			global $wpdb;

			// Check plugins table; id != 0 is always true, so it's a no-op when not excluding.
			$plugin_conflict = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}troy_plugins WHERE slug = %s AND id != %d",
				$this->slug,
				'plugin' === $this->for_type ? $this->exclude_id : 0,
			) );

			if ( $plugin_conflict )
				return $this->conflict_type = 'plugin';

			// Check packages table; id != 0 is always true, so it's a no-op when not excluding.
			$package_conflict = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}troy_packages WHERE slug = %s AND id != %d",
				$this->slug,
				'package' === $this->for_type ? $this->exclude_id : 0,
			) );

			if ( $package_conflict )
				return $this->conflict_type = 'package';

			return $this->conflict_type = null;
		}
	}

	/**
	 * @since 0.0.1184
	 * @var ?string $unique_slug The unique slug, lazily computed.
	 *                           The slug, possibly with a numeric suffix.
	 */
	public private(set) ?string $unique_slug {
		get {
			if ( isset( $this->unique_slug ) )
				return $this->unique_slug;

			if ( ! $this->conflict_type )
				return $this->unique_slug = $this->slug;

			global $wpdb;

			$pattern = '^' . $wpdb->esc_like( $this->slug ) . '(-[0-9]+)?$';

			$similar_slugs = array_unique( array_merge(
				$wpdb->get_col( $wpdb->prepare(
					"SELECT slug FROM {$wpdb->prefix}troy_plugins WHERE slug REGEXP %s",
					$pattern,
				) ) ?: [],
				$wpdb->get_col( $wpdb->prepare(
					"SELECT slug FROM {$wpdb->prefix}troy_packages WHERE slug REGEXP %s",
					$pattern,
				) ) ?: [],
			) );

			$max_suffix = 0;

			if ( $similar_slugs ) {
				natsort( $similar_slugs );

				$last_slug = end( $similar_slugs );

				if ( preg_match( '/-(\d+)$/', $last_slug, $matches ) )
					$max_suffix = (int) $matches[1];
			}

			++$max_suffix;

			return $this->unique_slug = substr(
				$this->slug,
				0,
				191 - \strlen( "-$max_suffix" ),
			) . "-$max_suffix";
		}
	}

	/**
	 * Constructor. You'll pay for that, Malfoy!
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug       The slug to validate.
	 * @param string $for_type   The entity type: 'plugin' or 'package'.
	 * @param int    $exclude_id The ID to exclude from same-type conflict checks.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $for_type,
		public readonly int $exclude_id = 0,
	) { }
}
