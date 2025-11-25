<?php
/**
 * @package Troy\Server\Plugins\CPT
 * @access  private
 */

namespace Troy\Server\Plugins\CPT;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	MAIN_FILE,
	PLUGINS_CPT,
	VERSION,
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
 * Class Troy\Server\Plugins\CPT\List_View.
 *
 * @since 0.0.1184
 */
final class List_View {

	/**
	 * Disable quick edit for the Plugins post type.
	 *
	 * Quick edit bypasses our integration system and could cause data inconsistencies.
	 *
	 * @hook quick_edit_enabled_for_post_type 10
	 * @since 0.0.1184
	 *
	 * @param Boolean $enabled   Whether quick edit is enabled.
	 * @param string  $post_type The post type.
	 * @return Boolean False for plugins, unchanged otherwise.
	 */
	public static function disable_quick_edit( $enabled, $post_type ) {

		if ( PLUGINS_CPT === $post_type )
			return false;

		return $enabled;
	}

	/**
	 * Disable bulk edit for the Plugins post type.
	 *
	 * Bulk edit bypasses our integration system and could cause data inconsistencies.
	 *
	 * @hook bulk_actions-edit-troy_plugins 10
	 * @since 0.0.1184
	 *
	 * @param array $actions The bulk actions.
	 * @return array The filtered bulk actions.
	 */
	public static function disable_bulk_edit( $actions ) {

		unset( $actions['edit'] );

		return $actions;
	}

	/**
	 * Defines the columns available for the CPT list table, including sorting and searching configuration.
	 *
	 * This method returns a static array defining the columns for the 'troy-plugin' CPT list table.
	 * Each key in the array represents a column identifier, and the value is an array
	 * detailing how the column should be handled in terms of display, sorting, and searching.
	 *
	 * @TODO bug: When searching for a non-unique value, the rows in the list-table get multiplied by number of occurrences.
	 * @TODO bug: When setting a before/after looking on a key that isn't parsed yet, it'll always be put after everything.
	 * @TODO Abstract this configuration to a common class applicable to other CPTs (e.g., themes).
	 * @since 0.0.1184
	 *
	 * @return array[] {
	 *     An associative array with unique column sorting keys for the CPT list table configuration.
	 *     The column keys must be unique and contain only alphanumeric characters and underscores.
	 *
	 *     @type array $column_config {
	 *         Configuration for a specific list table column.
	 *
	 *         @type string      $label    The translated display name for the column header.
	 *         @type array       $where    {
	 *             Database location for the column's data.
	 *
	 *             @type string $table The database table name (without prefix).
	 *             @type string $key   The column name within that table.
	 *         }
	 *         @type string|array $postfind {
	 *             Defines how to link the data back to a WordPress post ID.
	 *             If string: The name of the column in the 'where' table that directly contains the post ID.
	 *             If array: Describes a join needed to find the post ID: {
	 *
	 *                 @type string $local_key        Column name in the 'where' table used for the join.
	 *                 @type array  $foreign          {
	 *                     Foreign table details for the join.
	 *
	 *                     @type string $table Foreign table name (without prefix).
	 *                     @type string $key   Column name in the foreign table to join on.
	 *                 }
	 *                 @type string $foreign_postfind Column name in the 'foreign' table containing the post ID.
	 *             }
	 *         }
	 *         @type bool        $orderby  Indicates if the column should be sortable.
	 *         @type bool        $search   Indicates if the column's data should be included in searches.
	 *         @type string      $width    Optional. CSS width value for the column (e.g., '48px', '100px').
	 *         @type array       $before   Optional. Array of column keys to position this column before.
	 *                                     Note that the order of similar entries determine the display order.
	 *         @type array       $after    Optional. Array of column keys to position this column after.
	 *                                     Note that earlier entries will be placed before later ones.
	 *         @type bool        $mobile   Optional. Whether to show this column on mobile devices. Default true.
	 *         @type callable    $render   Optional. Callback function to render custom output for the column.
	 *                                     Receives the column value as parameter 1, and the post ID as parameter 2.
	 *     }
	 * }
	 */
	private static function get_columns() {
		// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis -- Still no support for return null coalescing assignment.
		static $columns;
		return $columns ??= [
			'troy_server_logo'              => [
				'label'    => \__( 'Logo', 'troy-server' ),
				'where'    => [ 'troy_plugins_metas', 'logo_uri' ], // We'll call these "table" and "key" to avoid confusion.
				'postfind' => [ // Complex lookup via through, we want to find the plugin_id from the post_id.
					'local_key'        => 'plugin_id',              // We know this.
					'foreign'          => [ 'troy_plugins', 'id' ], // We can match 'on' with this table.
					'foreign_postfind' => 'post_id',                // Wherein we can find the post ID as this. Let's not loop.
				],
				'orderby'  => false,
				'search'   => false,
				'width'    => '48px',
				'before'   => [ 'title' ],
				'mobile'   => false,
				'render'   => function ( $value ) {
					$value and printf(
						'<img src="%s" alt="%s" style="%s">',
						\esc_url( $value ),
						\esc_attr__( 'Logo', 'troy-server' ),
						'aspect-ratio:1/1;width:48px;height:48px;object-fit:cover',
					);
				},
			],
			'troy_server_plugin_id'         => [
				'label'    => \__( 'Plugin ID', 'troy-server' ),
				'where'    => [ 'troy_plugins', 'id' ], // We'll call these "table" and "key" to avoid confusion.
				'postfind' => 'post_id',                // The post ID index of the where table.
				'orderby'  => true,
				'search'   => true,
				'after'    => [ 'title' ],
			],
			'troy_server_slug'              => [
				'label'    => \__( 'Plugin slug', 'troy-server' ),
				'where'    => [ 'troy_plugins', 'slug' ], // We'll call these "table" and "key" to avoid confusion.
				'postfind' => 'post_id',                  // The post ID index of the where table.
				'orderby'  => true,
				'search'   => true,
				'after'    => [ 'title', 'troy_server_logo' ],
			],
			'troy_server_short_description' => [
				'label'    => \__( 'Short description', 'troy-server' ),
				'where'    => [ 'troy_plugins_metas', 'short_description' ], // We'll call these "table" and "key" to avoid confusion.
				'postfind' => [ // Complex lookup via through, we want to find the plugin_id from the post_id.
					'local_key'        => 'plugin_id',              // We know this.
					'foreign'          => [ 'troy_plugins', 'id' ], // We can match 'on' with this table.
					'foreign_postfind' => 'post_id',                // Wherein we can find the post ID as this. Let's not loop.
				],
				'orderby'  => false,
				'search'   => true,
				'after'    => [ 'troy_server_slug' ],
			],
			'troy_server_integration' => [
				'label'    => \__( 'Integration', 'troy-server' ),
				'where'    => [ 'troy_plugins_integrations', 'mode' ], // We'll call these "table" and "key" to avoid confusion.
				'postfind' => [ // Complex lookup via through, we want to find the plugin_id from the post_id.
					'local_key'        => 'plugin_id',              // We know this.
					'foreign'          => [ 'troy_plugins', 'id' ], // We can match 'on' with this table.
					'foreign_postfind' => 'post_id',                // Wherein we can find the post ID as this. Let's not loop.
				],
				'orderby'  => true,
				'search'   => true,
				'after'    => [ 'troy_server_short_description' ],
			],
		];
		// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis
	}

