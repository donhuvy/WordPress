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

/* =========================================================================
 * AI ASSISTANT BUTTONS (ChatGPT, Claude, Gemini, Copilot) FOR POSTS & SIDE POSTS
 * Inspired by modern documentation platforms like RustFS docs
 * ========================================================================= */

/**
 * Get AI prompt URLs for a given post.
 *
 * @param int|WP_Post|null $post_id Post ID or WP_Post object.
 * @param string           $custom_prompt Optional prompt override.
 * @return array Array with 'chatgpt', 'claude', 'gemini', 'copilot', 'permalink', and 'prompt'
 */
function bkit_get_ai_prompt_urls( $post_id = null, $custom_prompt = '' ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        $permalink = home_url( '/' );
        $title     = get_bloginfo( 'name' );
    } else {
        $permalink = get_permalink( $post );
        $title     = get_the_title( $post );
    }

    $prompt_suffix = ! empty( $custom_prompt ) ? trim( $custom_prompt ) : 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.';
    // 'Đọc' thay cho 'Read', không còn 2 dòng trống ở cuối câu hỏi
    $full_prompt   = "Đọc {$permalink}, {$prompt_suffix}";

    return array(
        'chatgpt'   => 'https://chatgpt.com/?prompt=' . rawurlencode( $full_prompt ) . '&hints=search',
        'claude'    => 'https://claude.ai/new?q=' . rawurlencode( $full_prompt ),
        'gemini'    => 'https://gemini.google.com/app/77a3900932bd8bb6?prompt=' . rawurlencode( $full_prompt ),
        'copilot'   => 'https://copilot.microsoft.com/?q=' . rawurlencode( $full_prompt ),
        'permalink' => $permalink,
        'title'     => $title,
        'prompt'    => $full_prompt,
    );
}

/**
 * Return authentic SVG icon markup for AI services.
 *
 * @param string $service 'chatgpt', 'claude', 'gemini', or 'copilot'
 * @return string SVG HTML
 */
function bkit_get_ai_icon( $service ) {
    switch ( $service ) {
        case 'chatgpt':
            return '<svg class="ai-icon ai-icon-chatgpt" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.259 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7466-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1683a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4947zm-9.66-4.1354a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1401-1.6564zm-1.6563-9.66a4.485 4.485 0 0 1 2.3418-1.9729v5.6725a.79.79 0 0 0 .388.6766l5.8144 3.3543-2.02 1.1683a.0757.0757 0 0 1-.071 0l-4.8303-2.7866A4.4992 4.4992 0 0 1 1.9436 8.6338zm16.597 3.8558L12.703 9.121l2.02-1.1683a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.388-.6766l-.019-.0047zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.009 9.9681V7.6357a.0757.0757 0 0 1 .0332-.0615l4.882-2.8245a4.4992 4.4992 0 0 1 6.6025 4.6548l-.001.0335zm-8.6942-3.415a4.4755 4.4755 0 0 1 2.8764 1.0408l-.1419.0804-4.7783 2.7582a.7948.7948 0 0 0-.3927.6813v6.7369l-2.02-1.1683a.071.071 0 0 1-.038-.052V9.8211a4.504 4.504 0 0 1 4.4945-4.4947zm-1.079 5.8617l2.848 1.643-2.848 1.643-2.848-1.643 2.848-1.643z" fill="currentColor"/></svg>';

        case 'claude':
            return '<svg class="ai-icon ai-icon-claude" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 2.007a.9.9 0 0 0-.82.529l-3.238 7.37-3.239-7.37a.9.9 0 0 0-.82-.536.9.9 0 0 0-.82.536L4.01 12.398a.9.9 0 0 0 .17.986l6.764 7.683a.9.9 0 0 0 1.353 0l6.764-7.683a.9.9 0 0 0 .17-.986l-4.54-9.862a.9.9 0 0 0-.219-.529zm-5.47 3.328 2.05 4.665h-4.1zM6.16 12.443l3.226-7.008 2.158 4.908-4.24 4.814zm11.68 0-1.144 2.714-4.24-4.814 2.158-4.908zm-5.84 6.634-5.267-5.981h10.534z"/></svg>';

        case 'gemini':
            return '<svg class="ai-icon ai-icon-gemini" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 24C12 17.3726 6.6274 12 0 12C6.6274 12 12 6.6274 12 0C12 6.6274 17.3726 12 24 12C17.3726 12 12 17.3726 12 24Z"/></svg>';

        case 'copilot':
            return '<svg class="ai-icon ai-icon-copilot" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M19.38 10.05a5.55 5.55 0 0 0-4.9-5.52A5.63 5.63 0 0 0 9 7.4a5.55 5.55 0 0 0-5.55 5.55c0 1.29.44 2.48 1.18 3.42A5.55 5.55 0 0 0 9 21.9a5.63 5.63 0 0 0 5.48-2.87 5.55 5.55 0 0 0 4.9-8.98zm-10.38 9.9a3.6 3.6 0 0 1-3.6-3.6 3.6 3.6 0 0 1 1.48-2.9 3.6 3.6 0 0 1 2.12-.7c1.99 0 3.6 1.61 3.6 3.6a3.6 3.6 0 0 1-3.6 3.6zm6-3.6a3.6 3.6 0 0 1-2.12.7 3.6 3.6 0 0 1-3.6-3.6c0-1.99 1.61-3.6 3.6-3.6a3.6 3.6 0 0 1 3.6 3.6 3.6 3.6 0 0 1-1.48 2.9z"/></svg>';

        default:
            return '';
    }
}

