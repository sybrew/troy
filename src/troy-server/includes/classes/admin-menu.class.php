<?php
/**
 * @package Troy\Server\Classes
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
 * Handles admin menu positioning.
 *
 * @since 1.0.0
 */
final class Admin_Menu {

	/**
	 * Reorders custom post type menu items.
	 *
	 * Collects post types with menu positions starting with 3.1184 and ensures
	 * they are placed at their correct positions in the admin menu.
	 *
	 * @hook admin_menu 999
	 * @since 1.0.0
	 */
	public static function reorder_menu_items() {

		global $wp_post_types, $menu;

		$target_menus = [];

		// Collect post types with menu positions starting with 3.1184
		foreach ( $wp_post_types as $post_type => $post_type_obj )
			if ( str_starts_with( (string) $post_type_obj?->menu_position, '3.1184' ) )
				// PHP implicitely converts keys from floats to integers in list arrays. Set to string to bypass.
				$target_menus[ (string) $post_type_obj?->menu_position ] = $post_type_obj->name;

		// Sort by position to maintain order
		ksort( $target_menus, SORT_NUMERIC );

		// Find and remove these items from current menu positions
		$items_to_move = [];
		foreach ( $menu as $position => $menu_item ) {
			$menu_slug = $menu_item[2];

			// Check if this menu item belongs to one of our target post types
			foreach ( $target_menus as $target_position => $post_type ) {
				if ( str_contains( $menu_slug, $post_type ) ) {
					$items_to_move[ $target_position ] = $menu_item;
					unset( $menu[ $position ] );
					break;
				}
			}
		}

		// Re-insert items at their correct positions
		foreach ( $items_to_move as $position => $menu_item )
			$menu[ $position ] = $menu_item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Bug in WP.
	}
}
