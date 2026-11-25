<?php

namespace Elementor\MCP\Composer\Mcp;

use Elementor\MCP\Composer\Admin\McpSettingsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base class for Elementor MCP abilities (tools and resources) shared by host plugins.
 *
 * Host-specific behavior runs through these hooks:
 * - `elementor/mcp/ability/is_available` (filter): true or WP_Error.
 * - `elementor/mcp/pre_execute_guard` (filter): WP_Error blocks a page-mutating tool.
 * - `elementor/mcp/ability/before_execute` (action): runs right before execute().
 * - `elementor/mcp/ability/after_execute` (action): runs right after execute().
 */
abstract class Abstract_Ability {

	const KIND_TOOL         = 'tool';
	const KIND_RESOURCE     = 'resource';
	const ABILITY_ID_PREFIX = 'elementor/';
	const DRY_RUN_PARAM     = 'dry_run';

	/**
	 * @var Ability_Definition|null
	 */
	private $cached_definition = null;

	/**
	 * @return string
	 */
	abstract protected function get_ability_id(): string;

	/**
	 * @return Ability_Definition
	 */
	abstract protected function get_definition(): Ability_Definition;

	/**
	 * @param mixed $input Ability input.
	 * @return mixed
	 */
	abstract public function execute( $input = [] );

