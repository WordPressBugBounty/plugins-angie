<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippet_Repository {

	public static function find_snippet_post_by_slug( $slug ) {
		$args = [
			'post_type'      => Module::CPT_NAME,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'title'          => $slug,
		];

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			$args = [
				'post_type'      => Module::CPT_NAME,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'name'           => $slug,
			];
			$posts = get_posts( $args );
		}

		return ! empty( $posts ) ? $posts[0] : null;
	}

	public static function get_snippet_slug_from_post( $post ) {
		$slug = sanitize_title( $post->post_title );
		if ( empty( $slug ) ) {
			$slug = $post->post_name;
		}
		if ( empty( $slug ) ) {
			$slug = 'snippet-' . $post->ID;
		}
		return $slug;
	}

	public static function create_snippet( $slug ) {
		$post_id = wp_insert_post( [
			'post_title'  => $slug,
			'post_type'   => Module::CPT_NAME,
			'post_status' => 'publish',
		], true );

		return $post_id;
	}

	public static function delete_snippet( $post_id ) {
		return wp_delete_post( $post_id, true );
	}

	public static function get_snippet_files( $post_id ) {
		$files = get_post_meta( $post_id, '_angie_snippet_files', true );
		if ( ! is_array( $files ) ) {
			$files = [];
		}
		return $files;
	}

	public static function update_snippet_files( $post_id, $files ) {
		return update_post_meta( $post_id, '_angie_snippet_files', $files );
	}

	public static function get_all_snippets() {
		$args = [
			'post_type'      => Module::CPT_NAME,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		return get_posts( $args );
	}

	private static function build_file_list( $files ) {
		$file_list = [];
		foreach ( $files as $file ) {
			$file_list[] = [
				'name' => $file['name'],
				'size' => isset( $file['content_b64'] ) ? strlen( base64_decode( $file['content_b64'], true ) ) : 0,
			];
		}
		return $file_list;
	}

	public static function get_snippet_data( $post ) {
		$files = self::get_snippet_files( $post->ID );

		return [
			'slug'   => self::get_snippet_slug_from_post( $post ),
			'title'  => $post->post_title,
			'status' => $post->post_status,
			'files'  => self::build_file_list( $files ),
		];
	}

	public static function get_file_by_name( $post_id, $filename ) {
		$files = self::get_snippet_files( $post_id );

		foreach ( $files as $file ) {
			if ( $file['name'] === $filename ) {
				return $file;
			}
		}

		return null;
	}

	public static function merge_snippet_files( $existing_files, $new_files ) {
		$merged_by_name = [];

		foreach ( $existing_files as $file ) {
			$merged_by_name[ $file['name'] ] = $file;
		}

		foreach ( $new_files as $file ) {
			$merged_by_name[ $file['name'] ] = $file;
		}

		return array_values( $merged_by_name );
	}

	public static function has_main_php_file( $files ) {
		foreach ( $files as $file ) {
			if ( 'main.php' === $file['name'] ) {
				return true;
			}
		}

		return false;
	}

	public static function get_snippet_file_list( $post_id ) {
		$files = self::get_snippet_files( $post_id );

		return self::build_file_list( $files );
	}
}
