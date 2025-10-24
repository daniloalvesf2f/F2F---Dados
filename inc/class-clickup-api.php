<?php
/**
 * Classe de Integração com ClickUp API
 *
 * @package F2FDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class F2F_ClickUp_API
 * 
 * Gerencia todas as interações com a API do ClickUp
 */
class F2F_ClickUp_API {
    
    /**
     * URL base da API do ClickUp
     */
    const API_BASE_URL = 'https://api.clickup.com/api/v2';
    
    /**
     * Instância única da classe (singleton)
     *
     * @var F2F_ClickUp_API
     */
    private static $instance = null;
    
    /**
     * API Token do ClickUp
     *
     * @var string
     */
    private $api_token;
    
    /**
     * Obtém a instância única da classe
     *
     * @return F2F_ClickUp_API
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        $this->api_token = get_option( 'f2f_clickup_api_token', '' );
    }
    
    /**
     * Define o token da API
     *
     * @param string $token Token da API
     */
    public function set_token( $token ) {
        $this->api_token = $token;
        update_option( 'f2f_clickup_api_token', $token );
    }
    
    /**
     * Verifica se a API está configurada
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->api_token );
    }
    
    /**
     * Faz uma requisição para a API do ClickUp
     *
     * @param string $endpoint Endpoint da API
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param array $data Dados para enviar
     * @return array|WP_Error Resposta da API ou erro
     */
    private function request( $endpoint, $method = 'GET', $data = array() ) {
        // Debug: Verificar token
        error_log( 'F2F ClickUp API Debug - Token atual: ' . ( ! empty( $this->api_token ) ? 'SIM (primeiros 10 chars: ' . substr( $this->api_token, 0, 10 ) . '...)' : 'NÃO' ) );
        
        if ( ! $this->is_configured() ) {
            error_log( 'F2F ClickUp API Debug - API não configurada' );
            return new WP_Error( 'not_configured', 'ClickUp API não está configurada. Configure o token em Configurações > ClickUp API.' );
        }
        
        $url = self::API_BASE_URL . $endpoint;
        
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => $this->api_token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15, // Reduzido para 15 segundos
        );
        
