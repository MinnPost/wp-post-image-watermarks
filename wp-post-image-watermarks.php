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

	public $thumbnail_image_field;
	public $watermarked_thumbnail_sizes;

	public $watermark_field;
	public $watermark_folder_url;
	public $watermark_extension;
	public $watermark_position_x;
	public $watermark_position_y;
	public $watermark_width_percent;

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

		// this needs to be a wp_postmeta field
		$this->thumbnail_image_field = '_mp_post_thumbnail_image';

		// this needs to be an array of theme image sizes
		$this->watermarked_thumbnail_sizes = array( 'feature', 'feature-large', 'feature-medium', 'newsletter-thumbnail', 'author-thumbnail', 'thumbnail' );

		// this is a wp_postmetafield. the value should represent a watermark filename in the folder path.
		$this->watermark_field         = '_mp_plus_icon_style';
		$this->watermark_folder_url    = get_theme_file_path() . '/assets/img/icons/';
		$this->watermark_extension     = '.png';
		$this->watermark_position_x    = 'right';
		$this->watermark_position_y    = 'top';
		$this->watermark_width_percent = 25;

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
		add_action( 'save_post', array( $this, 'save_watermark_image' ), 11, 1 );

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

		// get the necessary dimensions for the sizes we allow to pass to the editor.
		$image_sizes = $this->get_image_sizes( $this->watermarked_thumbnail_sizes );

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

		// see if the post has a value for the given thumbnail image field, and if it is a WordPress uploaded file
		if ( isset( $post_meta[ $this->thumbnail_image_field ][0] ) && 0 === strpos( $post_meta[ $this->thumbnail_image_field ][0], get_site_url() ) ) {
			$thumbnail_url = str_replace( get_site_url() . '/', get_home_path(), $post_meta[ $this->thumbnail_image_field ][0] );
		} else {
			return;
		}

		$watermark_url = $this->watermark_folder_url . $watermark_file_name . $this->watermark_extension;

		// edit the thumbnail image
		$image = wp_get_image_editor( $thumbnail_url );
		if ( ! is_wp_error( $image ) ) {
			$original_size = $image->get_size();
		}

		// by putting the watermark on it
		if ( ! is_wp_error( $image ) && is_callable( [ $image, 'stamp_watermark' ] ) ) {
			// this saves a watermarked version of each of the plugin's specified watermarked thumbnails.
			foreach ( $image_sizes as $size ) {
				$editor = wp_get_image_editor( $thumbnail_url );
				if ( ! is_wp_error( $editor ) ) {

					// set the dimensions that WordPress should generate for the image
					$editor->resize( $size['width'], $size['height'], $size['crop'] );

					// resize the watermark image to match the defined percentage of the to-be-generated image
					$watermark_image = wp_get_image_editor( $watermark_url );
					if ( ! is_wp_error( $watermark_image ) ) {
						$watermark_image = $watermark_image->resize_get_resource( ( $this->watermark_width_percent / 100 ) * $size['width'], null );
						// put the watermark on top of the generated image and save it
						$success      = $editor->stamp_watermark( $watermark_image, $this->watermark_position_x, $this->watermark_position_y );
						$resized_file = $editor->save();
						unset( $resized_file['path'] );
					}
				}
			}
		}

	}

	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @global $_wp_additional_image_sizes
	 * @uses   get_intermediate_image_sizes()
	 * @return array $sizes Data for all currently-registered image sizes.
	 */
	public function get_image_sizes( $allowed_sizes ) {
		global $_wp_additional_image_sizes;

		$sizes = array();

		foreach ( $allowed_sizes as $_size ) {
			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
				$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
				$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
				$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array(
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				);
			}
		}

		return $sizes;
	}

}

// Instantiate our class
$wp_post_image_watermarks = WP_Post_Image_Watermarks::get_instance();
