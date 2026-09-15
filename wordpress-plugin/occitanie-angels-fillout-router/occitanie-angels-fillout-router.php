<?php
/**
 * Plugin Name: Occitanie Angels - Fillout Router
 * Description: Page d'accueil qui vérifie si l'email du visiteur existe déjà dans Airtable et le redirige vers le bon formulaire Fillout (Création ou Modification). Supporte plusieurs configurations. Utilisation : shortcode [fillout_router config="nom"].
 * Version: 2.1.0
 * Author: Occitanie Angels
 * Text Domain: oa-fillout-router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Pas d'accès direct.
}

define( 'OA_FILLOUT_OPTION_KEY', 'oa_fillout_router_settings' );
define( 'OA_FILLOUT_REST_NAMESPACE', 'oa-fillout/v1' );
define( 'OA_FILLOUT_VERSION', '2.1.0' );
define( 'OA_FILLOUT_DEFAULT_CONFIG', 'default' );

/**
 * Valeurs par défaut : une configuration "default" pré-remplie avec les
 * informations fournies par l'association. Tout est modifiable ensuite dans
 * Réglages > Fillout Router, et d'autres configurations peuvent y être
 * ajoutées (une par couple de formulaires Fillout / table Airtable).
 */
function oa_fillout_default_settings() {
	return array(
		'airtable_token'     => '', // À renseigner dans les réglages (ou via la constante OA_FILLOUT_AIRTABLE_TOKEN dans wp-config.php).
		'rate_limit_per_min' => 10,
		'configs'            => array(
			OA_FILLOUT_DEFAULT_CONFIG => array(
				'label'                => 'Adhésion (Contacts)',
				'heading'              => 'Bienvenue !',
				'subheading'           => 'Renseignez votre adresse email pour accéder au formulaire adapté à votre situation.',
				'airtable_base_id'     => 'appQwliKqpfcSOYb0',
				'airtable_table_id'    => 'tblmVqV8aXjNauqBl',
				'airtable_email_field' => 'Email',
				'fillout_create_url'   => 'https://occitanieangels.fillout.com/t/tajiPdzR5uus',
				'fillout_edit_url'     => 'https://occitanieangels.fillout.com/t/nAxnHFpKNmus',
				'fillout_edit_param'   => 'id',
			),
		),
	);
}

function oa_fillout_get_settings() {
	$saved    = get_option( OA_FILLOUT_OPTION_KEY, array() );
	$defaults = oa_fillout_default_settings();
	$settings = wp_parse_args( $saved, $defaults );
	if ( empty( $settings['configs'] ) || ! is_array( $settings['configs'] ) ) {
		$settings['configs'] = $defaults['configs'];
	}
	return $settings;
}

function oa_fillout_get_config( $settings, $slug ) {
	$slug = sanitize_key( $slug );
	return isset( $settings['configs'][ $slug ] ) ? $settings['configs'][ $slug ] : null;
}

