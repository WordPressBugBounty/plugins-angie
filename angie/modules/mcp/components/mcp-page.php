<?php

namespace Angie\Modules\Mcp\Components;

use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Modules\Mcp\Module as Mcp_Module;
use Elementor\MCP\Composer\Admin\Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Page {

	const SLUG = 'angie-mcp';

	private Page $page;

	public function __construct() {
		$this->page = Page::instance();
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 10 );
	}

	public function register_admin_menu(): void {
		if ( ! ConsentManager::has_consent() ) {
			return;
		}

		$hook_suffix = add_submenu_page(
			'angie-app',
			esc_html__( 'MCP', 'angie' ),
			esc_html__( 'MCP', 'angie' ),
			$this->page->get_capability(),
			self::SLUG,
			[ $this, 'render' ],
			2
		);

		if ( is_string( $hook_suffix ) ) {
			add_action( "load-{$hook_suffix}", [ $this, 'maybe_redirect_to_core_mcp_admin' ] );
		}
	}

	public function maybe_redirect_to_core_mcp_admin(): void {
		Core_Admin_Redirect::maybe_redirect();
	}

	public function render(): void {
		Mcp_Module::instance()->register_shared_registry_slugs();
		$this->page->render();
	}
}
