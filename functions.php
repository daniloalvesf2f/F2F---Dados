<?php

/**
 * Include Theme Customizer.
 *
 * @since v1.0
 */
$theme_customizer = __DIR__ . '/inc/customizer.php';
if ( is_readable( $theme_customizer ) ) {
	require_once $theme_customizer;
}

if ( ! function_exists( 'f2fdashboard_setup_theme' ) ) {
	/**
	 * General Theme Settings.
	 *
	 * @since v1.0
	 *
	 * @return void
	 */
	function f2fdashboard_setup_theme() {
		// Make theme available for translation: Translations can be filed in the /languages/ directory.
		load_theme_textdomain( 'f2fdashboard', __DIR__ . '/languages' );

		/**
		 * Set the content width based on the theme's design and stylesheet.
		 *
		 * @since v1.0
		 */
		global $content_width;
		if ( ! isset( $content_width ) ) {
			$content_width = 800;
		}

		// Theme Support.
		add_theme_support( 'title-tag' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'script',
				'style',
				'navigation-widgets',
			)
		);

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );
		// Add support for full and wide alignment.
		add_theme_support( 'align-wide' );
		// Add support for Editor Styles.
		add_theme_support( 'editor-styles' );
		// Enqueue Editor Styles.
		add_editor_style( 'style-editor.css' );

		// Default attachment display settings.
		update_option( 'image_default_align', 'none' );
		update_option( 'image_default_link_type', 'none' );
		update_option( 'image_default_size', 'large' );

		// Custom CSS styles of WorPress gallery.
		add_filter( 'use_default_gallery_style', '__return_false' );
	}
	add_action( 'after_setup_theme', 'f2fdashboard_setup_theme' );

	/**
	 * Enqueue editor stylesheet (for iframed Post Editor):
	 * https://make.wordpress.org/core/2023/07/18/miscellaneous-editor-changes-in-wordpress-6-3/#post-editor-iframed
	 *
	 * @since v3.5.1
	 *
	 * @return void
	 */
	function f2fdashboard_load_editor_styles() {
		if ( is_admin() ) {
			wp_enqueue_style( 'editor-style', get_theme_file_uri( 'style-editor.css' ) );
		}
	}
	add_action( 'enqueue_block_assets', 'f2fdashboard_load_editor_styles' );

	// Disable Block Directory: https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/filters/editor-filters.md#block-directory
	remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
	remove_action( 'enqueue_block_editor_assets', 'gutenberg_enqueue_block_editor_assets_block_directory' );
}

if ( ! function_exists( 'wp_body_open' ) ) {
	/**
	 * Fire the wp_body_open action.
	 *
	 * Added for backwards compatibility to support pre 5.2.0 WordPress versions.
	 *
	 * @since v2.2
	 *
	 * @return void
	 */
	function wp_body_open() {
		do_action( 'wp_body_open' );
	}
}

if ( ! function_exists( 'f2fdashboard_add_user_fields' ) ) {
	/**
	 * Add new User fields to Userprofile:
	 * get_user_meta( $user->ID, 'facebook_profile', true );
	 *
	 * @since v1.0
	 *
	 * @param array $fields User fields.
	 *
	 * @return array
	 */
	function f2fdashboard_add_user_fields( $fields ) {
		// Add new fields.
		$fields['facebook_profile'] = 'Facebook URL';
		$fields['twitter_profile']  = 'Twitter URL';
		$fields['linkedin_profile'] = 'LinkedIn URL';
		$fields['xing_profile']     = 'Xing URL';
		$fields['github_profile']   = 'GitHub URL';

		return $fields;
	}
	add_filter( 'user_contactmethods', 'f2fdashboard_add_user_fields' );
}

/**
 * Test if a page is a blog page.
 * if ( is_blog() ) { ... }
 *
 * @since v1.0
 *
 * @global WP_Post $post Global post object.
 *
 * @return bool
 */
function is_blog() {
	global $post;
	$posttype = get_post_type( $post );

	return ( ( is_archive() || is_author() || is_category() || is_home() || is_single() || ( is_tag() && ( 'post' === $posttype ) ) ) ? true : false );
}

/**
 * Disable comments for Media (Image-Post, Jetpack-Carousel, etc.)
 *
 * @since v1.0
 *
 * @param bool $open    Comments open/closed.
 * @param int  $post_id Post ID.
 *
 * @return bool
 */
function f2fdashboard_filter_media_comment_status( $open, $post_id = null ) {
	$media_post = get_post( $post_id );

	if ( 'attachment' === $media_post->post_type ) {
		return false;
	}

	return $open;
}
add_filter( 'comments_open', 'f2fdashboard_filter_media_comment_status', 10, 2 );

/**
 * Style Edit buttons as badges: https://getbootstrap.com/docs/5.0/components/badge
 *
 * @since v1.0
 *
 * @param string $link Post Edit Link.
 *
 * @return string
 */
function f2fdashboard_custom_edit_post_link( $link ) {
	return str_replace( 'class="post-edit-link"', 'class="post-edit-link badge bg-secondary"', $link );
}
add_filter( 'edit_post_link', 'f2fdashboard_custom_edit_post_link' );

/**
 * Style Edit buttons as badges: https://getbootstrap.com/docs/5.0/components/badge
 *
 * @since v1.0
 *
 * @param string $link Comment Edit Link.
 */
function f2fdashboard_custom_edit_comment_link( $link ) {
	return str_replace( 'class="comment-edit-link"', 'class="comment-edit-link badge bg-secondary"', $link );
}
add_filter( 'edit_comment_link', 'f2fdashboard_custom_edit_comment_link' );

/**
 * Responsive oEmbed filter: https://getbootstrap.com/docs/5.0/helpers/ratio
 *
 * @since v1.0
 *
 * @param string $html Inner HTML.
 *
 * @return string
 */
function f2fdashboard_oembed_filter( $html ) {
	return '<div class="ratio ratio-16x9">' . $html . '</div>';
}
add_filter( 'embed_oembed_html', 'f2fdashboard_oembed_filter', 10 );

if ( ! function_exists( 'f2fdashboard_content_nav' ) ) {
	/**
	 * Display a navigation to next/previous pages when applicable.
	 *
	 * @since v1.0
	 *
	 * @param string $nav_id Navigation ID.
	 */
	function f2fdashboard_content_nav( $nav_id ) {
		global $wp_query;

		if ( $wp_query->max_num_pages > 1 ) {
			?>
			<div id="<?php echo esc_attr( $nav_id ); ?>" class="d-flex mb-4 justify-content-between">
				<div><?php next_posts_link( '<span aria-hidden="true">&larr;</span> ' . esc_html__( 'Older posts', 'f2fdashboard' ) ); ?></div>
				<div><?php previous_posts_link( esc_html__( 'Newer posts', 'f2fdashboard' ) . ' <span aria-hidden="true">&rarr;</span>' ); ?></div>
			</div><!-- /.d-flex -->
			<?php
		} else {
			echo '<div class="clearfix"></div>';
		}
	}

	/**
	 * Add Class.
	 *
	 * @since v1.0
	 *
	 * @return string
	 */
	function posts_link_attributes() {
		return 'class="btn btn-secondary btn-lg"';
	}
	add_filter( 'next_posts_link_attributes', 'posts_link_attributes' );
	add_filter( 'previous_posts_link_attributes', 'posts_link_attributes' );
}

/**
 * Init Widget areas in Sidebar.
 *
 * @since v1.0
 *
 * @return void
 */
