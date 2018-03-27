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
		$this->watermark_folder_url = get_theme_file_path() . '/assets/img/icons/';
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
			$thumbnail_url = str_replace( get_site_url() . '/', get_home_path(), $post_meta[ $this->thumbnail_image_field ][0] );
		} else {
			return;
		}

		$watermark_url = $this->watermark_folder_url . $watermark_file_name . $this->watermark_extension;

		$image = wp_get_image_editor( $thumbnail_url );
		if ( ! is_wp_error( $image ) && is_callable( [ $image, 'stamp_watermark' ] ) ) {
			$stamp   = imagecreatefrompng( $watermark_url );
			$success = $image->stamp_watermark( $watermark_url );
			if ( ! is_wp_error( $success ) ) {
				$image->save( $thumbnail_url );
			}
		}

	}

}

// Instantiate our class
$wp_post_image_watermarks = WP_Post_Image_Watermarks::get_instance();