/**
 * Render ready-to-use AI action buttons (ChatGPT, Claude, Gemini, Copilot).
 *
 * @param array $args Options for rendering.
 * @return string HTML output.
 */
function bkit_render_ai_action_buttons( $args = array() ) {
    $defaults = array(
        'post_id'       => null,
        'style'         => 'buttons', // 'buttons', 'compact', 'pills', 'cards', 'sidebar'
        'show_chatgpt'  => true,
        'show_claude'   => true,
        'show_gemini'   => true,
        'show_copilot'  => true,
        'chatgpt_text'  => 'ChatGPT',
        'claude_text'   => 'Claude',
        'gemini_text'   => 'Gemini',
        'copilot_text'  => 'Copilot',
        'chatgpt_sub'   => 'Hỏi đáp & phân tích nội dung',
        'claude_sub'    => 'Tóm tắt & nghiên cứu sâu',
        'gemini_sub'    => 'Tra cứu & mở rộng thông tin',
        'copilot_sub'   => 'Tìm kiếm & giải đáp thông minh',
        'custom_prompt' => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
        'class'         => '',
    );

    $args = wp_parse_args( $args, $defaults );
    $urls = bkit_get_ai_prompt_urls( $args['post_id'], $args['custom_prompt'] );

    $output  = '<div class="bkit-ai-actions bkit-ai-style-' . esc_attr( $args['style'] ) . ' ' . esc_attr( $args['class'] ) . '">';

    if ( 'sidebar' === $args['style'] || 'cards' === $args['style'] ) {
        // Full interactive card style with subtext
        if ( $args['show_chatgpt'] ) {
            $output .= '<a href="' . esc_url( $urls['chatgpt'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-chatgpt" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="chatgpt" title="Mở bài viết này trong ChatGPT kèm câu hỏi">';
            $output .= '  <div class="bkit-ai-btn-icon">' . bkit_get_ai_icon( 'chatgpt' ) . '</div>';
            $output .= '  <div class="bkit-ai-btn-text">';
            $output .= '    <span class="bkit-ai-btn-title">' . esc_html( $args['chatgpt_text'] ) . '</span>';
            if ( ! empty( $args['chatgpt_sub'] ) ) {
                $output .= '    <span class="bkit-ai-btn-desc">' . esc_html( $args['chatgpt_sub'] ) . '</span>';
            }
            $output .= '  </div>';
            $output .= '  <span class="bkit-ai-btn-arrow">&rarr;</span>';
            $output .= '</a>';
        }

        if ( $args['show_claude'] ) {
            $output .= '<a href="' . esc_url( $urls['claude'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-claude" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="claude" title="Mở bài viết này trong Claude kèm câu hỏi">';
            $output .= '  <div class="bkit-ai-btn-icon">' . bkit_get_ai_icon( 'claude' ) . '</div>';
            $output .= '  <div class="bkit-ai-btn-text">';
            $output .= '    <span class="bkit-ai-btn-title">' . esc_html( $args['claude_text'] ) . '</span>';
            if ( ! empty( $args['claude_sub'] ) ) {
                $output .= '    <span class="bkit-ai-btn-desc">' . esc_html( $args['claude_sub'] ) . '</span>';
            }
            $output .= '  </div>';
            $output .= '  <span class="bkit-ai-btn-arrow">&rarr;</span>';
            $output .= '</a>';
        }

        if ( $args['show_gemini'] ) {
            $output .= '<a href="' . esc_url( $urls['gemini'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-gemini" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="gemini" title="Mở bài viết này trong Google Gemini kèm câu hỏi">';
            $output .= '  <div class="bkit-ai-btn-icon">' . bkit_get_ai_icon( 'gemini' ) . '</div>';
            $output .= '  <div class="bkit-ai-btn-text">';
            $output .= '    <span class="bkit-ai-btn-title">' . esc_html( $args['gemini_text'] ) . '</span>';
            if ( ! empty( $args['gemini_sub'] ) ) {
                $output .= '    <span class="bkit-ai-btn-desc">' . esc_html( $args['gemini_sub'] ) . '</span>';
            }
            $output .= '  </div>';
            $output .= '  <span class="bkit-ai-btn-arrow">&rarr;</span>';
            $output .= '</a>';
        }

        if ( $args['show_copilot'] ) {
            $output .= '<a href="' . esc_url( $urls['copilot'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-copilot" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="copilot" title="Mở bài viết này trong Microsoft Copilot kèm câu hỏi">';
            $output .= '  <div class="bkit-ai-btn-icon">' . bkit_get_ai_icon( 'copilot' ) . '</div>';
            $output .= '  <div class="bkit-ai-btn-text">';
            $output .= '    <span class="bkit-ai-btn-title">' . esc_html( $args['copilot_text'] ) . '</span>';
            if ( ! empty( $args['copilot_sub'] ) ) {
                $output .= '    <span class="bkit-ai-btn-desc">' . esc_html( $args['copilot_sub'] ) . '</span>';
            }
            $output .= '  </div>';
            $output .= '  <span class="bkit-ai-btn-arrow">&rarr;</span>';
            $output .= '</a>';
        }
    } else {
        // Direct ready-to-use buttons (compact / pills / standard)
        if ( $args['show_chatgpt'] ) {
            $output .= '<a href="' . esc_url( $urls['chatgpt'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-chatgpt" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="chatgpt" title="Hỏi ChatGPT: ' . esc_attr( $urls['title'] ) . '">';
            $output .= '  ' . bkit_get_ai_icon( 'chatgpt' );
            $output .= '  <span class="bkit-ai-btn-label">' . esc_html( $args['chatgpt_text'] ) . '</span>';
            $output .= '</a>';
        }

        if ( $args['show_claude'] ) {
            $output .= '<a href="' . esc_url( $urls['claude'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-claude" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="claude" title="Hỏi Claude: ' . esc_attr( $urls['title'] ) . '">';
            $output .= '  ' . bkit_get_ai_icon( 'claude' );
            $output .= '  <span class="bkit-ai-btn-label">' . esc_html( $args['claude_text'] ) . '</span>';
            $output .= '</a>';
        }

        if ( $args['show_gemini'] ) {
            $output .= '<a href="' . esc_url( $urls['gemini'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-gemini" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="gemini" title="Hỏi Gemini: ' . esc_attr( $urls['title'] ) . '">';
            $output .= '  ' . bkit_get_ai_icon( 'gemini' );
            $output .= '  <span class="bkit-ai-btn-label">' . esc_html( $args['gemini_text'] ) . '</span>';
            $output .= '</a>';
        }

        if ( $args['show_copilot'] ) {
            $output .= '<a href="' . esc_url( $urls['copilot'] ) . '" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-copilot" data-ai-prompt="' . esc_attr( $urls['prompt'] ) . '" data-ai-service="copilot" title="Hỏi Copilot: ' . esc_attr( $urls['title'] ) . '">';
            $output .= '  ' . bkit_get_ai_icon( 'copilot' );
            $output .= '  <span class="bkit-ai-btn-label">' . esc_html( $args['copilot_text'] ) . '</span>';
            $output .= '</a>';
        }
    }

    $output .= '</div>';

    return $output;
}

/**
 * Render Side Posts Widget with AI buttons and recent/related posts.
 *
 * @param int $current_post_id Current post ID to exclude.
 * @param int $limit Number of posts to show.
 * @return string HTML output.
 */
function bkit_render_side_posts_sidebar( $current_post_id = 0, $limit = 5 ) {
    $current_post_id = $current_post_id ? $current_post_id : get_the_ID();

    $html  = '<aside class="bkit-post-sidebar">';
    
    // Khối 1: Hộp công cụ Hỏi AI trực tiếp cho bài viết hiện tại (ChatGPT, Claude, Gemini, Copilot)
    $html .= '<div class="bkit-sidebar-widget bkit-ai-widget">';
    $html .= '  <div class="bkit-widget-header">';
    $html .= '    <h3 class="bkit-widget-title">';
    $html .= '      <span class="bkit-widget-badge">Trợ lý AI</span>';
    $html .= '      Hỏi AI về bài viết';
    $html .= '    </h3>';
    $html .= '    <p class="bkit-widget-desc">Gửi trực tiếp liên kết bài viết sang AI để hỏi đáp, tra cứu hoặc giải thích chi tiết:</p>';
    $html .= '  </div>';

    $html .= bkit_render_ai_action_buttons( array(
        'post_id'       => $current_post_id,
        'style'         => 'sidebar',
        'chatgpt_text'  => 'ChatGPT',
        'claude_text'   => 'Claude',
        'gemini_text'   => 'Gemini',
        'copilot_text'  => 'Copilot',
        'chatgpt_sub'   => 'Hỏi đáp & phân tích nội dung',
        'claude_sub'    => 'Tóm tắt & nghiên cứu sâu',
        'gemini_sub'    => 'Tra cứu & mở rộng thông tin',
        'copilot_sub'   => 'Tìm kiếm & giải đáp thông minh',
        'custom_prompt' => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
    ) );

    $html .= '</div>';

    // Khối 2: Bài viết mới nhất (Side posts)
    $args = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => $limit,
        'post__not_in'        => $current_post_id ? array( $current_post_id ) : array(),
        'ignore_sticky_posts' => 1,
    );

    $side_query = new WP_Query( $args );

    if ( $side_query->have_posts() ) {
        $html .= '<div class="bkit-sidebar-widget bkit-recent-posts-widget">';
        $html .= '  <div class="bkit-widget-header">';
        $html .= '    <h3 class="bkit-widget-title">Bài viết liên quan & mới nhất</h3>';
        $html .= '  </div>';
        $html .= '  <ul class="bkit-side-posts-list">';

        while ( $side_query->have_posts() ) {
            $side_query->the_post();
            $post_id   = get_the_ID();
            $permalink = get_permalink();
            $title     = get_the_title();
            $date      = get_the_date( 'd/m/Y' );

            $html .= '<li class="bkit-side-post-item">';
            
            if ( has_post_thumbnail( $post_id ) ) {
                $html .= '<div class="bkit-side-post-thumb">';
                $html .= '  <a href="' . esc_url( $permalink ) . '">';
                $html .= get_the_post_thumbnail( $post_id, 'thumbnail' );
                $html .= '  </a>';
                $html .= '</div>';
            }

            $html .= '<div class="bkit-side-post-content">';
            $html .= '  <h4 class="bkit-side-post-title">';
            $html .= '    <a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a>';
            $html .= '  </h4>';
            $html .= '  <div class="bkit-side-post-meta">';
            $html .= '    <span class="bkit-side-post-date">' . esc_html( $date ) . '</span>';
            $html .= '  </div>';
            $html .= '</div>';

            $html .= '</li>';
        }
        wp_reset_postdata();

        $html .= '  </ul>';
        $html .= '</div>';
    }

    $html .= '</aside>';

    return $html;
}

/**
 * Shortcode: [ai_ask_buttons style="buttons|pills|sidebar|cards"]
 */
function bkit_ai_ask_buttons_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'post_id'       => 0,
            'style'         => 'buttons',
            'chatgpt_text'  => 'ChatGPT',
            'claude_text'   => 'Claude',
            'gemini_text'   => 'Gemini',
            'copilot_text'  => 'Copilot',
            'chatgpt_sub'   => 'Hỏi đáp & phân tích',
            'claude_sub'    => 'Tóm tắt & mở rộng',
            'gemini_sub'    => 'Tra cứu & mở rộng',
            'copilot_sub'   => 'Tìm kiếm thông minh',
            'custom_prompt' => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
            'class'         => '',
        ),
        $atts,
        'ai_ask_buttons'
    );

    if ( empty( $atts['post_id'] ) ) {
        $atts['post_id'] = get_the_ID();
    }

    return bkit_render_ai_action_buttons( $atts );
}
add_shortcode( 'ai_ask_buttons', 'bkit_ai_ask_buttons_shortcode' );
add_shortcode( 'ai_buttons', 'bkit_ai_ask_buttons_shortcode' );

/**
 * Shortcode: [chatgpt_button text="ChatGPT" prompt="..."]
 */
function bkit_chatgpt_button_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'post_id' => 0,
            'text'    => 'ChatGPT',
            'prompt'  => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
            'class'   => '',
        ),
        $atts,
        'chatgpt_button'
    );

    $post_id = ! empty( $atts['post_id'] ) ? $atts['post_id'] : get_the_ID();
    $urls    = bkit_get_ai_prompt_urls( $post_id, $atts['prompt'] );

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-chatgpt %s" data-ai-prompt="%s" data-ai-service="chatgpt" title="Hỏi ChatGPT: %s">%s <span class="bkit-ai-btn-label">%s</span></a>',
        esc_url( $urls['chatgpt'] ),
        esc_attr( $atts['class'] ),
        esc_attr( $urls['prompt'] ),
        esc_attr( $urls['title'] ),
        bkit_get_ai_icon( 'chatgpt' ),
        esc_html( $atts['text'] )
    );
}
add_shortcode( 'chatgpt_button', 'bkit_chatgpt_button_shortcode' );

/**
 * Shortcode: [claude_button text="Claude" prompt="..."]
 */
function bkit_claude_button_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'post_id' => 0,
            'text'    => 'Claude',
            'prompt'  => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
            'class'   => '',
        ),
        $atts,
        'claude_button'
    );

    $post_id = ! empty( $atts['post_id'] ) ? $atts['post_id'] : get_the_ID();
    $urls    = bkit_get_ai_prompt_urls( $post_id, $atts['prompt'] );

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-claude %s" data-ai-prompt="%s" data-ai-service="claude" title="Hỏi Claude: %s">%s <span class="bkit-ai-btn-label">%s</span></a>',
        esc_url( $urls['claude'] ),
        esc_attr( $atts['class'] ),
        esc_attr( $urls['prompt'] ),
        esc_attr( $urls['title'] ),
        bkit_get_ai_icon( 'claude' ),
        esc_html( $atts['text'] )
    );
}
add_shortcode( 'claude_button', 'bkit_claude_button_shortcode' );

/**
 * Shortcode: [gemini_button text="Gemini" prompt="..."]
 */
function bkit_gemini_button_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'post_id' => 0,
            'text'    => 'Gemini',
            'prompt'  => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
            'class'   => '',
        ),
        $atts,
        'gemini_button'
    );

    $post_id = ! empty( $atts['post_id'] ) ? $atts['post_id'] : get_the_ID();
    $urls    = bkit_get_ai_prompt_urls( $post_id, $atts['prompt'] );

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-gemini %s" data-ai-prompt="%s" data-ai-service="gemini" title="Hỏi Gemini: %s">%s <span class="bkit-ai-btn-label">%s</span></a>',
        esc_url( $urls['gemini'] ),
        esc_attr( $atts['class'] ),
        esc_attr( $urls['prompt'] ),
        esc_attr( $urls['title'] ),
        bkit_get_ai_icon( 'gemini' ),
        esc_html( $atts['text'] )
    );
}
add_shortcode( 'gemini_button', 'bkit_gemini_button_shortcode' );

