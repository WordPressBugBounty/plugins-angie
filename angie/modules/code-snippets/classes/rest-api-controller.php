<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Api_Controller {

	const NAMESPACE = 'angie/v1';
	const MAX_FILES_PER_REQUEST = 100;
	const MAX_FILE_SIZE_BYTES = 102400;

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/snippets',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_snippets' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<slug>[a-zA-Z0-9_-]+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_snippet' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'slug' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_snippet' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'slug' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<slug>[a-zA-Z0-9_-]+)/files',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_snippet_files' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'slug' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'upsert_snippet_files' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'slug'      => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'files'     => [
							'required' => true,
							'type'     => 'array',
						],
						'overwrite' => [
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						],
						'type'      => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<slug>[a-zA-Z0-9_-]+)/files/(?P<filename>.+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_snippet_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'slug'     => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'filename' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/dev-mode',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_dev_mode' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'enabled' => [
							'required'          => true,
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						],
					],
				],
			]
		);

	register_rest_route(
		self::NAMESPACE,
		'/snippets/(?P<slug>[a-zA-Z0-9_-]+)/publish',
		[
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'publish_snippet' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		]
	);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/validate',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'validate_snippet' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'files' => [
							'required' => true,
							'type'     => 'array',
						],
					],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/dev-mode/status',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'is_dev_mode' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	public function check_permission() {
		return Module::current_user_can_manage_snippets();
	}

	public function list_snippets( $request ) {
		$posts = Snippet_Repository::get_all_snippets();

		$snippets = [];
		foreach ( $posts as $post ) {
			$snippets[] = Snippet_Repository::get_snippet_data( $post );
		}

		return rest_ensure_response( [
			'snippets' => $snippets,
			'total'    => count( $snippets ),
		] );
	}

	public function get_snippet( $request ) {
		$slug = $request->get_param( 'slug' );
		$post = Snippet_Repository::find_snippet_post_by_slug( $slug );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( Snippet_Repository::get_snippet_data( $post ) );
	}

	public function list_snippet_files( $request ) {
		$slug = $request->get_param( 'slug' );
		$post = Snippet_Repository::find_snippet_post_by_slug( $slug );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		$file_list = Snippet_Repository::get_snippet_file_list( $post->ID );

		return rest_ensure_response( [
			'files' => $file_list,
			'total' => count( $file_list ),
		] );
	}

	public function get_snippet_file( $request ) {
		$slug     = $request->get_param( 'slug' );
		$filename = $request->get_param( 'filename' );
		$post     = Snippet_Repository::find_snippet_post_by_slug( $slug );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		$file = Snippet_Repository::get_file_by_name( $post->ID, $filename );

		if ( ! $file ) {
			return new \WP_Error(
				'file_not_found',
				esc_html__( 'File not found in snippet.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		$content = '';
		if ( isset( $file['content_b64'] ) && is_string( $file['content_b64'] ) ) {
			$decoded = base64_decode( $file['content_b64'], true );
			$content = ( false === $decoded ) ? '' : $decoded;
		}

		return rest_ensure_response( [
			'name'    => $file['name'],
			'content' => $content,
			'size'    => strlen( $content ),
		] );
	}

	public function upsert_snippet_files( $request ) {
		$slug      = $request->get_param( 'slug' );
		$files     = $request->get_param( 'files' );
		$overwrite = $request->get_param( 'overwrite' );
		$type      = $request->get_param( 'type' );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return new \WP_Error(
				'invalid_files',
				esc_html__( 'Files parameter must be a non-empty array.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		if ( count( $files ) > self::MAX_FILES_PER_REQUEST ) {
			return new \WP_Error(
				'too_many_files',
				sprintf(
					/* translators: %d: maximum number of files */
					esc_html__( 'Cannot process more than %d files per request.', 'angie' ),
					self::MAX_FILES_PER_REQUEST
				),
				[ 'status' => 400 ]
			);
		}

		$sanitized_files = [];

		foreach ( $files as $file ) {
			if ( ! isset( $file['name'] ) || ! isset( $file['content'] ) ) {
				return new \WP_Error(
					'invalid_file_format',
					esc_html__( 'Each file must have "name" and "content" properties.', 'angie' ),
					[ 'status' => 400 ]
				);
			}

			$name = File_Validator::sanitize_filename( $file['name'] );
			$content = $file['content'];

			if ( empty( $name ) ) {
				return new \WP_Error(
					'invalid_filename',
					esc_html__( 'File name cannot be empty or contains invalid characters.', 'angie' ),
					[ 'status' => 400 ]
				);
			}

			$allowed_extensions = [ 'php', 'css', 'js' ];
			$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $allowed_extensions, true ) ) {
				return new \WP_Error(
					'invalid_file_type',
					sprintf(
						/* translators: %s: filename */
						esc_html__( 'Invalid file type for: %s. Only PHP, CSS, and JS files are allowed.', 'angie' ),
						$name
					),
					[ 'status' => 400 ]
				);
			}

			if ( strlen( $content ) > self::MAX_FILE_SIZE_BYTES ) {
				return new \WP_Error(
					'file_too_large',
					sprintf(
						/* translators: %s: filename */
						esc_html__( 'File too large: %s. Maximum size is 100KB.', 'angie' ),
						$name
					),
					[ 'status' => 400 ]
				);
			}

			$sanitized_files[] = [
				'name' => $name,
				'content_b64' => base64_encode( $content ),
			];
		}

		$validation_result = Snippet_Validator::validate_snippet_files( $sanitized_files );

		if ( is_wp_error( $validation_result ) ) {
			return new \WP_Error(
				$validation_result->get_error_code(),
				$validation_result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $type ) && ! Taxonomy_Manager::is_valid_type( $type ) ) {
			return new \WP_Error(
				'invalid_type',
				sprintf(
					/* translators: %s: provided type value */
					esc_html__( 'Invalid snippet type: %s. Valid types are: code-snippet, elementor-widget, gutenberg-block, popup, form, visual-app.', 'angie' ),
					$type
				),
				[ 'status' => 400 ]
			);
		}

		$post = Snippet_Repository::find_snippet_post_by_slug( $slug );

		if ( ! $post ) {
			if ( ! Snippet_Repository::has_main_php_file( $sanitized_files ) ) {
				return new \WP_Error(
					'main_php_required',
					esc_html__( 'New snippets must include a main.php file.', 'angie' ),
					[ 'status' => 400 ]
				);
			}

			$post_id = Snippet_Repository::create_snippet( $slug );

			if ( is_wp_error( $post_id ) ) {
				return new \WP_Error(
					'snippet_create_failed',
					esc_html__( 'Failed to create snippet.', 'angie' ),
					[ 'status' => 500 ]
				);
			}

			if ( ! empty( $type ) ) {
				wp_set_object_terms( $post_id, $type, Taxonomy_Manager::TAXONOMY_NAME );
			}

			Snippet_Repository::update_snippet_files( $post_id, $sanitized_files );
			File_System_Handler::write_snippet_files_to_disk( Dev_Mode_Manager::ENV_DEV, $post_id, $sanitized_files );
			Cache_Manager::clear_published_snippet_cache();

			return rest_ensure_response( [
				'success' => true,
				'message' => esc_html__( 'Snippet created successfully.', 'angie' ),
				'slug'    => $slug,
				'files'   => count( $sanitized_files ),
			] );
		}

		if ( ! $overwrite ) {
			return new \WP_Error(
				'snippet_exists',
				esc_html__( 'Snippet already exists. Set overwrite=true to update.', 'angie' ),
				[ 'status' => 409 ]
			);
		}

		$existing_files = Snippet_Repository::get_snippet_files( $post->ID );
		$merged_files = Snippet_Repository::merge_snippet_files( $existing_files, $sanitized_files );

		if ( ! Snippet_Repository::has_main_php_file( $merged_files ) ) {
			return new \WP_Error(
				'main_php_required',
				esc_html__( 'Snippet must have a main.php file.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $type ) ) {
			wp_set_object_terms( $post->ID, $type, Taxonomy_Manager::TAXONOMY_NAME );
		}

		Snippet_Repository::update_snippet_files( $post->ID, $merged_files );
		File_System_Handler::write_snippet_files_to_disk( Dev_Mode_Manager::ENV_DEV, $post->ID, $merged_files );
		Cache_Manager::clear_published_snippet_cache();

		return rest_ensure_response( [
			'success' => true,
			'message' => esc_html__( 'Snippet files updated successfully.', 'angie' ),
			'slug'    => $slug,
			'files'   => count( $merged_files ),
		] );
	}

	public function delete_snippet( $request ) {
		$slug = $request->get_param( 'slug' );
		$post = Snippet_Repository::find_snippet_post_by_slug( $slug );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

 	$environments = [ Dev_Mode_Manager::ENV_DEV, Dev_Mode_Manager::ENV_PROD ];
 	File_System_Handler::delete_snippet_files( $post->ID, $environments );

 	$result = Snippet_Repository::delete_snippet( $post->ID );

 	if ( ! $result ) {
 		return new \WP_Error(
 			'delete_failed',
 			esc_html__( 'Failed to delete snippet.', 'angie' ),
 			[ 'status' => 500 ]
 		);
 	}

 	Cache_Manager::clear_published_snippet_cache();

 	return rest_ensure_response( [
 		'success' => true,
 		'message' => esc_html__( 'Snippet deleted successfully.', 'angie' ),
 		'slug'    => $slug,
 	] );
  }

 	public function set_dev_mode( $request ) {
 		$enabled = $request->get_param( 'enabled' );

 		if ( $enabled ) {
 			$session = Dev_Mode_Manager::create_dev_mode_session();

 			if ( ! $session ) {
 				return new \WP_Error(
 					'session_creation_failed',
 					esc_html__( 'Failed to create dev mode session. User must be logged in.', 'angie' ),
 					[ 'status' => 403 ]
 				);
 			}

 			return rest_ensure_response( [
 				'success' => true,
 				'message' => esc_html__( 'Dev mode enabled for 1 hour.', 'angie' ),
 				'enabled' => true,
 				'expiry'  => $session['expiry'],
 			] );
 		} else {
 			Dev_Mode_Manager::clear_dev_mode_session();

 			return rest_ensure_response( [
 				'success' => true,
 				'message' => esc_html__( 'Dev mode disabled.', 'angie' ),
 				'enabled' => false,
 			] );
 		}
 	}

 	public function publish_snippet( $request ) {
 		$slug = $request->get_param( 'slug' );
 		$post = Snippet_Repository::find_snippet_post_by_slug( $slug );

 		if ( ! $post ) {
 			return new \WP_Error(
 				'snippet_not_found',
 				esc_html__( 'Snippet not found.', 'angie' ),
 				[ 'status' => 404 ]
 			);
 		}

 		$files = Snippet_Repository::get_snippet_files( $post->ID );

 		if ( empty( $files ) ) {
 			return new \WP_Error(
 				'no_files',
 				esc_html__( 'Snippet has no files to publish.', 'angie' ),
 				[ 'status' => 400 ]
 			);
 		}

 		File_System_Handler::write_snippet_files_to_disk( Dev_Mode_Manager::ENV_PROD, $post->ID, $files );
 		Cache_Manager::clear_published_snippet_cache();

 		return rest_ensure_response( [
 			'success' => true,
 			'message' => esc_html__( 'Snippet published to production successfully.', 'angie' ),
 			'slug'    => $slug,
 			'files'   => count( $files ),
 		] );
 	}

	public function validate_snippet( $request ) {
		$files = $request->get_param( 'files' );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return new \WP_Error(
				'invalid_files',
				esc_html__( 'Files parameter must be a non-empty array.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		$sanitized_files = [];

		foreach ( $files as $file ) {
			if ( ! isset( $file['name'] ) || ! isset( $file['content'] ) ) {
				return new \WP_Error(
					'invalid_file_format',
					esc_html__( 'Each file must have "name" and "content" properties.', 'angie' ),
					[ 'status' => 400 ]
				);
			}

			$name    = sanitize_text_field( $file['name'] );
			$content = $file['content'];

			$check_result = File_Validator::check_forbidden_functions( $content );
			if ( ! $check_result['allowed'] ) {
				return new \WP_Error(
					'forbidden_function',
					sprintf(
						/* translators: %s: function name */
						esc_html__( 'Forbidden function detected: %s', 'angie' ),
						$check_result['function']
					),
					[ 'status' => 400 ]
				);
			}

			$sanitized_files[] = [
				'name'        => $name,
				'content'     => $content,
				'content_b64' => base64_encode( $content ),
			];
		}

		$validation_result = Snippet_Validator::validate_snippet_execution( $sanitized_files );

		if ( ! $validation_result['valid'] ) {
			return new \WP_Error(
				'validation_failed',
				$validation_result['error'],
				[
					'status'  => 400,
					'details' => $validation_result['details'],
				]
			);
		}

		return rest_ensure_response( [
			'valid'   => true,
			'message' => esc_html__( 'Snippet validation passed.', 'angie' ),
		] );
	}

	public function is_dev_mode( $request ) {
		return rest_ensure_response( [
			'enabled' => Dev_Mode_Manager::is_dev_mode_enabled(),
		] );
	}
}
