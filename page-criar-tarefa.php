<?php
/**
 * Template Name: Criar Tarefa ClickUp
 * Description: Página para criar tarefas no ClickUp (apenas para administradores)
 */

// Verificar se o usuário é administrador
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    // Redireciona para login se não for admin
    wp_redirect(home_url('/client-login/'));
    exit;
}

// Enfileira scripts necessários
wp_enqueue_script('jquery');
wp_enqueue_script('mainjs', get_theme_file_uri('build/main.js'), array('jquery'), '1.0.0', true);

// Localiza script para AJAX
wp_localize_script('mainjs', 'f2f_ajax', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('f2f_ajax_nonce')
));

get_header();

$api = F2F_ClickUp_API::get_instance();
$default_list = get_option('f2f_clickup_default_list', '');
$is_configured = $api->is_configured();

// Busca workspaces e listas se configurado
$workspaces = array();
$lists = array();

if ($is_configured) {
    $workspaces = $api->get_workspaces();
    if (!is_wp_error($workspaces) && !empty($workspaces)) {
        $default_workspace = get_option('f2f_clickup_default_workspace', '');
        if ($default_workspace) {
            $spaces = $api->get_spaces($default_workspace);
            if (!is_wp_error($spaces)) {
                foreach ($spaces as $space) {
                    $space_lists = $api->get_lists($space['id']);
                    if (!is_wp_error($space_lists)) {
                        foreach ($space_lists as $list) {
                            $lists[] = array(
                                'id' => $list['id'],
                                'name' => $space['name'] . ' > ' . $list['name']
                            );
                        }
                    }
                }
            }
        }
    }
}
?>

