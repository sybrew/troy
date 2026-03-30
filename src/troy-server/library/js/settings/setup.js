/**
 * Troy Server
 *
 * Copyright (c) 2026 Sybre Waaijer, CyberWire B.V.
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

	const { __ }   = wp.i18n;
	const apiFetch = wp.apiFetch;

	const config   = window.troyServerSetup || {};
	const restBase = config.restBase || '';

	const vendorInput = document.getElementById( 'troy-server-composer-vendor' );
	const previewEl   = document.getElementById( 'troy-server-composer-vendor-preview' );
	const saveButton  = document.getElementById( 'troy-server-setup-save' );
	const noticeEl    = document.getElementById( 'troy-server-setup-notice' );

	if ( ! saveButton )
		return;

	/**
	 * Updates the vendor preview codes below the input.
	 *
	 * @since 1.7.1184
	 *
	 * @param {string} vendor The vendor slug.
	 */
	function updatePreview( vendor ) {

		if ( ! previewEl )
			return;

		const safe = document.createElement( 'span' );
		safe.textContent = vendor;
		const escaped    = safe.innerHTML;

		const label = __( 'Preview:', 'troy-server' );

		previewEl.innerHTML =
			`${ label } <code>${ escaped }-package/example-slug</code>`;
	}

	/**
	 * Shows a notice above the save button.
	 *
	 * @since 1.7.1184
	 *
	 * @param {string} text The message text.
	 * @param {string} type 'success', 'error', or 'info'.
	 */
	function showNotice( text, type ) {

		noticeEl.className = `notice notice-${ type } inline is-dismissible`;
		noticeEl.innerHTML = `<p>${ text }</p>`;
		noticeEl.hidden    = false;

		wp.a11y.speak( text, 'assertive' );

		const dismissButton = document.createElement( 'button' );
		dismissButton.type      = 'button';
		dismissButton.className = 'notice-dismiss';
		dismissButton.append( (() => {
			const span       = document.createElement( 'span' );
			span.className   = 'screen-reader-text';
			span.textContent = __( 'Dismiss this notice.', 'troy-server' );
			return span;
		})() );
		dismissButton.addEventListener(
			'click',
			() => { noticeEl.hidden = true; },
		);
		noticeEl.append( dismissButton );
	}

	/**
	 * Collects all settings values from the form.
	 *
	 * @since 1.7.1184
	 *
	 * @return {Object} Key-value pairs.
	 */
	function collectSettings() {

		const data = {};

		if ( vendorInput )
			data.composer_vendor = vendorInput.value.trim();

		return data;
	}

	if ( vendorInput )
		vendorInput.addEventListener(
			'input',
			() => updatePreview( vendorInput.value || vendorInput.placeholder || vendorInput.defaultValue ),
		);

	saveButton.addEventListener(
		'click',
		() => {

			const data = collectSettings();

			saveButton.disabled = true;

			apiFetch( {
				url:    restBase,
				method: 'POST',
				data,
			} )
				.then( result => {

					const settings = result?.settings || {};

					if ( vendorInput && settings.composer_vendor ) {
						vendorInput.value        = settings.composer_vendor;
						vendorInput.defaultValue = settings.composer_vendor;
						updatePreview( settings.composer_vendor );
					}

					if ( result?.changed ) {
						showNotice( __( 'Settings saved.', 'troy-server' ), 'success' );
					} else {
						showNotice( __( 'No settings were changed.', 'troy-server' ), 'info' );
					}
				} )
				.catch( error => {

					showNotice(
						error?.message || __( 'Failed to save settings.', 'troy-server' ),
						'error',
					);
				} )
				.finally( () => {
					saveButton.disabled = false;
				} );
		},
	);
} )();
