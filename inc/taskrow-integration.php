<?php
/**
 * Taskrow Integration
 * 
 * Todas as funções e hooks relacionados à integração com Taskrow
 * 
 * @package F2F_Dashboard
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Incluir a classe da API Taskrow
require_once get_template_directory() . '/inc/class-taskrow-api.php';

/**
 * Cria tabela para armazenar demandas do Taskrow
 */
function f2f_create_taskrow_demands_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        taskrow_id varchar(100) NOT NULL,
        clickup_id varchar(100) DEFAULT NULL,
        title text NOT NULL,
        description longtext DEFAULT NULL,
        client_name varchar(255) DEFAULT NULL,
        status varchar(50) DEFAULT 'pending',
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

// Cria a tabela na ativação do tema
add_action('after_switch_theme', 'f2f_create_taskrow_demands_table');

// Cria a tabela no init se ainda não existir
add_action('init', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        f2f_create_taskrow_demands_table();
    }
}, 21);

/**
 * Cria automaticamente a página "Demandas Taskrow"
 */
function f2f_create_demandas_taskrow_page() {
    $existing_page = get_page_by_path('demandas-taskrow');
    
    if (!$existing_page) {
        $page_data = array(
            'post_title'    => 'Demandas Taskrow',
            'post_content'  => '<!-- Esta página mostra todas as demandas importadas do Taskrow -->',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'demandas-taskrow',
            'post_author'   => 1,
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            update_post_meta($page_id, '_wp_page_template', 'page-demandas-taskrow.php');
            update_option('f2f_demandas_taskrow_page_created', true);
        }
    }
}

add_action('after_switch_theme', 'f2f_create_demandas_taskrow_page');

add_action('init', function() {
    if (!get_option('f2f_demandas_taskrow_page_created')) {
        f2f_create_demandas_taskrow_page();
    }
}, 22);

/**
 * Cria automaticamente a página "Sincronizar Horas"
 */
function f2f_create_sincronizar_horas_page() {
    $existing_page = get_page_by_path('sincronizar-horas');
    
    if (!$existing_page) {
        $page_data = array(
            'post_title'    => 'Sincronizar Horas',
            'post_content'  => '<!-- Esta página permite sincronizar horas do ClickUp para o Taskrow -->',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'sincronizar-horas',
            'post_author'   => 1,
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            update_post_meta($page_id, '_wp_page_template', 'page-sincronizar-horas.php');
            update_option('f2f_sincronizar_horas_page_created', true);
        }
    }
}

add_action('after_switch_theme', 'f2f_create_sincronizar_horas_page');

add_action('init', function() {
    if (!get_option('f2f_sincronizar_horas_page_created')) {
        f2f_create_sincronizar_horas_page();
    }
}, 23);

/**
 * Adiciona menu de configurações do Taskrow no admin
 */
function f2f_taskrow_admin_menu() {
    add_submenu_page(
        'f2f-clickup-config',
        'Configurações Taskrow',
        'Taskrow',
        'manage_options',
        'f2f-taskrow-config',
        'f2f_taskrow_config_page'
    );
}
add_action('admin_menu', 'f2f_taskrow_admin_menu', 20);

/**
 * Página de configuração do Taskrow no admin
 */