	/**
	 * Registers the hooks for the CPT list table.
	 *
	 * We do this here to avoid filtering the queries everywhere.
	 *
	 * @TODO abstract this to a common class for all CPTs (i.e., themes).
	 * @hook load-edit.php
	 * @since 0.0.1184
	 */
	public static function register_list_edit_hooks() {

		// Only add the filter if the current screen is the plugins list table.
		if ( \get_current_screen()->post_type !== PLUGINS_CPT )
			return;

		// Remit FETCH_CLASS_NAME opcode, which performs a function call to check if it's valid.
		$class = static::class;

		\add_filter( 'posts_join', [ $class, 'register_custom_fields' ], 10, 2 );
		\add_filter( 'posts_orderby', [ $class, 'order_columns_by_custom_fields' ], 10, 2 );
		\add_filter( 'posts_search', [ $class, 'search_columns_by_custom_fields' ], 10, 2 );

		// Enqueue list table styles.
		\add_action( 'admin_enqueue_scripts', [ $class, 'enqueue_list_table_scripts' ] );
	}

	/**
	 * Register the custom columns for the CPT list table.
	 *
	 * @hook manage_{Troy\Server\PLUGINS_CPT}_posts_columns
	 * @since 0.0.1184
	 *
	 * @param array $columns The columns to register.
	 * @return array The registered columns.
	 */
	public static function register_columns( $columns ) {

		$after_counts = [];

		foreach ( static::get_columns() as $index => $conf ) {
			$column_keys = array_keys( $columns );
			$offset      = false;

			// Find first valid position
			foreach ( [ 'before', 'after' ] as $type ) {
				if ( empty( $conf[ $type ] ) )
					continue;

				foreach ( $conf[ $type ] as $key ) {
					// This offset is also used for 'before'; don't move it down.
					$offset = array_search( $key, $column_keys, true );

					if ( false !== $offset ) {
						if ( 'after' === $type ) {
							// Keep track of how many times we've added this column after the key.
							// This is to ensure we don't revert the order of columns that are added after the same key.
							$offset              += 1 + ( $after_counts[ $key ] ?? 0 );
							$after_counts[ $key ] = ( $after_counts[ $key ] ?? 0 ) + 1;
						}
						break 2;
					}
				}
			}

			if ( false === $offset ) {
				$columns[ $index ] = $conf['label'];
			} else {
				$before  = array_splice( $columns, 0, $offset );
				$columns = $before + [ $index => $conf['label'] ] + $columns;
			}
		}

		return $columns;
	}

