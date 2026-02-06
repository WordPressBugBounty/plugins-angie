<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Files_Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_files_meta_box' ] );
		add_action( 'save_post_' . Module::CPT_NAME, [ __CLASS__, 'save_files_meta' ] );
	}

	public static function add_files_meta_box() {
		add_meta_box(
			'angie_snippet_files',
			esc_html__( 'Snippet Files', 'angie' ),
			[ __CLASS__, 'render_files_meta_box' ],
			Module::CPT_NAME,
			'normal',
			'default'
		);
	}

	public static function render_files_meta_box( $post ) {
		$meta_key = '_angie_snippet_files';
		$files    = get_post_meta( $post->ID, $meta_key, true );
		if ( ! is_array( $files ) ) {
			$files = [];
		}

		wp_nonce_field( 'angie_snippet_files_save', 'angie_snippet_files_nonce' );

		self::enqueue_files_meta_box_script();

		echo '<div id="angie-snippet-files">';
		echo '<p>' . esc_html__( 'Add one or more files to this snippet. Each file has a filename and its content.', 'angie' ) . '</p>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th style="width:25%">' . esc_html__( 'Filename', 'angie' ) . '</th>';
		echo '<th>' . esc_html__( 'Content', 'angie' ) . '</th>';
		echo '<th style="width:80px">' . esc_html__( 'Actions', 'angie' ) . '</th>';
		echo '</tr></thead><tbody id="angie-snippet-files-rows">';

		if ( empty( $files ) ) {
			$files[] = [ 'name' => 'main.php', 'content' => '' ];
		}

		foreach ( $files as $index => $file ) {
			$name = isset( $file['name'] ) ? (string) $file['name'] : '';

			if ( isset( $file['content_b64'] ) && is_string( $file['content_b64'] ) ) {
				$decoded = base64_decode( $file['content_b64'], true );
				$content = ( false === $decoded ) ? '' : $decoded;
			} else {
				$content = isset( $file['content'] ) ? html_entity_decode( (string) $file['content'], ENT_QUOTES, 'UTF-8' ) : '';
			}

			$is_main = ( 'main.php' === $name );
			$readonly_attr = $is_main ? ' readonly="readonly"' : '';
			$remove_disabled = $is_main ? ' disabled="disabled"' : '';

			echo '<tr class="angie-snippet-file-row">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $readonly_attr is a hardcoded HTML attribute string
			echo '<td><input type="text" name="angie_snippet_files[' . esc_attr( (string) $index ) . '][name]" value="' . esc_attr( $name ) . '" class="widefat angie-filename" placeholder="' . esc_attr__( 'e.g. main.php', 'angie' ) . '"' . $readonly_attr . ' /></td>';
			echo '<td><textarea name="angie_snippet_files[' . esc_attr( (string) $index ) . '][content]" class="widefat angie-file-content" rows="8" placeholder="' . esc_attr__( 'File content…', 'angie' ) . '">' . esc_textarea( $content ) . '</textarea></td>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $remove_disabled is a hardcoded HTML attribute string
			echo '<td><button type="button" class="button link-delete angie-remove-file"' . $remove_disabled . '>' . esc_html__( 'Remove', 'angie' ) . '</button></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p><button type="button" class="button button-secondary" id="angie-add-file">' . esc_html__( 'Add file', 'angie' ) . '</button></p>';
		echo '</div>';
	}

	private static function enqueue_files_meta_box_script() {
		$script_url = plugins_url( 'assets/js/files-meta-box.js', dirname( __FILE__ ) );
		wp_enqueue_script(
			'angie-files-meta-box',
			$script_url,
			[ 'code-editor' ],
			'1.0.0',
			true
		);

		wp_localize_script(
			'angie-files-meta-box',
			'angieFilesMetaBox',
			[
				'placeholders' => [
					'name'    => esc_attr__( 'e.g. main.php', 'angie' ),
					'content' => esc_attr__( 'File content…', 'angie' ),
				],
				'labels'       => [
					'remove' => esc_html__( 'Remove', 'angie' ),
				],
			]
		);
	}

	public static function save_files_meta( $post_id ) {
		if ( ! isset( $_POST['angie_snippet_files_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['angie_snippet_files_nonce'] ) ), 'angie_snippet_files_save' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		$meta_key = '_angie_snippet_files';

		if ( ! isset( $_POST['angie_snippet_files'] ) || ! is_array( $_POST['angie_snippet_files'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		$raw_files = wp_unslash( $_POST['angie_snippet_files'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$files     = [];

		foreach ( $raw_files as $file ) {
			$name = isset( $file['name'] ) ? sanitize_text_field( wp_unslash( $file['name'] ) ) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashing to remove WP magic quotes; base64 encoded immediately.
			$content_clean = isset( $file['content'] ) ? wp_unslash( $file['content'] ) : '';

			if ( '' === trim( $name ) && '' === trim( $content_clean ) ) {
				continue;
			}

			$files[] = [
				'name'        => $name,
				'content_b64' => base64_encode( $content_clean ),
			];
		}

		if ( empty( $files ) ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		$validation_result = Snippet_Validator::validate_snippet_files( $files );

		if ( is_wp_error( $validation_result ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_die() handles escaping internally
			wp_die( $validation_result->get_error_message() );
		}

		update_post_meta( $post_id, $meta_key, $files );

		File_System_Handler::write_snippet_files_to_disk( Dev_Mode_Manager::ENV_DEV, $post_id, $files );
	}
}