function f2f_taskrow_config_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Salvar configurações
    if (isset($_POST['f2f_taskrow_save'])) {
        check_admin_referer('f2f_taskrow_config');
        
        update_option('f2f_taskrow_api_token', sanitize_text_field($_POST['taskrow_api_token']));
        
        // Limpar o host para remover qualquer path ou fragmento
        $host = sanitize_text_field($_POST['taskrow_host_name']);
        $host = preg_replace('/^https?:\/\//', '', $host); // Remove http:// ou https://
        $host = preg_replace('/\/.*$/', '', $host); // Remove qualquer path após /
        $host = preg_replace('/#.*$/', '', $host); // Remove qualquer fragmento após #
        
        update_option('f2f_taskrow_host_name', $host);
        
        echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
    }
    
    $api_token = get_option('f2f_taskrow_api_token', '');
    $api_host = get_option('f2f_taskrow_host_name', '');
    
    ?>
    <div class="wrap">
        <h1>⚙️ Configurações Taskrow</h1>
        
        <form method="post">
            <?php wp_nonce_field('f2f_taskrow_config'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="taskrow_host_name">Host do Taskrow</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="taskrow_host_name" 
                               name="taskrow_host_name" 
                               value="<?php echo esc_attr($api_host); ?>" 
                               class="regular-text" 
                               placeholder="ex: f2f.taskrow.com">
                        <p class="description">
                            Host do servidor Taskrow (apenas o domínio, ex: f2f.taskrow.com)<br>
                            <strong>Não inclua:</strong> https://, /, # ou qualquer path
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="taskrow_api_token">API Token</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="taskrow_api_token" 
                               name="taskrow_api_token" 
                               value="<?php echo esc_attr($api_token); ?>" 
                               class="regular-text" 
                               placeholder="Cole aqui o token da API do Taskrow">
                        <p class="description">
                            Token de autenticação da API do Taskrow. 
                            <a href="https://f2f.taskrow.com" target="_blank">Obter token</a>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="f2f_taskrow_save" class="button button-primary">
                    Salvar Configurações
                </button>
            </p>
        </form>
        
        <hr>
        
        <h2>🔗 Links Rápidos</h2>
        <p>
            <a href="<?php echo home_url('/demandas-taskrow/'); ?>" class="button" target="_blank">
                📋 Ver Demandas Taskrow
            </a>
            
            <a href="<?php echo home_url('/sincronizar-horas/'); ?>" class="button" target="_blank">
                ⏱️ Sincronizar Horas
            </a>
        </p>
    </div>
    <?php
}

/**
 * AJAX: Importar demandas do Taskrow
 */
function f2f_ajax_import_taskrow_demands() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }
    
    $api = F2F_Taskrow_API::get_instance();
    
    if (!$api->is_configured()) {
        wp_send_json_error('API Taskrow não configurada');
    }
    
    $demands = $api->get_demands();
    
    if (is_wp_error($demands)) {
        wp_send_json_error($demands->get_error_message());
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';
    $imported = 0;
    
    foreach ($demands as $demand) {
        // Verificar se já existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE taskrow_id = %s",
            $demand['id']
        ));
        
        $data = array(
            'taskrow_id'   => $demand['id'],
            'title'        => $demand['title'],
            'description'  => $demand['description'] ?? '',
            'client_name'  => $demand['client_name'] ?? '',
            'status'       => 'pending',
            'priority'     => $demand['priority'] ?? null,
            'due_date'     => $demand['due_date'] ?? null,
            'attachments'  => json_encode($demand['attachments'] ?? array()),
        );
        
        if ($existing) {
            $wpdb->update($table_name, $data, array('id' => $existing));
        } else {
            $wpdb->insert($table_name, $data);
            $imported++;
        }
    }
    
    wp_send_json_success(array(
        'message' => "Importadas {$imported} demandas do Taskrow",
        'total' => count($demands),
    ));
}
add_action('wp_ajax_f2f_import_taskrow_demands', 'f2f_ajax_import_taskrow_demands');

/**
 * AJAX: Testar conexão com Taskrow
 */
function f2f_ajax_test_taskrow_connection() {
    error_log('F2F Taskrow: Teste de conexão iniciado');
    
    if (!current_user_can('manage_options')) {
        error_log('F2F Taskrow: Usuário sem permissão');
        wp_send_json_error('Sem permissão');
    }
    
    error_log('F2F Taskrow: Verificando configuração da API');
    $api = F2F_Taskrow_API::get_instance();
    
    if (!$api->is_configured()) {
        error_log('F2F Taskrow: API não configurada');
        wp_send_json_error('API Taskrow não está configurada. Configure o token e host nas configurações.');
    }
    
    error_log('F2F Taskrow: Testando conexão...');
    $result = $api->test_connection();
    
    if (is_wp_error($result)) {
        error_log('F2F Taskrow: Erro na conexão: ' . $result->get_error_message());
        wp_send_json_error($result->get_error_message());
    }
    
    error_log('F2F Taskrow: Conexão bem-sucedida');
    wp_send_json_success($result);
}
add_action('wp_ajax_f2f_test_taskrow_connection', 'f2f_ajax_test_taskrow_connection');

