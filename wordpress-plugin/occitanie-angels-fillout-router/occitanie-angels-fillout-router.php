<?php
/**
 * Plugin Name: Occitanie Angels - Fillout Router
 * Description: Page d'accueil qui vérifie si l'email du visiteur existe déjà dans Airtable (table Contacts) et le redirige vers le bon formulaire Fillout (Création ou Modification). Utilisation : shortcode [fillout_router].
 * Version: 1.0.0
 * Author: Occitanie Angels
 * Text Domain: oa-fillout-router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Pas d'accès direct.
}

define( 'OA_FILLOUT_OPTION_KEY', 'oa_fillout_router_settings' );
define( 'OA_FILLOUT_REST_NAMESPACE', 'oa-fillout/v1' );
define( 'OA_FILLOUT_VERSION', '1.0.0' );

/**
 * Valeurs par défaut, pré-remplies avec les informations fournies par l'association.
 * Modifiables ensuite dans Réglages > Fillout Router.
 */
function oa_fillout_default_settings() {
	return array(
		'airtable_base_id'     => 'appQwliKqpfcSOYb0',
		'airtable_table_id'    => 'tblmVqV8aXjNauqBl',
		'airtable_email_field' => 'Email',
		'airtable_token'       => '', // À renseigner dans les réglages (ou via la constante OA_FILLOUT_AIRTABLE_TOKEN dans wp-config.php).
		'fillout_create_url'   => 'https://occitanieangels.fillout.com/t/tajiPdzR5uus',
		'fillout_edit_url'     => 'https://occitanieangels.fillout.com/t/nAxnHFpKNmus',
		'fillout_edit_param'   => 'id',
		'rate_limit_per_min'   => 10,
	);
}

function oa_fillout_get_settings() {
	$saved = get_option( OA_FILLOUT_OPTION_KEY, array() );
	return wp_parse_args( $saved, oa_fillout_default_settings() );
}

/**
 * Le token Airtable : priorité à une constante définie dans wp-config.php
 * (recommandé, car elle n'est pas stockée en base de données), sinon
 * on retombe sur la valeur saisie dans l'écran de réglages.
 */
function oa_fillout_get_airtable_token( $settings ) {
	if ( defined( 'OA_FILLOUT_AIRTABLE_TOKEN' ) && OA_FILLOUT_AIRTABLE_TOKEN ) {
		return OA_FILLOUT_AIRTABLE_TOKEN;
	}
	return isset( $settings['airtable_token'] ) ? $settings['airtable_token'] : '';
}

register_activation_hook( __FILE__, function () {
	if ( false === get_option( OA_FILLOUT_OPTION_KEY, false ) ) {
		add_option( OA_FILLOUT_OPTION_KEY, oa_fillout_default_settings() );
	}
} );

