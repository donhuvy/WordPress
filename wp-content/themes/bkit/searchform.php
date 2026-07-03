<?php
/**
 * Template for displaying search forms in BKIT theme
 */
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
    <label>
        <span class="screen-reader-text"><?php echo _x( 'Tìm kiếm cho:', 'label', 'bkit' ); ?></span>
        <input type="search" class="search-field" placeholder="<?php echo esc_attr_x( 'Tìm kiếm bài viết &hellip;', 'placeholder', 'bkit' ); ?>" value="<?php echo get_search_query(); ?>" name="s" required />
    </label>
    <button type="submit" class="search-submit"><?php echo esc_html_x( 'Tìm kiếm', 'submit button', 'bkit' ); ?></button>
</form>
