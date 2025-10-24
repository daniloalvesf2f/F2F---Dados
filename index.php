<?php
/**
 * Template Name: Blog Index
 * Description: The template for displaying the Blog index /blog.
 *
 */

get_header();

$page_id = get_option( 'page_for_posts' );
?>
<div class="blog-header">
	<div class="container">
		<div class="row">
			<div class="col-md-12 text-center">
				<h1 class="blog-title">Blog</h1>
				<p class="blog-description">Últimas notícias e atualizações</p>
			</div>
		</div>
	</div>
</div>

<div class="container my-5">
	<div class="row">
		<div class="col-md-12">
			<?php
				echo apply_filters( 'the_content', get_post_field( 'post_content', $page_id ) );

				edit_post_link( __( 'Edit', 'f2fdashboard' ), '<span class="edit-link">', '</span>', $page_id );
			?>
		</div><!-- /.col -->
		<div class="col-md-12">
			<?php
				get_template_part( 'archive', 'loop' );
			?>
		</div><!-- /.col -->
	</div><!-- /.row -->
</div>
<?php
get_footer();