<div class="f2f-dashboard-wrapper">
    <div class="container-fluid px-4 py-4">
        
        <!-- Header -->
        <div class="dashboard-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="dashboard-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Criar Nova Tarefa no ClickUp
                    </h1>
                    <p class="dashboard-subtitle">Crie e gerencie tarefas diretamente no ClickUp</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-outline-warning me-2" id="test-members-btn">
                        <i class="fas fa-users me-1"></i> Teste Membros
                    </button>
                    <button type="button" class="btn btn-outline-info me-2" id="debug-config-btn">
                        <i class="fas fa-bug me-1"></i> Debug
                    </button>
                    <button type="button" class="btn btn-outline-success me-2" id="test-connection-btn">
                        <i class="fas fa-wifi me-1"></i> Testar Conexão
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=f2f-clickup-api'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-cog me-1"></i> Configurações
                    </a>
                    <a href="<?php echo home_url('/'); ?>" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-home me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php if (!$is_configured) : ?>
        <!-- Aviso de não configurado -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h4 class="alert-heading">ClickUp API não está configurada</h4>
                        <p class="mb-0">
                            Para criar tarefas, você precisa configurar sua API Token do ClickUp.
                            <a href="<?php echo admin_url('admin.php?page=f2f-clickup-api'); ?>" class="alert-link">
                                Clique aqui para configurar
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php else : ?>
        
        <!-- Formulário de Criar Tarefa -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-gradient-primary text-white">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-edit me-2"></i>
                            Nova Tarefa
                        </h3>
                    </div>
                    
                    <div class="card-body p-4">
                        <form id="clickup-task-form">
                            <?php wp_nonce_field('f2f_create_clickup_task_frontend', 'f2f_nonce'); ?>
                            
                            <!-- Nome da Tarefa -->
                            <div class="form-group mb-4">
                                <label for="task_name" class="form-label fw-bold">
                                    <i class="fas fa-tasks text-primary me-2"></i>
                                    Nome da Tarefa *
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="task_name" 
                                       name="task_name" 
                                       placeholder="Ex: Implementar novo recurso de notificações"
                                       required>
                            </div>
                            
                            <!-- Descrição -->
                            <div class="form-group mb-4">
                                <label for="task_description" class="form-label fw-bold">
                                    <i class="fas fa-align-left text-primary me-2"></i>
                                    Descrição
                                </label>
                                <textarea class="form-control" 
                                          id="task_description" 
                                          name="task_description" 
                                          rows="6"
                                          placeholder="Descreva a tarefa em detalhes...&#10;&#10;Contexto:&#10;- ...&#10;&#10;Requisitos:&#10;- ..."></textarea>
                            </div>
                            
                            <div class="row">
                                <!-- Lista -->
                                <div class="col-md-6 form-group mb-4">
                                    <label for="task_list" class="form-label fw-bold">
                                        <i class="fas fa-list text-primary me-2"></i>
                                        Lista
                                    </label>
                                    <select class="form-control form-select" 
                                            id="task_list" 
                                            name="task_list">
                                        <?php if (!empty($lists)) : ?>
                                            <?php foreach ($lists as $list) : ?>
                                                <option value="<?php echo esc_attr($list['id']); ?>" 
                                                        <?php selected($default_list, $list['id']); ?>>
                                                    <?php echo esc_html($list['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <option value="<?php echo esc_attr($default_list); ?>">
                                                Lista Padrão
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <!-- Prioridade -->
                                <div class="col-md-6 form-group mb-4">
                                    <label for="task_priority" class="form-label fw-bold">
                                        <i class="fas fa-flag text-primary me-2"></i>
                                        Prioridade
                                    </label>
                                    <select class="form-control form-select" 
                                            id="task_priority" 
                                            name="task_priority">
                                        <option value="">Nenhuma</option>
                                        <option value="1">🔴 Urgente</option>
                                        <option value="2">🟡 Alta</option>
                                        <option value="3" selected>🔵 Normal</option>
                                        <option value="4">⚪ Baixa</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Atribuir para -->
                                <div class="col-12 form-group mb-4">
                                    <label for="task_assignees" class="form-label fw-bold">
                                        <i class="fas fa-user-plus text-primary me-2"></i>
                                        Atribuir para
                                    </label>
                                    
                                    <!-- Dropdown customizado -->
                                    <div class="assignee-dropdown-container">
                                        <div class="assignee-dropdown" id="assignee-dropdown">
                                            <div class="assignee-dropdown-toggle" id="assignee-toggle">
                                                <span class="assignee-placeholder">Selecionar membro(s)...</span>
                                                <i class="fas fa-chevron-down assignee-arrow"></i>
                                            </div>
                                            <div class="assignee-dropdown-menu" id="assignee-menu">
                                                <div class="assignee-search">
                                                    <input type="text" id="assignee-search" placeholder="Buscar membros..." class="form-control form-control-sm">
                                                </div>
                                                <div class="assignee-list" id="assignee-list">
                                                    <div class="assignee-loading">
                                                        <i class="fas fa-spinner fa-spin me-2"></i>
                                                        Carregando membros...
                                                    </div>
                                                </div>
                                                <div class="assignee-actions">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="reload-members-btn">
                                                        <i class="fas fa-sync-alt me-1"></i> Recarregar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-assignees-btn">
                                                        <i class="fas fa-times me-1"></i> Limpar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Tags dos selecionados -->
                                        <div class="assignee-selected" id="assignee-selected"></div>
                                        
                                        <!-- Input hidden para enviar os dados -->
                                        <input type="hidden" id="task_assignees" name="task_assignees" value="">
                                    </div>
                                    
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Clique para selecionar membros. Use a busca para filtrar.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Data de Início -->
                                <div class="col-md-6 form-group mb-4">
                                    <label for="task_start_date" class="form-label fw-bold">
                                        <i class="fas fa-play-circle text-primary me-2"></i>
                                        Data de Início
                                    </label>
                                    <input type="datetime-local" 
                                           class="form-control" 
                                           id="task_start_date" 
                                           name="task_start_date">
                                </div>
                                
                                <!-- Data de Entrega -->
                                <div class="col-md-6 form-group mb-4">
                                    <label for="task_due_date" class="form-label fw-bold">
                                        <i class="fas fa-calendar-check text-primary me-2"></i>
                                        Data de Entrega
                                    </label>
                                    <input type="datetime-local" 
                                           class="form-control" 
                                           id="task_due_date" 
                                           name="task_due_date">
                                </div>
                            </div>
                            
                            <!-- Tags -->
                            <div class="form-group mb-4">
                                <label for="task_tags" class="form-label fw-bold">
                                    <i class="fas fa-tags text-primary me-2"></i>
                                    Tags
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="task_tags" 
                                       name="task_tags"
                                       placeholder="Ex: urgente, frontend, cliente-x">
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Separe as tags com vírgulas
                                </small>
                            </div>
                            
                            <!-- Botão Submit -->
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" 
                                        class="btn btn-primary btn-lg" 
                                        id="submit-task-btn">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    <span class="btn-text">Criar Tarefa no ClickUp</span>
                                    <span class="btn-loading d-none">
                                        <i class="fas fa-spinner fa-spin me-2"></i>
                                        Criando tarefa...
                                    </span>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Resultado -->
                        <div id="task-result" class="mt-4"></div>
                    </div>
                </div>
                
                <!-- Card de Ajuda -->
                <div class="card mt-4 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Dicas Rápidas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-flag text-danger me-2"></i>Prioridades</h6>
                                <ul class="small">
                                    <li><strong>🔴 Urgente:</strong> Bloqueadores e emergências</li>
                                    <li><strong>🟡 Alta:</strong> Importantes e urgentes</li>
                                    <li><strong>🔵 Normal:</strong> Tarefas regulares</li>
                                    <li><strong>⚪ Baixa:</strong> Backlog e melhorias</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-tags text-info me-2"></i>Sugestões de Tags</h6>
                                <ul class="small">
                                    <li><strong>bug:</strong> Para reportar problemas</li>
                                    <li><strong>melhoria:</strong> Para sugestões</li>
                                    <li><strong>urgente:</strong> Requer atenção imediata</li>
                                    <li><strong>frontend/backend:</strong> Área específica</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card {
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.card-header {
    border-bottom: none;
    padding: 1.5rem;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.form-label {
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 1rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.alert {
    border-radius: 12px;
    border: none;
}

.shadow-sm {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.task-success-alert {
    animation: slideInUp 0.4s ease;
}

/* Dropdown customizado para atribuídos */
.assignee-dropdown-container {
    position: relative;
}

.assignee-dropdown {
    position: relative;
    width: 100%;
}

.assignee-dropdown-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    background-color: #fff;
    cursor: pointer;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.assignee-dropdown-toggle:hover {
    border-color: #86b7fe;
}

.assignee-dropdown-toggle.active {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.assignee-placeholder {
    color: #6c757d;
    font-size: 0.875rem;
}

.assignee-arrow {
    transition: transform 0.2s ease;
    color: #6c757d;
}

.assignee-dropdown-toggle.active .assignee-arrow {
    transform: rotate(180deg);
}

.assignee-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ced4da;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    z-index: 1000;
    display: none;
    max-height: 300px;
    overflow: hidden;
}

.assignee-dropdown-menu.show {
    display: block;
}

.assignee-search {
    padding: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.assignee-list {
    max-height: 200px;
    overflow-y: auto;
}

.assignee-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    transition: background-color 0.15s ease;
    border-bottom: 1px solid #f8f9fa;
}

.assignee-item:hover {
    background-color: #f8f9fa;
}

.assignee-item.selected {
    background-color: #e7f3ff;
    color: #0d6efd;
}

.assignee-item input[type="checkbox"] {
    margin-right: 0.5rem;
}

.assignee-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: 0.5rem;
    background-color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: bold;
}

.assignee-info {
    flex: 1;
}

.assignee-name {
    font-weight: 500;
    margin: 0;
    font-size: 0.875rem;
}

.assignee-email {
    color: #6c757d;
    font-size: 0.75rem;
    margin: 0;
}

.assignee-actions {
    padding: 0.5rem;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 0.5rem;
}

.assignee-selected {
    margin-top: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.assignee-tag {
    display: inline-flex;
    align-items: center;
    background-color: #e7f3ff;
    color: #0d6efd;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    border: 1px solid #b3d9ff;
}

.assignee-tag .remove {
    margin-left: 0.25rem;
    cursor: pointer;
    color: #6c757d;
}

.assignee-tag .remove:hover {
    color: #dc3545;
}

.assignee-loading {
    padding: 1rem;
    text-align: center;
    color: #6c757d;
}

.assignee-empty {
    padding: 1rem;
    text-align: center;
    color: #6c757d;
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Verificar se f2f_ajax está disponível
    console.log('f2f_ajax disponível:', typeof f2f_ajax !== 'undefined');
    if (typeof f2f_ajax !== 'undefined') {
        console.log('f2f_ajax.ajaxurl:', f2f_ajax.ajaxurl);
    } else {
        console.error('f2f_ajax não está definido!');
    }
    
    // Debug configuração
    $('#debug-config-btn').on('click', function() {
        console.log('Botão Debug clicado');
        
        $.post(f2f_ajax.ajaxurl, {
            action: 'f2f_debug_clickup_config'
        }, function(response) {
            console.log('Debug config:', response);
            
            var debugInfo = '🔍 DEBUG CONFIGURAÇÃO CLICKUP\n\n';
            debugInfo += 'Token existe: ' + (response.data.token_exists ? '✅ SIM' : '❌ NÃO') + '\n';
            debugInfo += 'Token preview: ' + response.data.token_preview + '\n';
            debugInfo += 'Lista padrão: ' + (response.data.default_list || 'N/A') + '\n';
            debugInfo += 'Workspace padrão: ' + (response.data.default_workspace || 'N/A') + '\n';
            debugInfo += 'API configurada: ' + (response.data.api_configured ? '✅ SIM' : '❌ NÃO') + '\n';
            debugInfo += 'AJAX URL: ' + response.data.ajaxurl + '\n';
            debugInfo += 'f2f_ajax disponível: ' + (typeof f2f_ajax !== 'undefined' ? '✅ SIM' : '❌ NÃO');
            
            alert(debugInfo);
        }).fail(function(xhr, status, error) {
            console.error('Erro no debug:', error);
            alert('❌ Erro no debug: ' + error);
        });
    });
    
    // Teste de membros (sem API)
    $('#test-members-btn').on('click', function() {
        console.log('Testando membros fictícios...');
        
        var $list = $('#assignee-list');
        console.log('Lista encontrada para teste:', $list.length);
        $list.html('<div class="assignee-loading"><i class="fas fa-spinner fa-spin me-2"></i>Testando...</div>');
        
        console.log('Enviando requisição AJAX...');
        $.post(f2f_ajax.ajaxurl, {
            action: 'f2f_test_members'
        }, function(response) {
            console.log('Resposta do teste recebida:', response);
            
            if (response.success && response.data.members.length > 0) {
                console.log('Membros de teste carregados:', response.data.members);
                assigneeMembers = response.data.members;
                renderMembersList(assigneeMembers);
                alert('✅ Teste de membros funcionou!\n\nMembros carregados: ' + response.data.members.length);
            } else {
                console.log('Erro na resposta do teste');
                $list.html('<div class="assignee-empty">Erro no teste</div>');
                alert('❌ Erro no teste de membros');
            }
        }).fail(function(xhr, status, error) {
            console.error('Erro no teste:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            $list.html('<div class="assignee-empty">Erro no teste</div>');
            alert('❌ Erro no teste: ' + error);
        });
    });
    
    // Teste específico para membros
    $('#test-connection-btn').on('click', function() {
        console.log('Testando conexão e membros...');
        
        // Primeiro testa a conexão
        $.post(f2f_ajax.ajaxurl, {
            action: 'f2f_test_clickup_connection'
        }, function(response) {
            if (response.success) {
                // Se conexão OK, testa workspaces e membros
                $.post(f2f_ajax.ajaxurl, {
                    action: 'f2f_list_workspaces'
                }, function(workspacesResponse) {
                    var result = '✅ CONEXÃO OK!\n\n';
                    result += 'Usuário: ' + response.data.user.username + '\n';
                    result += 'Email: ' + response.data.user.email + '\n\n';
                    
                    if (workspacesResponse.success) {
                        result += '🏢 WORKSPACES ENCONTRADOS: ' + workspacesResponse.data.workspaces.length + '\n';
                        workspacesResponse.data.workspaces.forEach(function(workspace, index) {
                            result += (index + 1) + '. ' + workspace.name + ' (ID: ' + workspace.id + ')\n';
                        });
                        result += '\n';
                    } else {
                        result += '❌ ERRO AO CARREGAR WORKSPACES:\n' + workspacesResponse.data + '\n\n';
                    }
                    
                    // Agora testa membros
                    $.post(f2f_ajax.ajaxurl, {
                        action: 'f2f_get_workspace_members'
                    }, function(membersResponse) {
                        console.log('Resposta dos membros:', membersResponse);
                        
                        if (membersResponse.success) {
                            result += '👥 MEMBROS ENCONTRADOS: ' + membersResponse.data.members.length + '\n\n';
                            membersResponse.data.members.forEach(function(member, index) {
                                if (member.user) {
                                    result += (index + 1) + '. ' + (member.user.username || member.user.email) + '\n';
                                }
                            });
                        } else {
                            result += '❌ ERRO AO CARREGAR MEMBROS:\n' + membersResponse.data;
                        }
                        
                        alert(result);
                    }).fail(function(xhr, status, error) {
                        result += '❌ Erro ao carregar membros: ' + error;
                        alert(result);
                    });
                }).fail(function(xhr, status, error) {
                    var result = '✅ CONEXÃO OK!\n\n';
                    result += 'Usuário: ' + response.data.user.username + '\n';
                    result += 'Email: ' + response.data.user.email + '\n\n';
                    result += '❌ Erro ao carregar workspaces: ' + error;
                    alert(result);
                });
            } else {
                alert('❌ Erro na conexão: ' + response.data);
            }
        }).fail(function(xhr, status, error) {
            alert('❌ Erro na requisição: ' + error);
        });
    });
    
    
    $('#clickup-task-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Formulário submetido');
        
        var $form = $(this);
        var $btn = $('#submit-task-btn');
        var $btnText = $btn.find('.btn-text');
        var $btnLoading = $btn.find('.btn-loading');
        var $result = $('#task-result');
        
        // Mostra loading
        $btn.prop('disabled', true);
        $btnText.addClass('d-none');
        $btnLoading.removeClass('d-none');
        $result.html('');
        
        console.log('Dados do formulário:', $form.serialize());
        
        // Prepara dados
        var formData = {
            action: 'f2f_frontend_create_clickup_task',
            nonce: $form.find('[name="f2f_nonce"]').val(),
            task_name: $('#task_name').val(),
            task_description: $('#task_description').val(),
            task_list: $('#task_list').val(),
            task_priority: $('#task_priority').val(),
            task_start_date: $('#task_start_date').val(),
            task_due_date: $('#task_due_date').val(),
            task_tags: $('#task_tags').val(),
            task_assignees: $('#task_assignees').val()
        };
        
        // Envia requisição
        console.log('Enviando dados para:', f2f_ajax.ajaxurl);
        console.log('Dados enviados:', formData);
        
        $.post(f2f_ajax.ajaxurl, formData, function(response) {
            console.log('Resposta da criação de tarefa:', response);
            
            // Restaura botão
            $btn.prop('disabled', false);
            $btnText.removeClass('d-none');
            $btnLoading.addClass('d-none');
            
            if (response.success) {
                // Sucesso
                $result.html(
                    '<div class="alert alert-success task-success-alert d-flex align-items-start" role="alert">' +
                        '<i class="fas fa-check-circle fa-2x text-success me-3 mt-1"></i>' +
                        '<div class="flex-grow-1">' +
                            '<h4 class="alert-heading mb-2">' +
                                '<i class="fas fa-rocket me-2"></i>Tarefa criada com sucesso!' +
                            '</h4>' +
                            '<p class="mb-2">Sua tarefa foi criada no ClickUp e já está disponível para a equipe.</p>' +
                            '<hr>' +
                            '<div class="mb-2">' +
                                '<strong>ID da Tarefa:</strong> ' +
                                '<code class="bg-light px-2 py-1 rounded">' + response.data.task.id + '</code>' +
                            '</div>' +
                            '<div class="mb-3">' +
                                '<strong>Nome:</strong> ' + response.data.task.name +
                            '</div>' +
                            (response.data.task.assignees && response.data.task.assignees.length > 0 ? 
                                '<div class="mb-3">' +
                                    '<strong>Atribuído para:</strong> ' + 
                                    response.data.task.assignees.map(function(assignee) {
                                        return assignee.username || assignee.email || 'ID: ' + assignee.id;
                                    }).join(', ') +
                                '</div>' : '') +
                            '<a href="' + response.data.task.url + '" target="_blank" class="btn btn-sm btn-outline-success">' +
                                '<i class="fas fa-external-link-alt me-1"></i> Abrir no ClickUp' +
                            '</a>' +
                            '<button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="location.reload()">' +
                                '<i class="fas fa-plus me-1"></i> Criar Outra Tarefa' +
                            '</button>' +
                        '</div>' +
                    '</div>'
                );
                
                // Limpa o formulário
                $form[0].reset();
                
                // Scroll até o resultado
                $('html, body').animate({
                    scrollTop: $result.offset().top - 100
                }, 500);
                
            } else {
                // Erro
                $result.html(
                    '<div class="alert alert-danger d-flex align-items-start" role="alert">' +
                        '<i class="fas fa-exclamation-triangle fa-2x text-danger me-3 mt-1"></i>' +
                        '<div>' +
                            '<h5 class="alert-heading">Erro ao criar tarefa</h5>' +
                            '<p class="mb-0">' + response.data + '</p>' +
                        '</div>' +
                    '</div>'
                );
            }
        }).fail(function(xhr, status, error) {
            console.error('Erro na requisição AJAX:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            
            // Erro de requisição
            $btn.prop('disabled', false);
            $btnText.removeClass('d-none');
            $btnLoading.addClass('d-none');
            
            $result.html(
                '<div class="alert alert-danger d-flex align-items-start" role="alert">' +
                    '<i class="fas fa-times-circle fa-2x text-danger me-3 mt-1"></i>' +
                    '<div>' +
                        '<h5 class="alert-heading">Erro de conexão</h5>' +
                        '<p class="mb-0">Erro: ' + error + '<br>Status: ' + status + '<br>Verifique o console para mais detalhes.</p>' +
                    '</div>' +
                '</div>'
            );
        });
    });
    
    // Auto-preencher data de início com data atual
    var now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    var currentDateTime = now.toISOString().slice(0, 16);
    
    // Botão para preencher data de início
    var startDateLabel = $('label[for="task_start_date"]');
    startDateLabel.append(' <button type="button" class="btn btn-sm btn-link p-0 ms-2" id="set-now-btn" title="Usar data atual"><i class="fas fa-clock"></i> Agora</button>');
    
    $(document).on('click', '#set-now-btn', function(e) {
        e.preventDefault();
        $('#task_start_date').val(currentDateTime);
    });
    
    // Botão para recarregar membros
    $(document).on('click', '#reload-members-btn', function(e) {
        e.preventDefault();
        loadWorkspaceMembers();
    });
    
    // Inicializar dropdown customizado
    try {
        initAssigneeDropdown();
        console.log('Dropdown inicializado com sucesso');
    } catch (error) {
        console.error('Erro ao inicializar dropdown:', error);
    }
    
    // Carregar membros do workspace
    try {
        loadWorkspaceMembers();
        console.log('Carregamento de membros iniciado');
    } catch (error) {
        console.error('Erro ao carregar membros:', error);
    }
});


// Variáveis globais para o dropdown
var assigneeMembers = [];
var selectedAssignees = [];

// Inicializar dropdown customizado
function initAssigneeDropdown() {
    console.log('Inicializando dropdown...');
    
    try {
        // Toggle do dropdown
        $('#assignee-toggle').on('click', function() {
            console.log('Toggle clicado');
            toggleDropdown();
        });
        
        // Fechar dropdown ao clicar fora
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.assignee-dropdown-container').length) {
                closeDropdown();
            }
        });
        
        // Busca de membros
        $('#assignee-search').on('input', function() {
            console.log('Busca digitada:', $(this).val());
            filterMembers($(this).val());
        });
        
        // Botão limpar
        $('#clear-assignees-btn').on('click', function() {
            console.log('Botão limpar clicado');
            clearSelectedAssignees();
        });
        
        console.log('Dropdown inicializado com sucesso');
    } catch (error) {
        console.error('Erro na inicialização do dropdown:', error);
    }
}

// Toggle do dropdown
function toggleDropdown() {
    console.log('toggleDropdown chamada');
    var $toggle = $('#assignee-toggle');
    var $menu = $('#assignee-menu');
    
    console.log('Toggle encontrado:', $toggle.length);
    console.log('Menu encontrado:', $menu.length);
    
    if ($menu.hasClass('show')) {
        console.log('Fechando dropdown');
        closeDropdown();
    } else {
        console.log('Abrindo dropdown');
        openDropdown();
    }
}

// Abrir dropdown
function openDropdown() {
    console.log('openDropdown chamada');
    $('#assignee-toggle').addClass('active');
    $('#assignee-menu').addClass('show');
    $('#assignee-search').focus();
    console.log('Dropdown aberto');
}

// Fechar dropdown
function closeDropdown() {
    console.log('closeDropdown chamada');
    $('#assignee-toggle').removeClass('active');
    $('#assignee-menu').removeClass('show');
    $('#assignee-search').val('');
    filterMembers('');
    console.log('Dropdown fechado');
}

// Filtrar membros
function filterMembers(searchTerm) {
    console.log('filterMembers chamada com termo:', searchTerm);
    var $list = $('#assignee-list');
    var filteredMembers = assigneeMembers;
    
    if (searchTerm) {
        filteredMembers = assigneeMembers.filter(function(member) {
            var name = member.user.username || member.user.email || '';
            var email = member.user.email || '';
            return name.toLowerCase().includes(searchTerm.toLowerCase()) || 
                   email.toLowerCase().includes(searchTerm.toLowerCase());
        });
    }
    
    renderMembersList(filteredMembers);
}

// Renderizar lista de membros
function renderMembersList(members) {
    console.log('renderMembersList chamada com', members.length, 'membros');
    var $list = $('#assignee-list');
    
    if (members.length === 0) {
        console.log('Nenhum membro encontrado');
        $list.html('<div class="assignee-empty">Nenhum membro encontrado</div>');
        return;
    }
    
    var html = '';
    members.forEach(function(member) {
        var isSelected = selectedAssignees.some(function(selected) {
            return selected.user.id === member.user.id;
        });
        
        var displayName = member.user.username || member.user.email || 'Usuário ' + member.user.id;
        var email = member.user.email || '';
        var initials = getInitials(displayName);
        
        html += '<div class="assignee-item' + (isSelected ? ' selected' : '') + '" data-member-id="' + member.user.id + '">';
        html += '<input type="checkbox" ' + (isSelected ? 'checked' : '') + '>';
        html += '<div class="assignee-avatar">' + initials + '</div>';
        html += '<div class="assignee-info">';
        html += '<div class="assignee-name">' + displayName + '</div>';
        if (email) {
            html += '<div class="assignee-email">' + email + '</div>';
        }
        html += '</div>';
        html += '</div>';
    });
    
    $list.html(html);
    
    // Event listeners para os itens
    $list.find('.assignee-item').on('click', function(e) {
        if (e.target.type !== 'checkbox') {
            $(this).find('input[type="checkbox"]').click();
        }
    });
    
    $list.find('input[type="checkbox"]').on('change', function() {
        var $item = $(this).closest('.assignee-item');
        var memberId = $item.data('member-id');
        var member = assigneeMembers.find(function(m) {
            return m.user.id === memberId;
        });
        
        if ($(this).is(':checked')) {
            if (!selectedAssignees.some(function(s) { return s.user.id === memberId; })) {
                selectedAssignees.push(member);
            }
            $item.addClass('selected');
        } else {
            selectedAssignees = selectedAssignees.filter(function(s) {
                return s.user.id !== memberId;
            });
            $item.removeClass('selected');
        }
        
        updateSelectedDisplay();
        updateHiddenInput();
    });
}

// Obter iniciais do nome
function getInitials(name) {
    return name.split(' ').map(function(word) {
        return word.charAt(0).toUpperCase();
    }).join('').substring(0, 2);
}

// Atualizar display dos selecionados
function updateSelectedDisplay() {
    var $container = $('#assignee-selected');
    
    if (selectedAssignees.length === 0) {
        $container.empty();
        $('.assignee-placeholder').text('Selecionar membro(s)...');
        return;
    }
    
    $('.assignee-placeholder').text(selectedAssignees.length + ' membro(s) selecionado(s)');
    
    var html = '';
    selectedAssignees.forEach(function(member) {
        var displayName = member.user.username || member.user.email || 'Usuário ' + member.user.id;
        html += '<div class="assignee-tag">';
        html += '<span>' + displayName + '</span>';
        html += '<span class="remove" data-member-id="' + member.user.id + '">&times;</span>';
        html += '</div>';
    });
    
    $container.html(html);
    
    // Event listener para remover tags
    $container.find('.remove').on('click', function() {
        var memberId = $(this).data('member-id');
        selectedAssignees = selectedAssignees.filter(function(s) {
            return s.user.id !== memberId;
        });
        
        // Desmarcar checkbox
        $('#assignee-list input[data-member-id="' + memberId + '"]').prop('checked', false);
        $('#assignee-list .assignee-item[data-member-id="' + memberId + '"]').removeClass('selected');
        
        updateSelectedDisplay();
        updateHiddenInput();
    });
}

// Atualizar input hidden
function updateHiddenInput() {
    var ids = selectedAssignees.map(function(member) {
        return member.user.id;
    });
    $('#task_assignees').val(ids.join(','));
}

// Limpar selecionados
function clearSelectedAssignees() {
    selectedAssignees = [];
    $('#assignee-list input[type="checkbox"]').prop('checked', false);
    $('#assignee-list .assignee-item').removeClass('selected');
    updateSelectedDisplay();
    updateHiddenInput();
    closeDropdown();
}

// Função para carregar membros (atualizada para o dropdown)
function loadWorkspaceMembers() {
    console.log('loadWorkspaceMembers chamada');
    var $list = $('#assignee-list');
    console.log('Lista encontrada:', $list.length);
    $list.html('<div class="assignee-loading"><i class="fas fa-spinner fa-spin me-2"></i>Carregando membros...</div>');
    
    // Adiciona timeout de 10 segundos
    var request = $.ajax({
        url: f2f_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'f2f_get_workspace_members'
        },
        timeout: 10000 // 10 segundos
    });
    
    request.done(function(response) {
        console.log('Resposta AJAX recebida:', response);
        
        if (response.success && response.data.members.length > 0) {
            console.log('Membros encontrados:', response.data.members.length);
            assigneeMembers = response.data.members;
            renderMembersList(assigneeMembers);
            console.log('Membros carregados com sucesso: ' + response.data.members.length);
        } else {
            console.log('Nenhum membro encontrado ou erro na resposta');
            $list.html('<div class="assignee-empty">Nenhum membro encontrado</div>');
            console.warn('Nenhum membro encontrado no workspace');
        }
    });
    
    request.fail(function(xhr, status, error) {
        console.error('Erro na requisição AJAX:', error);
        console.error('Status:', status);
        console.error('Response:', xhr.responseText);
        console.error('XHR object:', xhr);
        
        if (status === 'timeout') {
            console.log('Timeout na requisição');
            $list.html('<div class="assignee-empty">Timeout - clique em Recarregar</div>');
        } else {
            console.log('Erro na requisição');
            $list.html('<div class="assignee-empty">Erro ao carregar membros</div>');
        }
    });
}
</script>

<?php get_footer(); ?>


