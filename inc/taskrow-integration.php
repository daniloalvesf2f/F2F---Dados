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
}
add_action('after_setup_theme', 'f2f_create_taskrow_demands_table');

/**
 * AJAX: Importar demandas do Taskrow filtrando por tag #Tech
 */
function f2f_ajax_import_taskrow_demands()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }

    error_log('F2F: Iniciando importação de tasks com tag #Tech');

    $host_name = get_option('f2f_taskrow_host_name', '');
    $api_token = get_option('f2f_taskrow_api_token', '');

    if (empty($host_name) || empty($api_token)) {
        wp_send_json_error('API Taskrow não configurada');
    }

    // Buscar tasks com tag #Tech
    $tasks_url = 'https://' . $host_name . '/api/v2/search/tasks/advancedsearch';

    $all_tasks = array();
    $page = 1;
    $max_pages = 50; // Buscar até 10.000 tasks (50 páginas x 200)

    do {
        $body = array(
            'Term' => '#Tech',
            'IncludeClosed' => true,
            'Pagination' => array(
                'PageNumber' => $page,
                'PageSize' => 200
            )
        );

        $tasks_response = wp_remote_request($tasks_url, array(
            'method' => 'POST',
            'headers' => array(
                '__identifier' => $api_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));

        if (is_wp_error($tasks_response)) {
            error_log('F2F: Erro na página ' . $page . ': ' . $tasks_response->get_error_message());
            break;
        }

        $tasks_data = json_decode(wp_remote_retrieve_body($tasks_response), true);
        $page_tasks = $tasks_data['data'] ?? $tasks_data;

        if (empty($page_tasks) || !is_array($page_tasks)) {
            break;
        }

        $all_tasks = array_merge($all_tasks, $page_tasks);
        error_log('F2F: Página ' . $page . ' retornou ' . count($page_tasks) . ' tasks.');

        $page++;

    } while ($page <= $max_pages && count($page_tasks) == 200);

    $tasks = $all_tasks;

    if (empty($tasks) || !is_array($tasks)) {
        error_log('F2F: Nenhuma task encontrada com tag #Tech');
        wp_send_json_error('Nenhuma task encontrada com tag #Tech');
    }

    error_log('F2F: ' . count($tasks) . ' tasks encontradas com tag #Tech');

    // LOG TODAS AS PROPRIEDADES DA PRIMEIRA TASK
    if (count($tasks) > 0) {
        error_log('=== F2F: PRIMEIRA TASK - TODAS AS PROPRIEDADES ===');
        error_log(print_r($tasks[0], true));
        error_log('=== FIM PRIMEIRA TASK ===');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';

    $imported = 0;
    foreach ($tasks as $task) {
        $task_id = $task['taskID'] ?? $task['TaskID'] ?? null;

        if (!$task_id) {
            continue;
        }

        // Verificar se já existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE taskrow_id = %s",
            $task_id
        ));

        $data = array(
            'taskrow_id' => $task_id,
            'job_number' => $task['jobNumber'] ?? 0,
            'task_number' => $task['taskNumber'] ?? 0,
            'client_nickname' => $task['clientNickName'] ?? '',
            'title' => $task['taskTitle'] ?? $task['title'] ?? 'Sem título',
            'description' => $task['taskDescription'] ?? $task['description'] ?? '',
            'client_name' => $task['clientDisplayName'] ?? $task['clientNickName'] ?? 'Cliente Desconhecido',
            'status' => $task['pipelineStep'] ?? null,
            'priority' => $task['priority'] ?? $task['Priority'] ?? null,
            'due_date' => $task['dueDate'] ?? $task['due_date'] ?? null,
            'attachments' => json_encode($task['attachments'] ?? array()),
        );

        if ($existing) {
            $wpdb->update($table_name, $data, array('id' => $existing));
        } else {
            $wpdb->insert($table_name, $data);
            $imported++;
        }
    }

    error_log("F2F: Importação concluída. {$imported} tasks novas importadas");

    wp_send_json_success(array(
        'message' => "Importadas {$imported} tasks com tag #Tech",
        'total' => $imported,
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

    error_log('F2F Taskrow: Apagando todas as demandas');

    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';

    // Contar quantas demandas existem
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

    if ($count == 0) {
        wp_send_json_success(array(
            'message' => 'Nenhuma demanda encontrada para apagar.'
        ));
    }

    // Apagar todas as demandas
    $deleted = $wpdb->query("DELETE FROM {$table_name}");

    if ($deleted === false) {
        error_log('F2F Taskrow: Erro ao apagar demandas');
        wp_send_json_error('Erro ao apagar demandas do banco de dados.');
    }

    error_log("F2F Taskrow: {$deleted} demandas apagadas com sucesso");

    wp_send_json_success(array(
        'message' => "{$deleted} demandas apagadas com sucesso!",
        'deleted_count' => $deleted
    ));
}
add_action('wp_ajax_f2f_clear_all_taskrow_demands', 'f2f_ajax_clear_all_taskrow_demands');