/* -------------------------------------------------------------------------
 * Écran de réglages (Réglages > Fillout Router)
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_options_page(
		'Fillout Router',
		'Fillout Router',
		'manage_options',
		'oa-fillout-router',
		'oa_fillout_render_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'oa_fillout_router_group', OA_FILLOUT_OPTION_KEY, 'oa_fillout_sanitize_settings' );
} );

function oa_fillout_sanitize_settings( $input ) {
	$defaults = oa_fillout_default_settings();
	$out      = array();

	$out['airtable_base_id']     = sanitize_text_field( $input['airtable_base_id'] ?? $defaults['airtable_base_id'] );
	$out['airtable_table_id']    = sanitize_text_field( $input['airtable_table_id'] ?? $defaults['airtable_table_id'] );
	$out['airtable_email_field'] = sanitize_text_field( $input['airtable_email_field'] ?? $defaults['airtable_email_field'] );
	$out['airtable_token']       = trim( (string) ( $input['airtable_token'] ?? '' ) );
	$out['fillout_create_url']   = esc_url_raw( $input['fillout_create_url'] ?? $defaults['fillout_create_url'] );
	$out['fillout_edit_url']     = esc_url_raw( $input['fillout_edit_url'] ?? $defaults['fillout_edit_url'] );
	$out['fillout_edit_param']   = sanitize_key( $input['fillout_edit_param'] ?? $defaults['fillout_edit_param'] );
	$out['rate_limit_per_min']   = max( 1, (int) ( $input['rate_limit_per_min'] ?? $defaults['rate_limit_per_min'] ) );

	return $out;
}

function oa_fillout_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = oa_fillout_get_settings();
	$token_is_constant = defined( 'OA_FILLOUT_AIRTABLE_TOKEN' ) && OA_FILLOUT_AIRTABLE_TOKEN;
	?>
	<div class="wrap">
		<h1>Réglages - Fillout Router</h1>
		<p>Ces réglages pilotent le shortcode <code>[fillout_router]</code> : la page qui vérifie l'email du
			visiteur dans Airtable puis le redirige vers le bon formulaire Fillout.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'oa_fillout_router_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="airtable_token">Personal Access Token Airtable</label></th>
					<td>
						<?php if ( $token_is_constant ) : ?>
							<input type="password" value="••••••••••••" disabled style="width:400px" />
							<p class="description">Défini via la constante <code>OA_FILLOUT_AIRTABLE_TOKEN</code>
								dans <code>wp-config.php</code> (recommandé). Supprimez cette constante si vous
								préférez le saisir ici.</p>
						<?php else : ?>
							<input type="password" id="airtable_token"
								name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[airtable_token]"
								value="<?php echo esc_attr( $s['airtable_token'] ); ?>" style="width:400px"
								autocomplete="off" />
							<p class="description">
								Créez un token sur
								<a href="https://airtable.com/create/tokens" target="_blank" rel="noopener">airtable.com/create/tokens</a>
								avec le scope <code>data.records:read</code> limité à cette base uniquement.<br>
								Pour plus de sécurité, vous pouvez à la place définir dans <code>wp-config.php</code> :<br>
								<code>define('OA_FILLOUT_AIRTABLE_TOKEN', 'patXXXXXXXX...');</code>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="airtable_base_id">Base ID Airtable</label></th>
					<td><input type="text" id="airtable_base_id"
							name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[airtable_base_id]"
							value="<?php echo esc_attr( $s['airtable_base_id'] ); ?>" style="width:300px" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="airtable_table_id">Table Contacts (ID ou nom)</label></th>
					<td><input type="text" id="airtable_table_id"
							name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[airtable_table_id]"
							value="<?php echo esc_attr( $s['airtable_table_id'] ); ?>" style="width:300px" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="airtable_email_field">Nom du champ Email dans Airtable</label></th>
					<td><input type="text" id="airtable_email_field"
							name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[airtable_email_field]"
							value="<?php echo esc_attr( $s['airtable_email_field'] ); ?>" style="width:300px" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="fillout_create_url">URL Fillout - mode Création</label></th>
					<td><input type="url" id="fillout_create_url"
							name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[fillout_create_url]"
							value="<?php echo esc_attr( $s['fillout_create_url'] ); ?>" style="width:400px" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="fillout_edit_url">URL Fillout - mode Modification</label></th>
					<td><input type="url" id="fillout_edit_url"
							name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[fillout_edit_url]"
							value="<?php echo esc_attr( $s['fillout_edit_url'] ); ?>" style="width:400px" />
						<p class="description">Sans le paramètre d'ID à la fin, il sera ajouté automatiquement.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fillout_edit_param">Nom du paramètre d'URL pour le Record ID</label></th>
					<td><input type="text" id="fillout_edit_param"
							name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[fillout_edit_param]"
							value="<?php echo esc_attr( $s['fillout_edit_param'] ); ?>" style="width:150px" />
						<p class="description">D'après votre URL Fillout (<code>?id=RECORD_ID()</code>), ce
							paramètre est <code>id</code>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rate_limit_per_min">Limite de requêtes / minute / visiteur</label>
					</th>
					<td><input type="number" min="1" id="rate_limit_per_min"
							name="<?php echo esc_attr( OA_FILLOUT_OPTION_KEY ); ?>[rate_limit_per_min]"
							value="<?php echo esc_attr( $s['rate_limit_per_min'] ); ?>" style="width:100px" />
						<p class="description">Protection anti-abus (empêche de scanner en masse les emails
							existants).</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Utilisation</h2>
		<p>Ajoutez le shortcode <code>[fillout_router]</code> sur la page WordPress qui servira de page
			d'accueil (celle que vous partagerez sur LinkedIn / votre site).</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Shortcode : affiche le formulaire email + gère la redirection
 * ---------------------------------------------------------------------- */

add_shortcode( 'fillout_router', 'oa_fillout_render_shortcode' );

