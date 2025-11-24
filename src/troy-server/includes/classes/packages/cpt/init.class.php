<?php
/**
 * @package Troy\Server\Packages\CPT
 * @access  private
 */

namespace Troy\Server\Packages\CPT;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\PACKAGES_CPT;

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
 * Class Troy\Server\Packages\CPT\Init.
 *
 * @since 0.0.1184
 */
final class Init {

	/**
	 * Register the Troy Packages custom post type.
	 *
	 * We only use this post type to store the packages that are available for download.
	 * Via the custom high-performance tables, we will serve the packages via the APIs.
	 *
	 * @hook init 10
	 * @since 0.0.1184
	 */
	public static function register_post_types() {

		\register_post_type(
			PACKAGES_CPT,
			[
				'description'        => \__( 'A list of Troy packages.', 'troy-server' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_rest'       => false,
				'has_archive'        => false,
				'menu_icon'          => 'dashicons-archive',
				'menu_position'      => 3.1184_05,
				'capability_type'    => 'page',
				'supports'           => [ 'title' ],
				'labels'             => [
					'name'                  => \_x( 'Packages', 'Post type general name', 'troy-server' ),
					'singular_name'         => \_x( 'Package', 'Post type singular name', 'troy-server' ),
					'menu_name'             => \_x( 'Repo Packages', 'Admin Menu text', 'troy-server' ),
					'name_admin_bar'        => \_x( 'Package', 'Add New on Toolbar', 'troy-server' ),
					'add_new'               => \__( 'Add New', 'troy-server' ),
					'add_new_item'          => \__( 'Add New Package', 'troy-server' ),
					'new_item'              => \__( 'New Package', 'troy-server' ),
					'edit_item'             => \__( 'Edit Package', 'troy-server' ),
					'view_item'             => \__( 'View Package', 'troy-server' ),
					'all_items'             => \__( 'All Packages', 'troy-server' ),
					'search_items'          => \__( 'Search Packages', 'troy-server' ),
					'not_found'             => \__( 'No packages found.', 'troy-server' ),
					'not_found_in_trash'    => \__( 'No packages found in Trash.', 'troy-server' ),
					'filter_items_list'     => \_x( 'Filter packages list', 'Screen reader text for the filter links', 'troy-server' ),
					'items_list_navigation' => \_x( 'Packages list navigation', 'Screen reader text for the pagination', 'troy-server' ),
					'items_list'            => \_x( 'Packages list', 'Screen reader text for the items list', 'troy-server' ),
				],
			],
		);
	}
}