        if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
            $args['body'] = wp_json_encode( $data );
        }
        
        // Debug: Log da requisição
        error_log( 'F2F ClickUp API Debug - URL: ' . $url );
        error_log( 'F2F ClickUp API Debug - Method: ' . $method );
        error_log( 'F2F ClickUp API Debug - Args: ' . print_r( $args, true ) );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'F2F ClickUp API Debug - WP Error: ' . $response->get_error_message() );
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        // Debug: Log da resposta
        error_log( 'F2F ClickUp API Debug - Response Code: ' . $code );
        error_log( 'F2F ClickUp API Debug - Response Body: ' . $body );
        
        if ( $code < 200 || $code >= 300 ) {
            $error_data = json_decode( $body, true );
            $error_message = isset( $error_data['err'] ) ? $error_data['err'] : 'Erro desconhecido na API do ClickUp';
            
            error_log( 'F2F ClickUp API Debug - API Error: ' . $error_message );
            return new WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
        }
        
        return json_decode( $body, true );
    }
    
    /**
     * Testa a conexão com a API
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        return $this->request( '/user' );
    }
    
    /**
     * Obtém os workspaces (teams) do usuário
     *
     * @return array|WP_Error
     */
    public function get_workspaces() {
        $response = $this->request( '/team' );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['teams'] ) ? $response['teams'] : array();
    }
    
    /**
     * Obtém os spaces de um workspace
     *
     * @param string $team_id ID do workspace
     * @return array|WP_Error
     */
    public function get_spaces( $team_id ) {
        $response = $this->request( "/team/{$team_id}/space" );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['spaces'] ) ? $response['spaces'] : array();
    }
    
    /**
     * Obtém as listas de um space
     *
     * @param string $space_id ID do space
     * @return array|WP_Error
     */
    public function get_lists( $space_id ) {
        $response = $this->request( "/space/{$space_id}/list" );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['lists'] ) ? $response['lists'] : array();
    }
    
    /**
     * Obtém as listas de uma pasta (folder)
     *
     * @param string $folder_id ID da pasta
     * @return array|WP_Error
     */
    public function get_folder_lists( $folder_id ) {
        $response = $this->request( "/folder/{$folder_id}/list" );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['lists'] ) ? $response['lists'] : array();
    }
    
    /**
     * Obtém os membros de um workspace
     *
     * @param string $team_id ID do workspace
     * @return array|WP_Error
     */
    public function get_team_members( $team_id ) {
        $response = $this->request( "/team/{$team_id}" );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // Debug: Log da resposta completa
        error_log( 'F2F ClickUp API Debug - Team response: ' . print_r( $response, true ) );
        
        if ( isset( $response['team']['members'] ) ) {
            error_log( 'F2F ClickUp API Debug - Members found: ' . count( $response['team']['members'] ) );
            return $response['team']['members'];
        } else {
            error_log( 'F2F ClickUp API Debug - No members found in response structure' );
            return array();
        }
    }
    
    /**
     * Obtém os membros de um workspace (método alternativo)
     *
     * @param string $team_id ID do workspace
     * @return array|WP_Error
     */
    public function get_team_members_alt( $team_id ) {
        // Tenta buscar membros usando endpoint alternativo
        $response = $this->request( "/team/{$team_id}/member" );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'F2F ClickUp API Debug - Alternative endpoint failed: ' . $response->get_error_message() );
            return $response;
        }
        
        // Debug: Log da resposta alternativa
        error_log( 'F2F ClickUp API Debug - Alternative response: ' . print_r( $response, true ) );
        
        if ( isset( $response['members'] ) ) {
            error_log( 'F2F ClickUp API Debug - Alternative members found: ' . count( $response['members'] ) );
            return $response['members'];
        } else {
            error_log( 'F2F ClickUp API Debug - No members found in alternative response' );
            return array();
        }
    }
    
    /**
     * Cria uma nova tarefa no ClickUp
     *
     * @param string $list_id ID da lista onde criar a tarefa
     * @param array $task_data Dados da tarefa
     * @return array|WP_Error
     */
    public function create_task( $list_id, $task_data ) {
        $default_data = array(
            'name'        => '',
            'description' => '',
            'assignees'   => array(),
            'tags'        => array(),
            'status'      => null,
            'priority'    => null,
            'due_date'    => null,
            'start_date'  => null,
            'notify_all'  => false,
        );
        
        $task_data = wp_parse_args( $task_data, $default_data );
        
        // Remove campos vazios
        $task_data = array_filter( $task_data, function( $value ) {
            return null !== $value && '' !== $value;
        } );
        
        // Converte datas para timestamp em milissegundos
        if ( isset( $task_data['due_date'] ) && ! empty( $task_data['due_date'] ) ) {
            $timestamp = strtotime( $task_data['due_date'] );
            if ( $timestamp !== false && $timestamp > 0 ) {
                $task_data['due_date'] = $timestamp * 1000;
            } else {
                error_log( "F2F ClickUp API: Data de vencimento inválida: " . $task_data['due_date'] );
                unset( $task_data['due_date'] ); // Remove data inválida
            }
        }
        
        if ( isset( $task_data['start_date'] ) && ! empty( $task_data['start_date'] ) ) {
            $timestamp = strtotime( $task_data['start_date'] );
            if ( $timestamp !== false && $timestamp > 0 ) {
                $task_data['start_date'] = $timestamp * 1000;
            } else {
                error_log( "F2F ClickUp API: Data de início inválida: " . $task_data['start_date'] );
                unset( $task_data['start_date'] ); // Remove data inválida
            }
        }
        
        return $this->request( "/list/{$list_id}/task", 'POST', $task_data );
    }
    
    /**
     * Atualiza uma tarefa existente
     *
     * @param string $task_id ID da tarefa
     * @param array $task_data Dados para atualizar
     * @return array|WP_Error
     */
    public function update_task( $task_id, $task_data ) {
        return $this->request( "/task/{$task_id}", 'PUT', $task_data );
    }
    
    /**
     * Obtém uma tarefa específica
     *
     * @param string $task_id ID da tarefa
     * @return array|WP_Error
     */
    public function get_task( $task_id ) {
        return $this->request( "/task/{$task_id}" );
    }
    
    /**
     * Obtém o tempo trackado de uma tarefa
     *
     * @param string $task_id ID da tarefa
     * @return array|WP_Error
     */
    public function get_task_time( $task_id ) {
        return $this->request( "/task/{$task_id}/time" );
    }
    
    /**
     * Obtém as subtarefas de uma tarefa
     *
     * @param string $task_id ID da tarefa
     * @return array|WP_Error
     */
    public function get_subtasks( $task_id ) {
        return $this->request( "/task/{$task_id}/subtask" );
    }
    
    /**
     * Deleta uma tarefa
     *
     * @param string $task_id ID da tarefa
     * @return array|WP_Error
     */
    public function delete_task( $task_id ) {
        return $this->request( "/task/{$task_id}", 'DELETE' );
    }
    
    /**
     * Obtém os status de uma lista
     *
     * @param string $list_id ID da lista
     * @return array|WP_Error
     */
    public function get_list_statuses( $list_id ) {
        $response = $this->request( "/list/{$list_id}" );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['statuses'] ) ? $response['statuses'] : array();
    }
    
    /**
     * Adiciona um comentário a uma tarefa
     *
     * @param string $task_id ID da tarefa
     * @param string $comment_text Texto do comentário
     * @return array|WP_Error
     */
    public function add_comment( $task_id, $comment_text ) {
        return $this->request( "/task/{$task_id}/comment", 'POST', array(
            'comment_text' => $comment_text,
        ) );
    }
    
    /**
     * Faz upload de um anexo para uma tarefa
     *
     * @param string $task_id ID da tarefa
     * @param string $file_path Caminho do arquivo
     * @return array|WP_Error
     */
    public function upload_attachment( $task_id, $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', 'Arquivo não encontrado' );
        }
        
        $url = self::API_BASE_URL . "/task/{$task_id}/attachment";
        
        $boundary = wp_generate_password( 24, false );
        $file_contents = file_get_contents( $file_path );
        $filename = basename( $file_path );
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"attachment\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: " . mime_content_type( $file_path ) . "\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => $this->api_token,
                'Content-Type'  => "multipart/form-data; boundary={$boundary}",
            ),
            'body'    => $body,
            'timeout' => 60,
        );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'upload_failed', 'Falha ao fazer upload do arquivo' );
        }
        
        return json_decode( $body, true );
    }
    
    /**
     * Busca tarefas com filtros
     *
     * @param string $list_id ID da lista
     * @param array $filters Filtros de busca
     * @return array|WP_Error
     */
    public function search_tasks( $list_id, $filters = array() ) {
        $query_params = http_build_query( $filters );
        $endpoint = "/list/{$list_id}/task" . ( $query_params ? "?{$query_params}" : '' );
        
        $response = $this->request( $endpoint );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['tasks'] ) ? $response['tasks'] : array();
    }
    
    /**
     * Obtém custom fields de uma lista
     *
     * @param string $list_id ID da lista
     * @return array|WP_Error
     */
    public function get_custom_fields( $list_id ) {
        $response = $this->request( "/list/{$list_id}/field" );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['fields'] ) ? $response['fields'] : array();
    }
    
    /**
     * Define um custom field em uma tarefa
     *
     * @param string $task_id ID da tarefa
     * @param string $field_id ID do campo customizado
     * @param mixed $value Valor do campo
     * @return array|WP_Error
     */
    public function set_custom_field( $task_id, $field_id, $value ) {
        return $this->request( "/task/{$task_id}/field/{$field_id}", 'POST', array(
            'value' => $value,
        ) );
    }
}

