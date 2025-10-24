<?php
/*
Template Name: F2F Dashboard
*/

get_header();

// Verificar se um cliente está logado - se sim, redirecionar para sua página específica
if (class_exists('F2F_Client_Auth')) {
    $client_auth = F2F_Client_Auth::get_instance();
    if ($client_auth->is_client_logged_in()) {
        $logged_client_name = $client_auth->get_logged_client_name();
        
        // Buscar página do cliente logado com mais dados
        $client_page = null;
        
        $query = new WP_Query(array(
            'post_type' => 'cliente',
            's' => $logged_client_name,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $best_page = null;
        $max_data_count = 0;
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $page_title = get_the_title();
                $page_id = get_the_ID();
                
                // Contar quantos dados existem para esta página
                $data_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}f2f_clickup_data WHERE client = %s",
                    $page_title
                ));
                
                // Verificar se tem imagem destacada
                $has_featured_image = has_post_thumbnail($page_id);
                
                // Priorizar página com mais dados E com imagem destacada
                $score = $data_count;
                if ($has_featured_image) {
                    $score += 10; // Bonus menor para páginas com imagem (10 pontos)
                }
                
                // Escolher a página com maior score
                if ($score > $max_data_count) {
                    $max_data_count = $score;
                    $best_page = get_post($page_id);
                }
            }
            wp_reset_postdata();
        }
        
        $client_page = $best_page;
        
        if ($client_page) {
            wp_redirect(get_permalink($client_page->ID));
            exit;
        }
    }
}

global $wpdb;
$table = $wpdb->prefix . 'f2f_clickup_data';
// Usa métodos da classe para manter a lógica centralizada
$dashboard = F2F_Dashboard::get_instance();

// Processa filtros de data
$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : null;
$end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : null;
$filter_client = isset( $_GET['client'] ) ? sanitize_text_field( $_GET['client'] ) : null;
$filter_assignee = isset( $_GET['assignee'] ) ? sanitize_text_field( $_GET['assignee'] ) : null;

// Converte datas para formato MySQL se fornecidas
if ( $start_date ) {
    $start_date = date( 'Y-m-d H:i:s', strtotime( $start_date . ' 00:00:00' ) );
}
if ( $end_date ) {
    $end_date = date( 'Y-m-d H:i:s', strtotime( $end_date . ' 23:59:59' ) );
}


// KPIs básicos com filtros de data.
$total       = $dashboard->get_total_tasks( $start_date, $end_date, $filter_client, $filter_assignee );
$completed   = $dashboard->get_completed_tasks( $start_date, $end_date, $filter_client, $filter_assignee );
$in_progress = $dashboard->get_in_progress_tasks( $start_date, $end_date, $filter_client, $filter_assignee );
$overdue     = $dashboard->get_overdue_tasks( $start_date, $end_date, $filter_client, $filter_assignee );

// Tempo total por responsável: somar apenas colunas de tempo que existirem na tabela.
$group = $dashboard->get_data_by_assignee( $start_date, $end_date, $filter_client );
$by_assignee = isset( $group['data'] ) ? $group['data'] : array();
$use_counts_only = isset( $group['use_counts_only'] ) ? (bool) $group['use_counts_only'] : false;

// Tempo total por cliente (preferindo coluna 'client')
$group_proj = $dashboard->get_data_by_project( $start_date, $end_date, $filter_assignee );
$by_client = isset( $group_proj['data'] ) ? $group_proj['data'] : array();
$use_counts_only_proj = isset( $group_proj['use_counts_only'] ) ? (bool) $group_proj['use_counts_only'] : false;

