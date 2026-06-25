<?php
get_header();
?>

<main id="primary" class="site-main">
    <?php
    while ( have_posts() ) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
            <header class="entry-header">
                <h1 class="post-title"><?php the_title(); ?></h1>
                <div class="post-meta">
                    <span class="posted-on"><?php the_date(); ?></span>
                    <span class="author-meta"> | <?php the_author(); ?></span>
                </div>
            </header>

            <div class="entry-content">
                <?php
                the_content();
                ?>
            </div>
        </article>
        <?php
        
        // If comments are open or we have at least one comment, load up the comment template.
        if ( comments_open() || get_comments_number() ) :
            comments_template();
        endif;

    endwhile;
    ?>
</main>

<?php
get_footer();