function f2fdashboard_widgets_init() {
	// Area 1.
	register_sidebar(
		array(
			'name'          => 'Primary Widget Area (Sidebar)',
			'id'            => 'primary_widget_area',
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	// Area 2.
	register_sidebar(
		array(
			'name'          => 'Secondary Widget Area (Header Navigation)',
			'id'            => 'secondary_widget_area',
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	// Area 3.
	register_sidebar(
		array(
			'name'          => 'Third Widget Area (Footer)',
			'id'            => 'third_widget_area',
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'f2fdashboard_widgets_init' );

if ( ! function_exists( 'f2fdashboard_article_posted_on' ) ) {
	/**
	 * "Theme posted on" pattern.
	 *
	 * @since v1.0
	 */
	function f2fdashboard_article_posted_on() {
		printf(
			wp_kses_post( __( '<span class="sep">Posted on </span><a href="%1$s" title="%2$s" rel="bookmark"><time class="entry-date" datetime="%3$s">%4$s</time></a><span class="by-author"> <span class="sep"> by </span> <span class="author-meta vcard"><a class="url fn n" href="%5$s" title="%6$s" rel="author">%7$s</a></span></span>', 'f2fdashboard' ) ),
			esc_url( get_permalink() ),
			esc_attr( get_the_date() . ' - ' . get_the_time() ),
			esc_attr( get_the_date( 'c' ) ),
			esc_html( get_the_date() . ' - ' . get_the_time() ),
			esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) ),
			sprintf( esc_attr__( 'View all posts by %s', 'f2fdashboard' ), get_the_author() ),
			get_the_author()
		);
	}
}

/**
 * Template for Password protected post form.
 *
 * @since v1.0
 *
 * @global WP_Post $post Global post object.
 *
 * @return string
 */
function f2fdashboard_password_form() {
	global $post;
	$label = 'pwbox-' . ( empty( $post->ID ) ? wp_rand() : $post->ID );

	$output                  = '<div class="row">';
		$output             .= '<form action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" method="post">';
		$output             .= '<h4 class="col-md-12 alert alert-warning">' . esc_html__( 'This content is password protected. To view it please enter your password below.', 'f2fdashboard' ) . '</h4>';
			$output         .= '<div class="col-md-6">';
				$output     .= '<div class="input-group">';
					$output .= '<input type="password" name="post_password" id="' . esc_attr( $label ) . '" placeholder="' . esc_attr__( 'Password', 'f2fdashboard' ) . '" class="form-control" />';
					$output .= '<div class="input-group-append"><input type="submit" name="submit" class="btn btn-primary" value="' . esc_attr__( 'Submit', 'f2fdashboard' ) . '" /></div>';
				$output     .= '</div><!-- /.input-group -->';
			$output         .= '</div><!-- /.col -->';
		$output             .= '</form>';
	$output                 .= '</div><!-- /.row -->';

	return $output;
}
add_filter( 'the_password_form', 'f2fdashboard_password_form' );


if ( ! function_exists( 'f2fdashboard_comment' ) ) {
	/**
	 * Style Reply link.
	 *
	 * @since v1.0
	 *
	 * @param string $link Link output.
	 *
	 * @return string
	 */
	function f2fdashboard_replace_reply_link_class( $link ) {
		return str_replace( "class='comment-reply-link", "class='comment-reply-link btn btn-outline-secondary", $link );
	}
	add_filter( 'comment_reply_link', 'f2fdashboard_replace_reply_link_class' );

	/**
	 * Template for comments and pingbacks:
	 * add function to comments.php ... wp_list_comments( array( 'callback' => 'f2fdashboard_comment' ) );
	 *
	 * @since v1.0
	 *
	 * @param object $comment Comment object.
	 * @param array  $args    Comment args.
	 * @param int    $depth   Comment depth.
	 */
	function f2fdashboard_comment( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		switch ( $comment->comment_type ) :
			case 'pingback':
			case 'trackback':
				?>
		<li class="post pingback">
			<p>
				<?php
					esc_html_e( 'Pingback:', 'f2fdashboard' );
					comment_author_link();
					edit_comment_link( esc_html__( 'Edit', 'f2fdashboard' ), '<span class="edit-link">', '</span>' );
				?>
			</p>
				<?php
				break;
			default:
				?>
		<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
			<article id="comment-<?php comment_ID(); ?>" class="comment">
				<footer class="comment-meta">
					<div class="comment-author vcard">
						<?php
							$avatar_size = ( '0' !== $comment->comment_parent ? 68 : 136 );
							echo get_avatar( $comment, $avatar_size );

							/* Translators: 1: Comment author, 2: Date and time */
							printf(
								wp_kses_post( __( '%1$s, %2$s', 'f2fdashboard' ) ),
								sprintf( '<span class="fn">%s</span>', get_comment_author_link() ),
								sprintf(
									'<a href="%1$s"><time datetime="%2$s">%3$s</time></a>',
									esc_url( get_comment_link( $comment->comment_ID ) ),
									get_comment_time( 'c' ),
									/* Translators: 1: Date, 2: Time */
									sprintf( esc_html__( '%1$s ago', 'f2fdashboard' ), human_time_diff( (int) get_comment_time( 'U' ), current_time( 'timestamp' ) ) )
								)
							);

							edit_comment_link( esc_html__( 'Edit', 'f2fdashboard' ), '<span class="edit-link">', '</span>' );
						?>
					</div><!-- .comment-author .vcard -->

					<?php if ( '0' === $comment->comment_approved ) { ?>
						<em class="comment-awaiting-moderation">
							<?php esc_html_e( 'Your comment is awaiting moderation.', 'f2fdashboard' ); ?>
						</em>
						<br />
					<?php } ?>
				</footer>

				<div class="comment-content"><?php comment_text(); ?></div>

				<div class="reply">
					<?php
						comment_reply_link(
							array_merge(
								$args,
								array(
									'reply_text' => esc_html__( 'Reply', 'f2fdashboard' ) . ' <span>&darr;</span>',
									'depth'      => $depth,
									'max_depth'  => $args['max_depth'],
								)
							)
						);
					?>
				</div><!-- /.reply -->
			</article><!-- /#comment-## -->
				<?php
				break;
		endswitch;
	}

	/**
	 * Custom Comment form.
	 *
	 * @since v1.0
	 * @since v1.1: Added 'submit_button' and 'submit_field'
	 * @since v2.0.2: Added '$consent' and 'cookies'
	 *
	 * @param array $args    Form args.
	 * @param int   $post_id Post ID.
	 *
	 * @return array
	 */
	function f2fdashboard_custom_commentform( $args = array(), $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$commenter     = wp_get_current_commenter();
		$user          = wp_get_current_user();
		$user_identity = $user->exists() ? $user->display_name : '';

		$args = wp_parse_args( $args );

		$req      = get_option( 'require_name_email' );
		$aria_req = ( $req ? " aria-required='true' required" : '' );
		$consent  = ( empty( $commenter['comment_author_email'] ) ? '' : ' checked="checked"' );
		$fields   = array(
			'author'  => '<div class="form-floating mb-3">
							<input type="text" id="author" name="author" class="form-control" value="' . esc_attr( $commenter['comment_author'] ) . '" placeholder="' . esc_html__( 'Name', 'f2fdashboard' ) . ( $req ? '*' : '' ) . '"' . $aria_req . ' />
							<label for="author">' . esc_html__( 'Name', 'f2fdashboard' ) . ( $req ? '*' : '' ) . '</label>
						</div>',
			'email'   => '<div class="form-floating mb-3">
							<input type="email" id="email" name="email" class="form-control" value="' . esc_attr( $commenter['comment_author_email'] ) . '" placeholder="' . esc_html__( 'Email', 'f2fdashboard' ) . ( $req ? '*' : '' ) . '"' . $aria_req . ' />
							<label for="email">' . esc_html__( 'Email', 'f2fdashboard' ) . ( $req ? '*' : '' ) . '</label>
						</div>',
			'url'     => '',
			'cookies' => '<p class="form-check mb-3 comment-form-cookies-consent">
							<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" class="form-check-input" type="checkbox" value="yes"' . $consent . ' />
							<label class="form-check-label" for="wp-comment-cookies-consent">' . esc_html__( 'Save my name, email, and website in this browser for the next time I comment.', 'f2fdashboard' ) . '</label>
						</p>',
		);

		$defaults = array(
			'fields'               => apply_filters( 'comment_form_default_fields', $fields ),
			'comment_field'        => '<div class="form-floating mb-3">
											<textarea id="comment" name="comment" class="form-control" aria-required="true" required placeholder="' . esc_attr__( 'Comment', 'f2fdashboard' ) . ( $req ? '*' : '' ) . '"></textarea>
											<label for="comment">' . esc_html__( 'Comment', 'f2fdashboard' ) . '</label>
										</div>',
			/** This filter is documented in wp-includes/link-template.php */
			'must_log_in'          => '<p class="must-log-in">' . sprintf( wp_kses_post( __( 'You must be <a href="%s">logged in</a> to post a comment.', 'f2fdashboard' ) ), wp_login_url( esc_url( get_permalink( get_the_ID() ) ) ) ) . '</p>',
			/** This filter is documented in wp-includes/link-template.php */
			'logged_in_as'         => '<p class="logged-in-as">' . sprintf( wp_kses_post( __( 'Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s" title="Log out of this account">Log out?</a>', 'f2fdashboard' ) ), get_edit_user_link(), $user->display_name, wp_logout_url( apply_filters( 'the_permalink', esc_url( get_permalink( get_the_ID() ) ) ) ) ) . '</p>',
			'comment_notes_before' => '<p class="small comment-notes">' . esc_html__( 'Your Email address will not be published.', 'f2fdashboard' ) . '</p>',
			'comment_notes_after'  => '',
			'id_form'              => 'commentform',
			'id_submit'            => 'submit',
			'class_submit'         => 'btn btn-primary',
			'name_submit'          => 'submit',
			'title_reply'          => '',
			'title_reply_to'       => esc_html__( 'Leave a Reply to %s', 'f2fdashboard' ),
			'cancel_reply_link'    => esc_html__( 'Cancel reply', 'f2fdashboard' ),
			'label_submit'         => esc_html__( 'Post Comment', 'f2fdashboard' ),
			'submit_button'        => '<input type="submit" id="%2$s" name="%1$s" class="%3$s" value="%4$s" />',
			'submit_field'         => '<div class="form-submit">%1$s %2$s</div>',
			'format'               => 'html5',
		);

		return $defaults;
	}
	add_filter( 'comment_form_defaults', 'f2fdashboard_custom_commentform' );
}

if ( function_exists( 'register_nav_menus' ) ) {
	/**
	 * Nav menus.
	 *
	 * @since v1.0
	 *
	 * @return void
	 */
	register_nav_menus(
		array(
			'main-menu'   => 'Main Navigation Menu',
			'footer-menu' => 'Footer Menu',
		)
	);
}

// Custom Nav Walker: wp_bootstrap_navwalker().
$custom_walker = __DIR__ . '/inc/wp-bootstrap-navwalker.php';
if ( is_readable( $custom_walker ) ) {
	require_once $custom_walker;
}

$custom_walker_footer = __DIR__ . '/inc/wp-bootstrap-navwalker-footer.php';
if ( is_readable( $custom_walker_footer ) ) {
	require_once $custom_walker_footer;
}

/**
 * Loading All CSS Stylesheets and Javascript Files.
 *
 * @since v1.0
 *
 * @return void
 */
function f2fdashboard_scripts_loader() {
    $theme_version = wp_get_theme()->get( 'Version' );

    // 1. Styles.
    // Google Fonts for branding
    wp_enqueue_style( 'f2f-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap', array(), null, 'all' );
    wp_enqueue_style( 'style', get_theme_file_uri( 'style.css' ), array(), $theme_version, 'all' );
    wp_enqueue_style( 'main', get_theme_file_uri( 'build/main.css' ), array(), $theme_version, 'all' ); // main.scss: Compiled Framework source + custom styles.
    // Branding overrides are now included in main.scss

	if ( is_rtl() ) {
		wp_enqueue_style( 'rtl', get_theme_file_uri( 'build/rtl.css' ), array(), $theme_version, 'all' );
	}

	// 2. Scripts.
	// jQuery (garantir que está carregado)
	wp_enqueue_script( 'jquery' );
	
	// Bootstrap JS para modais e outros componentes
	wp_enqueue_script( 'bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true );
	wp_enqueue_script( 'mainjs', get_theme_file_uri( 'build/main.js' ), array('jquery', 'bootstrap-js'), $theme_version, true );
	
	// Chart.js para gráficos
	wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true );
	
	// Localiza script para AJAX
	wp_localize_script( 'mainjs', 'f2f_ajax', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'f2f_ajax_nonce' )
	));

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'f2fdashboard_scripts_loader' );

/**
 * Carrega CSS/JS do tema no Admin especificamente na página de configurações do F2F Dashboard.
 * Isso garante que o overlay de carregamento e o JS de ativação funcionem durante importações.
 */
function f2fdashboard_admin_assets( $hook_suffix ) {
    // O hook da página criada por add_menu_page com slug 'f2f-dashboard-settings'
    // normalmente é 'toplevel_page_f2f-dashboard-settings'.
    if ( 'toplevel_page_f2f-dashboard-settings' !== $hook_suffix ) {
        return;
    }

    $theme_version = wp_get_theme()->get( 'Version' );

    // Carrega o CSS compilado (contém estilos do overlay)
    wp_enqueue_style( 'f2f-admin-main', get_theme_file_uri( 'build/main.css' ), array(), $theme_version, 'all' );

    // Carrega o JS compilado (contém lógica para ativar o overlay nos formulários)
    wp_enqueue_script( 'f2f-admin-mainjs', get_theme_file_uri( 'build/main.js' ), array(), $theme_version, true );
}
add_action( 'admin_enqueue_scripts', 'f2fdashboard_admin_assets' );
/**
 * =====================
 * F2F Dashboard: Dados ClickUp
 * =====================
 */

// Carrega as classes do dashboard
require_once get_theme_file_path( 'inc/class-f2f-dashboard.php' );
require_once get_theme_file_path( 'inc/class-client-auth.php' );
require_once get_theme_file_path( 'inc/class-f2f-csv-importer.php' );
require_once get_theme_file_path( 'inc/class-clickup-api.php' );

// Inicializa o dashboard
add_action( 'init', function() {
    // Inicializa o dashboard (cria tabela se necessário)
    $dashboard = F2F_Dashboard::get_instance();
    // A classe já cria a tabela no construtor, não precisa chamar init()
    
    // Inicializa o sistema de autenticação de clientes
    if (class_exists('F2F_Client_Auth')) {
        $auth = F2F_Client_Auth::get_instance();
        
        // Hook para processar login de clientes
        if (isset($_POST['f2f_client_login'])) {
            $auth->handle_login();
        }
        
        // Hook para processar logout de clientes
        if (isset($_GET['f2f_client_logout'])) {
            // Se um usuário WordPress estiver logado, fazer logout também
            if (is_user_logged_in()) {
                wp_logout();
            }
            
            $auth->logout_client();
        }
        
        // Hook para menu admin
        add_action('admin_menu', function() use ($auth) {
            $auth->add_admin_menu();
        });
    }
});

// Proteção de acesso - redireciona para login se não estiver autenticado
add_action('template_redirect', function() {
    // Páginas que não precisam de autenticação
    $public_pages = array(
        'client-login',
        'login',
        'wp-login.php',
        'wp-admin'
    );
    
    // Verificar se é uma página pública
    $current_page = get_query_var('pagename');
    $is_public = false;
    
    foreach ($public_pages as $page) {
        if (strpos($_SERVER['REQUEST_URI'], $page) !== false) {
            $is_public = true;
            break;
        }
    }
    
    // Se não for página pública e não estiver logado
    if (!$is_public && !is_user_logged_in() && !class_exists('F2F_Client_Auth')) {
        wp_redirect(home_url('/client-login/'));
        exit;
    }
    
    // Se não for página pública e não estiver logado (verificar cliente também)
    if (!$is_public && !is_user_logged_in()) {
        if (class_exists('F2F_Client_Auth')) {
            $client_auth = F2F_Client_Auth::get_instance();
            if (!$client_auth->is_client_logged_in()) {
                wp_redirect(home_url('/client-login/'));
                exit;
            }
        } else {
            wp_redirect(home_url('/client-login/'));
            exit;
        }
    }
});

/**
 * Registra o post type "cliente" para páginas individuais por cliente/projeto.
 */
function f2f_register_cliente_cpt() {
    $labels = array(
        'name'               => 'Clientes',
        'singular_name'      => 'Cliente',
        'add_new'            => 'Adicionar novo',
        'add_new_item'       => 'Adicionar novo cliente',
        'edit_item'          => 'Editar cliente',
        'new_item'           => 'Novo cliente',
        'view_item'          => 'Ver cliente',
        'search_items'       => 'Pesquisar clientes',
        'not_found'          => 'Nenhum cliente encontrado',
        'not_found_in_trash' => 'Nenhum cliente na lixeira',
        'menu_name'          => 'Clientes',
    );

    register_post_type( 'cliente', array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => false,
        'show_in_rest' => true,
        // Habilita imagem destacada para permitir logo do cliente via Thumb
        'supports' => array( 'title', 'editor', 'thumbnail' ),
        'rewrite' => array( 'slug' => 'cliente' ),
        'menu_icon' => 'dashicons-groups',
    ) );
}
add_action( 'init', 'f2f_register_cliente_cpt' );

/**
 * Sincroniza posts do CPT "cliente" com os projetos distintos da tabela de dados.
 * Cria (ou atualiza título) para cada project distinto.
 */
function f2f_sync_clients_posts() {
    global $wpdb;
    $table = $wpdb->prefix . 'f2f_clickup_data';
    // Preferir coluna 'client' se existir; caso contrário usar 'project'.
    $cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    $group_col = in_array( 'client', $cols, true ) ? 'client' : 'project';
    $projects = (array) $wpdb->get_col( "SELECT DISTINCT {$group_col} FROM {$table} WHERE {$group_col} IS NOT NULL AND {$group_col} <> ''" );

    foreach ( $projects as $project_name ) {
        $project_name = trim( $project_name );
        if ( $project_name === '' ) continue;

        // Tenta localizar por título exato primeiro
        $existing = get_page_by_title( $project_name, OBJECT, 'cliente' );
        if ( $existing ) {
            // Já existe; nada a fazer.
            continue;
        }

        // Se não existe, cria um novo post
        wp_insert_post( array(
            'post_type'   => 'cliente',
            'post_status' => 'publish',
            'post_title'  => $project_name,
            'post_content'=> '',
        ) );
    }
}

/**
 * Backfill: copia valores de project para client quando client estiver vazio.
 */
function f2f_backfill_client_from_project() {
    global $wpdb;
    $table = $wpdb->prefix . 'f2f_clickup_data';
    $cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( in_array( 'client', $cols, true ) ) {
        // Somente se coluna client existe
        $wpdb->query( "UPDATE {$table} SET client = project WHERE (client IS NULL OR client = '') AND project IS NOT NULL AND project <> ''" );
    }
}

/**
 * Adiciona página de configurações no Admin para escolher fonte de dados e importar/coletar CSV.
 */
function f2f_add_dashboard_settings_page() {
    add_menu_page(
        'F2F Dashboard',
        'F2F Dashboard',
        'manage_options',
        'f2f-dashboard-settings',
        'f2f_render_dashboard_settings',
        'dashicons-chart-bar',
        65
    );
}
add_action( 'admin_menu', 'f2f_add_dashboard_settings_page' );

/**
 * Renderiza a página de configurações.
 */
function f2f_render_dashboard_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $data_source = get_option( 'f2f_data_source_type', 'upload_csv' );
    $csv_url     = get_option( 'f2f_google_csv_url', '' );

    ?>
    <div class="wrap">
        <h1>F2F Dashboard – Configurações</h1>
        
        <?php
        // Exibe mensagens de feedback
        if ( isset( $_GET['imported'] ) && '1' === $_GET['imported'] ) {
            $count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo sprintf( 'Importação concluída com sucesso! Foram importadas <strong>%d</strong> linhas de dados.', $count );
            echo '</p></div>';
        } elseif ( isset( $_GET['imported'] ) && '0' === $_GET['imported'] ) {
            echo '<div class="notice notice-warning is-dismissible"><p>Importação concluída, mas nenhum dado foi importado.</p></div>';
        } elseif ( isset( $_GET['error'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            switch ( $_GET['error'] ) {
                case 'nofile':
                    echo 'Erro: Nenhum arquivo foi enviado.';
                    break;
                case 'upload':
                    echo 'Erro: Falha ao fazer upload do arquivo.';
                    break;
                case 'nourl':
                    echo 'Erro: URL do Google Sheets não configurada.';
                    break;
                case 'request':
                    echo 'Erro: Falha ao acessar o arquivo CSV remoto.';
                    break;
                case 'empty':
                    echo 'Erro: O arquivo CSV remoto está vazio ou inválido.';
                    break;
                default:
                    echo 'Erro desconhecido durante a importação.';
            }
            echo '</p></div>';
        } elseif ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
        } elseif ( isset( $_GET['cleared'] ) && '1' === $_GET['cleared'] ) {
            $count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo sprintf( 'Todos os dados foram apagados com sucesso! Foram removidos <strong>%d</strong> registros.', $count );
            echo '</p></div>';
        }
        ?>
        
        <h2 class="nav-tab-wrapper">
            <a href="#" class="nav-tab nav-tab-active">Fonte de Dados</a>
        </h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'f2f_save_settings', 'f2f_nonce' ); ?>
            <input type="hidden" name="action" value="f2f_save_settings" />

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Tipo de Fonte</th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="f2f_data_source_type" value="upload_csv" <?php checked( $data_source, 'upload_csv' ); ?> /> Upload CSV (via CMS)</label><br />
                                <label><input type="radio" name="f2f_data_source_type" value="google_csv" <?php checked( $data_source, 'google_csv' ); ?> /> Google Sheets (Publish CSV)</label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">URL CSV (Google Sheets)</th>
                        <td>
                            <input type="url" name="f2f_google_csv_url" class="regular-text" value="<?php echo esc_attr( $csv_url ); ?>" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv" />
                            <p class="description">Publique a aba como CSV e cole a URL aqui.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( 'Salvar Configurações' ); ?>
        </form>

        <hr />

        <h2>Importar/Coletar Dados</h2>

        <!-- Overlay de carregamento -->
        <div id="f2f-loading-overlay" class="f2f-loading-overlay">
            <div>
                <div class="f2f-loading-spinner"></div>
                <div class="f2f-loading-message">Importando dados, por favor aguarde...</div>
            </div>
        </div>

        <div style="display:flex; gap:24px; align-items:flex-start;">
            <div style="flex:1;">
                <h3>Upload CSV</h3>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'f2f_import_csv', 'f2f_nonce' ); ?>
                    <input type="hidden" name="action" value="f2f_import_csv" />
                    <input type="file" name="f2f_csv_file" accept=".csv" required />
                    <?php submit_button( 'Importar CSV', 'primary', 'submit', false ); ?>
                </form>
            </div>

            <div style="flex:1;">
                <h3>Buscar do Google Sheets</h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'f2f_fetch_csv', 'f2f_nonce' ); ?>
                    <input type="hidden" name="action" value="f2f_fetch_csv" />
                    <?php submit_button( 'Coletar CSV da URL', 'secondary', 'submit', false ); ?>
                </form>
            </div>
            <div style="flex:1;">
                <h3>Importar CSV do tema</h3>
                <p class="description">Procura o arquivo mais recente em <code>assets/csv</code> do tema.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'f2f_import_theme_csv', 'f2f_nonce' ); ?>
                    <input type="hidden" name="action" value="f2f_import_theme_csv" />
                    <?php submit_button( 'Importar último CSV em assets/csv', 'secondary', 'submit', false ); ?>
                </form>
            </div>
        </div>
        
        <hr />
        
        <h2>Gerenciamento de Dados</h2>
        <div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'f2f_clear_data', 'f2f_nonce' ); ?>
                <input type="hidden" name="action" value="f2f_clear_data" />
                <?php submit_button( 'Apagar Todos os Dados', 'delete', 'submit', false, array(
                    'onclick' => "return confirm('Tem certeza que deseja apagar todos os dados? Esta ação não pode ser desfeita.');"
                ) ); ?>
                <p class="description">Esta ação irá remover permanentemente todos os dados importados. Use com cuidado.</p>
            </form>
        </div>

    </div>
    <?php
}

