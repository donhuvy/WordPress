<?php
/**
 * BKIT theme functions and definitions
 * Hỗ trợ font Tiếng Việt (Vietnamese font support)
 *
 * FONT STRATEGY:
 * WordPress/Gutenberg injects font-family via multiple mechanisms:
 *   1. global-styles-inline-css (from theme.json / Site Editor)
 *   2. wp-block-library / wp-block-library-theme stylesheets
 *   3. Inline style="" attributes on individual block elements
 *
 * CSS !important rules CANNOT reliably override all of these because:
 *   - Gutenberg's inline CSS also uses !important
 *   - Inline style attributes have highest specificity
 *   - Load order is unpredictable with plugins
 *
 * SOLUTION: Three-layer approach:
 *   Layer 1 (PHP):  Dequeue/deregister Gutenberg font stylesheets at source
 *   Layer 2 (JS):   MutationObserver in <head> that forces font-family on
 *                    every element — runs before paint and catches all future
 *                    DOM changes (lazy loading, AJAX, etc.)
 *   Layer 3 (CSS):  Theme's style.css handles layout, colors, spacing only —
 *                    no font-family declarations (they'd just lose anyway)
 */

if ( ! function_exists( 'bkit_setup' ) ) :
    function bkit_setup() {
        // Load Vietnamese translation files if available.
        load_theme_textdomain( 'bkit', get_template_directory() . '/languages' );

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

        // Add support for block editor styles.
        add_theme_support( 'editor-styles' );

        // Enqueue editor styles.
        add_editor_style( 'style.css' );
    }
endif;
add_action( 'after_setup_theme', 'bkit_setup' );

/**
 * Force UTF-8 charset for Vietnamese character support.
 */
add_filter( 'blog_charset', function() {
    return 'UTF-8';
} );

/* =========================================================================
 * LAYER 1: Remove WordPress/Gutenberg font sources at the PHP level
 * ========================================================================= */

/**
 * Dequeue and deregister Gutenberg stylesheets that inject conflicting fonts.
 * Runs at priority 9999 to ensure it fires after all enqueues.
 */
function bkit_remove_wp_font_sources() {
    // Block library theme styles (sets serif fonts on quotes, tables, etc.)
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_deregister_style( 'wp-block-library-theme' );

    // Global styles generated from theme.json / Site Editor
    // This is the main source of Gutenberg's font-family injection.
    wp_dequeue_style( 'global-styles' );
    wp_deregister_style( 'global-styles' );

    // Classic theme styles (WP 6.1+)
    wp_dequeue_style( 'classic-theme-styles' );
    wp_deregister_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'bkit_remove_wp_font_sources', 9999 );

/**
 * Enqueue theme stylesheet.
 */
function bkit_scripts() {
    $css_file = get_stylesheet_directory() . '/style.css';
    $version  = file_exists( $css_file ) ? filemtime( $css_file ) : '2.0.0';
    wp_enqueue_style( 'bkit-style', get_stylesheet_uri(), array(), $version );
}
add_action( 'wp_enqueue_scripts', 'bkit_scripts' );

/* =========================================================================
 * LAYER 2: JavaScript font enforcer (the nuclear option that always works)
 * ========================================================================= */

/**
 * Inject font enforcer script directly into <head>, BEFORE any content renders.
 * Uses MutationObserver to catch every element — past, present, and future.
 *
 * Priority 1 = runs as early as possible in wp_head.
 */
function bkit_font_enforcer_script() {
    ?>
    <script>
    (function() {
        var FONT = 'Arial, "Segoe UI", Roboto, "Helvetica Neue", Tahoma, "Noto Sans", sans-serif';
        var normalizedTarget = FONT.replace(/['"\s]/g, '').toLowerCase();

        // Force font on a single element
        function forceFont(el) {
            if (el.nodeType !== 1) return; // Element nodes only
            var currentFont = el.style.fontFamily || '';
            if (currentFont.replace(/['"\s]/g, '').toLowerCase() === normalizedTarget) return;
            el.style.setProperty('font-family', FONT, 'important');
        }

        // Force font on an element and all its descendants
        function forceFontTree(root) {
            forceFont(root);
            var children = root.querySelectorAll('*');
            for (var i = 0; i < children.length; i++) {
                forceFont(children[i]);
            }
        }

        // Process everything currently in the DOM
        forceFontTree(document.documentElement);

        // Watch for ANY future DOM changes
        var observer = new MutationObserver(function(mutations) {
            // Tạm thời ngắt kết nối để tránh bắt các thay đổi do chính script này tạo ra
            observer.disconnect();

            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                // New nodes added
                if (m.addedNodes) {
                    for (var j = 0; j < m.addedNodes.length; j++) {
                        var node = m.addedNodes[j];
                        if (node.nodeType === 1) {
                            forceFontTree(node);
                        }
                    }
                }
                // Attribute changed (style hoặc class)
                if (m.type === 'attributes' && m.attributeName === 'style') {
                    forceFont(m.target);
                }
            }

            // Kết nối lại sau khi xử lý xong các thay đổi
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });

        // Safety net: re-run after full page load (catches deferred scripts)
        window.addEventListener('load', function() {
            observer.disconnect();
            forceFontTree(document.documentElement);
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        });
    })();
    </script>
    <?php
}
add_action( 'wp_head', 'bkit_font_enforcer_script', 1 );

/**
 * Add Vietnamese encoding meta header.
 */
function bkit_vietnamese_encoding_headers() {
    echo '<meta http-equiv="Content-Language" content="vi">' . "\n";
}
add_action( 'wp_head', 'bkit_vietnamese_encoding_headers', 0 );
