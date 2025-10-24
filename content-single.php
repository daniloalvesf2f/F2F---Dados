<?php
/**
 * The template for displaying content in the single.php template.
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('single-post-article'); ?>>
	<header class="entry-header mb-4">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="post-thumbnail mb-3">
				<?php the_post_thumbnail( 'large', array( 'class' => 'img-fluid rounded shadow' ) ); ?>
			</div>
		<?php endif; ?>
		
		<h1 class="entry-title"><?php the_title(); ?></h1>
		
		<?php if ( 'post' === get_post_type() ) : ?>
			<div class="entry-meta">
				<?php f2fdashboard_article_posted_on(); ?>
			</div>
		<?php endif; ?>
	</header>

	<div class="entry-content">
		<?php
		the_content();

		wp_link_pages(
			array(
				'before' => '<div class="page-link"><span>' . esc_html__( 'Pages:', 'f2fdashboard' ) . '</span>',
				'after'  => '</div>',
			)
		);
		?>
	</div><!-- /.entry-content -->

	<?php
		edit_post_link( __( 'Edit', 'f2fdashboard' ), '<span class="edit-link">', '</span>' );
	?>

	<footer class="entry-meta">
		<hr>
		<?php
			/* translators: used between list items, there is a space after the comma */
			$category_list = get_the_category_list( __( ', ', 'f2fdashboard' ) );

			/* translators: used between list items, there is a space after the comma */
			$tag_list = get_the_tag_list( '', __( ', ', 'f2fdashboard' ) );
		if ( '' !== $tag_list ) {
			$utility_text = __( 'This entry was posted in %1$s and tagged %2$s by <a href="%6$s">%5$s</a>. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'f2fdashboard' );
		} elseif ( '' !== $category_list ) {
			$utility_text = __( 'This entry was posted in %1$s by <a href="%6$s">%5$s</a>. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'f2fdashboard' );
		} else {
			$utility_text = __( 'This entry was posted by <a href="%6$s">%5$s</a>. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'f2fdashboard' );
		}

			printf(
				$utility_text,
				$category_list,
				$tag_list,
				esc_url( get_permalink() ),
				the_title_attribute( array( 'echo' => false ) ),
				get_the_author(),
				esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) )
			);
			?>
		<hr>
		<?php
			get_template_part( 'author', 'bio' );
		?>
	</footer><!-- /.entry-meta -->
</article><!-- /#post-<?php the_ID(); ?> -->