/**
 * Le token Airtable : priorité à une constante définie dans wp-config.php
 * (recommandé, car elle n'est pas stockée en base de données), sinon
 * on retombe sur la valeur saisie dans l'écran de réglages. Un seul token
 * est utilisé pour toutes les configurations : créez-le avec accès à
 * toutes les bases Airtable concernées.
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
	$out                       = array();
	$out['airtable_token']     = trim( (string) ( $input['airtable_token'] ?? '' ) );
	$out['rate_limit_per_min'] = max( 1, (int) ( $input['rate_limit_per_min'] ?? 10 ) );

	$configs      = array();
	$config_rows  = isset( $input['configs'] ) && is_array( $input['configs'] ) ? $input['configs'] : array();

	foreach ( $config_rows as $row ) {
		$slug = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
		if ( empty( $slug ) ) {
			continue; // Ligne vide (ex. la ligne "nouvelle configuration" non utilisée) : ignorée.
		}
		if ( ! empty( $row['delete'] ) ) {
			continue; // Configuration supprimée par l'utilisateur.
		}

		$configs[ $slug ] = array(
			'label'                => sanitize_text_field( $row['label'] ?? $slug ),
			'heading'              => sanitize_text_field( $row['heading'] ?? 'Bienvenue !' ),
			'subheading'           => sanitize_textarea_field( $row['subheading'] ?? '' ),
			'airtable_base_id'     => sanitize_text_field( $row['airtable_base_id'] ?? '' ),
			'airtable_table_id'    => sanitize_text_field( $row['airtable_table_id'] ?? '' ),
			'airtable_email_field' => sanitize_text_field( $row['airtable_email_field'] ?? 'Email' ),
			'fillout_create_url'   => esc_url_raw( $row['fillout_create_url'] ?? '' ),
			'fillout_edit_url'     => esc_url_raw( $row['fillout_edit_url'] ?? '' ),
			'fillout_edit_param'   => sanitize_key( $row['fillout_edit_param'] ?? 'id' ),
		);
	}

	if ( empty( $configs ) ) {
		$defaults = oa_fillout_default_settings();
		$configs  = $defaults['configs'];
	}

	$out['configs'] = $configs;

	return $out;
}

function oa_fillout_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s                  = oa_fillout_get_settings();
	$token_is_constant  = defined( 'OA_FILLOUT_AIRTABLE_TOKEN' ) && OA_FILLOUT_AIRTABLE_TOKEN;
	$rows               = $s['configs'];
	$rows['']           = array( // Ligne vierge en bas de liste pour ajouter une configuration.
		'label'                => '',
		'heading'              => 'Bienvenue !',
		'subheading'           => 'Renseignez votre adresse email pour accéder au formulaire adapté à votre situation.',
		'airtable_base_id'     => '',
		'airtable_table_id'    => '',
		'airtable_email_field' => 'Email',
		'fillout_create_url'   => '',
		'fillout_edit_url'     => '',
		'fillout_edit_param'   => 'id',
	);
	$option_key         = OA_FILLOUT_OPTION_KEY;
	$row_index          = 0;
	?>
	<div class="wrap">
		<h1>Réglages - Fillout Router</h1>
		<p>Chaque configuration ci-dessous correspond à un couple de formulaires Fillout (Création +
			Modification) branché sur une table Airtable. Utilisez-la avec
			<code>[fillout_router config="votre-slug"]</code> (le slug <code>default</code> s'utilise avec
			simplement <code>[fillout_router]</code>).</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'oa_fillout_router_group' ); ?>

			<h2>Réglages généraux</h2>
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
								name="<?php echo esc_attr( $option_key ); ?>[airtable_token]"
								value="<?php echo esc_attr( $s['airtable_token'] ); ?>" style="width:400px"
								autocomplete="off" />
							<p class="description">
								Créez un token sur
								<a href="https://airtable.com/create/tokens" target="_blank" rel="noopener">airtable.com/create/tokens</a>
								avec le scope <code>data.records:read</code>, en donnant accès à
								<strong>toutes les bases</strong> utilisées par vos configurations ci-dessous
								(un seul token sert à toutes les configurations).<br>
								Pour plus de sécurité, vous pouvez à la place définir dans <code>wp-config.php</code> :<br>
								<code>define('OA_FILLOUT_AIRTABLE_TOKEN', 'patXXXXXXXX...');</code>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rate_limit_per_min">Limite de requêtes / minute / visiteur</label>
					</th>
					<td><input type="number" min="1" id="rate_limit_per_min"
							name="<?php echo esc_attr( $option_key ); ?>[rate_limit_per_min]"
							value="<?php echo esc_attr( $s['rate_limit_per_min'] ); ?>" style="width:100px" />
						<p class="description">Protection anti-abus (empêche de scanner en masse les emails
							existants), tous formulaires confondus.</p>
					</td>
				</tr>
			</table>

			<h2>Configurations (couples de formulaires)</h2>
			<?php foreach ( $rows as $slug => $cfg ) : $row_index++; $is_new = ( '' === $slug ); ?>
				<fieldset style="border:1px solid #ccd0d4;padding:1rem 1.5rem;margin-bottom:1.5rem;background:#fff;">
					<legend style="font-weight:600;padding:0 .5rem;">
						<?php echo $is_new ? 'Ajouter une nouvelle configuration' : esc_html( $slug ); ?>
					</legend>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Identifiant (slug)</th>
							<td>
								<input type="text"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][slug]"
									value="<?php echo esc_attr( $slug ); ?>" style="width:220px"
									placeholder="ex: evenement-2026" />
								<p class="description">Utilisé dans le shortcode : <code>[fillout_router config="<?php echo esc_html( $slug ?: 'votre-slug' ); ?>"]</code>. Lettres minuscules, chiffres, tirets.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Libellé (repère interne)</th>
							<td><input type="text"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][label]"
									value="<?php echo esc_attr( $cfg['label'] ); ?>" style="width:300px" /></td>
						</tr>
						<tr>
							<th scope="row">Titre affiché</th>
							<td><input type="text"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][heading]"
									value="<?php echo esc_attr( $cfg['heading'] ?? 'Bienvenue !' ); ?>" style="width:300px" /></td>
						</tr>
						<tr>
							<th scope="row">Sous-texte affiché</th>
							<td><textarea
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][subheading]"
									rows="2" style="width:400px"><?php echo esc_textarea( $cfg['subheading'] ?? '' ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row">Base ID Airtable</th>
							<td><input type="text"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][airtable_base_id]"
									value="<?php echo esc_attr( $cfg['airtable_base_id'] ); ?>" style="width:300px" /></td>
						</tr>
						<tr>
							<th scope="row">Table (ID ou nom)</th>
							<td><input type="text"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][airtable_table_id]"
									value="<?php echo esc_attr( $cfg['airtable_table_id'] ); ?>" style="width:300px" /></td>
						</tr>
						<tr>
							<th scope="row">Nom du champ Email</th>
							<td><input type="text"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][airtable_email_field]"
									value="<?php echo esc_attr( $cfg['airtable_email_field'] ); ?>" style="width:300px" /></td>
						</tr>
						<tr>
							<th scope="row">URL Fillout - mode Création</th>
							<td><input type="url"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][fillout_create_url]"
									value="<?php echo esc_attr( $cfg['fillout_create_url'] ); ?>" style="width:400px" /></td>
						</tr>
						<tr>
							<th scope="row">URL Fillout - mode Modification</th>
							<td><input type="url"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][fillout_edit_url]"
									value="<?php echo esc_attr( $cfg['fillout_edit_url'] ); ?>" style="width:400px" />
								<p class="description">Sans le paramètre d'ID à la fin, il sera ajouté automatiquement.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Paramètre d'URL pour le Record ID</th>
							<td><input type="text"
									name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][fillout_edit_param]"
									value="<?php echo esc_attr( $cfg['fillout_edit_param'] ); ?>" style="width:150px" /></td>
						</tr>
						<?php if ( ! $is_new ) : ?>
							<tr>
								<th scope="row">Supprimer</th>
								<td>
									<label>
										<input type="checkbox"
											name="<?php echo esc_attr( $option_key ); ?>[configs][<?php echo $row_index; ?>][delete]"
											value="1" />
										Supprimer cette configuration à l'enregistrement
									</label>
								</td>
							</tr>
						<?php endif; ?>
					</table>
				</fieldset>
			<?php endforeach; ?>

			<?php submit_button(); ?>
		</form>

		<h2>Utilisation</h2>
		<p>Ajoutez le shortcode <code>[fillout_router config="votre-slug"]</code> sur la page WordPress
			correspondante (le paramètre <code>config</code> peut être omis pour la configuration
			<code>default</code>). Vous pouvez utiliser plusieurs shortcodes, avec des slugs différents, sur des
			pages différentes (ou même sur la même page).</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Shortcode : affiche le formulaire email + gère la redirection
 * ---------------------------------------------------------------------- */

