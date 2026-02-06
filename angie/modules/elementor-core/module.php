<?php

namespace Angie\Modules\ElementorCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Angie\Classes\Module_Base;
use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Plugin;
use Angie\Modules\ElementorCore\Components\Kit_Provider;
use Angie\Includes\Utils;
/**
 * Module `Elementor Editor`
 *
 * A module is responsible over a specific part of the app logic,
 * Typically it is constructed by a main Module class (the class in this file) and components (e.g. `A_Component)
 * depending on its role, it may have additional parts such as `database` or `rest` etc'
 *
 * Please describe the role of your module.
 */
class Module extends Module_Base {

	/**
	 * Kit Provider controller
	 *
	 * @var \Angie\Modules\ElementorCore\Components\Kit_Provider
	 */
	public $kit_provider;

	public function get_name(): string {
		return 'elementor-core';
	}

	public static function is_active(): bool {
		return ConsentManager::has_consent() && Utils::is_plugin_active( 'elementor/elementor.php' );
	}

	protected function __construct() {
		$this->init_rest_controllers();
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'angie_mcp_plugins', function ( $plugins ) {
			$plugins['elementor'] = [];
			return $plugins;
		} );
	}

	/**
	 * Initialize controllers
	 */
	private function init_rest_controllers() {
		$this->kit_provider = new Kit_Provider();
	}

	public function enqueue_scripts() {
		/**
		 * @var \Angie\Modules\AngieApp\Module
		 */
		$app_module = Plugin::instance()->modules_manager->get_modules( 'AngieApp' );

		if ( ! $app_module ) {
			return;
		}

		/**
		 * @var \Angie\Modules\AngieApp\Components\Angie_App
		 */
		$app_component = $app_module->get_component( 'Angie_App' );

		if ( ! $app_component ) {
			return;
		}

		$app_component->enqueue_scripts();
	}
}