/**
 * AJAX: Apagar todas as demandas do Taskrow
 */
function f2f_ajax_clear_all_taskrow_demands() {
    if (!current_user_can('manage_options')) {
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

/**
 * AJAX: Enviar demanda para ClickUp
 */
function f2f_ajax_send_demand_to_clickup() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }
    
    $demand_id = intval($_POST['demand_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';
    
    $demand = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $demand_id
    ), ARRAY_A);
    
    if (!$demand) {
        wp_send_json_error('Demanda não encontrada');
    }
    
    if ($demand['clickup_id']) {
        wp_send_json_error('Demanda já foi enviada ao ClickUp');
    }
    
    // Criar task no ClickUp
    $clickup_api = F2F_ClickUp_API::get_instance();
    $list_id = get_option('f2f_clickup_default_list');
    
    if (!$list_id) {
        wp_send_json_error('Lista padrão do ClickUp não configurada');
    }
    
    $task_data = array(
        'name'        => $demand['title'],
        'description' => $demand['description'],
        'priority'    => $demand['priority'],
    );
    
    if ( ! empty( $demand['due_date'] ) ) {
        $timestamp = strtotime( $demand['due_date'] );
        if ( $timestamp !== false && $timestamp > 0 ) {
            $task_data['due_date'] = $timestamp * 1000;
        } else {
            error_log( "F2F Taskrow Integration: Data de vencimento inválida: " . $demand['due_date'] );
            // Não adiciona data inválida
        }
    }
    
    $result = $clickup_api->create_task($list_id, $task_data);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    // Atualizar demanda com ID do ClickUp
    $wpdb->update(
        $table_name,
        array(
            'clickup_id' => $result['id'],
            'status'     => 'sent_to_clickup',
        ),
        array('id' => $demand_id)
    );
    
    wp_send_json_success(array(
        'message' => 'Demanda enviada ao ClickUp com sucesso!',
        'clickup_url' => $result['url'],
    ));
}
add_action('wp_ajax_f2f_send_demand_to_clickup', 'f2f_ajax_send_demand_to_clickup');

/**
 * AJAX: Sincronizar horas do ClickUp para Taskrow
 */
function f2f_ajax_sync_hours_to_taskrow() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }
    
    $demand_id = intval($_POST['demand_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'f2f_taskrow_demands';
    
    $demand = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $demand_id
    ), ARRAY_A);
    
    if (!$demand || !$demand['clickup_id']) {
        wp_send_json_error('Demanda não encontrada ou não vinculada ao ClickUp');
    }
    
    // Buscar horas no ClickUp
    $clickup_api = F2F_ClickUp_API::get_instance();
    $time_entries = $clickup_api->get_task_time_entries($demand['clickup_id']);
    
    if (is_wp_error($time_entries)) {
        wp_send_json_error($time_entries->get_error_message());
    }
    
    // Calcular total de horas
    $total_hours = 0;
    foreach ($time_entries as $entry) {
        $total_hours += ($entry['duration'] / 1000 / 60 / 60); // ms para horas
    }
    
    // Enviar para Taskrow
    $taskrow_api = F2F_Taskrow_API::get_instance();
    $result = $taskrow_api->save_time_entry(
        $demand['taskrow_id'],
        $total_hours,
        array(
            'description' => 'Horas sincronizadas do ClickUp',
            'date' => date('Y-m-d'),
        )
    );
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    // Atualizar registro
    $wpdb->update(
        $table_name,
        array(
            'hours_tracked' => $total_hours,
            'hours_synced'  => 1,
            'last_sync'     => current_time('mysql'),
        ),
        array('id' => $demand_id)
    );
    
    wp_send_json_success(array(
        'message' => 'Horas sincronizadas com sucesso!',
        'hours' => round($total_hours, 2),
    ));
}
add_action('wp_ajax_f2f_sync_hours_to_taskrow', 'f2f_ajax_sync_hours_to_taskrow');