	/**
	 * Register the sortable columns for the CPT list table.
	 *
	 * @hook manage_edit-{Troy\Server\PLUGINS_CPT}_sortable_columns
	 * @since 0.0.1184
	 *
	 * @param array $columns The columns to register.
	 * @return array The registered columns.
	 */
	public static function register_sortable_columns( $columns ) {

		foreach ( static::get_columns() as $index => $conf ) {
			if ( empty( $conf['orderby'] ) )
				continue;

			$columns[ $index ] = $index;
		}

		return $columns;
	}

	/**
	 * Makes CPT data available for listing, searching, and sorting.
	 *
	 * @hook posts_join
	 * @since 0.0.1184
	 *
	 * @param string    $join  The join clause.
	 * @param \WP_Query $query The query object.
	 * @return string The modified join clause.
	 */
	public static function register_custom_fields( $join, $query ) {

		// Check if it's the correct post type and main query.
		if ( $query->get( 'post_type' ) !== PLUGINS_CPT || ! $query->is_main_query() )
			return $join;

		global $wpdb;

		$sortables     = static::get_columns();
		$joined_tables = []; // Keep track of joined tables to avoid duplicates.

		$primary_table = $wpdb->posts;

		foreach ( $sortables as $column_data ) {
			[ $table ] = $column_data['where'];
			$postfind  = $column_data['postfind'];

			if ( \is_array( $postfind ) ) {
				// Complex join involving an intermediate table.
				$local_key           = $postfind['local_key'];
				[ $f_table, $f_key ] = $postfind['foreign'];
				$f_postfind          = $postfind['foreign_postfind'];

				$intermediate_table_alias = "{$wpdb->prefix}$f_table";
				$final_table_alias        = "{$wpdb->prefix}$table";

				// Join the intermediate table if not already joined.
				if ( ! isset( $joined_tables[ $intermediate_table_alias ] ) ) {
					$join .= " LEFT JOIN `$intermediate_table_alias` ON `$intermediate_table_alias`.`$f_postfind` = `$primary_table`.ID";

					$joined_tables[ $intermediate_table_alias ] = true;
				}

				// Join the final table if not already joined.
				if ( ! isset( $joined_tables[ $final_table_alias ] ) ) {
					// Ensure the intermediate table is joined first (should be guaranteed by the check above).
					$join .= " LEFT JOIN `$final_table_alias` ON `$final_table_alias`.`$local_key` = `$intermediate_table_alias`.`$f_key`";

					$joined_tables[ $final_table_alias ] = true;
				}
			} else {
				// Simple direct join.
				$final_table_alias = "{$wpdb->prefix}$table";

				if ( ! isset( $joined_tables[ $final_table_alias ] ) ) {
					$join .= " LEFT JOIN `$final_table_alias` ON `$final_table_alias`.`$postfind` = `$primary_table`.ID";

					$joined_tables[ $final_table_alias ] = true;
				}
			}
		}

		return $join;
	}

	/**
	 * Sort the CPT list table by the custom columns.
	 *
	 * @hook posts_orderby
	 * @since 0.0.1184
	 *
	 * @param string    $orderby The orderby clause.
	 * @param \WP_Query $query The query object.
	 * @return string The modified orderby clause.
	 */
	public static function order_columns_by_custom_fields( $orderby, $query ) {

		$order_key = $query->get( 'orderby' );
		$sortables = static::get_columns();

		if ( empty( $sortables[ $order_key ] ) || empty( $sortables[ $order_key ]['orderby'] ) )
			return $orderby;

		global $wpdb;

		[ $table, $key ] = $sortables[ $order_key ]['where'];

		$direction = 'ASC' === strtoupper( $query->get( 'order' ) )
			? 'ASC'
			: 'DESC';

		return "`{$wpdb->prefix}$table`.`$key` $direction";
	}

