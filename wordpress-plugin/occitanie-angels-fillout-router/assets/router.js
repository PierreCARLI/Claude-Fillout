( function () {
	'use strict';

	var forms = document.querySelectorAll( '.oa-fillout-router-form' );

	forms.forEach( function ( form ) {
		var emailInput  = form.querySelector( '.oa-fillout-email-input' );
		var submitBtn   = form.querySelector( '.oa-fillout-submit-btn' );
		var submitLabel = form.querySelector( '.oa-fillout-submit-label' );
		var messageEl   = form.querySelector( '.oa-fillout-message' );
		var honeypotEl  = form.querySelector( '.oa-fillout-website-input' );
		var configSlug  = form.dataset.config || 'default';
		var dynamicFormUrl = form.dataset.dynamicFormUrl || '';

		function setMessage( text, type ) {
			messageEl.textContent = text || '';
			messageEl.className = 'oa-fillout-message' + ( type ? ' is-' + type : '' );
		}

		function setLoading( isLoading ) {
			submitBtn.disabled = isLoading;
			submitLabel.textContent = isLoading ? 'Vérification…' : 'Continuer';
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
					config: configSlug,
					form_url: dynamicFormUrl,
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
	} );
} )();