/**
 * Salva configurações da página.
 */
function f2f_handle_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permissão insuficiente.' );
    }

    check_admin_referer( 'f2f_save_settings', 'f2f_nonce' );

    $type = isset( $_POST['f2f_data_source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['f2f_data_source_type'] ) ) : 'upload_csv';
    $url  = isset( $_POST['f2f_google_csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['f2f_google_csv_url'] ) ) : '';

    update_option( 'f2f_data_source_type', $type );
    update_option( 'f2f_google_csv_url', $url );

    wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&updated=1' ) );
    exit;
}
add_action( 'admin_post_f2f_save_settings', 'f2f_handle_save_settings' );

/**
 * Importa CSV via upload.
 */
function f2f_handle_import_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permissão insuficiente.' );
    }

    check_admin_referer( 'f2f_import_csv', 'f2f_nonce' );

    if ( empty( $_FILES['f2f_csv_file'] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=nofile' ) );
        exit;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Garante que a tabela exista.
    F2F_Dashboard::get_instance();

    $attachment_id = media_handle_upload( 'f2f_csv_file', 0 );
    if ( is_wp_error( $attachment_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=upload' ) );
        exit;
    }

    $file_path = get_attached_file( $attachment_id );
    $result = f2f_import_csv_from_path( $file_path );
    
    if (is_array($result) && isset($result['success']) && $result['success'] === true) {
        $count = isset($result['imported_lines']) ? intval($result['imported_lines']) : 
                (isset($result['count']) ? intval($result['count']) : 0);
    } else if (is_numeric($result)) {
        $count = intval($result);
    } else {
        $count = 0;
    }

    update_option( 'f2f_latest_csv_attachment_id', (int) $attachment_id );

    update_option( 'f2f_last_import_count', (int) $count );
    // Backfill e sincroniza clientes após importação
    f2f_backfill_client_from_project();
    // Sincroniza clientes após importação
    f2f_sync_clients_posts();
    wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&imported=' . ( $count > 0 ? '1' : '0' ) . '&count=' . (int) $count ) );
    exit;
}
add_action( 'admin_post_f2f_import_csv', 'f2f_handle_import_csv' );

/**
 * Busca CSV de uma URL (Google Sheets publicado) e importa.
 */
function f2f_handle_fetch_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permissão insuficiente.' );
    }

    check_admin_referer( 'f2f_fetch_csv', 'f2f_nonce' );

    // Garante que a tabela exista (já é criada pelo F2F_Dashboard)
    F2F_Dashboard::get_instance();

    $url = get_option( 'f2f_google_csv_url', '' );
    if ( empty( $url ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=nourl' ) );
        exit;
    }

    $response = wp_remote_get( $url, array( 'timeout' => 30 ) );
    if ( is_wp_error( $response ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=request' ) );
        exit;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    if ( $code !== 200 || empty( $body ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=empty' ) );
        exit;
    }

    // Salva temporariamente e importa.
    $tmp = wp_tempnam( 'f2f_google_csv' );
    file_put_contents( $tmp, $body );
    $result = f2f_import_csv_from_path( $tmp );
    @unlink( $tmp );

    if (is_array($result) && isset($result['success']) && $result['success'] === true) {
        $count = isset($result['imported_lines']) ? intval($result['imported_lines']) : 
                (isset($result['count']) ? intval($result['count']) : 0);
    } else if (is_numeric($result)) {
        $count = intval($result);
    } else {
        $count = 0;
    }

    update_option( 'f2f_last_import_count', (int) $count );
    // Backfill e sincroniza clientes após importação
    f2f_backfill_client_from_project();
    // Sincroniza clientes após importação
    f2f_sync_clients_posts();
    wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&imported=' . ( $count > 0 ? '1' : '0' ) . '&count=' . (int) $count ) );
    exit;
}
add_action( 'admin_post_f2f_fetch_csv', 'f2f_handle_fetch_csv' );

/**
 * Importa um arquivo CSV do caminho especificado.
 * Faz normalização básica e upsert por entry_id (único por linha de tempo).
 *
 * @param string $path Caminho do arquivo CSV.
 * @return array|int Retorna array com status da importação e contagem de linhas ou número de linhas importadas.
 */
function f2f_import_csv_from_path( $path ) {
    if (!file_exists($path)) {
        error_log('F2F Import: Arquivo não encontrado: ' . $path);
        return array(
            'success' => false,
            'error' => 'Arquivo não encontrado'
        );
    }
    
    $importer = F2F_CSV_Importer::get_instance();
    $result = $importer->import_from_path( $path );
    
    // Registra o resultado para debug
    error_log('F2F Import: Resultado da importação: ' . (is_array($result) ? json_encode($result) : $result));
    
    if (is_array($result) && isset($result['success'])) {
        return $result;
    } else if (is_numeric($result)) {
        // Compatibilidade com versão anterior que retornava apenas o número de linhas
        return intval($result);
    } else {
        return array(
            'success' => false,
            'error' => 'Falha na importação'
        );
    }
}

/**
 * Encontra o primeiro campo existente entre candidatos.
 *
 * @param array $assoc Dados da linha (chave: header normalizado).
 * @param array $candidates Lista de nomes possíveis.
 * @return string Valor encontrado ou string vazia.
 */
function f2f_guess_field( $assoc, $candidates ) {
    foreach ( $candidates as $c ) {
        $key = strtolower( trim( $c ) );
        if ( isset( $assoc[ $key ] ) && $assoc[ $key ] !== '' ) {
            return $assoc[ $key ];
        }
    }
    return '';
}

/**
 * Normaliza o status removendo prefixos numéricos e padronizando em minúsculas.
 *
 * @param string $status_raw
 * @return string
 */
function f2f_normalize_status( $status_raw ) {
    $status = is_string( $status_raw ) ? trim( $status_raw ) : '';
    // Remove prefixo numérico e espaços (ex.: "10 concluído" -> "concluído")
    $status = preg_replace( '/^\d+\s*/', '', $status );
    // Minúsculas para facilitar comparações.
    $status = strtolower( $status );
    return $status;
}

/**
 * Converte várias possibilidades de campo de data em DATETIME Y-m-d H:i:s.
 * Preferência: campos de texto → milissegundos → strtotime geral.
 *
 * @param array $assoc
 * @param array $candidates
 * @return string|null
 */
function f2f_parse_date_candidates( $assoc, $candidates ) {
    foreach ( $candidates as $c ) {
        $key = strtolower( trim( $c ) );
        if ( isset( $assoc[ $key ] ) && $assoc[ $key ] !== '' ) {
            $val = $assoc[ $key ];
            // Campo texto legível.
            if ( preg_match( '/[a-zA-Z]/', $key ) && false !== strtotime( $val ) ) {
                return date( 'Y-m-d H:i:s', strtotime( $val ) );
            }
            // Milissegundos desde epoch.
            if ( is_numeric( $val ) ) {
                // Alguns exports usam ms; outros podem já estar em segundos.
                $ts = (int) $val;
                if ( $ts > 2000000000 ) { // maior que ~2033 em segundos, então deve ser ms
                    $ts = (int) round( $ts / 1000 );
                }
                return date( 'Y-m-d H:i:s', $ts );
            }
            // Fallback: tentar strtotime
            $parsed = strtotime( $val );
            if ( false !== $parsed ) {
                return date( 'Y-m-d H:i:s', $parsed );
            }
        }
    }
    return null;
}

/**
 * Converte valores de duração em segundos.
 * Aceita número (ms ou segundos) e/ou texto no formato HH:MM:SS.
 *
 * @param mixed  $val_num  Valor numérico da duração.
 * @param string $val_text Valor textual da duração.
 * @return int|null Segundos, ou null se não for possível converter.
 */
function f2f_parse_duration_seconds( $val_num, $val_text ) {
    $seconds = null;

    if ( is_numeric( $val_num ) ) {
        $n = (int) $val_num;
        // Heurística: ClickUp exporta milissegundos para campos de tempo.
        // Se o número for grande (>= 100000) ou múltiplo de 1000, tratar como ms.
        if ( $n >= 100000 || ( $n % 1000 ) === 0 ) {
            $seconds = (int) round( $n / 1000 );
        } else {
            $seconds = $n; // já em segundos.
        }
    }

    if ( null === $seconds && is_string( $val_text ) ) {
        $t = trim( $val_text );
        if ( preg_match( '/^\d{1,3}:\d{2}:\d{2}$/', $t ) ) {
            list( $h, $m, $s ) = explode( ':', $t );
            $seconds = ( (int) $h ) * 3600 + ( (int) $m ) * 60 + ( (int) $s );
        }
    }

    return $seconds;
}

/**
 * Importa o último CSV presente em assets/csv do tema.
 */
function f2f_handle_import_theme_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permissão insuficiente.' );
    }

    check_admin_referer( 'f2f_import_theme_csv', 'f2f_nonce' );

    // Garante que a tabela exista (já é criada pelo F2F_Dashboard)
    F2F_Dashboard::get_instance();

    $dir = get_theme_file_path( 'assets/csv' );
    if ( ! is_dir( $dir ) ) {
        error_log('F2F Import: Diretório não encontrado: ' . $dir);
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=nocsvdir' ) );
        exit;
    }

    $files = glob( $dir . '/*.csv' );
    if ( empty( $files ) ) {
        error_log('F2F Import: Nenhum arquivo CSV encontrado em: ' . $dir);
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=nocsv' ) );
        exit;
    }

    // Seleciona o arquivo mais recente por mtime.
    usort( $files, function( $a, $b ) { return filemtime( $b ) <=> filemtime( $a ); } );
    $latest = $files[0];
    
    error_log('F2F Import: Importando arquivo: ' . $latest);
    
    // Verifica se o arquivo existe e é legível
    if (!is_readable($latest)) {
        error_log('F2F Import: Arquivo não é legível: ' . $latest);
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=csvnotreadable' ) );
        exit;
    }
    
    // Verifica o tamanho do arquivo
    $filesize = filesize($latest);
    error_log('F2F Import: Tamanho do arquivo: ' . $filesize . ' bytes');
    
    if ($filesize <= 0) {
        error_log('F2F Import: Arquivo vazio');
        wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&error=csvempty' ) );
        exit;
    }

    $result = f2f_import_csv_from_path( $latest );
    error_log('F2F Import: Resultado da importação: ' . (is_array($result) ? json_encode($result) : $result));
    
    if (is_array($result) && isset($result['success']) && $result['success'] === true) {
        $count = isset($result['imported_lines']) ? intval($result['imported_lines']) : 
                (isset($result['count']) ? intval($result['count']) : 0);
    } else if (is_numeric($result)) {
        $count = intval($result);
    } else {
        $count = 0;
    }
    
    error_log('F2F Import: Contagem final: ' . $count);
    update_option( 'f2f_last_import_count', (int) $count );
    // Backfill e sincroniza clientes após importação
    f2f_backfill_client_from_project();
    // Sincroniza clientes após importação
    f2f_sync_clients_posts();
    wp_safe_redirect( admin_url( 'admin.php?page=f2f-dashboard-settings&imported=' . ( $count > 0 ? '1' : '0' ) . '&count=' . (int) $count ) );
    exit;
}
add_action( 'admin_post_f2f_import_theme_csv', 'f2f_handle_import_theme_csv' );

/**
 * Handler para limpar todos os dados da tabela
 */
function f2f_handle_clear_data() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acesso negado' );
    }

    check_admin_referer( 'f2f_clear_data', 'f2f_nonce' );

    // Limpa todos os dados
    $dashboard = F2F_Dashboard::get_instance();
    $count = $dashboard->clear_all_data();

    // Redireciona de volta para a página de configurações
    wp_safe_redirect( add_query_arg( 
        array( 
            'page' => 'f2f-dashboard-settings',
            'cleared' => '1',
            'count' => $count
        ), 
        admin_url( 'admin.php' ) 
    ) );
    exit;
}
add_action( 'admin_post_f2f_clear_data', 'f2f_handle_clear_data' );

// Handler AJAX para buscar tarefas atrasadas
add_action( 'wp_ajax_get_overdue_tasks_details', 'handle_get_overdue_tasks_details' );
add_action( 'wp_ajax_nopriv_get_overdue_tasks_details', 'handle_get_overdue_tasks_details' );

// Handler AJAX para buscar horas de uma tarefa específica
add_action( 'wp_ajax_get_task_hours', 'handle_get_task_hours' );
add_action( 'wp_ajax_nopriv_get_task_hours', 'handle_get_task_hours' );

// Handler AJAX para buscar tarefas em andamento
add_action( 'wp_ajax_get_in_progress_tasks', 'handle_get_in_progress_tasks' );
add_action( 'wp_ajax_nopriv_get_in_progress_tasks', 'handle_get_in_progress_tasks' );

// Handler AJAX de teste
add_action( 'wp_ajax_test_ajax', 'handle_test_ajax' );
add_action( 'wp_ajax_nopriv_test_ajax', 'handle_test_ajax' );

// Handler AJAX para testar a classe F2F_Dashboard
add_action( 'wp_ajax_test_dashboard_class', 'handle_test_dashboard_class' );
add_action( 'wp_ajax_nopriv_test_dashboard_class', 'handle_test_dashboard_class' );

function handle_get_overdue_tasks_details() {
    // Verifica nonce
    if ( ! wp_verify_nonce( $_POST['nonce'], 'overdue_tasks_nonce' ) ) {
        wp_die( 'Erro de segurança' );
    }
    
    // Sanitiza parâmetros
    $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null;
    $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null;
    $client = isset( $_POST['client'] ) ? sanitize_text_field( $_POST['client'] ) : null;
    $assignee = isset( $_POST['assignee'] ) ? sanitize_text_field( $_POST['assignee'] ) : null;
    
    // Converte datas para formato MySQL se fornecidas
    if ( $start_date ) {
        $start_date = date( 'Y-m-d H:i:s', strtotime( $start_date . ' 00:00:00' ) );
    }
    if ( $end_date ) {
        $end_date = date( 'Y-m-d H:i:s', strtotime( $end_date . ' 23:59:59' ) );
    }
    
    // Busca as tarefas atrasadas
    $dashboard = F2F_Dashboard::get_instance();
    $tasks = $dashboard->get_overdue_tasks_details( $start_date, $end_date, $client, $assignee );
    
    wp_send_json_success( $tasks );
}

function handle_get_task_hours() {
    // Verifica nonce
    if ( ! wp_verify_nonce( $_POST['nonce'], 'task_hours_nonce' ) ) {
        wp_die( 'Erro de segurança' );
    }
    
    // Sanitiza parâmetros
    $task_id = isset( $_POST['task_id'] ) ? sanitize_text_field( $_POST['task_id'] ) : '';
    
    if ( empty( $task_id ) ) {
        wp_send_json_error( 'ID da tarefa não fornecido' );
    }
    
    // Busca as horas da tarefa
    $dashboard = F2F_Dashboard::get_instance();
    $hours = $dashboard->get_task_hours( $task_id );
    
    wp_send_json_success( $hours );
}

function handle_get_in_progress_tasks() {
    // Limpa qualquer output anterior que possa estar causando problema
    if ( ob_get_length() ) {
        ob_clean();
    }
    
    error_log( 'F2F Dashboard: Handler AJAX para tarefas em andamento chamado' );
    
    // Verifica nonce (comentado temporariamente para debug)
    // if ( ! wp_verify_nonce( $_POST['nonce'], 'in_progress_tasks_nonce' ) ) {
    //     wp_die( 'Erro de segurança' );
    // }
    
    try {
        error_log( 'F2F Dashboard: Tentando obter instância da classe...' );
        
        // Verifica se a classe existe
        if ( ! class_exists( 'F2F_Dashboard' ) ) {
            error_log( 'F2F Dashboard: Classe F2F_Dashboard não encontrada!' );
            wp_send_json_error( 'Classe F2F_Dashboard não encontrada' );
            return;
        }
        
        // Busca as tarefas em andamento
        $dashboard = F2F_Dashboard::get_instance();
        error_log( 'F2F Dashboard: Instância obtida com sucesso' );
        
        $tasks = $dashboard->get_in_progress_tasks_list( 50 );
        error_log( 'F2F Dashboard: Retornando ' . count( $tasks ) . ' tarefas em andamento' );
        
        // Garante que não há output antes do JSON
        wp_send_json_success( $tasks );
        exit; // Garante que nada mais será executado
    } catch ( Exception $e ) {
        error_log( 'F2F Dashboard: Erro ao buscar tarefas em andamento: ' . $e->getMessage() );
        error_log( 'F2F Dashboard: Stack trace: ' . $e->getTraceAsString() );
        wp_send_json_error( 'Erro interno: ' . $e->getMessage() );
        exit;
    }
}

function handle_test_ajax() {
    error_log( 'F2F Dashboard: Teste AJAX chamado' );
    wp_send_json_success( array( 'message' => 'AJAX funcionando!', 'timestamp' => current_time( 'mysql' ) ) );
}

function handle_test_dashboard_class() {
    error_log( 'F2F Dashboard: Teste da classe F2F_Dashboard chamado' );
    
    try {
        // Verifica se a classe existe
        if ( ! class_exists( 'F2F_Dashboard' ) ) {
            wp_send_json_error( 'Classe F2F_Dashboard não encontrada' );
            return;
        }
        
        // Tenta obter a instância
        $dashboard = F2F_Dashboard::get_instance();
        
        // Verifica se a instância foi criada
        if ( ! $dashboard ) {
            wp_send_json_error( 'Não foi possível criar instância da classe F2F_Dashboard' );
            return;
        }
        
        // Verifica se o método existe
        if ( ! method_exists( $dashboard, 'get_in_progress_tasks_list' ) ) {
            wp_send_json_error( 'Método get_in_progress_tasks_list não encontrado' );
            return;
        }
        
        // Tenta chamar o método
        $tasks = $dashboard->get_in_progress_tasks_list( 5 );
        
        wp_send_json_success( array( 
            'message' => 'Classe F2F_Dashboard funcionando!', 
            'tasks_count' => count( $tasks ),
            'sample_task' => ! empty( $tasks ) ? $tasks[0] : null
        ) );
        
    } catch ( Exception $e ) {
        error_log( 'F2F Dashboard: Erro no teste da classe: ' . $e->getMessage() );
        wp_send_json_error( 'Erro: ' . $e->getMessage() );
    }
}

// Handler AJAX para verificar e corrigir estrutura da tabela
add_action( 'wp_ajax_fix_table_structure', 'handle_fix_table_structure' );
add_action( 'wp_ajax_nopriv_fix_table_structure', 'handle_fix_table_structure' );

function handle_fix_table_structure() {
    error_log( 'F2F Dashboard: Corrigindo estrutura da tabela...' );
    
    try {
        global $wpdb;
        
        // Nome da tabela
        $table_name = $wpdb->prefix . 'f2f_clickup_data';
        
        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
        
        if ( ! $table_exists ) {
            error_log( 'F2F Dashboard: Tabela não existe, criando...' );
            
            // Cria a tabela usando a classe F2F_Dashboard
            $dashboard = F2F_Dashboard::get_instance();
            $dashboard->create_table();
            
            wp_send_json_success( array( 'message' => 'Tabela criada com sucesso!' ) );
            return;
        }
        
        // Verifica se a coluna entry_id existe
        $column_exists = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'entry_id'" );
        
        if ( ! $column_exists ) {
            error_log( 'F2F Dashboard: Coluna entry_id não existe, adicionando...' );
            
            // Adiciona a coluna entry_id
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN entry_id VARCHAR(64) DEFAULT NULL AFTER id" );
            
            // Adiciona índice único
            $wpdb->query( "ALTER TABLE $table_name ADD UNIQUE KEY entry_id (entry_id)" );
            
            wp_send_json_success( array( 'message' => 'Coluna entry_id adicionada com sucesso!' ) );
            return;
        }
        
        wp_send_json_success( array( 'message' => 'Estrutura da tabela está correta!' ) );
        
    } catch ( Exception $e ) {
        error_log( 'F2F Dashboard: Erro ao corrigir tabela: ' . $e->getMessage() );
        wp_send_json_error( 'Erro: ' . $e->getMessage() );
    }
}

/**
 * Função para criar credenciais para clientes existentes
 */
add_action( 'wp_ajax_f2f_create_client_credentials', 'f2f_create_client_credentials' );
function f2f_create_client_credentials() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão');
    }
    
    $client_auth = F2F_Client_Auth::get_instance();
    
    // Buscar todos os clientes (CPTs)
    $clients = get_posts(array(
        'post_type' => 'cliente',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    $created = 0;
    foreach ($clients as $client) {
        $client_name = $client->post_title;
        
        // Verificar se já existe credencial
        global $wpdb;
        $table_name = $wpdb->prefix . 'f2f_client_access';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE client_name = %s",
            $client_name
        ));
        
        if (!$existing) {
            $credentials = $client_auth->create_client_credentials($client_name);
            if ($credentials) {
                $created++;
            }
        }
    }
    
    wp_send_json_success(array(
        'message' => "Criadas {$created} credenciais para clientes.",
        'created' => $created
    ));
}

