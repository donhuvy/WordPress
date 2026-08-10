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

/* =========================================================================
 * GOOGLE PREFERRED SOURCES (Nguồn Ưu Tiên Google Search) FOR ketoan.bkit.vn
 * Documentation: https://developers.google.com/search/docs/appearance/preferred-sources
 * ========================================================================= */

/**
 * Output Open Graph and Google Preferred Source meta tags in <head>.
 */
function bkit_google_preferred_source_meta() {
    echo '<meta property="og:site_name" content="Kế toán BKIT">' . "\n";
}
add_action( 'wp_head', 'bkit_google_preferred_source_meta', 2 );

/**
 * Output JSON-LD Structured Data for WebSite & Organization for Google Search.
 */
function bkit_google_preferred_source_schema() {
    $site_url = 'https://ketoan.bkit.vn';
    $logo_url = get_template_directory_uri() . '/images/bkit.png';

    $website_schema = array(
        '@context'      => 'https://schema.org',
        '@type'         => 'WebSite',
        '@id'           => $site_url . '/#website',
        'url'           => $site_url,
        'name'          => 'Kế toán BKIT',
        'alternateName' => 'BKIT Accounting',
        'description'   => get_bloginfo( 'description' ),
        'inLanguage'    => 'vi-VN',
        'publisher'     => array(
            '@id' => $site_url . '/#organization',
        ),
    );

    $organization_schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        '@id'      => $site_url . '/#organization',
        'name'     => 'Kế toán BKIT',
        'url'      => $site_url,
        'logo'     => array(
            '@type' => 'ImageObject',
            'url'   => $logo_url,
        ),
    );

    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( array( $website_schema, $organization_schema ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    echo "\n</script>\n";
}
add_action( 'wp_head', 'bkit_google_preferred_source_schema', 3 );

/**
 * Render Google Preferred Source CTA HTML.
 *
 * @param string $style Style of CTA ('button', 'badge', 'banner').
 * @param string $text  Custom text override.
 * @return string HTML output.
 */
function bkit_google_preferred_source_cta( $style = 'button', $text = '' ) {
    $target_url  = 'https://www.google.com/preferences/source?q=ketoan.bkit.vn';
    $google_icon = '<svg class="google-icon" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>';
    $star_icon   = '<svg class="star-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';

    if ( 'banner' === $style ) {
        $default_text = 'Chọn <strong>Kế toán BKIT</strong> làm Nguồn Ưu Tiên trên Google Tìm Kiếm';
        $cta_text     = ! empty( $text ) ? $text : $default_text;

        $html  = '<div class="google-preferred-source-banner">';
        $html .= '  <div class="gps-banner-content">';
        $html .= '    <div class="gps-banner-header">';
        $html .= '      ' . $google_icon;
        $html .= '      <span class="gps-banner-title">' . $cta_text . '</span>';
        $html .= '    </div>';
        $html .= '    <p class="gps-banner-desc">Nhận thông tin cập nhật nhanh nhất & chính xác nhất về kế toán, thuế và tài chính trực tiếp trên Google Search & AI Overviews.</p>';
        $html .= '  </div>';
        $html .= '  <a href="' . esc_url( $target_url ) . '" target="_blank" rel="noopener noreferrer" class="gps-btn gps-btn-primary">';
        $html .= '    ' . $star_icon . ' <span>Thêm Nguồn Ưu Tiên</span>';
        $html .= '  </a>';
        $html .= '</div>';
        return $html;
    }

    if ( 'badge' === $style ) {
        $default_text = 'Nguồn Ưu Tiên Google';
        $cta_text     = ! empty( $text ) ? $text : $default_text;

        $html  = '<a href="' . esc_url( $target_url ) . '" target="_blank" rel="noopener noreferrer" class="google-preferred-source-badge" title="Đặt ketoan.bkit.vn làm nguồn ưu tiên trên Google Search">';
        $html .= '  ' . $google_icon;
        $html .= '  <span>' . esc_html( $cta_text ) . '</span>';
        $html .= '  ' . $star_icon;
        $html .= '</a>';
        return $html;
    }

    // Default 'button' style
    $default_text = 'Thêm làm Nguồn Ưu Tiên trên Google';
    $cta_text     = ! empty( $text ) ? $text : $default_text;

    $html  = '<a href="' . esc_url( $target_url ) . '" target="_blank" rel="noopener noreferrer" class="google-preferred-source-btn" title="Chọn ketoan.bkit.vn làm Nguồn Ưu Tiên trên Google Tìm Kiếm">';
    $html .= '  ' . $google_icon;
    $html .= '  <span>' . esc_html( $cta_text ) . '</span>';
    $html .= '</a>';

    return $html;
}

/**
 * Shortcode [google_preferred_source style="button|banner|badge" text="..."]
 */
function bkit_google_preferred_source_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'style' => 'button',
            'text'  => '',
        ),
        $atts,
        'google_preferred_source'
    );

    return bkit_google_preferred_source_cta( $atts['style'], $atts['text'] );
}
add_shortcode( 'google_preferred_source', 'bkit_google_preferred_source_shortcode' );



