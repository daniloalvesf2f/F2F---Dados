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
                <!-- <button type="button" class="btn btn-outline-info btn-lg me-2" id="test-connection-btn">
                    <i class="fas fa-plug me-2"></i>
                    Testar Conexão Taskrow
                </button> -->
                <button type="button" class="btn btn-primary btn-lg me-2" id="import-demands-btn">
                    <i class="fas fa-download me-2"></i>
                    Importar Demandas do Taskrow
                </button>
                <button type="button" class="btn btn-danger btn-lg me-2" id="clear-all-demands-btn">
                    <i class="fas fa-trash me-2"></i>
                    Apagar Todas as Demandas
                </button>
                <!-- <button type="button" class="btn btn-info btn-lg me-2" id="list-clients-btn">
                    <i class="fas fa-building me-2"></i>
                    Ver Clientes
                </button> -->
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
                                    $client_val = '';
                                    if (!empty($d->client_nickname)) {
                                        $client_val = (string)$d->client_nickname;
                                    } elseif (!empty($d->client_name)) {
                                        $client_val = (string)$d->client_name;
                                    }
                                    $client = trim(isset($client_val) ? (string)$client_val : '');
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
                        <!-- Novo filtro: Responsável (oculto no front, porém ativo) -->
                        <div class="col-md-2 d-none" id="filter-owner-wrapper" aria-hidden="true">
                            <label for="filter-owner" class="form-label fw-semibold">
                                <i class="fas fa-user-check me-1"></i> Responsável
                            </label>
                            <select id="filter-owner" class="form-select">
                                <option value="">Todos</option>
                                <?php
                                $owners = array();
                                foreach ($demands as $d) {
                                    if (!empty($d->owner_user_login)) {
                                        $login = strtolower((string)$d->owner_user_login);
                                        $owners[] = $login;
                                    }
                                }
                                $owners = array_unique($owners);
                                sort($owners);
                                foreach ($owners as $login) {
                                    $label = ucwords(str_replace(array('.', '_'), ' ', $login));
                                    echo '<option value="' . esc_attr($login) . '">' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <!-- Novo filtro: Grupo/Área -->
                        <div class="col-md-2">
                            <label for="filter-group" class="form-label fw-semibold">
                                <i class="fas fa-layer-group me-1"></i> Grupo
                            </label>
                            <select id="filter-group" class="form-select">
                                <option value="">Todos</option>
                                <option value="tech">Tech (Ingrid Bisi)</option>
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
                                        <th style="min-width: 290px;">
                                            <i class="fas fa-tasks"></i> Tarefa
                                        </th>
                                        <th style="min-width: 120px; width: 150px;">
                                            <i class="fas fa-user-circle"></i> Cliente
                                        </th>
                                        <th style="min-width: 150px; width: 160px;" class="text-center">
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
                                        <?php
                                        $titleSafe = isset($demand->title) ? (string)$demand->title : '';
                                        $statusSafe = isset($demand->status) ? (string)$demand->status : '';
                                        $descSafe = isset($demand->description) ? (string)$demand->description : '';
                                        $ownerLoginSafe = isset($demand->owner_user_login) ? (string)$demand->owner_user_login : '';
                                        $group = '';
                                        if (
                                            stripos($statusSafe, 'tech') !== false ||
                                            stripos($titleSafe, 'tech') !== false ||
                                            stripos($descSafe, 'tech') !== false ||
                                            stripos($ownerLoginSafe, 'ingrid') !== false ||
                                            stripos($ownerLoginSafe, 'raissa') !== false
                                        ) {
                                            $group = 'tech';
                                        }
                                        $clientSafeRaw = '';
                                        if (!empty($demand->client_nickname)) {
                                            $clientSafeRaw = (string)$demand->client_nickname;
                                        } elseif (!empty($demand->client_name)) {
                                            $clientSafeRaw = (string)$demand->client_name;
                                        }
                                        $clientDataAttr = strtolower($clientSafeRaw);
                                        $createdStr = '';
                                        if (!empty($demand->created_at)) {
                                            $cts = strtotime($demand->created_at);
                                            if ($cts && $cts > 0) {
                                                $createdStr = date('d/m/Y', $cts);
                                            }
                                        }
                                        ?>
                                        <tr data-demand-id="<?php echo $demand->id; ?>"
                                            data-title="<?php echo esc_attr(strtolower($titleSafe)); ?>"
                                            data-client="<?php echo esc_attr($clientDataAttr); ?>"
                                            data-status="<?php echo esc_attr($statusSafe); ?>"
                                            data-taskrow-id="<?php echo esc_attr($demand->taskrow_id); ?>"
                                            data-job-number="<?php echo esc_attr($demand->job_number); ?>"
                                            data-task-number="<?php echo esc_attr($demand->task_number); ?>"
                                            data-client-nickname="<?php echo esc_attr($demand->client_nickname); ?>"
                                            data-owner="<?php echo esc_attr(strtolower($ownerLoginSafe)); ?>"
                                            data-created="<?php echo esc_attr($createdStr); ?>"
                                            data-group="<?php echo esc_attr($group); ?>"
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
                                                        <div class="task-desc-wrapper">
                                                            <small class="text-muted d-block task-description">
                                                                <i class="fas fa-align-left me-1"></i>
                                                                <?php echo esc_html(wp_trim_words(wp_strip_all_tags($demand->description), 25)); ?>
                                                            </small>
                                                            <span class="task-description-full d-none">
                                                                <?php echo wp_kses_post($demand->description); ?>
                                                            </span>
                                                        </div>
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
                                                <?php
                                                // Definir ícone/classe de prioridade de forma segura
                                                $priority_val = $demand->priority ?? '';
                                                if (!empty($priority_val)) {
                                                    $priority_class = 'secondary';
                                                    $priority_icon = 'fa-flag';
                                                    switch (strtolower($priority_val)) {
                                                        case 'urgent':
                                                        case 'alta':
                                                        case 'high':
                                                            $priority_class = 'danger';
                                                            $priority_icon = 'fa-exclamation-circle';
                                                            break;
                                                        case 'media':
                                                        case 'medium':
                                                            $priority_class = 'warning';
                                                            $priority_icon = 'fa-exclamation-triangle';
                                                            break;
                                                        case 'baixa':
                                                        case 'low':
                                                            $priority_class = 'info';
                                                            $priority_icon = 'fa-info-circle';
                                                            break;
                                                    }
                                                ?>
                                                    <span class="badge bg-<?php echo esc_attr($priority_class); ?> priority-badge">
                                                        <i class="fas <?php echo esc_attr($priority_icon); ?> me-1"></i>
                                                        <?php echo esc_html(ucfirst($priority_val)); ?>
                                                    </span>
                                                <?php
                                                    echo '<span class="text-muted">-</span>';
                                                }
                                                ?>
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
                                                        $date_text = null;
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
                                                            <?php if (!empty($date_text)): ?>
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
                                                    <!-- 
                                                    <button class="btn btn-outline-info copy-info-btn"
                                                        data-demand-id="<//?php echo $demand->id; ?>"
                                                        data-title="<//?php echo esc_attr($demand->title); ?>"
                                                        data-client="<//?php echo esc_attr($demand->client_nickname ?: $demand->client_name); ?>"
                                                        title="Copiar informações">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary view-description-btn"
                                                        data-client-nickname="<//?php echo esc_attr($demand->client_nickname); ?>"
                                                        data-job-number="<//?php echo esc_attr($demand->job_number); ?>"
                                                        data-task-number="<//?php echo esc_attr($demand->task_number); ?>"
                                                        title="Ver descrição (Taskrow)">
                                                        <i class="fas fa-align-left"></i>
                                                    </button> -->
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
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="modal-open-taskrow-btn">
                    <i class="fas fa-external-link-alt me-1"></i> Abrir no Taskrow
                </button>
                <button type="button" class="btn btn-primary" id="modal-export-clickup-btn">
                    <i class="fas fa-paper-plane me-1"></i> Exportar para ClickUp
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
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

<!-- Modal de Descrição da Taskrow -->
<div class="modal fade" id="taskDescriptionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-align-left me-2"></i>Descrição da Tarefa (Taskrow)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="task-desc-loading" class="alert alert-info mb-3" style="display:none;">
                    <i class="fas fa-spinner fa-spin me-1"></i> Carregando descrição...
                </div>
                <div id="task-desc-error" class="alert alert-danger mb-3" style="display:none;"></div>
                <div id="task-desc-success" style="display:none;">
                    <p class="mb-2"><strong>Origem:</strong> <code id="task-desc-source"></code></p>
                    <p class="mb-2"><strong>Endpoint:</strong> <code id="task-desc-endpoint"></code></p>
                    <div class="mb-4">
                        <h6 class="fw-semibold">HTML Bruto</h6>
                        <div class="border rounded p-3 bg-white" style="max-height:300px; overflow:auto;">
                            <pre id="task-desc-raw" style="white-space:pre-wrap;font-size:12px;"></pre>
                        </div>
                    </div>
                    <div class="mb-4">
                        <h6 class="fw-semibold">Renderizado</h6>
                        <div id="task-desc-render" class="border rounded p-3 bg-white" style="max-height:300px; overflow:auto;"></div>
                    </div>
                    <div class="mb-2">
                        <h6 class="fw-semibold">Texto Limpo</h6>
                        <div id="task-desc-text" class="border rounded p-3 bg-white" style="max-height:300px; overflow:auto;font-size:13px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-outline-primary" id="task-desc-copy-btn" style="display:none;">
                    <i class="fas fa-copy me-1"></i> Copiar Texto
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // v1.0.2 - Fixed createdDate scope issue
    jQuery(document).ready(function($) {
    const TASKROW_HOST = '<?php echo esc_js($api_host); ?>';
        // Verificar se f2f_ajax está disponível
        if (typeof f2f_ajax === 'undefined') {
            console.error('f2f_ajax não está definido! Verifique se o script está sendo carregado.');
            alert('Erro: Configuração do tema não carregada corretamente. Recarregue a página.');
            return;
        }

        console.log('✅ Script carregado com sucesso! v1.0.2');

        // Preenche o select de Responsável a partir das linhas renderizadas, se vazio
        const populateOwnerSelect = () => {
            const $sel = $('#filter-owner');
            if ($sel.length === 0) return;
            // já possui opções além de "Todos"? então mantém
            if ($sel.find('option').length > 1) return;
            const owners = new Set();
            $('.demand-row').each(function() {
                const val = String($(this).data('owner') || '').trim().toLowerCase();
                if (val) owners.add(val);
            });
            const sorted = Array.from(owners).sort();
            sorted.forEach(v => {
                const label = v.replace(/[._]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                $sel.append(`<option value="${v}">${label}</option>`);
            });
        };

        // Utilitário: parseia a primeira data dd/mm/aaaa presente em uma string
        const parsePtBrDate = (s) => {
            if (!s) return null;
            const m = String(s).match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (!m) return null;
            const d = new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
            return isNaN(d.getTime()) ? null : d;
        };

        const sortVisibleByDueDate = () => {
            const $tbody = $('#demands-table tbody');
            const rows = $tbody.find('tr.demand-row:visible').get();
            rows.sort((a, b) => {
                const aText = $(a).find('td:eq(5)').text();
                const bText = $(b).find('td:eq(5)').text();
                const ad = parsePtBrDate(aText);
                const bd = parsePtBrDate(bText);
                const at = ad ? ad.getTime() : Number.POSITIVE_INFINITY;
                const bt = bd ? bd.getTime() : Number.POSITIVE_INFINITY;
                if (at === bt) {
                    const aId = parseInt($(a).data('taskrow-id')) || 0;
                    const bId = parseInt($(b).data('taskrow-id')) || 0;
                    return aId - bId; // desempate estável
                }
                return at - bt; // ascendente por prazo
            });
            $.each(rows, function(_, row) {
                $tbody.append(row);
            });
        };

        // Filtros em tempo real
        function filterTable() {
            const searchTerm = $('#search-input').val().toLowerCase();
            const statusFilter = $('#filter-status').val();
            const clientFilter = $('#filter-client').val().toLowerCase();
            const ownerFilter = ($('#filter-owner').val() || '').toLowerCase();
            const groupFilter = ($('#filter-group').val() || '').toLowerCase();
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
                const group = ($row.data('group') || '').toString().toLowerCase();
                const owner = ($row.data('owner') || '').toString().toLowerCase();
                let createdAtStr = (($row.data('created') || '').toString()).trim();
                const dueDateStr = ($row.find('td:eq(5)').text() || '').trim();

                const matchesSearch = !searchTerm ||
                    title.includes(searchTerm) ||
                    client.includes(searchTerm) ||
                    String(taskrowId).includes(searchTerm);

                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesClient = !clientFilter || client.includes(clientFilter);
                const matchesGroup = !groupFilter || group.includes(groupFilter);
                const matchesOwner = !ownerFilter || owner.includes(ownerFilter);

                let matchesDate = true;

                if (dateFilter) {
                    // Função para verificar se uma data atende ao filtro
                    const withinFilter = (d) => {
                        if (!(d instanceof Date) || isNaN(d.getTime())) return false;
                        d.setHours(0, 0, 0, 0);
                        const currentMonth = today.getMonth();
                        const currentYear = today.getFullYear();
                        switch (dateFilter) {
                            case 'last-2-months': {
                                const twoMonthsAgo = new Date(today);
                                twoMonthsAgo.setMonth(today.getMonth() - 2);
                                return d >= twoMonthsAgo;
                            }
                            case 'current-month':
                                return d.getMonth() === currentMonth && d.getFullYear() === currentYear;
                            case 'last-month': {
                                const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
                                const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;
                                return d.getMonth() === lastMonth && d.getFullYear() === lastMonthYear;
                            }
                            case 'this-week': {
                                const weekStart = new Date(today);
                                weekStart.setDate(today.getDate() - today.getDay());
                                const weekEnd = new Date(weekStart);
                                weekEnd.setDate(weekStart.getDate() + 6);
                                return d >= weekStart && d <= weekEnd;
                            }
                            case 'last-week': {
                                const lastWeekStart = new Date(today);
                                lastWeekStart.setDate(today.getDate() - today.getDay() - 7);
                                const lastWeekEnd = new Date(lastWeekStart);
                                lastWeekEnd.setDate(lastWeekStart.getDate() + 6);
                                return d >= lastWeekStart && d <= lastWeekEnd;
                            }
                        }
                        return true;
                    };

                    const createdDate = parsePtBrDate(createdAtStr);
                    const dueDate = parsePtBrDate(dueDateStr);

                    const createdMatches = createdDate ? withinFilter(createdDate) : false;
                    const dueMatches = dueDate ? withinFilter(dueDate) : false;

                    // Considerar no período se a criação OU a entrega estiverem no intervalo
                    matchesDate = createdMatches || dueMatches;

                    if (dateFilter === 'last-2-months' && debugInfo.rows.length < 10) {
                        debugInfo.rows.push({
                            id: taskrowId,
                            created: createdAtStr,
                            due: dueDateStr,
                            c_ok: createdMatches,
                            d_ok: dueMatches,
                            matches: matchesDate
                        });
                    }
                }

                if (matchesSearch && matchesStatus && matchesClient && matchesGroup && matchesOwner && matchesDate) {
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

            // Ordena linhas visíveis por prazo (asc) como no Taskrow
            sortVisibleByDueDate();

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
        $('#filter-owner').on('change', filterTable);
        $('#filter-created-date').on('change', filterTable);
        $('#filter-group').on('change', filterTable);

        // Popular owners (caso backend não tenha preenchido)
        populateOwnerSelect();

        // Defaults ao carregar: Data = últimos 2 meses, Grupo = tech, Responsável = Raissa
        $('#filter-created-date').val('last-2-months');
        $('#filter-group').val('tech');
        const ownerDefault = (function() {
            let val = '';
            $('#filter-owner option').each(function() {
                const v = ($(this).val() || '').toLowerCase();
                if (!val && v.includes('raissa')) {
                    val = $(this).val();
                }
            });
            return val;
        })();
        if (ownerDefault) {
            $('#filter-owner').val(ownerDefault);
        }
        filterTable();

        // Limpar filtros
        $('#clear-filters-btn').on('click', function() {
            $('#search-input').val('');
            $('#filter-status').val('');
            $('#filter-client').val('');
            $('#filter-created-date').val('last-2-months');
            $('#filter-group').val('tech');
            const ownerDefault2 = (function() {
                let val = '';
                $('#filter-owner option').each(function() {
                    const v = ($(this).val() || '').toLowerCase();
                    if (!val && v.includes('raissa')) {
                        val = $(this).val();
                    }
                });
                return val;
            })();
            if (ownerDefault2) {
                $('#filter-owner').val(ownerDefault2);
            } else {
                $('#filter-owner').val('');
            }
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
            $statusText.text('Preparando importação...');
            $subStatusText.text('Conectando à API do Taskrow...');
            $modal.show();

            function addLog(msg, type = 'info') {
                const color = type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-muted');
                const time = new Date().toLocaleTimeString();
                $log.append(`<div class="${color}">[${time}] ${msg}</div>`);
                $log.scrollTop($log[0].scrollHeight);
            }

            addLog('Iniciando importação...', 'info');
            // Barra de progresso em etapas simuladas até 95% (finalização vai a 100%)
            let progress = 0;
            const setProgress = (p) => {
                p = Math.max(0, Math.min(100, Math.floor(p)));
                $progressBar.css('width', p + '%').text(p + '%');
            };
            const stages = [{
                    threshold: 1,
                    status: 'Conectando...',
                    sub: 'Validando credenciais'
                },
                {
                    threshold: 8,
                    status: 'Carregando usuários...',
                    sub: 'Montando mapa UserID → Login'
                },
                {
                    threshold: 20,
                    status: 'Buscando projetos...',
                    sub: 'Localizando clientes/projetos permitidos'
                },
                {
                    threshold: 40,
                    status: 'Importando tarefas...',
                    sub: 'Paginando resultados (lotes grandes)'
                },
                {
                    threshold: 65,
                    status: 'Gravando no banco...',
                    sub: 'Upsert de novas/atualizadas'
                },
                {
                    threshold: 85,
                    status: 'Finalizando...',
                    sub: 'Limpando memória e preparando resumo'
                }
            ];
            let stageIndexApplied = -1;
            const tick = () => {
                if (progress >= 95) return; // aguarda retorno real para 100%
                const delta = Math.random() * 2.2 + 0.8; // 0.8% a 3.0%
                progress = Math.min(95, progress + delta);
                setProgress(progress);
                // atualizar textos por estágio
                for (let i = stages.length - 1; i >= 0; i--) {
                    if (progress >= stages[i].threshold && stageIndexApplied !== i) {
                        stageIndexApplied = i;
                        $statusText.text(stages[i].status);
                        $subStatusText.text(stages[i].sub);
                        addLog(stages[i].status + ' — ' + stages[i].sub);
                        break;
                    }
                }
            };
            const timer = setInterval(tick, 400);
            setProgress(0);

            $.post(f2f_ajax.ajaxurl, {
                    action: 'f2f_import_by_clients',
                    nonce: f2f_ajax.nonce
                })
                .done(function(response) {
                    clearInterval(timer);
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
                    setProgress(100);
                    $progressBar.addClass('bg-success');
                    $closeBtn.prop('disabled', false).text('Concluir e Recarregar');

                    $closeBtn.off('click').on('click', function() {
                        location.reload();
                    });

                })
                .fail(function() {
                    clearInterval(timer);
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
            // Restaurar descrição imediata (usa full se disponível) para exibir algo enquanto carrega API
            const fullDescHtml = $row.find('.task-description-full').html();
            const description = fullDescHtml ? fullDescHtml : ($row.find('.task-description').text() || 'Sem descrição disponível');
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
                    <div class="detail-section mb-4" id="api-description-wrapper">
                        <h6 class="fw-semibold mb-2"><i class="fas fa-align-left me-1"></i>Descrição (Taskrow API)</h6>
                    </div>
                    <div class="border rounded p-3 bg-white small" style="max-height:250px;overflow:auto;">
                        ${description}
                    </div>
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

            // Buscar descrição completa via AJAX
            // Botões do modal: abrir Taskrow e exportar para ClickUp
            (function(){
                let host = (typeof f2f_ajax !== 'undefined' && f2f_ajax.taskrow_host) ? String(f2f_ajax.taskrow_host) : String(TASKROW_HOST || '');
                // Garantir URL absoluta: se não tiver protocolo, assumir https
                if (host && !/^https?:\/\//i.test(host)) {
                    host = 'https://' + host;
                }
                let taskrowUrl = '';
                // Construir URL completa no padrão: /#dashboard/tasks/{clientNickname}/{jobNumber}/{taskNumber}
                const clientNickname = ($row.data('client-nickname') || '').toString().trim();
                const jobNumber = ($row.data('job-number') || '').toString().trim();
                const taskNumber = ($row.data('task-number') || '').toString().trim();
                if (host && clientNickname && jobNumber && taskNumber) {
                    taskrowUrl = host.replace(/\/$/, '') + '/#dashboard/tasks/' + encodeURIComponent(clientNickname) + '/' + encodeURIComponent(jobNumber) + '/' + encodeURIComponent(taskNumber);
                } else if (host && taskrowId) {
                    // Fallback simples caso não tenhamos os três parâmetros
                    taskrowUrl = host.replace(/\/$/, '') + '/#/tasks/' + encodeURIComponent(taskrowId);
                }
                $('#modal-open-taskrow-btn').prop('disabled', !taskrowUrl).off('click').on('click', function(){
                    if (!taskrowUrl) return;
                    window.open(taskrowUrl, '_blank');
                });
                $('#modal-export-clickup-btn').off('click').on('click', function(){
                    const id = demandId;
                    const $btn = $('.send-to-clickup-btn[data-demand-id="' + id + '"]');
                    if ($btn.length) { $btn.trigger('click'); }
                });
            })();

            // Buscar descrição completa via AJAX
            const jobNumber = $row.data('job-number');
            const taskNumber = $row.data('task-number');
            const clientNickname = $row.data('client-nickname');

            if (!jobNumber || !taskNumber || !clientNickname) {
                $('#api-description-loading').addClass('d-none');
                $('#api-description-error').removeClass('d-none').text('Dados insuficientes para obter descrição.');
                return;
            }

            $.post(f2f_ajax.ajaxurl, {
                action: 'f2f_get_taskrow_description',
                nonce: f2f_ajax.nonce,
                clientNickname: clientNickname,
                jobNumber: jobNumber,
                taskNumber: taskNumber
            }).done(function(resp) {
                $('#api-description-loading').addClass('d-none');
                if (!resp || !resp.success) {
                    const msg = (resp && resp.data && resp.data.message) ? resp.data.message : (resp && resp.data ? resp.data : 'Erro desconhecido');
                    $('#api-description-error').removeClass('d-none').text('Falha: ' + msg);
                    return;
                }
                const data = resp.data;
                const htmlRaw = data.descriptionHtml || '';
                const textClean = data.descriptionText || '';
                let renderBlock = htmlRaw ? htmlRaw : '<em>Sem conteúdo</em>';
                $('#api-description-content').removeClass('d-none').html(renderBlock);
                const source = data.source ? 'Origem: ' + data.source : 'Origem não encontrada';
                $('#api-description-source').removeClass('d-none').text(source);
            }).fail(function() {
                $('#api-description-loading').addClass('d-none');
                $('#api-description-error').removeClass('d-none').text('Erro de rede ao obter descrição.');
            });
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

        // === Descrição da Task (TaskDetail) ===
        const descModalEl = document.getElementById('taskDescriptionModal');
        let descModal = null;
        if (window.bootstrap && descModalEl) {
            descModal = new window.bootstrap.Modal(descModalEl);
        }

        function showDescSection(id) {
            ['task-desc-loading', 'task-desc-error', 'task-desc-success'].forEach(function(k) {
                const el = document.getElementById(k);
                if (el) el.style.display = 'none';
            });
            const target = document.getElementById(id);
            if (target) target.style.display = 'block';
        }

        $(document).on('click', '.view-description-btn', function() {
            const clientNickname = $(this).data('client-nickname');
            const jobNumber = $(this).data('job-number');
            const taskNumber = $(this).data('task-number');

            if (!clientNickname || !jobNumber || !taskNumber) {
                showMessage('warning', 'Dados insuficientes para buscar descrição.');
                return;
            }
            if (descModal) {
                descModal.show();
            }
            showDescSection('task-desc-loading');
            $('#task-desc-raw').text('');
            $('#task-desc-render').html('');
            $('#task-desc-text').text('');
            $('#task-desc-source').text('');
            $('#task-desc-endpoint').text('');
            $('#task-desc-copy-btn').hide();

            $.post(f2f_ajax.ajaxurl, {
                action: 'f2f_get_taskrow_description',
                nonce: f2f_ajax.nonce,
                clientNickname: clientNickname,
                jobNumber: jobNumber,
                taskNumber: taskNumber
            }).done(function(resp) {
                if (!resp || !resp.success) {
                    const msg = (resp && resp.data && resp.data.message) ? resp.data.message : (resp && resp.data ? resp.data : 'Erro desconhecido');
                    $('#task-desc-error').text('Falha: ' + msg);
                    showDescSection('task-desc-error');
                    return;
                }
                const data = resp.data;
                $('#task-desc-raw').text(data.descriptionHtml || '(vazio)');
                $('#task-desc-render').html(data.descriptionHtml || '<em>Sem conteúdo</em>');
                $('#task-desc-text').text(data.descriptionText || '(vazio)');
                $('#task-desc-source').text(data.source || '(não encontrado)');
                $('#task-desc-endpoint').text(data.endpoint || '');
                $('#task-desc-copy-btn').show();
                showDescSection('task-desc-success');
            }).fail(function() {
                $('#task-desc-error').text('Erro de rede ao obter descrição.');
                showDescSection('task-desc-error');
            });
        });

        $('#task-desc-copy-btn').on('click', function() {
            const text = $('#task-desc-text').text();
            navigator.clipboard.writeText(text).then(() => {
                $('#task-desc-copy-btn').html('<i class="fas fa-check me-1"></i> Copiado');
                setTimeout(() => {
                    $('#task-desc-copy-btn').html('<i class="fas fa-copy me-1"></i> Copiar Texto');
                }, 1500);
            });
        });
    });
    // Garantir objeto f2f_ajax (caso não venha do wp_localize_script por algum motivo)
    window.f2f_ajax = window.f2f_ajax || {
        ajaxurl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
        nonce: '<?php echo esc_js(wp_create_nonce('f2f_taskrow_desc')); ?>'
    };
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

    /* Fixa layout da tabela para respeitar larguras dos cabeçalhos */
    .taskrow-table {
        table-layout: fixed;
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
        max-width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        text-overflow: ellipsis;
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
        white-space: normal;
        overflow: hidden;
        text-overflow: ellipsis;
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