?>
<div class="f2f-dashboard-wrapper">
  <div class="container-fluid px-4 py-4 pb-0">
    
    <!-- Dashboard Header -->
    <div class="dashboard-header mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
          <h1 class="dashboard-title">
            <i class="fas fa-chart-line me-2"></i>
            Dashboard F2F
          </h1>
          <p class="dashboard-subtitle">Acompanhamento de projetos e tarefas em tempo real</p>
        </div>
        <div class="col-md-6 text-md-end">
          <?php if ( $start_date || $end_date ) : ?>
            <div class="active-filter-badge">
              <i class="fas fa-filter me-2"></i>
              <span class="filter-text">
                <?php if ( $start_date ) : ?>
                  <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $start_date ) ) ); ?>
                <?php endif; ?>
                <?php if ( $start_date && $end_date ) : ?> - <?php endif; ?>
                <?php if ( $end_date ) : ?>
                  <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $end_date ) ) ); ?>
                <?php endif; ?>
              </span>
              <a href="<?php echo esc_url( remove_query_arg( array( 'start_date', 'end_date' ) ) ); ?>" class="clear-filter-btn">
                <i class="fas fa-times"></i>
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Avisos de Consumo de Horas -->
    <?php
    // Verifica se algum cliente atingiu 50% ou 90% e mostra avisos
    $warnings = array();
    $temp_client_hours = array(
      'ABRA' => array('contracted' => 49, 'consumed' => 0, 'db_name' => 'Abrafati'),
      'GLP' => array('contracted' => 10, 'consumed' => 0, 'db_name' => '[F] GLP'),
      'MERZ' => array('contracted' => 25, 'consumed' => 0, 'db_name' => 'MERZ'),
      'ALARES' => array('contracted' => 400, 'consumed' => 0, 'db_name' => 'ALARES')
    );
    
    $temp_month_start = date('Y-m-01 00:00:00');
    $temp_month_end = date('Y-m-t 23:59:59');
    
    foreach ($temp_client_hours as $client_name => $hours_data) {
      $search_name = $hours_data['db_name'];
      
      // Usa a MESMA lógica que a página do cliente individual
      // Prioriza time_tracked_seconds, depois task_time_spent_seconds
      $temp_query = $wpdb->prepare(
        "SELECT SUM(IFNULL(time_tracked_seconds, 0)) AS total_seconds
        FROM {$wpdb->prefix}f2f_clickup_data
        WHERE client LIKE %s
        AND (
          (execution_date >= %s AND execution_date <= %s)
          OR (execution_date IS NULL AND start_date >= %s AND start_date <= %s)
          OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= %s AND updated_at <= %s)
        )",
        '%' . $search_name . '%',
        $temp_month_start, $temp_month_end,
        $temp_month_start, $temp_month_end,
        $temp_month_start, $temp_month_end
      );
      
      $total_seconds = $wpdb->get_var($temp_query);
      
      // Se time_tracked_seconds não retornou nada, tenta task_time_spent_seconds
      if (!$total_seconds || $total_seconds == 0) {
        $temp_query = $wpdb->prepare(
          "SELECT SUM(IFNULL(task_time_spent_seconds, 0)) AS total_seconds
          FROM {$wpdb->prefix}f2f_clickup_data
          WHERE client LIKE %s
          AND (
            (execution_date >= %s AND execution_date <= %s)
            OR (execution_date IS NULL AND start_date >= %s AND start_date <= %s)
            OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= %s AND updated_at <= %s)
          )",
          '%' . $search_name . '%',
          $temp_month_start, $temp_month_end,
          $temp_month_start, $temp_month_end,
          $temp_month_start, $temp_month_end
        );
        $total_seconds = $wpdb->get_var($temp_query);
      }
      
      $consumed_hours = $total_seconds > 0 ? round($total_seconds / 3600, 2) : 0;
      $contracted = $hours_data['contracted'];
      $percentage = $contracted > 0 ? ($consumed_hours / $contracted) * 100 : 0;
      
      // Busca a página do cliente para o link
      $client_page_query = new WP_Query(array(
        'post_type' => 'cliente',
        's' => $search_name,
        'posts_per_page' => 1,
        'post_status' => 'publish'
      ));
      
      $client_url = null;
      if ($client_page_query->have_posts()) {
        $client_page_query->the_post();
        $client_url = get_permalink();
        wp_reset_postdata();
      }
      
      // Verifica se atingiu 90% (crítico)
      if ($percentage >= 90 && $percentage < 100) {
        $warnings[] = array(
          'type' => 'danger',
          'icon' => 'exclamation-triangle',
          'client' => $client_name,
          'message' => sprintf('O cliente %s está com %.1f%% das horas consumidas (%.2fh de %dh)', $client_name, $percentage, $consumed_hours, $contracted),
          'url' => $client_url
        );
      }
      // Verifica se ultrapassou 100% (urgente)
      elseif ($percentage >= 100) {
        $warnings[] = array(
          'type' => 'danger',
          'icon' => 'exclamation-circle',
          'client' => $client_name,
          'message' => sprintf('⚠️ ATENÇÃO! O cliente %s ULTRAPASSOU o limite de horas! %.2fh consumidas de %dh contratadas', $client_name, $consumed_hours, $contracted),
          'url' => $client_url
        );
      }
      // Verifica se atingiu 50% (alerta)
      elseif ($percentage >= 50 && $percentage < 90) {
        $warnings[] = array(
          'type' => 'warning',
          'icon' => 'info-circle',
          'client' => $client_name,
          'message' => sprintf('O cliente %s atingiu %.1f%% das horas contratadas (%.2fh de %dh)', $client_name, $percentage, $consumed_hours, $contracted),
          'url' => $client_url
        );
      }
    }
    
    // Exibe os avisos se houver
    if (!empty($warnings)) :
    ?>
    <div class="alert-container mb-4">
      <?php foreach ($warnings as $warning) : ?>
      <div class="alert alert-<?php echo $warning['type']; ?> alert-dismissible fade show d-flex align-items-center" role="alert" style="border-left: 4px solid <?php echo $warning['type'] === 'danger' ? '#dc3545' : '#ffc107'; ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <i class="fas fa-<?php echo $warning['icon']; ?> me-3" style="font-size: 1.5rem;"></i>
        <div class="flex-grow-1">
          <strong><?php echo $warning['message']; ?></strong>
          <?php if ($warning['url']) : ?>
            <br>
            <a href="<?php echo esc_url($warning['url']); ?>" class="alert-link">
              <i class="fas fa-external-link-alt me-1"></i>
              Ver detalhes do cliente
            </a>
          <?php endif; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Painel de Horas por Cliente -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <i class="fas fa-clock text-white me-2" style="font-size: 1.5rem;"></i>
          <h5 class="card-title mb-0 text-white">Horas Contratadas por Cliente</h5>
        </div>
        
        <div class="row g-3">
          <?php
          // Define as horas contratadas por cliente
          // O array usa um nome de exibição e o nome real no banco de dados
          // DEBUG: Vamos buscar os nomes reais dos clientes no banco
          global $wpdb;
          $debug_clients = $wpdb->get_results("SELECT DISTINCT client FROM {$wpdb->prefix}f2f_clickup_data WHERE client LIKE '%MERZ%' OR client LIKE '%ALARES%' OR client LIKE '%Abra%' OR client LIKE '%GLP%' ORDER BY client");
          error_log('=== CLIENTES NO BANCO ===');
          foreach ($debug_clients as $dc) {
            error_log('Cliente: ' . $dc->client);
          }
          error_log('=== FIM DEBUG CLIENTES ===');
          
          $client_hours = array(
            'ABRA' => array('contracted' => 49, 'consumed' => 0, 'db_name' => 'Abrafati'),
            'GLP' => array('contracted' => 10, 'consumed' => 0, 'db_name' => '[F] GLP'),
            'MERZ' => array('contracted' => 25, 'consumed' => 0, 'db_name' => 'MERZ'),
            'ALARES' => array('contracted' => 400, 'consumed' => 0, 'db_name' => 'ALARES')
          );
          
          // Busca as horas consumidas por cada cliente no mês atual
          $current_month_start = date('Y-m-01 00:00:00');
          $current_month_end = date('Y-m-t 23:59:59');
          
          foreach ($client_hours as $client_name => $hours_data) {
            // Usa o nome real do banco de dados para buscar
            $search_name = $hours_data['db_name'];
            
            // Usa a MESMA lógica que a página do cliente individual
            // Prioriza time_tracked_seconds, depois task_time_spent_seconds
            $query = $wpdb->prepare(
              "SELECT SUM(IFNULL(time_tracked_seconds, 0)) AS total_seconds
              FROM {$wpdb->prefix}f2f_clickup_data
              WHERE client LIKE %s
              AND (
                (execution_date >= %s AND execution_date <= %s)
                OR (execution_date IS NULL AND start_date >= %s AND start_date <= %s)
                OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= %s AND updated_at <= %s)
              )",
              '%' . $search_name . '%',
              $current_month_start, $current_month_end,
              $current_month_start, $current_month_end,
              $current_month_start, $current_month_end
            );
            
            $total_seconds = $wpdb->get_var($query);
            
            // Se time_tracked_seconds não retornou nada, tenta task_time_spent_seconds
            if (!$total_seconds || $total_seconds == 0) {
              $query = $wpdb->prepare(
                "SELECT SUM(IFNULL(task_time_spent_seconds, 0)) AS total_seconds
                FROM {$wpdb->prefix}f2f_clickup_data
                WHERE client LIKE %s
                AND (
                  (execution_date >= %s AND execution_date <= %s)
                  OR (execution_date IS NULL AND start_date >= %s AND start_date <= %s)
                  OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= %s AND updated_at <= %s)
                )",
                '%' . $search_name . '%',
                $current_month_start, $current_month_end,
                $current_month_start, $current_month_end,
                $current_month_start, $current_month_end
              );
              $total_seconds = $wpdb->get_var($query);
            }
            
            $consumed_hours = $total_seconds > 0 ? round($total_seconds / 3600, 2) : 0;
            $client_hours[$client_name]['consumed'] = $consumed_hours;
          }
          
          // Exibe os cards
          foreach ($client_hours as $client_name => $hours_data) :
            $contracted = $hours_data['contracted'];
            $consumed = $hours_data['consumed'];
            $percentage = $contracted > 0 ? ($consumed / $contracted) * 100 : 0;
            $remaining = $contracted - $consumed;
            
            // Define a cor da barra de progresso
            if ($percentage >= 90) {
              $progress_color = 'danger';
            } elseif ($percentage >= 70) {
              $progress_color = 'warning';
            } else {
              $progress_color = 'success';
            }
            
            // Busca a página do cliente
            $search_name = $hours_data['db_name'];
            $client_page_query = new WP_Query(array(
              'post_type' => 'cliente',
              's' => $search_name,
              'posts_per_page' => 1,
              'post_status' => 'publish'
            ));
            
            $client_url = null;
            if ($client_page_query->have_posts()) {
              $client_page_query->the_post();
              $client_url = get_permalink();
              wp_reset_postdata();
            }
            
            $card_style = 'background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: none; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);';
            if ($client_url) {
              $card_style .= ' cursor: pointer; transition: all 0.3s ease;';
            }
          ?>
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 client-hours-card" 
                 style="<?php echo $card_style; ?>"
                 <?php if ($client_url) : ?>
                   onclick="window.location.href='<?php echo esc_url($client_url); ?>'"
                   onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 5px 20px rgba(0, 0, 0, 0.2)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0, 0, 0, 0.1)';"
                 <?php endif; ?>>
              <div class="card-body">
                <h6 class="card-title fw-bold text-primary mb-3 d-flex justify-content-between align-items-center">
                  <span>
                    <i class="fas fa-building me-2"></i><?php echo esc_html($client_name); ?>
                  </span>
                  <?php if ($client_url) : ?>
                    <i class="fas fa-external-link-alt text-muted" style="font-size: 0.875rem;"></i>
                  <?php endif; ?>
                </h6>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="text-muted small">Consumido</span>
                  <span class="fw-bold text-dark"><?php echo number_format($consumed, 2, ',', '.'); ?>h</span>
                </div>
                
                <div class="progress mb-2" style="height: 8px;">
                  <div class="progress-bar bg-<?php echo $progress_color; ?>" 
                       role="progressbar" 
                       style="width: <?php echo min($percentage, 100); ?>%;" 
                       aria-valuenow="<?php echo $percentage; ?>" 
                       aria-valuemin="0" 
                       aria-valuemax="100">
                  </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-muted small">
                    <?php echo number_format($percentage, 1, ',', '.'); ?>%
                  </span>
                  <span class="small">
                    <span class="fw-bold <?php echo $remaining < 0 ? 'text-danger' : 'text-success'; ?>">
                      <?php echo $remaining >= 0 ? number_format($remaining, 2, ',', '.') : number_format(abs($remaining), 2, ',', '.'); ?>h
                    </span>
                    <span class="text-muted">/ <?php echo $contracted; ?>h</span>
                  </span>
                </div>
                
                <?php if ($remaining < 0) : ?>
                <div class="alert alert-danger alert-sm mt-2 mb-0 py-1 px-2" style="font-size: 0.75rem;">
                  <i class="fas fa-exclamation-triangle me-1"></i>
                  Limite excedido!
                </div>
                <?php elseif ($percentage >= 90) : ?>
                <div class="alert alert-warning alert-sm mt-2 mb-0 py-1 px-2" style="font-size: 0.75rem;">
                  <i class="fas fa-exclamation-circle me-1"></i>
                  Próximo do limite
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-3">
          <small class="text-white opacity-75">
            <i class="fas fa-info-circle me-1"></i>
            Referente ao mês atual: <?php echo date_i18n('F/Y'); ?>
          </small>
        </div>
      </div>
    </div>
    
    <!-- Filtros de Data / Cliente / Desenvolvedor -->
    <div class="card date-filter-card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <i class="fas fa-calendar-alt filter-icon me-2"></i>
          <h5 class="card-title mb-0">Filtros de Período</h5>
        </div>
        
        <form method="GET" class="date-filter-form" id="date-filter-form">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label for="start_date" class="form-label">
                <i class="fas fa-calendar-day me-1"></i> Data Início
              </label>
              <input type="date" class="form-control" id="start_date" name="start_date" 
                     value="<?php echo esc_attr( isset( $_GET['start_date'] ) ? $_GET['start_date'] : '' ); ?>">
            </div>
            <div class="col-md-3">
              <label for="end_date" class="form-label">
                <i class="fas fa-calendar-check me-1"></i> Data Fim
              </label>
              <input type="date" class="form-control" id="end_date" name="end_date" 
                     value="<?php echo esc_attr( isset( $_GET['end_date'] ) ? $_GET['end_date'] : '' ); ?>">
            </div>
            <div class="col-md-3">
              <label for="client" class="form-label">
                <i class="fas fa-building me-1"></i> Cliente
              </label>
              <select name="client" id="client" class="form-control">
                <option value="">Todos</option>
                <?php foreach ( $dashboard->get_distinct_clients() as $c ) : ?>
                  <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $filter_client, $c ); ?>><?php echo esc_html( $c ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="assignee" class="form-label">
                <i class="fas fa-user me-1"></i> Desenvolvedor
              </label>
              <select name="assignee" id="assignee" class="form-control">
                <option value="">Todos</option>
                <?php foreach ( $dashboard->get_distinct_assignees() as $a ) : ?>
                  <option value="<?php echo esc_attr( $a ); ?>" <?php selected( $filter_assignee, $a ); ?>><?php echo esc_html( $a ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12 col-lg-6">
              <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-search me-1"></i> Aplicar Filtro
                </button>
                <a href="<?php echo esc_url( remove_query_arg( array( 'start_date', 'end_date' ) ) ); ?>" 
                   class="btn btn-outline-secondary">
                  <i class="fas fa-redo me-1"></i> Limpar
                </a>
              </div>
            </div>
          </div>
          
          <!-- Períodos rápidos -->
          <div class="quick-periods mt-3">
            <label class="quick-periods-label">
              <i class="fas fa-bolt me-1"></i> Períodos rápidos:
            </label>
            <div class="quick-periods-buttons">
              <button type="button" class="btn-quick-period" id="btn-today" onclick="setQuickPeriod('today')">
                Hoje
              </button>
              <button type="button" class="btn-quick-period" id="btn-week" onclick="setQuickPeriod('week')">
                Esta Semana
              </button>
              <button type="button" class="btn-quick-period" id="btn-month" onclick="setQuickPeriod('month')">
                Este Mês
              </button>
              <button type="button" class="btn-quick-period" id="btn-quarter" onclick="setQuickPeriod('quarter')">
                Este Trimestre
              </button>
              <button type="button" class="btn-quick-period" id="btn-year" onclick="setQuickPeriod('year')">
                Este Ano
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-3 col-md-6">
        <div class="kpi-card kpi-card-total">
          <div class="kpi-icon">
            <i class="fas fa-tasks"></i>
          </div>
          <div class="kpi-content">
            <h5 class="kpi-label">Total de Tarefas</h5>
            <div class="kpi-value"><?php echo esc_html( $total ); ?></div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="kpi-card kpi-card-progress" id="inProgressKpiCard" style="cursor: pointer;">
          <div class="kpi-icon">
            <i class="fas fa-spinner"></i>
          </div>
          <div class="kpi-content">
            <h5 class="kpi-label">Em Andamento</h5>
            <div class="kpi-value"><?php echo esc_html( $in_progress ); ?></div>
            <?php if ( $total > 0 ) : ?>
              <div class="kpi-progress">
                <div class="progress-bar" style="width: <?php echo round( ( $in_progress / $total ) * 100, 1 ); ?>%"></div>
              </div>
              <small class="kpi-percentage"><?php echo round( ( $in_progress / $total ) * 100, 1 ); ?>%</small>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="kpi-card kpi-card-overdue" style="cursor: pointer;" id="overdueKpiCard" title="Clique para ver detalhes das tarefas atrasadas">
          <div class="kpi-icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div class="kpi-content">
          <h5 class="kpi-label">Atrasadas ou Sem Data</h5>
          <div class="kpi-value"><?php echo esc_html( $overdue ); ?></div>
          <small style="color: #666; font-size: 0.7rem;">Clique para detalhes</small>
            <?php if ( $total > 0 ) : ?>
              <div class="kpi-progress">
                <div class="progress-bar" style="width: <?php echo round( ( $overdue / $total ) * 100, 1 ); ?>%"></div>
              </div>
              <small class="kpi-percentage"><?php echo round( ( $overdue / $total ) * 100, 1 ); ?>%</small>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="kpi-card kpi-card-completed">
          <div class="kpi-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="kpi-content">
            <h5 class="kpi-label">Concluídas</h5>
            <div class="kpi-value"><?php echo esc_html( $completed ); ?></div>
            <?php if ( $total > 0 ) : ?>
              <div class="kpi-progress">
                <div class="progress-bar" style="width: <?php echo round( ( $completed / $total ) * 100, 1 ); ?>%"></div>
              </div>
              <small class="kpi-percentage"><?php echo round( ( $completed / $total ) * 100, 1 ); ?>%</small>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Gráficos de Pizza -->
    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="fas fa-chart-pie me-2"></i>
              Status das Tarefas
            </h5>
          </div>
          <div class="card-body">
            <canvas id="statusChart" width="400" height="400"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="fas fa-users me-2"></i>
              Distribuição por Cliente
            </h5>
          </div>
          <div class="card-body">
            <canvas id="clientChart" width="400" height="400"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabelas de Dados -->
    <div class="row g-4">
      <div class="col-lg-6">
        <h3>
          <i class="fas fa-user-tie"></i> Por Responsável
        </h3>
        <div class="table-responsive mb-4">
          <table class="table">
            <thead>
              <tr>
                <th><i class="fas fa-user me-1"></i> Responsável</th>
                <th><i class="fas fa-clock me-1"></i> Horas Totais</th>
                <th><i class="fas fa-tasks me-1"></i> Tarefas</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( (array) $by_assignee as $row ) : ?>
                <?php
                  $secs = ( ! empty( $row->seconds ) && ! $use_counts_only ) ? (int) $row->seconds : 0;
                  $h = floor( $secs / 3600 );
                  $m = floor( ( $secs % 3600 ) / 60 );
                  $s = $secs % 60;
                  $time_fmt = sprintf( '%02dh %02dm', $h, $m );
                ?>
                <tr>
                  <td>
                    <strong><?php echo esc_html( $row->assignee ? $row->assignee : '—' ); ?></strong>
                  </td>
                  <td>
                    <span class="badge bg-primary"><?php echo esc_html( $time_fmt ); ?></span>
                  </td>
                  <td>
                    <span class="badge bg-info text-dark"><?php echo esc_html( isset( $row->c ) ? (int) $row->c : 0 ); ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $by_assignee ) ) : ?>
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

        <h3>
          <i class="fas fa-building"></i> Por Cliente
        </h3>
        <div class="table-responsive table-scroll-8">
          <table class="table">
            <thead>
              <tr>
                <th><i class="fas fa-briefcase me-1"></i> Cliente</th>
                <th><i class="fas fa-clock me-1"></i> Horas Totais</th>
                <th><i class="fas fa-tasks me-1"></i> Tarefas</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( (array) $by_client as $row ) : ?>
                <?php
                  $secs = ( ! empty( $row->seconds ) && ! $use_counts_only_proj ) ? (int) $row->seconds : 0;
                  $h = floor( $secs / 3600 );
                  $m = floor( ( $secs % 3600 ) / 60 );
                  $s = $secs % 60;
                  $time_fmt_proj = sprintf( '%02dh %02dm', $h, $m );
                ?>
                <tr>
                  <td>
                    <strong><?php echo esc_html( isset($row->client) && $row->client ? $row->client : '—' ); ?></strong>
                  </td>
                  <td>
                    <span class="badge bg-primary"><?php echo esc_html( $time_fmt_proj ); ?></span>
                  </td>
                  <td>
                    <span class="badge bg-info text-dark"><?php echo esc_html( isset( $row->c ) ? (int) $row->c : 0 ); ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $by_client ) ) : ?>
                <tr>
                  <td colspan="3" class="text-center text-muted">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    Sem dados de cliente
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <div class="col-lg-6">
        <h3>
          <i class="fas fa-history"></i> Últimas Atualizações
        </h3>
        <?php $recent = $dashboard->get_recent_tasks( 10, $start_date, $end_date ); ?>
        <div class="table-responsive table-scroll-8">
          <table class="table table-layout-fixed" style="table-layout: fixed; width: 100%;">
            <thead>
              <tr>
                <th style="width: 35%;"><i class="fas fa-file-alt me-1"></i> Tarefa</th>
                <th style="width: 18%;"><i class="fas fa-toggle-on me-1"></i> Status</th>
                <th style="width: 17%;"><i class="fas fa-user me-1"></i> Responsável</th>
                <th style="width: 15%;"><i class="fas fa-building me-1"></i> Cliente</th>
                <th style="width: 15%;"><i class="fas fa-calendar me-1"></i> Data</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( (array) $recent as $r ) : ?>
                <tr class="task-row" style="cursor: pointer;" data-task-id="<?php echo esc_attr( $r->task_id ?? '' ); ?>" data-task-name="<?php echo esc_attr( $r->name ); ?>" data-task-status="<?php echo esc_attr( $r->status ); ?>" data-task-assignee="<?php echo esc_attr( $r->assignee ); ?>" data-task-client="<?php echo esc_attr( isset($r->client) ? $r->client : $r->project ); ?>" data-task-due-date="<?php echo esc_attr( $r->due_date ); ?>">
                  <td>
                    <div class="task-name" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr( $r->name ); ?>">
                      <?php echo esc_html( $r->name ); ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-secondary" title="<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $r->status ); ?></span>
                  </td>
                  <td>
                    <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr( $r->assignee ); ?>">
                      <?php echo esc_html( $r->assignee ); ?>
                    </div>
                  </td>
                  <td>
                    <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr( isset($r->client) ? $r->client : $r->project ); ?>">
                      <?php echo esc_html( isset($r->client) ? $r->client : $r->project ); ?>
                    </div>
                  </td>
                  <td>
                    <?php if ( ! empty( $r->due_date ) ) : 
                        $timestamp = strtotime( $r->due_date );
                        if ( $timestamp !== false && $timestamp > 0 ) : 
                            $today = time();
                            $is_overdue = $timestamp < $today;
                            $days_late = $is_overdue ? ceil(($today - $timestamp) / (24 * 60 * 60)) : 0;
                            ?>
                          <div class="d-flex flex-column">
                            <span class="<?php echo $is_overdue ? 'text-danger' : 'text-primary'; ?> small" title="<?php echo esc_attr( date_i18n( 'd/m/Y H:i', $timestamp ) ); ?>">
                              <?php echo esc_html( date_i18n( 'd/m/Y', $timestamp ) ); ?>
                      </span>
                            <?php if ( $is_overdue ) : ?>
                              <small class="text-danger"><?php echo $days_late; ?>d atraso</small>
                            <?php endif; ?>
                          </div>
                    <?php else : ?>
                          <span class="text-danger small" title="Data inválida">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Inválida
                          </span>
                        <?php endif; ?>
                    <?php else : ?>
                      <span class="text-muted small">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $recent ) ) : ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    Sem registros importados ainda.
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

