<?php
get_header();
?>

<main id="primary" class="site-main site-main-single">
    <div class="bkit-single-layout">
        <div class="bkit-single-main">
            <?php
            while ( have_posts() ) :
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card bkit-single-card' ); ?>>
                    <header class="entry-header">
                        <h1 class="post-title"><?php the_title(); ?></h1>
                        <div class="post-meta">
                            <span class="posted-on">Đăng ngày: <span class="post-date"><?php echo get_the_date('d/m/Y H:i:s'); ?></span> | Cập nhật: <span class="post-date"><?php echo get_the_modified_date('d/m/Y H:i:s'); ?></span></span>
                            <span class="author-meta"> | <?php the_author(); ?></span>
                        </div>

                        <!-- Top Quick AI Ask Action Buttons -->
                        <?php if ( function_exists( 'bkit_render_ai_action_buttons' ) ) : ?>
                            <div class="bkit-post-quick-ai">
                                <span class="bkit-quick-ai-label">Hỏi AI nhanh:</span>
                                <?php echo bkit_render_ai_action_buttons( array(
                                    'post_id'       => get_the_ID(),
                                    'style'         => 'pills',
                                    'chatgpt_text'  => 'ChatGPT',
                                    'claude_text'   => 'Claude',
                                    'custom_prompt' => 'Tôi muốn hỏi câu hỏi liên quan đến chủ đề này.',
                                ) ); ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <div class="entry-content">
                        <?php
                        the_content();
                        ?>
                    </div>

                    <?php 
                    if ( function_exists( 'bkit_google_preferred_source_cta' ) ) {
                        echo bkit_google_preferred_source_cta( 'banner' );
                    }
                    ?>

                    <?php if ( has_tag() ) : ?>
                        <div class="post-tags">
                            <?php the_tags( '', ' ', '' ); ?>
                        </div>
                    <?php endif; ?>
                </article>
                <?php
                
                // If comments are open or we have at least one comment, load up the comment template.
                if ( comments_open() || get_comments_number() ) :
                    comments_template();
                endif;

            endwhile;
            ?>
        </div>

        <!-- Side Posts & AI Tools Column -->
        <div class="bkit-single-sidebar">
            <?php
            if ( function_exists( 'bkit_render_side_posts_sidebar' ) ) {
                echo bkit_render_side_posts_sidebar( get_the_ID(), 6 );
            }
            ?>
        </div>
    </div>
</main>

<?php
get_footer();
