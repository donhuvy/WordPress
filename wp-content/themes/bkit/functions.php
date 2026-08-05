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

/**
 * Add red B favicon for BKIT theme without modifying core WordPress files.
 */
function bkit_add_custom_favicon() {
    $theme_dir = get_template_directory_uri();
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $theme_dir . '/images/favicon.svg' ) . '">' . "\n";
    echo '<link rel="alternate icon" type="image/x-icon" href="' . esc_url( $theme_dir . '/images/favicon.ico' ) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url( $theme_dir . '/images/favicon.svg' ) . '">' . "\n";
}
add_action( 'wp_head', 'bkit_add_custom_favicon', 1 );
add_action( 'admin_head', 'bkit_add_custom_favicon', 1 );
add_action( 'login_head', 'bkit_add_custom_favicon', 1 );

/**
 * Tùy chỉnh hiển thị tối đa 20 nút bấm trang trực tiếp (chưa bao gồm nút Trang trước, Trang sau).
 * Nếu tổng số trang <= 20, hiển thị đầy đủ tất cả các nút trang.
 * Nếu tổng số trang > 20, hiển thị tối đa 20 nút bấm trực tiếp quanh trang hiện tại.
 */
function bkit_custom_paginate_links_output( $r, $args ) {
    if ( empty( $args['total'] ) || (int) $args['total'] < 2 ) {
        return $r;
    }

    $total       = (int) $args['total'];
    $current     = (int) ( $args['current'] ?? 1 );
    $max_visible = 20;

    if ( $total <= $max_visible ) {
        $pages_to_show = range( 1, $total );
    } else {
        $start = $current - 8;
        $end   = $current + 9;
        if ( $start <= 3 ) {
            $start = 1;
            $end   = $max_visible - 1;
        } elseif ( $end >= $total - 2 ) {
            $end   = $total;
            $start = $total - ( $max_visible - 2 );
        }

        $pages_to_show = array();
        if ( $start > 1 ) {
            $pages_to_show[] = 1;
            if ( $start > 2 ) {
                $pages_to_show[] = 'dots';
            }
        }
        for ( $i = $start; $i <= $end; $i++ ) {
            $pages_to_show[] = $i;
        }
        if ( $end < $total ) {
            if ( $end < $total - 1 ) {
                $pages_to_show[] = 'dots';
            }
            $pages_to_show[] = $total;
        }
    }

    $page_links = array();
    $add_args   = $args['add_args'] ?? array();

    // Nút "Trang trước"
    if ( ! empty( $args['prev_next'] ) && $current && $current > 1 ) {
        $link = str_replace( '%_%', 2 === $current ? '' : $args['format'], $args['base'] );
        $link = str_replace( '%#%', $current - 1, $link );
        if ( ! empty( $add_args ) ) {
            $link = add_query_arg( $add_args, $link );
        }
        $link .= $args['add_fragment'] ?? '';

        $page_links[] = sprintf(
            '<a class="prev page-numbers" href="%s">%s</a>',
            esc_url( apply_filters( 'paginate_links', $link ) ),
            $args['prev_text'] ?? __( '&laquo; Previous' )
        );
    }

    // Các nút số trang
    foreach ( $pages_to_show as $p ) {
        if ( 'dots' === $p ) {
            $page_links[] = '<span class="page-numbers dots">' . __( '&hellip;' ) . '</span>';
        } elseif ( $p === $current ) {
            $page_links[] = sprintf(
                '<span aria-current="%s" class="page-numbers current">%s</span>',
                esc_attr( $args['aria_current'] ?? 'page' ),
                ( $args['before_page_number'] ?? '' ) . number_format_i18n( $p ) . ( $args['after_page_number'] ?? '' )
            );
        } else {
            $link = str_replace( '%_%', 1 === $p ? '' : $args['format'], $args['base'] );
            $link = str_replace( '%#%', $p, $link );
            if ( ! empty( $add_args ) ) {
                $link = add_query_arg( $add_args, $link );
            }
            $link .= $args['add_fragment'] ?? '';

            $page_links[] = sprintf(
                '<a class="page-numbers" href="%s">%s</a>',
                esc_url( apply_filters( 'paginate_links', $link ) ),
                ( $args['before_page_number'] ?? '' ) . number_format_i18n( $p ) . ( $args['after_page_number'] ?? '' )
            );
        }
    }

    // Nút "Trang sau"
    if ( ! empty( $args['prev_next'] ) && $current && $current < $total ) {
        $link = str_replace( '%_%', $args['format'], $args['base'] );
        $link = str_replace( '%#%', $current + 1, $link );
        if ( ! empty( $add_args ) ) {
            $link = add_query_arg( $add_args, $link );
        }
        $link .= $args['add_fragment'] ?? '';

        $page_links[] = sprintf(
            '<a class="next page-numbers" href="%s">%s</a>',
            esc_url( apply_filters( 'paginate_links', $link ) ),
            $args['next_text'] ?? __( 'Next &raquo;' )
        );
    }

    $type = $args['type'] ?? 'plain';
    switch ( $type ) {
        case 'array':
            return $page_links;
        case 'list':
            $out  = "<ul class='page-numbers'>\n\t<li>";
            $out .= implode( "</li>\n\t<li>", $page_links );
            $out .= "</li>\n</ul>\n";
            return $out;
        default:
            return implode( "\n", $page_links );
    }
}
add_filter( 'paginate_links_output', 'bkit_custom_paginate_links_output', 10, 2 );


