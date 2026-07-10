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
    <button type="submit" class="search-submit">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="search-icon">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <span class="screen-reader-text"><?php echo esc_html_x( 'Tìm kiếm', 'submit button', 'bkit' ); ?></span>
    </button>
</form>
