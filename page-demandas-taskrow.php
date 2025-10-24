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
$pending_demands = count(array_filter($demands, function($d) { return $d->status === 'pending'; }));
$sent_demands = count(array_filter($demands, function($d) { return $d->status === 'sent_to_clickup'; }));
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-12">
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
                <h6><i class="fas fa-<?php echo $api_configured ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>Status da API Taskrow</h6>
                <?php if ($api_configured): ?>
                    <p class="mb-0">✅ API configurada corretamente</p>
                    <small>Host: <code><?php echo esc_html($api_host); ?></code> | Token: <code><?php echo esc_html(substr($api_token, 0, 10)); ?>...</code></small>
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
                <a href="<?php echo admin_url('admin.php?page=f2f-taskrow-config'); ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-cog me-2"></i>
                    Configurações
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
                    Nenhuma demanda encontrada. Clique em "Importar Demandas" para buscar do Taskrow.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Prioridade</th>
                                <th>Data de Entrega</th>
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
                                        <?php if ($demand->description): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo esc_html(wp_trim_words($demand->description, 15)); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($demand->client_name ?: '-'); ?></td>
                                    <td>
                                        <?php if ($demand->status === 'pending'): ?>
                                            <span class="badge bg-warning">Pendente</span>
                                        <?php elseif ($demand->status === 'sent_to_clickup'): ?>
                                            <span class="badge bg-success">No ClickUp</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo esc_html($demand->status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($demand->priority): ?>
                                            <span class="badge bg-danger"><?php echo esc_html($demand->priority); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ( ! empty( $demand->due_date ) ) {
                                            $timestamp = strtotime( $demand->due_date );
                                            if ( $timestamp !== false && $timestamp > 0 ) {
                                                echo date( 'd/m/Y', $timestamp );
                                            } else {
                                                echo '<span class="text-danger" title="Data inválida">Data inválida</span>';
                                            }
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($demand->clickup_id): ?>
                                            <button class="btn btn-sm btn-info" disabled>
                                                <i class="fas fa-check me-1"></i>
                                                Já no ClickUp
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-primary send-to-clickup-btn" 
                                                    data-demand-id="<?php echo $demand->id; ?>">
                                                <i class="fas fa-arrow-right me-1"></i>
                                                Enviar ao ClickUp
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-outline-secondary view-details-btn" 
                                                data-demand-id="<?php echo $demand->id; ?>">
                                            <i class="fas fa-eye"></i>
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

<script>
jQuery(document).ready(function($) {
    
    // Testar Conexão Taskrow
    $('#test-connection-btn').on('click', function() {
        console.log('Botão de teste clicado!');
        console.log('f2f_ajax:', f2f_ajax);
        
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin me-2"></i>Testando...');
        
        console.log('Fazendo requisição AJAX...');
        $.post(f2f_ajax.ajaxurl, {
            action: 'f2f_test_taskrow_connection',
            nonce: f2f_ajax.nonce
        })
        .done(function(response) {
            console.log('Resposta recebida:', response);
            if (response.success) {
                showMessage('success', `
                    <strong>✅ Conexão bem-sucedida!</strong><br>
                    ${response.data.message}<br>
                    <small>Endpoint: ${response.data.endpoint}</small>
                `);
            } else {
                showMessage('danger', `
                    <strong>❌ Erro na conexão:</strong><br>
                    ${response.data}
                `);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('Erro na requisição:', xhr, status, error);
            showMessage('danger', `<strong>❌ Erro:</strong> Falha na requisição AJAX. Status: ${status}, Error: ${error}`);
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
        
        console.log('Apagando todas as demandas...');
        $.post(f2f_ajax.ajaxurl, {
            action: 'f2f_clear_all_taskrow_demands',
            nonce: f2f_ajax.nonce
        })
        .done(function(response) {
            console.log('Resposta recebida:', response);
            if (response.success) {
                showMessage('success', `
                    <strong>✅ Demandas apagadas com sucesso!</strong><br>
                    ${response.data.message}
                `);
                setTimeout(() => location.reload(), 2000);
            } else {
                showMessage('danger', `
                    <strong>❌ Erro ao apagar demandas:</strong><br>
                    ${response.data}
                `);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('Erro na requisição:', xhr, status, error);
            showMessage('danger', `<strong>❌ Erro:</strong> Falha na requisição AJAX. Status: ${status}, Error: ${error}`);
        })
        .always(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    });
    
    // Importar Demandas
    $('#import-demands-btn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin me-2"></i>Importando...');
        
        $.post(f2f_ajax.ajaxurl, {
            action: 'f2f_import_taskrow_demands',
            nonce: f2f_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                showMessage('success', response.data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                showMessage('danger', response.data);
            }
        })
        .fail(function() {
            showMessage('danger', 'Erro ao importar demandas');
        })
        .always(function() {
            $btn.prop('disabled', false).html(originalText);
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
            .html('<i class="fas fa-spinner fa-spin me-1"></i>Enviando...');
        
        $.post(f2f_ajax.ajaxurl, {
            action: 'f2f_send_demand_to_clickup',
            demand_id: demandId,
            nonce: f2f_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                showMessage('success', response.data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                showMessage('danger', response.data);
                $btn.prop('disabled', false).html(originalText);
            }
        })
        .fail(function() {
            showMessage('danger', 'Erro ao enviar demanda');
            $btn.prop('disabled', false).html(originalText);
        });
    });
    
    // Ver Detalhes
    $(document).on('click', '.view-details-btn', function() {
        const demandId = $(this).data('demand-id');
        const $row = $('[data-demand-id="' + demandId + '"]');
        
        // Buscar dados da linha
        const title = $row.find('td:eq(1) strong').text();
        const description = $row.find('td:eq(1) small').text() || 'Sem descrição';
        const client = $row.find('td:eq(2)').text();
        const status = $row.find('td:eq(3)').text();
        const priority = $row.find('td:eq(4)').text();
        const dueDate = $row.find('td:eq(5)').text();
        
        // Montar conteúdo do modal
        let html = `
            <div class="mb-3">
                <h6>Título:</h6>
                <p>${title}</p>
            </div>
            <div class="mb-3">
                <h6>Descrição:</h6>
                <p>${description}</p>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <h6>Cliente:</h6>
                    <p>${client}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <h6>Status:</h6>
                    <p>${status}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <h6>Prioridade:</h6>
                    <p>${priority}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <h6>Data de Entrega:</h6>
                    <p>${dueDate}</p>
                </div>
            </div>
        `;
        
        $('#modal-demand-details').html(html);
        new bootstrap.Modal($('#demandDetailsModal')[0]).show();
    });
    
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
</style>

<?php get_footer(); ?>

