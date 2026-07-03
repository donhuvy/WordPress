<?php
/**
 * The template for displaying search results pages
 */

get_header();
?>

<main id="primary" class="site-main">
    <header class="page-header post-card" style="margin-bottom: 2rem;">
        <h1 class="page-title">
            <?php
            /* translators: %s: search query. */
            printf( esc_html__( 'Kết quả tìm kiếm cho: %s', 'bkit' ), '<span style="font-size: 50%;">' . get_search_query() . '</span>' );
            ?>
        </h1>
    </header>

    <?php
    if ( have_posts() ) :
        while ( have_posts() ) :
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="post-thumbnail">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail( 'large' ); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="post-content-wrapper">
                    <header class="entry-header">
                        <h2 class="post-title">
                            <a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a>
                        </h2>
                        <div class="post-meta">
                            <span class="posted-on">Đăng ngày: <?php echo get_the_date('d/m/Y H:i:s'); ?> | Cập nhật: <?php echo get_the_modified_date('d/m/Y H:i:s'); ?></span>
                            <span class="author-meta"> | <?php the_author(); ?></span>
                        </div>
                    </header>

                    <div class="entry-content">
                        <?php the_excerpt(); ?>
                    </div>

                    <?php if ( has_tag() ) : ?>
                        <div class="post-tags">
                            <?php the_tags( '', ' ', '' ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="entry-footer" style="margin-top: 1.5rem;">
                        <a href="<?php the_permalink(); ?>" class="btn-flat">Xem thêm</a>
                    </div>
                </div>
            </article>
            <?php
        endwhile;

        the_posts_pagination(
            array(
                'prev_text'          => 'Trang trước',
                'next_text'          => 'Trang sau',
                'screen_reader_text' => 'Điều hướng bài viết',
            )
        );

    else :
        ?>
        <section class="no-results not-found post-card">
            <h2 class="post-title">Không tìm thấy kết quả</h2>
            <p>Rất tiếc, không có bài viết nào khớp với từ khóa tìm kiếm của bạn. Vui lòng thử lại với từ khóa khác.</p>
            <div style="margin-top: 1.5rem; max-width: 500px;">
                <?php get_search_form(); ?>
            </div>
        </section>
        <?php
    endif;
    ?>
</main>

<?php
get_footer();