<!-- Modal para Tarefas em Andamento -->
<div class="modal fade" id="inProgressModal" tabindex="-1" aria-labelledby="inProgressModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" style="margin-top: 3rem;">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="inProgressModalLabel">
          <i class="fas fa-tasks me-2"></i>
          Tarefas em Andamento
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <!-- Dica fixa no topo -->
        <div class="alert alert-info m-0 rounded-0 border-0" style="position: sticky; top: 0; z-index: 20; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
          <i class="fas fa-lightbulb me-2"></i>
          <strong>Dica:</strong> Clique em uma tarefa para selecioná-la, depois use o botão "Ir para ClickUp" para abrir a tarefa específica.
        </div>
        
        <!-- Conteúdo com altura fixa e scroll -->
        <div style="max-height: 500px; overflow-y: auto; overflow-x: hidden;">
          <div id="inProgressContent" class="p-3">
            <div class="text-center py-5">
              <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
              <p class="text-muted mt-3">Carregando tarefas em andamento...</p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-top" style="position: sticky; bottom: 0;">
        <div class="d-flex justify-content-between align-items-center w-100">
          <small class="text-muted">
            <i class="fas fa-list me-1"></i>
            <span id="inProgressCount">0</span> tarefas em andamento
          </small>
          <div>
            <button type="button" class="btn btn-primary me-2" id="goToClickUpBtn" onclick="goToClickUp()">
              <i class="fas fa-external-link-alt me-2"></i>
              Ir para ClickUp
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-2"></i>
              Fechar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal para Detalhes da Tarefa -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskDetailsModalLabel">
          <i class="fas fa-tasks text-primary me-2"></i>
          <span id="taskDetailsTitle">Detalhes da Tarefa</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <strong>Status:</strong> <span id="taskDetailsStatus" class="badge bg-secondary"></span>
          </div>
          <div class="col-md-6">
            <strong>Responsável:</strong> <span id="taskDetailsAssignee"></span>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <strong>Cliente:</strong> <span id="taskDetailsClient"></span>
          </div>
          <div class="col-md-6">
            <strong>Data de Vencimento:</strong> <span id="taskDetailsDueDate"></span>
          </div>
        </div>
        
        <hr>
        
        <h6><i class="fas fa-clock me-2"></i>Horas Trackadas</h6>
        <div id="taskHoursContent">
          <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
            <p class="text-muted mt-2">Carregando horas...</p>
          </div>
        </div>
        
               <div class="mt-3">
                 <button type="button" class="btn btn-primary" id="sendToTaskrowBtn" disabled>
                   <i class="fas fa-paper-plane me-2"></i>
                   Enviar Horas para Taskrow
                   <small class="d-block text-muted">(Funcionalidade futura)</small>
                 </button>
               </div>
             </div>
             <div class="modal-footer">
               <button type="button" class="btn btn-primary me-2" onclick="goToClickUp()">
                 <i class="fas fa-external-link-alt me-2"></i>
                 Ir para ClickUp
               </button>
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                 <i class="fas fa-times me-2"></i>
                 Fechar
               </button>
             </div>
    </div>
  </div>
