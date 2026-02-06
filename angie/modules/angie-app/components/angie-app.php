<?php

namespace Angie\Modules\AngieApp\Components;

use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Modules\ConsentManager\Components\Consent_Page;
use Angie\Modules\CodeSnippets\Module as CodeSnippets;
use Angie\Includes\Utils;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Angie App Component
 *
 * Creates a page to load the external Angie app via script
 */
class Angie_App {

	const OPTION_CONNECT_SITE_KEY = 'elementor_connect_site_key';


	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1 );
		add_filter( 'body_class', [ $this, 'add_angie_body_class' ] );
	}

	/**
	 * Register the main menu for Angie App
	 *
	 * @param callable|null $callback Callback function to render the page.
	 */
	public static function register_main_menu( $callback = null ) {
		// Custom SVG icon for Angie menu
		$svg_icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#a7aaad" viewBox="0 0 20 20"><path d="M0 3.36h3.36v14.428H0zM17.788 0v3.36H3.36V0zM12.225 4.413c.818 3.938 3.701 7.21 7.719 7.79-4.146.6-7.035 3.622-7.72 7.74-.643-3.872-3.96-6.885-7.755-7.74 3.798-.814 7.063-3.946 7.756-7.79ZM17.748 4.47c.233 1.113 1.109 2.036 2.252 2.2-1.18.17-2.057 1.047-2.252 2.213-.183-1.096-1.081-1.971-2.161-2.213 1.08-.23 1.964-1.113 2.16-2.2Z"/></svg>'
		);

		add_menu_page(
			esc_html__( 'Angie', 'angie' ),
			esc_html__( 'Angie', 'angie' ),
			'manage_options',
			'angie-app', // Set the default page to the Angie App.
			$callback,
			$svg_icon,
			3
		);
	}


	public function register_admin_menu() {
		$has_consent = ConsentManager::has_consent();

		if ( ! $has_consent ) {
			// No consent: main menu shows welcome page
			$welcome_component = new Consent_Page();
			self::register_main_menu( [ $welcome_component, 'render_consent_page' ] );
		} else {
			// Has consent: main menu shows app page
			self::register_main_menu( [ $this, 'render_app_page' ] );

		// Add the Angie App as the first submenu item explicitly to ensure it's labeled correctly.
		add_submenu_page(
			'angie-app',
			esc_html__( 'Home', 'angie' ),
			esc_html__( 'Home', 'angie' ),
			'manage_options',
			'angie-app',
			[ $this, 'render_app_page' ],
			1 // Lower priority to ensure it appears first.
		);
		}
	}

	public function enqueue_scripts() {
		if ( ! current_user_can( 'use_angie' ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External Google Fonts resource, version not applicable
		wp_enqueue_style(
			'angie-google-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
			[],
			null
		);

		// Exclude Site Planner Connect page from loading Angie app script.
		$excluded_pages = [
			'e-site-planner-password-generator',
		];
		// PHPcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = Utils::get_sanitized_query_var( 'page' );
		if ( $current_page && in_array( $current_page, $excluded_pages, true ) ) {
			return;
		}

		// Check if user has given consent.
		if ( ! ConsentManager::has_consent() ) {
			return;
		}

		// Register and enqueue the external script.
		wp_enqueue_script(
			'angie-app',
			'https://editor-static-bucket.elementor.com/angie.umd.cjs',
			[ 'wp-api-request' ],
			ANGIE_VERSION,
			false
		);

		$plugins = apply_filters( 'angie_mcp_plugins', [] );

		// Is WooCommerce active?
		if ( Utils::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$plugins['woocommerce'] = [];

			// Only check for single product edit page in admin area where get_current_screen() is available.
			$is_single_product_edit_page = false;
			if ( is_admin() && function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				if ( $screen ) {
					$is_single_product_edit_page = 'post' === $screen->base && 'product' === $screen->post_type;
				}
			}

			$plugins['woocommerce']['isSingleProductEdit'] = $is_single_product_edit_page;
		}

		if ( Utils::is_plugin_active( 'elementor/elementor.php' ) ) {
			$plugins['elementor'] = [];
		}

		if ( Utils::is_plugin_active( 'angie-acf-mcp/angie-acf-mcp.php' ) ) {
			$plugins['angie-acf-mcp'] = [];
		}

		$installed_plugins = $this->get_installed_plugins_info();


		$post_types_names = array_keys( get_post_types( [
			'show_in_menu' => true,
			'show_in_rest' => true,
		] ) );

		// Get current user data
		$current_user = wp_get_current_user();
		$wp_username = $current_user->exists() ? $current_user->display_name : null;
		$wp_user_role = $current_user->exists() && !empty($current_user->roles) ? $current_user->roles[0] : null;


		wp_add_inline_script(
			'angie-app',
			'window.angieConfig = ' . wp_json_encode( [
				'plugins' => $plugins,
				'installedPlugins' => $installed_plugins,
				'postTypesNames' => $post_types_names,
				'version' => ANGIE_VERSION,
				'wpVersion' => get_bloginfo( 'version' ),
				'wpUsername' => $wp_username,
				'untrusted__wpUserRole' => $wp_user_role, // Used only for analytics - Never use for auth decisions
				'siteKey' => $this->get_site_key(),
				'codeSnippetsActive' => CodeSnippets::is_active(),
				'isElementorOneConnected' => $this->is_elementor_one_connected(),
			] ),
			'before'
		);
	}

	private function is_oauth_flow_active() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_oauth_return = isset( $_GET['oauth_code'] ) || isset( $_GET['oauth_state'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_oauth_starting = isset( $_GET['start-oauth'] );
		return [
			'is_starting' => $is_oauth_starting,
			'is_returning' => $is_oauth_return,
			'is_active' => $is_oauth_starting || $is_oauth_return,
		];
	}

	private function get_site_key() {
		$site_key = \get_option( static::OPTION_CONNECT_SITE_KEY );

		if ( ! $site_key ) {
			$site_key = md5( uniqid( \wp_generate_password() ) );
			\update_option( static::OPTION_CONNECT_SITE_KEY, $site_key );
		}

		return $site_key;
	}

	private function get_installed_plugins_info() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		$plugins_info = [];

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$is_active = in_array( $plugin_file, $active_plugins, true );
			$plugin_name = $plugin_data['Name'];

			$plugins_info[ $plugin_name ] = [
				'version' => $plugin_data['Version'],
				'status' => $is_active ? 'active' : 'inactive',
			];
		}

		return $plugins_info;
	}


	private function is_elementor_one_connected() {
		if ( ! class_exists( '\ElementorOne\Connect\Facade' ) ) {
			return false;
		}
		$facade = \ElementorOne\Connect\Facade::get( 'elementor-one' );
		return $facade && $facade->utils()->is_connected();
	}

	public function render_app_page() {
		$oauth_state = $this->is_oauth_flow_active();
		$is_oauth_starting = $oauth_state['is_starting'];
		$is_oauth_return = $oauth_state['is_returning'];
		$is_in_oauth_flow = $oauth_state['is_active'];

		?>
		<style>
			body {
				background-color: #FFFFFF;
			}
		</style>

		<?php if ( ConsentManager::has_consent() ) : ?>
			<div class="angie-app-page" data-testid="angie-app-page">
				<div class="angie-app-layout" data-testid="angie-app-layout">
						<div class="angie-app-start" id="angie-app-start" data-testid="angie-app-start">
						<h4>
							<?php esc_html_e( 'Meet Angie,', 'angie' ); ?><br>
							<?php esc_html_e( 'your new AI assistant', 'angie' ); ?>
						</h4>
					<p><?php esc_html_e( 'Build and manage WordPress sites effortlessly with Agentic AI.', 'angie' ); ?></p>
					<?php if ( $is_in_oauth_flow ) : ?>
							<div class="angie-loading-state" data-testid="angie-loading-state">
								<div class="angie-spinner"></div>
								<p class="angie-loading-text">
									<?php
									if ( $is_oauth_starting && ! $is_oauth_return ) {
										esc_html_e( 'Redirecting to sign in...', 'angie' );
									} else {
										esc_html_e( 'Completing authentication...', 'angie' );
									}
									?>
								</p>
							</div>
						<?php endif; ?>
					</div>

						<div class="angie-app-end" data-testid="angie-app-end">
						<div class="angie-end-container" data-testid="angie-end-container">
							<img src="<?php echo esc_url( Utils::get_asset_url( 'askAngieImage.png', __DIR__ ) ); ?>"
								alt="<?php esc_attr_e( 'Ask Angie AI Assistant', 'angie' ); ?>"
								class="angie-ask-image" data-testid="angie-ask-image" />
						</div>
					</div>
				</div>
			</div>

			<?php $this->render_app_styles(); ?>

		<script>
			(function() {
				// Listen for messages from iframe about authentication status
				window.addEventListener('message', function(event) {
					// Check if message is from iframe about user being already authenticated
					if (event.data && event.data.type === 'ANGIE_USER_ALREADY_AUTHENTICATED') {
						console.log('User already authenticated');
						// Open sidebar after a short delay
						setTimeout(() => {
							if (typeof window.toggleAngieSidebar === 'function') {
								window.toggleAngieSidebar(true);
							}
						}, 500);
					}
				});

					<?php if ( $is_in_oauth_flow ) : ?>
					const isStarting = <?php echo json_encode( $is_oauth_starting ); ?>;
					const isReturning = <?php echo json_encode( $is_oauth_return ); ?>;

					function ensureSidebarClosed() {
						if (typeof window.toggleAngieSidebar === 'function') {
							window.toggleAngieSidebar(false, true);
						}
					}

					function isOAuthComplete() {
						const urlParams = new URLSearchParams(window.location.search);
						return !urlParams.has('oauth_code') && !urlParams.has('oauth_state') && !urlParams.has('start-oauth');
					}

					function updateUIAfterAuth() {
						const appStart = document.getElementById('angie-app-start');
						if (appStart) {
							// Remove loading state if it exists
							const loadingState = appStart.querySelector('.angie-loading-state');
							if (loadingState) {
								loadingState.remove();
							}
						}
					}

					function openSidebarAfterAuth() {
						try {
							localStorage.setItem('angie_sidebar_state', 'open');
						} catch (e) {}
						if (typeof window.toggleAngieSidebar === 'function') {
							setTimeout(() => window.toggleAngieSidebar(true), 500);
						}
					}

					function monitorAuthCompletion() {
						let authenticationSuccessful = false;
						const checkInterval = setInterval(function() {
							const sidebar = document.getElementById('angie-sidebar-container');
							if (sidebar?.querySelector('iframe') && isOAuthComplete()) {
								updateUIAfterAuth();
								clearInterval(checkInterval);
								authenticationSuccessful = true;
								openSidebarAfterAuth();
								window.location.reload(); // Reload to update MCPs
							}
						}, 500);

						setTimeout(function() {
							clearInterval(checkInterval);
							if (!authenticationSuccessful) {
								console.log('OAuth authentication timed out');
								ensureSidebarClosed();
							}
						}, 30000);
					}

					ensureSidebarClosed();

					if (isStarting && !isReturning) {
						console.log('OAuth flow starting, waiting for redirect...');
					} else if (isReturning) {
						monitorAuthCompletion();
					}
					<?php endif; ?>
				})();
			</script>
		<?php else : ?>
			<div class="wrap">
				<div class="angie-consent-required">
					<span class="dashicons dashicons-lock"></span>
					<h2><?php esc_html_e( 'External Scripts Consent Required', 'angie' ); ?></h2>
					<p><?php esc_html_e( 'To use the Angie App, you need to approve loading external scripts.', 'angie' ); ?></p>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=angie-consent' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Get Started with Angie', 'angie' ); ?>
						</a>
					</p>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	private function render_app_styles() {
		wp_enqueue_style(
			'angie-app',
			Utils::get_asset_url( 'app-styles.css', __DIR__ ),
			[],
			ANGIE_VERSION
		);
	}

	public function add_angie_body_class( $classes ) {
		$classes[] = 'angie-default';
		return $classes;
	}
}
