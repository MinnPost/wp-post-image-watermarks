<?php
class Watermark_Image_Editor extends WP_Image_Editor_GD {
	public function stamp_watermark( $stamp_path, $margin_h = 0, $margin_v = 0 ) {
		$loaded = $this->load();
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$stamp = imagecreatefrompng( $stamp_path );
		if ( is_wp_error( $stamp ) ) {
			return $stamp;
		}
		imagealphablending( $stamp, true );

		$sx = imagesx( $stamp );
		$sy = imagesy( $stamp );
		imagealphablending( $this->image, true );
		imagecopy( $this->image, $stamp, $margin_h, $this->size['height'] - $sy - $margin_v, 0, 0, $sx, $sy );
	}

	public static function test( $args = array() ) {
		return parent::test( $args );
	}

	public static function supports_mime_type( $mime_type ) {
		return parent::supports_mime_type( $mime_type );
	}
}
