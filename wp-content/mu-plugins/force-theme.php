<?php
/**
 * Plugin Name: Force BKIT Theme
 * Description: Forces the theme to be BKIT programmatically.
 */

add_filter( 'template', function ( $template ) {
    return 'bkit';
} );

add_filter( 'stylesheet', function ( $stylesheet ) {
    return 'bkit';
} );
