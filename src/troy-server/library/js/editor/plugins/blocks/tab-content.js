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

'use strict';

/**
 * @module troyServerEditorPluginsBlocksTabContent
 * @description Plugin tab content block for Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const { registerBlockType } = wp.blocks;
	const { createElement: JSX } = wp.element;
	const { useSelect } = wp.data;
	const {
		useBlockProps,
		useInnerBlocksProps,
		DefaultBlockAppender,
	} = wp.blockEditor;

	/**
	 * Renders the tab panel in editor mode.
	 *
	 * @param {object}  props          The component props.
	 * @param {boolean} props.isActive Whether the tab is active.
	 * @returns {JSX.Element} The rendered component.
	 */
	function TabViewEditorMode( { isActive } ) {

		const blockProps = useBlockProps( {
			className: `troy-server-block-plugin-tab-panel ${
				isActive ? 'is-active' : 'is-inactive'
			}`,
			style: {
				...( ! isActive && { display: 'none' } ),
			},
		} );

		const innerBlocksProps = useInnerBlocksProps(
			blockProps,
			{
				template:       [ [ 'core/paragraph', {} ] ],
				templateLock:   false, // TODO is this necessary?
				renderAppender: DefaultBlockAppender,
			},
		);

		return JSX( 'div', innerBlocksProps );
	}

	/**
	 * Renders the tab panel in readme mode.
	 *
	 * @param {object}  props              The component props.
	 * @param {boolean} props.isActive     Whether the tab is active.
	 * @param {object}  props.storeData    The plugin store data.
	 * @param {boolean} props.allTabsEmpty Whether all tabs are empty.
	 * @param {object}  props.attributes   The block attributes.
	 * @returns {JSX.Element} The rendered component.
	 */
	function TabViewReadmeMode( { isActive, storeData, allTabsEmpty, attributes } ) {

		const isEmpty        = ! storeData.contents?.[ attributes?.troyServerTabId ]?.length;
		const displayContent = isActive && ( ! isEmpty || allTabsEmpty );

		return JSX(
			'div',
			{
				// Force remount when content state changes via key that checks content state; not visibility
				// This key prevents race conditions during auto-switching while still allowing remounting when needed
				key: `${attributes?.troyServerTabId}-${+isEmpty}-${+allTabsEmpty}`,
				...useInnerBlocksProps(
					useBlockProps( {
						className: `troy-server-block-plugin-tab-panel ${
							isActive ? 'is-active' : 'is-inactive'
						}`,
						style: {
							...( ! displayContent && { display: 'none' } ),
						},
					} ),
					{
						templateLock:   'all',
						renderAppender: false,
					},
				),
			}
		);
	}

	registerBlockType(
		'troy-server/plugin-tab-content',
		{
			usesContext: [ 'troy-server/plugin-tabs/activeTab' ],
			edit: ( { clientId, context, attributes } ) => {
				const index = useSelect(
					select => select( 'core/block-editor' ).getBlockIndex( clientId ),
					[ clientId ],
				);

				const {
					data: storeData,
					allTabsEmpty,
				} = troyServerGetPluginStore();

				const isActive = index === context[ 'troy-server/plugin-tabs/activeTab' ];

				if ( 'readme' === storeData.builder_type ) {
					return JSX(
						TabViewReadmeMode,
						{
							isActive,
							storeData,
							allTabsEmpty,
							attributes,
						},
					);
				}

				return JSX(
					TabViewEditorMode,
					{
						isActive,
					},
				);
			},
			save: () => {
				// NOTE: We do not sync the block content with troyPluginEditorStoreData.
				// Instead, we save the content of the InnerBlocks on save.
				// Note that the contents will be overwritten when the builder_type changes,
				// such as the 'readme' type from the 'post' type.
				return JSX(
					'div',
					useInnerBlocksProps.save(
						useBlockProps.save( {
							className: 'troy-server-block-plugin-tab-panel',
						} ),
					),
				);
			},
		},
	);
} )( window.wp );
