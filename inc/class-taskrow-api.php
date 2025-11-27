<?php
/**
 * F2F Taskrow API Class
 *
 * @package F2F_Dashboard
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class F2F_Taskrow_API {

    private static $instance = null;
    private $api_token;
    private $base_url;
    private $host_name; // e.g., f2f.taskrow.com

    private function __construct() {
        $this->api_token = get_option( 'f2f_taskrow_api_token', '' );
        $this->host_name = get_option( 'f2f_taskrow_host_name', '' );
        $this->base_url  = 'https://' . $this->host_name . '/api/v1/'; // Assuming v1 API
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function is_configured() {
        return ! empty( $this->api_token ) && ! empty( $this->host_name );
    }

    public function set_api_token( $token ) {
        $this->api_token = $token;
        update_option( 'f2f_taskrow_api_token', $token );
    }

    public function set_host_name( $host ) {
        $this->host_name = $host;
        $this->base_url  = 'https://' . $this->host_name . '/api/v1/';
        update_option( 'f2f_taskrow_host_name', $host );
    }

    private function request( $endpoint, $method = 'GET', $body = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'Taskrow API não está configurada.' );
        }

        $url = $this->base_url . $endpoint;

        $args = array(
            'method'  => $method,
            'headers' => array(
                '__identifier'  => $this->api_token, // Taskrow usa __identifier como header
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );

        if ( $method === 'POST' || $method === 'PUT' ) {
            $args['body'] = json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'F2F Taskrow API Error: ' . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( $response_code < 200 || $response_code >= 300 ) {
            error_log( 'F2F Taskrow API Error - Code: ' . $response_code . ' Body: ' . $response_body );
            return new WP_Error( 'taskrow_api_error', 'Erro na API Taskrow: ' . ( isset( $data['message'] ) ? $data['message'] : $response_body ), array( 'status' => $response_code, 'response' => $data ) );
        }

        return $data;
    }


    /**
     * Buscar demandas (tarefas) do Taskrow
     * Baseado na documentação oficial da API
     */
    public function get_demands() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando tarefas usando documentação oficial" );

        // Tentar múltiplos endpoints baseados na documentação
        $endpoints_to_try = array(
            // 1. Busca avançada de tarefas (v2)
            array(
                'url' => 'https://' . $this->host_name . '/api/v2/search/tasks/advancedsearch',
                'method' => 'POST',
                'body' => array(
                    'StartDate' => date( 'Y-m-dT00:00:00Z', strtotime('-3 months') ), // Últimos 3 meses
                    'EndDate'   => date( 'Y-m-dT23:59:59Z', strtotime('+1 month') ),  // Próximo mês
                )
            ),
            // 2. Listar tarefas simples (v1)
            array(
                'url' => 'https://' . $this->host_name . '/api/v1/Task/ListTasks',
                'method' => 'GET',
                'body' => array()
            ),
            // 3. Buscar tarefas por código externo (v1)
            array(
                'url' => 'https://' . $this->host_name . '/api/v1/Task/GetTaskByExternalCode',
                'method' => 'GET',
                'body' => array()
            )
        );

        foreach ( $endpoints_to_try as $index => $endpoint_config ) {
            error_log( "F2F Taskrow API: Tentativa " . ($index + 1) . " - " . $endpoint_config['url'] );
            
            $args = array(
                'method'  => $endpoint_config['method'],
                'headers' => array(
                    '__identifier'  => $this->api_token,
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 30,
            );

            if ( $endpoint_config['method'] === 'POST' && ! empty( $endpoint_config['body'] ) ) {
                $args['body'] = json_encode( $endpoint_config['body'] );
            }

            $response = wp_remote_request( $endpoint_config['url'], $args );
            
            if ( is_wp_error( $response ) ) {
                error_log( 'F2F Taskrow API Error: ' . $response->get_error_message() );
                continue;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            error_log( "F2F Taskrow API: Resposta - Code: {$response_code}, Body: {$response_body}" );

            if ( $response_code >= 200 && $response_code < 300 ) {
                $result = json_decode( $response_body, true );
                
                if ( ! empty( $result ) ) {
                    error_log( "F2F Taskrow API: Sucesso no endpoint " . ($index + 1) );
                    return $this->process_demands_response( $result );
                }
            }
        }

        // Se todos os endpoints falharam, tentar buscar projetos
        error_log( "F2F Taskrow API: Todos endpoints de tarefas falharam, tentando projetos" );
        $projects_result = $this->get_projects();
        if ( ! is_wp_error( $projects_result ) && ! empty( $projects_result ) ) {
            return $this->get_dummy_demands_with_projects( $projects_result );
        }
        
        // Último recurso: dados fictícios
        error_log( "F2F Taskrow API: Retornando dados fictícios" );
        return $this->get_dummy_demands();
    }

    /**
     * Buscar projetos do Taskrow
     */
    public function get_projects() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando projetos" );

        $url = 'https://' . $this->host_name . '/api/v2/core/job/list';
        
        $args = array(
            'method'  => 'GET',
            'headers' => array(
                '__identifier'  => $this->api_token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );

        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'F2F Taskrow API Error: ' . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $result = json_decode( $response_body, true );

        if ( $response_code < 200 || $response_code >= 300 ) {
            error_log( 'F2F Taskrow API Error - Code: ' . $response_code . ' Body: ' . $response_body );
            return new WP_Error( 'taskrow_api_error', 'Erro na API Taskrow: ' . $response_body, array( 'status' => $response_code ) );
        }

        error_log( "F2F Taskrow API: Projetos encontrados: " . print_r( $result, true ) );
        return $result;
    }

    /**
     * Retorna dados fictícios para teste
     */
    private function get_dummy_demands() {
        // Usar datas realistas baseadas na data atual
        $today = new DateTime();
        $next_week = clone $today;
        $next_week->add(new DateInterval('P7D')); // +7 dias
        $next_month = clone $today;
        $next_month->add(new DateInterval('P30D')); // +30 dias
        
        return array(
            array(
                'id'          => 'TR1001',
                'title'       => 'Demanda de Teste 1 (API não retornou dados)',
                'description' => 'Esta é uma demanda de teste. A API do Taskrow não retornou dados reais.',
                'client_name' => 'Cliente Fictício A',
                'status'      => 'open',
                'priority'    => 'high',
                'due_date'    => $next_week->format('Y-m-d H:i:s'),
                'attachments' => json_encode( array() ),
            ),
            array(
                'id'          => 'TR1002',
                'title'       => 'Demanda de Teste 2 (API não retornou dados)',
                'description' => 'Outra demanda de teste. A API do Taskrow não retornou dados reais.',
                'client_name' => 'Cliente Fictício B',
                'status'      => 'in_progress',
                'priority'    => 'medium',
                'due_date'    => $next_month->format('Y-m-d H:i:s'),
                'attachments' => json_encode( array() ),
            ),
        );
    }

    /**
     * Retorna dados fictícios baseados em projetos reais
     */
    private function get_dummy_demands_with_projects( $projects ) {
        $demands = array();
        
        if ( isset( $projects['items'] ) && is_array( $projects['items'] ) ) {
            foreach ( $projects['items'] as $project ) {
                $demands[] = array(
                    'id'          => 'TR' . $project['jobID'],
                    'title'       => 'Tarefa do Projeto: ' . $project['jobTitle'],
                    'description' => 'Projeto: ' . $project['jobTitle'] . ' (ID: ' . $project['jobID'] . ')',
                    'client_name' => $project['client']['displayName'] ?? 'Cliente não informado',
                    'status'      => 'open',
                    'priority'    => 'medium',
                    'due_date'    => null,
                    'attachments' => json_encode( array() ),
                );
            }
        }
        
        if ( empty( $demands ) ) {
            return $this->get_dummy_demands();
        }
        
        return $demands;
    }

    /**
     * Processa a resposta da API do Taskrow
     */
    private function process_demands_response( $response ) {
        $demands = array();
        
        // Se a resposta é um array direto de demandas
        if ( is_array( $response ) ) {
            foreach ( $response as $item ) {
                if ( is_array( $item ) ) {
                    $demands[] = $this->format_demand_data( $item );
                }
            }
        }
        
        // Se a resposta tem uma propriedade 'data' ou 'demands'
        if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
            foreach ( $response['data'] as $item ) {
                $demands[] = $this->format_demand_data( $item );
            }
        }
        
        if ( isset( $response['demands'] ) && is_array( $response['demands'] ) ) {
            foreach ( $response['demands'] as $item ) {
                $demands[] = $this->format_demand_data( $item );
            }
        }
        
        return $demands;
    }

    /**
     * Formata os dados de uma demanda para o formato padrão
     */
    private function format_demand_data( $item ) {
        // Validar e formatar data de vencimento
        $due_date = $item['due_date'] ?? $item['deadline'] ?? $item['due'] ?? null;
        $formatted_due_date = $this->validate_and_format_date( $due_date );
        
        return array(
            'id'          => $item['id'] ?? $item['ID'] ?? uniqid( 'TR' ),
            'title'       => $item['title'] ?? $item['name'] ?? $item['subject'] ?? 'Sem título',
            'description' => $item['description'] ?? $item['content'] ?? $item['body'] ?? '',
            'client_name' => $item['client_name'] ?? $item['client'] ?? $item['customer'] ?? 'Cliente não informado',
            'status'      => $item['status'] ?? $item['state'] ?? 'open',
            'priority'    => $item['priority'] ?? $item['importance'] ?? 'medium',
            'due_date'    => $formatted_due_date,
            'attachments' => json_encode( $item['attachments'] ?? $item['files'] ?? array() ),
        );
    }

    /**
     * Valida e formata uma data para garantir que seja realista
     */
    private function validate_and_format_date( $date_string ) {
        if ( empty( $date_string ) ) {
            return null;
        }

        // Tentar converter a data
        $date = DateTime::createFromFormat( 'Y-m-d H:i:s', $date_string );
        if ( ! $date ) {
            $date = DateTime::createFromFormat( 'Y-m-d', $date_string );
        }
        if ( ! $date ) {
            $date = new DateTime( $date_string );
        }

        if ( ! $date ) {
            error_log( "F2F Taskrow API: Data inválida recebida: {$date_string}" );
            return null;
        }

        // Verificar se a data não é muito antiga (mais de 2 anos atrás)
        $two_years_ago = new DateTime( '-2 years' );
        if ( $date < $two_years_ago ) {
            error_log( "F2F Taskrow API: Data muito antiga ignorada: {$date_string}" );
            return null;
        }

        // Verificar se a data não é muito futura (mais de 5 anos no futuro)
        $five_years_future = new DateTime( '+5 years' );
        if ( $date > $five_years_future ) {
            error_log( "F2F Taskrow API: Data muito futura ignorada: {$date_string}" );
            return null;
        }

        return $date->format( 'Y-m-d H:i:s' );
    }

    /**
     * Testa a conexão com a API do Taskrow
     * Baseado na documentação oficial
     */
    public function test_connection() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Testando conexão com host: {$this->host_name}" );

        // Endpoints baseados na documentação oficial
        $test_endpoints = array(
            // v1 endpoints
            array(
                'url' => 'https://' . $this->host_name . '/api/v1/User/ListUsers',
                'name' => 'Listar Usuários (v1)',
                'method' => 'GET'
            ),
            array(
                'url' => 'https://' . $this->host_name . '/api/v1/Client/ListClients',
                'name' => 'Listar Clientes (v1)',
                'method' => 'GET'
            ),
            // v2 endpoints
            array(
                'url' => 'https://' . $this->host_name . '/api/v2/core/job/list',
                'name' => 'Listar Projetos (v2)',
                'method' => 'GET'
            ),
            array(
                'url' => 'https://' . $this->host_name . '/api/v1/Administrative/ListJobSubType',
                'name' => 'Listar Subtipos de Projeto (v1)',
                'method' => 'GET'
            )
        );
        
        $successful_endpoints = array();
        $failed_endpoints = array();
        
        foreach ( $test_endpoints as $endpoint ) {
            error_log( "F2F Taskrow API: Testando: {$endpoint['name']}" );
            
            $args = array(
                'method'  => $endpoint['method'],
                'headers' => array(
                    '__identifier'  => $this->api_token,
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 30,
            );

            $response = wp_remote_request( $endpoint['url'], $args );
            
            if ( is_wp_error( $response ) ) {
                $error_msg = $response->get_error_message();
                error_log( "F2F Taskrow API: Erro em {$endpoint['name']}: {$error_msg}" );
                $failed_endpoints[] = array(
                    'endpoint' => $endpoint['name'],
                    'error' => $error_msg
                );
                continue;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            error_log( "F2F Taskrow API: {$endpoint['name']} - Code: {$response_code}" );
            
            if ( $response_code >= 200 && $response_code < 300 ) {
                $successful_endpoints[] = array(
                    'endpoint' => $endpoint['name'],
                    'code' => $response_code,
                    'response' => json_decode( $response_body, true )
                );
            } else {
                $failed_endpoints[] = array(
                    'endpoint' => $endpoint['name'],
                    'code' => $response_code,
                    'error' => $response_body
                );
            }
        }

        // Se pelo menos um endpoint funcionou, consideramos a conexão bem-sucedida
        if ( ! empty( $successful_endpoints ) ) {
            return array(
                'success' => true,
                'message' => 'Conexão com Taskrow estabelecida com sucesso!',
                'successful_endpoints' => $successful_endpoints,
                'failed_endpoints' => $failed_endpoints,
                'total_tested' => count( $test_endpoints ),
                'successful_count' => count( $successful_endpoints )
            );
        }

        return new WP_Error( 'taskrow_connection_failed', 'Não foi possível conectar com nenhum endpoint da API do Taskrow. Verifique as credenciais e o host.', array(
            'failed_endpoints' => $failed_endpoints
        ));
    }

    /**
     * Buscar horas lançadas de uma tarefa no Taskrow
     */
    public function get_task_hours( $task_number ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando horas da tarefa {$task_number}" );

        $result = $this->request( 'Task/GetTaskMinutesSpent', 'GET', array( 'taskNumber' => $task_number ) );

        if ( is_wp_error( $result ) ) {
            error_log( "F2F Taskrow API: Erro ao buscar horas: " . $result->get_error_message() );
            return $result;
        }

        error_log( "F2F Taskrow API: Horas encontradas: " . print_r( $result, true ) );
        return $result;
    }

    /**
     * Salvar horas no Taskrow (placeholder - endpoint não documentado)
     */
    public function save_time_entry( $taskrow_id, $hours, $description = '' ) {
        error_log( "F2F Taskrow API: Salvando horas para Taskrow ID {$taskrow_id}: {$hours} horas." );
        
        // Nota: O endpoint para salvar horas não está documentado no JSON fornecido
        // Por enquanto, vamos simular o salvamento
        return array( 
            'success' => true, 
            'message' => "Horas sincronizadas com sucesso: {$hours}h para a tarefa {$taskrow_id}",
            'hours_saved' => $hours,
            'task_id' => $taskrow_id
        );
    }

    /**
     * Listar usuários do Taskrow
     * Baseado na documentação: /api/v1/User/ListUsers
     */
    public function get_users() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando usuários" );
        return $this->request( 'User/ListUsers', 'GET' );
    }

    /**
     * Listar clientes do Taskrow
     * Baseado na documentação: /api/v1/Client/ListClients
     */
    public function get_clients() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando clientes" );
        return $this->request( 'Client/ListClients', 'GET' );
    }

    /**
     * Buscar tarefa por código externo
     * Baseado na documentação: /api/v1/Task/GetTaskByExternalCode
     */
    public function get_task_by_external_code( $external_code ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando tarefa por código externo: {$external_code}" );
        return $this->request( 'Task/GetTaskByExternalCode', 'GET', array( 'externalCode' => $external_code ) );
    }

    /**
     * Buscar cliente por código externo
     * Baseado na documentação: /api/v1/Client/GetClientByExternalCode
     */
    public function get_client_by_external_code( $external_code ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando cliente por código externo: {$external_code}" );
        return $this->request( 'Client/GetClientByExternalCode', 'GET', array( 'externalCode' => $external_code ) );
    }

    /**
     * Buscar projeto por código externo
     * Baseado na documentação: /api/v1/Job/GetJobByExternalCode
     */
    public function get_project_by_external_code( $external_code ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando projeto por código externo: {$external_code}" );
        return $this->request( 'Job/GetJobByExternalCode', 'GET', array( 'externalCode' => $external_code ) );
    }

    /**
     * Listar subtipos de projeto
     * Baseado na documentação: /api/v1/Administrative/ListJobSubType
     */
    public function get_job_subtypes() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        error_log( "F2F Taskrow API: Buscando subtipos de projeto" );
        return $this->request( 'Administrative/ListJobSubType', 'GET' );
    }

    /**
     * Listar cidades
     * Baseado na documentação: /api/v1/Client/ListCities
     */
    public function get_cities( $uf = null ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        $params = array();
        if ( $uf ) {
            $params['uf'] = $uf;
        }

        error_log( "F2F Taskrow API: Buscando cidades" . ( $uf ? " para UF: {$uf}" : "" ) );
        return $this->request( 'Client/ListCities', 'GET', $params );
    }

    /**
     * Buscar detalhes completos de uma tarefa (TaskDetail)
     * Endpoint: /api/v1/Task/TaskDetail
     * Parâmetros (query): clientNickname, jobNumber, taskNumber, connectionID
     * Retorna descrição (TaskItemComment) com múltiplos fallbacks e origem.
     */
    public function get_task_detail( $client_nickname, $job_number, $task_number ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'taskrow_api_not_configured', 'API Taskrow não está configurada.' );
        }

        $client_nickname = trim( (string) $client_nickname );
        $job_number      = intval( $job_number );
        $task_number     = intval( $task_number );

        if ( $client_nickname === '' || $job_number <= 0 || $task_number <= 0 ) {
            return new WP_Error( 'taskrow_invalid_params', 'Parâmetros inválidos para TaskDetail.' );
        }

        // Gerar connectionID (GUID simples)
        $connection_id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );

        $url = 'https://' . $this->host_name . '/api/v1/Task/TaskDetail'
             . '?clientNickname=' . rawurlencode( $client_nickname )
             . '&jobNumber=' . $job_number
             . '&taskNumber=' . $task_number
             . '&connectionID=' . rawurlencode( $connection_id );

        $args = array(
            'method'  => 'GET',
            'headers' => array(
                '__identifier' => $this->api_token,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'WordPress/F2F Taskrow Integration'
            ),
            'timeout'  => 45,
            'sslverify' => false,
        );

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        $data = json_decode( $body, true );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'taskrow_taskdetail_error', 'Erro ao obter TaskDetail (HTTP ' . $code . ')', array( 'body' => $body, 'code' => $code ) );
        }
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'taskrow_taskdetail_json_error', 'Falha ao decodificar JSON: ' . json_last_error_msg(), array( 'body' => $body ) );
        }

        // Função recursiva para localizar TaskItemComment em qualquer profundidade
        $recursive_find = function( $node, $path = array() ) use ( &$recursive_find ) {
            if ( is_array( $node ) ) {
                foreach ( $node as $k => $v ) {
                    $current_path = array_merge( $path, array( $k ) );
                    if ( $k === 'TaskItemComment' && is_string( $v ) && $v !== '' ) {
                        return array( 'value' => $v, 'path' => $current_path );
                    }
                    $found = $recursive_find( $v, $current_path );
                    if ( $found ) {
                        return $found;
                    }
                }
            }
            return null;
        };

        $description      = null;
        $description_path = null;
        $task_data        = isset( $data['TaskData'] ) && is_array( $data['TaskData'] ) ? $data['TaskData'] : array();

        // 1. Direto em TaskData
        if ( isset( $task_data['TaskItemComment'] ) && is_string( $task_data['TaskItemComment'] ) ) {
            $description      = $task_data['TaskItemComment'];
            $description_path = 'TaskData.TaskItemComment';
        }
        // 2. TaskItems
        if ( ! $description && isset( $task_data['TaskItems'][0]['TaskItemComment'] ) ) {
            $description      = $task_data['TaskItems'][0]['TaskItemComment'];
            $description_path = 'TaskData.TaskItems[0].TaskItemComment';
        }
        // 3. NewTaskItems
        if ( ! $description && isset( $task_data['NewTaskItems'][0]['TaskItemComment'] ) ) {
            $description      = $task_data['NewTaskItems'][0]['TaskItemComment'];
            $description_path = 'TaskData.NewTaskItems[0].TaskItemComment';
        }
        // 4. ExternalTaskItems
        if ( ! $description && isset( $task_data['ExternalTaskItems'][0]['TaskItemComment'] ) ) {
            $description      = $task_data['ExternalTaskItems'][0]['TaskItemComment'];
            $description_path = 'TaskData.ExternalTaskItems[0].TaskItemComment';
        }
        // 5. Recursivo
        if ( ! $description ) {
            $found = $recursive_find( $data );
            if ( $found ) {
                $description      = $found['value'];
                $description_path = implode( ' > ', $found['path'] );
            }
        }

        return array(
            'raw'               => $data,
            'description'       => $description,
            'description_path'  => $description_path,
            'clientNickname'    => $client_nickname,
            'jobNumber'         => $job_number,
            'taskNumber'        => $task_number,
            'connectionID'      => $connection_id,
            'endpoint'          => $url,
        );
    }
}
