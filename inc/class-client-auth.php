<?php
/**
 * Sistema de Autenticação para Clientes
 */

if (!defined('ABSPATH')) {
    exit;
}

class F2F_Client_Auth {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'f2f_client_access';
        
        // Criar tabela se não existir
        $this->create_table();
        
        // Iniciar sessão se não estiver iniciada
        if (!session_id()) {
            session_start();
        }
        
        // Hooks apenas para admin (sem verificação global)
        add_action('wp_ajax_f2f_toggle_client_access', array($this, 'toggle_client_access'));
        add_action('wp_ajax_f2f_get_client_data', array($this, 'get_client_data'));
        add_action('wp_ajax_f2f_update_client_credentials', array($this, 'update_client_credentials'));
    }
    
    /**
     * Cria a tabela para controle de acesso dos clientes
     */
    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            client_name varchar(255) NOT NULL,
            username varchar(100) NOT NULL,
            password varchar(255) NOT NULL,
            has_access tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY username (username),
            UNIQUE KEY client_name (client_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Verifica se um cliente tem acesso
     */
    public function client_has_access($client_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT has_access FROM {$this->table_name} WHERE client_name = %s",
            $client_name
        ));
        
        return (bool) $result;
    }
    
    /**
     * Verifica se o usuário está logado como cliente (método removido - usando versão com parâmetro)
     */
    
    /**
     * Obtém o cliente logado
     */
    public function get_logged_client() {
        return isset($_SESSION['f2f_client_name']) ? $_SESSION['f2f_client_name'] : null;
    }
    
    /**
     * Processa o login do cliente
     */
    public function handle_login() {
        if (isset($_POST['f2f_client_login']) && wp_verify_nonce($_POST['f2f_nonce'], 'f2f_client_login')) {
            $username = sanitize_text_field($_POST['username']);
            $password = $_POST['password'];
            
            // Debug: Log da tentativa de login
            error_log('F2F Client Login: Tentativa de login para username: ' . $username);
            
            // 1) Tenta login como ADMIN WordPress
            $wp_user = get_user_by('login', $username);
            if ($wp_user && user_can($wp_user, 'manage_options')) {
                $creds = array(
                    'user_login'    => $username,
                    'user_password' => $password,
                    'remember'      => true,
                );
                $signon = wp_signon($creds, false);
                if (!is_wp_error($signon)) {
                    // Admin autenticado via WP → sempre redirecionar para home
                    wp_redirect(home_url('/'));
                    exit;
                }
            }
            
            // 2) Tenta login como CLIENTE da tabela própria
            global $wpdb;
            $client = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE username = %s AND has_access = 1",
                $username
            ));
            
            // Debug: Log do resultado da busca
            if ($client) {
                error_log('F2F Client Login: Cliente encontrado: ' . $client->client_name . ', has_access: ' . $client->has_access);
                
                if (wp_check_password($password, $client->password)) {
                    error_log('F2F Client Login: Senha correta, fazendo login');
                    $_SESSION['f2f_client_logged_in'] = true;
                    $_SESSION['f2f_client_name'] = $client->client_name;
                    $_SESSION['f2f_client_id'] = $client->id;
                    
                    // Cliente logado → redirecionar para sua página específica
                    $client_page = null;
                    
                    // Buscar todas as páginas relacionadas ao cliente
                    $query = new WP_Query(array(
                        'post_type' => 'cliente',
                        's' => $client->client_name,
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
                            
                            if ($score > $max_data_count) {
                                $max_data_count = $score;
                                $best_page = get_post($page_id);
                            }
                            
                            error_log('F2F Client Login: Página "' . $page_title . '" tem ' . $data_count . ' registros, imagem: ' . ($has_featured_image ? 'SIM' : 'NÃO') . ', score: ' . $score);
                        }
                        wp_reset_postdata();
                    }
                    
                    if ($best_page) {
                        $redirect_url = get_permalink($best_page->ID);
                        error_log('F2F Client Login: Redirecionando para página com mais dados: ' . $redirect_url . ' (' . $max_data_count . ' registros)');
                        wp_redirect($redirect_url);
                    } else {
                        error_log('F2F Client Login: Página do cliente não encontrada para: ' . $client->client_name);
                        wp_redirect(home_url('/client-login/?error=page_not_found'));
                    }
                    exit;
                } else {
                    error_log('F2F Client Login: Senha incorreta');
                }
            } else {
                error_log('F2F Client Login: Cliente não encontrado ou sem acesso');
            }
            
            // Falhou
            error_log('F2F Client Login: Login falhou, redirecionando com erro');
            wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
            exit;
        }
    }
    
    /**
     * Limpa a sessão do cliente
     */
    public function clear_client_session() {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION['f2f_client_logged_in']);
        unset($_SESSION['f2f_client_name']);
        unset($_SESSION['f2f_client_id']);
    }
    
    /**
     * Redireciona para nossa página de login após logout
     */
    public function redirect_after_logout() {
        // Evitar loop de redirecionamento
        if (!isset($_GET['loggedout'])) {
            wp_redirect(home_url('/client-login/'));
            exit;
        }
    }
    
    /**
     * Redireciona tentativas de acesso ao wp-login.php para nossa página
     */
    public function redirect_wp_login() {
        // Verificar se não é uma requisição AJAX ou admin específica
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'logout') {
            return; // Permitir logout
        }
        
        // Redirecionar para nossa página de login
        wp_redirect(home_url('/client-login/'));
        exit;
    }
    
    /**
     * Customiza a URL de logout para redirecionar para nossa página
     */
    public function custom_logout_url($logout_url) {
        return wp_logout_url(home_url('/client-login/'));
    }
    
    /**
     * Verifica se o cliente pode acessar uma página específica
     */
    public function can_access_client_page($client_name) {
        // Se é admin (logado via WordPress), pode acessar qualquer página
        if (is_user_logged_in() && current_user_can('manage_options')) return true;

        // Caso contrário, exige sessão de cliente
        if (!$this->is_client_logged_in()) return false;
        
        // Cliente só pode acessar sua própria página
        return $this->get_logged_client() === $client_name;
    }
    
    /**
     * Obtém todos os clientes
     */
    public function get_all_clients() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY client_name ASC");
    }
    
    /**
     * Adiciona menu no admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Controle de Acesso - Clientes',
            'Clientes - Acesso',
            'manage_options',
            'f2f-client-access',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Página de administração
     */
    public function admin_page() {
        $clients = $this->get_all_clients();
        
        // Buscar todos os clientes (CPTs) para mostrar mesmo sem credenciais
        $all_client_posts = get_posts(array(
            'post_type' => 'cliente',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        echo '<div class="wrap">';
        echo '<h1>Controle de Acesso - Clientes</h1>';
        
        if (isset($_GET['message'])) {
            $message = $_GET['message'] === 'success' ? 'Configurações salvas com sucesso!' : 'Erro ao salvar configurações.';
            echo '<div class="notice notice-' . ($_GET['message'] === 'success' ? 'success' : 'error') . '"><p>' . $message . '</p></div>';
        }
        
        // Verificar se a página de login existe
        $login_page = get_page_by_path('client-login');
        if (!$login_page) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Atenção:</strong> A página de login ainda não foi criada. ';
            echo '<button type="button" class="button" id="create-login-page">Criar Página de Login</button>';
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Página de login:</strong> <a href="' . get_permalink($login_page->ID) . '" target="_blank">' . get_permalink($login_page->ID) . '</a>';
            echo '</p></div>';
        }
        
        // Botão para criar credenciais em massa
        echo '<div style="margin: 20px 0;">';
        echo '<button type="button" class="button button-primary" id="create-all-credentials">';
        echo '<span class="dashicons dashicons-admin-users" style="margin-top: 3px;"></span> Criar Credenciais para Todos os Clientes';
        echo '</button>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Cliente</th><th>Username</th><th>Acesso</th><th>Ações</th></tr></thead>';
        echo '<tbody>';
        
        // Primeiro, mostrar clientes com credenciais
        foreach ($clients as $client) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($client->client_name) . '</strong></td>';
            echo '<td>' . esc_html($client->username) . '</td>';
            echo '<td>' . ($client->has_access ? '<span style="color: green;">✓ Ativo</span>' : '<span style="color: red;">✗ Inativo</span>') . '</td>';
            echo '<td>';
            echo '<button type="button" class="button toggle-access" data-client-id="' . $client->id . '" data-current-access="' . $client->has_access . '">';
            echo $client->has_access ? 'Desativar' : 'Ativar';
            echo '</button> ';
            echo '<button type="button" class="button edit-credentials" data-client-id="' . $client->id . '" data-username="' . esc_attr($client->username) . '" data-client-name="' . esc_attr($client->client_name) . '">Editar</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        // Depois, mostrar clientes sem credenciais
        $existing_client_names = array_column($clients, 'client_name');
        foreach ($all_client_posts as $post) {
            if (!in_array($post->post_title, $existing_client_names)) {
                echo '<tr style="background-color: #fff3cd;">';
                echo '<td><strong>' . esc_html($post->post_title) . '</strong></td>';
                echo '<td><em>Sem credenciais</em></td>';
                echo '<td><span style="color: orange;">⚠ Sem acesso</span></td>';
                echo '<td>';
                echo '<button type="button" class="button button-primary create-single-credentials" data-client-name="' . esc_attr($post->post_title) . '">Criar Credenciais</button>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody></table>';
        
        // Modal para editar credenciais
        echo '<div id="edit-credentials-modal" style="display: none;">
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; min-width: 400px;">
                    <h3>Editar Credenciais do Cliente</h3>
                    <form id="edit-credentials-form">
                        <input type="hidden" id="edit-client-id" name="client_id">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="edit-client-name">Cliente</label></th>
                                <td><input type="text" id="edit-client-name" name="client_name" class="regular-text" readonly style="background: #f0f0f0;"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="edit-username">Usuário</label></th>
                                <td><input type="text" id="edit-username" name="username" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="edit-password">Nova Senha</label></th>
                                <td><input type="password" id="edit-password" name="password" class="regular-text" placeholder="Deixe em branco para manter atual"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="edit-has-access">Acesso</label></th>
                                <td><label><input type="checkbox" id="edit-has-access" name="has_access" value="1"> Ativo</label></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="button" class="button button-secondary" onclick="closeEditModal()">Cancelar</button>
                            <button type="submit" class="button button-primary">Salvar</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>';
        
        echo '</div>';
        
        // JavaScript para toggle e criar página
        echo '<script>
        jQuery(document).ready(function($) {
            $(".toggle-access").click(function() {
                var button = $(this);
                var clientId = button.data("client-id");
                var currentAccess = button.data("current-access");
                var newAccess = currentAccess ? 0 : 1;
                
                $.post(ajaxurl, {
                    action: "f2f_toggle_client_access",
                    client_id: clientId,
                    new_access: newAccess,
                    nonce: "' . wp_create_nonce('f2f_toggle_access') . '"
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert("Erro ao alterar acesso.");
                    }
                });
            });
            
            $("#create-login-page").click(function() {
                var button = $(this);
                button.prop("disabled", true).text("Criando...");
                
                $.post(ajaxurl, {
                    action: "f2f_create_login_page",
                    nonce: "' . wp_create_nonce('f2f_create_login_page') . '"
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert("Erro ao criar página: " + response.data);
                        button.prop("disabled", false).text("Criar Página de Login");
                    }
                });
            });
            
            $("#create-all-credentials").click(function() {
                var button = $(this);
                if (!confirm("Isso criará credenciais para todos os clientes que ainda não possuem. Continuar?")) {
                    return;
                }
                
                button.prop("disabled", true).text("Criando credenciais...");
                
                $.post(ajaxurl, {
                    action: "f2f_create_client_credentials",
                    nonce: "' . wp_create_nonce('f2f_create_client_credentials') . '"
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert("Erro ao criar credenciais: " + response.data);
                        button.prop("disabled", false).text("Criar Credenciais para Todos os Clientes");
                    }
                });
            });
            
            $(".create-single-credentials").click(function() {
                var button = $(this);
                var clientName = button.data("client-name");
                
                if (!confirm("Criar credenciais para o cliente \'" + clientName + "\'?")) {
                    return;
                }
                
                button.prop("disabled", true).text("Criando...");
                
                $.post(ajaxurl, {
                    action: "f2f_create_single_client_credentials",
                    client_name: clientName,
                    nonce: "' . wp_create_nonce('f2f_create_single_client_credentials') . '"
                }, function(response) {
                    if (response.success) {
                        alert("Credenciais criadas com sucesso!\\nUsername: " + response.data.username + "\\nPassword: " + response.data.password);
                        location.reload();
                    } else {
                        alert("Erro ao criar credenciais: " + response.data);
                        button.prop("disabled", false).text("Criar Credenciais");
                    }
                });
            });
            
            $(".edit-credentials").click(function() {
                var clientId = $(this).data("client-id");
                var username = $(this).data("username");
                var clientName = $(this).data("client-name");
                
                $("#edit-client-id").val(clientId);
                $("#edit-client-name").val(clientName);
                $("#edit-username").val(username);
                $("#edit-password").val("");
                
                // Buscar dados atuais do cliente
                $.post(ajaxurl, {
                    action: "f2f_get_client_data",
                    client_id: clientId,
                    nonce: "' . wp_create_nonce('f2f_get_client_data') . '"
                }, function(response) {
                    if (response.success) {
                        $("#edit-has-access").prop("checked", response.data.has_access == 1);
                    }
                });
                
                $("#edit-credentials-modal").show();
            });
            
            $("#edit-credentials-form").submit(function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += "&action=f2f_update_client_credentials";
                formData += "&nonce=' . wp_create_nonce('f2f_update_client_credentials') . '";
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert("Credenciais atualizadas com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro ao atualizar credenciais: " + response.data);
                    }
                });
            });
        });
        
        function closeEditModal() {
            $("#edit-credentials-modal").hide();
        }
        </script>';
    }
    
    /**
     * Toggle do acesso do cliente
     */
    public function toggle_client_access() {
        if (!wp_verify_nonce($_POST['nonce'], 'f2f_toggle_access')) {
            wp_die('Erro de segurança');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        
        $client_id = intval($_POST['client_id']);
        $new_access = intval($_POST['new_access']);
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('has_access' => $new_access),
            array('id' => $client_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * Cria credenciais padrão para um cliente
     */
    public function create_client_credentials($client_name, $username = null, $password = null) {
        if (!$username) {
            $username = sanitize_title($client_name);
        }
        
        if (!$password) {
            $password = wp_generate_password(8, false);
        }
        
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'client_name' => $client_name,
                'username' => $username,
                'password' => wp_hash_password($password),
                'has_access' => 0 // Inativo por padrão
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            return array(
                'username' => $username,
                'password' => $password
            );
        }
        
        return false;
    }
    
    /**
     * AJAX para obter dados do cliente
     */
    public function get_client_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'f2f_get_client_data')) {
            wp_die('Erro de segurança');
        }
        
        $client_id = intval($_POST['client_id']);
        
        global $wpdb;
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $client_id
        ));
        
        if ($client) {
            wp_send_json_success(array(
                'has_access' => $client->has_access
            ));
        } else {
            wp_send_json_error('Cliente não encontrado');
        }
    }
    
    /**
     * AJAX para atualizar credenciais do cliente
     */
    public function update_client_credentials() {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'f2f_update_client_credentials')) {
            wp_die('Erro de segurança');
        }
        
        $client_id = intval($_POST['client_id']);
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $has_access = isset($_POST['has_access']) ? 1 : 0;
        
        global $wpdb;
        
        $update_data = array(
            'username' => $username,
            'has_access' => $has_access
        );
        
        $update_format = array('%s', '%d');
        
        // Atualizar senha apenas se fornecida
        if (!empty($password)) {
            $update_data['password'] = wp_hash_password($password);
            $update_format[] = '%s';
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $client_id),
            $update_format,
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Erro ao atualizar credenciais');
        }
    }
    
    /**
     * Verifica se um cliente específico está logado
     */
    public function is_client_logged_in($client_name = null) {
        if (!session_id()) {
            session_start();
        }
        
        $logged_in = isset($_SESSION['f2f_client_logged_in']) && $_SESSION['f2f_client_logged_in'];
        
        if ($client_name) {
            $correct_client = isset($_SESSION['f2f_client_name']) && $_SESSION['f2f_client_name'] === $client_name;
            return $logged_in && $correct_client;
        }
        
        return $logged_in;
    }
    
    /**
     * Obtém o nome do cliente logado
     */
    public function get_logged_client_name() {
        if (!session_id()) {
            session_start();
        }
        
        return isset($_SESSION['f2f_client_name']) ? $_SESSION['f2f_client_name'] : null;
    }
    
    /**
     * Faz logout do cliente
     */
    public function logout_client() {
        // Garantir que a sessão esteja iniciada
        if (!session_id()) {
            session_start();
        }
        
        // Log do logout para debug
        error_log('F2F Client Logout: Iniciando logout do cliente');
        
        // Limpar variáveis de sessão
        unset($_SESSION['f2f_client_logged_in']);
        unset($_SESSION['f2f_client_name']);
        unset($_SESSION['f2f_client_id']);
        
        // Limpar cookie de sessão se existir
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destruir sessão
        session_destroy();
        
        // Log de confirmação
        error_log('F2F Client Logout: Logout concluído, redirecionando para login');
        
        // Redirecionar para página de login
        wp_redirect(home_url('/client-login/'));
        exit;
    }
    
    /**
     * Verifica se o usuário está autenticado em todas as páginas
     */
    public function check_authentication() {
        // Evitar loop infinito - verificar se já estamos na página de login
        $current_path = $_SERVER['REQUEST_URI'];
        if (strpos($current_path, '/client-login') !== false) {
            return; // Já estamos na página de login, não redirecionar
        }
        
        // Páginas que não precisam de autenticação
        $excluded_pages = array(
            'wp-admin',
            'admin-ajax.php',
            'wp-login.php'
        );
        
        $is_excluded = false;
        foreach ($excluded_pages as $excluded) {
            if (strpos($current_path, $excluded) !== false) {
                $is_excluded = true;
                break;
            }
        }
        
        // Se é uma página excluída, não fazer nada
        if ($is_excluded) {
            return;
        }
        
        // Verificar se é admin logado
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return; // Admin pode acessar tudo
        }
        
        // Verificar se é cliente logado
        if ($this->is_client_logged_in()) {
            return; // Cliente logado pode acessar
        }
        
        // Se chegou até aqui, não está autenticado
        // Redirecionar para login
        wp_redirect(home_url('/client-login/?redirect=' . urlencode($current_path)));
        exit;
    }
}

// Inicializar a classe
F2F_Client_Auth::get_instance();
