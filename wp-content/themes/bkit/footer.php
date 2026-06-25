<footer id="colophon" class="site-footer">
    <div class="container">
        <div class="footer-info">
            <?php 
            $logo_url = get_template_directory_uri() . '/images/bkit.png';
            ?>
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo( 'name' ); ?> Logo" class="site-logo" style="height: 35px;">
            <span class="site-title" style="color: #ff0000 !important; font-weight: 800; font-size: 1.25rem;"><?php bloginfo( 'name' ); ?></span>
        </div>
        <div class="footer-copyright">
            <p>&copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