	/**
	 * Search the CPT list table by the custom columns.
	 *
	 * @hook posts_search
	 * @since 0.0.1184
	 *
	 * @param string    $search The search clause.
	 * @param \WP_Query $query The query object.
	 * @return string The modified search clause.
	 */
	public static function search_columns_by_custom_fields( $search, $query ) {

		if (
			   $query->get( 'post_type' ) !== PLUGINS_CPT
			|| ! $query->is_main_query()
			|| ! $query->is_search()
		) return $search;

		$search_q = preg_replace(
			'/[\s\n\r\v]+/',
			' ',
			\esc_sql( stripslashes( trim( $query->get( 's' ) ) ) ),
		);

		if ( empty( $search_q ) )
			return $search;

		global $wpdb;

		$like      = \esc_sql( '%' . $wpdb->esc_like( $search_q ) . '%' );
		$sortables = static::get_columns();
		$clauses   = [];

		foreach ( $sortables as $sortable ) {
			if ( empty( $sortable['search'] ) )
				continue;

			[ $table, $key ] = $sortable['where'];

			$clauses[] = "(`{$wpdb->prefix}$table`.`$key` LIKE '$like')";
		}

		if ( ! $clauses )
			return $search;

		if ( $search ) {
			// only replace the final )) so we don't break the core grouping.
			$search = preg_replace(
				'/([)]+?)\s*$/',
				' OR ' . implode( ' OR ', $clauses ) . '$1',
				$search,
				1,
			);
		} else {
			$search = ' AND (' . implode( ' OR ', $clauses ) . ')';
		}

		return $search;
	}

	/**
	 * Renders the custom columns for the CPT list table.
	 *
	 * @hook manage_{Troy\Server\PLUGINS_CPT}_posts_custom_column
	 * @since 0.0.1184
	 *
	 * @param string $column The column to render.
	 * @param int    $post_id The post ID.
	 */
	public static function render_columns( $column, $post_id ) {

		$sortables = static::get_columns();

		if ( ! isset( $sortables[ $column ] ) )
			return;

		global $wpdb;

		[ $table, $key ] = $sortables[ $column ]['where'];
		$postfind        = $sortables[ $column ]['postfind'];

		if ( \is_array( $postfind ) ) {
			$local_key           = $postfind['local_key'];
			[ $f_table, $f_key ] = $postfind['foreign'];
			$f_postfind          = $postfind['foreign_postfind'];

			static $foreign_key_value = []; // Cache foreign key values to avoid multiple queries.

			// Create pointer to the cached foreign key value to remit FETCH_DIM_W opcode.
			$fc_ptr = &$foreign_key_value[ "{$f_table}_{$f_key}_{$f_postfind}_{$post_id}" ];

			// Get the foreign key value first
			$fc_ptr ??= $wpdb->get_var( $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Strings defined at static::get_columns().
				"SELECT `$f_key` FROM `{$wpdb->prefix}$f_table` WHERE `$f_postfind` = %d",
				$post_id,
			) ) ?: false; // Set to false to avoid querying again if not found.

			if ( $fc_ptr ) {
				$value = $wpdb->get_var( $wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Strings defined at static::get_columns().
					"SELECT `$key` FROM `{$wpdb->prefix}$table` WHERE `$local_key` = %d",
					$fc_ptr,
				) );
			} else {
				$value = null;
			}
		} else {
			// $postfind is a string.
			$value = $wpdb->get_var( $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Strings defined at static::get_columns().
				"SELECT `$key` FROM `{$wpdb->prefix}$table` WHERE `$postfind` = %d",
				$post_id,
			) );
		}

		// Render the value.
		if ( isset( $sortables[ $column ]['render'] ) && \is_callable( $sortables[ $column ]['render'] ) ) {
			$sortables[ $column ]['render']( $value, $post_id );
		} else {
			echo \esc_html( $value );
		}
	}

	/**
	 * Enqueues styles and scripts for the CPT list table.
	 * Actually, we just print it. But we should enqueue it here when we have a need for it.
	 *
	 * @hook admin_enqueue_scripts
	 * @since 0.0.1184
	 */
	public static function enqueue_list_table_scripts() {

		$width_columns         = [];
		$hidden_mobile_columns = [];

		foreach ( static::get_columns() as $column_id => $config ) {
			// Collect columns with similar properties
			if ( isset( $config['width'] ) )
				$width_columns[ $config['width'] ][] = $column_id;

			if ( isset( $config['mobile'] ) && ! $config['mobile'] )
				$hidden_mobile_columns[] = $column_id;
		}

		$css = '';

		foreach ( $width_columns as $width => $cols ) {
			$selectors = implode(
				",\n",
				array_map(
					fn( $col ) => ".wp-list-table .column-{$col}",
					$cols,
				)
			);

			$css .= <<<CSS
				{$selectors} {
					width: {$width}
				}
			CSS;
		}

		if ( $hidden_mobile_columns ) {
			$selectors = implode(
				",\n",
				array_map(
					fn( $col ) => ".wp-list-table .column-{$col}, .wp-list-table .is-expanded .column-{$col}:not(.hidden)",
					$hidden_mobile_columns,
				),
			);

			// We add visibility and height to ensure that odd specificities don't cause unforeseen issues.
			$css .= <<<CSS
				@media (max-width: 782px) {
					{$selectors} {
						display: none !important;
						visibility: hidden;
						height: 0;
					}
				}
			CSS;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is fine.
		echo "<style id=troy-server-plugin-list-css>{$css}</style>";
	}
}
