<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<?php wp_head(); ?>
</head>

<?php
$navbar_scheme   = get_theme_mod('navbar_scheme', 'navbar-light bg-light'); // Get custom meta-value.
$navbar_position = get_theme_mod('navbar_position', 'static'); // Get custom meta-value.

$search_enabled  = get_theme_mod('search_enabled', '1'); // Get custom meta-value.

// Verificar se um cliente está logado
$is_client_logged_in = false;
$logged_client_name = null;
if (class_exists('F2F_Client_Auth')) {
    $client_auth = F2F_Client_Auth::get_instance();
    $is_client_logged_in = $client_auth->is_client_logged_in();
    $logged_client_name = $client_auth->get_logged_client_name();
}
?>

<body <?php body_class(); ?>>

	<?php wp_body_open(); ?>

	<a href="#main" class="visually-hidden-focusable"><?php esc_html_e('Skip to main content', 'f2fdashboard'); ?></a>

	<div id="wrapper">
		<header>
			<nav id="header" class="navbar navbar-expand-md <?php echo esc_attr($navbar_scheme);
															if (isset($navbar_position) && 'fixed_top' === $navbar_position) : echo ' fixed-top';
															elseif (isset($navbar_position) && 'fixed_bottom' === $navbar_position) : echo ' fixed-bottom';
															endif;
															if (is_home() || is_front_page()) : echo ' home';
															endif; ?>">
				<div class="container">
					<a class="navbar-brand" href="<?php echo $is_client_logged_in ? '#' : esc_url(home_url()); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home" <?php echo $is_client_logged_in ? 'onclick="return false;"' : ''; ?>>
						<?php
						$header_logo = get_theme_mod('header_logo'); // Get custom meta-value.
						$f2f_logo_url = get_theme_file_uri('assets/logo (1).png');

						if (! empty($header_logo)) :
						?>
							<img src="<?php echo esc_url($header_logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" class="f2f-logo" />
						<?php
						else :
							// Logo PNG da F2F
						?>
							<div class="f2f-logo-container">
								<img src="<?php echo esc_url($f2f_logo_url); ?>" alt="F2F Logo" class="f2f-logo-img" />
							</div>
						<?php
						endif;
						?>
					</a>

					<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="<?php esc_attr_e('Toggle navigation', 'f2fdashboard'); ?>">
						<span class="navbar-toggler-icon"></span>
					</button>

					<div id="navbar" class="collapse navbar-collapse">
						<?php if ($is_client_logged_in) : ?>
							<!-- Menu simplificado para clientes logados -->
							<ul class="navbar-nav me-auto">
								<li class="nav-item">
									<span class="client-name-display">
										<?php echo esc_html($logged_client_name); ?>
									</span>
								</li>
							</ul>
						<?php else : ?>
							<?php
							// Loading WordPress Custom Menu (theme_location) para admins.
							wp_nav_menu(
								array(
									'menu_class'     => 'navbar-nav me-auto',
									'container'      => '',
									'fallback_cb'    => 'WP_Bootstrap_Navwalker::fallback',
									'walker'         => new WP_Bootstrap_Navwalker(),
									'theme_location' => 'main-menu',
								)
							);
							?>
							
							<!-- Link para Demandas Taskrow -->
							<?php if (current_user_can('manage_options')) : ?>
								<ul class="navbar-nav">
									<li class="nav-item">
										<a class="nav-link" href="<?php echo esc_url(get_permalink(get_page_by_path('demandas-taskrow'))); ?>">
											<i class="fas fa-tasks me-1"></i> Demandas Taskrow
										</a>
									</li>
								</ul>
							<?php endif; ?>

							<?php
							if ('1' === $search_enabled) :
							?>

							<?php
							endif;
							?>
						<?php endif; ?>
					</div><!-- /.navbar-collapse -->
				</div><!-- /.container -->
			</nav><!-- /#header -->
		</header>

		<main id="main" class="container" <?php if (isset($navbar_position) && 'fixed_top' === $navbar_position) : echo ' style="padding-top: 100px;"';
											elseif (isset($navbar_position) && 'fixed_bottom' === $navbar_position) : echo ' style="padding-bottom: 100px;"';
											endif; ?>>
			<?php
			// If Single or Archive (Category, Tag, Author or a Date based page).
			if (is_single() || is_archive()) :
			?>
				<div class="row">
					<div class="<?php echo is_singular('cliente') ? 'col-md-12' : 'col-md-8'; ?> col-sm-12">
					<?php
				endif;
					?>