</div>

<!-- Modal para Tarefas Atrasadas ou Sem Data -->
<div class="modal fade" id="overdueModal" tabindex="-1" aria-labelledby="overdueModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="overdueModalLabel">
          <i class="fas fa-exclamation-triangle text-danger me-2"></i>
          Tarefas Atrasadas ou Sem Data
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 0;">
        <!-- Dica Fixa no Topo -->
        <div style="position: sticky; top: 0; z-index: 20; background: #d1ecf1; border-bottom: 2px solid #0c5460; padding: 15px;">
          <div class="alert alert-info mb-0" style="border: none; background: transparent; padding: 0;">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Dica:</strong> Clique em uma tarefa para selecioná-la, depois use o botão "Ir para ClickUp" para abrir a tarefa específica.
          </div>
        </div>
        
        <!-- Container com Scroll -->
        <div style="max-height: 650px; overflow-y: auto; overflow-x: hidden;">
          <table class="table table-hover mb-0" style="table-layout: fixed; width: 100%;">
            <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
              <tr>
                <th style="width: 35%;"><i class="fas fa-tasks me-1"></i> Tarefa</th>
                <th style="width: 15%;"><i class="fas fa-user me-1"></i> Responsável</th>
                <th style="width: 15%;"><i class="fas fa-building me-1"></i> Cliente</th>
                <th style="width: 15%;"><i class="fas fa-flag me-1"></i> Status</th>
                <th style="width: 10%;"><i class="fas fa-calendar-times me-1"></i> Vencimento</th>
                <th style="width: 10%;"><i class="fas fa-clock me-1"></i> Atraso</th>
              </tr>
            </thead>
            <tbody id="overdueTasksTable">
              <!-- Conteúdo será carregado via AJAX -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary me-2" onclick="goToClickUp()">
          <i class="fas fa-external-link-alt me-2"></i>
          Ir para ClickUp
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-2"></i>
          Fechar
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* Estilos para o modal de tarefas atrasadas */
#overdueModal .table td {
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

