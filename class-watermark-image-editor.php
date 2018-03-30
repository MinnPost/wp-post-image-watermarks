<?php
class Watermark_Image_Editor extends WP_Image_Editor_GD {
	/**
	* Stamp the watermark onto the image attached to this class
	*
	* @param string $stamp_path
	* @param string|int $x
	* @param string|int $y
	*
	*/
	public function stamp_watermark( $stamp_path, $x = 0, $y = 0 ) {
		$loaded = $this->load();
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$stamp = imagecreatefrompng( $stamp_path );
		if ( is_wp_error( $stamp ) ) {
			return $stamp;
		}
		imagealphablending( $stamp, true );

		$stamp_width  = imagesx( $stamp );
		$stamp_height = imagesy( $stamp );
		imagealphablending( $this->image, true );

		switch ( $x ) {
			case 'left':
				$x = 0;
				break;
			case 'right':
				$x = $image_width - $stamp_width;
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
				$y = $image_width - $stamp_height;
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
		imagecopy( $this->image, $stamp, $x, $y, 0, 0, $stamp_width, $stamp_height );
	}

	public static function test( $args = array() ) {
		return parent::test( $args );
	}

	public static function supports_mime_type( $mime_type ) {
		return parent::supports_mime_type( $mime_type );
	}
}
