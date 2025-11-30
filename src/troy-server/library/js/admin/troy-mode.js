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
 * @module troyServerMode
 * @description Provides "Troy Mode" toggle functionality to hide non-essential admin menu items.
 * @since 0.0.1184
 */
window.troyServerMode = ( () => {

	const _storageKey = 'troyServerModeActive';
	const _bodyClass  = 'troy-mode-active';
	const _buttonId   = 'troy-mode-toggle';

	const visibleIds = new Set( [
		'menu-dashboard',
		'menu-plugins',
		'menu-tools',
		'menu-settings',
		'collapse-menu',
	] );

	/**
	 * Checks if Troy Mode is currently active.
	 *
	 * @since 0.0.1184
	 *
	 * @return {Boolean} True if active, false otherwise.
	 */
	function isActive() {
		return '1' === localStorage.getItem( _storageKey );
	}

	/**
	 * Sets Troy Mode state and updates body class.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Boolean} active Whether to activate Troy Mode.
	 */
	function setState( active ) {

		localStorage.setItem( _storageKey, active ? '1' : '0' );

		document.body.classList.toggle( _bodyClass, active );

		document.getElementById( _buttonId )
			?.querySelector( '.troy-mode-switch' )
			?.classList.toggle( 'on', active );

		_updateSeparators();
	}

	/**
	 * Updates separator visibility to prevent consecutive separators.
	 *
	 * Hides consecutive and trailing separators when Troy Mode is active.
	 * Clears hidden classes when inactive.
	 *
	 * @since 0.0.1184
	 * @access private
	 */
	function _updateSeparators() {

		const adminMenu = document.getElementById( 'adminmenu' );

		if ( ! adminMenu )
			return;

		if ( ! isActive() ) {
			adminMenu.querySelectorAll( '.troy-mode-hidden' )
				.forEach( el => el.classList.remove( 'troy-mode-hidden' ) );

			return;
		}

		let allowNextSeparator = true;

		for ( const item of adminMenu.children ) {
			if ( item.classList.contains( 'wp-menu-separator' ) ) {
				item.classList.toggle( 'troy-mode-hidden', ! allowNextSeparator );
				allowNextSeparator = false;
				continue;
			}

			allowNextSeparator = visibleIds.has( item.id ) || item.id.includes( 'troy-server' );
		}
	}

	/**
	 * Toggles Troy Mode state.
	 *
	 * @since 0.0.1184
	 */
	function toggle() {
		setState( ! isActive() );
	}

	/**
	 * Creates the Troy Mode toggle button in the admin bar.
	 *
	 * @since 0.0.1184
	 * @access private
	 */
	function _createButton() {

		const toolbar = document.getElementById( 'wp-admin-bar-root-default' );

		if ( ! toolbar )
			return;

		const onClass = isActive() ? ' on' : '';

		const li = document.createElement( 'li' );

		li.id        = _buttonId;
		li.role      = 'group';
		li.innerHTML = `<a class="ab-item" role="menuitem" href="#troy-mode">
			<span class="ab-icon dashicons dashicons-desktop" aria-hidden="true"></span>
			<span class="ab-label">Troy Mode<span class="troy-mode-switch${onClass}"><span class="troy-mode-switch-indicator"></span></span></span>
		</a>`;

		li.querySelector( 'a' ).addEventListener(
			'click',
			e => {
				e.preventDefault();
				toggle();
			}
		);

		toolbar.append( li );
	}

	/**
	 * Syncs state from other tabs/windows via storage event.
	 *
	 * @since 0.0.1184
	 * @access private
	 *
	 * @param {StorageEvent} e The storage event.
	 */
	function _onStorageChange( e ) {
		if ( _storageKey === e.key )
			setState( '1' === e.newValue );
	}

	// Load immediately: prevent FOUC.
	if ( isActive() )
		document.body.classList.add( _bodyClass );

	_createButton();
	_updateSeparators();

	window.addEventListener( 'storage', _onStorageChange );

	return {
		isActive,
		setState,
		toggle,
	};
} )();
