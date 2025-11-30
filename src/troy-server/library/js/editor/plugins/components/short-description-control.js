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
 * @module troyServerPluginEditorComponents
 * @description Short description control component for the Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const {
		createElement: JSX,
		useState,
		useMemo,
		Fragment,
	} = wp.element;
	const { __, sprintf } = wp.i18n;
	const {
		Button,
		TextareaControl,
	} = wp.components;

	// Experimental components
	const VStack                 = wp.components?.VStack || wp.components?.__experimentalVStack;
	const InspectorPopoverHeader = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	const {
		PanelRow,
		createPopoverProps,
	} = troyServerEditorComponents;

	const { MenuDropdown } = troyServerPluginEditorComponents;

	/**
	 * Short Description Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose            Callback function to close the popover.
	 *     @param {Object}   props.description        The current plugin description.
	 *     @param {Function} props.updateDescription  Function to update the plugin description.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ShortDescriptionPopover( { onClose, description, updateDescription } ) {

		const [ localDescription, setLocalDescription ] = useState( description );

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Short Description', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				JSX(
					TextareaControl,
					{
						label:     sprintf(
							/* translators: %d is the character count, 150 is the recommended maximum length */
							__( 'Description - %d/150 characters', 'troy-server' ),
							localDescription.length,
						),
						value:     localDescription,
						onChange:  value => {
							setLocalDescription( value );
							updateDescription( value );
						},
						rows:      4,
						help:      __( 'A brief description of this plugin that appears in plugin listings.', 'troy-server' ),
						maxLength: 191,
						__nextHasNoMarginBottom: true,
					},
				),
			),
		);
	}

	/**
	 * Short Description Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.description        The current plugin description.
	 *     @param {Function} props.updateDescription  Function to update the plugin description.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ShortDescriptionControl( { description, updateDescription } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Short Description', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const displayText = description.length > 150
			? description.substring( 0, 149 ) + '…'
			: description || __( 'No description', 'troy-server' );

		return JSX(
			PanelRow,
			{
				label: __( 'Description', 'troy-server' ),
				onRefChange: setPopoverAnchor,
			},
			JSX(
				MenuDropdown,
				{
					popoverProps,
					focusOnMount: true,
					renderToggle: ( { onToggle, isOpen } ) => JSX(
						Button,
						{
							variant:         'tertiary',
							size:            'compact',
							onClick:         onToggle,
							'aria-expanded': isOpen,
							style:           { textAlign: 'left' },
						},
						displayText,
					),
					renderContent: ( { onClose } ) => JSX(
						ShortDescriptionPopover,
						{
							onClose,
							description,
							updateDescription,
						},
					),
				},
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { ShortDescriptionControl } );
} )( window.wp );
