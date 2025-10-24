<?php
/**
 * The template for displaying content in the index.php template.
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'col-lg-4 col-md-6 mb-4' ); ?>>
	<div class="card blog-card h-100 shadow-sm">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="card-img-top-container">
				<?php 
				$thumbnail_id = get_post_thumbnail_id();
				$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'medium_large' );
				?>
				<a href="<?php the_permalink(); ?>" class="post-thumbnail-link">
					<div class="post-thumbnail" style="background-image: url('<?php echo esc_url( $thumbnail_url ); ?>');">
						<div class="thumbnail-overlay">
							<i class="fas fa-eye"></i>
						</div>
					</div>
				</a>
			</div>
		<?php endif; ?>
		
		<div class="card-body d-flex flex-column">
			<header class="card-header">
				<h3 class="card-title">
					<a href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'f2fdashboard' ), the_title_attribute( array( 'echo' => false ) ) ); ?>" rel="bookmark"><?php the_title(); ?></a>
				</h3>
				<?php if ( 'post' === get_post_type() ) : ?>
					<div class="entry-meta">
						<?php
							f2fdashboard_article_posted_on();

							$num_comments = get_comments_number();
							if ( comments_open() && $num_comments >= 1 ) :
								echo ' <a href="' . esc_url( get_comments_link() ) . '" class="badge bg-primary ms-2" title="' . esc_attr( sprintf( _n( '%s Comment', '%s Comments', $num_comments, 'f2fdashboard' ), $num_comments ) ) . '">' . $num_comments . '</a>';
							endif;
						?>
					</div>
				<?php endif; ?>
			</header>
			
			<div class="card-text entry-content flex-grow-1">
				<?php
					if ( is_search() ) {
						the_excerpt();
					} else {
						the_excerpt();
					}
				?>
			</div>
			
			<footer class="card-footer bg-transparent border-0 mt-auto">
				<a href="<?php the_permalink(); ?>" class="btn btn-primary btn-sm">
					<?php esc_html_e( 'Leia mais', 'f2fdashboard' ); ?>
					<i class="fas fa-arrow-right ms-1"></i>
				</a>
			</footer>
		</div>
	</div>
</article>
