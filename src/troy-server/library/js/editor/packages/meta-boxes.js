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

( () => {

	/**
	 * Initializes package plugin item checkboxes.
	 *
	 * @since 0.0.1184
	 */
	const initPluginCheckboxes = () => {

		const pluginItems = document.querySelectorAll( '.troy-package-plugin-item' );

		pluginItems.forEach( item => {

			const checkbox     = item.querySelector( '.troy-package-plugin-checkbox' );
			const optionsPanel = item.querySelector( '.troy-package-plugin-options' );

			if ( ! checkbox || ! optionsPanel )
				return;

			// Toggle options panel visibility when main checkbox is toggled
			checkbox.addEventListener(
				'change',
				event => {
					optionsPanel.style.display = event.target.checked ? '' : 'none';
				},
			);
		} );
	};

	/**
	 * Auto-fills the slug field from the post title if slug is empty.
	 *
	 * @since 0.0.1184
	 */
	const initSlugAutoFill = () => {

		const slugInput  = document.getElementById( 'troy_package_slug' );
		const titleInput = document.getElementById( 'title' );
		// const postNameInput = document.getElementById( 'post_name' ); // We do not support post_name (Core slug) -- maybe later.

		if ( ! slugInput || ! titleInput )
			return;

		const updateSlugFromTitle = () => {

			if ( slugInput.value )
				return;

			const postTitle = titleInput?.value ?? '';
			// const postSlug  = postNameInput?.value ?? document.getElementById( 'editable-post-name-full' )?.innerText ?? '';

			slugInput.placeholder = postTitle
				.toLowerCase()
				.replace( /\s+/g, '-' )
				.replace( /[^a-z0-9-]/g, '' )
				.replace( /-{2,}/g, '-' )
				.replace( /^[^a-z1-9]+/, '' )
				.slice( 0, 191 );
		};

		titleInput?.addEventListener( 'input', updateSlugFromTitle );
		// postNameInput?.addEventListener( 'input', updateSlugFromTitle );

		updateSlugFromTitle();
	};

	if ( 'complete' === document.readyState ) {
		initPluginCheckboxes();
		initSlugAutoFill();
	} else {
		document.addEventListener( 'DOMContentLoaded', () => {
			initPluginCheckboxes();
			initSlugAutoFill();
		} );
	}
} )();
