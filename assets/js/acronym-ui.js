/**
 * Acronym UI — accessible tooltip for .aui-acronym elements.
 *
 * The native `title` attribute is left in place (fallback for no-JS and for
 * screen readers), and this layers a visual tooltip bubble on top, shown on
 * hover/focus for pointer devices and toggled by click/tap everywhere —
 * which is what makes it usable on mobile, where hover doesn't exist.
 * No jQuery dependency.
 */
( function () {
	'use strict';

	function initAcronymTooltips() {
		var triggers = document.querySelectorAll( '.aui-acronym[title]' );
		if ( ! triggers.length ) {
			return;
		}

		var bubble = document.createElement( 'div' );
		bubble.className = 'aui-tooltip';
		bubble.setAttribute( 'role', 'tooltip' );
		document.body.appendChild( bubble );

		var current = null;

		function position( trigger ) {
			var rect = trigger.getBoundingClientRect();
			bubble.style.top = rect.bottom + window.scrollY + 6 + 'px';
			bubble.style.left = rect.left + window.scrollX + 'px';
		}

		function show( trigger ) {
			bubble.textContent = trigger.getAttribute( 'title' ) || '';
			position( trigger );
			bubble.classList.add( 'is-visible' );
			current = trigger;
		}

		function hide() {
			bubble.classList.remove( 'is-visible' );
			current = null;
		}

		Array.prototype.forEach.call( triggers, function ( trigger ) {
			trigger.addEventListener( 'mouseenter', function () {
				show( trigger );
			} );
			trigger.addEventListener( 'mouseleave', hide );
			trigger.addEventListener( 'focus', function () {
				show( trigger );
			} );
			trigger.addEventListener( 'blur', hide );
			trigger.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				if ( current === trigger ) {
					hide();
				} else {
					show( trigger );
				}
			} );
		} );

		document.addEventListener( 'click', function () {
			if ( current ) {
				hide();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && current ) {
				hide();
			}
		} );

		window.addEventListener(
			'scroll',
			function () {
				if ( current ) {
					position( current );
				}
			},
			{ passive: true }
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAcronymTooltips );
	} else {
		initAcronymTooltips();
	}
} )();