// AJAX para criar credenciais de um cliente específico
add_action( 'wp_ajax_f2f_create_single_client_credentials', 'f2f_create_single_client_credentials' );
function f2f_create_single_client_credentials() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'f2f_create_single_client_credentials')) {
        wp_die('Erro de segurança');
    }
    
    $client_name = sanitize_text_field($_POST['client_name']);
    
    if (empty($client_name)) {
        wp_send_json_error('Nome do cliente é obrigatório');
    }
    
    $client_auth = F2F_Client_Auth::get_instance();
    
    // Verificar se já existe credencial
    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_client_access';
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE client_name = %s",
        $client_name
    ));
    
    if ($existing) {
        wp_send_json_error('Credenciais já existem para este cliente');
    }
    
    $credentials = $client_auth->create_client_credentials($client_name);
    
    if ($credentials) {
        wp_send_json_success(array(
            'username' => $credentials['username'],
            'password' => $credentials['password']
        ));
    } else {
        wp_send_json_error('Erro ao criar credenciais');
    }
}

/**
 * Criar página de login automaticamente na ativação do tema
 */
add_action('after_switch_theme', 'f2f_create_login_page');
add_action('init', 'f2f_create_login_page_once');
function f2f_create_login_page() {
    // Verificar se a página já existe
    $existing_page = get_page_by_path('client-login');
    
    if (!$existing_page) {
        // Criar a página
        $page_data = array(
            'post_title'    => 'Login Cliente',
            'post_content'  => 'Esta página é para login de clientes.',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'client-login'
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            // Definir o template personalizado
            update_post_meta($page_id, '_wp_page_template', 'page-client-login.php');
        }
    }
    
    // Criar credenciais para clientes existentes
    $client_auth = F2F_Client_Auth::get_instance();
    
    // Buscar todos os clientes
    $clients = get_posts(array(
        'post_type' => 'cliente',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    foreach ($clients as $client) {
        $client_name = $client->post_title;
        
        // Verificar se já existe credencial
        global $wpdb;
        $table_name = $wpdb->prefix . 'f2f_client_access';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE client_name = %s",
            $client_name
        ));
        
        if (!$existing) {
            $client_auth->create_client_credentials($client_name);
        }
    }
}