function oa_fillout_render_shortcode() {
	wp_enqueue_style(
		'oa-fillout-router',
		plugins_url( 'assets/router.css', __FILE__ ),
		array(),
		OA_FILLOUT_VERSION
	);
	wp_enqueue_script(
		'oa-fillout-router',
		plugins_url( 'assets/router.js', __FILE__ ),
		array(),
		OA_FILLOUT_VERSION,
		true
	);
	wp_localize_script( 'oa-fillout-router', 'oaFilloutRouter', array(
		'restUrl' => esc_url_raw( rest_url( OA_FILLOUT_REST_NAMESPACE . '/check-email' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );

	ob_start();
	?>
	<div class="oa-fillout-router" id="oa-fillout-router">
		<form id="oa-fillout-router-form" novalidate>
			<label for="oa-fillout-email">Votre adresse email</label>
			<input type="email" id="oa-fillout-email" name="email" required
				placeholder="vous@exemple.com" autocomplete="email" />

			<!-- Champ honeypot anti-bot : doit rester vide, caché visuellement -->
			<div class="oa-fillout-hp" aria-hidden="true">
				<label for="oa-fillout-website">Site web</label>
				<input type="text" id="oa-fillout-website" name="website" tabindex="-1" autocomplete="off" />
			</div>

			<button type="submit" id="oa-fillout-submit">Continuer</button>
			<p class="oa-fillout-message" id="oa-fillout-message" role="status"></p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * REST API : vérification de l'email dans Airtable
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( OA_FILLOUT_REST_NAMESPACE, '/check-email', array(
		'methods'             => 'POST',
		'callback'            => 'oa_fillout_handle_check_email',
		'permission_callback' => '__return_true',
		'args'                => array(
			'email' => array(
				'required' => true,
				'type'     => 'string',
			),
		),
	) );
} );

function oa_fillout_handle_check_email( WP_REST_Request $request ) {
	$settings = oa_fillout_get_settings();

	// Honeypot : si rempli, on considère que c'est un bot, on renvoie une réponse neutre sans appeler Airtable.
	$honeypot = $request->get_param( 'website' );
	if ( ! empty( $honeypot ) ) {
		return new WP_Error( 'oa_fillout_bot', 'Requête invalide.', array( 'status' => 400 ) );
	}

	// Rate limiting simple par IP.
	$ip           = oa_fillout_get_client_ip();
	$rl_key       = 'oa_fillout_rl_' . md5( $ip );
	$current_hits = (int) get_transient( $rl_key );
	if ( $current_hits >= (int) $settings['rate_limit_per_min'] ) {
		return new WP_Error( 'oa_fillout_rate_limited', 'Trop de tentatives, réessayez dans une minute.', array( 'status' => 429 ) );
	}
	set_transient( $rl_key, $current_hits + 1, MINUTE_IN_SECONDS );

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( empty( $email ) || ! is_email( $email ) ) {
		return new WP_Error( 'oa_fillout_invalid_email', 'Adresse email invalide.', array( 'status' => 400 ) );
	}

	$token = oa_fillout_get_airtable_token( $settings );
	if ( empty( $token ) ) {
		return new WP_Error( 'oa_fillout_not_configured', 'Le plugin n\'est pas encore configuré (token Airtable manquant).', array( 'status' => 500 ) );
	}

	$record = oa_fillout_lookup_airtable_record_by_email( $email, $settings, $token );
	if ( is_wp_error( $record ) ) {
		return $record;
	}

	if ( $record ) {
		$redirect_url = add_query_arg(
			array( $settings['fillout_edit_param'] => $record['id'] ),
			$settings['fillout_edit_url']
		);
	} else {
		$redirect_url = $settings['fillout_create_url'];
	}

	return array(
		'exists'      => (bool) $record,
		'redirectUrl' => $redirect_url,
	);
}

/**
 * Interroge Airtable pour trouver un enregistrement dont le champ email
 * correspond (comparaison insensible à la casse), et renvoie son record ID.
 */
function oa_fillout_lookup_airtable_record_by_email( $email, $settings, $token ) {
	$field_name = $settings['airtable_email_field'];

	// Échappement de l'email pour l'insérer sans risque dans la formule Airtable.
	$escaped_email = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $email );
	$formula       = sprintf(
		'LOWER({%s}) = LOWER("%s")',
		$field_name,
		$escaped_email
	);

	$endpoint = sprintf(
		'https://api.airtable.com/v0/%s/%s',
		rawurlencode( $settings['airtable_base_id'] ),
		rawurlencode( $settings['airtable_table_id'] )
	);
	$endpoint = add_query_arg( array(
		'filterByFormula' => $formula,
		'maxRecords'      => 1,
	), $endpoint );

	$response = wp_remote_get( $endpoint, array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
		),
		'timeout' => 10,
	) );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'oa_fillout_airtable_unreachable', 'Impossible de contacter Airtable pour le moment.', array( 'status' => 502 ) );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$msg = $body['error']['message'] ?? 'Erreur Airtable inconnue.';
		return new WP_Error( 'oa_fillout_airtable_error', 'Erreur Airtable : ' . $msg, array( 'status' => 502 ) );
	}

	if ( ! empty( $body['records'] ) && isset( $body['records'][0]['id'] ) ) {
		return array( 'id' => $body['records'][0]['id'] );
	}

	return null;
}

function oa_fillout_get_client_ip() {
	foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = explode( ',', wp_unslash( $_SERVER[ $key ] ) )[0];
			return trim( $ip );
		}
	}
	return '0.0.0.0';
}
