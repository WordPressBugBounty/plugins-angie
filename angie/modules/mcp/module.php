<?php

namespace Angie\Modules\Mcp;

use Angie\Classes\Module_Base;
use Angie\Modules\WpAbilities\Classes\Mcp_Adapter_Ability_Discovery;
use Angie\Modules\WpAbilities\Classes\Mcp_Adapter_Ability_Registration;
use Angie\Modules\WpAbilities\Classes\Wp_Abilities_Support;
use Elementor\MCP\Composer\Admin\Page;
use Elementor\MCP\Composer\Mcp\Registry as Shared_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module extends Module_Base {

	public function get_name(): string {
		return 'mcp';
	}

	public static function is_active(): bool {
		return class_exists( Page::class ) && class_exists( Shared_Registry::class );
	}

	public function __construct() {
		add_action( 'init', [ $this, 'bootstrap_shared_registry' ], 20 );
		add_action( 'wp_abilities_api_init', [ $this, 'register_shared_registry_slugs' ], 110 );

		if ( is_admin() ) {
			$this->register_components( [
				'Mcp_Page',
			] );
		}
	}

	public function bootstrap_shared_registry(): void {
		if ( function_exists( 'wp_get_abilities' ) ) {
			wp_get_abilities();
		}

		$this->register_shared_registry_slugs();
	}

	public function register_shared_registry_slugs(): void {
		$shared = Shared_Registry::instance();
		$shared->register_tools( $this->get_shared_tool_slugs() );
		$shared->register_resources( Mcp_Adapter_Ability_Discovery::get_mcp_resource_ability_names() );
	}

	/**
	 * @return string[]
	 */
	private function get_shared_tool_slugs(): array {
		$tools = [];

		if ( function_exists( 'wp_get_abilities' ) ) {
			foreach ( wp_get_abilities() as $ability ) {
				if ( Mcp_Adapter_Ability_Discovery::is_discoverable_mcp_tool( $ability ) ) {
					$tools[] = $ability->get_name();
				}
			}
		}

		foreach ( Mcp_Adapter_Ability_Registration::get_adapter_ability_names() as $name ) {
			if ( null !== Wp_Abilities_Support::get_registered_ability( $name ) ) {
				$tools[] = $name;
			}
		}

		return array_values( array_unique( $tools ) );
	}
}
