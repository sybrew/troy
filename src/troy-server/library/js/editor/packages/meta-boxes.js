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

		const pluginItems = document.querySelectorAll( '.troy-server-package-plugin-item' );

		pluginItems.forEach( item => {

			const checkbox     = item.querySelector( '.troy-server-package-plugin-checkbox' );
			const optionsPanel = item.querySelector( '.troy-server-package-plugin-options' );

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

		const slugInput  = document.getElementById( 'troy-server-package-slug' );
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
	 * Initializes the character counter for the description field.
	 *
	 * @since 1.6.1184
	 */
	const initDescriptionCharCounter = () => {

		const descInput = document.getElementById( 'troy-server-package-description' );
		const counter   = document.getElementById( 'troy-server-package-description__counter' );

		if ( ! descInput || ! counter )
			return;

		const maxLength = parseInt( descInput.getAttribute( 'maxlength' ), 10 );

		const updateCounter = () => {
			counter.textContent = `${descInput.value.length}/${maxLength} characters`;
		};

		descInput.addEventListener( 'input', updateCounter );
		updateCounter();
	};

	/**
	 * Initializes checkbox dependencies for completion options.
	 *
	 * When delete_on_completion is checked, deactivate_on_completion is also checked.
	 * When deactivate_on_completion is unchecked, delete_on_completion is also unchecked.
	 *
	 * @since 1.6.1184
	 */
	const initCompletionCheckboxDependencies = () => {

		const deactivateCheckbox = document.getElementById( 'troy-server-package-deactivate-on-completion' );
		const deleteCheckbox     = document.getElementById( 'troy-server-package-delete-on-completion' );

		if ( ! deactivateCheckbox || ! deleteCheckbox )
			return;

		deleteCheckbox.addEventListener(
			'change',
			() => {
				if ( deleteCheckbox.checked )
					deactivateCheckbox.checked = true;
			},
		);

		deactivateCheckbox.addEventListener(
			'change',
			() => {
				if ( ! deactivateCheckbox.checked )
					deleteCheckbox.checked = false;
			},
		);
	};

	/**
	 * Initializes the publish checklist.
	 *
	 * Updates checklist items based on current form state.
	 *
	 * @since 1.6.1184
	 */
	const initPublishChecklist = () => {

		const checklist = document.querySelector( '.troy-server-publish-checklist' );

		if ( ! checklist )
			return;

		const slugItem    = checklist.querySelector( '[data-checklist="slug"]' );
		const pluginsItem = checklist.querySelector( '[data-checklist="plugins"]' );
		const slugInput   = document.getElementById( 'troy-server-package-slug' );
		const titleInput  = document.getElementById( 'title' );
		const slugWarning = document.querySelector( '.troy-server-package-slug-warning' );

		const updateSlugCheck = () => {

			if ( ! slugItem )
				return;

			const hasSlug  = !! ( slugInput?.value.trim() || titleInput?.value.trim() );
			const approved = hasSlug && ! slugWarning?.textContent;

			slugItem.classList.toggle( 'is-ok', approved );
			slugItem.classList.toggle( 'is-missing', ! approved );
		};

		const updatePluginsCheck = () => {

			if ( ! pluginsItem )
				return;

			const hasPlugins = !! document.querySelectorAll( '.troy-server-package-plugin-checkbox:checked' ).length;

			pluginsItem.classList.toggle( 'is-ok', hasPlugins );
			pluginsItem.classList.toggle( 'is-missing', ! hasPlugins );
		};

		slugInput?.addEventListener( 'input', updateSlugCheck );
		titleInput?.addEventListener( 'input', updateSlugCheck );

		// Observe slug warning changes from initSlugValidation.
		if ( slugWarning ) {
			new MutationObserver( updateSlugCheck )
				.observe( slugWarning, { childList: true } );
		}

		document.querySelectorAll( '.troy-server-package-plugin-checkbox' )
			.forEach( cb => cb.addEventListener( 'change', updatePluginsCheck ) );

		updateSlugCheck();
		updatePluginsCheck();
	};

	/**
	 * Initializes slug conflict validation.
	 *
	 * Validates the slug against existing plugins and packages on input change.
	 *
	 * @since 0.0.1184
	 */
	const initSlugValidation = () => {

		const slugInput = document.getElementById( 'troy-server-package-slug' );

		if ( ! slugInput || 'undefined' === typeof troyPackageEditorData )
			return;


		// Create warning element
		const warningEl       = document.createElement( 'p' );
		warningEl.className   = 'troy-server-package-slug-warning';
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
		initDescriptionCharCounter();
		initCompletionCheckboxDependencies();
		initPublishChecklist();
	} else {
		document.addEventListener( 'DOMContentLoaded', () => {
			initPluginCheckboxes();
			initSlugAutoFill();
			initSlugValidation();
			initDescriptionCharCounter();
			initCompletionCheckboxDependencies();
			initPublishChecklist();
		} );
	}
} )();