/**
 * Shortcode: [copilot_button text="Copilot" prompt="..."]
 */
function bkit_copilot_button_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'post_id' => 0,
            'text'    => 'Copilot',
            'prompt'  => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
            'class'   => '',
        ),
        $atts,
        'copilot_button'
    );

    $post_id = ! empty( $atts['post_id'] ) ? $atts['post_id'] : get_the_ID();
    $urls    = bkit_get_ai_prompt_urls( $post_id, $atts['prompt'] );

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer" class="bkit-ai-btn bkit-ai-copilot %s" data-ai-prompt="%s" data-ai-service="copilot" title="Hỏi Copilot: %s">%s <span class="bkit-ai-btn-label">%s</span></a>',
        esc_url( $urls['copilot'] ),
        esc_attr( $atts['class'] ),
        esc_attr( $urls['prompt'] ),
        esc_attr( $urls['title'] ),
        bkit_get_ai_icon( 'copilot' ),
        esc_html( $atts['text'] )
    );
}
add_shortcode( 'copilot_button', 'bkit_copilot_button_shortcode' );

/**
 * AI Assistant Clipboard Copier & Toast Notification Script.
 * Ensures the prompt is copied to clipboard whenever any AI button is clicked,
 * providing instant Ctrl+V pasting into Gemini/Copilot/Claude/ChatGPT.
 */
