<?php

class WPSEO_Recalculate_Scores_Ajax {

	/**
	 * @var int
	 */
	private $posts_per_page = 1;

	/**
	 * @var array The fields which should be always queries, can be extended by array_merge
	 */
	private $query_fields   = array(
		'post_type'      => 'any',
		'meta_key'       => '_yoast_wpseo_focuskw',
	);

	/**
	 * @var array The options stored in the database
	 */
	private $options;

	/**
	 * Initialize the AJAX hooks
	 */
	public function __construct() {

		// Add the AJAX hook for recalculating.
		add_action( 'wp_ajax_wpseo_recalculate_scores', array( $this, 'recalculate_scores' ) );

	}

	/**
	 * Start recalculation
	 */
	public function recalculate_scores() {
		// @todo: Nonce check
//		if( nonce check is passed)
		wp_die(
			$this->get_posts( filter_input( INPUT_POST, 'paged', FILTER_VALIDATE_INT ) )
		);

	}

	/**
	 * Saving the new linkdex score for given post
	 *
	 * @param string $linkdex
	 * @param string $post_id
	 */
	public function save_score( $linkdex, $post_id ) {
		WPSEO_Meta::set_value( 'linkdex', $linkdex, $post_id );
	}

	/**
	 * Getting the posts from the database by doing a WP_Query.
	 *«
	 *
	 * @param integer $paged
	 *
	 * @return string
	 */
	private function get_posts( $paged ) {
		$post_query = new WP_Query(
			array_merge(
				$this->query_fields,
				array(
					'posts_per_page' => $this->posts_per_page,
					'paged'          => $paged,
				)
			)
		);

		if ( $posts = $post_query->get_posts() ) {
			$this->options = WPSEO_Options::get_all();

			$parsed_posts = $this->parse_posts( $posts );

			$response  = array(
				'posts'       => $parsed_posts,
				'total_posts' => count( $parsed_posts ),
				'next_page'   => ( $paged + 1 ),
			);

			return json_encode( $response );
		}

		return '';
	}

	/**
	 * Parsing the posts with the value we need
	 *
	 * @param array $posts
	 *
	 * @return array
	 */
	private function parse_posts( array $posts ) {
		$parsed_posts = array();
		foreach ( $posts as $post ) {
			$parsed_posts[] = $this->post_to_response( $post );
		}

		return $parsed_posts;
	}

	/**
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	private function post_to_response( WP_Post $post ) {
		$focus_keyword = WPSEO_Meta::get_value( 'focuskw', $post->ID );

		return array(
			'post_id'          => $post->ID,
			// 'post_content'     => $post->post_content,
			'title'            => apply_filters( 'wpseo_title',    wpseo_replace_vars( $this->get_title( $post->ID, $post->post_type ), $post ) ),
			'meta_description' => apply_filters( 'wpseo_metadesc', wpseo_replace_vars( $this->get_meta_description( $post->ID, $post->post_type ), $post ) ),
			'focus_keyword'    => $focus_keyword,
			'focus_keyword_used' => $this->get_focus_keyword_used( $focus_keyword, $post->ID ),
		);
	}

	/**
	 * Counting the number of given keyword used for other posts than given post_id
	 *
	 * @param string  $keyword
	 * @param integer $post_id
	 *
	 * @return int
	 */
	private function get_focus_keyword_used( $keyword, $post_id ) {
		$posts = get_posts(
			array(
				'meta_key'    => '_yoast_wpseo_focuskw',
				'meta_value'  => $keyword,
				'exclude'     => $post_id,
				'fields'      => 'ids',
				'post_type'   => 'any',
				'numberposts' => -1,
			)
		);

		return count( $posts );
	}

	/**
	 * Getting the title for given post
	 *
	 * @param integer $post_id
	 * @param string  $post_type
	 *
	 * @return mixed|string
	 */
	private function get_title( $post_id, $post_type ) {
		if ( ( $title = WPSEO_Meta::get_value( 'title', $post_id )  ) !== '' ) {
			return $title;
		}

		if ( $default_from_options = $this->default_from_options( 'title', $post_type ) ) {
			return str_replace( ' %%page%% ', ' ', $default_from_options );
		}

		return '%%title%%';
	}

	/**
	 * Getting the meta description for given post
	 *
	 * @param integer $post_id
	 * @param string  $post_type
	 *
	 * @return bool|string
	 */
	private function get_meta_description( $post_id, $post_type ) {
		if ( ( $meta_description = WPSEO_Meta::get_value( 'metadesc', $post_id ) ) !== '' ) {
			return $meta_description;
		}

		if ( $default_from_options = $this->default_from_options( 'metadesc', $post_type ) ) {
			return $default_from_options;
		}

		return '';
	}


	/**
	 * Getting default from the options for given field
	 *
	 * @param string $field
	 * @param string $post_type
	 *
	 * @return bool|string
	 */
	private function default_from_options( $field, $post_type ) {
		if ( isset( $this->options[ $field . '-' . $post_type ] ) && $this->options[ $field . '-' . $post_type ] !== '' ) {
			return $this->options[ $field . '-' . $post_type ];
		}

		return false;
	}

}