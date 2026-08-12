<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Consumer_Settings {

	public function __construct() {
		add_action( 'angie_settings_page_cards', [ $this, 'render_settings_card' ] );
	}

	public static function is_available(): bool {
		return Wp_Abilities_Support::is_mcp_adapter_supported()
			&& function_exists( 'wp_is_application_passwords_available' )
			&& wp_is_application_passwords_available()
			&& current_user_can( 'use_angie' )
			&& current_user_can( 'manage_options' )
			&& current_user_can( 'create_app_password', get_current_user_id() );
	}

	public function render_settings_card(): void {
		if ( ! self::is_available() ) {
			return;
		}

		?>
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( 'External MCP Consumers', 'angie' ); ?></h2>

			<p>
				<?php esc_html_e( 'Connect Claude Desktop, Cursor, or other MCP clients to this site’s Angie server. Use Generate and Copy to create an application password and copy the config snippet into your client settings file.', 'angie' ); ?>
			</p>

			<ol style="margin-left: 18px;">
				<li><?php esc_html_e( 'Click Generate and Copy on the config snippet below.', 'angie' ); ?></li>
				<li><?php esc_html_e( 'Paste the MCP config snippet (or click the text box to select all).', 'angie' ); ?></li>
				<li>
					<?php esc_html_e( 'Paste into', 'angie' ); ?>
					<code
						id="angie-mcp-cursor-path-link"
						class="angie-mcp-config-path"
						role="button"
						tabindex="0"
					></code>
					<?php esc_html_e( '(Cursor) or', 'angie' ); ?>
					<code
						id="angie-mcp-claude-path-link"
						class="angie-mcp-config-path"
						role="button"
						tabindex="0"
					></code>
					<?php esc_html_e( '(Claude Desktop). Merge with any existing mcpServers entries.', 'angie' ); ?>
				</li>
				<li><?php esc_html_e( 'Restart the MCP client so it picks up the new server.', 'angie' ); ?></li>
			</ol>

			<div id="angie-mcp-path-copy-status" class="angie-mcp-path-copy-notice" hidden></div>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="angie-mcp-consumer-config">
							<?php esc_html_e( 'MCP config snippet', 'angie' ); ?>
						</label>
					</th>
					<td>
						<p>
							<label for="angie-mcp-include-wrapper">
								<input
									type="checkbox"
									id="angie-mcp-include-wrapper"
									checked
								/>
								<?php esc_html_e( 'Include mcpServers wrapper', 'angie' ); ?>
							</label>
							<button
								type="button"
								class="button button-primary"
								id="angie-mcp-copy-config"
								style="margin-left: 10px; vertical-align: middle;"
							>
								<?php esc_html_e( 'Generate and Copy', 'angie' ); ?>
							</button>
						</p>
					</td>
				</tr>
				<tr class="angie-mcp-config-snippet-field">
					<td colspan="2">
						<textarea
							id="angie-mcp-consumer-config"
							class="large-text code"
							rows="20"
							readonly
							onfocus="this.select();"
						></textarea>
						<p class="description">
							<?php esc_html_e( 'Uses @automattic/mcp-wordpress-remote via npx (requires Node.js 22+). Works in Claude Desktop and Cursor.', 'angie' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Generate and Copy creates the app password once and copies the config. Further clicks copy the same config. Click inside the box to select all.', 'angie' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p id="angie-mcp-consumer-status" hidden></p>

			<p
				id="angie-mcp-generated-password"
				class="angie-mcp-generated-password-notice"
				hidden
			></p>
		</div>
		<?php
		$this->render_assets();
	}

	private function render_assets(): void {
		$assets_dir = dirname( __DIR__ ) . '/assets';
		?>
		<style>
			<?php require $assets_dir . '/mcp-consumer-settings.css'; ?>
		</style>
		<script>
			window.angieMcpConsumerSettings = <?php echo wp_json_encode( $this->get_script_settings() ); ?>;
			<?php require $assets_dir . '/mcp-consumer-settings.js'; ?>
		</script>
		<?php
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_script_settings(): array {
		$user = wp_get_current_user();

		return [
			'restUrl'               => rest_url( 'wp/v2/users/me/application-passwords' ),
			'username'              => $user->user_login,
			'appPasswordNamePrefix' => esc_html__( 'Angie as MCP', 'angie' ),
			'nonce'                 => wp_create_nonce( 'wp_rest' ),
			'indexPhpUrl'           => home_url( '/index.php' ),
			'generatingLabel'   => esc_html__( 'Generating…', 'angie' ),
			'errorLabel'        => esc_html__( 'Failed to generate application password.', 'angie' ),
			'passwordLabel'     => esc_html__( 'Application password (copy now — shown once):', 'angie' ),
			'pathCopiedMac'     => esc_html__( 'Path copied. In Finder press Cmd+Shift+G, paste, and press Enter.', 'angie' ),
			'pathCopiedWin'     => esc_html__( 'Path copied. Paste into the Explorer address bar and press Enter.', 'angie' ),
			'pathCopiedLinux'   => esc_html__( 'Path copied. Paste the path into your file manager.', 'angie' ),
			'pathCopyFailed'    => esc_html__( 'Could not copy the path automatically. Select and copy it manually.', 'angie' ),
			'copyLabel'         => esc_html__( 'Generate and Copy', 'angie' ),
			'copyingLabel'      => esc_html__( 'Copying…', 'angie' ),
			'copiedLabel'       => esc_html__( 'Copied!', 'angie' ),
			'configCopied'      => esc_html__( 'Config copied to clipboard. Application password was generated for this snippet.', 'angie' ),
			'configCopiedAgain' => esc_html__( 'Config copied to clipboard.', 'angie' ),
			'successMessage'    => esc_html__( 'Application password generated. Copy the config below — the password is shown only once.', 'angie' ),
		];
	}
}
