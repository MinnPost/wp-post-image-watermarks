<?php
/*
Plugin Name: WP Post Image Watermarks
Plugin URI:
Description:
Version: 0.0.3
Author: Jonathan Stegall
Author URI: https://code.minnpost.com
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

	public $watermark_all_sizes;
	public $watermark_original;
	public $save_temp;

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
		$this->version       = '0.0.3';
		$this->slug          = 'wp-post-image-watermarks';

		// this needs to be a wp_postmeta field
		$this->thumbnail_image_field = '_mp_post_thumbnail_image';

		// this needs to be a wp_postmeta field
		$this->thumbnail_image_field_id = '_mp_post_thumbnail_image_id';

		// this needs to be an array of theme image sizes
		$this->watermarked_thumbnail_sizes = array( 'feature', 'feature-large', 'feature-medium', 'newsletter-thumbnail', 'author-thumbnail', 'thumbnail' );

		// this is a wp_postmetafield. the value should represent a watermark filename in the folder path.
		$this->watermark_field         = '_mp_plus_icon_style';
		$this->watermark_folder_url    = get_theme_file_path() . '/assets/img/icons/';
		$this->watermark_extension     = '.png';
		$this->watermark_position_x    = 'right';
		$this->watermark_position_y    = 'top';
		$this->watermark_width_percent = 25;

		$this->watermark_original  = true;
		$this->watermark_all_sizes = false;
		$this->save_temp           = true;

		$this->add_actions();
	}

	/**
	* Actions and filters
	*
	*/
	private function add_actions() {
		// add the image editors
		add_filter( 'wp_image_editors', array( $this, 'wp_image_editors' ), 10, 1 );

		// save watermark on post save
		add_action( 'save_post', array( $this, 'save_watermark_image' ), 11, 1 );

	}

	/**
	* Use the Improved Image Editor and Watermark Image Editor classes
	*
	* @param array $editors
	* @return array $editors
	* These are the available image editors to WordPress
	*/
	public function wp_image_editors( $editors ) {
		require_once 'editors/gd.php';
		require_once 'editors/imagick.php';
		require_once 'editors/gmagick.php';
		require_once __DIR__ . '/class-watermark-image-editor-gmagick.php';
		require_once __DIR__ . '/class-watermark-image-editor-imagick.php';
		require_once __DIR__ . '/class-watermark-image-editor-gd.php';

		$new_editors = array(
			'Watermark_Image_Editor_Gmagick',
			'Watermark_Image_Editor_Imagick',
			'Watermark_Image_Editor_GD',
			'Improved_Image_Editor_Gmagick',
			'Improved_Image_Editor_Imagick',
			'Improved_Image_Editor_GD',
		);

		$editors = array_merge( $new_editors, $editors );

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

			// if true, this saves a watermarked version of the original uploaded image
			if ( true === $this->watermark_original ) {
				$original_image  = wp_get_image_editor( $thumbnail_url );
				$watermark_image = wp_get_image_editor( $watermark_url );
				if ( ! is_wp_error( $watermark_image ) && ! is_wp_error( $original_image ) ) {
					$original_size = $original_image->get_size();
					$is_resized    = $watermark_image->resize_get_resource( ( $this->watermark_width_percent / 100 ) * $original_size['width'], null );
					// put the watermark on top of the generated image and save it
					$success      = $original_image->stamp_watermark( $watermark_image, $this->watermark_position_x, $this->watermark_position_y );
					$this->save_or_sideload( $thumbnail_url, $original_image, $post_id );
				}
			}

			// this saves a watermarked version of each of the plugin's specified watermarked thumbnails.
			if ( true === $this->watermark_all_sizes ) {
				foreach ( $image_sizes as $key => $size ) {
					$thumbnail_editor = wp_get_image_editor( $thumbnail_url );
					$watermark_image  = wp_get_image_editor( $watermark_url );
					if ( ! is_wp_error( $thumbnail_editor ) && ! is_wp_error( $watermark_image ) ) {
						// set the dimensions that WordPress should generate for the image
						$thumbnail_editor->resize( $size['width'], $size['height'], $size['crop'] );
						$is_resized = $watermark_image->resize_get_resource( ( $this->watermark_width_percent / 100 ) * $size['width'], null );
						// put the watermark on top of the generated image and save it
						$success      = $thumbnail_editor->stamp_watermark( $watermark_image, $this->watermark_position_x, $this->watermark_position_y );

						$this->save_or_sideload( $thumbnail_url, $thumbnail_editor, $post_id, $key );

					}
				}
			}
		}

	}

	/**
	* Save the file to the directory, or save it to a temporary directory and then sideload it into the Media Library
	*
	* @param string $image_url
	* @param object $image_editor
	* @param int $post_id
	* @param string $size_name
	*/
	private function save_or_sideload( $image_url, $image_editor, $post_id, $size_name = '' ) {
		// save to temp directory
		if ( true === $this->save_temp ) {
			$filename       = wp_basename( $image_url );
			$temp_file      = get_temp_dir() . $filename;
			$resized_file   = $image_editor->save( $temp_file );
			$filename_parts = pathinfo( $image_url );

			if ( '' !== $size_name ) {
				$filename = $filename_parts['filename'] . '-' . $resized_file['width'] . 'x' . $resized_file['height'] . '.' . $filename_parts['extension'];
			}

			$resized_file_array = array( // array to mimic $_FILES
	            'name' => $filename,
	            'type' => $resized_file['mime-type'],
	            'tmp_name' => $temp_file, //this field passes the actual path to the image
	            'error' => 0,
	            'size' => filesize( $temp_file ),
	        );

			$sideload_id = media_handle_sideload( $resized_file_array, $post_id );

			if ( ! is_wp_error( $sideload_id ) ) {
				if ( is_file ( $temp_file ) ) {
					unlink( $temp_file );
				}
				if ( '' === $size_name ) { // this is an original file upload
					if ( '' !== $this->thumbnail_image_field ) {
						$media_url = wp_get_attachment_url( $sideload_id );
						update_post_meta( $post_id, $this->thumbnail_image_field, $media_url );
					}
					if ( '' !== $this->thumbnail_image_field_id ) {
						update_post_meta( $post_id, $this->thumbnail_image_field_id, $sideload_id );
					}
				} else {
					// this is a thumbnail size
					if ( '' !== $this->thumbnail_image_field_id ) {
						$post_meta = get_post_meta( $post_id );
						// see if there is an image id on the post
						if ( isset( $post_meta[ $this->thumbnail_image_field_id ][0] ) ) {
							$attachment_id = $post_meta[ $this->thumbnail_image_field_id ][0];
							$metadata      = wp_get_attachment_metadata( $attachment_id );
							$data['sizes'][ $size_name ] = array(
								'file' => $filename,
								'width' => $resized_file['width'],
								'height' => $resized_file['height'],
								'mime-type' => $resized_file['mime-type'],
							);
							wp_update_attachment_metadata( $attachment_id, $data );
							$updated_metadata      = wp_get_attachment_metadata( $attachment_id );
						} else {
							return;
						}
					}
				}
			}
		} else {
			$resized_file = $image_editor->save();
			unset( $resized_file['path'] );
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