#overdueModal .table td:first-child {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#overdueModal .badge {
    font-size: 0.75rem;
    white-space: nowrap;
}

#overdueModal .small {
    font-size: 0.8rem;
}

/* Estilos para a tabela principal do dashboard */
.table-layout-fixed {
    table-layout: fixed !important;
    font-size: 0.9rem;
}

.table-layout-fixed th {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.75rem 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-layout-fixed td {
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
    padding: 0.75rem 0.5rem;
}

.table-layout-fixed .badge {
    font-size: 0.8rem;
    white-space: nowrap;
    padding: 0.35rem 0.6rem;
    max-width: 100%;
    overflow: visible;
    text-overflow: unset;
}

.table-layout-fixed .small {
    font-size: 0.75rem;
}

/* Garantir que a coluna de status não corte o conteúdo */
.table-layout-fixed td:nth-child(2) {
    overflow: visible;
    white-space: nowrap;
}

.table-layout-fixed td:nth-child(2) .badge {
    display: inline-block;
    max-width: none;
    overflow: visible;
}

/* Estilo para linhas clicáveis */
.task-row:hover {
    background-color: #f8f9fa !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.task-row:hover td {
    border-color: #dee2e6;
}

/* Melhorar responsividade */
@media (max-width: 768px) {
    .table-layout-fixed th,
    .table-layout-fixed td {
        font-size: 0.8rem;
        padding: 0.5rem 0.25rem;
    }
    
    .table-layout-fixed .badge {
        font-size: 0.75rem;
        padding: 0.3rem 0.5rem;
    }
}

@media (max-width: 576px) {
    .table-layout-fixed th,
    .table-layout-fixed td {
        font-size: 0.75rem;
        padding: 0.4rem 0.2rem;
    }
}
</style>

<script>
function setQuickPeriod(period) {
    const today = new Date();
    let startDate, endDate;
    
    switch(period) {
        case 'today':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
        case 'week':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            startDate = startOfWeek.toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            startDate = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
    document.getElementById('date-filter-form').submit();
}

// Função para destacar o filtro ativo
function highlightActiveFilter() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    // Remove todas as classes ativas
    document.querySelectorAll('.btn-quick-period').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (!startDate && !endDate) {
        return; // Nenhum filtro ativo
    }
    
    const today = new Date();
    
    // Verifica qual período está ativo
    if (startDate === endDate && startDate === today.toISOString().split('T')[0]) {
        // Hoje
        document.getElementById('btn-today').classList.add('active');
    } else if (startDate && endDate) {
        // Verifica se é esta semana
        const startOfWeek = new Date(today);
        startOfWeek.setDate(today.getDate() - today.getDay());
        const weekStart = startOfWeek.toISOString().split('T')[0];
        const weekEnd = today.toISOString().split('T')[0];
        
        if (startDate === weekStart && endDate === weekEnd) {
            document.getElementById('btn-week').classList.add('active');
        } else {
            // Verifica se é este mês
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            const monthEnd = today.toISOString().split('T')[0];
            
            if (startDate === monthStart && endDate === monthEnd) {
                document.getElementById('btn-month').classList.add('active');
            } else {
                // Verifica se é este trimestre
                const quarter = Math.floor(today.getMonth() / 3);
                const quarterStart = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
                const quarterEnd = today.toISOString().split('T')[0];
                
                if (startDate === quarterStart && endDate === quarterEnd) {
                    document.getElementById('btn-quarter').classList.add('active');
                } else {
                    // Verifica se é este ano
                    const yearStart = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                    const yearEnd = today.toISOString().split('T')[0];
                    
                    if (startDate === yearStart && endDate === yearEnd) {
                        document.getElementById('btn-year').classList.add('active');
                    }
                }
            }
        }
    }
}

// Executa quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    highlightActiveFilter();
    createCharts();
});

