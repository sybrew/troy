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
 * @description Plugin author control component for the Troy Server plugin editor.
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
	const { decodeEntities } = wp.htmlEntities;
	const {
		Button,
		SelectControl,
		ComboboxControl,
	} = wp.components;
	const { useSelect }      = wp.data;

	// Experimental components
	const VStack                 = wp.components?.VStack || wp.components?.__experimentalVStack;
	const InspectorPopoverHeader = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	const {
		PanelRow,
		createPopoverProps,
	} = troyServerEditorComponents;

	const {
		AUTHORS_BASE_QUERY,
		AUTHORS_QUERY,
	} = troyServerConstants;

	const { MenuDropdown } = troyServerPluginEditorComponents;

	/**
	 * Custom hook for querying authors with search functionality.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string}  search   The search term to filter authors.
	 * @returns {Object} Object containing authorOptions, isLoading, and showCombobox.
	 */
	function useAuthorsQuery( search = '' ) {

		const showCombobox = useSelect(
			select => {
				return select( 'core' ).getUsers( AUTHORS_QUERY )?.length >= 25; // 25 is also used in WordPress core.
			},
			[],
		);

		// Get authors list (and optionally search)
		const { authors, isLoading } = useSelect(
			select => {
				const { getUsers, isResolving } = select( 'core' );
				const query = { ...AUTHORS_QUERY };

				// Add search if using combobox and search term exists
				if ( search ) {
					query.search         = search;
					query.search_columns = [ 'name' ];
				}

				return {
					authors:   getUsers( query ),
					isLoading: isResolving( 'getUsers', [ query ] ),
				};
			},
			[ search ],
		);

		// Create author options
		const authorOptions = useMemo(
			() => {
				const fetchedAuthors = ( authors ?? [] )
					.map( author => ( {
						value: author.id,
						label: decodeEntities( `${author.name} [${author.id}]` ),
					} ) );

				// For SelectControl, prepend placeholder when using SelectControl
				if ( ! showCombobox ) {
					return [
						{
							value: 0,
							label: __( 'Select an author…', 'troy-server' ),
						},
						...fetchedAuthors,
					];
				}

				return fetchedAuthors;
			},
			[ authors, showCombobox ],
		);

		return {
			authorOptions,
			isLoading,
			showCombobox,
		};
	}

	/**
	 * Plugin Author Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {number}   props.authorId      The current author ID.
	 *     @param {Function} props.updateAuthor  Function to update the author ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginAuthorPopover( { onClose, authorId, updateAuthor } ) {

		const [ selectedAuthorId, setSelectedAuthorId ] = useState( authorId || 0 );
		const [ filterValue, setFilterValue ]           = useState( '' );

		const { authorOptions, isLoading, showCombobox } = useAuthorsQuery( filterValue );

		const timing = troyServerTiming;

		const handleAuthorChange = newAuthorId => {
			const authorId = parseInt( newAuthorId ) || 0;
			setSelectedAuthorId( authorId );
			updateAuthor( authorId );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin Author', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				showCombobox
					? JSX(
						ComboboxControl,
						{
							label:               __( 'Author', 'troy-server' ),
							value:               selectedAuthorId || '',
							options:             authorOptions,
							onChange:            handleAuthorChange,
							onFilterValueChange: timing.debounce( setFilterValue, 300 ), // Magic Number: WordPress core uses 300ms
							help:                __( 'Type to search for authors. Choose the author who will be displayed for this plugin.', 'troy-server' ),
							hideLabelFromVision: true,
							isLoading,
							allowReset:          true,
							placeholder:         __( 'Search authors…', 'troy-server' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize:   true,
						},
					)
					: JSX(
						SelectControl,
						{
							label:               __( 'Author', 'troy-server' ),
							value:               selectedAuthorId,
							options:             authorOptions,
							onChange:            newAuthorId => {
								const authorId = parseInt( newAuthorId ) || 0;
								setSelectedAuthorId( authorId );
								updateAuthor( authorId );
							},
							help:                __( 'Choose the author who will be displayed for this plugin.', 'troy-server' ),
							hideLabelFromVision: true,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize:   true,
						},
					),
			),
		);
	}

	/**
	 * Plugin Author Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {number}   props.authorId     The current author ID.
	 *     @param {Function} props.updateAuthor Function to update the author ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginAuthorControl( { authorId, updateAuthor } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin Author', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		// Get author name directly from WordPress core data store (following WordPress core pattern)
		const authorName = useSelect(
			select => {
				if ( ! authorId )
					return '';

				return select( 'core' ).getUser( authorId, AUTHORS_BASE_QUERY )?.name
					|| '';
			},
			[ authorId ],
		);

		const displayText = authorName
			? decodeEntities( authorName )
			: __( 'No author set', 'troy-server' );

		return JSX(
			PanelRow,
			{
				label:       __( 'Author', 'troy-server' ),
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
						displayText,
					),
					renderContent: ( { onClose } ) => JSX(
						PluginAuthorPopover,
						{
							onClose,
							authorId,
							updateAuthor,
						},
					),
				},
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { PluginAuthorControl } );
} )( window.wp );
