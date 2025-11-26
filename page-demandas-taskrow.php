<?php

/**
 * Template Name: Demandas Taskrow
 * 
 * Página para visualizar e gerenciar demandas importadas do Taskrow
 */

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(home_url());
    exit;
}

get_header();

global $wpdb;
$table_name = $wpdb->prefix . 'f2f_taskrow_demands';

// Buscar todas as demandas
$demands = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
$total_demands = count($demands);
$pending_demands = count(array_filter($demands, function ($d) {
    return $d->status === 'pending';
}));
$sent_demands = count(array_filter($demands, function ($d) {
    return $d->status === 'sent_to_clickup';
}));
?>

<div class="container-fluid py-5 px-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-3">📋 Demandas Taskrow</h1>

            <!-- Estatísticas -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $total_demands; ?></h3>
                            <p class="mb-0">Total de Demandas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $pending_demands; ?></h3>
                            <p class="mb-0">Pendentes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $sent_demands; ?></h3>
                            <p class="mb-0">Enviadas ao ClickUp</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status da API -->
            <?php
            $taskrow_api = F2F_Taskrow_API::get_instance();
            $api_configured = $taskrow_api->is_configured();
            $api_token = get_option('f2f_taskrow_api_token', '');
            $api_host = get_option('f2f_taskrow_host_name', '');
            ?>
            <div class="alert alert-<?php echo $api_configured ? 'success' : 'warning'; ?> mb-4">
                <h6><i
                        class="fas fa-<?php echo $api_configured ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>Status
                    da API Taskrow</h6>
                <?php if ($api_configured): ?>
                    <p class="mb-0">✅ API configurada corretamente</p>
                    <small>Host: <code><?php echo esc_html($api_host); ?></code> | Token:
                        <code><?php echo esc_html(substr($api_token, 0, 10)); ?>...</code></small>
                <?php else: ?>
                    <p class="mb-0">⚠️ API não configurada</p>
                    <small>Configure o token e host da API do Taskrow para importar demandas reais.</small>
                <?php endif; ?>
            </div>

            <!-- Botões de Ação -->
            <div class="mb-4">
                <button type="button" class="btn btn-outline-info btn-lg me-2" id="test-connection-btn">
                    <i class="fas fa-plug me-2"></i>
                    Testar Conexão Taskrow
                </button>
                <button type="button" class="btn btn-primary btn-lg me-2" id="import-demands-btn">
                    <i class="fas fa-download me-2"></i>
                    Importar Demandas do Taskrow
                </button>
                <button type="button" class="btn btn-danger btn-lg me-2" id="clear-all-demands-btn">
                    <i class="fas fa-trash me-2"></i>
                    Apagar Todas as Demandas
                </button>
                <button type="button" class="btn btn-info btn-lg me-2" id="list-clients-btn">
                    <i class="fas fa-building me-2"></i>
                    Ver Clientes
                </button>
                <a href="<?php echo admin_url('admin.php?page=f2f-taskrow-config'); ?>"
                    class="btn btn-secondary btn-lg">
                    <i class="fas fa-cog me-2"></i>
                    Configurações
                </a>
            </div>

            <!-- Mensagens -->
            <div id="message-container"></div>
        </div>
    </div>
    <!-- Filtros e Busca -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="search-input" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i> Buscar
                            </label>
                            <input type="text" id="search-input" class="form-control"
                                placeholder="Buscar por título, cliente ou ID...">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-status" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i> Status
                            </label>
                            <select id="filter-status" class="form-select">
                                <option value="">Todos</option>
                                <?php
                                // Buscar status únicos das demandas
                                $statuses = array();
                                foreach ($demands as $demand) {
                                    if (!empty($demand->status)) {
                                        $statuses[$demand->status] = $demand->status;
                                    }
                                }
                                ksort($statuses);

                                foreach ($statuses as $status):
                                    // Formatar nome do status para exibição
                                    $status_display = ucfirst(str_replace('_', ' ', $status));
                                ?>
                                    <option value="<?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($status_display); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter-client" class="form-label fw-semibold">
                                <i class="fas fa-user me-1"></i> Cliente
                            </label>
                            <select id="filter-client" class="form-select">
                                <option value="">Todos</option>
                                <?php
                                // Coletar client_nickname de todas as demandas (não vazio)
                                $clients = array();
                                foreach ($demands as $d) {
                                    $client = trim($d->client_nickname ?: $d->client_name);
                                    if (!empty($client) && $client !== 'Cliente Desconhecido') {
                                        $clients[] = $client;
                                    }
                                }
                                $clients = array_unique($clients);
                                sort($clients);
                                
                                foreach ($clients as $client):
                                ?>
                                        <option value="<?php echo esc_attr($client); ?>"><?php echo esc_html($client); ?>
                                        </option>
                                <?php
                                endforeach;
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter-created-date" class="form-label fw-semibold">
                                <i class="fas fa-calendar me-1"></i> Data de Criação
                            </label>
                            <select id="filter-created-date" class="form-select">
                                <option value="">Todas</option>
                                <option value="last-2-months" selected>Últimos 2 meses</option>
                                <option value="current-month">Este mês</option>
                                <option value="last-month">Mês passado</option>
                                <option value="this-week">Esta semana</option>
                                <option value="last-week">Semana passada</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="button" id="clear-filters-btn" class="btn btn-outline-secondary w-100"
                                title="Limpar filtros">
                                <i class="fas fa-undo"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Demandas -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($demands)): ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma demanda encontrada</h5>
                        <p class="text-muted mb-3">Clique em "Importar Demandas" para buscar do Taskrow.</p>
                        <button type="button" class="btn btn-primary"
                            onclick="document.getElementById('import-demands-btn').click()">
                            <i class="fas fa-download me-2"></i> Importar Agora
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2 text-primary"></i>
                                Lista de Demandas
                                <span class="badge bg-primary ms-2" id="total-count"><?php echo count($demands); ?></span>
                            </h5>
                            <div>
                                <span class="text-muted me-2">Mostrando <strong
                                        id="showing-count"><?php echo count($demands); ?></strong> de
                                    <strong id="total-demands"><?php echo count($demands); ?></strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                            <table class="table table-hover taskrow-table mb-0" id="demands-table">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="min-width: 80px; width: 80px;" class="text-center">
                                            <i class="fas fa-hashtag"></i> ID
                                        </th>
                                        <th style="min-width: 300px;">
                                            <i class="fas fa-tasks"></i> Tarefa
                                        </th>
                                        <th style="min-width: 120px; width: 150px;">
                                            <i class="fas fa-user-circle"></i> Cliente
                                        </th>
                                        <th style="min-width: 110px; width: 110px;" class="text-center">
                                            <i class="fas fa-info-circle"></i> Status
                                        </th>
                                        <th style="min-width: 100px; width: 100px;" class="text-center">
                                            <i class="fas fa-flag"></i> Prioridade
                                        </th>
                                        <th style="min-width: 120px; width: 120px;" class="text-center">
                                            <i class="fas fa-calendar-alt"></i> Entrega
                                        </th>
                                        <th style="min-width: 140px; width: 140px;" class="text-center">
                                            <i class="fas fa-cog"></i> Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($demands as $demand):
                                        // Calcular dias até vencimento
                                        $is_overdue = false;
                                        $days_until_due = null;
                                        if (!empty($demand->due_date)) {
                                            $timestamp = strtotime($demand->due_date);
                                            if ($timestamp !== false && $timestamp > 0) {
                                                $days_until_due = floor(($timestamp - time()) / 86400);
                                                $is_overdue = $days_until_due < 0;
                                            }
                                        }
                                    ?>
                                        <tr data-demand-id="<?php echo $demand->id; ?>"
                                            data-title="<?php echo esc_attr(strtolower($demand->title)); ?>"
                                            data-client="<?php echo esc_attr(strtolower($demand->client_nickname ?: $demand->client_name)); ?>"
                                            data-status="<?php echo esc_attr($demand->status); ?>"
                                            data-taskrow-id="<?php echo esc_attr($demand->taskrow_id); ?>"
                                            class="demand-row <?php echo $is_overdue ? 'table-danger-subtle' : ''; ?>">
                                            <td class="text-center align-middle">
                                                <div class="task-id-badge">
                                                    <small
                                                        class="text-muted fw-semibold">#<?php echo $demand->taskrow_id; ?></small>
                                                    <?php if (!empty($demand->task_number)): ?>
                                                        <br><span class="badge bg-secondary"
                                                            style="font-size: 0.7rem;">T-<?php echo $demand->task_number; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <div class="task-info">
                                                    <strong
                                                        class="d-block mb-1 text-dark"><?php echo esc_html($demand->title); ?></strong>
                                                    <?php if ($demand->description): ?>
                                                        <small class="text-muted d-block task-description">
                                                            <i class="fas fa-align-left me-1"></i>
                                                            <?php echo esc_html(wp_trim_words($demand->description, 20)); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($demand->job_number)): ?>
                                                        <small class="text-info d-block mt-1">
                                                            <i class="fas fa-briefcase me-1"></i> Job
                                                            #<?php echo $demand->job_number; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <div class="client-info">
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="fas fa-building me-1"></i>
                                                        <?php echo esc_html($demand->client_name ?: 'Não definido'); ?>
                                                    </span>
                                                    <?php if (!empty($demand->client_nickname)): ?>
                                                        <br><small
                                                            class="text-muted"><?php echo esc_html($demand->client_nickname); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php
                                                $status_class = 'secondary';
                                                $status_icon = 'fa-circle';
                                                $status_text = $demand->status ?: 'Indefinido';

                                                if ($demand->status === 'pending') {
                                                    $status_class = 'warning';
                                                    $status_icon = 'fa-clock';
                                                    $status_text = 'Pendente';
                                                } elseif ($demand->status === 'sent_to_clickup') {
                                                    $status_class = 'success';
                                                    $status_icon = 'fa-check-circle';
                                                    $status_text = 'No ClickUp';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?> status-badge">
                                                    <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                                    <?php echo esc_html($status_text); ?>
                                                </span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php if ($demand->priority):
                                                    $priority_class = 'secondary';
                                                    $priority_icon = 'fa-flag';

                                                    switch (strtolower($demand->priority)) {
                                                        case 'urgent':
                                                        case 'alta':
                                                        case 'high':
                                                            $priority_class = 'danger';
                                                            break;
                                                        case 'media':
                                                        case 'medium':
                                                            $priority_class = 'warning';
                                                            break;
                                                        case 'baixa':
                                                        case 'low':
                                                            $priority_class = 'info';
                                                            break;
                                                    }
                                                ?>
                                                    <span class="badge bg-<?php echo $priority_class; ?> priority-badge">
                                                        <i class="fas <?php echo $priority_icon; ?> me-1"></i>
                                                        <?php echo esc_html(ucfirst($demand->priority)); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php
                                                if (!empty($demand->due_date)) {
                                                    $timestamp = strtotime($demand->due_date);
                                                    if ($timestamp !== false && $timestamp > 0) {
                                                        $formatted_date = date('d/m/Y', $timestamp);
                                                        $date_class = 'text-muted';
                                                        $date_icon = 'fa-calendar';
                                                        $date_badge_class = 'secondary';

                                                        if ($days_until_due !== null) {
                                                            if ($days_until_due < 0) {
                                                                $date_class = 'text-danger';
                                                                $date_icon = 'fa-exclamation-triangle';
                                                                $date_badge_class = 'danger';
                                                                $date_text = 'Atrasado ' . abs($days_until_due) . 'd';
                                                            } elseif ($days_until_due <= 3) {
                                                                $date_class = 'text-warning';
                                                                $date_icon = 'fa-clock';
                                                                $date_badge_class = 'warning';
                                                                $date_text = 'Vence em ' . $days_until_due . 'd';
                                                            } else {
                                                                $date_text = 'Em ' . $days_until_due . ' dias';
                                                            }
                                                        }
                                                ?>
                                                        <div class="date-info">
                                                            <span class="<?php echo $date_class; ?> fw-semibold d-block">
                                                                <i class="fas <?php echo $date_icon; ?> me-1"></i>
                                                                <?php echo $formatted_date; ?>
                                                            </span>
                                                            <?php if (isset($date_text)): ?>
                                                                <small>
                                                                    <span class="badge bg-<?php echo $date_badge_class; ?> mt-1">
                                                                        <?php echo $date_text; ?>
                                                                    </span>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                <?php
                                                    } else {
                                                        echo '<span class="text-danger" title="Data inválida"><i class="fas fa-times-circle"></i> Inválida</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted"><i class="fas fa-minus-circle"></i> N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center align-middle">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if ($demand->clickup_id): ?>
                                                        <button class="btn btn-success" disabled title="Tarefa já está no ClickUp">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-primary send-to-clickup-btn"
                                                            data-demand-id="<?php echo $demand->id; ?>" title="Enviar para ClickUp">
                                                            <i class="fas fa-paper-plane"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <button class="btn btn-outline-secondary view-details-btn"
                                                        data-demand-id="<?php echo $demand->id; ?>" title="Ver detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </button>

                                                    <button class="btn btn-outline-info copy-info-btn"
                                                        data-demand-id="<?php echo $demand->id; ?>"
                                                        data-title="<?php echo esc_attr($demand->title); ?>"
                                                        data-client="<?php echo esc_attr($demand->client_nickname ?: $demand->client_name); ?>"
                                                        title="Copiar informações">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Última atualização: <?php echo date('d/m/Y H:i'); ?>
                            </small>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i> Atualizar Lista
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- Modal de Detalhes -->
<div class="modal fade" id="demandDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da Demanda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modal-demand-details">
                <!-- Conteúdo carregado via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Modal de Progresso -->
<div class="modal fade" id="importProgressModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importando Demandas</h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span id="import-status-text" class="fw-bold">Iniciando...</span>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div id="import-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                            role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <small id="import-substatus-text" class="text-muted d-block mt-1"></small>
                </div>
                <div id="import-log" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem; background: #f8f9fa; padding: 0.75rem; border-radius: 6px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-import-btn" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
    // v1.0.2 - Fixed createdDate scope issue
    jQuery(document).ready(function($) {
        // Verificar se f2f_ajax está disponível
        if (typeof f2f_ajax === 'undefined') {
            console.error('f2f_ajax não está definido! Verifique se o script está sendo carregado.');
            alert('Erro: Configuração do tema não carregada corretamente. Recarregue a página.');
            return;
        }
        
        console.log('✅ Script carregado com sucesso! v1.0.2');

        // Filtros em tempo real
        function filterTable() {
            const searchTerm = $('#search-input').val().toLowerCase();
            const statusFilter = $('#filter-status').val();
            const clientFilter = $('#filter-client').val().toLowerCase();
            const dateFilter = $('#filter-created-date').val();

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            let visibleCount = 0;
            let debugInfo = {
                filter: dateFilter,
                today: today.toISOString(),
                rows: []
            };

            $('.demand-row').each(function() {
                const $row = $(this);
                const title = $row.data('title') || '';
                const client = $row.data('client') || '';
                const status = $row.data('status') || '';
                const taskrowId = $row.data('taskrow-id') || '';
                const createdAtStr = $row.find('td:eq(5)').text().trim();

                const matchesSearch = !searchTerm ||
                    title.includes(searchTerm) ||
                    client.includes(searchTerm) ||
                    String(taskrowId).includes(searchTerm);

                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesClient = !clientFilter || client.includes(clientFilter);

                let matchesDate = true;
                
                if (dateFilter && createdAtStr && createdAtStr !== 'N/A') {
                    const dateParts = createdAtStr.split('/');
                    if (dateParts.length === 3) {
                        try {
                            const createdDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
                            
                            // Verificar se a data é válida
                            if (isNaN(createdDate.getTime())) {
                                matchesDate = false;
                            } else {
                                createdDate.setHours(0, 0, 0, 0);

                                const currentMonth = today.getMonth();
                                const currentYear = today.getFullYear();
                                const createdMonth = createdDate.getMonth();
                                const createdYear = createdDate.getFullYear();

                                switch (dateFilter) {
                                    case 'last-2-months':
                                        const twoMonthsAgo = new Date(today);
                                        twoMonthsAgo.setMonth(today.getMonth() - 2);
                                        matchesDate = createdDate >= twoMonthsAgo;
                                        break;
                                    case 'current-month':
                                        matchesDate = createdMonth === currentMonth && createdYear === currentYear;
                                        break;
                                    case 'last-month':
                                        const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
                                        const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;
                                        matchesDate = createdMonth === lastMonth && createdYear === lastMonthYear;
                                        break;
                                    case 'this-week':
                                        const weekStart = new Date(today);
                                        weekStart.setDate(today.getDate() - today.getDay());
                                        const weekEnd = new Date(weekStart);
                                        weekEnd.setDate(weekStart.getDate() + 6);
                                        matchesDate = createdDate >= weekStart && createdDate <= weekEnd;
                                        break;
                                    case 'last-week':
                                        const lastWeekStart = new Date(today);
                                        lastWeekStart.setDate(today.getDate() - today.getDay() - 7);
                                        const lastWeekEnd = new Date(lastWeekStart);
                                        lastWeekEnd.setDate(lastWeekStart.getDate() + 6);
                                        matchesDate = createdDate >= lastWeekStart && createdDate <= lastWeekEnd;
                                        break;
                                }
                                
                                // Debug: salvar info da row
                                if (dateFilter === 'last-2-months' && debugInfo.rows.length < 10) {
                                    debugInfo.rows.push({
                                        id: taskrowId,
                                        created: createdAtStr,
                                        parsed: createdDate.toISOString(),
                                        matches: matchesDate
                                    });
                                }
                            }
                        } catch (e) {
                            console.error('Erro ao processar data:', createdAtStr, e);
                            matchesDate = false;
                        }
                    } else {
                        matchesDate = false;
                    }
                } else if (dateFilter) {
                    matchesDate = false;
                }

                if (matchesSearch && matchesStatus && matchesClient && matchesDate) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            // Debug log
            if (dateFilter === 'last-2-months' && debugInfo.rows.length > 0) {
                console.log('=== DEBUG FILTRO DE DATA ===');
                console.log('Filtro:', dateFilter);
                console.log('Hoje:', debugInfo.today);
                console.log('Total de rows:', debugInfo.rows.length);
                console.log('Visíveis:', visibleCount);
                console.table(debugInfo.rows.slice(0, 10)); // Primeiras 10 rows
            }

            $('#showing-count').text(visibleCount);

            // Mostrar mensagem se nenhum resultado
            if (visibleCount === 0) {
                if ($('#no-results-row').length === 0) {
                    $('#demands-table tbody').append(
                        '<tr id="no-results-row">' +
                        '<td colspan="7" class="text-center py-5">' +
                        '<i class="fas fa-search fa-3x text-muted mb-3"></i>' +
                        '<h5 class="text-muted">Nenhuma demanda encontrada</h5>' +
                        '<p class="text-muted">Tente ajustar os filtros de busca</p>' +
                        '</td>' +
                        '</tr>'
                    );
                }
            } else {
                $('#no-results-row').remove();
            }
        }

        // Event listeners para filtros
        $('#search-input').on('keyup', filterTable);
        $('#filter-status').on('change', filterTable);
        $('#filter-client').on('change', filterTable);
        $('#filter-created-date').on('change', filterTable);

        // Aplicar filtro de data padrão (últimos 2 meses) ao carregar
        filterTable();

        // Limpar filtros
        $('#clear-filters-btn').on('click', function() {
            $('#search-input').val('');
            $('#filter-status').val('');
            $('#filter-client').val('');
            $('#filter-created-date').val('last-2-months');
            filterTable();
        });

        // Copiar informações
        $(document).on('click', '.copy-info-btn', function() {
            const title = $(this).data('title');
            const client = $(this).data('client');
            const copyText = `Tarefa: ${title}\nCliente: ${client}`;

            navigator.clipboard.writeText(copyText).then(function() {
                showMessage('success', '<i class="fas fa-check-circle me-2"></i>Informações copiadas!');
            }).catch(function() {
                showMessage('warning', '<i class="fas fa-exclamation-triangle me-2"></i>Erro ao copiar. Tente novamente.');
            });
        });

        // Testar Conexão Taskrow
        $('#test-connection-btn').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.html();

            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin me-2"></i>Testando...');

            $.post(f2f_ajax.ajaxurl, {
                    action: 'f2f_test_taskrow_connection',
                    nonce: f2f_ajax.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        showMessage('success', `
                    <strong><i class="fas fa-check-circle me-2"></i>Conexão bem-sucedida!</strong><br>
                    ${response.data.message}
                `);
                    } else {
                        showMessage('danger', `
                    <strong><i class="fas fa-times-circle me-2"></i>Erro na conexão:</strong><br>
                    ${response.data}
                `);
                    }
                })
                .fail(function(xhr, status, error) {
                    showMessage('danger', `<strong><i class="fas fa-exclamation-triangle me-2"></i>Erro:</strong> Falha na requisição AJAX.`);
                })
                .always(function() {
                    $btn.prop('disabled', false).html(originalText);
                });
        });

        // Apagar Todas as Demandas
        $('#clear-all-demands-btn').on('click', function() {
            if (!confirm('⚠️ ATENÇÃO: Esta ação irá apagar TODAS as demandas importadas do Taskrow.\n\nIsso não pode ser desfeito!\n\nTem certeza que deseja continuar?')) {
                return;
            }

            const $btn = $(this);
            const originalText = $btn.html();

            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin me-2"></i>Apagando...');

            $.post(f2f_ajax.ajaxurl, {
                    action: 'f2f_clear_all_taskrow_demands',
                    nonce: f2f_ajax.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        showMessage('success', `
                    <strong><i class="fas fa-check-circle me-2"></i>Demandas apagadas com sucesso!</strong><br>
                    ${response.data.message}
                `);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showMessage('danger', `
                    <strong><i class="fas fa-times-circle me-2"></i>Erro ao apagar demandas:</strong><br>
                    ${response.data}
                `);
                    }
                })
                .fail(function() {
                    showMessage('danger', '<i class="fas fa-exclamation-triangle me-2"></i>Erro ao apagar demandas');
                })
                .always(function() {
                    $btn.prop('disabled', false).html(originalText);
                });
        });

        // Importar Demandas
        $('#import-demands-btn').on('click', function() {
            const $modal = new bootstrap.Modal(document.getElementById('importProgressModal'));
            const $progressBar = $('#import-progress-bar');
            const $statusText = $('#import-status-text');
            const $subStatusText = $('#import-substatus-text');
            const $log = $('#import-log');
            const $closeBtn = $('#cancel-import-btn');

            $progressBar.css('width', '0%').text('0%').removeClass('bg-success bg-danger');
            $log.html('');
            $closeBtn.prop('disabled', true);
            $statusText.text('Buscando projetos...');
            $subStatusText.text('Conectando à API...');
            $modal.show();

            function addLog(msg, type = 'info') {
                const color = type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-muted');
                const time = new Date().toLocaleTimeString();
                $log.append(`<div class="${color}">[${time}] ${msg}</div>`);
                $log.scrollTop($log[0].scrollHeight);
            }

            addLog('Iniciando importação...', 'info');
            $statusText.text('Importando tarefas...');
            $subStatusText.text('Processando todos os clientes');
            $progressBar.css('width', '50%').text('50%');

            $.post(f2f_ajax.ajaxurl, {
                    action: 'f2f_import_by_clients',
                    nonce: f2f_ajax.nonce
                })
                .done(function(response) {
                    if (!response.success) {
                        addLog('Erro ao importar: ' + response.data, 'error');
                        $statusText.text('Erro na importação');
                        $progressBar.addClass('bg-danger');
                        $closeBtn.prop('disabled', false);
                        return;
                    }

                    const imported = response.data.imported || 0;
                    const updated = response.data.updated || 0;
                    const clientsProcessed = response.data.clients_processed || 0;

                    addLog(`Importação concluída! ${clientsProcessed} clientes processados.`, 'success');
                    addLog(`Total: ${imported} novas tarefas, ${updated} atualizadas.`, 'success');

                    $statusText.text('Importação Concluída!');
                    $subStatusText.text(`${clientsProcessed} clientes | ${imported} novos | ${updated} atualizados`);
                    $progressBar.css('width', '100%').text('100%').addClass('bg-success');
                    $closeBtn.prop('disabled', false).text('Concluir e Recarregar');

                    $closeBtn.off('click').on('click', function() {
                        location.reload();
                    });

                })
                .fail(function() {
                    addLog('Erro fatal ao conectar com servidor.', 'error');
                    $statusText.text('Erro de Conexão');
                    $progressBar.addClass('bg-danger');
                    $closeBtn.prop('disabled', false);
                });
        });

        // Ver Detalhes
        $(document).on('click', '.view-details-btn', function() {
            const demandId = $(this).data('demand-id');
            const $row = $('[data-demand-id="' + demandId + '"]');

            // Buscar dados da linha com mais informações
            const taskrowId = $row.data('taskrow-id');
            const title = $row.find('.task-info strong').text();
            const description = $row.find('.task-description').text() || 'Sem descrição disponível';
            const client = $row.find('.client-info .badge').text().replace(/\s+/g, ' ').trim();
            const status = $row.find('.status-badge').text().replace(/\s+/g, ' ').trim();
            const priority = $row.find('.priority-badge').text().trim() || 'Não definida';
            const dueDate = $row.find('.date-info span:first').text() || 'Não definida';

            // Montar conteúdo do modal com design melhorado
            let html = `
            <div class="demand-details">
                <div class="detail-section mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">${title}</h4>
                        <span class="badge bg-secondary">#${taskrowId}</span>
                    </div>
                    <p class="text-muted mb-0">${description}</p>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-building text-primary me-2"></i>Cliente
                            </div>
                            <div class="detail-value">${client}</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-info-circle text-info me-2"></i>Status
                            </div>
                            <div class="detail-value">${status}</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-flag text-warning me-2"></i>Prioridade
                            </div>
                            <div class="detail-value">${priority}</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-calendar-alt text-success me-2"></i>Data de Entrega
                            </div>
                            <div class="detail-value">${dueDate}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

            $('#modal-demand-details').html(html);
            new bootstrap.Modal($('#demandDetailsModal')[0]).show();
        });

        // Enviar para ClickUp
        $(document).on('click', '.send-to-clickup-btn', function() {
            const $btn = $(this);
            const demandId = $btn.data('demand-id');
            const originalText = $btn.html();

            if (!confirm('Deseja enviar esta demanda para o ClickUp?')) {
                return;
            }

            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i>');

            $.post(f2f_ajax.ajaxurl, {
                    action: 'f2f_send_demand_to_clickup',
                    demand_id: demandId,
                    nonce: f2f_ajax.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        showMessage('success', '<i class="fas fa-check-circle me-2"></i>' + response.data.message);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showMessage('danger', '<i class="fas fa-times-circle me-2"></i>' + response.data);
                        $btn.prop('disabled', false).html(originalText);
                    }
                })
                .fail(function() {
                    showMessage('danger', '<i class="fas fa-exclamation-triangle me-2"></i>Erro ao enviar demanda');
                    $btn.prop('disabled', false).html(originalText);
                });
        });

        // Botão para listar clientes
        $('#list-clients-btn').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.html();

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Carregando...');

            $.post(f2f_ajax.ajaxurl, {
                    action: 'f2f_list_taskrow_clients',
                    nonce: f2f_ajax.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        const clients = response.data.clients;
                        const total = response.data.total;

                        let html = `<div class="modal fade show" style="display:block; background: rgba(0,0,0,0.5);" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">
                                            <i class="fas fa-building me-2"></i>
                                            Clientes Taskrow (${total})
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white close-clients-modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Nome</th>
                                                        <th>Nickname</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>`;

                        clients.forEach(client => {
                            const status = client.active ? 
                                '<span class="badge bg-success">Ativo</span>' : 
                                '<span class="badge bg-secondary">Inativo</span>';
                            
                            html += `<tr>
                                <td><code>${client.id}</code></td>
                                <td>${client.name}</td>
                                <td><strong>${client.nickname}</strong></td>
                                <td>${status}</td>
                            </tr>`;
                        });

                        html += `</tbody>
                                            </table>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-secondary" onclick="console.log(${JSON.stringify(clients)})">
                                                <i class="fas fa-code me-2"></i>Log JSON no Console
                                            </button>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary close-clients-modal">Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>`;

                        $('body').append(html);

                        // Fechar modal
                        $('.close-clients-modal').on('click', function() {
                            $('.modal').remove();
                        });

                        // Log no console
                        console.log('=== CLIENTES TASKROW ===');
                        console.log('Total:', total);
                        console.table(clients);
                    } else {
                        showMessage('danger', '<i class="fas fa-times-circle me-2"></i>' + response.data);
                    }
                })
                .fail(function() {
                    showMessage('danger', '<i class="fas fa-exclamation-triangle me-2"></i>Erro ao buscar clientes');
                })
                .always(function() {
                    $btn.prop('disabled', false).html(originalText);
                });
        });

        // Função auxiliar para mostrar mensagens com animação
        function showMessage(type, message) {
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'warning' ? 'alert-warning' :
                type === 'danger' ? 'alert-danger' : 'alert-info';

            const html = `<div class="alert ${alertClass} alert-dismissible fade show shadow-sm" role="alert" style="animation: slideInDown 0.3s ease-out;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;

            $('#message-container').html(html);

            setTimeout(() => {
                $('.alert').fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Tooltips
        $('[title]').tooltip();
    });
</script>

<style>
    .container-fluid {
        max-width: 100%;
        padding-right: 15px;
        padding-left: 15px;
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .card {
        border: none;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .card.shadow-sm {
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08) !important;
    }

    .card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12) !important;
    }

    .card-header,
    .card-footer {
        border: none;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .taskrow-table {
        font-size: 0.95rem;
        width: 100%;
        min-width: 1020px;
        table-layout: fixed;
    }

    .taskrow-table thead th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e0e0e0;
        padding: 1rem 0.75rem;
        background: linear-gradient(to bottom, #f8f9fa 0%, #f1f3f5 100%);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .taskrow-table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f0f0f0;
    }

    .taskrow-table tbody tr:hover {
        background-color: #f8f9ff !important;
        transform: translateX(2px);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }

    .taskrow-table tbody td {
        padding: 1rem 0.75rem;
        vertical-align: middle;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .taskrow-table thead th.sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
        background: linear-gradient(to bottom, #f8f9fa 0%, #f1f3f5 100%);
    }

    .table-danger-subtle {
        background-color: #fff5f5 !important;
        border-left: 4px solid #dc3545;
    }

    .table-danger-subtle:hover {
        background-color: #ffe5e5 !important;
    }

    .badge {
        padding: 0.4em 0.75em;
        font-weight: 500;
        border-radius: 6px;
        font-size: 0.8rem;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .status-badge {
        min-width: 90px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .priority-badge {
        min-width: 80px;
    }

    .task-info strong {
        font-size: 1rem;
        line-height: 1.4;
        color: #2c3e50;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .task-description {
        line-height: 1.5;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: normal;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .task-id-badge {
        padding: 0.25rem;
    }

    .client-info .badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
    }

    .date-info {
        min-width: 100px;
    }

    .date-info .badge {
        font-size: 0.75rem;
    }

    .btn-group-sm>.btn {
        padding: 0.375rem 0.625rem;
        font-size: 0.875rem;
        border-radius: 6px;
    }

    .btn-group .btn:not(:last-child) {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        margin-right: -1px;
    }

    .btn-group .btn:not(:first-child) {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .form-label {
        margin-bottom: 0.35rem;
        font-size: 0.875rem;
        color: #495057;
    }

    .form-control,
    .form-select {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        transition: all 0.2s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
    }

    .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 1.5rem;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    .modal-title {
        font-weight: 600;
    }

    .modal-body {
        padding: 2rem;
    }

    .demand-details .detail-section {
        border-bottom: 1px solid #e9ecef;
    }

    .detail-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        height: 100%;
        transition: all 0.2s ease;
    }

    .detail-card:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }

    .detail-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        font-size: 1rem;
        color: #2c3e50;
        font-weight: 500;
    }

    .card.bg-primary,
    .card.bg-warning,
    .card.bg-success {
        border: none;
        transition: all 0.3s ease;
    }

    .card.bg-primary:hover,
    .card.bg-warning:hover,
    .card.bg-success:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .alert {
        border: none;
        border-radius: 10px;
        border-left: 4px solid;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .alert-success {
        background-color: #d1f2e8;
        border-left-color: #28a745;
        color: #155724;
    }

    .alert-danger {
        background-color: #f8d7da;
        border-left-color: #dc3545;
        color: #721c24;
    }

    .alert-warning {
        background-color: #fff3cd;
        border-left-color: #ffc107;
        color: #856404;
    }

    .alert-info {
        background-color: #d1ecf1;
        border-left-color: #17a2b8;
        color: #0c5460;
    }

    .btn {
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.2s ease;
        border: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-outline-secondary {
        border: 2px solid #6c757d;
    }

    .btn-outline-secondary:hover {
        background-color: #6c757d;
        transform: translateY(-2px);
    }

    @media (max-width: 1400px) {
        .taskrow-table {
            min-width: 1020px;
        }
    }

    @media (max-width: 992px) {
        .container-fluid {
            padding-right: 10px;
            padding-left: 10px;
        }

        .taskrow-table {
            font-size: 0.85rem;
            min-width: 920px;
        }

        .taskrow-table thead th {
            padding: 0.75rem 0.5rem;
            font-size: 0.75rem;
        }

        .taskrow-table tbody td {
            padding: 0.75rem 0.5rem;
        }
    }

    @media (max-width: 768px) {
        .taskrow-table {
            min-width: 800px;
        }

        .task-info strong {
            font-size: 0.9rem;
        }

        .task-description {
            font-size: 0.8rem;
            max-width: 300px;
        }

        .btn-group-sm>.btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .badge {
            font-size: 0.7rem;
            padding: 0.3em 0.6em;
        }
    }

    .btn .fa-spinner {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .tooltip {
        font-size: 0.85rem;
    }

    #no-results-row i {
        opacity: 0.3;
    }

    .table-responsive::-webkit-scrollbar {
        height: 10px;
        width: 10px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: #f1f3f5;
        border-radius: 10px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
    }

    .table-responsive::-webkit-scrollbar-corner {
        background: #f1f3f5;
    }

    .card-body.p-0 {
        overflow: visible;
    }
</style>

<?php get_footer(); ?>