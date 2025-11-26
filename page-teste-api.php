<?php
/**
 * Template Name: Teste API TaskRow
 */

get_header();
?>

<div class="container mt-5">
    <h1>🔍 Teste API TaskRow</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>Credenciais TaskRow</h5>
            <p>Cole aqui o token e o host do TaskRow para testes (somente administradores).</p>
            <div class="mb-2">
                <label for="taskrow_token">API Token</label>
                <input type="text" id="taskrow_token" class="form-control" placeholder="Cole o token aqui" value="<?php echo esc_attr( get_option('f2f_taskrow_api_token', ( defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '' ) ) ); ?>">
            </div>
            <div class="mb-2">
                <label for="taskrow_host">Host (ex: f2f.taskrow.com)</label>
                <input type="text" id="taskrow_host" class="form-control" placeholder="f2f.taskrow.com" value="<?php echo esc_attr( get_option('f2f_taskrow_host_name', ( defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '' ) ) ); ?>">
            </div>
            <button class="btn btn-secondary" id="btnSalvarCredenciais">Salvar Credenciais</button>
            <div id="credStatus" class="mt-2"></div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>1. Listar TODOS os Projetos</h5>
            <button class="btn btn-primary" id="btnListarProjetos">Buscar Projetos</button>
            <div class="mt-2">
                <button class="btn btn-secondary" id="btnAtualizarTabela">Atualizar Estrutura da Tabela</button>
                <button class="btn btn-danger" id="btnClearDemands">Apagar todas as demandas (Clear DB)</button>
                <button class="btn btn-warning" id="btnRunImport">Executar Importação (Import)</button>
            </div>
            <div id="resultProjetos" class="mt-3"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5>2. Listar Usuários do TaskRow</h5>
            <p>Lista completa de usuários com <code>UserID</code>, nome completo, email e login.</p>
            <button class="btn btn-info" id="btnListarUsuarios">Listar Usuários</button>
            <div id="resultUsuarios" class="mt-3"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5>3. Listar Clientes do TaskRow</h5>
            <button class="btn btn-secondary" id="btnListarClientes">Listar Clientes</button>
            <div id="resultClientes" class="mt-3"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5>4. Buscar Tasks de um Projeto Específico</h5>
            <input type="text" class="form-control mb-2" id="projetoId" placeholder="ID do Projeto (ex: 149066)">
            <button class="btn btn-success" id="btnBuscarTasks">Buscar Tasks</button>
            <div id="resultTasks" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Dados de contexto para auto-preenchimento / auto-salvar
    const f2fCreds = {
        existingToken: '<?php echo esc_js(get_option('f2f_taskrow_api_token','')); ?>',
        existingHost: '<?php echo esc_js(get_option('f2f_taskrow_host_name','')); ?>',
        fallbackToken: '<?php echo esc_js(defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : ''); ?>',
        fallbackHost: '<?php echo esc_js(defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : ''); ?>',
        nonceSave: '<?php echo wp_create_nonce('f2f_save_taskrow_nonce'); ?>'
    };

    // Preencher inputs se vazios usando fallback
    if (!$('#taskrow_token').val() && f2fCreds.fallbackToken) {
        $('#taskrow_token').val(f2fCreds.fallbackToken);
    }
    if (!$('#taskrow_host').val() && f2fCreds.fallbackHost) {
        $('#taskrow_host').val(f2fCreds.fallbackHost);
    }

    // Auto-salvar se opções ainda não existem mas temos fallback
    if ((!f2fCreds.existingToken || !f2fCreds.existingHost) && f2fCreds.fallbackToken && f2fCreds.fallbackHost) {
        $('#credStatus').html('<div class="spinner-border spinner-border-sm"></div> Salvando credenciais padrão...');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'f2f_save_taskrow_credentials',
                token: f2fCreds.fallbackToken,
                host: f2fCreds.fallbackHost,
                nonce: f2fCreds.nonceSave
            },
            success: function(r){
                if (r.success) {
                    $('#credStatus').html('<div class="alert alert-success">Credenciais padrão aplicadas automaticamente.</div>');
                } else {
                    $('#credStatus').html('<div class="alert alert-warning">Falha ao auto-salvar: '+ (r.data || 'Erro desconhecido') +'</div>');
                }
            },
            error: function(xhr){
                $('#credStatus').html('<div class="alert alert-danger">Erro auto-salvar: '+ xhr.status +' '+ xhr.responseText +'</div>');
            }
        });
    }
    // Handler para salvar credenciais via AJAX
    $('#btnSalvarCredenciais').on('click', function() {
        const token = $('#taskrow_token').val();
        const host = $('#taskrow_host').val();

        if (!token || !host) {
            $('#credStatus').html('<div class="alert alert-warning">Preencha token e host antes de salvar.</div>');
            return;
        }

        $('#credStatus').html('<div class="spinner-border spinner-border-sm"></div> Salvando...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'f2f_save_taskrow_credentials',
                token: token,
                host: host,
                nonce: '<?php echo wp_create_nonce('f2f_save_taskrow_nonce'); ?>'
            },
            success: function(response) {
                if (!response.success) {
                    $('#credStatus').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }

                $('#credStatus').html('<div class="alert alert-success">Credenciais salvas com sucesso.</div>');
            },
            error: function(xhr) {
                $('#credStatus').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });
    
    // Atualizar estrutura da tabela
    $('#btnAtualizarTabela').on('click', function() {
        $('#resultProjetos').html('<div class="spinner-border"></div> Atualizando estrutura da tabela...');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: { action: 'f2f_update_table_structure' },
            success: function(response) {
                if (!response.success) {
                    $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                $('#resultProjetos').html('<div class="alert alert-success">' + response.data.message + '</div>');
            },
            error: function(xhr) {
                $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });
    
    // Listar projetos
    $('#btnListarProjetos').on('click', function() {
        $('#resultProjetos').html('<div class="spinner-border"></div> Carregando...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'f2f_test_list_projects'
            },
            success: function(response) {
                if (!response.success) {
                    $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                
                const data = response.data;
                console.log('Projetos:', data);
                
                let html = '<h6>Total: ' + (data.items ? data.items.length : 0) + ' projetos</h6>';
                html += '<pre style="max-height: 500px; overflow: auto; background: #f5f5f5; padding: 15px; border-radius: 5px;">';
                html += JSON.stringify(data, null, 2);
                html += '</pre>';
                
                // Mostrar apenas os primeiros 10 em tabela
                if (data.items && data.items.length > 0) {
                    html += '<h6 class="mt-3">Primeiros 10 projetos:</h6>';
                    html += '<table class="table table-sm table-striped">';
                    html += '<thead><tr><th>ID</th><th>Nome</th><th>Cliente DisplayName</th><th>Cliente Nickname</th></tr></thead><tbody>';
                    
                    data.items.slice(0, 10).forEach(function(project) {
                        html += '<tr>';
                        html += '<td>' + project.jobID + '</td>';
                        html += '<td>' + (project.jobTitle || project.name || 'N/A') + '</td>';
                        html += '<td>' + (project.client?.displayName || 'N/A') + '</td>';
                        html += '<td>' + (project.client?.clientNickname || 'N/A') + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                }
                
                $('#resultProjetos').html(html);
            },
            error: function(xhr) {
                $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Listar usuários
    $('#btnListarUsuarios').on('click', function() {
        $('#resultUsuarios').html('<div class="spinner-border"></div> Carregando usuários...');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: { action: 'f2f_test_list_users' },
            success: function(response) {
                if (!response.success) {
                    $('#resultUsuarios').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                const data = response.data;
                console.log('Usuários:', data);
                
                let users = Array.isArray(data) ? data : (data.items || []);
                let html = '<div class="alert alert-success"><strong>✓ ' + users.length + ' usuários encontrados</strong></div>';
                
                if (users.length > 0) {
                    // Filtro por nome
                    html += '<input type="text" class="form-control mb-2" id="filterUsers" placeholder="Filtrar por nome...">';
                    html += '<table class="table table-sm table-striped" id="usersTable">';
                    html += '<thead><tr><th>UserID</th><th>Nome Completo</th><th>Email</th><th>Login</th><th>Função</th><th>Ativo?</th></tr></thead><tbody>';
                    users.forEach(function(u) {
                        const inactive = u.Inactive === true || u.inactive === true;
                        const rowClass = inactive ? 'table-secondary' : '';
                        html += '<tr class="'+rowClass+'" data-name="'+(u.FullName || u.fullName || '').toLowerCase()+'">'+
                            '<td><strong>' + (u.UserID || u.userID || 'N/A') + '</strong></td>'+
                            '<td>' + (u.FullName || u.fullName || 'N/A') + '</td>'+
                            '<td>' + (u.MainEmail || u.mainEmail || 'N/A') + '</td>'+
                            '<td>' + (u.UserLogin || u.userLogin || 'N/A') + '</td>'+
                            '<td>' + (u.UserFunctionTitle || u.userFunctionTitle || 'N/A') + '</td>'+
                            '<td>' + (inactive ? '<span class="badge badge-secondary">Inativo</span>' : '<span class="badge badge-success">Ativo</span>') + '</td>'+
                        '</tr>';
                    });
                    html += '</tbody></table>';
                    
                    // Script para filtro
                    setTimeout(function() {
                        $('#filterUsers').on('keyup', function() {
                            const filter = $(this).val().toLowerCase();
                            $('#usersTable tbody tr').each(function() {
                                const name = $(this).data('name');
                                $(this).toggle(name.indexOf(filter) > -1);
                            });
                        });
                    }, 100);
                } else {
                    html += '<div class="alert alert-warning">Nenhum usuário encontrado.</div>';
                }
                
                $('#resultUsuarios').html(html);
            },
            error: function(xhr) {
                $('#resultUsuarios').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Listar clientes
    $('#btnListarClientes').on('click', function() {
        $('#resultClientes').html('<div class="spinner-border"></div> Carregando clientes...');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: { action: 'f2f_test_list_clients' },
            success: function(response) {
                if (!response.success) {
                    $('#resultClientes').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                const data = response.data;
                console.log('Clientes:', data);
                let items = data.items || data;
                let html = '<div class="alert alert-success"><strong>✓ ' + (Array.isArray(items) ? items.length : 0) + ' clientes encontrados</strong></div>';
                if (Array.isArray(items) && items.length) {
                    html += '<table class="table table-sm table-striped"><thead><tr><th>ClientID</th><th>Nome</th><th>Nickname</th><th>Ativo?</th></tr></thead><tbody>';
                    items.forEach(function(c){
                        html += '<tr>'+
                            '<td><strong>' + (c.clientID || c.id || 'N/A') + '</strong></td>'+
                            '<td>' + (c.displayName || c.name || 'N/A') + '</td>'+
                            '<td>' + (c.clientNickname || c.clientNickName || 'N/A') + '</td>'+
                            '<td>' + (c.active === true || c.isActive === true ? '<span class="badge badge-success">Sim</span>' : '<span class="badge badge-secondary">Não</span>') + '</td>'+
                        '</tr>';
                    });
                    html += '</tbody></table>';
                }
                $('#resultClientes').html(html);
            },
            error: function(xhr){
                $('#resultClientes').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Buscar tasks
    $('#btnBuscarTasks').on('click', function() {
        const projetoId = $('#projetoId').val();
        
        if (!projetoId) {
            alert('Digite o ID do projeto!');
            return;
        }
        
        $('#resultTasks').html('<div class="spinner-border"></div> Carregando...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'f2f_test_search_tasks',
                project_id: projetoId
            },
            success: function(response) {
                if (!response.success) {
                    $('#resultTasks').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                
                const data = response.data;
                console.log('Tasks:', data);
                
                const tasks = data.data || data;
                let html = '<h6>Total retornado: ' + (Array.isArray(tasks) ? tasks.length : 0) + ' tasks</h6>';
                html += '<pre style="max-height: 500px; overflow: auto; background: #f5f5f5; padding: 15px; border-radius: 5px;">';
                html += JSON.stringify(data, null, 2);
                html += '</pre>';
                
                $('#resultTasks').html(html);
            },
            error: function(xhr) {
                $('#resultTasks').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Apagar todas as demandas (action já existente)
    $('#btnClearDemands').on('click', function() {
        if (!confirm('Confirma apagar TODAS as demandas da tabela local? Essa ação é irreversível.')) return;
        $('#resultProjetos').html('<div class="spinner-border"></div> Apagando...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'f2f_clear_all_taskrow_demands'
            },
            success: function(response) {
                if (!response.success) {
                    $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                $('#resultProjetos').html('<div class="alert alert-success">' + response.data.message + '</div>');
            },
            error: function(xhr) {
                $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Executar importação (action já existente)
    $('#btnRunImport').on('click', function() {
        if (!confirm('Executar importação das tasks agora? Isso buscará tasks para os clientes permitidos.')) return;
        $('#resultProjetos').html('<div class="spinner-border"></div> Importando...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'f2f_import_taskrow_demands'
            },
            success: function(response) {
                if (!response.success) {
                    $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                $('#resultProjetos').html('<div class="alert alert-success">' + response.data.message + '</div>');
            },
            error: function(xhr) {
                $('#resultProjetos').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Buscar task específica por TaskID
    $('#btnGetTaskById').on('click', function() {
        const taskId = $('#specificTaskId').val().trim();
        if (!taskId) { alert('Informe o TaskID.'); return; }
        $('#resultSpecificTask').html('<div class="spinner-border"></div> Buscando task #'+taskId+' em múltiplos endpoints...');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: { action: 'f2f_get_task_by_id', task_id: taskId },
            success: function(response) {
                if (!response.success) {
                    $('#resultSpecificTask').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                const data = response.data;
                console.log('Task #'+taskId+' tentativas:', data);
                
                let html = '<h6>Resultados para Task #'+data.task_id+':</h6>';
                
                // Mostrar cada tentativa de endpoint
                data.attempts.forEach(function(attempt, idx) {
                    html += '<div class="card mb-3 '+(attempt.success ? 'border-success' : 'border-warning')+'">';
                    html += '<div class="card-header '+(attempt.success ? 'bg-success text-white' : 'bg-warning')+'">';
                    html += '<strong>' + attempt.endpoint + '</strong> - HTTP ' + attempt.http_code;
                    html += '</div><div class="card-body">';
                    
                    if (attempt.success) {
                        // Mostrar campos de usuário encontrados
                        if (attempt.user_fields_found && Object.keys(attempt.user_fields_found).length > 0) {
                            html += '<div class="alert alert-info"><strong>✓ Campos de Usuário Encontrados:</strong><br>';
                            for (const [field, value] of Object.entries(attempt.user_fields_found)) {
                                html += '<code>' + field + '</code>: ' + JSON.stringify(value) + '<br>';
                            }
                            html += '</div>';
                        } else {
                            html += '<div class="alert alert-warning">⚠️ Nenhum campo de usuário responsável encontrado</div>';
                        }
                        
                        // JSON completo colapsável
                        html += '<details><summary>Ver JSON completo</summary>';
                        html += '<pre style="max-height:400px; overflow:auto; background:#f5f5f5; padding:10px; margin-top:10px;">'+JSON.stringify(attempt.data, null, 2)+'</pre>';
                        html += '</details>';
                    } else {
                        html += '<div class="alert alert-danger">Erro: ' + (attempt.error || 'Requisição falhou') + '</div>';
                    }
                    
                    html += '</div></div>';
                });
                
                $('#resultSpecificTask').html(html);
            },
            error: function(xhr) {
                $('#resultSpecificTask').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Buscar tasks por ownerUserID
    $('#btnGetTasksByOwner').on('click', function() {
        const ownerId = $('#ownerUserId').val().trim();
        if (!ownerId) { alert('Informe o UserID do responsável.'); return; }
        $('#resultTasksByOwner').html('<div class="spinner-border"></div> Buscando tasks do UserID '+ownerId+'...');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: { action: 'f2f_get_tasks_by_owner', owner_user_id: ownerId },
            success: function(response) {
                if (!response.success) {
                    $('#resultTasksByOwner').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                const data = response.data;
                console.log('Tasks do UserID '+ownerId+':', data);
                
                let html = '<div class="alert alert-success"><strong>✓ Encontradas ' + data.total_found + ' tasks</strong> onde UserID ' + data.owner_user_id + ' é responsável (owner)</div>';
                
                if (data.tasks && data.tasks.length > 0) {
                    html += '<table class="table table-sm table-striped">';
                    html += '<thead><tr><th>TaskID</th><th>Título</th><th>Job#</th><th>Status</th><th>Cliente</th><th>Owner</th></tr></thead><tbody>';
                    data.tasks.forEach(function(t) {
                        html += '<tr>'+
                          '<td><strong>' + t.taskID + '</strong></td>'+
                          '<td>' + t.taskTitle + '</td>'+
                          '<td>' + t.jobNumber + '</td>'+
                          '<td><span class="badge badge-info">' + t.pipelineStep + '</span></td>'+
                          '<td>' + t.clientNickName + '</td>'+
                          '<td>' + t.ownerUserLogin + ' (#' + t.ownerUserID + ')</td>'+
                        '</tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<div class="alert alert-warning mt-2">Nenhuma task encontrada.</div>';
                }
                
                $('#resultTasksByOwner').html(html);
            },
            error: function(xhr) {
                $('#resultTasksByOwner').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });

    // Itens pendentes do usuário
    $('#btnUserPending').on('click', function() {
        const uid = $('#userPendingId').val().trim();
        if (!uid) { alert('Informe o UserID.'); return; }
        $('#resultUserPending').html('<div class="spinner-border"></div> Carregando itens do usuário...');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: { action: 'f2f_get_user_pending_entities', user_id: uid },
            success: function(response) {
                if (!response.success) {
                    $('#resultUserPending').html('<div class="alert alert-danger">Erro: ' + response.data + '</div>');
                    return;
                }
                const data = response.data;
                const entity = data.Entity || data.entity || data;
                let html = '<h6>Resultado</h6>';
                html += '<pre style="max-height:300px; overflow:auto; background:#f5f5f5; padding:10px; border-radius:5px;">'+JSON.stringify(data, null, 2)+'</pre>';
                if (entity.Tasks && entity.Tasks.length) {
                    html += '<h6 class="mt-3">Tasks ('+entity.Tasks.length+'):</h6>';
                    html += '<table class="table table-sm table-striped"><thead><tr><th>ID</th><th>Título</th><th>Status</th><th>Job#</th></tr></thead><tbody>';
                    entity.Tasks.slice(0,100).forEach(function(t){
                        html += '<tr>'+
                          '<td>'+ (t.taskID || t.TaskID || 'N/A') +'</td>'+
                          '<td>'+ (t.taskTitle || t.title || 'N/A') +'</td>'+
                          '<td>'+ (t.pipelineStep || t.status || 'N/A') +'</td>'+
                          '<td>'+ (t.jobNumber || 'N/A') +'</td>'+
                        '</tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<div class="alert alert-info mt-2">Nenhuma task pendente encontrada para este usuário.</div>';
                }
                $('#resultUserPending').html(html);
            },
            error: function(xhr) {
                $('#resultUserPending').html('<div class="alert alert-danger">Erro: ' + xhr.responseText + '</div>');
            }
        });
    });
});
</script>

<?php
get_footer();
