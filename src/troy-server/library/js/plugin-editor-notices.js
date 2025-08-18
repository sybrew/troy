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

( wp => {
	const { registerPlugin }         = wp.plugins;
	const { useEffect }              = wp.element;
	const { useSelect, useDispatch } = wp.data;
	const { __ }                     = wp.i18n;

	/**
	 * Configuration for different notice types.
	 * Keyed by notice ID. Each entry includes:
	 * - status: Notice status ('info', 'warning', 'error', 'success').
	 * - message: The text content of the notice.
	 * - options: Options object for createNotice (e.g., type, isDismissible).
	 * - test: A function that takes storeData and returns true if the notice should be shown.
	 */
	const NOTICE_CONFIG = {
		'troy-server-plugin-editor-slug-locked': {
			type:    'warning',
			message: __( 'Set a unique slug in the sidebar under Plugin Settings to start creating a plugin.', 'troy-server' ),
			options: {
				isDismissible: false,
			},
			show: storeData => ! storeData.slug,
		},
		'troy-server-plugin-editor-readme-warning': {
			type:    'warning',
			message: __( 'No valid readme found in current version.', 'troy-server' ), // TODO explain what a valid readme is, add link to docs
			options: {
				isDismissible: false,
			},
			show: storeData => storeData.slug
				&& 'readme' === storeData.builder_type
				&& ! Object.values( storeData.contents || {} ).some( c => c.length ),
		},
		'troy-server-store-is-loading': {
			type:    'info',
			message: __( 'Loading plugin data…', 'troy-server' ),
			options: {
				isDismissible: false,
			},
			show: storeData => ! storeData || storeData.isLoading,
		},
	};

	/**
	 * A component that manages editor notices based on TroyServerPluginEditorStore data.
	 * Allows multiple notices to be displayed simultaneously based on test functions.
	 *
	 * @since 0.0.1184
	 *
	 * @returns {null} Nothing is rendered visually.
	 */
	function renderPluginNotices() {

		const notices = useSelect(
			select => select( 'core/notices' ).getNotices(),
			[],
		);
		const { data: storeData } = troyServerGetPluginStore();

		const { createNotice, removeNotice } = useDispatch( 'core/notices' );

		useEffect(
			() => {
				if ( ! storeData )
					return;

				for (
					const [ id, { type, message, options, show } ]
					of Object.entries( NOTICE_CONFIG )
				) {
					const hasNotice = notices.some( n => n.id === id );

					if ( show( storeData ) ) {
						if ( ! hasNotice )
							createNotice( type, message, { ...options, id } );
					} else if ( hasNotice ) {
						removeNotice( id );
					}
				}
			},
			[ storeData, notices ],
		);

		// Render nothing visually in the plugin.
		return null;
	}

	registerPlugin(
		'troy-server-editor-plugin-notices',
		{ render: renderPluginNotices },
	);
} )( window.wp );
