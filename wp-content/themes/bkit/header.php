<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
    <style>
        /* Force Arial font last to override any third-party or dynamic CSS */
        *, html, body, p, span, a, h1, h2, h3, h4, h5, h6, li, td, th, label, input, textarea, select, button, 
        .post-title, .post-title a, .entry-title, .entry-title a,
        .submit, #submit, input[type="submit"] {
            font-family: Arial, sans-serif !important;
        }
    </style>
</head>
<body <?php body_class('theme-bkit'); ?>>
<?php wp_body_open(); ?>

<header id="masthead" class="site-header">
    <div class="container">
        <div class="site-branding">
            <?php 
            $logo_url = get_template_directory_uri() . '/images/bkit.png';
            ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="site-branding">
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo( 'name' ); ?> Logo" class="site-logo">
                <span class="site-title"><?php bloginfo( 'name' ); ?></span>
            </a>
        </div>

        <nav id="site-navigation" class="main-navigation">
            <?php
            wp_nav_menu(
                array(
                    'theme_location' => 'menu-1',
                    'menu_id'        => 'primary-menu',
                    'fallback_cb'    => 'wp_page_menu',
                )
            );
            ?>
        </nav>
    </div>
</header>
