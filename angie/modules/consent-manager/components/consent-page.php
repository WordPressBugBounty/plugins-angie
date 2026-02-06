<?php

namespace Angie\Modules\ConsentManager\Components;

use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Modules\AngieApp\Components\Angie_App;
use Angie\Includes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Consent Page Component
 *
 * Creates a welcome page for managing consent settings with OAuth integration
 */
class Consent_Page {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_init', [ $this, 'handle_reset_action' ] );
	}

	public function register_admin_menu() {
		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );

		// Only show submenu when consent is already granted (for settings)
		if ( ConsentManager::has_consent() ) {
			add_submenu_page(
				'angie-app', // Parent slug.
				esc_html__( 'Angie Settings', 'angie' ),
				esc_html__( 'Settings', 'angie' ),
				'manage_options',
				'angie-consent',
				[ $this, 'render_consent_page' ],
				20 // Lower priority for settings
			);
		}
	}

	/**
	 * Enqueue scripts for the consent page
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_angie-app' !== $hook && 'angie-app_page_angie-consent' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External Google Fonts resource, version not applicable
		wp_enqueue_style(
			'angie-google-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
			[],
			null
		);

		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );
		
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-i18n' );
		
		// Set up wp.apiFetch configuration
		wp_add_inline_script( 'wp-api-fetch', sprintf(
			'wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( %s ) );' .
			'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( %s ) );',
			wp_json_encode( rest_url() ),
			wp_json_encode( wp_create_nonce( 'wp_rest' ) )
		), 'after' );
		
		wp_localize_script( 'wp-api-fetch', 'angieConsent', [
			'restUrl' => rest_url( 'angie/v1/consent' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'hasConsent' => $consent === 'yes',
		] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route( 'angie/v1', '/consent', [
			'methods' => 'POST',
			'callback' => [ $this, 'handle_consent_grant_rest' ],
			'permission_callback' => [ $this, 'check_consent_permissions' ],
		] );
	}

	public function register_settings() {
		register_setting(
			'angie_consent_settings',
			ConsentManager::CONSENT_OPTION_NAME,
			[
				'type'              => 'string',
				'default'           => 'no',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
	}

	/**
	 * Check permissions for consent REST endpoint
	 */
	public function check_consent_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle REST API request to grant consent and start OAuth
	 */
	public function handle_consent_grant_rest( $request ) {
		// Grant consent
		update_option( ConsentManager::CONSENT_OPTION_NAME, 'yes' );
		
		// Validate OAuth parameter before including in redirect
		$oauth_param = filter_input( INPUT_GET, 'start-oauth', FILTER_VALIDATE_INT );
		$redirect_url = admin_url( 'admin.php?page=angie-app' );
		if ( $oauth_param === 1 ) {
			$redirect_url .= '&start-oauth=1';
		}
		
		return new \WP_REST_Response( [
			'message' => 'Consent granted successfully',
			'redirect' => admin_url( 'admin.php?page=angie-app&start-oauth=1' ),
		], 200 );
	}

	public function handle_reset_action() {
		$page    = Utils::get_sanitized_query_var( 'page' );
		$action  = Utils::get_sanitized_query_var( 'action' );
		$wpnonce = Utils::get_sanitized_query_var( '_wpnonce' );

		if ( 'angie-consent' !== $page ) {
			return;
		}

		if ( 'reset' !== $action ) {
			return;
		}

		if ( ! $wpnonce || ! wp_verify_nonce( $wpnonce, 'angie_reset_consent' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'angie' ) );
		}

		// Reset the consent setting.
		delete_option( ConsentManager::CONSENT_OPTION_NAME );

		// Redirect to main Angie app (which will show welcome page since consent is now reset)
		wp_safe_redirect( admin_url( 'admin.php?page=angie-app' ) );
		exit;
	}

	public function render_consent_page() {
		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );
		
		// If consent is already granted, show settings page
		if ( 'yes' === $consent ) {
			$this->render_settings_page();
			return;
		}
		
		?>
		<style>
			body {
				background-color: #FFFFFF;
			}
		</style>
		
		<div class="angie-welcome-page" data-testid="angie-welcome-page">
			<div class="angie-welcome-layout" data-testid="angie-welcome-layout">
				<!-- Left Side Content -->
				<div class="angie-welcome-left" data-testid="angie-welcome-left">
					<h4><?php esc_html_e( 'Ready to unlock Angie\'s full potential?', 'angie' ); ?></h4>
					<p><?php esc_html_e( 'Meet Angie, Agentic AI built for WordPress that gets real work done for you. From launching pages to bulk updates, Angie turns hours of manual tasks into minutes so you can stay focused on what matters.', 'angie' ); ?></p>
					
					<div class="angie-consent-section" data-testid="angie-consent-section">
						<label class="angie-consent-checkbox" data-testid="angie-consent-checkbox">
							<input type="checkbox" id="angie-terms-consent" />
							<span class="checkmark"></span>
							<span class="consent-text">
								<?php esc_html_e( 'I agree to the ', 'angie' ); ?>
								<a href="https://go.elementor.com/angie-terms" target="_blank"><?php esc_html_e( 'Terms of Service', 'angie' ); ?></a>
								<?php esc_html_e( ' and ', 'angie' ); ?>
								<a href="https://go.elementor.com/ai-privacy-policy/" target="_blank"><?php esc_html_e( 'Privacy Policy', 'angie' ); ?></a>.
							</span>
						</label>
					</div>
					
					<button class="angie-signin-button" id="angie-signin-btn" disabled data-testid="angie-signin-btn">
						<?php esc_html_e( 'Sign in to continue', 'angie' ); ?>
					</button>
				</div>
				
				<!-- Right Side - Image -->
				<div class="angie-welcome-right" data-testid="angie-welcome-right">
					<div class="angie-right-container" data-testid="angie-right-container">
						<img src="<?php echo esc_url( Utils::get_asset_url( 'askAngieImage.png', __DIR__ ) ); ?>" 
							 alt="<?php esc_attr_e( 'Ask Angie AI Assistant', 'angie' ); ?>" 
							 class="angie-ask-image" data-testid="angie-ask-image" />
					</div>
				</div>
			</div>
		</div>
		
		<?php $this->render_welcome_styles_and_scripts(); ?>
		<?php
	}



	private function render_settings_page() {
		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );
		$settings_class = new \Angie\Modules\AngieSettings\Components\Settings();
		$website_uuid = $settings_class->get_website_uuid();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Angie Settings', 'angie' ); ?></h1>
			
			<div class="card" style="max-width: 800px;">
				<h2><?php esc_html_e( 'External Script Loading', 'angie' ); ?></h2>
				<p><strong><?php esc_html_e( 'Status:', 'angie' ); ?></strong> 
					<?php if ( 'yes' === $consent ) : ?>
						<span style="color: green;"><?php esc_html_e( 'Approved', 'angie' ); ?></span>
					<?php else : ?>
						<span style="color: red;"><?php esc_html_e( 'Not Approved', 'angie' ); ?></span>
					<?php endif; ?>
				</p>
				<p><?php esc_html_e( 'You have approved the loading of external scripts required for Angie functionality.', 'angie' ); ?></p>
				<hr style="border: none; height: 1px; background-color: lightgray;">
				<div style="margin-top: 20px;">
					<p><?php esc_html_e( 'Want to revoke your approval for any reason?', 'angie' ); ?></p>
					<p><?php esc_html_e( 'Please note that this action will revoke the permission for all users to use Angie on this website.', 'angie' ); ?></p>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'reset' ), 'angie_reset_consent' ) ); ?>" class="button button-secondary" style="color: #2271b1; background: white; border-color: #2271b1;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to deactivate Angie?', 'angie' ); ?>');">
						<?php esc_html_e( 'Deactivate Angie on this website', 'angie' ); ?>
					</a>
				</div>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Website Information', 'angie' ); ?></h2>
				<table class="form-table" style="table-layout: auto;">
					<tr>
						<th scope="row" style="padding-left: 0; width: auto;">
							<?php esc_html_e( 'Website Unique ID:', 'angie' ); ?>
						</th>
						<td style="padding-left: 20px;">
							<code style="background: #f7f7f7; padding: 4px 8px; border-radius: 3px; font-family: monospace; display: inline-block;"><?php echo esc_html( $website_uuid ); ?></code>
							<p class="description" style="margin-top: 5px;">
								<?php esc_html_e( 'This is your website\'s unique identifier used by Angie services.', 'angie' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	private function render_welcome_styles_and_scripts() {
		// Enqueue styles
		wp_enqueue_style(
			'angie-consent-page',
			Utils::get_asset_url( 'consent-page-styles.css', __DIR__ ),
			[],
			ANGIE_VERSION
		);
		
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const checkbox = document.getElementById('angie-terms-consent');
				const button = document.getElementById('angie-signin-btn');
				const hasConsent = <?php echo json_encode( get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' ) === 'yes' ); ?>;
				
				let isProcessing = false;
				
				checkbox.addEventListener('change', function() {
					button.disabled = !this.checked;
				});
				
				button.addEventListener('click', function() {
					if (button.disabled || isProcessing) return;
					
					isProcessing = true;
					
					button.classList.add('loading');
					button.textContent = 'Processing...';
					button.disabled = true;
					
					wp.apiFetch({
						path: '/angie/v1/consent',
						method: 'POST'
					})
					.then(function(response) {
						console.log('Consent granted successfully');
						button.textContent = 'Redirecting...';
						window.location.href = response.redirect;
					})
					.catch(function(error) {
						console.error('Request failed:', error);
						resetButton();
					});
				});
				
				function resetButton() {
					isProcessing = false;
					button.classList.remove('loading');
					button.textContent = 'Sign in to continue';
					button.disabled = !checkbox.checked;
				}
			});
		</script>
		<?php
	}
}
