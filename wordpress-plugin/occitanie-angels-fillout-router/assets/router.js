( function () {
	'use strict';

	var form = document.getElementById( 'oa-fillout-router-form' );
	if ( ! form ) {
		return;
	}

	var emailInput  = document.getElementById( 'oa-fillout-email' );
	var submitBtn   = document.getElementById( 'oa-fillout-submit' );
	var messageEl   = document.getElementById( 'oa-fillout-message' );
	var honeypotEl  = document.getElementById( 'oa-fillout-website' );

	function setMessage( text, type ) {
		messageEl.textContent = text || '';
		messageEl.className = 'oa-fillout-message' + ( type ? ' is-' + type : '' );
	}

	function setLoading( isLoading ) {
		submitBtn.disabled = isLoading;
		submitBtn.textContent = isLoading ? 'Vérification…' : 'Continuer';
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		setMessage( '' );

		var email = ( emailInput.value || '' ).trim();
		if ( ! email || email.indexOf( '@' ) === -1 ) {
			setMessage( 'Merci de saisir une adresse email valide.', 'error' );
			emailInput.focus();
			return;
		}

		setLoading( true );

		fetch( oaFilloutRouter.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': oaFilloutRouter.nonce
			},
			body: JSON.stringify( {
				email: email,
				website: honeypotEl ? honeypotEl.value : ''
			} )
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				if ( ! result.ok || ! result.data || ! result.data.redirectUrl ) {
					var msg = ( result.data && result.data.message )
						? result.data.message
						: 'Une erreur est survenue. Merci de réessayer.';
					setMessage( msg, 'error' );
					setLoading( false );
					return;
				}

				setMessage( 'Redirection en cours…', 'success' );
				window.location.href = result.data.redirectUrl;
			} )
			.catch( function () {
				setMessage( 'Impossible de vous rediriger pour le moment. Merci de réessayer dans un instant.', 'error' );
				setLoading( false );
			} );
	} );
} )();