function bkit_ai_buttons_footer_script() {
    ?>
    <div id="bkit-ai-toast" class="bkit-ai-toast" aria-live="polite">
        <span class="bkit-toast-icon">📋</span>
        <span class="bkit-toast-msg"></span>
    </div>
    <script>
    (function() {
        var toast = document.getElementById('bkit-ai-toast');
        var toastTimer = null;

        function showToast(msg) {
            if (!toast) return;
            var msgEl = toast.querySelector('.bkit-toast-msg');
            if (msgEl) msgEl.textContent = msg;
            toast.classList.add('bkit-toast-visible');
            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(function() {
                toast.classList.remove('bkit-toast-visible');
            }, 3500);
        }

        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function(resolve, reject) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                ta.style.top = '-9999px';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try {
                    document.execCommand('copy') ? resolve() : reject();
                } catch (e) {
                    reject(e);
                } finally {
                    ta.remove();
                }
            });
        }

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.bkit-ai-btn');
            if (!btn) return;

            var prompt = btn.getAttribute('data-ai-prompt');
            var service = btn.getAttribute('data-ai-service') || 'AI';
            if (prompt) {
                copyText(prompt).then(function() {
                    var sName = service.charAt(0).toUpperCase() + service.slice(1);
                    if (service === 'gemini' || service === 'copilot') {
                        showToast('Đã sao chép prompt! Nhấn Ctrl+V để dán vào ' + sName);
                    } else {
                        showToast('Đã mở ' + sName + ' & sao chép prompt vào bộ nhớ tạm');
                    }
                }).catch(function() {
                    // Ignore clipboard error
                });
            }
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'bkit_ai_buttons_footer_script' );