/**
 * Criar página de login uma única vez
 */
function f2f_create_login_page_once() {
    // Verificar se já foi criada
    if (get_option('f2f_login_page_created')) {
        return;
    }
    
    // Verificar se a página já existe
    $existing_page = get_page_by_path('client-login');
    
    if (!$existing_page) {
        // Criar a página
        $page_data = array(
            'post_title'    => 'Login Cliente',
            'post_content'  => 'Esta página é para login de clientes.',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'client-login'
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            // Definir o template personalizado
            update_post_meta($page_id, '_wp_page_template', 'page-client-login.php');
            
            // Marcar como criada
            update_option('f2f_login_page_created', true);
        }
    } else {
        // Marcar como criada mesmo se já existia
        update_option('f2f_login_page_created', true);
    }
}

/**
 * AJAX para criar página de login manualmente
 */
add_action('wp_ajax_f2f_create_login_page', 'f2f_create_login_page_ajax');
function f2f_create_login_page_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão');
    }
    
    // Verificar se a página já existe
    $existing_page = get_page_by_path('client-login');
    
    if (!$existing_page) {
        // Criar a página
        $page_data = array(
            'post_title'    => 'Login Cliente',
            'post_content'  => 'Esta página é para login de clientes.',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'client-login'
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            // Definir o template personalizado
            update_post_meta($page_id, '_wp_page_template', 'page-client-login.php');
            
            // Marcar como criada
            update_option('f2f_login_page_created', true);
            
            wp_send_json_success(array(
                'message' => 'Página de login criada com sucesso!',
                'page_id' => $page_id,
                'url' => get_permalink($page_id)
            ));
        } else {
            wp_send_json_error('Erro ao criar a página.');
        }
    } else {
        wp_send_json_success(array(
            'message' => 'Página já existe.',
            'page_id' => $existing_page->ID,
            'url' => get_permalink($existing_page->ID)
        ));
    }
}

// AJAX handlers movidos para a classe F2F_Client_Auth

/**
 * =====================
 * ClickUp API Integration
 * =====================
 */

/**
 * Adiciona página de configuração da API do ClickUp
 */
function f2f_add_clickup_api_settings_page() {
    add_submenu_page(
        'f2f-dashboard-settings',
        'ClickUp API',
        'ClickUp API',
        'manage_options',
        'f2f-clickup-api',
        'f2f_render_clickup_api_page'
    );
}
add_action( 'admin_menu', 'f2f_add_clickup_api_settings_page', 20 );

/**
 * Renderiza a página de configuração da API
 */
