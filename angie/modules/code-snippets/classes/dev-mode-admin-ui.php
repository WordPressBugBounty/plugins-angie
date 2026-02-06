<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dev_Mode_Admin_UI {

	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'render_dev_mode_notice' ] );
		add_action( 'admin_footer', [ __CLASS__, 'render_dev_mode_exit_button' ] );

		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_dev_mode_assets' ] );
		add_action( 'elementor/editor/footer', [ __CLASS__, 'render_elementor_dev_mode_exit_button' ] );
	}

	public static function render_dev_mode_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . Module::CPT_NAME !== $screen->id ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		$is_dev_mode = Dev_Mode_Manager::is_dev_mode_enabled();

		if ( $is_dev_mode ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p>';
			echo '<strong>' . esc_html__( 'Dev Mode is Active', 'angie' ) . '</strong> - ' . esc_html__( 'Snippets are loading from the development environment.', 'angie' ) . ' ';
			echo '<button type="button" class="button button-secondary" id="angie-disable-dev-mode">' . esc_html__( 'Disable Dev Mode', 'angie' ) . '</button>';
			echo '</p>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-info">';
			echo '<p>';
			echo esc_html__( 'Snippets are loading from production.', 'angie' ) . ' ';
			echo '<button type="button" class="button button-primary" id="angie-enable-dev-mode">' . esc_html__( 'Enable Dev Mode', 'angie' ) . '</button>';
			echo '</p>';
			echo '</div>';
		}
	}

	public static function render_dev_mode_exit_button() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		if ( ! Dev_Mode_Manager::is_dev_mode_enabled() ) {
			return;
		}

		self::enqueue_dev_mode_border_assets();
		self::render_angie_dev_mode_exit_button_html();
	}

	private static function enqueue_dev_mode_border_assets() {
		wp_enqueue_style(
			'angie-dev-mode-border',
			plugins_url( 'assets/css/dev-mode-border.css', dirname( __FILE__ ) ),
			[],
			ANGIE_VERSION
		);

		wp_enqueue_script(
			'angie-dev-mode-exit-button',
			plugins_url( 'assets/js/dev-mode-exit-button.js', dirname( __FILE__ ) ),
			[],
			ANGIE_VERSION,
			true
		);

		wp_localize_script(
			'angie-dev-mode-exit-button',
			'angieDevModeExit',
			[
				'restUrl' => esc_url( rest_url( 'angie/v1/dev-mode' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'buttonText' => esc_html__( 'Test mode', 'angie' ),
				'exitingText' => esc_html__( 'Exiting...', 'angie' ),
				'errorText' => esc_html__( 'An error occurred.', 'angie' ),
				'requestFailedText' => esc_html__( 'Request failed.', 'angie' ),
			]
		);
	}

	private static function render_angie_dev_mode_exit_button_html() {
		?>
		<div id="angie-dev-mode-border-overlay"></div>
		<div id="angie-dev-mode-button-wrapper">
			<div id="angie-dev-mode-tooltip" class="angie-tooltip-hidden">
				<p class="angie-tooltip-title"><?php echo esc_html__( 'Test mode lets you try things safely.', 'angie' ); ?></p>
				<p><?php echo esc_html__( 'Changes you make here are saved as drafts and won\'t affect your live site until you publish them.', 'angie' ); ?></p>
				<p><?php echo esc_html__( 'You can create, edit, and preview widgets freely, then publish when you\'re ready.', 'angie' ); ?></p>
				<div class="angie-tooltip-arrow"></div>
			</div>
			<button type="button" id="angie-dev-mode-exit-button">
				<span class="angie-exit-icon">✕</span>
				<span><?php echo esc_html__( 'Test mode', 'angie' ); ?></span>
				<span class="angie-info-icon">i</span>
			</button>
		</div>
		<?php
	}

	public static function enqueue_elementor_dev_mode_assets() {
		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		if ( ! Dev_Mode_Manager::is_dev_mode_enabled() ) {
			return;
		}

		self::enqueue_dev_mode_border_assets();
	}

	public static function render_elementor_dev_mode_exit_button() {
		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		if ( ! Dev_Mode_Manager::is_dev_mode_enabled() ) {
			return;
		}

		self::render_angie_dev_mode_exit_button_html();
	}
}
