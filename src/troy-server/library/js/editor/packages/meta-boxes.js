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

	const apiFetch         = wp.apiFetch;
	const { addQueryArgs } = wp.url;
	const timing           = troyServerTiming;

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

	/**
	 * Initializes slug conflict validation.
	 *
	 * Validates the slug against existing plugins and packages on input change.
	 *
	 * @since 0.0.1184
	 */
	const initSlugValidation = () => {

		const slugInput = document.getElementById( 'troy_package_slug' );

		if ( ! slugInput || 'undefined' === typeof troyPackageEditorData )
			return;


		// Create warning element
		const warningEl       = document.createElement( 'p' );
		warningEl.className   = 'troy-package-slug-warning';
		warningEl.style.color = '#d63638';

		slugInput.parentNode.insertBefore( warningEl, slugInput.nextSibling );

		const validateSlug = () => {

			const slug = slugInput.value.trim();

			if ( ! slug ) {
				warningEl.textContent = '';
				return;
			}

			apiFetch( {
				url: addQueryArgs(
					troyPackageEditorData.restUrls.validateSlug,
					{
						slug,
						package_id: troyPackageEditorData.packageId || 0,
					},
				),
				method: 'GET',
			} )
				.then( data => {
					warningEl.textContent = data.valid ? '' : data.message;
				} )
				.catch( () => {
					warningEl.textContent = '';
				} );
		};

		const debouncedValidateSlug = timing.debounce( validateSlug, 300 ); // Magic Number: 300ms

		slugInput.addEventListener( 'input', debouncedValidateSlug );

		// Validate on load if there's a value
		if ( slugInput.value )
			validateSlug();
	};

	if ( 'complete' === document.readyState ) {
		initPluginCheckboxes();
		initSlugAutoFill();
		initSlugValidation();
	} else {
		document.addEventListener( 'DOMContentLoaded', () => {
			initPluginCheckboxes();
			initSlugAutoFill();
			initSlugValidation();
		} );
	}
} )();