function f2f_render_clickup_api_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $api = F2F_ClickUp_API::get_instance();
    $api_token = get_option( 'f2f_clickup_api_token', '' );
    $default_list = get_option( 'f2f_clickup_default_list', '' );
    $default_workspace = get_option( 'f2f_clickup_default_workspace', '' );
    
    // Testa conexão se o token estiver configurado
    $connection_status = '';
    $workspaces = array();
    if ( ! empty( $api_token ) ) {
        $test = $api->test_connection();
        if ( is_wp_error( $test ) ) {
            $connection_status = '<div class="notice notice-error"><p><strong>Erro:</strong> ' . $test->get_error_message() . '</p></div>';
        } else {
            $connection_status = '<div class="notice notice-success"><p><strong>Conexão OK!</strong> Conectado como: ' . esc_html( $test['user']['username'] ) . '</p></div>';
            
            // Busca workspaces
            $workspaces = $api->get_workspaces();
            if ( is_wp_error( $workspaces ) ) {
                $workspaces = array();
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><i class="dashicons dashicons-admin-plugins"></i> Integração ClickUp API</h1>
        
        <?php
        if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
        }
        
        echo $connection_status;
        ?>
        
        <div class="card" style="max-width: 800px;">
            <h2>Configuração da API</h2>
            
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'f2f_save_clickup_api', 'f2f_nonce' ); ?>
                <input type="hidden" name="action" value="f2f_save_clickup_api" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_token">API Token</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="api_token" 
                                   name="api_token" 
                                   value="<?php echo esc_attr( $api_token ); ?>" 
                                   class="regular-text"
                                   placeholder="pk_...">
                            <p class="description">
                                Obtenha seu token em: 
                                <a href="https://app.clickup.com/settings/apps" target="_blank">
                                    ClickUp Settings > Apps
                                </a>
                            </p>
                        </td>
                    </tr>
                    
                    <?php if ( ! empty( $workspaces ) ) : ?>
                    <tr>
                        <th scope="row">
                            <label for="default_workspace">Workspace Padrão</label>
                        </th>
                        <td>
                            <select id="default_workspace" name="default_workspace" class="regular-text">
                                <option value="">Selecione um workspace</option>
                                <?php foreach ( $workspaces as $workspace ) : ?>
                                    <option value="<?php echo esc_attr( $workspace['id'] ); ?>" 
                                            <?php selected( $default_workspace, $workspace['id'] ); ?>>
                                        <?php echo esc_html( $workspace['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Workspace padrão para criar novas tarefas</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_list">Lista Padrão</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="default_list" 
                                   name="default_list" 
                                   value="<?php echo esc_attr( $default_list ); ?>" 
                                   class="regular-text"
                                   placeholder="ID da lista (ex: 123456789)">
                            <button type="button" class="button" id="load-lists-btn">Carregar Listas</button>
                            <div id="lists-container" style="margin-top: 10px;"></div>
                            <p class="description">ID da lista onde as tarefas serão criadas por padrão</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php submit_button( 'Salvar Configurações' ); ?>
            </form>
        </div>
        
        <?php if ( ! empty( $api_token ) && ! empty( $default_list ) ) : ?>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Criar Nova Tarefa</h2>
            
            <form id="create-task-form">
                <?php wp_nonce_field( 'f2f_create_task', 'f2f_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="task_name">Nome da Tarefa *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="task_name" 
                                   name="task_name" 
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="task_description">Descrição</label>
                        </th>
                        <td>
                            <textarea id="task_description" 
                                      name="task_description" 
                                      rows="5" 
                                      class="large-text"></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="task_list">Lista</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="task_list" 
                                   name="task_list" 
                                   value="<?php echo esc_attr( $default_list ); ?>" 
                                   class="regular-text">
                            <p class="description">Deixe em branco para usar a lista padrão</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="task_priority">Prioridade</label>
                        </th>
                        <td>
                            <select id="task_priority" name="task_priority">
                                <option value="">Nenhuma</option>
                                <option value="1">Urgente</option>
                                <option value="2">Alta</option>
                                <option value="3">Normal</option>
                                <option value="4">Baixa</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="task_due_date">Data de Entrega</label>
                        </th>
                        <td>
                            <input type="datetime-local" 
                                   id="task_due_date" 
                                   name="task_due_date">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="task_tags">Tags</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="task_tags" 
                                   name="task_tags" 
                                   class="regular-text"
                                   placeholder="tag1, tag2, tag3">
                            <p class="description">Separe as tags com vírgulas</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="create-task-btn">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        Criar Tarefa
                    </button>
                </p>
            </form>
            
            <div id="task-result" style="margin-top: 20px;"></div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Carregar listas do workspace selecionado
        $('#load-lists-btn').click(function() {
            var workspaceId = $('#default_workspace').val();
            if (!workspaceId) {
                alert('Selecione um workspace primeiro');
                return;
            }
            
            $(this).prop('disabled', true).text('Carregando...');
            
            $.post(f2f_ajax.ajaxurl, {
                action: 'f2f_get_clickup_lists',
                workspace_id: workspaceId,
                nonce: '<?php echo wp_create_nonce('f2f_get_lists'); ?>'
            }, function(response) {
                if (response.success) {
                    var html = '<select id="list-selector" style="width: 100%; padding: 8px;">';
                    html += '<option value="">Selecione uma lista</option>';
                    
                    $.each(response.data.lists, function(i, list) {
                        html += '<option value="' + list.id + '">' + list.name + '</option>';
                    });
                    
                    html += '</select>';
                    html += '<button type="button" class="button" style="margin-top: 10px;" id="select-list-btn">Usar Esta Lista</button>';
                    
                    $('#lists-container').html(html);
                } else {
                    alert('Erro ao carregar listas: ' + response.data);
                }
                
                $('#load-lists-btn').prop('disabled', false).text('Carregar Listas');
            });
        });
        
        // Selecionar lista
        $(document).on('click', '#select-list-btn', function() {
            var listId = $('#list-selector').val();
            if (listId) {
                $('#default_list').val(listId);
                alert('Lista selecionada! Salve as configurações.');
            }
        });
        
        // Criar tarefa
        $('#create-task-form').submit(function(e) {
            e.preventDefault();
            
            var $btn = $('#create-task-btn');
            var originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite; margin-top: 3px;"></span> Criando...');
            
            var formData = {
                action: 'f2f_create_clickup_task',
                nonce: $('#f2f_nonce').val(),
                task_name: $('#task_name').val(),
                task_description: $('#task_description').val(),
                task_list: $('#task_list').val() || '<?php echo esc_js( $default_list ); ?>',
                task_priority: $('#task_priority').val(),
                task_due_date: $('#task_due_date').val(),
                task_tags: $('#task_tags').val()
            };
            
            $.post(f2f_ajax.ajaxurl, formData, function(response) {
                if (response.success) {
                    $('#task-result').html(
                        '<div class="notice notice-success"><p><strong>Tarefa criada com sucesso!</strong><br>' +
                        'ID: ' + response.data.task.id + '<br>' +
                        '<a href="' + response.data.task.url + '" target="_blank">Abrir no ClickUp</a>' +
                        '</p></div>'
                    );
                    
                    // Limpa o formulário
                    $('#create-task-form')[0].reset();
                    $('#task_list').val('<?php echo esc_js( $default_list ); ?>');
                } else {
                    $('#task-result').html(
                        '<div class="notice notice-error"><p><strong>Erro ao criar tarefa:</strong><br>' +
                        response.data +
                        '</p></div>'
                    );
                }
                
                $btn.prop('disabled', false).html(originalText);
            });
        });
    });
    
    // CSS para animação de loading
    var style = document.createElement('style');
    style.innerHTML = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
    document.head.appendChild(style);
    </script>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-top: 20px;
    }
    .card h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    </style>
    <?php
}

/**
 * Salva configurações da API
 */
function f2f_handle_save_clickup_api() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permissão insuficiente.' );
    }
    
    check_admin_referer( 'f2f_save_clickup_api', 'f2f_nonce' );
    
    $api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( $_POST['api_token'] ) : '';
    $default_list = isset( $_POST['default_list'] ) ? sanitize_text_field( $_POST['default_list'] ) : '';
    $default_workspace = isset( $_POST['default_workspace'] ) ? sanitize_text_field( $_POST['default_workspace'] ) : '';
    
    update_option( 'f2f_clickup_api_token', $api_token );
    update_option( 'f2f_clickup_default_list', $default_list );
    update_option( 'f2f_clickup_default_workspace', $default_workspace );
    
    // Atualiza o token na instância da API
    $api = F2F_ClickUp_API::get_instance();
    $api->set_token( $api_token );
    
    wp_safe_redirect( admin_url( 'admin.php?page=f2f-clickup-api&updated=1' ) );
    exit;
}
add_action( 'admin_post_f2f_save_clickup_api', 'f2f_handle_save_clickup_api' );

/**
 * AJAX: Obter listas do ClickUp
 */
function f2f_ajax_get_clickup_lists() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Sem permissão' );
    }
    
    if ( ! wp_verify_nonce( $_POST['nonce'], 'f2f_get_lists' ) ) {
        wp_send_json_error( 'Erro de segurança' );
    }
    
    $workspace_id = sanitize_text_field( $_POST['workspace_id'] );
    
    $api = F2F_ClickUp_API::get_instance();
    
    // Busca spaces do workspace
    $spaces = $api->get_spaces( $workspace_id );
    
    if ( is_wp_error( $spaces ) ) {
        wp_send_json_error( $spaces->get_error_message() );
    }
    
    $all_lists = array();
    
    // Busca listas de cada space
    foreach ( $spaces as $space ) {
        $lists = $api->get_lists( $space['id'] );
        
        if ( ! is_wp_error( $lists ) ) {
            foreach ( $lists as $list ) {
                $all_lists[] = array(
                    'id' => $list['id'],
                    'name' => $space['name'] . ' > ' . $list['name'],
                );
            }
        }
    }
    
    wp_send_json_success( array( 'lists' => $all_lists ) );
}
add_action( 'wp_ajax_f2f_get_clickup_lists', 'f2f_ajax_get_clickup_lists' );

/**
 * AJAX: Criar tarefa no ClickUp
 */
function f2f_ajax_create_clickup_task() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Sem permissão' );
    }
    
    if ( ! wp_verify_nonce( $_POST['nonce'], 'f2f_create_task' ) ) {
        wp_send_json_error( 'Erro de segurança' );
    }
    
    $api = F2F_ClickUp_API::get_instance();
    
    if ( ! $api->is_configured() ) {
        wp_send_json_error( 'API não está configurada' );
    }
    
    $list_id = sanitize_text_field( $_POST['task_list'] );
    
    $task_data = array(
        'name' => sanitize_text_field( $_POST['task_name'] ),
        'description' => sanitize_textarea_field( $_POST['task_description'] ),
    );
    
    // Prioridade
    if ( ! empty( $_POST['task_priority'] ) ) {
        $task_data['priority'] = intval( $_POST['task_priority'] );
    }
    
    // Data de entrega
    if ( ! empty( $_POST['task_due_date'] ) ) {
        $task_data['due_date'] = sanitize_text_field( $_POST['task_due_date'] );
    }
    
    // Tags
    if ( ! empty( $_POST['task_tags'] ) ) {
        $tags = array_map( 'trim', explode( ',', $_POST['task_tags'] ) );
        $task_data['tags'] = array_filter( $tags );
    }
    
    $result = $api->create_task( $list_id, $task_data );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    
    wp_send_json_success( array( 'task' => $result ) );
}
add_action( 'wp_ajax_f2f_create_clickup_task', 'f2f_ajax_create_clickup_task' );

/**
 * Shortcode para criar tarefas no frontend
 * 
 * Uso: [f2f_create_task list_id="123456"]
 */
