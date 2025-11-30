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
 * @module troyServerEditorPluginsBlocksTabs
 * @description Plugin tabs block for Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const { registerBlockType } = wp.blocks;
	const {
		createElement: JSX,
		useEffect,
	} = wp.element;
	const { __ } = wp.i18n;
	const { useSelect } = wp.data;
	const { Button } = wp.components;
	const {
		useBlockProps,
		useInnerBlocksProps,
		store: blockEditorStore,
	} = wp.blockEditor;

	registerBlockType(
		'troy-server/plugin-tabs',
		{
			providesContext: {
				'troy-server/plugin-tabs/activeTab': 'activeTab',
			},
			edit: ( { attributes, setAttributes, clientId } ) => {
				const { activeTab } = attributes;
				const { dynamicInnerBlocks } = useSelect(
					select => ( {
						dynamicInnerBlocks: select( blockEditorStore ).getBlocks( clientId ),
					} ),
					[ clientId ],
				);

				const {
					data: storeData,
					allTabsEmpty,
				} = troyServerGetPluginStore();

				// Auto-switch to first available tab if current tab is hidden
				useEffect(
					() => {
						if (
							   'readme' !== storeData.builder_type
							|| ! dynamicInnerBlocks.length
						) return;

						const currentTabBlock   = dynamicInnerBlocks[ activeTab ];
						const currentTabContent = storeData.contents?.[ currentTabBlock?.attributes?.troyServerTabId ];

						if ( ! currentTabContent?.length ) {
							// Find first available tab
							const firstAvailableTabIndex = dynamicInnerBlocks.findIndex(
								block => storeData.contents?.[ block.attributes?.troyServerTabId ]?.length > 0,
							);

							if ( -1 !== firstAvailableTabIndex && firstAvailableTabIndex !== activeTab ) {
								const timeoutId = setTimeout(
									() => {
										setAttributes( { activeTab: firstAvailableTabIndex } );
									},
									10, // Magic Number: Store data should stabilize, prevents race conditions
								);

								return () => clearTimeout( timeoutId );
							}
						}
					},
					[ activeTab, dynamicInnerBlocks, storeData.builder_type, storeData.contents ],
				);

				// We share the blockProps with the inner blocks.
				// Oddly, this prevents the editor from jumping sporadically when switching tabs.
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-tabs-container',
				} );

				return JSX(
					'div',
					{ ...blockProps },
					JSX(
						'div',
						{
							className: 'troy-server-block-plugin-tabs-nav',
						},
						dynamicInnerBlocks.map( ( block, index ) => {
							// Hide the tab if it's empty. But, show if all tabs are empty; a11y: show context.
							if ( 'readme' === storeData.builder_type && ! allTabsEmpty )
								if ( ! storeData.contents?.[ block.attributes?.troyServerTabId ]?.length )
									return null;

							return JSX(
								Button,
								{
									key:         block.clientId,
									isPrimary:   activeTab === index,
									isSecondary: activeTab !== index,
									onClick:     () => {
										setAttributes( { activeTab: index } );
									},
									className:   'troy-server-block-plugin-tab-button',
								},
								block.attributes.title || `Tab ${ index + 1 }`
							);
						} ),
					),
					JSX(
						'div',
						useInnerBlocksProps(
							blockProps,
							{
								allowedBlocks:  [ 'troy-server/plugin-tab-content' ],
								template:       Object.values( troyPluginEditorData.contentTabs ).map( tab => [
									'troy-server/plugin-tab-content',
									{
										title:           tab.title,
										troyServerTabId: tab.id,
									},
								] ),
								templateLock:   true,
								renderAppender: false, // Don't allow adding new tabs here
							},
						),
					),
				);
			},
			save: () => {
				// Save only the inner blocks' content.
				return JSX(
					'div',
					useInnerBlocksProps.save(
						useBlockProps.save(),
					),
				);
			},
		},
	);
} )( window.wp );
