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
 * @description Auto process tags control component for the Troy Server plugin editor.
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
	const { __ }            = wp.i18n;
	const {
		Button,
		RadioControl,
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
	 * Auto-Process Tags Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose        Callback function to close the popover.
	 *     @param {string}   props.autoProcess    The current auto-process setting.
	 *                                            Accepts 'all', 'tag', 'beta', and 'none'.
	 *     @param {Function} props.setAutoProcess Function to set the auto-process value.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function AutoProcessTagsPopover( { onClose, autoProcess, setAutoProcess } ) {

		const [ localAutoProcess, setLocalAutoProcess ] = useState( autoProcess );

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Auto-Process Tags', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				JSX(
					RadioControl,
					{
						label:    __( 'Auto-process tags', 'troy-server' ),
						selected: localAutoProcess,
						options:  troyPluginEditorData.autoProcess,
						onChange: newValue => {
							setLocalAutoProcess( newValue );
							setAutoProcess( newValue );
						},
						help:     __( 'Automatically import new tags when discovered.', 'troy-server' ),
						hideLabelFromVision: true,
					},
				),
			),
		);
	}

	/**
	 * Auto-Process Tags Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.autoProcess    The current auto-process setting.
	 *                                            Accepts 'all', 'tag', 'beta', and 'none'.
	 *     @param {Function} props.setAutoProcess Function to set the auto-process value.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function AutoProcessTagsControl( { autoProcess, setAutoProcess } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Auto-Process Tags', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const autoProcessLabel = troyPluginEditorData.autoProcess?.find(
			option => option.value === autoProcess,
		)?.label || autoProcess;

		return JSX(
			PanelRow,
			{
				label:       __( 'Auto-process', 'troy-server' ),
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
						},
						autoProcessLabel,
					),
					renderContent: ( { onClose } ) => JSX(
						AutoProcessTagsPopover,
						{
							onClose,
							autoProcess,
							setAutoProcess,
						},
					),
				},
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { AutoProcessTagsControl } );
} )( window.wp );