function f2f_create_task_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'list_id' => get_option( 'f2f_clickup_default_list', '' ),
        'button_text' => 'Criar Tarefa',
        'show_priority' => 'yes',
        'show_due_date' => 'yes',
        'show_tags' => 'yes',
    ), $atts );
    
    $api = F2F_ClickUp_API::get_instance();
    
    if ( ! $api->is_configured() ) {
        return '<div class="f2f-task-form-error">ClickUp API não está configurada.</div>';
    }
    
    if ( empty( $atts['list_id'] ) ) {
        return '<div class="f2f-task-form-error">ID da lista não foi definido.</div>';
    }
    
    // Enfileira estilos e scripts
    wp_enqueue_style( 'f2f-task-form', get_theme_file_uri( 'build/main.css' ) );
    
    $form_id = 'f2f-task-form-' . wp_rand( 1000, 9999 );
    
    ob_start();
    ?>
    <div class="f2f-task-form-container" id="<?php echo esc_attr( $form_id ); ?>">
        <form class="f2f-task-form" data-list-id="<?php echo esc_attr( $atts['list_id'] ); ?>">
            <?php wp_nonce_field( 'f2f_frontend_create_task', 'f2f_nonce' ); ?>
            
            <div class="f2f-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>_name">
                    <i class="fas fa-tasks"></i> Nome da Tarefa *
                </label>
                <input type="text" 
                       id="<?php echo esc_attr( $form_id ); ?>_name" 
                       name="task_name" 
                       class="f2f-form-control" 
                       required 
                       placeholder="Digite o nome da tarefa">
            </div>
            
            <div class="f2f-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>_description">
                    <i class="fas fa-align-left"></i> Descrição
                </label>
                <textarea id="<?php echo esc_attr( $form_id ); ?>_description" 
                          name="task_description" 
                          class="f2f-form-control" 
                          rows="4"
                          placeholder="Descreva a tarefa em detalhes"></textarea>
            </div>
            
            <?php if ( 'yes' === $atts['show_priority'] ) : ?>
            <div class="f2f-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>_priority">
                    <i class="fas fa-flag"></i> Prioridade
                </label>
                <select id="<?php echo esc_attr( $form_id ); ?>_priority" 
                        name="task_priority" 
                        class="f2f-form-control">
                    <option value="">Nenhuma</option>
                    <option value="1">🔴 Urgente</option>
                    <option value="2">🟡 Alta</option>
                    <option value="3" selected>🔵 Normal</option>
                    <option value="4">⚪ Baixa</option>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ( 'yes' === $atts['show_due_date'] ) : ?>
            <div class="f2f-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>_due_date">
                    <i class="fas fa-calendar"></i> Data de Entrega
                </label>
                <input type="datetime-local" 
                       id="<?php echo esc_attr( $form_id ); ?>_due_date" 
                       name="task_due_date" 
                       class="f2f-form-control">
            </div>
            <?php endif; ?>
            
            <?php if ( 'yes' === $atts['show_tags'] ) : ?>
            <div class="f2f-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>_tags">
                    <i class="fas fa-tags"></i> Tags
                </label>
                <input type="text" 
                       id="<?php echo esc_attr( $form_id ); ?>_tags" 
                       name="task_tags" 
                       class="f2f-form-control"
                       placeholder="Ex: urgente, cliente-x, desenvolvimento">
                <small class="f2f-form-help">Separe as tags com vírgulas</small>
            </div>
            <?php endif; ?>
            
            <div class="f2f-form-group">
                <button type="submit" class="f2f-btn f2f-btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    <span class="btn-text"><?php echo esc_html( $atts['button_text'] ); ?></span>
                    <span class="btn-loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Criando...
                    </span>
                </button>
            </div>
            
            <div class="f2f-form-result"></div>
        </form>
    </div>
    
    <script>
    (function() {
        var form = document.querySelector('#<?php echo esc_js( $form_id ); ?> .f2f-task-form');
        
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var btn = form.querySelector('.f2f-btn-primary');
            var btnText = btn.querySelector('.btn-text');
            var btnLoading = btn.querySelector('.btn-loading');
            var resultDiv = form.querySelector('.f2f-form-result');
            
            // Mostra loading
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
            resultDiv.innerHTML = '';
            
            // Prepara dados
            var formData = new FormData();
            formData.append('action', 'f2f_frontend_create_task');
            formData.append('nonce', form.querySelector('[name="f2f_nonce"]').value);
            formData.append('task_name', form.querySelector('[name="task_name"]').value);
            formData.append('task_description', form.querySelector('[name="task_description"]').value);
            formData.append('task_list', form.getAttribute('data-list-id'));
            
            var priorityField = form.querySelector('[name="task_priority"]');
            if (priorityField) {
                formData.append('task_priority', priorityField.value);
            }
            
            var dueDateField = form.querySelector('[name="task_due_date"]');
            if (dueDateField) {
                formData.append('task_due_date', dueDateField.value);
            }
            
            var tagsField = form.querySelector('[name="task_tags"]');
            if (tagsField) {
                formData.append('task_tags', tagsField.value);
            }
            
            // Envia requisição
            fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                // Restaura botão
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                
                if (data.success) {
                    // Sucesso
                    resultDiv.innerHTML = '<div class="f2f-alert f2f-alert-success">' +
                        '<i class="fas fa-check-circle"></i> ' +
                        '<strong>Tarefa criada com sucesso!</strong><br>' +
                        '<small>ID: ' + data.data.task.id + '</small>' +
                        '</div>';
                    
                    // Limpa formulário
                    form.reset();
                    
                    // Scroll até o resultado
                    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    // Erro
                    resultDiv.innerHTML = '<div class="f2f-alert f2f-alert-error">' +
                        '<i class="fas fa-exclamation-triangle"></i> ' +
                        '<strong>Erro ao criar tarefa:</strong><br>' +
                        data.data +
                        '</div>';
                }
            })
            .catch(function(error) {
                // Restaura botão
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                
                resultDiv.innerHTML = '<div class="f2f-alert f2f-alert-error">' +
                    '<i class="fas fa-exclamation-triangle"></i> ' +
                    'Erro na requisição. Tente novamente.' +
                    '</div>';
            });
        });
    })();
    </script>
    
    <style>
    .f2f-task-form-container {
        max-width: 600px;
        margin: 20px auto;
        padding: 20px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .f2f-form-group {
        margin-bottom: 20px;
    }
    
    .f2f-form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    
    .f2f-form-group label i {
        margin-right: 5px;
        color: #667eea;
    }
    
    .f2f-form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        font-family: inherit;
    }
    
    .f2f-form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .f2f-form-help {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: #718096;
    }
    
    .f2f-btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .f2f-btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .f2f-btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    .f2f-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .f2f-alert {
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
        animation: slideIn 0.3s ease;
    }
    
    .f2f-alert-success {
        background: #c6f6d5;
        color: #2f855a;
        border: 1px solid #9ae6b4;
    }
    
    .f2f-alert-error {
        background: #fed7d7;
        color: #c53030;
        border: 1px solid #feb2b2;
    }
    
    .f2f-alert i {
        margin-right: 8px;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 640px) {
        .f2f-task-form-container {
            padding: 15px;
            margin: 10px;
        }
    }
    </style>
    <?php
    
    return ob_get_clean();
}
add_shortcode( 'f2f_create_task', 'f2f_create_task_shortcode' );

/**
 * AJAX: Criar tarefa no ClickUp (Frontend)
 */
function f2f_ajax_frontend_create_task() {
    // Permite usuários logados e não logados (você pode restringir se quiser)
    if ( ! wp_verify_nonce( $_POST['nonce'], 'f2f_frontend_create_task' ) ) {
        wp_send_json_error( 'Erro de segurança' );
    }
    
    $api = F2F_ClickUp_API::get_instance();
    
    if ( ! $api->is_configured() ) {
        wp_send_json_error( 'API não está configurada' );
    }
    
    $list_id = sanitize_text_field( $_POST['task_list'] );
    
    if ( empty( $list_id ) ) {
        wp_send_json_error( 'ID da lista não foi definido' );
    }
    
    $task_data = array(
        'name' => sanitize_text_field( $_POST['task_name'] ),
        'description' => sanitize_textarea_field( $_POST['task_description'] ),
    );
    
    // Adiciona informações do usuário na descrição
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $task_data['description'] .= "\n\n---\nCriado por: " . $current_user->display_name . " (" . $current_user->user_email . ")";
    } else {
        $task_data['description'] .= "\n\n---\nCriado por: Visitante do site";
    }
    
    // Prioridade
    if ( ! empty( $_POST['task_priority'] ) ) {
        $task_data['priority'] = intval( $_POST['task_priority'] );
    }
    
    // Data de entrega
    if ( ! empty( $_POST['task_due_date'] ) ) {
        $task_data['due_date'] = sanitize_text_field( $_POST['task_due_date'] );
    }
    
    // Tags
    if ( ! empty( $_POST['task_tags'] ) ) {
        $tags = array_map( 'trim', explode( ',', $_POST['task_tags'] ) );
        $task_data['tags'] = array_filter( $tags );
    }
    
    // Adiciona tag identificadora de criação via site
    if ( ! isset( $task_data['tags'] ) ) {
        $task_data['tags'] = array();
    }
    $task_data['tags'][] = 'via-site';
    
    $result = $api->create_task( $list_id, $task_data );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    
    wp_send_json_success( array( 'task' => $result ) );
}
add_action( 'wp_ajax_f2f_frontend_create_task', 'f2f_ajax_frontend_create_task' );
add_action( 'wp_ajax_nopriv_f2f_frontend_create_task', 'f2f_ajax_frontend_create_task' );

/**
 * AJAX: Criar tarefa no ClickUp (Frontend Admin Page)
 */
function f2f_ajax_frontend_create_clickup_task() {
    // Verifica se é administrador
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Você não tem permissão para criar tarefas' );
    }
    
    if ( ! wp_verify_nonce( $_POST['nonce'], 'f2f_create_clickup_task_frontend' ) ) {
        wp_send_json_error( 'Erro de segurança' );
    }
    
    $api = F2F_ClickUp_API::get_instance();
    
    if ( ! $api->is_configured() ) {
        wp_send_json_error( 'API não está configurada. Verifique se o token foi salvo em Configurações > ClickUp API.' );
    }
    
    // Debug: Log da requisição
    error_log( 'F2F ClickUp Debug - Dados recebidos: ' . print_r( $_POST, true ) );
    
    $list_id = sanitize_text_field( $_POST['task_list'] );
    
    if ( empty( $list_id ) ) {
        $list_id = get_option( 'f2f_clickup_default_list', '' );
    }
    
    if ( empty( $list_id ) ) {
        wp_send_json_error( 'ID da lista não foi definido' );
    }
    
    $task_data = array(
        'name' => sanitize_text_field( $_POST['task_name'] ),
        'description' => sanitize_textarea_field( $_POST['task_description'] ),
    );
    
    // Adiciona informações do criador
    $current_user = wp_get_current_user();
    $task_data['description'] .= "\n\n---\n👤 Criado por: " . $current_user->display_name . " (" . $current_user->user_email . ")";
    $task_data['description'] .= "\n🌐 Via: Dashboard F2F";
    $task_data['description'] .= "\n📅 Data: " . date_i18n( 'd/m/Y H:i:s' );
    
    // Prioridade
    if ( ! empty( $_POST['task_priority'] ) ) {
        $task_data['priority'] = intval( $_POST['task_priority'] );
    }
    
    // Data de início
    if ( ! empty( $_POST['task_start_date'] ) ) {
        $task_data['start_date'] = sanitize_text_field( $_POST['task_start_date'] );
    }
    
    // Data de entrega
    if ( ! empty( $_POST['task_due_date'] ) ) {
        $task_data['due_date'] = sanitize_text_field( $_POST['task_due_date'] );
    }
    
    // Tags
    if ( ! empty( $_POST['task_tags'] ) ) {
        $tags = array_map( 'trim', explode( ',', $_POST['task_tags'] ) );
        $task_data['tags'] = array_filter( $tags );
    }
    
    // Adiciona tag identificadora
    if ( ! isset( $task_data['tags'] ) ) {
        $task_data['tags'] = array();
    }
    $task_data['tags'][] = 'dashboard-f2f';
    
    // Atribuídos
    if ( ! empty( $_POST['task_assignees'] ) ) {
        $assignees = $_POST['task_assignees'];
        if ( is_array( $assignees ) ) {
            $task_data['assignees'] = array_map( 'intval', $assignees );
        } else {
            $task_data['assignees'] = array( intval( $assignees ) );
        }
    }
    
    // Debug: Log dos dados da tarefa
    error_log( 'F2F ClickUp Debug - List ID: ' . $list_id );
    error_log( 'F2F ClickUp Debug - Task Data: ' . print_r( $task_data, true ) );
    
    $result = $api->create_task( $list_id, $task_data );
    
    // Debug: Log do resultado
    error_log( 'F2F ClickUp Debug - Result: ' . print_r( $result, true ) );
    
    if ( is_wp_error( $result ) ) {
        error_log( 'F2F ClickUp Debug - Error: ' . $result->get_error_message() );
        wp_send_json_error( $result->get_error_message() );
    }
    
    wp_send_json_success( array( 'task' => $result ) );
}
add_action( 'wp_ajax_f2f_frontend_create_clickup_task', 'f2f_ajax_frontend_create_clickup_task' );

/**
 * AJAX: Testar conexão com ClickUp API
 */
