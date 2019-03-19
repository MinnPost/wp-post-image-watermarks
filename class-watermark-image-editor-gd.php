<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Watermark_Image_Editor_GD extends Improved_Image_Editor_GD {
	/**
	* Stamp the watermark onto the image attached to this class
	*
	* @param string|resource $watermark_image
	* @param string|int $x
	* @param string|int $y
	*
	*/
	public function stamp_watermark( $watermark_image, $x = 0, $y = 0 ) {
		$loaded = $this->load();
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		if ( is_string( $watermark_image ) ) {
			switch ( $this->mime_type ) {
				case 'gif':
					$watermark_image = imagecreatefromgif( $watermark_image );
					break;
				case 'png':
					$watermark_image = imagecreatefrompng( $watermark_image );
					break;
				case 'jpg':
				case 'jpeg':
					$watermark_image = imagecreatefromjpeg( $watermark_image );
					break;
				default:
					return false;
					break;
			}
		}

		if ( is_wp_error( $watermark_image ) ) {
			return $watermark_image;
		}
		imagealphablending( $watermark_image, true );

		$image_width  = imagesx( $this->image );
		$image_height = imagesy( $this->image );

		$watermark_width  = imagesx( $watermark_image );
		$watermark_height = imagesy( $watermark_image );

		imagealphablending( $this->image, true );

		switch ( $x ) {
			case 'left':
				$x = 0;
				break;
			case 'right':
				$x = $image_width - $watermark_width;
				break;
			default:
				$x = $x;
				break;
		}

		switch ( $y ) {
			case 'top':
				$y = 0;
				break;
			case 'bottom':
				$y = $image_width - $watermark_height;
				break;
			default:
				$y = $y;
				break;
		}

		/**
		* PHP imagecopy function
		*
		* @param resource $dst_im
		*     Destination image link resource.
		* @param resource $src_im
		*     Source image link resource.
		* @param int $dst_x
		*     x-coordinate of destination point.
		* @param int $dst_y
		*     y-coordinate of destination point.
		* @param int $src_x
		*     x-coordinate of source point.
		* @param int $src_y
		*     y-coordinate of source point.
		* @param int
		*     $src_w Source width.
		* @param int
		*     $src_h Source height.
		*
		* @return bool
		*     true on success, false on failure
		*
		*/
		imagecopy( $this->image, $watermark_image, $x, $y, 0, 0, $watermark_width, $watermark_height );
	}

	/**
	 * Resizes current image and returns it as a resource instead of a boolean.
	 * Returns the GD Resource from _resize.
	 *
	 * At minimum, either a height or width must be provided.
	 * If one of the two is set to null, the resize will
	 * maintain aspect ratio according to the provided dimension.
	 *
	 * @since 3.5.0
	 *
	 * @param  int|null $max_w Image width.
	 * @param  int|null $max_h Image height.
	 * @param  bool     $crop
	 * @return true|WP_Error
	 */
	public function resize_get_resource( $max_w, $max_h, $crop = false ) {
		if ( ( $this->size['width'] == $max_w ) && ( $this->size['height'] == $max_h ) ) {
			return true;
		}
		$resized = $this->_resize( $max_w, $max_h, $crop );
		return $resized;
	}

	public static function test( $args = array() ) {
		return parent::test( $args );
	}

	public static function supports_mime_type( $mime_type ) {
		return parent::supports_mime_type( $mime_type );
	}
}
