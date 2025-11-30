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
 * @description Plugin slug control component for the Troy Server plugin editor.
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
	const { __ } = wp.i18n;
	const {
		TextControl,
		Button,
		Notice,
	} = wp.components;

	const apiFetch = wp.apiFetch;

	// Experimental components
	const VStack                 = wp.components?.VStack || wp.components?.__experimentalVStack;
	const InspectorPopoverHeader = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	const {
		PanelRow,
		createPopoverProps,
	} = troyServerEditorComponents;

	const { MenuDropdown } = troyServerPluginEditorComponents;

	/**
	 * Plugin Slug Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {string}   props.postId        The ID of the current post.
	 *     @param {string}   props.plugin_slug   The current plugin slug value.
	 *     @param {Function} props.storeSlug     Function to store the plugin slug.
	 *     @param {Function} props.storePluginId Function to store the plugin ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginSlugPopover( { onClose, postId, plugin_slug, storeSlug, storePluginId } ) {

		const [ localSlug, setLocalSlug ] = useState( plugin_slug || '' );

		const [ isLoading, setIsLoading ]       = useState( false );
		const [ notification, setNotification ] = useState( {
			type:    null,
			message: null,
		} );

		const showError         = message => setNotification( { type: 'error', message } );
		const clearNotification = () => setNotification( { type: null, message: null } );

		const handleStoreSlug = () => {

			setIsLoading( true );
			// Clear previous notifications
			clearNotification();

			apiFetch( {
				url:    troyPluginEditorData.restUrls.registerSlug,
				method: 'POST',
				data:   {
					post_id:     postId,
					plugin_slug: localSlug,
				},
			} )
				.then( response => {
					storeSlug( response.plugin_slug );
					storePluginId( response.plugin_id );
					setIsLoading( false );
					onClose();
				} )
				.catch( error => {
					showError( error.message || __( 'Error storing slug.', 'troy-server' ) );

					let _slug = plugin_slug;

					// If server returned post_id, it means this post already has a registered slug
					if ( error.post_id && error.post_id == postId )
						_slug = error.plugin_slug || '';

					setLocalSlug( _slug );
					storeSlug( _slug );
					setIsLoading( false );
				} );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin Slug', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				JSX(
					TextControl,
					{
						label:    __( 'Plugin Slug', 'troy-server' ),
						value:    localSlug,
						onChange: value => {
							setLocalSlug(
								value.toLowerCase()
									.replace( /\s+/g, '-' )
									.replace( /[^a-z0-9-]/g, '' )
									.replace( /-{2,}/g, '-' )
									.replace( /^[^a-z1-9]+/, '' )
									.slice( 0, 191 ),
							);
						},
						onBlur:   () => {
							setLocalSlug( localSlug.replace( /-+$/g, '' ) );
						},
						pattern:  '[a-z1-9][a-z0-9\\-]*',
						maxLength: 191,
						help:     __( 'A unique identifier. This will become the wp-content plugin folder for all future releases and ZIP file names for all downloads and is used to localize updates. Assume it to be permanent until Troy Client supports slug migrations.', 'troy-server' ),
						disabled: isLoading,
						hideLabelFromVision: true,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
				JSX(
					Button,
					{
						variant:  'primary',
						onClick:  handleStoreSlug,
						isBusy:   isLoading,
						disabled: isLoading || ! localSlug || plugin_slug,
					},
					plugin_slug
						? __( 'Update slug (planned feature)', 'troy-server' )
						: __( 'Reserve slug immediately', 'troy-server' ),
				),
				notification.message && JSX(
					Notice,
					{
						status:        notification.type,
						isDismissible: true,
						onRemove:      clearNotification,
					},
					notification.message,
				),
			),
		);
	}

	/**
	 * Plugin Slug Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.postId        The ID of the current post.
	 *     @param {string}   props.plugin_slug   The current plugin slug value.
	 *     @param {Function} props.storeSlug     Function to store the plugin slug.
	 *     @param {Function} props.storePluginId Function to store the plugin ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginSlugControl( { postId, plugin_slug, storeSlug, storePluginId } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin Slug', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		return JSX(
			PanelRow,
			{
				label:       __( 'Plugin slug', 'troy-server' ),
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
						plugin_slug || __( 'Not set', 'troy-server' ),
					),
					renderContent: ( { onClose } ) => JSX(
						PluginSlugPopover,
						{
							onClose,
							postId,
							plugin_slug,
							storeSlug,
							storePluginId,
						},
					),
				},
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { PluginSlugControl } );
} )( window.wp );
