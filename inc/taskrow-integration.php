<?php
/**
 * Taskrow Integration
 * 
 * Todas as funções e hooks relacionados à integração com Taskrow
 * 
 * @package F2F_Dashboard
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Incluir a classe da API Taskrow
require_once get_template_directory() . '/inc/class-taskrow-api.php';

// Fallback temporário (usado apenas para testes rápidos quando as opções WP não estiverem definidas)
if (!defined('F2F_TASKROW_FALLBACK_TOKEN')) {
    define('F2F_TASKROW_FALLBACK_TOKEN', '1e3Y6irpQlLdK6hnk9lbNy6Pz7o3i-TT0td9lBTbmJkuN8pdCsEisH1Fkt3E_B3vHGdeDReHcq6vNUlVTcBtsR4R9KR9dIrj6bpl-3VG37k1');
}
if (!defined('F2F_TASKROW_FALLBACK_HOST')) {
    define('F2F_TASKROW_FALLBACK_HOST', 'f2f.taskrow.com');
}

/**
 * Cria tabela para armazenar demandas do Taskrow
 */
function f2f_create_taskrow_demands_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        taskrow_id varchar(100) NOT NULL,
        job_number int(11) DEFAULT 0,
        task_number int(11) DEFAULT 0,
        client_nickname varchar(255) DEFAULT NULL,
        clickup_id varchar(100) DEFAULT NULL,
        owner_user_id varchar(20) DEFAULT NULL,
        owner_user_login varchar(255) DEFAULT NULL,
        title text NOT NULL,
        description longtext DEFAULT NULL,
        client_name varchar(255) DEFAULT NULL,
        status varchar(50) DEFAULT NULL,
        priority varchar(50) DEFAULT NULL,
        due_date datetime DEFAULT NULL,
        attachments longtext DEFAULT NULL,
        hours_tracked decimal(10,2) DEFAULT 0,
        hours_synced tinyint(1) DEFAULT 0,
        last_sync datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY taskrow_id (taskrow_id),
        KEY clickup_id (clickup_id),
        KEY status (status)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Adicionar colunas owner_user_id e owner_user_login se não existirem
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'owner_user_id'");
    if (empty($column_check)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN owner_user_id varchar(20) DEFAULT NULL AFTER clickup_id");
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN owner_user_login varchar(255) DEFAULT NULL AFTER owner_user_id");
        error_log('F2F: Colunas owner_user_id e owner_user_login adicionadas à tabela');
    }
}
add_action('after_setup_theme', 'f2f_create_taskrow_demands_table');

/**
 * AJAX: Importar demandas do Taskrow (TODAS as tasks)
 */