// Função para criar os gráficos de pizza
function createCharts() {
    // Dados para o gráfico de status
    const statusData = {
        labels: ['Concluídas', 'Em andamento', 'Atrasadas ou Sem Data'],
        datasets: [{
            data: [<?php echo $completed; ?>, <?php echo $in_progress; ?>, <?php echo $overdue; ?>],
            backgroundColor: [
                '#28a745', // Verde para concluídas
                '#ffc107', // Amarelo para em andamento
                '#dc3545'  // Vermelho para atrasadas
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };

    // Dados para o gráfico de clientes
    const clientData = {
        labels: [<?php 
            $client_labels = array();
            foreach($by_client as $client) {
                $client_labels[] = "'" . esc_js($client->client) . "'";
            }
            echo implode(', ', $client_labels);
        ?>],
        datasets: [{
            data: [<?php 
                $client_values = array();
                foreach($by_client as $client) {
                    $client_values[] = $client->c;
                }
                echo implode(', ', $client_values);
            ?>],
            backgroundColor: [
                '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', 
                '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#17a2b8'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };

    // Configurações dos gráficos
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    };

    // Criar gráfico de status
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'pie',
        data: statusData,
        options: chartOptions
    });

    // Criar gráfico de clientes
    const clientCtx = document.getElementById('clientChart').getContext('2d');
    new Chart(clientCtx, {
        type: 'pie',
        data: clientData,
        options: chartOptions
    });
}

// Função para abrir modal de tarefas atrasadas
function openOverdueModal() {
    const modalElement = document.getElementById('overdueModal');
    if (!modalElement) {
        return;
    }
    
    // Verifica se Bootstrap está disponível
    if (typeof bootstrap === 'undefined') {
        // Fallback: mostra o modal manualmente
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        
        // Adiciona backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
        
        // Carrega os dados das tarefas atrasadas
        loadOverdueTasks();
        return;
    }
    
    try {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Carrega os dados das tarefas atrasadas
        loadOverdueTasks();
    } catch (error) {
        console.error('Erro ao abrir modal:', error);
    }
}

// Função para abrir modal de tarefas em andamento
function openInProgressModal() {
    const modalElement = document.getElementById('inProgressModal');
    if (!modalElement) {
        return;
    }
    
    // Verifica se Bootstrap está disponível
    if (typeof bootstrap === 'undefined') {
        // Fallback: mostra o modal manualmente
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        
        // Adiciona backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
        
        // Carrega os dados das tarefas em andamento
        loadInProgressTasks();
        return;
    }
    
    try {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Carrega os dados das tarefas em andamento
        loadInProgressTasks();
    } catch (error) {
        console.error('Erro ao abrir modal:', error);
    }
}

// Função para carregar tarefas atrasadas via AJAX
function loadOverdueTasks() {
    const tbody = document.getElementById('overdueTasksTable');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>';
    
    // Parâmetros dos filtros atuais - não aplica filtros de data para o modal
    const params = new URLSearchParams(window.location.search);
    const startDate = ''; // Sempre vazio para mostrar todas as tarefas atrasadas
    const endDate = '';   // Sempre vazio para mostrar todas as tarefas atrasadas
    const client = params.get('client') || '';
    const assignee = params.get('assignee') || '';
    
    // Faz requisição AJAX
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'get_overdue_tasks_details',
            start_date: startDate,
            end_date: endDate,
            client: client,
            assignee: assignee,
            nonce: '<?php echo wp_create_nonce('overdue_tasks_nonce'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayOverdueTasks(data.data);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar dados</td></tr>';
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar dados</td></tr>';
    });
}

// Função para exibir as tarefas atrasadas na tabela
function displayOverdueTasks(tasks) {
    const tbody = document.getElementById('overdueTasksTable');
    
    if (tasks.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhuma tarefa atrasada encontrada</td></tr>';
        return;
    }
    
    let html = '';
    tasks.forEach(task => {
        // Validar data de vencimento
        let dueDateDisplay = 'N/A';
        let daysLateDisplay = 'N/A';
        
        if (task.due_date && task.due_date !== '0000-00-00 00:00:00' && task.due_date !== '1969-12-31 21:00:00') {
        const dueDate = new Date(task.due_date);
            
            // Verificar se a data é válida
            if (!isNaN(dueDate.getTime()) && dueDate.getFullYear() > 1970) {
        const today = new Date();
        const daysLate = Math.ceil((today - dueDate) / (1000 * 60 * 60 * 24));
                
                dueDateDisplay = dueDate.toLocaleDateString('pt-BR');
                daysLateDisplay = `${daysLate} dia${daysLate !== 1 ? 's' : ''}`;
            }
        }
        
        html += `
            <tr class="task-row" style="cursor: pointer;" 
                data-task-id="${task.task_id || ''}" 
                data-task-name="${task.name}" 
                data-task-status="${task.status}" 
                data-task-assignee="${task.assignee}" 
                data-task-client="${task.client || task.project}" 
                data-task-due-date="${task.due_date}">
                <td>
                    <div class="fw-bold" title="${task.name}">${task.name}</div>
                </td>
                <td>
                    <span class="badge bg-primary">${task.assignee || 'Não definido'}</span>
                </td>
                <td>
                    <span class="badge bg-info">${task.client || 'Não definido'}</span>
                </td>
                <td>
                    <span class="badge bg-warning">${task.status || 'Não definido'}</span>
                </td>
                <td>
                    <span class="text-muted small">${dueDateDisplay === 'N/A' ? 'Não preenchido' : dueDateDisplay}</span>
                </td>
                <td>
                    <span class="text-muted small">${daysLateDisplay === 'N/A' ? 'Não preenchido' : daysLateDisplay + ' em atraso'}</span>
                </td>
            </tr>
        `;
    });
    
    // Adiciona contador de tarefas no rodapé
    html += `
        <tr>
            <td colspan="6" class="text-center text-muted py-2" style="background: #f8f9fa; position: sticky; bottom: 0;">
                <small class="text-muted fw-bold">
                    <i class="fas fa-list me-1"></i>
                    Total: ${tasks.length} tarefas atrasadas ou sem data
                </small>
            </td>
        </tr>
    `;
    
    tbody.innerHTML = html;
    
    // Adiciona event listeners após renderizar as tarefas
    console.log('Adicionando event listeners para tarefas atrasadas...');
    const overdueTaskRows = tbody.querySelectorAll('.task-row');
    console.log('Tarefas encontradas:', overdueTaskRows.length);
    
    overdueTaskRows.forEach((row, index) => {
        console.log(`Tarefa ${index + 1}:`, row.getAttribute('data-task-id'), row.getAttribute('data-task-name'));
        
        // Remove event listeners existentes para evitar duplicação
        row.removeEventListener('click', handleOverdueTaskClick);
        
        // Adiciona novo event listener
        row.addEventListener('click', handleOverdueTaskClick);
    });
}

// Função específica para lidar com cliques em tarefas atrasadas
function handleOverdueTaskClick(event) {
    console.log('handleOverdueTaskClick chamada');
    event.preventDefault();
    event.stopPropagation();
    
    const taskId = this.getAttribute('data-task-id');
    const taskName = this.getAttribute('data-task-name');
    
    console.log('Tarefa clicada:', { taskId, taskName });
    
    // Chama a função de seleção
    selectTaskForClickUp(taskId, taskName);
}

// Função para abrir modal de detalhes da tarefa
function openTaskDetailsModal(taskId, taskName, taskStatus, taskAssignee, taskClient, taskDueDate) {
    // Preenche os dados básicos da tarefa
    // Inclui o task_id no título para facilitar a extração
    document.getElementById('taskDetailsTitle').textContent = `${taskName} | #${taskId}`;
    document.getElementById('taskDetailsStatus').textContent = taskStatus;
    document.getElementById('taskDetailsAssignee').textContent = taskAssignee || 'Não definido';
    document.getElementById('taskDetailsClient').textContent = taskClient || 'Não definido';
    
    // Armazena o task_id em um data attribute do modal para facilitar o acesso
    const modal = document.getElementById('taskDetailsModal');
    if (modal) {
        modal.setAttribute('data-current-task-id', taskId);
    }
    
    // Formata a data de vencimento
    let dueDateDisplay = 'Não preenchido';
    if (taskDueDate && taskDueDate !== '0000-00-00 00:00:00') {
        const timestamp = new Date(taskDueDate).getTime();
        if (!isNaN(timestamp) && timestamp > 0) {
            const date = new Date(taskDueDate);
            dueDateDisplay = date.toLocaleDateString('pt-BR');
        }
    }
    document.getElementById('taskDetailsDueDate').textContent = dueDateDisplay;
    
    // Abre o modal
    const modalElement = document.getElementById('taskDetailsModal');
    if (typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        // Fallback manual
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
    }
    
    // Carrega as horas da tarefa
    loadTaskHours(taskId);
}




// Função para carregar tarefas em andamento via AJAX
function loadInProgressTasks() {
    const contentDiv = document.getElementById('inProgressContent');
    contentDiv.innerHTML = `
        <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
            <p class="text-muted mt-2">Carregando tarefas em andamento...</p>
        </div>
    `;
    
    // Faz requisição AJAX para buscar tarefas em andamento
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        },
        body: new URLSearchParams({
            action: 'get_in_progress_tasks',
            _t: Date.now() // Cache busting timestamp
        })
    })
    .then(response => {
        console.log('Status da resposta:', response.status);
        console.log('Headers da resposta:', response.headers);
        
        // Verifica se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta não é JSON válido');
        }
        
        return response.text().then(text => {
            console.log('Resposta bruta:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Erro ao fazer parse do JSON:', e);
                console.error('Texto recebido:', text);
                throw new Error('Resposta não é JSON válido: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        console.log('Resposta AJAX tarefas em andamento:', data);
        if (data.success) {
            console.log('Tarefas recebidas:', data.data);
            displayInProgressTasks(data.data);
        } else {
            console.error('Erro na resposta:', data);
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erro ao carregar tarefas em andamento: ${data.data || 'Erro desconhecido'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        contentDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Erro na requisição: ${error.message}
                <br><small>Verifique o console para mais detalhes.</small>
            </div>
        `;
    });
}

// Função para exibir tarefas em andamento
function displayInProgressTasks(tasks) {
    const contentDiv = document.getElementById('inProgressContent');
    const countSpan = document.getElementById('inProgressCount');
    
    // Atualiza o contador no rodapé
    if (countSpan) {
        countSpan.textContent = tasks ? tasks.length : 0;
    }
    
    if (!tasks || tasks.length === 0) {
        contentDiv.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Nenhuma tarefa em andamento</h5>
                <p class="text-muted">Todas as tarefas estão concluídas ou aguardando aprovação.</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="width: 35%;">Tarefa</th>
                        <th style="width: 18%;">Status</th>
                        <th style="width: 17%;">Responsável</th>
                        <th style="width: 15%;">Cliente</th>
                        <th style="width: 15%;">Data</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    tasks.forEach(task => {
        const dueDate = task.due_date ? new Date(task.due_date) : null;
        let dateDisplay = '-';
        
        if (dueDate && !isNaN(dueDate.getTime())) {
            const today = new Date();
            const diffTime = dueDate.getTime() - today.getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) {
                dateDisplay = `<span class="text-danger">${Math.abs(diffDays)}d atraso</span>`;
            } else if (diffDays === 0) {
                dateDisplay = '<span class="text-warning">Hoje</span>';
            } else {
                dateDisplay = dueDate.toLocaleDateString('pt-BR');
            }
        }
        
        html += `
            <tr class="task-row" style="cursor: pointer; transition: all 0.2s ease;" 
                data-task-id="${task.task_id || ''}" 
                data-task-name="${task.name}" 
                data-task-status="${task.status}" 
                data-task-assignee="${task.assignee}" 
                data-task-client="${task.client || task.project}" 
                data-task-due-date="${task.due_date}"
                onclick="selectTaskForClickUp('${task.task_id || ''}', '${task.name}')"
                onmouseover="this.style.backgroundColor='#f8f9fa'"
                onmouseout="this.style.backgroundColor=''">
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tasks text-primary me-2"></i>
                        <strong>${task.name}</strong>
                    </div>
                </td>
                <td>
                    <span class="badge bg-success fs-6">${task.status}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user text-muted me-2"></i>
                        ${task.assignee || '-'}
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-building text-muted me-2"></i>
                        ${task.client || task.project || '-'}
                    </div>
                </td>
                <td>${dateDisplay}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    contentDiv.innerHTML = html;
}

// Variável global para armazenar a tarefa selecionada
let selectedTaskForClickUp = null;

// Função para selecionar uma tarefa para ir ao ClickUp
function selectTaskForClickUp(taskId, taskName) {
    console.log('selectTaskForClickUp chamada:', { taskId, taskName });
    
    selectedTaskForClickUp = { id: taskId, name: taskName };
    
    // Remove seleção anterior
    document.querySelectorAll('.task-row').forEach(row => {
        row.classList.remove('table-active');
    });
    
    // Adiciona seleção na linha clicada
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('table-active');
        console.log('Linha selecionada:', event.currentTarget);
    }
    
    // Atualiza o botão para mostrar qual tarefa foi selecionada
    const goToClickUpBtn = document.getElementById('goToClickUpBtn');
    if (goToClickUpBtn) {
        goToClickUpBtn.innerHTML = `
            <i class="fas fa-external-link-alt me-2"></i>
            Ir para: ${taskName}
        `;
        console.log('Botão atualizado com:', taskName);
    }
}

// Função para ir ao ClickUp
function goToClickUp() {
    console.log('Função goToClickUp chamada');
    
    // URL base do ClickUp
    const clickupBaseUrl = 'https://app.clickup.com';
    
    // Prioridade 1: Tarefa selecionada pelo usuário
    let taskId = '';
    if (selectedTaskForClickUp && selectedTaskForClickUp.id) {
        taskId = selectedTaskForClickUp.id;
        console.log('Usando tarefa selecionada:', selectedTaskForClickUp);
    }
    
    // Prioridade 2: Modal de detalhes da tarefa (já tem tarefa específica)
    if (!taskId) {
        const taskDetailsModal = document.getElementById('taskDetailsModal');
        if (taskDetailsModal && taskDetailsModal.classList.contains('show')) {
            console.log('Modal de detalhes está aberto');
            
            // Tenta obter o task_id do título do modal
            const modalTitle = document.getElementById('taskDetailsTitle');
            if (modalTitle && modalTitle.textContent) {
                console.log('Título do modal:', modalTitle.textContent);
                // Extrai o task_id se estiver no título (formato: "Nome da Tarefa | #task_id")
                const titleMatch = modalTitle.textContent.match(/#([a-zA-Z0-9]+)/);
                if (titleMatch) {
                    taskId = titleMatch[1];
                    console.log('Task ID extraído do título:', taskId);
                }
            }
            
            // Se não conseguiu pelo título, tenta pelo data attribute do modal
            if (!taskId) {
                const currentTaskId = taskDetailsModal.getAttribute('data-current-task-id');
                if (currentTaskId) {
                    taskId = currentTaskId;
                    console.log('Task ID encontrado no data attribute do modal:', taskId);
                }
            }
            
            // Se ainda não conseguiu, tenta pelos data attributes do modal body
            if (!taskId) {
                const modalBody = taskDetailsModal.querySelector('.modal-body');
                if (modalBody) {
                    // Procura por elementos com data-task-id
                    const taskElement = modalBody.querySelector('[data-task-id]');
                    if (taskElement) {
                        taskId = taskElement.getAttribute('data-task-id');
                        console.log('Task ID encontrado no modal body:', taskId);
                    }
                }
            }
        }
    }
    
    // Prioridade 3: Modal de tarefas atrasadas
    if (!taskId) {
        const overdueModal = document.getElementById('overdueModal');
        if (overdueModal && overdueModal.classList.contains('show')) {
            console.log('Modal de tarefas atrasadas está aberto');
            
            // Procura por uma tarefa selecionada no modal
            const selectedRow = overdueModal.querySelector('.task-row.table-active');
            if (selectedRow) {
                taskId = selectedRow.getAttribute('data-task-id');
                console.log('Task ID da tarefa selecionada no modal atrasadas:', taskId);
            }
        }
    }
    
    // Constrói a URL
    let url = clickupBaseUrl;
    if (taskId) {
        // Se tem task_id, vai direto para a tarefa específica
        url = `${clickupBaseUrl}/t/${taskId}`;
        console.log('Indo para tarefa específica:', taskId, 'URL:', url);
    } else {
        console.log('Nenhum task_id encontrado, indo para ClickUp geral');
    }
    
    // Abre o ClickUp em uma nova aba
    console.log('Abrindo URL:', url);
    window.open(url, '_blank');
}

// Função para carregar horas da tarefa via AJAX
function loadTaskHours(taskId) {
    const contentDiv = document.getElementById('taskHoursContent');
    const sendBtn = document.getElementById('sendToTaskrowBtn');
    
    // Mostra loading
    contentDiv.innerHTML = `
        <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
            <p class="text-muted mt-2">Carregando horas...</p>
        </div>
    `;
    sendBtn.disabled = true;
    
    if (!taskId) {
        contentDiv.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Esta tarefa não possui ID válido para buscar horas.<br>
                <small>Task ID: "${taskId}"</small>
            </div>
        `;
        return;
    }
    
    // Faz requisição AJAX para buscar horas (com cache busting)
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        },
        body: new URLSearchParams({
            action: 'get_task_hours',
            task_id: taskId,
            nonce: '<?php echo wp_create_nonce('task_hours_nonce'); ?>',
            _t: Date.now() // Cache busting timestamp
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayTaskHours(data.data);
            sendBtn.disabled = false;
        } else {
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Erro ao carregar horas: ${data.data || 'Erro desconhecido'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        contentDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Erro na requisição: ${error.message}
            </div>
        `;
    });
}

// Função para exibir as horas da tarefa
function displayTaskHours(hoursData) {
    const contentDiv = document.getElementById('taskHoursContent');
    
    if (!hoursData || hoursData.length === 0) {
        contentDiv.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Nenhuma hora foi trackada para esta tarefa ainda.
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr><th>Data</th><th>Usuário</th><th>Horas</th><th>Descrição</th></tr></thead><tbody>';
    
    let totalHours = 0;
    hoursData.forEach(hour => {
        const date = new Date(hour.date).toLocaleDateString('pt-BR');
        const hours = parseFloat(hour.hours) || 0;
        totalHours += hours;
        
        html += `
            <tr>
                <td>${date}</td>
                <td>${hour.user || 'N/A'}</td>
                <td><span class="badge bg-primary">${hours}h</span></td>
                <td>${hour.description || '-'}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    html += `<div class="alert alert-success"><strong>Total de horas: ${totalHours}h</strong></div>`;
    
    contentDiv.innerHTML = html;
}

// Event listener para o KPI card de tarefas atrasadas
document.addEventListener('DOMContentLoaded', function() {
    // Aguarda um pouco para garantir que tudo esteja carregado
    setTimeout(function() {
        const overdueCard = document.getElementById('overdueKpiCard');
        if (overdueCard) {
            overdueCard.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openOverdueModal();
            });
            
            // Adiciona estilo hover
            overdueCard.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.transition = 'transform 0.3s ease';
            });
            
            overdueCard.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        }
        
        // Event listener para o KPI card de tarefas em andamento
        const inProgressCard = document.getElementById('inProgressKpiCard');
        if (inProgressCard) {
            inProgressCard.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openInProgressModal();
            });
            
            // Adiciona estilo hover
            inProgressCard.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.transition = 'transform 0.3s ease';
            });
            
            inProgressCard.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        }
        
        // Event listener para linhas de tarefas clicáveis (apenas da tabela principal)
        // Procura especificamente pela tabela de "Últimas Atualizações"
        const mainTable = document.querySelector('.table-responsive table.table-layout-fixed tbody');
        if (mainTable) {
            const taskRows = mainTable.querySelectorAll('.task-row');
            taskRows.forEach(row => {
                row.addEventListener('click', function() {
                    const taskId = this.dataset.taskId;
                    const taskName = this.dataset.taskName;
                    const taskStatus = this.dataset.taskStatus;
                    const taskAssignee = this.dataset.taskAssignee;
                    const taskClient = this.dataset.taskClient;
                    const taskDueDate = this.dataset.taskDueDate;
                    
                    openTaskDetailsModal(taskId, taskName, taskStatus, taskAssignee, taskClient, taskDueDate);
                });
            });
        }
        
        // Event listener para fechar modal
        const modal = document.getElementById('overdueModal');
        if (modal) {
            const closeBtn = modal.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    closeModalManually();
                });
            }
            
            // Fecha modal ao clicar fora dele
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModalManually();
                }
            });
        }
    }, 100);
});

// Função para fechar modal manualmente (fallback)
function closeModalManually() {
    const modal = document.getElementById('overdueModal');
    const backdrop = document.getElementById('modalBackdrop');
    
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
    }
    
    if (backdrop) {
        backdrop.remove();
    }
}
</script>

<?php get_footer();