<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server\Plugins\CPT;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	VERSION,
	PLUGINS_CPT,
};

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
 * Class Troy\Server\Plugins\CPT\Init.
 *
 * @since 0.0.1184
 */
class Init {

	/**
	 * Register the Troy Plugins custom post type.
	 *
	 * We only use this post type to store the plugins that are available for download.
	 * Via the custom high-performance tables, we will serve the plugins via the APIs.
	 *
	 * @hook init 10
	 * @since 0.0.1184
	 */
	public static function register_post_types() {

		// TODO implement the template Just In Time.
		\register_post_type(
			PLUGINS_CPT,
			[
				'description'        => \__( 'A list of Troy plugins.', 'troy-server' ),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'has_archive'        => true,
				'rewrite'            => [
					'slug'       => 'plugins',
					'with_front' => true,
					'ep_mask'    => EP_PERMALINK,
				],
				'menu_icon'          => 'dashicons-admin-plugins',
				'menu_position'      => 2, // Above 'Troy Server'.
				'capability_type'    => 'page', // Maybe later: Use something custom.
				'supports'           => [ 'title', 'editor', 'media', 'custom-fields' ],
				'labels'             => [
					'name'                  => \_x( 'Plugins', 'Post type general name', 'troy-server' ),
					'singular_name'         => \_x( 'Plugin', 'Post type singular name', 'troy-server' ),
					'menu_name'             => \_x( 'Repo Plugins', 'Admin Menu text', 'troy-server' ),
					'name_admin_bar'        => \_x( 'Repo Plugin', 'Add New on Toolbar', 'troy-server' ),
					'add_new'               => \__( 'Add New', 'troy-server' ),
					'add_new_item'          => \__( 'Add New Plugin', 'troy-server' ),
					'new_item'              => \__( 'New Plugin', 'troy-server' ),
					'edit_item'             => \__( 'Edit Plugin', 'troy-server' ),
					'view_item'             => \__( 'View Plugin', 'troy-server' ),
					'all_items'             => \__( 'All Plugins', 'troy-server' ),
					'search_items'          => \__( 'Search Plugins', 'troy-server' ),
					'not_found'             => \__( 'No plugins found.', 'troy-server' ),
					'not_found_in_trash'    => \__( 'No plugins found in Trash.', 'troy-server' ),
					'featured_image'        => \_x( 'Plugin Featured Image', 'Overrides the "Featured Image" phrase', 'troy-server' ),
					'set_featured_image'    => \_x( 'Set featured image', 'Overrides the "Set featured image" phrase', 'troy-server' ),
					'remove_featured_image' => \_x( 'Remove featured image', 'Overrides the "Remove featured image" phrase', 'troy-server' ),
					'use_featured_image'    => \_x( 'Use as featured image', 'Overrides the "Use as featured image" phrase', 'troy-server' ),
					'archives'              => \_x( 'Plugin archives', 'The post type archive label used in nav menus', 'troy-server' ),
					'attributes'            => \_x( 'Plugin attributes', 'The post type attributes label', 'troy-server' ),
					'insert_into_item'      => \_x( 'Insert into plugin', 'Overrides the "Insert into post" phrase', 'troy-server' ),
					'uploaded_to_this_item' => \_x( 'Uploaded to this plugin', 'Overrides the "Uploaded to this post" phrase', 'troy-server' ),
					'filter_items_list'     => \_x( 'Filter plugins list', 'Screen reader text for the filter links', 'troy-server' ),
					'items_list_navigation' => \_x( 'Plugins list navigation', 'Screen reader text for the pagination', 'troy-server' ),
					'items_list'            => \_x( 'Plugins list', 'Screen reader text for the items list', 'troy-server' ),
				],
			],
		);

		// These won't be automatically selectable. That's because get_post_statuses() is hardcoded to suck.
		// See https://core.trac.wordpress.org/ticket/12706 and https://core.trac.wordpress.org/ticket/23174.
		\register_post_status(
			'public',
			[
				'label'                     => \__( 'Public', 'troy-server' ),
				'public'                    => true,
				'internal'                  => false,
				'protected'                 => false,
				'private'                   => false,
				'publicly_queryable'        => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of public plugins */
				'label_count'               => \_n_noop( 'Public <span class="count">(%s)</span>', 'Public <span class="count">(%s)</span>', 'troy-server' ),
			],
		);
		\register_post_status(
			'unlisted',
			[
				'label'                     => \__( 'Unlisted', 'troy-server' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'private'                   => true,
				'publicly_queryable'        => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of unlisted plugins */
				'label_count'               => \_n_noop( 'Unlisted <span class="count">(%s)</span>', 'Unlisted <span class="count">(%s)</span>', 'troy-server' ),
			],
		);
		\register_post_status(
			'protected',
			[
				'label'                     => \__( 'Protected', 'troy-server' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'private'                   => true,
				'publicly_queryable'        => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of protected plugins */
				'label_count'               => \_n_noop( 'Protected <span class="count">(%s)</span>', 'Protected <span class="count">(%s)</span>', 'troy-server' ),
			],
		);
		\register_post_status(
			'pending',
			[
				'label'                     => \__( 'Pending', 'troy-server' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'private'                   => true,
				'publicly_queryable'        => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of pending plugins */
				'label_count'               => \_n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'troy-server' ),
			],
		);
		\register_post_status(
			'disabled',
			[
				'label'                     => \__( 'Disabled', 'troy-server' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'private'                   => true,
				'publicly_queryable'        => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of disabled plugins */
				'label_count'               => \_n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'troy-server' ),
			],
		);
	}

	/**
	 * Register the Troy Plugins taxonomies.
	 *
	 * @hook init 10
	 * @since 0.0.1184
	 */
	public static function register_taxonomies() {

		\register_taxonomy(
			PLUGINS_CPT . '_category',
			PLUGINS_CPT,
			[
				'label'             => \__( 'Plugin Categories', 'troy-server' ),
				'public'            => true,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'sort'              => false,
				'rewrite'           => [
					'hierarchical' => false,
					'slug'         => 'category',
					'with_front'   => false,
					'ep_mask'      => EP_TAGS,
				],
				'labels'            => [
					'name'              => \__( 'Plugin Categories', 'troy-server' ),
					'singular_name'     => \__( 'Plugin Category', 'troy-server' ),
					'search_items'      => \__( 'Search Plugin Categories', 'troy-server' ),
					'all_items'         => \__( 'All Plugin Categories', 'troy-server' ),
					'parent_item'       => \__( 'Parent Plugin Category', 'troy-server' ),
					'parent_item_colon' => \__( 'Parent Plugin Category:', 'troy-server' ),
					'edit_item'         => \__( 'Edit Plugin Category', 'troy-server' ),
					'update_item'       => \__( 'Update Plugin Category', 'troy-server' ),
					'add_new_item'      => \__( 'Add New Plugin Category', 'troy-server' ),
					'new_item_name'     => \__( 'New Plugin Category Name', 'troy-server' ),
					'menu_name'         => \__( 'Plugin Categories', 'troy-server' ),
				],
			],
		);
	}
}