add_shortcode( 'fillout_router', 'oa_fillout_render_shortcode' );

function oa_fillout_render_shortcode( $atts ) {
	static $instance = 0;
	$instance++;

	$atts = shortcode_atts( array(
		'config' => OA_FILLOUT_DEFAULT_CONFIG,
	), $atts, 'fillout_router' );

	$config_slug = sanitize_key( $atts['config'] );
	$settings    = oa_fillout_get_settings();
	$config      = oa_fillout_get_config( $settings, $config_slug );

	if ( null === $config ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p><strong>Fillout Router :</strong> la configuration "' . esc_html( $config_slug ) . '" est introuvable. Vérifiez Réglages → Fillout Router.</p>';
		}
		return '<p>Ce formulaire n\'est pas disponible pour le moment. Merci de nous contacter.</p>';
	}

	wp_enqueue_style(
		'oa-fillout-router-font-inter',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'oa-fillout-router',
		plugins_url( 'assets/router.css', __FILE__ ),
		array( 'oa-fillout-router-font-inter' ),
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

	$heading    = ! empty( $config['heading'] ) ? $config['heading'] : 'Bienvenue !';
	$subheading = ! empty( $config['subheading'] )
		? $config['subheading']
		: 'Renseignez votre adresse email pour accéder au formulaire adapté à votre situation.';

	ob_start();
	?>
	<div class="oa-fillout-router" id="oa-fillout-router-<?php echo (int) $instance; ?>">
		<div class="oa-fillout-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
				<polyline points="22,6 12,13 2,6"></polyline>
			</svg>
		</div>
		<h2 class="oa-fillout-heading"><?php echo esc_html( $heading ); ?></h2>
		<p class="oa-fillout-subheading"><?php echo esc_html( $subheading ); ?></p>

		<form class="oa-fillout-router-form" data-config="<?php echo esc_attr( $config_slug ); ?>" novalidate>
			<label class="oa-fillout-sr-only" for="oa-fillout-email-<?php echo (int) $instance; ?>">Votre adresse email</label>
			<input type="email" id="oa-fillout-email-<?php echo (int) $instance; ?>"
				class="oa-fillout-email-input" name="email" required placeholder="votre@email.com"
				autocomplete="email" />

			<!-- Champ honeypot anti-bot : doit rester vide, caché visuellement -->
			<div class="oa-fillout-hp" aria-hidden="true">
				<label for="oa-fillout-website-<?php echo (int) $instance; ?>">Site web</label>
				<input type="text" id="oa-fillout-website-<?php echo (int) $instance; ?>"
					class="oa-fillout-website-input" name="website" tabindex="-1" autocomplete="off" />
			</div>

			<button type="submit" class="oa-fillout-submit-btn">
				<span class="oa-fillout-submit-label">Continuer</span>
				<svg class="oa-fillout-arrow" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="5" y1="12" x2="19" y2="12"></line>
					<polyline points="12 5 19 12 12 19"></polyline>
				</svg>
			</button>
			<p class="oa-fillout-message" role="status"></p>
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
			'email'  => array(
				'required' => true,
				'type'     => 'string',
			),
			'config' => array(
				'required' => false,
				'type'     => 'string',
				'default'  => OA_FILLOUT_DEFAULT_CONFIG,
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

	// Rate limiting simple par IP (tous formulaires confondus).
	$ip           = oa_fillout_get_client_ip();
	$rl_key       = 'oa_fillout_rl_' . md5( $ip );
	$current_hits = (int) get_transient( $rl_key );
	if ( $current_hits >= (int) $settings['rate_limit_per_min'] ) {
		return new WP_Error( 'oa_fillout_rate_limited', 'Trop de tentatives, réessayez dans une minute.', array( 'status' => 429 ) );
	}
	set_transient( $rl_key, $current_hits + 1, MINUTE_IN_SECONDS );

	$config_slug = (string) $request->get_param( 'config' );
	$config      = oa_fillout_get_config( $settings, $config_slug ?: OA_FILLOUT_DEFAULT_CONFIG );
	if ( null === $config ) {
		return new WP_Error( 'oa_fillout_unknown_config', 'Configuration inconnue.', array( 'status' => 404 ) );
	}

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( empty( $email ) || ! is_email( $email ) ) {
		return new WP_Error( 'oa_fillout_invalid_email', 'Adresse email invalide.', array( 'status' => 400 ) );
	}

	$token = oa_fillout_get_airtable_token( $settings );
	if ( empty( $token ) ) {
		return new WP_Error( 'oa_fillout_not_configured', 'Le plugin n\'est pas encore configuré (token Airtable manquant).', array( 'status' => 500 ) );
	}

	$record = oa_fillout_lookup_airtable_record_by_email( $email, $config, $token );
	if ( is_wp_error( $record ) ) {
		return $record;
	}

	if ( $record ) {
		$redirect_url = add_query_arg(
			array( $config['fillout_edit_param'] => $record['id'] ),
			$config['fillout_edit_url']
		);
	} else {
		$redirect_url = $config['fillout_create_url'];
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
function oa_fillout_lookup_airtable_record_by_email( $email, $config, $token ) {
	$field_name = $config['airtable_email_field'];

	// Échappement de l'email pour l'insérer sans risque dans la formule Airtable.
	$escaped_email = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $email );
	$formula       = sprintf(
		'LOWER({%s}) = LOWER("%s")',
		$field_name,
		$escaped_email
	);

	$endpoint = sprintf(
		'https://api.airtable.com/v0/%s/%s',
		rawurlencode( $config['airtable_base_id'] ),
		rawurlencode( $config['airtable_table_id'] )
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
