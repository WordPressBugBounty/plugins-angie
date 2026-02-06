<?php
namespace Angie\Modules\CodeSnippets\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fatal_Error_Handler {

	const FATAL_ERROR_TYPES = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ];

	public static function init() {
		if ( ! self::should_handle_errors() ) {
			return;
		}

		ob_start();
		register_shutdown_function( [ __CLASS__, 'handle_shutdown' ] );
	}

	private static function should_handle_errors(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return Dev_Mode_Manager::is_dev_mode_enabled();
	}

	public static function handle_shutdown(): void {
		$error = error_get_last();

		if ( ! $error || ! in_array( $error['type'], self::FATAL_ERROR_TYPES, true ) ) {
			return;
		}

		if ( ! Dev_Mode_Manager::is_dev_mode_enabled() ) {
			return;
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		self::render_fallback_notice();
	}

	private static function render_fallback_notice(): void {
		$exit_url = self::get_exit_test_mode_url();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html__( 'Error in Test Mode', 'angie' ); ?></title>
	<style>
		.angie-fatal-notice {
			background: #fff;
			border-left: 4px solid #ED01EE;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			max-width: 600px;
			padding: 14px 24px;
			border-radius: 4px;
			display: block;
		}
		.angie-fatal-notice h2 {
			display: block;
			font-size: 18px;
			font-weight: 600;
			color: #1d2327;
			margin-bottom: 12px;
		}
		.angie-fatal-notice p {
			display: block;
			font-size: 14px;
			line-height: 1.6;
			color: #50575e;
			margin-bottom: 20px;
		}
		.angie-fatal-buttons {
			display: block;
		}
		.angie-btn {
			display: inline-block;
			padding: 10px 20px;
			font-size: 14px;
			font-weight: 500;
			text-decoration: none;
			border-radius: 4px;
			cursor: pointer;
			text-align: center;
			background: #f0f0f1;
			color: #1d2327;
			border: 1px solid #c3c4c7;
		}
	</style>
</head>
<body>
	<div class="angie-fatal-notice">
		<h2><?php echo esc_html__( 'Something went wrong', 'angie' ); ?></h2>
		<p><?php echo esc_html__( "Don't worry, the website's visitors won't see this because you are previewing unpublished changes in test mode.", 'angie' ); ?></p>
		<div class="angie-fatal-buttons">
			<a href="<?php echo esc_url( $exit_url ); ?>" class="angie-btn">
				<?php echo esc_html__( 'Exit test mode', 'angie' ); ?>
			</a>
		</div>
	</div>
</body>
</html>
		<?php
		exit;
	}

	private static function get_exit_test_mode_url(): string {
		$current_url = home_url( add_query_arg( null, null ) );
		return add_query_arg( 'angie-exit-test-mode', '1', $current_url );
	}
}
