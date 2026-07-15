<?php
/**
 * Runtime artifact URL normalization.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Artifact_Url_Normalizer' ) ) {
	/**
	 * Converts bounded runtime artifact paths into absolute Cloud URLs.
	 */
	final class Npcink_Cloud_Runtime_Artifact_Url_Normalizer {
		/**
		 * Recursively normalizes artifact paths in one decoded runtime value.
		 *
		 * @param mixed  $value Decoded runtime value.
		 * @param string $base_url Cloud base URL.
		 * @return mixed
		 */
		public static function normalize( $value, string $base_url ) {
			return self::normalize_value( $value, untrailingslashit( $base_url ) );
		}

		/**
		 * Recursively normalizes one decoded value.
		 *
		 * @param mixed  $value Decoded runtime value.
		 * @param string $base_url Normalized Cloud base URL.
		 * @return mixed
		 */
		private static function normalize_value( $value, string $base_url ) {
			if ( is_string( $value ) ) {
				return self::absolute_url( $value, $base_url );
			}
			if ( ! is_array( $value ) ) {
				return $value;
			}

			$next = array();
			foreach ( $value as $key => $item ) {
				$next[ $key ] = self::normalize_value( $item, $base_url );
			}

			return $next;
		}

		/**
		 * Builds an absolute Cloud URL for one bounded artifact path.
		 *
		 * @param string $value Decoded scalar value.
		 * @param string $base_url Normalized Cloud base URL.
		 * @return string
		 */
		private static function absolute_url( string $value, string $base_url ): string {
			if ( 1 !== preg_match( '#^/v1/runtime/artifacts/[A-Za-z0-9._:-]+/(?:download|public-download)(?:\\?token=[A-Za-z0-9._~-]+)?$#', $value ) ) {
				return $value;
			}
			if ( '' === $base_url ) {
				return $value;
			}

			return esc_url_raw( $base_url . $value );
		}
	}
}
