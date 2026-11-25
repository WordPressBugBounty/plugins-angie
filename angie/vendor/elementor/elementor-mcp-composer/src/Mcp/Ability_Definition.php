<?php

namespace Elementor\MCP\Composer\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Value object holding the arguments an Abstract_Ability passes to wp_register_ability().
 */
class Ability_Definition {

	/**
	 * @var string
	 */
	public $label;

	/**
	 * @var string
	 */
	public $description;

	/**
	 * @var string
	 */
	public $category;

	/**
	 * @var array<string, mixed>
	 */
	public $output_schema;

	/**
	 * @var array<string, mixed>
	 */
	public $meta;

	/**
	 * @var callable
	 */
	public $permission_callback;

	/**
	 * @var array<string, mixed>
	 */
	public $input_schema;

	/**
	 * @param string               $label               Ability label.
	 * @param string               $description         Ability description.
	 * @param string               $category            Ability category slug.
	 * @param array<string, mixed> $output_schema       Output JSON schema.
	 * @param array<string, mixed> $meta                Ability meta.
	 * @param callable             $permission_callback Permission callback.
	 * @param array<string, mixed> $input_schema        Input JSON schema.
	 */
	public function __construct(
		string $label,
		string $description,
		string $category,
		array $output_schema,
		array $meta,
		callable $permission_callback,
		array $input_schema = []
	) {
		$this->label               = $label;
		$this->description         = $description;
		$this->category            = $category;
		$this->output_schema       = $output_schema;
		$this->meta                = $meta;
		$this->permission_callback = $permission_callback;
		$this->input_schema        = $input_schema;
	}

	/**
	 * Input schema for abilities that take no input.
	 *
	 * @return array<string, mixed>
	 */
	public static function empty_object_input_schema(): array {
		return [
			'type'    => 'object',
			'default' => (object) [],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'label'               => $this->label,
			'description'         => $this->description,
			'category'            => $this->category,
			'output_schema'       => $this->output_schema,
			'meta'                => $this->meta,
			'permission_callback' => $this->permission_callback,
			'input_schema'        => $this->normalized_input_schema(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function normalized_input_schema(): array {
		if ( empty( $this->input_schema ) ) {
			return self::empty_object_input_schema();
		}

		$schema = $this->input_schema;

		if (
			array_key_exists( 'properties', $schema )
			&& is_array( $schema['properties'] )
			&& empty( $schema['properties'] )
		) {
			unset( $schema['properties'] );
		}

		if ( ! isset( $schema['type'] ) ) {
			$schema['type'] = 'object';
		}

		return $schema;
	}
}
