<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets_Manager {

	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_code_editor_assets' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_dev_mode_assets' ] );
	}

	public static function enqueue_dev_mode_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . Module::CPT_NAME !== $screen->id ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		$script_url = plugins_url( 'assets/js/dev-mode.js', dirname( __FILE__ ) );
		wp_enqueue_script(
			'angie-dev-mode',
			$script_url,
			[ 'jquery' ],
			'1.0.0',
			true
		);

		wp_localize_script(
			'angie-dev-mode',
			'angieDevMode',
			[
				'restUrl' => esc_url( rest_url( 'angie/v1/dev-mode' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	public static function enqueue_code_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( 'post' !== $screen->base && 'post-new' !== $screen->base ) ) {
			return;
		}

		$post_type = '';
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id   = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = get_post_type( $post_id );
		} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $screen->post_type ) ) {
			$post_type = (string) $screen->post_type;
		}

		if ( Module::CPT_NAME !== $post_type ) {
			return;
		}

		$settings = wp_enqueue_code_editor( [ 'type' => 'text/plain' ] );
		if ( false === $settings ) {
			return;
		}

		wp_enqueue_script( 'code-editor' );
		wp_enqueue_style( 'code-editor' );

		$settings_js = wp_json_encode( $settings );
		wp_add_inline_script( 'code-editor', 'window.AngieCodeEditorSettings = ' . $settings_js . ';', 'after' );
	}
}
