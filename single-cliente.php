<?php
/**
 * Template single para CPT Cliente
 */

get_header();

$dashboard = F2F_Dashboard::get_instance();

// Obter o nome do cliente do título da página
$client_name = get_the_title();

// Verificar se um cliente está logado
$client_auth = F2F_Client_Auth::get_instance();
$is_client_logged_in = $client_auth->is_client_logged_in($client_name);
$logged_client_name = $client_auth->get_logged_client_name();

// Se um cliente está logado mas não é o cliente correto, redirecionar
if ($client_auth->is_client_logged_in() && !$is_client_logged_in) {
    $correct_client_page = get_page_by_title($logged_client_name);
    if ($correct_client_page) {
        wp_redirect(get_permalink($correct_client_page->ID));
        exit;
    }
}

// Filtros de data – padrão mês atual
$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date( 'Y-m-01' );
$end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : date( 'Y-m-t' );

// Converte para formato datetime mysql
$start_dt = $start_date ? date( 'Y-m-d 00:00:00', strtotime( $start_date ) ) : null;
$end_dt = $end_date ? date( 'Y-m-d 23:59:59', strtotime( $end_date ) ) : null;

// Debug: Log das informações
error_log( 'F2F Single Cliente Debug:' );
error_log( 'Client Name: ' . $client_name );
error_log( 'Start Date: ' . $start_dt );
error_log( 'End Date: ' . $end_dt );

// Verificar se os métodos existem antes de chamá-los
$totals = array( 'seconds' => 0, 'count' => 0 );
$assignees = array();
$tasks = array();

if ( method_exists( $dashboard, 'get_client_totals_in_period' ) ) {
    $totals = $dashboard->get_client_totals_in_period( $client_name, $start_dt, $end_dt );
    error_log( 'Totals result: ' . print_r( $totals, true ) );
}

if ( method_exists( $dashboard, 'get_assignees_by_client_in_period' ) ) {
    $assignees = $dashboard->get_assignees_by_client_in_period( $client_name, $start_dt, $end_dt );
    error_log( 'Assignees result: ' . print_r( $assignees, true ) );
}

if ( method_exists( $dashboard, 'get_recent_tasks' ) ) {
    $tasks = $dashboard->get_recent_tasks( 30, $start_dt, $end_dt, $client_name, null );
    error_log( 'Tasks result: ' . print_r( $tasks, true ) );
}

// Helper inline para formatar 00h 00m 00s
function f2f_fmt_hms_space( $seconds ) {
    $seconds = (int) $seconds;
    $h = floor( $seconds / 3600 );
    $m = floor( ( $seconds % 3600 ) / 60 );
    $s = $seconds % 60;
    return sprintf( '%02dh %02dm %02ds', $h, $m, $s );
}
?>

<div class="container my-4 mb-0">
    <!-- Cliente Header -->
    <div class="client-header mb-4">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="client-icon me-3">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <img src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ); ?>" alt="<?php echo esc_attr( $client_name ); ?>" />
                    <?php else : ?>
                        <i class="fas fa-building"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="mb-0"><?php echo esc_html( $client_name ); ?></h1>
                    <p class="text-muted mb-0">Detalhes do Cliente</p>
                </div>
            </div>
            
            <?php if ( current_user_can('manage_options') && !$is_client_logged_in ) : ?>
            <div class="admin-actions">
                <a href="<?php echo admin_url('edit.php?post_type=cliente'); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> Admin
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ( $is_client_logged_in ) : ?>
            <div class="client-actions">
                <a href="<?php echo add_query_arg('f2f_client_logout', '1', home_url()); ?>" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros de Data -->
    <div class="card date-filter-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="start_date" class="form-label"><i class="fas fa-calendar-day me-1"></i> Data Início</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo esc_attr( $start_date ); ?>" />
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label"><i class="fas fa-calendar-check me-1"></i> Data Fim</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo esc_attr( $end_date ); ?>" />
                </div>
                <div class="col-md-4">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Aplicar</button>
                        <a href="<?php echo esc_url( get_permalink() ); ?>" class="btn btn-outline-secondary"><i class="fas fa-redo me-1"></i> Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card client-kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon-circle me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-2">
                                <i class="fas fa-stopwatch me-2"></i>Horas Totais
                            </h5>
                            <p class="card-text mb-0">
                                <strong><?php echo f2f_fmt_hms_space( $totals['seconds'] ); ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card client-kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon-circle me-3">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-2">
                                <i class="fas fa-list-check me-2"></i>Total de Tarefas
                            </h5>
                            <p class="card-text mb-0">
                                <strong><?php echo (int) $totals['count']; ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabelas de Dados -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-user-tie"></i> Por Responsável
                    </h5>
        <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user me-1"></i> Responsável</th>
                                    <th><i class="fas fa-clock me-1"></i> Horas Totais</th>
                                    <th><i class="fas fa-tasks me-1"></i> Tarefas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $assignees ) ) : ?>
                                    <?php foreach ( $assignees as $row ) : ?>
                                        <tr>
                                            <td><strong><?php echo esc_html( $row->assignee ); ?></strong></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php
                                                    if ( isset( $row->seconds ) ) {
                                                        $secs = (int) $row->seconds;
                                                        $h = floor( $secs / 3600 );
                                                        $m = floor( ( $secs % 3600 ) / 60 );
                                                        echo sprintf( '%02dh %02dm', $h, $m );
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?php echo isset( $row->c ) ? (int) $row->c : '—'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            Sem dados de responsável
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-history"></i> Últimas Tarefas
                    </h5>
                    <div class="table-responsive table-scroll-8">
                        <table class="table table-layout-fixed">
                            <thead>
                                <tr>
                                    <th style="width: 35%;"><i class="fas fa-file-alt me-1"></i> Tarefa</th>
                                    <th style="width: 20%;"><i class="fas fa-toggle-on me-1"></i> Status</th>
                                    <th style="width: 22%;"><i class="fas fa-user me-1"></i> Responsável</th>
                                    <th style="width: 23%;"><i class="fas fa-calendar me-1"></i> Atualizado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $tasks ) ) : ?>
                                    <?php foreach ( $tasks as $t ) : ?>
                                        <tr>
                                            <td>
                                                <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr( $t->name ); ?>">
                                                    <?php echo esc_html( $t->name ); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary" title="<?php echo esc_attr( $t->status ); ?>">
                                                    <?php echo esc_html( $t->status ); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr( $t->assignee ); ?>">
                                                    <?php echo esc_html( $t->assignee ); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-primary" title="<?php echo esc_attr( date_i18n( 'd/m/Y H:i', strtotime( $t->updated_at ) ) ); ?>">
                                                    <i class="fas fa-calendar-check me-1"></i>
                                                    <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $t->updated_at ) ) ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            Sem tarefas recentes
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>