function f2f_ajax_import_taskrow_demands()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }

    error_log('F2F: Iniciando importação de TODAS as tasks do TaskRow');

    $host_name = get_option('f2f_taskrow_host_name', '');
    $api_token = get_option('f2f_taskrow_api_token', '');

    if (empty($host_name) || empty($api_token)) {
        wp_send_json_error('API Taskrow não configurada');
    }

    // Primeiro, buscar TODOS os projetos disponíveis
    error_log('F2F: Buscando lista de todos os projetos');
    $projects_url = 'https://' . $host_name . '/api/v2/core/job/list';

    $projects_response = wp_remote_request($projects_url, array(
        'method' => 'GET',
        'headers' => array(
            '__identifier' => $api_token,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 60,
    ));

    if (is_wp_error($projects_response)) {
        error_log('F2F: Erro ao buscar projetos: ' . $projects_response->get_error_message());
        wp_send_json_error('Erro ao buscar projetos: ' . $projects_response->get_error_message());
    }

    $projects_data = json_decode(wp_remote_retrieve_body($projects_response), true);
    $projects = $projects_data['items'] ?? array();

    error_log('F2F: Total de projetos encontrados: ' . count($projects));

    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';

    $total_imported = 0;
    $total_updated = 0;

    // Agora buscar tasks de TODOS os projetos e IMPORTAR DIRETO
    $tasks_url = 'https://' . $host_name . '/api/v2/search/tasks/advancedsearch';

    // Para cada projeto, buscar suas tasks com paginação até esgotar
    foreach ($projects as $index => $project) {
        $project_id = $project['jobID'] ?? null;
        $project_title = $project['jobTitle'] ?? 'Sem título';

        // Capturar o clientNickname do PROJETO para aplicar às tasks
        $project_client_nickname = trim($project['client']['clientNickname'] ?? $project['client']['clientNickName'] ?? $project['client']['displayName'] ?? '');

        if (!$project_id) {
            continue;
        }

        error_log("F2F: Iniciando paginação do projeto #{$project_id}: {$project_title} (Cliente: {$project_client_nickname}) (" . ($index + 1) . "/" . count($projects) . ")");

        $page_number = 1;
        $page_size = 500; // tentar maior página para reduzir chamadas (TaskRow padrão é 200)
        $total_pages_fetched = 0;

        while (true) {
            $body = array(
                'JobIDs' => array($project_id),
                'IncludeClosed' => true,
                'Pagination' => array(
                    'PageNumber' => $page_number,
                    'PageSize' => $page_size,
                )
            );

            $tasks_response = wp_remote_request($tasks_url, array(
                'method' => 'POST',
                'headers' => array(
                    '__identifier' => $api_token,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($body),
                'timeout' => 120,
            ));

            if (is_wp_error($tasks_response)) {
                error_log('F2F: Erro na página ' . $page_number . ' do projeto ' . $project_id . ': ' . $tasks_response->get_error_message());
                break; // parar este projeto
            }

            $tasks_data = json_decode(wp_remote_retrieve_body($tasks_response), true);
            $page_tasks = $tasks_data['data'] ?? $tasks_data;

            if (empty($page_tasks) || !is_array($page_tasks)) {
                error_log('F2F: Projeto ' . $project_id . ' - página ' . $page_number . ' vazia. Encerrando.');
                break;
            }

            error_log('F2F: Projeto ' . $project_id . ' - Página ' . $page_number . ' retornou ' . count($page_tasks) . ' tasks');
            $total_pages_fetched++;

            // Importar página
            foreach ($page_tasks as $task) {
                $task_id = $task['taskID'] ?? $task['TaskID'] ?? null;

                if (!$task_id) {
                    continue;
                }

                // Tentar extrair client nickname e display name da própria task
                $raw_task_client_nick = trim($task['clientNickName'] ?? $task['clientNickname'] ?? '');
                $raw_task_client_display = trim($task['clientDisplayName'] ?? '');

                // Fallbacks: se não vier nada na task, usar do projeto
                $task_client_nick = $raw_task_client_nick !== '' ? $raw_task_client_nick : $project_client_nickname;
                $task_client_display = $raw_task_client_display !== '' ? $raw_task_client_display : ($task_client_nick !== '' ? $task_client_nick : $project_client_nickname);

                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE taskrow_id = %s",
                    $task_id
                ));

                $data = array(
                    'taskrow_id' => $task_id,
                    'job_number' => $task['jobNumber'] ?? 0,
                    'task_number' => $task['taskNumber'] ?? 0,
                    'client_nickname' => $task_client_nick,
                    'title' => $task['taskTitle'] ?? $task['title'] ?? 'Sem título',
                    'description' => $task['taskDescription'] ?? $task['description'] ?? '',
                    'client_name' => $task_client_display ?: 'Cliente Desconhecido',
                    'status' => $task['pipelineStep'] ?? null,
                    'priority' => $task['priority'] ?? $task['Priority'] ?? null,
                    'due_date' => $task['dueDate'] ?? $task['due_date'] ?? null,
                    'attachments' => json_encode($task['attachments'] ?? array()),
                );

                if ($existing) {
                    $wpdb->update($table_name, $data, array('id' => $existing));
                    $total_updated++;
                } else {
                    $wpdb->insert($table_name, $data);
                    $total_imported++;
                }
            }

            // Liberar memória da página
            unset($page_tasks);
            unset($tasks_data);
            unset($tasks_response);

            // Se retornou menos que page_size, acabou
            if (isset($tasks_data['data']) && count($tasks_data['data']) < $page_size) {
                error_log('F2F: Projeto ' . $project_id . ' - última página alcançada (menos que ' . $page_size . ').');
                break;
            }
            if (!isset($tasks_data['data']) && (count($tasks_data) < $page_size)) {
                error_log('F2F: Projeto ' . $project_id . ' - última página (array raiz menor que ' . $page_size . ').');
                break;
            }

            $page_number++;
        }

        error_log('F2F: Projeto ' . $project_id . " concluído. Páginas: {$total_pages_fetched}. Total acumulado: " . ($total_imported + $total_updated));
    }

    error_log("F2F: Importação concluída. {$total_imported} tasks novas, {$total_updated} atualizadas");

    wp_send_json_success(array(
        'message' => "Importadas {$total_imported} tasks novas e {$total_updated} atualizadas",
        'total' => $total_imported,
        'updated' => $total_updated,
    ));
}
add_action('wp_ajax_f2f_import_taskrow_demands', 'f2f_ajax_import_taskrow_demands');

/**
 * AJAX: Apagar todas as demandas do Taskrow
 */
