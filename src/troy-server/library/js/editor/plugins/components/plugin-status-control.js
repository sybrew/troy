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
 * @description Plugin status control component for the Troy Server plugin editor.
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
	 * Plugin Status Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {string}   props.status        The current plugin status.
	 *     @param {Function} props.updateStatus  Function to update the plugin status.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginStatusPopover( { onClose, status, updateStatus } ) {

		const [ localStatus, setLocalStatus ] = useState( status || 'public' );

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin Status', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				JSX(
					RadioControl,
					{
						label:    __( 'Status', 'troy-server' ),
						selected: localStatus,
						options:  troyPluginEditorData.pluginStatuses,
						onChange: newStatus => {
							setLocalStatus( newStatus );
							updateStatus( newStatus );
						},
						help:     __( 'Control the visibility of this plugin.', 'troy-server' ),
						hideLabelFromVision: true,
					},
				),
			),
		);
	}

	/**
	 * Plugin Status Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.status       The current plugin status.
	 *     @param {Function} props.updateStatus Function to update the plugin status.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginStatusControl( { status, updateStatus } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin Status', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const statusLabel = troyPluginEditorData.pluginStatuses?.find(
			option => option.value === status,
		)?.label || status;

		return JSX(
			PanelRow,
			{
				label:       __( 'Status', 'troy-server' ),
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
						statusLabel,
					),
					renderContent: ( { onClose } ) => JSX(
						PluginStatusPopover,
						{
							onClose,
							status,
							updateStatus,
						},
					),
				},
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { PluginStatusControl } );
} )( window.wp );
