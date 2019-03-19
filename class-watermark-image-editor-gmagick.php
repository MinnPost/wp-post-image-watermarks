<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Watermark_Image_Editor_Gmagick extends Improved_Image_Editor_Gmagick {
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
			$watermark_image = $this->load( $watermark_image );
		}

		if ( is_wp_error( $watermark_image ) ) {
			return $watermark_image;
		}

		$image_width  = $this->image->getimagewidth();
		$image_height = $this->image->getimageheight();

		$watermark_width  = $watermark_image->image->getimagewidth();
		$watermark_height = $watermark_image->image->getimageheight();

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

		// Draw the watermark on your image
		$this->image->compositeImage( $watermark_image->image, Gmagick::COMPOSITE_OVER, $x, $y );
	}

	/**
	 * Resizes current image and returns it as a resource instead of a boolean.
	 * Returns the Gmagick Resource from resize.
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
		$resized = $this->resize( $max_w, $max_h, $crop );
		return $resized;
	}

	public static function test( $args = array() ) {
		return parent::test( $args );
	}

}
