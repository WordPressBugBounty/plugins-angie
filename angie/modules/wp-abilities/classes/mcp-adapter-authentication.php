<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Adapter_Authentication {

	public static function check(): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'authentication_required', 'User must be authenticated.' );
		}

		if ( ! current_user_can( 'use_angie' ) ) {
			return new \WP_Error( 'insufficient_capability', 'User lacks required capability: use_angie' );
		}

		return true;
	}

	public static function check_transport_permission( $_request = null ): bool|\WP_Error {
		return self::check();
	}
}