function f2f_ajax_clear_all_taskrow_demands()
{
    error_log('=== F2F: CLEAR ALL DEMANDS CHAMADO ===');

    if (!current_user_can('manage_options')) {
        error_log('F2F: Usuário sem permissão');
        wp_send_json_error('Sem permissão');
    }

    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'f2f_ajax_nonce')) {
        error_log('F2F: Nonce inválido ao apagar demandas');
        wp_send_json_error('Nonce inválido');
    }

    error_log('F2F Taskrow: Apagando todas as demandas (TRUNCATE)');

    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';

    // Contar quantas demandas existem antes
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

    if ($count == 0) {
        wp_send_json_success(array(
            'message' => 'Nenhuma demanda encontrada para apagar.'
        ));
    }

    // Usar TRUNCATE para limpar tudo e resetar IDs
    $result = $wpdb->query("TRUNCATE TABLE {$table_name}");

    if ($result === false) {
        error_log('F2F Taskrow: Erro ao apagar demandas');
        wp_send_json_error('Erro ao apagar demandas do banco de dados.');
    }

    error_log("F2F Taskrow: Tabela truncada com sucesso. Anteriormente tinha {$count} registros.");

    wp_send_json_success(array(
        'message' => "Todas as demandas ({$count}) foram apagadas com sucesso!",
        'deleted_count' => $count
    ));
}
add_action('wp_ajax_f2f_clear_all_taskrow_demands', 'f2f_ajax_clear_all_taskrow_demands');

// AJAX: Atualizar estrutura da tabela
function f2f_ajax_update_table_structure()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';

    // Verificar se as colunas owner_user_id e owner_user_login existem
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'owner_user_id'");

    if (empty($column_check)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN owner_user_id varchar(20) DEFAULT NULL AFTER clickup_id");
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN owner_user_login varchar(255) DEFAULT NULL AFTER owner_user_id");
        error_log('F2F: Colunas owner_user_id e owner_user_login adicionadas');
        wp_send_json_success(array(
            'message' => 'Colunas owner_user_id e owner_user_login adicionadas com sucesso!'
        ));
    } else {
        wp_send_json_success(array(
            'message' => 'Colunas já existem. Estrutura da tabela está atualizada.'
        ));
    }
}
add_action('wp_ajax_f2f_update_table_structure', 'f2f_ajax_update_table_structure');

