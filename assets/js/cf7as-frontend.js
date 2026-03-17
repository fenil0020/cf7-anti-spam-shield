/**
 * CF7 Anti-Spam Shield — Frontend script.
 *
 * Sets the timestamp, clears the honeypot field on page load,
 * and optionally disables the submit button during form processing.
 *
 * @package CF7_Anti_Spam_Shield
 * @since   1.0.0
 */
( function () {
	'use strict';

	var pageLoadTime = Math.floor( Date.now() / 1000 );

	function initForms() {
		var forms = document.querySelectorAll( '.wpcf7-form' );

		forms.forEach( function ( form ) {
			var tsField = form.querySelector( 'input[name="cf7as_ts"]' );
			if ( tsField && ! tsField.value ) {
				tsField.value = pageLoadTime;
			}

			var trapField = form.querySelector( 'input[name="cf7as_hp_field"]' );
			if ( trapField ) {
				trapField.value = '';
			}
		} );
	}

	// Set timestamp on page load.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initForms );
	} else {
		initForms();
	}

	// Also set timestamp on any form submit as a fallback.
	document.addEventListener( 'submit', function ( e ) {
		if ( ! e.target.classList.contains( 'wpcf7-form' ) ) {
			return;
		}
		var tsField = e.target.querySelector( 'input[name="cf7as_ts"]' );
		if ( tsField && ! tsField.value ) {
			tsField.value = pageLoadTime;
		}
	}, true );

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof cf7as_settings !== 'undefined' && cf7as_settings.disable_submit ) {
			document.addEventListener( 'wpcf7beforesubmit', function ( e ) {
				var btn = e.target.querySelector( 'input[type="submit"], button[type="submit"]' );
				if ( btn ) {
					btn.disabled = true;
					btn.dataset.cf7asOriginalValue = btn.value || btn.textContent;
					if ( 'INPUT' === btn.tagName ) {
						btn.value = btn.dataset.cf7asOriginalValue + '…';
					}
				}
			} );

			document.addEventListener( 'wpcf7mailsent', re_enable );
			document.addEventListener( 'wpcf7mailfailed', re_enable );
			document.addEventListener( 'wpcf7spam', re_enable );
			document.addEventListener( 'wpcf7invalid', re_enable );
			document.addEventListener( 'wpcf7submit', re_enable );
		}
	} );

	function re_enable( e ) {
		var btn = e.target.querySelector( 'input[type="submit"], button[type="submit"]' );
		if ( btn ) {
			btn.disabled = false;
			if ( 'INPUT' === btn.tagName && btn.dataset.cf7asOriginalValue ) {
				btn.value = btn.dataset.cf7asOriginalValue;
			}
			delete btn.dataset.cf7asOriginalValue;
		}
	}
} )();