function f2f_ajax_test_clickup_connection() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Sem permissão' );
    }
    
    $api = F2F_ClickUp_API::get_instance();
    
    // Debug: Verificar token
    $token = get_option( 'f2f_clickup_api_token', '' );
    error_log( 'F2F ClickUp Debug - Token salvo: ' . ( ! empty( $token ) ? 'SIM (primeiros 10 chars: ' . substr( $token, 0, 10 ) . '...)' : 'NÃO' ) );
    
    if ( ! $api->is_configured() ) {
        wp_send_json_error( 'API não está configurada. Token: ' . ( ! empty( $token ) ? 'Existe' : 'Não existe' ) );
    }
    
    $result = $api->test_connection();
    
    if ( is_wp_error( $result ) ) {
        error_log( 'F2F ClickUp Debug - Erro no teste de conexão: ' . $result->get_error_message() );
        wp_send_json_error( $result->get_error_message() );
    }
    
    error_log( 'F2F ClickUp Debug - Teste de conexão OK: ' . print_r( $result, true ) );
    wp_send_json_success( array( 'user' => $result ) );
}
add_action( 'wp_ajax_f2f_test_clickup_connection', 'f2f_ajax_test_clickup_connection' );

/**
 * AJAX: Debug - Verificar configuração
 */
function f2f_ajax_debug_clickup_config() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Sem permissão' );
    }
    
    $token = get_option( 'f2f_clickup_api_token', '' );
    $default_list = get_option( 'f2f_clickup_default_list', '' );
    $default_workspace = get_option( 'f2f_clickup_default_workspace', '' );
    
    $api = F2F_ClickUp_API::get_instance();
    
    wp_send_json_success( array(
        'token_exists' => ! empty( $token ),
        'token_preview' => ! empty( $token ) ? substr( $token, 0, 10 ) . '...' : 'N/A',
        'default_list' => $default_list,
        'default_workspace' => $default_workspace,
        'api_configured' => $api->is_configured(),
        'ajaxurl' => admin_url( 'admin-ajax.php' )
    ) );
}
add_action( 'wp_ajax_f2f_debug_clickup_config', 'f2f_ajax_debug_clickup_config' );

/**
 * AJAX: Obter membros do workspace
 */
function f2f_ajax_get_workspace_members() {
    // Aumenta o tempo limite para 30 segundos
    set_time_limit( 30 );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Sem permissão' );
    }
    
    $api = F2F_ClickUp_API::get_instance();
    
    if ( ! $api->is_configured() ) {
        wp_send_json_error( 'API não está configurada' );
    }
    
    $workspace_id = get_option( 'f2f_clickup_default_workspace', '' );
    
    // Debug: Log do workspace ID
    error_log( 'F2F ClickUp Debug - Workspace ID: ' . $workspace_id );
    
    if ( empty( $workspace_id ) ) {
        wp_send_json_error( 'Workspace padrão não configurado' );
    }
    
    try {
        $members = $api->get_team_members( $workspace_id );
        
        // Debug: Log dos membros
        error_log( 'F2F ClickUp Debug - Members result: ' . print_r( $members, true ) );
        
        if ( is_wp_error( $members ) ) {
            error_log( 'F2F ClickUp Debug - Members error: ' . $members->get_error_message() );
            wp_send_json_error( $members->get_error_message() );
        }
        
        // Se não encontrou membros, tenta método alternativo
        if ( empty( $members ) ) {
            error_log( 'F2F ClickUp Debug - No members found, trying alternative method' );
            $members = $api->get_team_members_alt( $workspace_id );
            
            if ( is_wp_error( $members ) ) {
                error_log( 'F2F ClickUp Debug - Alternative method error: ' . $members->get_error_message() );
                wp_send_json_error( 'Erro ao buscar membros: ' . $members->get_error_message() );
            }
        }
        
        wp_send_json_success( array( 'members' => $members ) );
        
    } catch ( Exception $e ) {
        error_log( 'F2F ClickUp Debug - Exception: ' . $e->getMessage() );
        wp_send_json_error( 'Erro interno: ' . $e->getMessage() );
    }
}
add_action( 'wp_ajax_f2f_get_workspace_members', 'f2f_ajax_get_workspace_members' );

/**
 * AJAX: Teste simples de membros (sem API)
 */
function f2f_ajax_test_members() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Sem permissão' );
    }
    
    // Retorna membros fictícios para teste
    $test_members = array(
        array(
            'user' => array(
                'id' => '123',
                'username' => 'João Silva',
                'email' => 'joao@exemplo.com'
            )
        ),
        array(
            'user' => array(
                'id' => '456',
                'username' => 'Maria Santos',
                'email' => 'maria@exemplo.com'
            )
        )
    );
    
    wp_send_json_success( array( 'members' => $test_members ) );
}
add_action( 'wp_ajax_f2f_test_members', 'f2f_ajax_test_members' );

/**
 * AJAX: Listar todos os workspaces
 */
function f2f_ajax_list_workspaces() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Sem permissão' );
    }
    
    $api = F2F_ClickUp_API::get_instance();
    
    if ( ! $api->is_configured() ) {
        wp_send_json_error( 'API não está configurada' );
    }
    
    $workspaces = $api->get_workspaces();
    
    if ( is_wp_error( $workspaces ) ) {
        wp_send_json_error( $workspaces->get_error_message() );
    }
    
    wp_send_json_success( array( 'workspaces' => $workspaces ) );
}
add_action( 'wp_ajax_f2f_list_workspaces', 'f2f_ajax_list_workspaces' );

/**
 * Cria automaticamente a página "Criar Tarefa" na ativação do tema
 */
function f2f_create_clickup_task_page() {
    // Verifica se a página já existe
    $existing_page = get_page_by_path( 'criar-tarefa-clickup' );
    
    if ( ! $existing_page ) {
        // Cria a página
        $page_data = array(
            'post_title'    => 'Criar Tarefa ClickUp',
            'post_content'  => '<!-- Esta página usa o template personalizado para criar tarefas no ClickUp -->',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'criar-tarefa-clickup',
            'post_author'   => 1,
        );
        
        $page_id = wp_insert_post( $page_data );
        
        if ( $page_id ) {
            // Define o template personalizado
            update_post_meta( $page_id, '_wp_page_template', 'page-criar-tarefa.php' );
            
            // Salva a opção indicando que foi criada
            update_option( 'f2f_clickup_page_created', true );
        }
    }
}

// Cria a página na ativação do tema
add_action( 'after_switch_theme', 'f2f_create_clickup_task_page' );

// Cria a página no init se ainda não existir
add_action( 'init', function() {
    if ( ! get_option( 'f2f_clickup_page_created' ) ) {
        f2f_create_clickup_task_page();
    }
}, 20 );

/**
 * Adiciona link no menu admin para a página de criar tarefa
 */
function f2f_add_clickup_task_menu_link() {
    // Adiciona link apenas se o usuário for admin
    if ( current_user_can( 'manage_options' ) ) {
        add_menu_page(
            'Criar Tarefa ClickUp',
            'Nova Tarefa',
            'manage_options',
            'criar-tarefa-clickup',
            function() {
                // Redireciona para a página frontend
                wp_redirect( home_url( '/criar-tarefa-clickup/' ) );
                exit;
            },
            'dashicons-plus-alt',
            3 // Posição logo após Dashboard
        );
    }
}
add_action( 'admin_menu', 'f2f_add_clickup_task_menu_link' );

/**
 * Adiciona botão flutuante para criar tarefa (apenas para administradores)
 */
function f2f_add_floating_task_button() {
    // Apenas para administradores logados
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Não mostrar na própria página de criar tarefa
    if ( is_page( 'criar-tarefa-clickup' ) ) {
        return;
    }
    ?>
    <style>
    .f2f-floating-task-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 999;
        text-decoration: none;
        font-size: 24px;
    }
    
    .f2f-floating-task-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        color: white;
        text-decoration: none;
    }
    
    .f2f-floating-task-btn .tooltip-text {
        position: absolute;
        right: 70px;
        background: #2d3748;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        white-space: nowrap;
        font-size: 14px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    
    .f2f-floating-task-btn:hover .tooltip-text {
        opacity: 1;
    }
    
    .f2f-floating-task-btn .tooltip-text::after {
        content: '';
        position: absolute;
        right: -8px;
        top: 50%;
        transform: translateY(-50%);
        border-left: 8px solid #2d3748;
        border-top: 8px solid transparent;
        border-bottom: 8px solid transparent;
    }
    
    @media (max-width: 768px) {
        .f2f-floating-task-btn {
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
        
        .f2f-floating-task-btn .tooltip-text {
            display: none;
        }
    }
    </style>
    
    <a href="<?php echo home_url( '/criar-tarefa-clickup/' ); ?>" 
       class="f2f-floating-task-btn"
       title="Criar Nova Tarefa no ClickUp">
        <i class="fas fa-plus"></i>
        <span class="tooltip-text">Criar Tarefa</span>
    </a>
    <?php
}
add_action( 'wp_footer', 'f2f_add_floating_task_button' );

/**
 * Adiciona item no menu de navegação para administradores
 */
function f2f_add_task_menu_item( $items, $args ) {
    // Apenas para administradores logados
    if ( ! current_user_can( 'manage_options' ) ) {
        return $items;
    }
    
    // Adiciona apenas no menu principal
    if ( $args->theme_location == 'main-menu' ) {
        $task_link = '<li class="nav-item">' .
                    '<a href="' . home_url( '/criar-tarefa-clickup/' ) . '" class="nav-link f2f-task-menu-link">' .
                    '<i class="fas fa-plus-circle me-1"></i> Nova Tarefa' .
                    '</a>' .
                    '</li>';
        
        // Adiciona antes do último item (geralmente é logout ou config)
        $items = $items . $task_link;
    }
    
    return $items;
}
add_filter( 'wp_nav_menu_items', 'f2f_add_task_menu_item', 10, 2 );

/**
 * Adiciona estilos para o item de menu
 */
function f2f_task_menu_styles() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <style>
    .f2f-task-menu-link {
        background: linear-gradient(135deg, #667eea, #764ba2) !important;
        color: white !important;
        border-radius: 6px !important;
        padding: 8px 16px !important;
        margin: 0 5px !important;
        transition: all 0.3s ease !important;
    }
    
    .f2f-task-menu-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    @media (max-width: 991px) {
        .f2f-task-menu-link {
            margin: 5px 0 !important;
            display: inline-block !important;
        }
    }
    </style>
    <?php
}
add_action( 'wp_head', 'f2f_task_menu_styles' );


/**
 * Cria automaticamente a página "Responsáveis"
 */
function f2f_create_responsaveis_page() {
    // Verifica se a página já existe
    $existing_page = get_page_by_path( 'responsaveis-clickup' );
    
    if ( ! $existing_page ) {
        // Cria a página
        $page_data = array(
            'post_title'    => 'Responsáveis',
            'post_content'  => '<!-- Esta página mostra todos os responsáveis do ClickUp com suas métricas -->',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'responsaveis-clickup',
            'post_author'   => 1,
        );
        
        $page_id = wp_insert_post( $page_data );
        
        if ( $page_id ) {
            // Define o template personalizado
            update_post_meta( $page_id, '_wp_page_template', 'page-responsaveis.php' );
            
            // Salva a opção indicando que foi criada
            update_option( 'f2f_responsaveis_page_created', true );
        }
    }
}

// Cria a página na ativação do tema
add_action( 'after_switch_theme', 'f2f_create_responsaveis_page' );

// Cria a página no init se ainda não existir
add_action( 'init', function() {
    if ( ! get_option( 'f2f_responsaveis_page_created' ) ) {
        f2f_create_responsaveis_page();
    }
}, 20 );


/**
 * ========================================
 * TASKROW API INTEGRATION
 * ========================================
 */

// Incluir a integração do Taskrow
require_once get_template_directory() . '/inc/taskrow-integration.php';
