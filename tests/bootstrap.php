<?php
/**
 * Stubs mínimos de WordPress para testear Query y Source sin cargar WP.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DGES_DOCS_VERSION', '1.3.0-test' );
define( 'DGES_DOCS_URL', 'https://example.test/wp-content/plugins/DGESdocs/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['dges_test_options'] = array();

function remove_accents( $text ) {
    return strtr(
        (string) $text,
        array(
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
        )
    );
}

function wp_strip_all_tags( $text ) {
    return strip_tags( (string) $text );
}

function wp_parse_args( $args, $defaults = array() ) {
    if ( ! is_array( $args ) ) {
        $args = array();
    }
    return array_merge( $defaults, $args );
}

function sanitize_text_field( $text ) {
    return trim( strip_tags( (string) $text ) );
}

function sanitize_key( $text ) {
    return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $text ) );
}

function absint( $value ) {
    return abs( (int) $value );
}

function wp_unslash( $value ) {
    return $value;
}

function esc_url_raw( $url ) {
    return trim( (string) $url );
}

function wp_parse_url( $url, $component = -1 ) {
    return parse_url( $url, $component );
}

function trailingslashit( $value ) {
    return rtrim( (string) $value, '/\\' ) . '/';
}

function apply_filters( $tag, $value ) {
    return $value;
}

function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['dges_test_options'] ) ? $GLOBALS['dges_test_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
    $GLOBALS['dges_test_options'][ $key ] = $value;
    return true;
}

function add_option( $key, $value, $deprecated = '', $autoload = '' ) {
    if ( array_key_exists( $key, $GLOBALS['dges_test_options'] ) ) {
        return false;
    }
    $GLOBALS['dges_test_options'][ $key ] = $value;
    return true;
}

function delete_option( $key ) {
    unset( $GLOBALS['dges_test_options'][ $key ] );
    return true;
}

function __( $text, $domain = null ) {
    return $text;
}

function _n( $single, $plural, $number, $domain = null ) {
    return 1 === (int) $number ? $single : $plural;
}

function esc_html__( $text, $domain = null ) {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

class WP_Error {
    private $code;
    private $message;
    private $data;

    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code() {
        return $this->code;
    }

    public function get_error_message() {
        return $this->message;
    }

    public function get_error_data() {
        return $this->data;
    }
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}
