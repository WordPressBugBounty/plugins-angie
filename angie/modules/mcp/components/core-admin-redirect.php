<?php

namespace Angie\Modules\Mcp\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Core_Admin_Redirect {

	private const FALLBACK_PAGE_SLUG = 'elementor-mcp';

	private const FALLBACK_CAPABILITY = 'manage_options';

	public static function get_page_slug(): string {
		if ( class_exists( '\Elementor\MCP\Composer\Admin\Page' ) ) {
			return \Elementor\MCP\Composer\Admin\Page::SLUG;
		}

		return self::FALLBACK_PAGE_SLUG;
	}

	public static function get_required_capability(): string {
		if ( class_exists( '\Elementor\MCP\Composer\Admin\Page' ) ) {
			return \Elementor\MCP\Composer\Admin\Page::instance()->get_capability();
		}

		return self::FALLBACK_CAPABILITY;
	}

	public static function should_redirect(): bool {
		if ( ! class_exists( '\Elementor\Modules\Mcp\Module' ) ) {
			return false;
		}

		if ( ! current_user_can( self::get_required_capability() ) ) {
			return false;
		}

		return true;
	}

	public static function get_admin_url(): string {
		return admin_url( 'admin.php?page=' . self::get_page_slug() );
	}

	public static function maybe_redirect(): void {
		if ( ! self::should_redirect() ) {
			return;
		}

		wp_safe_redirect( self::get_admin_url() );
		exit;
	}
}
