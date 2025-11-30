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
 * @description URLs control component for the Troy Server plugin editor.
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
	const { __ }     = wp.i18n;
	const {
		Button,
		TextControl,
	} = wp.components;
	const { useSelect } = wp.data;

	// Experimental components
	const VStack                 = wp.components?.VStack || wp.components?.__experimentalVStack;
	const InspectorPopoverHeader = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	const {
		PanelRow,
		createPopoverProps,
	} = troyServerEditorComponents;

	const { MenuDropdown } = troyServerPluginEditorComponents;

	/**
	 * URLs Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose          Callback function to close the popover.
	 *     @param {string}   props.permalink        The current plugin permalink URL.
	 *     @param {string}   props.supportUri       The current plugin support URI.
	 *     @param {string}   props.donateUri        The current plugin donate URI.
	 *     @param {Function} props.updatePermalink  Function to update the permalink.
	 *     @param {Function} props.updateSupport    Function to update the support URI.
	 *     @param {Function} props.updateDonate     Function to update the donate URI.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function UrlsPopover( { onClose, permalink, supportUri, donateUri, updatePermalink, updateSupport, updateDonate } ) {

		const [ localPermalink, setLocalPermalink ]   = useState( permalink );
		const [ localSupportUri, setLocalSupportUri ] = useState( supportUri );
		const [ localDonateUri, setLocalDonateUri ]   = useState( donateUri );

		const [ permalinkPlaceholder, setPermalinkPlaceholder ] = useState( '' );

		useSelect(
			select => {
				if ( ! permalinkPlaceholder )
					setPermalinkPlaceholder( select( 'core/editor' ).getPermalink() );
			},
			[ permalinkPlaceholder ],
		);

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin URLs', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				JSX(
					TextControl,
					{
						label:       __( 'Custom Permalink', 'troy-server' ),
						value:       localPermalink,
						placeholder: permalinkPlaceholder,
						onChange:    value => {
							setLocalPermalink( value );
							updatePermalink( value );
						},
						type:        'url',
						help:        __( 'This link is used when information about this plugin is requested, also known as the "plugin homepage".', 'troy-server' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
				JSX(
					TextControl,
					{
						label:    __( 'Support URI', 'troy-server' ),
						value:    localSupportUri,
						onChange: value => {
							setLocalSupportUri( value );
							updateSupport( value );
						},
						type:     'url',
						help:     __( 'A link to the plugin support forum or contact page.', 'troy-server' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
				JSX(
					TextControl,
					{
						label:    __( 'Donate URI', 'troy-server' ),
						value:    localDonateUri,
						onChange: value => {
							setLocalDonateUri( value );
							updateDonate( value );
						},
						type:     'url',
						help:     __( 'A link to support the plugin author financially.', 'troy-server' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
			),
		);
	}

	/**
	 * URLs Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {string}   props.permalink        The current plugin permalink.
	 *     @param {string}   props.supportUri       The current plugin support URI.
	 *     @param {string}   props.donateUri        The current plugin donate URI.
	 *     @param {Function} props.updatePermalink  Function to update the permalink.
	 *     @param {Function} props.updateSupport    Function to update the support URI.
	 *     @param {Function} props.updateDonate     Function to update the donate URI.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function UrlsControl( { permalink, supportUri, donateUri, updatePermalink, updateSupport, updateDonate } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin URLs', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const hasUrls = permalink || supportUri || donateUri;

		return JSX(
			PanelRow,
			{
				label:       __( 'URLs', 'troy-server' ),
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
						hasUrls
							? __( 'Update URLs', 'troy-server' )
							: __( 'Set URLs', 'troy-server' ),
					),
					renderContent: ( { onClose } ) => JSX(
						UrlsPopover,
						{
							onClose,
							permalink,
							supportUri,
							donateUri,
							updatePermalink,
							updateSupport,
							updateDonate,
						},
					),
				},
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { UrlsControl } );
} )( window.wp );