	/**
	 * Execute callback registered with the Abilities API.
	 *
	 * @param mixed $input Ability input.
	 * @return mixed
	 */
	final public function execute_guarded( $input = [] ) {
		if ( ! is_array( $input ) ) {
			$input = is_object( $input ) ? (array) $input : [];
		}

		$availability = $this->is_available();
		if ( is_wp_error( $availability ) ) {
			return $availability;
		}

		// The is_available filter can return false or another non-true value; treat anything but true as unavailable.
		if ( true !== $availability ) {
			return new \WP_Error( 'ability_unavailable', __( 'This ability is not available.', 'elementor' ) );
		}

		$is_dry_run = self::is_dry_run( $input );
		if ( $is_dry_run && ! $this->supports_dry_run() ) {
			return new \WP_Error(
				'dry_run_unsupported',
				__( 'This ability does not support dry run.', 'elementor' ),
				[ 'status' => \WP_Http::BAD_REQUEST ]
			);
		}

		$is_mutating = self::KIND_TOOL === $this->get_kind() && $this->is_page_mutating();

		if ( $is_mutating ) {
			$guard = apply_filters( 'elementor/mcp/pre_execute_guard', null, $input );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		do_action( 'elementor/mcp/ability/before_execute', $this, $input );

		$result = $this->execute( $input );

		$is_failed = is_wp_error( $result ) || ( is_array( $result ) && 'error' === ( $result['status'] ?? '' ) );

		do_action(
			'elementor/mcp/ability/after_execute',
			$this,
			$input,
			$result,
			[
				'is_mutating'       => $is_mutating,
				'is_failed'         => $is_failed,
				'is_dry_run'        => $is_dry_run,
				'post_id'           => isset( $input['post_id'] ) ? (int) $input['post_id'] : 0,
				'record_capability' => $this->should_record_site_capability( $input, $result ),
			]
		);

		return $result;
	}

	/**
	 * Validates input against the ability's input schema, the same way the WordPress Abilities API does.
	 *
	 * @param mixed $input Ability input.
	 * @return true|\WP_Error
	 */
	public function validate_input_against_schema( $input ) {
		return rest_validate_value_from_schema( $input, $this->definition()->to_array()['input_schema'], 'input' );
	}

	/**
	 * @return bool
	 */
	public function check_permission(): bool {
		return (bool) call_user_func( $this->definition()->permission_callback );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		$definition                     = $this->definition()->to_array();
		$definition['execute_callback'] = [ $this, 'execute_guarded' ];
		$definition['description']      = $this->get_description_for_llm();

		$meta          = is_array( $definition['meta'] ?? null ) ? $definition['meta'] : [];
		$mcp           = is_array( $meta['mcp'] ?? null ) ? $meta['mcp'] : [];
		$mcp['public'] = McpSettingsController::is_enabled();
		if ( isset( $mcp['description'] ) ) {
			$mcp['description'] = $this->maybe_append_unavailable_notice( (string) $mcp['description'] );
		}
		$meta['mcp']          = $mcp;
		$meta['show_in_rest'] = true;
		$definition['meta']   = $meta;

		wp_register_ability( $this->get_id(), $definition );
	}

	/**
	 * @return string
	 */
	public function get_id(): string {
		return $this->get_ability_id();
	}

	/**
	 * @return string
	 */
	public function get_kind(): string {
		return $this->is_resource() ? self::KIND_RESOURCE : self::KIND_TOOL;
	}

	/**
	 * @return string|null
	 */
	public function get_uri(): ?string {
		return $this->mcp_meta()['uri'] ?? null;
	}

	/**
	 * @return string|null
	 */
	public function get_mime_type(): ?string {
		return $this->mcp_meta()['mimeType'] ?? null;
	}

	/**
	 * @return string|null
	 */
	public function get_resource_description(): ?string {
		$base = $this->mcp_meta()['description'] ?? $this->definition()->description;

		return $this->maybe_append_unavailable_notice( (string) $base );
	}

	/**
	 * @return string
	 */
	public function get_description_for_llm(): string {
		return $this->maybe_append_unavailable_notice( (string) $this->definition()->description );
	}

	/**
	 * @return string
	 */
	public function get_display_name(): string {
		return (string) ( $this->mcp_meta()['name'] ?? $this->definition()->label );
	}

	/**
	 * @return string
	 */
	public function get_proxy_slug(): string {
		if ( self::KIND_RESOURCE === $this->get_kind() ) {
			return (string) $this->get_uri();
		}

		return substr( $this->get_id(), strlen( static::ABILITY_ID_PREFIX ) );
	}

	/**
	 * Whether this ability may currently run.
	 *
	 * Subclasses may override to add reasons (missing plugin, licence tier,
	 * post-type support, ...). Return `true` when available, or a `\WP_Error`
	 * explaining why not. Include a `description_notice` entry in the error
	 * data to add a short hint to this ability's description.
	 *
	 * @return true|\WP_Error
	 */
	public function is_available() {
		return apply_filters( 'elementor/mcp/ability/is_available', true, $this->get_id(), $this );
	}

	/**
	 * @return bool
	 */
	public function is_exposed_via_proxy(): bool {
		return true;
	}

	/**
	 * @return bool
	 */
	public function is_exposed_on_server(): bool {
		return true;
	}

	/**
	 * Whether the ability accepts `dry_run: true`. A dry run validates the input and returns
	 * the exact result a real run would produce, without writing anything, so the editor can apply it locally.
	 *
	 * @return bool
	 */
	public function supports_dry_run(): bool {
		return false;
	}

	/**
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public static function is_dry_run( $input ): bool {
		return is_array( $input ) && ! empty( $input[ self::DRY_RUN_PARAM ] );
	}

	/**
	 * Whether a successful run should be recorded as a site capability.
	 *
	 * Passed to `elementor/mcp/ability/after_execute` as `record_capability`;
	 * the host decides which capability, if any, the ability maps to.
	 *
	 * @param mixed $input  Ability input.
	 * @param mixed $result Ability result.
	 * @return bool
	 */
	protected function should_record_site_capability( $input, $result ): bool {
		if ( self::is_dry_run( $input ) ) {
			return false;
		}

		if ( is_wp_error( $result ) ) {
			return false;
		}

		if ( is_array( $result ) && 'error' === ( $result['status'] ?? '' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @return Ability_Definition
	 */
	protected function definition(): Ability_Definition {
		if ( null === $this->cached_definition ) {
			$this->cached_definition = $this->get_definition();

			if ( $this->supports_dry_run() ) {
				$this->add_dry_run_input( $this->cached_definition );
			}
		}

		return $this->cached_definition;
	}

	/**
	 * @param Ability_Definition $definition Definition to extend.
	 * @return void
	 */
	private function add_dry_run_input( Ability_Definition $definition ): void {
		if ( isset( $definition->input_schema['properties'][ self::DRY_RUN_PARAM ] ) ) {
			return;
		}

		$definition->input_schema['type'] = $definition->input_schema['type'] ?? 'object';
		$definition->input_schema['properties'][ self::DRY_RUN_PARAM ] = [
			'type'        => 'boolean',
			'default'     => false,
			'description' => 'Validate and return the planned result without writing anything.',
		];
	}

	/**
	 * @return bool
	 */
	private function is_resource(): bool {
		return self::KIND_RESOURCE === ( $this->mcp_meta()['type'] ?? null );
	}

	/**
	 * @return bool
	 */
	private function is_page_mutating(): bool {
		return true === ( $this->definition()->meta['mutates_page'] ?? false );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mcp_meta(): array {
		$meta = $this->definition()->meta;

		return is_array( $meta['mcp'] ?? null ) ? $meta['mcp'] : [];
	}

	/**
	 * @param string $description Description to extend.
	 * @return string
	 */
	private function maybe_append_unavailable_notice( string $description ): string {
		$availability = $this->is_available();
		if ( ! is_wp_error( $availability ) ) {
			return $description;
		}

		$data   = $availability->get_error_data();
		$notice = is_array( $data ) && isset( $data['description_notice'] )
			? (string) $data['description_notice']
			: $availability->get_error_message();

		if ( '' === $notice ) {
			return $description;
		}

		return '' === $description ? $notice : rtrim( $description ) . ' ' . $notice;
	}
}
