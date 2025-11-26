<?php
/**
 * @package Troy\Server\Bootstrap
 * @access  private
 */

namespace Troy\Server\Bootstrap\Hook;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	PLUGINS_CPT,
	PACKAGES_CPT,
};

use Troy\Server\{
	Admin_Menu,
	Admin_Scripts,
	Packages,
	Plugins,
	Settings,
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

// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- no love for goto.

admin: {
	\add_action( 'admin_init', [ Admin_Scripts::class, 'register_utils' ], 1 );
	\add_action( 'admin_menu', [ Admin_Menu::class, 'reorder_menu_items' ], 999 );
}

// We couldn't think of any useful settings yet, so we're disabling settings for now.
settings: {
	// Register the admin settings menu.
	\add_action( 'admin_menu', [ Settings\Main::class, 'register_admin_menu' ] );

	// Register the settings save action. TODO: Later, we don't have any settings yet.
	\add_action( 'admin_post_' . Settings\Main::SAVE_ACTION, [ Settings\Main::class, 'process_settings_submission' ] );
}

plugins: {
	// Register the Plugins\CPT assets.
	\add_action( 'enqueue_block_editor_assets', [ Plugins\CPT\Block_Editor::class, 'enqueue_editor_assets' ] );
	\add_action( 'enqueue_block_assets', [ Plugins\CPT\Block_Editor::class, 'enqueue_block_assets' ] );
	\add_filter( 'wp_theme_json_data_theme', [ Plugins\CPT\Block_Editor::class, 'adjust_theme_json' ] );

	// Register the custom columns for the CPT list table.
	\add_filter( 'manage_' . PLUGINS_CPT . '_posts_columns', [ Plugins\CPT\List_View::class, 'register_columns' ] );
	\add_action( 'manage_' . PLUGINS_CPT . '_posts_custom_column', [ Plugins\CPT\List_View::class, 'render_columns' ], 10, 2 );
	\add_filter( 'manage_edit-' . PLUGINS_CPT . '_sortable_columns', [ Plugins\CPT\List_View::class, 'register_sortable_columns' ] );
	\add_action( 'load-edit.php', [ Plugins\CPT\List_View::class, 'register_list_edit_hooks' ] );

	// Disable quick edit and bulk edit for the Plugins CPT.
	\add_filter( 'quick_edit_enabled_for_post_type', [ Plugins\CPT\List_View::class, 'disable_quick_edit' ], 10, 2 );
	\add_filter( 'bulk_actions-edit-' . PLUGINS_CPT, [ Plugins\CPT\List_View::class, 'disable_bulk_edit' ] );

	// Handle user deletion cleanup.
	\add_action( 'delete_user', [ Plugins\CPT\Store::class, 'handle_user_deletion' ], 10, 2 );

	// Register the block editor template for the CPT.
	\add_filter( 'block_editor_settings_all', [ Plugins\CPT\Block_Editor::class, 'register_block_editor_template' ], 10, 2 );
	\add_action( 'init', [ Plugins\CPT\Block_Editor::class, 'register_blocks' ] );
}

packages: {
	// Register package editor assets.
	\add_action( 'admin_enqueue_scripts', [ Packages\Meta_Boxes::class, 'enqueue_editor_assets' ] );
	\add_action( 'admin_enqueue_scripts', [ Packages\CPT\List_View::class, 'enqueue_editor_assets' ] );

	// Register package save nonce.
	\add_action( 'edit_form_top', [ Packages\CPT\Store::class, 'output_save_nonce' ] );

	// Register package admin notices and filter default post messages.
	\add_action( 'admin_notices', [ Packages\CPT\Store::class, 'display_admin_notices' ] );
	\add_filter( 'post_updated_messages', [ Packages\CPT\Store::class, 'filter_post_updated_messages' ] );

	// Register list view columns.
	\add_filter( 'manage_' . PACKAGES_CPT . '_posts_columns', [ Packages\CPT\List_View::class, 'register_columns' ] );
	\add_action( 'manage_' . PACKAGES_CPT . '_posts_custom_column', [ Packages\CPT\List_View::class, 'render_columns' ], 10, 2 );

	// Disable quick edit and bulk edit for the Packages CPT.
	\add_filter( 'quick_edit_enabled_for_post_type', [ Packages\CPT\List_View::class, 'disable_quick_edit' ], 10, 2 );
	\add_filter( 'bulk_actions-edit-' . PACKAGES_CPT, [ Packages\CPT\List_View::class, 'disable_bulk_edit' ] );
}
