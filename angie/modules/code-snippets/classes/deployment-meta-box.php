<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deployment_Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_deployment_meta_box' ] );
		add_action( 'save_post_' . Module::CPT_NAME, [ __CLASS__, 'save_deployment_meta' ], 5 );
		add_action( 'admin_post_angie_delete_environment', [ __CLASS__, 'handle_delete_environment' ] );
	}

	public static function add_deployment_meta_box() {
		add_meta_box(
			'angie_snippet_deployment',
			esc_html__( 'Environment & Deployment', 'angie' ),
			[ __CLASS__, 'render_deployment_meta_box' ],
			Module::CPT_NAME,
			'side',
			'default'
		);
	}

	public static function render_deployment_meta_box( $post ) {
		wp_nonce_field( 'angie_snippet_deployment_save', 'angie_snippet_deployment_nonce' );

		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post->ID );
		$dev_time = $timestamps['dev'];
		$prod_time = $timestamps['prod'];
		$sync_status = $timestamps['status'];
		$delete_url_base = admin_url( 'admin-post.php' );

		echo '<div style="padding: 10px 0;">';
		echo '<p><strong>' . esc_html__( 'Current Live Version:', 'angie' ) . '</strong><br>';
		if ( $prod_time > 0 ) {
			echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $prod_time ) );
			$delete_prod_url = wp_nonce_url(
				add_query_arg(
					[
						'action'      => 'angie_delete_environment',
						'post_id'     => $post->ID,
						'environment' => Dev_Mode_Manager::ENV_PROD,
					],
					$delete_url_base
				),
				'angie_delete_environment_' . $post->ID
			);
			echo '<br><a href="' . esc_url( $delete_prod_url ) . '" class="button-link-delete" onclick="return confirm(\'' . esc_js( esc_html__( 'Are you sure you want to delete the Live environment?', 'angie' ) ) . '\');">' . esc_html__( 'Delete Live', 'angie' ) . '</a>';
		} else {
			echo '<em>' . esc_html__( 'Not deployed yet', 'angie' ) . '</em>';
		}
		echo '</p>';

		echo '<p><strong>' . esc_html__( 'Current Work Version:', 'angie' ) . '</strong><br>';
		if ( $dev_time > 0 ) {
			echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dev_time ) );
			$delete_dev_url = wp_nonce_url(
				add_query_arg(
					[
						'action'      => 'angie_delete_environment',
						'post_id'     => $post->ID,
						'environment' => Dev_Mode_Manager::ENV_DEV,
					],
					$delete_url_base
				),
				'angie_delete_environment_' . $post->ID
			);
			echo '<br><a href="' . esc_url( $delete_dev_url ) . '" class="button-link-delete" onclick="return confirm(\'' . esc_js( esc_html__( 'Are you sure you want to delete the Work environment?', 'angie' ) ) . '\');">' . esc_html__( 'Delete Work', 'angie' ) . '</a>';
		} else {
			echo '<em>' . esc_html__( 'Not saved yet', 'angie' ) . '</em>';
		}
		echo '</p>';

		echo '<p><strong>' . esc_html__( 'Sync Status:', 'angie' ) . '</strong><br>';
		if ( Dev_Mode_Manager::SYNC_STATUS_NOT_DEPLOYED === $sync_status ) {
		   echo '<span style="color: #999;">' . esc_html__( 'Not Deployed', 'angie' ) . '</span>';
		} elseif ( Dev_Mode_Manager::SYNC_STATUS_CHANGES_PENDING === $sync_status ) {
		   echo '<span style="color: #d63638;">' . esc_html__( 'Test & Live not synced', 'angie' ) . '</span>';
		} elseif ( Dev_Mode_Manager::SYNC_STATUS_TEST_ONLY === $sync_status ) {
		   echo '<span style="color: #d63638;">' . esc_html__( 'Test Environment only', 'angie' ) . '</span>';
		} else {
		   echo '<span style="color: #00a32a;">' . esc_html__( 'Live & Synced', 'angie' ) . '</span>';
		}
		echo '</p>';

		echo '<p>';
		$button_text = ( $dev_time > 0 ) ? esc_html__( 'Push to Production', 'angie' ) : esc_html__( 'Publish to Dev', 'angie' );
		submit_button( $button_text, 'primary', 'angie_push_to_production', false );
		echo '</p>';
		echo '</div>';
	}

	public static function save_deployment_meta( $post_id ) {
		if ( ! isset( $_POST['angie_snippet_deployment_nonce'] ) ) {
			return;
		}

		if ( ! isset( $_POST['angie_push_to_production'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['angie_snippet_deployment_nonce'] ) ), 'angie_snippet_deployment_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}


		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post_id );
		$dev_time = $timestamps['dev'];

		if ( $dev_time > 0 ) {
			Dev_Mode_Manager::push_snippet_to_production( $post_id );
		} else {
			Dev_Mode_Manager::push_snippet_to_dev( $post_id );
		}
	}

	public static function handle_delete_environment() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$environment = isset( $_GET['environment'] ) ? sanitize_text_field( wp_unslash( $_GET['environment'] ) ) : '';

		if ( ! $post_id || ! $environment ) {
			wp_die( esc_html__( 'Invalid request.', 'angie' ) );
		}

		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'angie_delete_environment_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'angie' ) );
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'angie' ) );
		}

		$valid_environments = [ Dev_Mode_Manager::ENV_DEV, Dev_Mode_Manager::ENV_PROD ];
		if ( ! in_array( $environment, $valid_environments, true ) ) {
			wp_die( esc_html__( 'Invalid environment.', 'angie' ) );
		}

		File_System_Handler::delete_snippet_files( $post_id, [ $environment ] );

		$redirect_url = get_edit_post_link( $post_id, 'raw' );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}
