<?php
/**
 * Template Name: Sincronizar Horas
 * 
 * Página para sincronizar horas do ClickUp para o Taskrow
 */

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(home_url());
    exit;
}

get_header();

global $wpdb;
$table_name = $wpdb->prefix . 'f2f_taskrow_demands';

// Buscar demandas que estão no ClickUp
$demands = $wpdb->get_results(
    "SELECT * FROM {$table_name} 
     WHERE clickup_id IS NOT NULL 
     ORDER BY created_at DESC"
);

$total_demands = count($demands);
$synced_demands = count(array_filter($demands, function($d) { return $d->hours_synced; }));
$pending_sync = $total_demands - $synced_demands;
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mb-3">⏱️ Sincronizar Horas</h1>
            <p class="text-muted">
                Sincronize as horas trabalhadas do ClickUp para o Taskrow
            </p>
            
            <!-- Estatísticas -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $total_demands; ?></h3>
                            <p class="mb-0">Total no ClickUp</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $pending_sync; ?></h3>
                            <p class="mb-0">Pendente Sincronização</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $synced_demands; ?></h3>
                            <p class="mb-0">Sincronizadas</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botões de Ação -->
            <div class="mb-4">
                <button type="button" class="btn btn-success btn-lg" id="sync-all-btn">
                    <i class="fas fa-sync-alt me-2"></i>
                    Sincronizar Todas as Horas
                </button>
                <a href="<?php echo home_url('/demandas-taskrow/'); ?>" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>
                    Voltar para Demandas
                </a>
            </div>
            
            <!-- Mensagens -->
            <div id="message-container"></div>
        </div>
    </div>
    
    <!-- Lista de Demandas -->
    <div class="row">
        <div class="col-md-12">
            <?php if (empty($demands)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Nenhuma demanda no ClickUp encontrada. 
                    <a href="<?php echo home_url('/demandas-taskrow/'); ?>">Envie demandas primeiro</a>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Taskrow</th>
                                <th>Título</th>
                                <th>Cliente</th>
                                <th>Horas Rastreadas</th>
                                <th>Última Sinc.</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demands as $demand): ?>
                                <tr data-demand-id="<?php echo $demand->id; ?>">
                                    <td>
                                        <small class="text-muted">#<?php echo $demand->taskrow_id; ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($demand->title); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            ClickUp: #<?php echo $demand->clickup_id; ?>
                                        </small>
                                    </td>
                                    <td><?php echo esc_html($demand->client_name ?: '-'); ?></td>
                                    <td>
                                        <strong class="hours-display">
                                            <?php echo number_format($demand->hours_tracked, 2); ?>h
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if ($demand->last_sync): ?>
                                            <small>
                                                <?php echo date('d/m/Y H:i', strtotime($demand->last_sync)); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($demand->hours_synced): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>
                                                Sincronizada
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>
                                                Pendente
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary sync-hours-btn" 
                                                data-demand-id="<?php echo $demand->id; ?>">
                                            <i class="fas fa-sync-alt me-1"></i>
                                            Sincronizar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Progresso -->
<div class="modal fade" id="syncProgressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    Sincronizando Horas
                </h5>
            </div>
            <div class="modal-body">
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         style="width: 0%" 
                         id="sync-progress-bar">
                        0%
                    </div>
                </div>
                <p id="sync-status-text">Preparando...</p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Sincronizar Horas Individual
    $(document).on('click', '.sync-hours-btn', function() {
        const $btn = $(this);
        const demandId = $btn.data('demand-id');
        const $row = $btn.closest('tr');
        const originalText = $btn.html();
        
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin me-1"></i>Sincronizando...');
        
        syncDemand(demandId)
            .then(function(result) {
                if (result.success) {
                    // Atualizar display
                    $row.find('.hours-display').text(result.hours + 'h');
                    $row.find('td:eq(5)').html('<span class="badge bg-success"><i class="fas fa-check me-1"></i>Sincronizada</span>');
                    showMessage('success', result.message);
                    
                    $btn.html('<i class="fas fa-check me-1"></i>Sincronizado');
                    setTimeout(() => {
                        $btn.prop('disabled', false).html(originalText);
                    }, 2000);
                } else {
                    showMessage('danger', result.message);
                    $btn.prop('disabled', false).html(originalText);
                }
            })
            .catch(function(error) {
                showMessage('danger', 'Erro ao sincronizar: ' + error);
                $btn.prop('disabled', false).html(originalText);
            });
    });
    
    // Sincronizar Todas
    $('#sync-all-btn').on('click', function() {
        const $rows = $('tbody tr');
        const total = $rows.length;
        
        if (total === 0) {
            showMessage('warning', 'Nenhuma demanda para sincronizar');
            return;
        }
        
        if (!confirm(`Deseja sincronizar ${total} demanda(s)?`)) {
            return;
        }
        
        // Mostrar modal de progresso
        const modal = new bootstrap.Modal($('#syncProgressModal')[0]);
        modal.show();
        
        let completed = 0;
        const promises = [];
        
        $rows.each(function() {
            const demandId = $(this).data('demand-id');
            promises.push(syncDemand(demandId));
        });
        
        // Processar em lote
        Promise.allSettled(promises).then(function(results) {
            let success = 0;
            let failed = 0;
            
            results.forEach(function(result, index) {
                completed++;
                updateProgress(completed, total);
                
                if (result.status === 'fulfilled' && result.value.success) {
                    success++;
                } else {
                    failed++;
                }
            });
            
            // Fechar modal e mostrar resultado
            setTimeout(() => {
                modal.hide();
                
                let message = `Sincronização concluída! ✅ ${success} sucesso`;
                if (failed > 0) {
                    message += `, ❌ ${failed} erro(s)`;
                }
                
                showMessage(failed > 0 ? 'warning' : 'success', message);
                
                // Recarregar página após 3 segundos
                setTimeout(() => location.reload(), 3000);
            }, 1000);
        });
    });
    
    // Função para sincronizar uma demanda
    function syncDemand(demandId) {
        return new Promise((resolve, reject) => {
            $.post(ajaxurl, {
                action: 'f2f_sync_hours_to_taskrow',
                demand_id: demandId
            })
            .done(function(response) {
                if (response.success) {
                    resolve({
                        success: true,
                        message: response.data.message,
                        hours: response.data.hours
                    });
                } else {
                    resolve({
                        success: false,
                        message: response.data
                    });
                }
            })
            .fail(function() {
                reject('Erro na requisição');
            });
        });
    }
    
    // Atualizar barra de progresso
    function updateProgress(current, total) {
        const percentage = Math.round((current / total) * 100);
        $('#sync-progress-bar')
            .css('width', percentage + '%')
            .text(percentage + '%');
        $('#sync-status-text').text(`Sincronizando ${current} de ${total}...`);
    }
    
    // Função auxiliar para mostrar mensagens
    function showMessage(type, message) {
        const html = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#message-container').html(html);
        
        // Auto-remover após 5 segundos
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }
});
</script>

<style>
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.02);
}

.badge {
    padding: 0.35em 0.65em;
}

.hours-display {
    font-size: 1.1em;
    color: #0d6efd;
}
</style>

<?php get_footer(); ?>

