<?php
/**
 * Classe para importação e processamento de CSV
 *
 * @package F2FDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Classe F2F_CSV_Importer
 * 
 * Gerencia a importação e processamento de dados CSV do ClickUp.
 */
class F2F_CSV_Importer {
    /**
     * Nome da tabela no banco de dados
     *
     * @var string
     */
    private $table_name;

    /**
     * Instância única da classe (singleton)
     *
     * @var F2F_CSV_Importer
     */
    private static $instance = null;

    /**
     * Construtor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'f2f_clickup_data';
    }

    /**
     * Obtém a instância única da classe
     *
     * @return F2F_CSV_Importer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Importa dados de um arquivo CSV
     *
     * @param string $path Caminho para o arquivo CSV
     * @return array Resultado da importação com contagem de linhas
     */
    public function import_from_path( $path ) {
        $result = array(
            'success' => false,
            'total_lines' => 0,
            'imported_lines' => 0,
            'error' => '',
            'error_count' => 0
        );
        
        if ( ! file_exists( $path ) ) {
            error_log('F2F CSV Importer: Arquivo não existe: ' . $path);
            $result['error'] = 'Arquivo não encontrado: ' . $path;
            return $result;
        }

        // Verifica o tamanho do arquivo
        $filesize = filesize($path);
        error_log('F2F CSV Importer: Tamanho do arquivo: ' . $filesize . ' bytes');
        
        if ($filesize <= 0) {
            error_log('F2F CSV Importer: Arquivo vazio');
            $result['error'] = 'Arquivo CSV vazio';
            return $result;
        }

        error_log('F2F CSV Importer: Caminho do arquivo recebido: ' . $path);
        $handle = fopen( $path, 'r' );
        if ( ! $handle ) {
            error_log('F2F CSV Importer: Não foi possível abrir o arquivo: ' . $path);
            $result['error'] = 'Não foi possível abrir o arquivo: ' . $path;
            return $result;
        }
        // Lê cabeçalhos
        $headers = fgetcsv( $handle );
        error_log('F2F CSV Importer: Cabeçalhos lidos: ' . print_r($headers, true));
        if ( ! is_array( $headers ) ) {
            error_log('F2F CSV Importer: Cabeçalhos inválidos no arquivo CSV');
            fclose( $handle );
            $result['error'] = 'Arquivo CSV inválido ou vazio';
            return $result;
        }
        // Normaliza nomes dos cabeçalhos para comparação case-insensitive
        $headers_norm = array();
        foreach ($headers as $idx => $h) {
            if (empty(trim($h)) || is_numeric(trim($h))) {
                $headers_norm[$idx] = (string)$idx;
            } else {
                $headers_norm[$idx] = strtolower(trim($h));
            }
        }
        error_log('F2F CSV Importer: Cabeçalhos normalizados: ' . implode(', ', $headers_norm));
        // Processa linhas
        $row_count = 0;
        $success_count = 0;
        $error_count = 0;
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            error_log('F2F CSV Importer: Linha ' . $line . ': ' . print_r($row, true));
            $row_count++;
            $line++;
            // Pula linhas vazias
            if ( empty( $row ) || count( array_filter( $row ) ) === 0 ) {
                continue;
            }
            // Verifica se o número de colunas corresponde ao número de cabeçalhos
            if (count($row) !== count($headers_norm)) {
                error_log('F2F CSV Importer: Linha ' . $row_count . ' tem ' . count($row) . ' colunas, mas esperava ' . count($headers_norm));
                // Ajusta o tamanho do array para corresponder aos cabeçalhos
                if (count($row) > count($headers_norm)) {
                    $row = array_slice($row, 0, count($headers_norm));
                } else {
                    $row = array_pad($row, count($headers_norm), '');
                }
            }
            $data_assoc = array();
            foreach ( $headers_norm as $idx => $hn ) {
                $data_assoc[ $hn ] = isset( $row[ $idx ] ) ? $row[ $idx ] : '';
            }
            // Processa os dados da linha
            $processed_data = $this->process_row_data( $data_assoc );
            // Insere ou atualiza no banco de dados
            $result_row = $this->insert_or_update_row( $processed_data );
            if ($result_row) {
                $success_count++;
            } else {
                $error_count++;
            }
            // Log para as primeiras 5 linhas para debug
            if ($row_count <= 5) {
                error_log('F2F CSV Importer: Linha ' . $row_count . ' processada. Resultado: ' . ($result_row ? 'Sucesso' : 'Falha'));
                error_log('F2F CSV Importer: Dados processados: ' . json_encode($processed_data));
            }
        }
        fclose( $handle );
        error_log('F2F CSV Importer: Importação concluída. Total de linhas: ' . $row_count . ', Inseridas com sucesso: ' . $success_count . ', Erros: ' . $error_count);
        $result['success'] = $success_count > 0;
        $result['total_lines'] = $row_count;
        $result['imported_lines'] = $success_count;
        $result['error_count'] = $error_count;
        return $result;
    }

    /**
     * Processa os dados de uma linha do CSV
     *
     * @param array $data_assoc Dados associativos da linha
     * @return array Dados processados
     */
    private function process_row_data( $data_assoc ) {
        // Log para debug
        error_log('F2F CSV Importer: Processando linha com dados: ' . wp_json_encode($data_assoc));
        
        // Campos específicos para o formato do ClickUp
        $entry_id = $this->guess_field( $data_assoc, array( 'time entry id', 'entry id' ) );
        $task_id  = $this->guess_field( $data_assoc, array( 'task id', 'custom task id', 'parent task id' ) );
        $name     = $this->guess_field( $data_assoc, array( 'task name', 'name', 'title' ) );
        $status   = $this->guess_field( $data_assoc, array( 'task status', 'status' ) );
        $assignee = $this->guess_field( $data_assoc, array( 'username', 'assignee', 'user' ) );
        // Preferir cabeçalho exato "cliente"; se não houver, usar projeto/list name como fallback
        $client   = $this->get_exact_field( $data_assoc, array( 'cliente' ) );
        $project  = $this->guess_field( $data_assoc, array( 'list name', 'project' ) );

        // Datas: preferir campo de texto quando disponível; caso contrário tratar milissegundos
        $start_date = $this->parse_date_candidates( $data_assoc, array( 
            'start date text', 'start date', 'date created text', 'date created'
        ) );
        
        $due_date = $this->parse_date_candidates( $data_assoc, array( 
            'due date text', 'due date', 'due', 'deadline'
        ) );
        
        // Data de execução: quando o time tracking foi executado (coluna "Start" do CSV)
        // Esta é a data REAL de quando o trabalho foi feito
        $execution_date = $this->parse_date_candidates( $data_assoc, array( 
            'start', 'start text', 'Start', 'Start Text'
        ) );
        
        // Debug: Log da data de execução encontrada
        if ( ! empty( $execution_date ) ) {
            error_log( 'F2F CSV Importer: Execution date encontrada: ' . $execution_date . ' para entry_id: ' . $entry_id );
        } else {
            error_log( 'F2F CSV Importer: Execution date NÃO encontrada para entry_id: ' . $entry_id . ' - Dados disponíveis: ' . print_r( array_keys( $data_assoc ), true ) );
        }

        // Durações em segundos (converte milissegundos para segundos)
        // Tempo por entrada: usar apenas cabeçalhos exatos para evitar usar totais agregados
        $time_tracked_seconds = $this->parse_duration_seconds(
            $this->get_exact_field( $data_assoc, array( 'time tracked' ) ),
            $this->get_exact_field( $data_assoc, array( 'time tracked text' ) )
        );
        
        $task_time_spent_seconds = $this->parse_duration_seconds(
            $this->get_exact_field( $data_assoc, array( 'task time spent' ) ),
            $this->get_exact_field( $data_assoc, array( 'task time spent text' ) )
        );
        
        $user_period_time_spent_seconds = $this->parse_duration_seconds(
            $this->get_exact_field( $data_assoc, array( 'user period time spent' ) ),
            $this->get_exact_field( $data_assoc, array( 'user period time spent text' ) )
        );

        // Processa campos numéricos
        $points = $this->guess_field( $data_assoc, array( 'points', 'story points', 'estimate', 'time estimate', '29', '30' ) );
        $points = is_numeric( $points ) ? floatval( $points ) : 0;

        $raw_json = wp_json_encode( $data_assoc );

        // Preferir sempre o ID da entrada de tempo (entry_id) para evitar colapsar várias entradas do mesmo task.
        // Se não houver entry_id, gerar um ID único baseado no task_id + hash da linha; se nem task_id houver, usar hash da linha.
        if ( ! empty( $entry_id ) ) {
            error_log('F2F CSV Importer: ID da entrada encontrado: ' . $entry_id);
        } elseif ( ! empty( $task_id ) ) {
            $entry_id = 'task_' . $task_id . '_' . substr( md5( $raw_json ), 0, 8 );
            error_log('F2F CSV Importer: Sem entry_id; usando task_id + hash: ' . $entry_id);
        } else {
            $entry_id = 'row_' . md5( $raw_json );
            error_log('F2F CSV Importer: Sem entry_id e sem task_id; gerando ID por hash: ' . $entry_id);
        }

        // Garante que temos pelo menos um nome de tarefa
        if (empty($name)) {
            $name = 'Tarefa sem nome - ' . substr($entry_id, 0, 8);
            error_log('F2F CSV Importer: Nome não encontrado, gerando nome: ' . $name);
        }

        $result = array(
            'entry_id' => $entry_id,
            'task_id' => $task_id,
            'name' => $name,
            'status' => $status,
            'assignee' => $assignee,
            'start_date' => $start_date,
            'due_date' => $due_date,
            'execution_date' => $execution_date,
            'client' => !empty($client) ? $client : $project,
            'project' => $project,
            'points' => $points,
            'time_tracked_seconds' => $time_tracked_seconds,
            'task_time_spent_seconds' => $task_time_spent_seconds,
            'user_period_time_spent_seconds' => $user_period_time_spent_seconds,
            'raw' => $raw_json,
        );
        
        // Log dos dados processados
        error_log('F2F CSV Importer: Dados processados: ' . wp_json_encode($result));
        
        return $result;
    }

    /**
     * Insere ou atualiza uma linha no banco de dados
     *
     * @param array $data Dados a serem inseridos/atualizados
     * @return bool Sucesso ou falha
     */
    private function insert_or_update_row( $data ) {
        global $wpdb;
        
        // Verifica se os dados essenciais estão presentes
        if (empty($data['entry_id'])) {
            error_log('F2F CSV Importer: Falha ao inserir linha - entry_id vazio');
            return false;
        }
        
        // Sanitiza os dados para evitar problemas de formato
        $sanitized_data = array(
            'entry_id' => $data['entry_id'],
            'task_id' => isset($data['task_id']) ? $data['task_id'] : '',
            'name' => isset($data['name']) ? $data['name'] : '',
            'status' => isset($data['status']) ? $data['status'] : '',
            'assignee' => isset($data['assignee']) ? $data['assignee'] : '',
            'start_date' => isset($data['start_date']) ? $data['start_date'] : null,
            'due_date' => isset($data['due_date']) ? $data['due_date'] : null,
            'execution_date' => isset($data['execution_date']) ? $data['execution_date'] : null,
            'client' => isset($data['client']) ? $data['client'] : '',
            'project' => isset($data['project']) ? $data['project'] : '',
            'points' => isset($data['points']) ? floatval($data['points']) : 0,
            'time_tracked_seconds' => isset($data['time_tracked_seconds']) ? intval($data['time_tracked_seconds']) : 0,
            'task_time_spent_seconds' => isset($data['task_time_spent_seconds']) ? intval($data['task_time_spent_seconds']) : 0,
            'user_period_time_spent_seconds' => isset($data['user_period_time_spent_seconds']) ? intval($data['user_period_time_spent_seconds']) : 0,
            'raw' => isset($data['raw']) ? $data['raw'] : ''
        );
        
        // Verifica se o registro já existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE entry_id = %s",
            $sanitized_data['entry_id']
        ));
        
        if ($exists) {
            // Atualiza o registro existente
            $result = $wpdb->update(
                $this->table_name,
                $sanitized_data,
                array('entry_id' => $sanitized_data['entry_id']),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s'),
                array('%s')
            );
            
            if (false === $result) {
                error_log('F2F CSV Importer: Erro ao atualizar registro: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
        } else {
            // Insere um novo registro
            $result = $wpdb->insert(
                $this->table_name,
                $sanitized_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s')
            );
            
            if (false === $result) {
                error_log('F2F CSV Importer: Erro ao inserir registro: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
        }
        
        // Sanitiza os dados para evitar problemas de formato
        $sanitized_data = array(
            'entry_id' => $data['entry_id'],
            'task_id' => isset($data['task_id']) ? $data['task_id'] : '',
            'name' => isset($data['name']) ? $data['name'] : '',
            'status' => isset($data['status']) ? $data['status'] : '',
            'assignee' => isset($data['assignee']) ? $data['assignee'] : '',
            'start_date' => isset($data['start_date']) ? $data['start_date'] : null,
            'due_date' => isset($data['due_date']) ? $data['due_date'] : null,
            'execution_date' => isset($data['execution_date']) ? $data['execution_date'] : null,
            'client' => isset($data['client']) ? $data['client'] : '',
            'project' => isset($data['project']) ? $data['project'] : '',
            'points' => isset($data['points']) ? floatval($data['points']) : 0,
            'time_tracked_seconds' => isset($data['time_tracked_seconds']) ? intval($data['time_tracked_seconds']) : 0,
            'task_time_spent_seconds' => isset($data['task_time_spent_seconds']) ? intval($data['task_time_spent_seconds']) : 0,
            'user_period_time_spent_seconds' => isset($data['user_period_time_spent_seconds']) ? intval($data['user_period_time_spent_seconds']) : 0,
            'raw' => isset($data['raw']) ? $data['raw'] : ''
        );
        
        // Verifica se o registro já existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE entry_id = %s",
            $sanitized_data['entry_id']
        ));
        
        if ($exists) {
            // Atualiza o registro existente
            $result = $wpdb->update(
                $this->table_name,
                $sanitized_data,
                array('entry_id' => $sanitized_data['entry_id']),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s'),
                array('%s')
            );
            
            if (false === $result) {
                error_log('F2F CSV Importer: Erro ao atualizar registro: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
        } else {
            // Insere um novo registro
            $result = $wpdb->insert(
                $this->table_name,
                $sanitized_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s')
            );
            
            if (false === $result) {
                error_log('F2F CSV Importer: Erro ao inserir registro: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
        }
    }

    /**
     * Tenta encontrar um valor em um array associativo usando várias chaves candidatas
     *
     * @param array $data Array associativo
     * @param array $candidates Lista de chaves candidatas
     * @return string Valor encontrado ou string vazia
     */
    private function guess_field( $data, $candidates ) {
        // Log para debug
        error_log('F2F CSV Importer: Tentando encontrar campo entre candidatos: ' . implode(', ', $candidates));
        error_log('F2F CSV Importer: Chaves disponíveis: ' . implode(', ', array_keys($data)));
        
        // Primeiro tenta encontrar correspondência exata
        foreach ( $candidates as $c ) {
            if ( isset( $data[ $c ] ) && '' !== $data[ $c ] ) {
                error_log('F2F CSV Importer: Campo encontrado (correspondência exata): ' . $c);
                return $data[ $c ];
            }
        }
        
        // Se não encontrar, tenta correspondência parcial (para CSV com cabeçalhos diferentes)
        foreach ( $candidates as $c ) {
            foreach ( array_keys( $data ) as $key ) {
                if ( stripos( $key, $c ) !== false || stripos( $c, $key ) !== false ) {
                    if ( '' !== $data[ $key ] ) {
                        error_log('F2F CSV Importer: Campo encontrado (correspondência parcial): ' . $key);
                        return $data[ $key ];
                    }
                }
            }
        }
        
        error_log('F2F CSV Importer: Campo não encontrado para candidatos: ' . implode(', ', $candidates));
        return '';
    }

    /**
     * Retorna valor apenas se houver correspondência exata de cabeçalho.
     * Evita que "time tracked" capture "user total time tracked" por coincidência parcial.
     *
     * @param array $data
     * @param array $candidates
     * @return string
     */
    private function get_exact_field( $data, $candidates ) {
        foreach ( $candidates as $c ) {
            if ( isset( $data[ $c ] ) && '' !== $data[ $c ] ) {
                return $data[ $c ];
            }
            // Também tenta em minúsculas se as chaves foram normalizadas
            $lc = strtolower( $c );
            if ( isset( $data[ $lc ] ) && '' !== $data[ $lc ] ) {
                return $data[ $lc ];
            }
        }
        return '';
    }

    /**
     * Analisa candidatos a data e retorna no formato MySQL
     *
     * @param array $data Array associativo
     * @param array $candidates Lista de chaves candidatas
     * @return string|null Data no formato MySQL ou null
     */
    private function parse_date_candidates( $data, $candidates ) {
        // Primeiro tenta campos de texto
        foreach ( $candidates as $c ) {
            if ( isset( $data[ $c ] ) && '' !== $data[ $c ] && false !== strpos( $c, 'text' ) ) {
                $timestamp = strtotime( $data[ $c ] );
                if ( $timestamp ) {
                    return date( 'Y-m-d H:i:s', $timestamp );
                }
            }
        }
        
        // Depois tenta campos numéricos (milissegundos)
        foreach ( $candidates as $c ) {
            if ( isset( $data[ $c ] ) && '' !== $data[ $c ] && is_numeric( $data[ $c ] ) ) {
                // Converte milissegundos para segundos
                $timestamp = (int) ( $data[ $c ] / 1000 );
                if ( $timestamp > 0 ) {
                    return date( 'Y-m-d H:i:s', $timestamp );
                }
            }
        }
        
        return null;
    }

    /**
     * Analisa duração em segundos a partir de valor numérico (milissegundos) ou texto (HH:MM:SS)
     *
     * @param string $ms_value Valor em milissegundos
     * @param string $text_value Valor em formato texto (HH:MM:SS)
     * @return int Duração em segundos
     */
    private function parse_duration_seconds( $ms_value, $text_value ) {
        // Se temos um valor de texto no formato HH:MM:SS, usamos ele
        if ( ! empty( $text_value ) ) {
            $parts = explode( ':', $text_value );
            if ( count( $parts ) >= 2 ) {
                $h = (int) $parts[0];
                $m = (int) $parts[1];
                $s = isset( $parts[2] ) ? (int) $parts[2] : 0;
                return $h * 3600 + $m * 60 + $s;
            }
        }
        
        // Se temos um valor numérico em milissegundos, convertemos para segundos
        if ( ! empty( $ms_value ) && is_numeric( $ms_value ) ) {
            return (int) ( $ms_value / 1000 );
        }
        
        return 0;
    }
}