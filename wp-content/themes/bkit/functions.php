<?php
/**
 * BKIT theme functions and definitions
 */

if ( ! function_exists( 'bkit_setup' ) ) :
    function bkit_setup() {
        // Add default posts and comments RSS feed links to head.
        add_theme_support( 'automatic-feed-links' );

        // Let WordPress manage the document title.
        add_theme_support( 'title-tag' );

        // Enable support for Post Thumbnails on posts and pages.
        add_theme_support( 'post-thumbnails' );

        // This theme uses wp_nav_menu() in one location.
        register_nav_menus(
            array(
                'menu-1' => esc_html__( 'Primary Navigation Menu', 'bkit' ),
            )
        );

        // Switch default core markup for search form, comment form, and comments to output valid HTML5.
        add_theme_support(
            'html5',
            array(
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
                'style',
                'script',
            )
        );
    }
endif;
add_action( 'after_setup_theme', 'bkit_setup' );

/**
 * Enqueue scripts and styles.
 */
function bkit_scripts() {
    $css_file = get_stylesheet_directory() . '/style.css';
    $version  = file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0';
    wp_enqueue_style( 'bkit-style', get_stylesheet_uri(), array(), $version );
}
add_action( 'wp_enqueue_scripts', 'bkit_scripts' );
