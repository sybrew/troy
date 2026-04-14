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

	const apiFetch           = wp.apiFetch;
	const { addQueryArgs }   = wp.url;
	const { __, sprintf }    = wp.i18n;

	const modal     = document.getElementById( 'troy-server-composer-modal' );
	const modalBody = document.getElementById( 'troy-server-composer-modal-body' );

	if ( ! modal || ! modalBody )
		return;

	/**
	 * Shows the modal overlay and locks body scroll.
	 *
	 * @since 1.7.1184
	 */
	const showModal = () => {

		modal.hidden                 = false;
		document.body.style.overflow = 'hidden';
	};

	/**
	 * Hides the modal overlay and restores body scroll.
	 *
	 * @since 1.7.1184
	 */
	const hideModal = () => {

		modal.hidden                 = true;
		document.body.style.overflow = '';
	};

	/**
	 * Builds the step-by-step Composer setup HTML.
	 *
	 * @since 1.7.1184
	 *
	 * @param {Object} data              REST response data.
	 * @param {string} data.packageName  The full Composer package name.
	 * @param {string} data.repoUrl      The Composer repository URL.
	 * @param {Object} data.require      Map of package names to version constraints.
	 * @return {string} The HTML content for the modal body.
	 */
	const buildSnippetHtml = data => {

		const deployTroyRepoUrl = 'https://repo.deploytroy.org/composer/get';
		const siteSlug          = data.packageName.split( '/' )[0].replace( /-package$/, '' );

		const repositories = JSON.stringify(
			[
				{ type: 'composer', url: data.repoUrl },
				{ type: 'composer', url: deployTroyRepoUrl },
			],
			null,
			4,
		);

		const requireJson = JSON.stringify(
			{ [data.packageName]: '*' },
			null,
			4,
		);

		const fullRequire = Object.assign(
			{ 'composer/installers': '^2.0' },
			{ [data.packageName]: '*' },
		);

		const fullSnippet = JSON.stringify(
			{
				name:        `${ siteSlug }/my-project`,
				description: __( 'My project description', 'troy-server' ),
				license:     'proprietary',
				repositories: [
					{ type: 'composer', url: data.repoUrl },
					{ type: 'composer', url: deployTroyRepoUrl },
				],
				require:      fullRequire,
				extra:        {
					'installer-paths': {
						'wp-content/plugins/{$name}/': [ 'type:wordpress-plugin' ],
						'wp-content/mu-plugins/{$name}/': [ 'type:wordpress-muplugin' ],
						'wp-content/themes/{$name}/': [ 'type:wordpress-theme' ],
					},
				},
			},
			null,
			4,
		);

		const slugInput    = document.getElementById( 'troy-server-package-slug' );
		const slugChanged  = slugInput && slugInput.value !== slugInput.defaultValue;
		const hintClass    = 'troy-server-composer-modal-hint' + ( slugChanged ? ' is-warning' : '' );
		const hintText     = slugChanged
			? __( 'The slug has changed. Save the package to update the examples below.', 'troy-server' )
			: __( 'The examples below reflect the last saved state of the package.', 'troy-server' );

		return `
			<p class="${ hintClass }">${ hintText }</p>
			<div class="troy-server-composer-modal-section">
				<p>${ sprintf(
					/* translators: %s: Link to getcomposer.org */
					__( '%s manages PHP dependencies via a <code>composer.json</code> file in a project root folder.', 'troy-server' ),
					'<a href="https://getcomposer.org/" target="_blank" rel="noopener noreferrer">Composer</a>',
				) }</p>
				<p>${ __( 'In the packages, Troy Client is included for update request filtering and active install reporting.', 'troy-server' ) }</p>
			</div>
			<div class="troy-server-composer-modal-section">
				<h4>${ __( 'Composer responses', 'troy-server' ) }</h4>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col">${ __( 'Endpoint', 'troy-server' ) }</th>
							<th scope="col">${ __( 'Response', 'troy-server' ) }</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>${ __( 'Composer discovery', 'troy-server' ) }</td>
							<td><a href="${ data.repoUrl }/packages.json" target="_blank" rel="noopener noreferrer">packages.json</a></td>
						</tr>
						<tr>
							<td>${ __( 'Package', 'troy-server' ) }</td>
							<td><a href="${ data.repoUrl }/${ data.packageName }.json" target="_blank" rel="noopener noreferrer">${ data.packageName }.json</a></td>
						</tr>
						${ Object.keys( data.require ).map( pkg => {
							const url = pkg.startsWith( 'deploytroy-' )
								? `${ deployTroyRepoUrl }/${ pkg }.json`
								: `${ data.repoUrl }/${ pkg }.json`;
							return `<tr>
							<td>${ __( 'Dependency', 'troy-server' ) }</td>
							<td><a href="${ url }" target="_blank" rel="noopener noreferrer">${ pkg }.json</a></td>
						</tr>`;
						} ).join( '' ) }
					</tbody>
				</table>
			</div>
			<div class="troy-server-composer-modal-section">
				<h4>${ __( 'Existing composer.json (e.g. Bedrock)', 'troy-server' ) }</h4>
				<p>${ __( 'If you already have a <code>composer.json</code>, add these package repositories from a terminal:', 'troy-server' ) }</p>
				<pre class="troy-server-pre troy-server-copyable"><code>composer config repositories.${ siteSlug } composer ${ data.repoUrl }\ncomposer config repositories.deploytroy composer ${ deployTroyRepoUrl }</code></pre>
				<p>${ __( 'Then require this package:', 'troy-server' ) }</p>
				<pre class="troy-server-pre troy-server-copyable"><code>composer require ${ data.packageName }</code></pre>
				<p>${ __( 'Or merge the following entries into your <code>composer.json</code> manually:', 'troy-server' ) }</p>
				<pre class="troy-server-pre"><code>"repositories": ${ repositories }</code></pre>
				<pre class="troy-server-pre"><code>"require": ${ requireJson }</code></pre>
				<p>${ __( 'Then run <code>composer update</code> from a terminal in your project root.', 'troy-server' ) }</p>
			</div>
			<div class="troy-server-composer-modal-section">
				<h4>${ __( 'Full composer.json', 'troy-server' ) }</h4>
				<p>${ __( 'For new projects, save this as <code>composer.json</code> in your project root, then run <code>composer install</code> from a terminal in that directory:', 'troy-server' ) }</p>
				<pre class="troy-server-pre troy-server-copyable"><code>${ fullSnippet }</code></pre>
			</div>
		`;
	};

	/**
	 * Opens the modal and fetches Composer data for the given package.
	 *
	 * @since 1.7.1184
	 *
	 * @param {number} packageId The package ID to fetch data for.
	 */
	const openComposerModal = async packageId => {

		showModal();

		modalBody.innerHTML = `<p class="troy-server-composer-modal-loading">
			${ __('Loading&hellip;', 'troy-server') }
		</p>`;

		try {
			const data = await apiFetch( {
				url: addQueryArgs(
					troyComposerModalData.restUrl,
					{ package_id: packageId },
				),
				method: 'GET',
			} );

			modalBody.innerHTML = buildSnippetHtml( data );
		} catch {
			modalBody.innerHTML = `<div class="troy-server-composer-modal-error">
				${ __( 'Could not load Composer data. Make sure the package is published with at least one active plugin.', 'troy-server' ) }
			</div>`;
		}
	};

	/**
	 * Initializes modal event listeners.
	 *
	 * @since 1.7.1184
	 */
	const init = () => {

		let mouseDownTarget = null;

		modal.querySelector( '.troy-server-composer-modal-close' )
			?.addEventListener( 'click', hideModal );

		const setMouseDownTarget = e => { mouseDownTarget = e.target; };

		modal.addEventListener( 'mousedown', setMouseDownTarget );
		modal.addEventListener( 'touchstart', setMouseDownTarget );

		const hideOnOutsideUp = e => {
			if ( e.target === modal && mouseDownTarget === modal )
				hideModal();
		};

		modal.addEventListener( 'mouseup', hideOnOutsideUp );
		modal.addEventListener( 'touchend', hideOnOutsideUp );

		document.addEventListener(
			'keydown',
			e => {
				if ( 'Escape' === e.key && ! modal.hidden )
					hideModal();
			},
		);

		document.querySelectorAll( '[data-composer-package-id]' )
			.forEach( btn => {
				btn.addEventListener(
					'click',
					e => {
						e.preventDefault();
						openComposerModal( btn.dataset.composerPackageId );
					},
				);
			} );
	};

	if ( 'complete' === document.readyState )
		init();
	else
		document.addEventListener( 'DOMContentLoaded', init );
} )();
