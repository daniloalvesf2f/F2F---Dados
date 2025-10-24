<?php
/**
 * Template Name: Page (Default)
 * Description: Page template with Sidebar on the left side.
 *
 */

get_header();

the_post();
?>
<?php if ( has_post_thumbnail() ) : ?>
	<div class="page-hero">
		<?php 
		$thumbnail_id = get_post_thumbnail_id();
		$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
		?>
		<div class="hero-image" style="background-image: url('<?php echo esc_url( $thumbnail_url ); ?>');">
			<div class="hero-overlay">
				<div class="container">
					<div class="hero-content text-center">
						<h1 class="page-title"><?php the_title(); ?></h1>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<div class="container my-5">
	<div class="row">
		<div class="col-md-8 order-md-2 col-sm-12">
			<div id="post-<?php the_ID(); ?>" <?php post_class( 'content page-content' ); ?>>
				<?php if ( !has_post_thumbnail() ) : ?>
					<h1 class="entry-title"><?php the_title(); ?></h1>
				<?php endif; ?>
				<?php
					the_content();

					wp_link_pages(
						array(
							'before'   => '<nav class="page-links" aria-label="' . esc_attr__( 'Page', 'f2fdashboard' ) . '">',
							'after'    => '</nav>',
							'pagelink' => esc_html__( 'Page %', 'f2fdashboard' ),
						)
					);
					edit_post_link(
						esc_attr__( 'Edit', 'f2fdashboard' ),
						'<span class="edit-link">',
						'</span>'
					);
				?>
			</div><!-- /#post-<?php the_ID(); ?> -->
		<?php
			// If comments are open or we have at least one comment, load up the comment template.
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		?>
	</div><!-- /.col -->
	<?php
		get_sidebar();
	?>
</div><!-- /.row -->
<?php
get_footer();
