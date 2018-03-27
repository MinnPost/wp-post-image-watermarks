<?php
/*
Plugin Name: WP Post Image Watermarks
Plugin URI:
Description:
Version: 0.0.1
Author: Jonathan Stegall
Author URI: http://code.minnpost.com
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-post-image-watermarks
*/

class WP_Post_Image_Watermarks {

	public $option_prefix;
	public $version;
	public $slug;

	public $watermark_field;
	public $watermark_folder_url;
	public $watermark_extension;

	/*private $api_key;
	private $resource_type;
	private $resource_id;
	private $user_subresource_type;
	private $list_subresource_type;*/

	/**
	 * @var object
	 * Static property to hold an instance of the class; this seems to make it reusable
	 *
	 */
	static $instance = null;

	/**
	* Load the static $instance property that holds the instance of the class.
	* This instance makes the class reusable by other plugins
	*
	* @return object
	*   Instance of the plugin object
	*
	*/
	static public function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new WP_Post_Image_Watermarks();
		}
		return self::$instance;
	}

	public function __construct() {

		$this->option_prefix = 'wp_post_image_watermark';
		$this->version       = '0.0.1';
		$this->slug          = 'wp-post-image-watermarks';

		$this->thumbnail_image_field = '_mp_post_thumbnail_image';

		$this->watermark_field      = '_mp_plus_icon_style';
		$this->watermark_folder_url = get_theme_file_uri() . '/assets/img/icons/';
		$this->watermark_extension  = '.png';

		$this->add_actions();
	}

	/**
	* Actions and filters
	*
	*/
	private function add_actions() {
		// add the watermark image editor
		add_filter( 'wp_image_editors', array( $this, 'add_watermark_editor' ), 10, 1 );

		// save watermark on post save
		add_action( 'save_post', array( $this, 'save_watermark_image' ) );

		// when an image is uploaded, add watermark if applicable
		//add_filter( 'wp_handle_upload', array( $this, 'handle_upload_callback' ), 10, 3 );
	}

	/**
	* Save watermark when post is saved
	*
	* @param int $post_id
	*/
	public function save_watermark_image( $post_id ) {
			// Make sure we should be doing this action
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] && ! current_user_can( 'edit_page', $post_id ) ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Filter the default overlay settings
		//add_filter( 'watermark_image_filter_defaults', 'update_watermark_image_defaults', 10, 2 );
		//Watermark_Image_Generator::generate_watermark( $text, $img_url, $post_id );

		// hook to set whether or not this attachment needs to be watermarked
		$watermarked = apply_filters( 'image_watermark_allowed', false, $post_id );

		if ( false === $watermarked ) {
			return;
		}

		$post_meta = get_post_meta( $post_id );

		// see if there is a value on the post for the watermark field
		if ( isset( $post_meta[ $this->watermark_field ][0] ) ) {
			$watermark_file_name = $post_meta[ $this->watermark_field ][0];
		} else {
			return;
		}

		if ( isset( $post_meta[ $this->thumbnail_image_field ][0] ) ) {
			$thumbnail_url = str_replace( get_site_url(), $_SERVER['DOCUMENT_ROOT'], $post_meta[ $this->thumbnail_image_field ][0] );
		} else {
			return;
		}

		$watermark_url = str_replace( get_site_url(), $_SERVER['DOCUMENT_ROOT'], $this->watermark_folder_url . $watermark_file_name . $this->watermark_extension );

		$image = wp_get_image_editor( $thumbnail_url );
		if ( ! is_wp_error( $image ) && is_callable( [ $image, 'stamp_watermark' ] ) ) {
			$stamp   = imagecreatefrompng( $watermark_url );
			$success = $image->stamp_watermark( $watermark_url );
			if ( ! is_wp_error( $success ) ) {
				error_log( 'save the image at ' . $thumbnail_url );
				$image->save( $thumbnail_url );
			}
		}

	}

	/**
	* Use the Watermark Image Editor class
	*
	* @param array $editors
	* @return array $editors
	* These are the available image editors to WordPress
	*/
	public function add_watermark_editor( $editors ) {
		require_once __DIR__ . '/class-watermark-image-editor.php';
		if ( ! is_array( $editors ) ) {
			return $editors; //someone broke the filtered value
		}
		array_unshift( $editors, 'Watermark_Image_Editor' );
		return $editors;
	}

	/**
	* Filter data for uploaded files
	* We use this to add the watermark.
	*
	* @param array $upload ( 'file' => filename, 'url' => file url, 'type' => file type )
	* @param string $context (values are upload or sideload)
	* @return array $upload
	*/
	public function handle_upload_callback( $upload, $context ) {
		//$attachment = $this->get_attachment( $upload['url'] );
		$attachment_id = $this->get_attachment_id( $upload['url'] );
		// if this attachment is not attached to a post, we can't do anything with it
		if ( 0 == $post_id ) {
			error_log( 'not an attachment' );
			return $upload;
		}

		// hook to set whether or not this attachment needs to be watermarked
		$watermarked = apply_filters( 'image_watermark_allowed', false, $attachment, $attachment->post_parent );

		if ( false === $watermarked ) {
			error_log( 'not allowed to watermark' );
			return $upload;
		}

		$post_meta = get_post_meta( $post_id );

		// see if there is a value on the post for the watermark field
		if ( ! isset( $post_meta[ $this->watermark_field ] ) ) {
			error_log( 'post has no value for watermark' );
			return $upload;
		}

		$watermark_url = $this->watermark_folder_url . $this->watermark_field . $this->watermark_extension;

		error_log( 'url is ' . $watermark_url );

		$image = wp_get_image_editor( $upload['file'] );
		if ( ! is_wp_error( $image ) && is_callable( [ $image, 'stamp_watermark' ] ) ) {
			$stamp   = imagecreatefrompng( $watermark_url );
			$success = $editor->stamp_watermark( $stamp );
			if ( ! is_wp_error( $success ) ) {
				$image->save();
			}
		}
		return $upload;
	}

	/**
	* Retrieves the attachment object from the file URL
	*
	* @param string $url
	* @return object $attachment
	*/
	private function get_attachment( $url ) {
		global $wpdb;
		error_log( 'url is ' . $url . ' and query is ' . $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s;", $url ) );
		$query = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s;", $url ) );

		error_log( 'query is ' . print_r( $query, true ) );

		$id    = $query[0];
		if ( false !== $id ) {
			$attachment = get_post( $id );
		} else {
			$attachment = $id;
		}
		return $attachment;
	}

	/**
	 * Get an attachment ID given a URL.
	 *
	 * @param string $url
	 *
	 * @return int Attachment ID on success, 0 on failure
	 */
	function get_attachment_id( $url ) {
		$attachment_id = 0;
		$dir           = wp_upload_dir();
		if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?
			$file       = basename( $url );
			$query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'value'   => $file,
						'compare' => 'LIKE',
						'key'     => '_wp_attachment_metadata',
					),
				),
			);
			$query      = new WP_Query( $query_args );
			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post_id ) {
					$meta                = wp_get_attachment_metadata( $post_id );
					$original_file       = basename( $meta['file'] );
					$cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
					if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
						$attachment_id = $post_id;
						break;
					}
				}
			}
		}
		return $attachment_id;
	}

}

// Instantiate our class
$wp_post_image_watermarks = WP_Post_Image_Watermarks::get_instance();