// AJAX handler para testar API - Listar Projetos
function f2f_ajax_test_list_projects()
{
    $api_token = get_option('taskrow_api_token');
    $host_name = get_option('taskrow_host_name');
    // fallback para testes locais
    if (empty($api_token)) {
        $api_token = defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '';
    }
    if (empty($host_name)) {
        $host_name = defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '';
    }

    if (empty($api_token) || empty($host_name)) {
        wp_send_json_error('API não configurada');
        return;
    }

    $url = 'https://' . $host_name . '/api/v2/core/job/list';

    $response = wp_remote_request($url, array(
        'method' => 'GET',
        'headers' => array(
            '__identifier' => $api_token,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    wp_send_json_success($data);
}
add_action('wp_ajax_f2f_test_list_projects', 'f2f_ajax_test_list_projects');
add_action('wp_ajax_nopriv_f2f_test_list_projects', 'f2f_ajax_test_list_projects');
// AJAX handler para testar API - Listar Usuários
function f2f_ajax_test_list_users()
{
    // Usar classe central quando possível para manter consistência
    if (class_exists('F2F_Taskrow_API')) {
        $api = F2F_Taskrow_API::get_instance();
        if ($api->is_configured()) {
            $result = $api->get_users();
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }
            wp_send_json_success($result);
            return;
        }
    }

    // Fallback manual caso a classe ainda não esteja configurada
    $api_token = get_option('taskrow_api_token');
    $host_name = get_option('taskrow_host_name');
    if (empty($api_token)) {
        $api_token = get_option('f2f_taskrow_api_token');
    }
    if (empty($host_name)) {
        $host_name = get_option('f2f_taskrow_host_name');
    }
    if (empty($api_token)) {
        $api_token = defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '';
    }
    if (empty($host_name)) {
        $host_name = defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '';
    }

    if (empty($api_token) || empty($host_name)) {
        wp_send_json_error('API não configurada (token/host ausentes)');
        return;
    }

    $url = 'https://' . $host_name . '/api/v1/User/ListUsers';
    error_log('F2F: Tentando listar usuários via endpoint v1: ' . $url);

    $response = wp_remote_request($url, array(
        'method' => 'GET',
        'headers' => array(
            '__identifier' => $api_token,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 40,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('F2F: Erro ao listar usuários - ' . $response->get_error_message());
        wp_send_json_error($response->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        error_log('F2F: Falha listar usuários - HTTP ' . $code . ' Body: ' . $body);
        wp_send_json_error('Falha listar usuários. HTTP ' . $code);
        return;
    }

    $data = json_decode($body, true);
    if ($data === null) {
        error_log('F2F: JSON inválido em ListUsers: ' . $body);
        wp_send_json_error('Resposta inválida da API (JSON)');
        return;
    }

    wp_send_json_success($data);
}
add_action('wp_ajax_f2f_test_list_users', 'f2f_ajax_test_list_users');
add_action('wp_ajax_nopriv_f2f_test_list_users', 'f2f_ajax_test_list_users');

// AJAX handler para listar clientes
function f2f_ajax_test_list_clients()
{
    if (class_exists('F2F_Taskrow_API')) {
        $api = F2F_Taskrow_API::get_instance();
        if ($api->is_configured()) {
            $result = $api->get_clients();
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }
            wp_send_json_success($result);
            return;
        }
    }
    $api_token = get_option('taskrow_api_token') ?: get_option('f2f_taskrow_api_token');
    $host_name = get_option('taskrow_host_name') ?: get_option('f2f_taskrow_host_name');
    if (empty($api_token)) {
        $api_token = defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '';
    }
    if (empty($host_name)) {
        $host_name = defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '';
    }
    if (empty($api_token) || empty($host_name)) {
        wp_send_json_error('API não configurada (token/host ausentes)');
        return;
    }
    $url = 'https://' . $host_name . '/api/v1/Client/ListClients';
    error_log('F2F: Tentando listar clientes via endpoint v1: ' . $url);
    $response = wp_remote_request($url, array(
        'method' => 'GET',
        'headers' => array(
            '__identifier' => $api_token,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 40,
        'sslverify' => false,
    ));
    if (is_wp_error($response)) {
        error_log('F2F: Erro ao listar clientes - ' . $response->get_error_message());
        wp_send_json_error($response->get_error_message());
        return;
    }
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        error_log('F2F: Falha listar clientes - HTTP ' . $code . ' Body: ' . $body);
        wp_send_json_error('Falha listar clientes. HTTP ' . $code);
        return;
    }
    $data = json_decode($body, true);
    if ($data === null) {
        error_log('F2F: JSON inválido em ListClients: ' . $body);
        wp_send_json_error('Resposta inválida da API (JSON)');
        return;
    }
    wp_send_json_success($data);
}
add_action('wp_ajax_f2f_test_list_clients', 'f2f_ajax_test_list_clients');
add_action('wp_ajax_nopriv_f2f_test_list_clients', 'f2f_ajax_test_list_clients');

// AJAX: Itens pendentes (Clients, Jobs, Tasks) de um usuário específico
function f2f_ajax_get_user_pending_entities()
{
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if ($user_id <= 0) {
        wp_send_json_error('user_id inválido');
    }

    // Verificar se o usuário existe antes de tentar endpoints pendentes
    $list_users_url = 'https://' . (get_option('f2f_taskrow_host_name', get_option('taskrow_host_name')) ?: (defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '')) . '/api/v1/User/ListUsers';
    $token_check = get_option('f2f_taskrow_api_token', get_option('taskrow_api_token')) ?: (defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '');
    if ($token_check) {
        $u_resp = wp_remote_request($list_users_url, array(
            'method' => 'GET',
            'headers' => array('__identifier' => $token_check, 'Content-Type' => 'application/json'),
            'timeout' => 25,
            'sslverify' => false,
        ));
        if (!is_wp_error($u_resp)) {
            $u_body = wp_remote_retrieve_body($u_resp);
            $u_json = json_decode($u_body, true);
            $found = false;
            if (is_array($u_json)) {
                $items = $u_json['items'] ?? $u_json;
                if (is_array($items)) {
                    foreach ($items as $usr) {
                        $idCandidate = $usr['UserID'] ?? $usr['userID'] ?? $usr['id'] ?? null;
                        if ($idCandidate == $user_id) {
                            $found = true;
                            break;
                        }
                    }
                }
            }
            if (!$found) {
                error_log('F2F: UserID ' . $user_id . ' não encontrado em ListUsers antes de tentar pending entities');
            } else {
                error_log('F2F: UserID ' . $user_id . ' confirmado em ListUsers');
            }
        }
    }

    // Priorizar classe central
    if (class_exists('F2F_Taskrow_API')) {
        $api = F2F_Taskrow_API::get_instance();
        if (!$api->is_configured()) {
            // fallback manual abaixo
        } else {
            // Tentar múltiplas variações do endpoint
            $token = get_option('f2f_taskrow_api_token');
            $host = get_option('f2f_taskrow_host_name');
            $attempts = array(
                array('method' => 'GET', 'url' => 'https://' . $host . '/api/v1/User/GetUserPendingEntities?userID=' . $user_id, 'body' => null, 'label' => 'GET query param'),
                array('method' => 'GET', 'url' => 'https://' . $host . '/api/v1/User/GetUserPendingEntities/' . $user_id, 'body' => null, 'label' => 'GET path param'),
            );
            $errors = array();
            foreach ($attempts as $a) {
                error_log('F2F: Tentativa GetUserPendingEntities [' . $a['label'] . '] URL=' . $a['url']);
                $args = array(
                    'method' => $a['method'],
                    'headers' => array(
                        '__identifier' => $token,
                        'Content-Type' => 'application/json',
                    ),
                    'timeout' => 45,
                    'sslverify' => false,
                );
                if ($a['method'] === 'POST') {
                    $args['body'] = json_encode($a['body']);
                }
                $response = wp_remote_request($a['url'], $args);
                if (is_wp_error($response)) {
                    $errors[] = $a['label'] . ': WP_Error ' . $response->get_error_message();
                    continue;
                }
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if ($code < 200 || $code >= 300) {
                    $errors[] = $a['label'] . ': HTTP ' . $code . ' Body=' . substr($body, 0, 200);
                    continue;
                }
                $data = json_decode($body, true);
                if ($data === null) {
                    $errors[] = $a['label'] . ': JSON inválido Body=' . substr($body, 0, 200);
                    continue;
                }
                $data['_attempt'] = $a['label'];
                wp_send_json_success($data);
                return;
            }
            wp_send_json_error('Todas as tentativas falharam: ' . implode(' | ', $errors));
            return;
        }
    }

    // Fallback manual
    $api_token = get_option('taskrow_api_token') ?: get_option('f2f_taskrow_api_token');
    $host_name = get_option('taskrow_host_name') ?: get_option('f2f_taskrow_host_name');
    if (empty($api_token)) {
        $api_token = defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '';
    }
    if (empty($host_name)) {
        $host_name = defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '';
    }
    if (empty($api_token) || empty($host_name)) {
        wp_send_json_error('API não configurada');
    }

    // Fallback manual com as mesmas tentativas
    $attempts = array(
        array('method' => 'GET', 'url' => 'https://' . $host_name . '/api/v1/User/GetUserPendingEntities?userID=' . $user_id, 'body' => null, 'label' => 'GET query param'),
        array('method' => 'GET', 'url' => 'https://' . $host_name . '/api/v1/User/GetUserPendingEntities/' . $user_id, 'body' => null, 'label' => 'GET path param'),
    );
    $errors = array();
    foreach ($attempts as $a) {
        error_log('F2F: Fallback tentativa GetUserPendingEntities [' . $a['label'] . '] URL=' . $a['url']);
        $args = array(
            'method' => $a['method'],
            'headers' => array(
                '__identifier' => $api_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 45,
            'sslverify' => false,
        );
        if ($a['method'] === 'POST') {
            $args['body'] = json_encode($a['body']);
        }
        $response = wp_remote_request($a['url'], $args);
        if (is_wp_error($response)) {
            $errors[] = $a['label'] . ': WP_Error ' . $response->get_error_message();
            continue;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            $errors[] = $a['label'] . ': HTTP ' . $code . ' Body=' . substr($body, 0, 200);
            continue;
        }
        $data = json_decode($body, true);
        if ($data === null) {
            $errors[] = $a['label'] . ': JSON inválido Body=' . substr($body, 0, 200);
            continue;
        }
        $data['_attempt'] = $a['label'];
        wp_send_json_success($data);
        return;
    }
    wp_send_json_error('Todas as tentativas falharam: ' . implode(' | ', $errors));
}
add_action('wp_ajax_f2f_get_user_pending_entities', 'f2f_ajax_get_user_pending_entities');
add_action('wp_ajax_nopriv_f2f_get_user_pending_entities', 'f2f_ajax_get_user_pending_entities');

// AJAX: Buscar task específica por TaskID para inspeção
function f2f_ajax_get_task_by_id()
{
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    if ($task_id <= 0) {
        wp_send_json_error('task_id inválido');
    }
    $api_token = get_option('taskrow_api_token') ?: get_option('f2f_taskrow_api_token');
    $host_name = get_option('taskrow_host_name') ?: get_option('f2f_taskrow_host_name');
    if (empty($api_token)) {
        $api_token = defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '';
    }
    if (empty($host_name)) {
        $host_name = defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '';
    }
    if (empty($api_token) || empty($host_name)) {
        wp_send_json_error('API não configurada');
    }

    // Tentar múltiplos endpoints para encontrar dados de responsável
    $endpoints_to_try = array();

    // 1. GET direto da task (pode existir endpoint /api/v1/Task/Get ou similar)
    $endpoints_to_try[] = array(
        'name' => 'GET Task Direct v1',
        'url' => 'https://' . $host_name . '/api/v1/Task/Get?taskID=' . $task_id,
        'method' => 'GET',
        'body' => null
    );

    // 2. GET v2 task
    $endpoints_to_try[] = array(
        'name' => 'GET Task Direct v2',
        'url' => 'https://' . $host_name . '/api/v2/Task/Get?taskID=' . $task_id,
        'method' => 'GET',
        'body' => null
    );

    // 3. Busca avançada (atual)
    $endpoints_to_try[] = array(
        'name' => 'POST AdvancedSearch',
        'url' => 'https://' . $host_name . '/api/v2/search/tasks/advancedsearch',
        'method' => 'POST',
        'body' => json_encode(array(
            'TaskIDs' => array($task_id),
            'IncludeClosed' => true,
            'Pagination' => array('PageNumber' => 1, 'PageSize' => 1)
        ))
    );

    $results = array();

    foreach ($endpoints_to_try as $endpoint) {
        $args = array(
            'method' => $endpoint['method'],
            'headers' => array('__identifier' => $api_token),
            'timeout' => 40,
            'sslverify' => false,
        );

        if ($endpoint['method'] === 'POST' && $endpoint['body']) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = $endpoint['body'];
        }

        $response = wp_remote_request($endpoint['url'], $args);
        $code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        $body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);

        $result_entry = array(
            'endpoint' => $endpoint['name'],
            'url' => $endpoint['url'],
            'http_code' => $code,
            'success' => ($code >= 200 && $code < 300)
        );

        if ($result_entry['success']) {
            $decoded = json_decode($body, true);
            $result_entry['data'] = $decoded;
            // Tentar encontrar campos de usuário responsável
            $result_entry['user_fields_found'] = f2f_extract_user_fields_from_task($decoded);
        } else {
            $result_entry['error'] = substr($body, 0, 200);
        }

        $results[] = $result_entry;
    }

    wp_send_json_success(array(
        'task_id' => $task_id,
        'attempts' => $results
    ));
}

// Helper: extrair todos os campos relacionados a usuários de uma task
function f2f_extract_user_fields_from_task($data)
{
    $user_fields = array();

    // Se for array de items, pega primeiro
    if (isset($data['items']) && is_array($data['items']) && count($data['items']) > 0) {
        $task = $data['items'][0];
    } else {
        $task = $data;
    }

    // Buscar RECURSIVAMENTE qualquer campo que contenha "user", "owner", "responsible", "assigned", "participant"
    // ou que contenha o UserID 29116 (Raissa)
    $search_terms = array('user', 'owner', 'responsible', 'assigned', 'participant', 'creator', 'modifier', '29116');

    function search_recursive($array, $search_terms, $parent_key = '')
    {
        $found = array();

        if (!is_array($array)) {
            return $found;
        }

        foreach ($array as $key => $value) {
            $full_key = $parent_key ? $parent_key . '.' . $key : $key;
            $key_lower = strtolower($key);

            // Verificar se a chave contém algum termo de busca
            $matches_key = false;
            foreach ($search_terms as $term) {
                if (stripos($key_lower, strtolower($term)) !== false) {
                    $matches_key = true;
                    break;
                }
            }

            // Verificar se o valor contém algum termo de busca (se for string/number)
            $matches_value = false;
            if (is_scalar($value)) {
                $value_str = (string) $value;
                foreach ($search_terms as $term) {
                    if (stripos($value_str, $term) !== false) {
                        $matches_value = true;
                        break;
                    }
                }
            }

            if ($matches_key || $matches_value) {
                $found[$full_key] = $value;
            }

            // Recursão para arrays/objetos aninhados
            if (is_array($value)) {
                $nested = search_recursive($value, $search_terms, $full_key);
                $found = array_merge($found, $nested);
            }
        }

        return $found;
    }

    $user_fields = search_recursive($task, $search_terms);

    return $user_fields;
}
add_action('wp_ajax_f2f_get_task_by_id', 'f2f_ajax_get_task_by_id');
add_action('wp_ajax_nopriv_f2f_get_task_by_id', 'f2f_ajax_get_task_by_id');

// AJAX: Buscar tasks filtrando por ownerUserID
function f2f_ajax_get_tasks_by_owner()
{
    $owner_user_id = isset($_POST['owner_user_id']) ? intval($_POST['owner_user_id']) : 0;
    if ($owner_user_id <= 0) {
        wp_send_json_error('owner_user_id inválido');
    }

    $api_token = get_option('taskrow_api_token') ?: get_option('f2f_taskrow_api_token');
    $host_name = get_option('taskrow_host_name') ?: get_option('f2f_taskrow_host_name');
    if (empty($api_token)) {
        $api_token = defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '';
    }
    if (empty($host_name)) {
        $host_name = defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '';
    }
    if (empty($api_token) || empty($host_name)) {
        wp_send_json_error('API não configurada');
    }

    // Buscar TODAS as tasks e filtrar por ownerUserID no PHP (API pode não ter filtro direto)
    $url = 'https://' . $host_name . '/api/v2/search/tasks/advancedsearch';
    $page = 1;
    $page_size = 200;
    $matched_tasks = array();

    do {
        $body = array(
            'IncludeClosed' => true,
            'Pagination' => array('PageNumber' => $page, 'PageSize' => $page_size)
        );

        $response = wp_remote_request($url, array(
            'method' => 'POST',
            'headers' => array('__identifier' => $api_token, 'Content-Type' => 'application/json'),
            'body' => json_encode($body),
            'timeout' => 40,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            wp_send_json_error('HTTP ' . $code);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $items = $data['items'] ?? array();

        // Filtrar tasks onde ownerUserID == $owner_user_id
        foreach ($items as $task) {
            if (isset($task['ownerUserID']) && intval($task['ownerUserID']) === $owner_user_id) {
                $matched_tasks[] = array(
                    'taskID' => $task['taskID'] ?? 'N/A',
                    'taskTitle' => $task['taskTitle'] ?? 'N/A',
                    'jobNumber' => $task['jobNumber'] ?? 'N/A',
                    'pipelineStep' => $task['pipelineStep'] ?? 'N/A',
                    'clientNickName' => $task['clientNickName'] ?? $task['clientNickname'] ?? 'N/A',
                    'ownerUserID' => $task['ownerUserID'],
                    'ownerUserLogin' => $task['ownerUserLogin'] ?? 'N/A'
                );
            }
        }

        $has_more = count($items) === $page_size;
        $page++;

        // Limite de segurança: máximo 5 páginas (1000 tasks)
        if ($page > 5)
            break;

    } while ($has_more);

    wp_send_json_success(array(
        'owner_user_id' => $owner_user_id,
        'total_found' => count($matched_tasks),
        'tasks' => $matched_tasks
    ));
}
add_action('wp_ajax_f2f_get_tasks_by_owner', 'f2f_ajax_get_tasks_by_owner');
add_action('wp_ajax_nopriv_f2f_get_tasks_by_owner', 'f2f_ajax_get_tasks_by_owner');


// AJAX handler para testar API - Buscar Tasks
function f2f_ajax_test_search_tasks()
{
    $api_token = get_option('taskrow_api_token');
    $host_name = get_option('taskrow_host_name');
    // fallback para testes locais
    if (empty($api_token)) {
        $api_token = defined('F2F_TASKROW_FALLBACK_TOKEN') ? F2F_TASKROW_FALLBACK_TOKEN : '';
    }
    if (empty($host_name)) {
        $host_name = defined('F2F_TASKROW_FALLBACK_HOST') ? F2F_TASKROW_FALLBACK_HOST : '';
    }
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

    if (empty($api_token) || empty($host_name)) {
        wp_send_json_error('API não configurada');
        return;
    }

    if (empty($project_id)) {
        wp_send_json_error('ID do projeto é obrigatório');
        return;
    }

    $url = 'https://' . $host_name . '/api/v2/search/tasks/advancedsearch';

    $body = array(
        'JobIDs' => array($project_id),
        'IncludeClosed' => true,
        'Pagination' => array(
            'PageNumber' => 1,
            'PageSize' => 10
        )
    );

    $response = wp_remote_request($url, array(
        'method' => 'POST',
        'headers' => array(
            '__identifier' => $api_token,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($body),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    wp_send_json_success($data);
}
add_action('wp_ajax_f2f_test_search_tasks', 'f2f_ajax_test_search_tasks');
add_action('wp_ajax_nopriv_f2f_test_search_tasks', 'f2f_ajax_test_search_tasks');

/**
 * AJAX: Salvar credenciais do TaskRow (token + host)
 */
function f2f_ajax_save_taskrow_credentials()
{
    // Verifica permissão e nonce
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'f2f_save_taskrow_nonce')) {
        wp_send_json_error('Nonce inválido');
    }

    $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
    $host = isset($_POST['host']) ? sanitize_text_field($_POST['host']) : '';

    if (empty($token) || empty($host)) {
        wp_send_json_error('Token e host são obrigatórios');
    }

    // Salvar em ambas chaves para compatibilidade com código existente
    update_option('taskrow_api_token', $token);
    update_option('taskrow_host_name', $host);
    update_option('f2f_taskrow_api_token', $token);
    update_option('f2f_taskrow_host_name', $host);

    error_log('F2F: Credenciais TaskRow salvas via AJAX (usuário ID: ' . get_current_user_id() . ')');

    wp_send_json_success('Credenciais salvas');
}
add_action('wp_ajax_f2f_save_taskrow_credentials', 'f2f_ajax_save_taskrow_credentials');

/**
 * AJAX: Importar demandas de UM projeto específico (Incremental)
 */
function f2f_ajax_import_single_project()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }

    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $project_title = isset($_POST['project_title']) ? sanitize_text_field($_POST['project_title']) : 'Sem título';
    $project_client_nickname = isset($_POST['client_nickname']) ? sanitize_text_field($_POST['client_nickname']) : '';

    if (!$project_id) {
        wp_send_json_error('ID do projeto inválido');
    }

    $host_name = get_option('f2f_taskrow_host_name', '');
    $api_token = get_option('f2f_taskrow_api_token', '');

    if (empty($host_name) || empty($api_token)) {
        wp_send_json_error('API Taskrow não configurada');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';

    $total_imported = 0;
    $total_updated = 0;

    $tasks_url = 'https://' . $host_name . '/api/v2/search/tasks/advancedsearch';

    error_log("F2F: Iniciando importação do projeto #{$project_id}: {$project_title}");

    $page_number = 1;
    $page_size = 500;
    $total_pages_fetched = 0;

    while (true) {
        $body = array(
            'JobIDs' => array($project_id),
            'IncludeClosed' => true,
            'Pagination' => array(
                'PageNumber' => $page_number,
                'PageSize' => $page_size,
            )
        );

        $tasks_response = wp_remote_request($tasks_url, array(
            'method' => 'POST',
            'headers' => array(
                '__identifier' => $api_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60, // Timeout menor pois é por projeto
        ));

        if (is_wp_error($tasks_response)) {
            error_log('F2F: Erro na página ' . $page_number . ' do projeto ' . $project_id . ': ' . $tasks_response->get_error_message());
            break;
        }

        $tasks_data = json_decode(wp_remote_retrieve_body($tasks_response), true);
        $page_tasks = $tasks_data['data'] ?? $tasks_data;

        if (empty($page_tasks) || !is_array($page_tasks)) {
            break;
        }

        $total_pages_fetched++;

        foreach ($page_tasks as $task) {
            $task_id = $task['taskID'] ?? $task['TaskID'] ?? null;

            if (!$task_id) {
                continue;
            }

            $raw_task_client_nick = trim($task['clientNickName'] ?? $task['clientNickname'] ?? '');
            $raw_task_client_display = trim($task['clientDisplayName'] ?? '');

            $task_client_nick = $raw_task_client_nick !== '' ? $raw_task_client_nick : $project_client_nickname;
            $task_client_display = $raw_task_client_display !== '' ? $raw_task_client_display : ($task_client_nick !== '' ? $task_client_nick : $project_client_nickname);

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE taskrow_id = %s",
                $task_id
            ));

            $data = array(
                'taskrow_id' => $task_id,
                'job_number' => $task['jobNumber'] ?? 0,
                'task_number' => $task['taskNumber'] ?? 0,
                'client_nickname' => $task_client_nick,
                'title' => $task['taskTitle'] ?? $task['title'] ?? 'Sem título',
                'description' => $task['taskDescription'] ?? $task['description'] ?? '',
                'client_name' => $task_client_display ?: 'Cliente Desconhecido',
                'status' => $task['pipelineStep'] ?? null,
                'priority' => $task['priority'] ?? $task['Priority'] ?? null,
                'due_date' => $task['dueDate'] ?? $task['due_date'] ?? null,
                'attachments' => json_encode($task['attachments'] ?? array()),
            );

            if ($existing) {
                $wpdb->update($table_name, $data, array('id' => $existing));
                $total_updated++;
            } else {
                $wpdb->insert($table_name, $data);
                $total_imported++;
            }
        }

        // Liberar memória
        unset($page_tasks);
        unset($tasks_data);

        // Se retornou menos que page_size, acabou
        if (isset($tasks_data['data']) && count($tasks_data['data']) < $page_size) {
            break;
        }
        if (!isset($tasks_data['data']) && (count($tasks_data) < $page_size)) {
            break;
        }

        $page_number++;
    }

    wp_send_json_success(array(
        'message' => "Projeto {$project_id} processado.",
        'imported' => $total_imported,
        'updated' => $total_updated,
        'project_id' => $project_id
    ));
}
add_action('wp_ajax_f2f_import_single_project', 'f2f_ajax_import_single_project');
