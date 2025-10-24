<?php
/**
 * Classe principal para gerenciar o Dashboard F2F
 *
 * @package F2FDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Classe F2F_Dashboard
 * 
 * Gerencia a estrutura principal do dashboard, incluindo consultas e exibição de dados.
 */
class F2F_Dashboard {
    /**
     * Nome da tabela no banco de dados
     *
     * @var string
     */
    private $table_name;

    /**
     * Instância única da classe (singleton)
     *
     * @var F2F_Dashboard
     */
    private static $instance = null;

    /**
     * Construtor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'f2f_clickup_data';
        
        // Garante que a tabela exista
        $this->create_table();
    }

    /**
     * Obtém a instância única da classe
     *
     * @return F2F_Dashboard
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Cria a tabela no banco de dados se não existir
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Definição completa da tabela usada pelo dashboard.
        // Nota: dbDelta funciona melhor sem 'IF NOT EXISTS'.
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id VARCHAR(64) DEFAULT NULL,
            task_id VARCHAR(64) DEFAULT NULL,
            name TEXT DEFAULT NULL,
            status VARCHAR(64) DEFAULT NULL,
            assignee VARCHAR(128) DEFAULT NULL,
            start_date DATETIME DEFAULT NULL,
            due_date DATETIME DEFAULT NULL,
            project VARCHAR(128) DEFAULT NULL,
            client VARCHAR(128) DEFAULT NULL,
            points FLOAT DEFAULT NULL,
            time_tracked_seconds INT DEFAULT NULL,
            task_time_spent_seconds INT DEFAULT NULL,
            user_period_time_spent_seconds INT DEFAULT NULL,
            raw LONGTEXT DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY entry_id (entry_id),
            KEY status (status),
            KEY assignee (assignee),
            KEY due_date (due_date),
            KEY client (client)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Pós-verificação de esquema: adiciona colunas ausentes caso a tabela exista com um esquema antigo.
        $existing_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );

        // Colunas requeridas e seus tipos.
        $required_columns = array(
            'entry_id' => 'VARCHAR(64) DEFAULT NULL',
            'task_id' => 'VARCHAR(64) DEFAULT NULL',
            'name' => 'TEXT DEFAULT NULL',
            'status' => 'VARCHAR(64) DEFAULT NULL',
            'assignee' => 'VARCHAR(128) DEFAULT NULL',
            'start_date' => 'DATETIME DEFAULT NULL',
            'due_date' => 'DATETIME DEFAULT NULL',
            'execution_date' => 'DATETIME DEFAULT NULL',
            'project' => 'VARCHAR(128) DEFAULT NULL',
            'client' => 'VARCHAR(128) DEFAULT NULL',
            'points' => 'FLOAT DEFAULT NULL',
            'time_tracked_seconds' => 'INT DEFAULT NULL',
            'task_time_spent_seconds' => 'INT DEFAULT NULL',
            'user_period_time_spent_seconds' => 'INT DEFAULT NULL',
            'raw' => 'LONGTEXT DEFAULT NULL',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        );

        foreach ( $required_columns as $col => $type ) {
            if ( ! in_array( $col, $existing_columns, true ) ) {
                $wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN {$col} {$type}" );
            }
        }

        // Garante índices essenciais.
        $indexes = (array) $wpdb->get_results( "SHOW INDEX FROM {$this->table_name}" );
        $has_unique_entry_id = false;
        $has_status_index = false;
        $has_assignee_index = false;
        $has_due_date_index = false;
        $has_client_index = false;
        foreach ( $indexes as $idx ) {
            if ( isset( $idx->Key_name ) ) {
                if ( 'entry_id' === $idx->Key_name && isset( $idx->Non_unique ) && (int) $idx->Non_unique === 0 ) {
                    $has_unique_entry_id = true;
                }
                if ( 'status' === $idx->Key_name ) {
                    $has_status_index = true;
                }
                if ( 'assignee' === $idx->Key_name ) {
                    $has_assignee_index = true;
                }
                if ( 'due_date' === $idx->Key_name ) {
                    $has_due_date_index = true;
                }
                if ( 'client' === $idx->Key_name ) {
                    $has_client_index = true;
                }
            }
        }
        if ( ! $has_unique_entry_id && in_array( 'entry_id', $existing_columns, true ) ) {
            // Tenta criar índice único em entry_id.
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD UNIQUE KEY entry_id (entry_id)" );
        }
        if ( ! $has_status_index && in_array( 'status', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX status (status)" );
        }
        if ( ! $has_assignee_index && in_array( 'assignee', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX assignee (assignee)" );
        }
        if ( ! $has_due_date_index && in_array( 'due_date', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX due_date (due_date)" );
        }
        if ( ! $has_client_index && in_array( 'client', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX client (client)" );
        }

        // Remove índice único em task_id se existir (vamos usar entry_id como único).
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$this->table_name}" );
        foreach ( (array) $indexes as $idx ) {
            if ( isset( $idx->Key_name ) && 'task_id' === $idx->Key_name ) {
                $wpdb->query( "ALTER TABLE {$this->table_name} DROP INDEX task_id" );
                break;
            }
        }
    }

    /**
     * Retorna o nome da coluna usada para cliente (preferir 'client', senão 'project').
     */
    private function get_client_group_column() {
        global $wpdb;
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        return in_array( 'client', $columns, true ) ? 'client' : 'project';
    }

    /**
     * Limpa todos os dados da tabela
     * 
     * @return int Número de registros removidos
     */
    public function clear_all_data() {
        global $wpdb;
        
        // Conta quantos registros serão removidos
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        
        // Executa o truncate para limpar a tabela
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
        
        return $count;
    }

    /**
     * Obtém o total de tarefas
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return int
     */
    public function get_total_tasks( $start_date = null, $end_date = null, $client = null, $assignee = null ) {
        global $wpdb;
        
        // Combina condições de data - usa execution_date se disponível, senão start_date, senão updated_at
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        
        // Adiciona condição de task_id válido
        $conditions[] = "task_id IS NOT NULL AND task_id <> ''";
        
        // Filtros opcionais
        if ( $client ) {
            $group_col = $this->get_client_group_column();
            $conditions[] = $wpdb->prepare("{$group_col} = %s", $client);
        }
        if ( $assignee ) {
            $conditions[] = $wpdb->prepare("assignee = %s", $assignee);
        }

        $where_clause = ! empty( $conditions ) ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
        
        // Conta tarefas únicas (DISTINCT task_id) em vez de entradas de tempo
        return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT task_id) FROM {$this->table_name} {$where_clause}" );
    }

    /**
     * Obtém o total de tarefas concluídas
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return int
     */
    public function get_completed_tasks( $start_date = null, $end_date = null, $client = null, $assignee = null ) {
        global $wpdb;
        $status_condition = "(LOWER(status) IN ('closed','complete','done','finished') 
            OR LOWER(status) LIKE '%conclu%' 
            OR LOWER(status) LIKE '%finaliz%')";
        
        
        // Combina condições de data com condições de status
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        $conditions[] = $status_condition;
        if ( $client ) {
            $group_col = $this->get_client_group_column();
            $conditions[] = $wpdb->prepare("{$group_col} = %s", $client);
        }
        if ( $assignee ) {
            $conditions[] = $wpdb->prepare("assignee = %s", $assignee);
        }
        
        // Adiciona condição de task_id válido
        $conditions[] = "task_id IS NOT NULL AND task_id <> ''";
        
        $where_clause = 'WHERE ' . implode( ' AND ', $conditions );
        
        // Conta tarefas únicas concluídas (DISTINCT task_id) em vez de entradas de tempo
        return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT task_id) FROM {$this->table_name} {$where_clause}" );
    }

    /**
     * Obtém o total de tarefas em andamento
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return int
     */
    public function get_in_progress_tasks( $start_date = null, $end_date = null, $client = null, $assignee = null ) {
        global $wpdb;
        
        // Combina condições de data - usa execution_date se disponível, senão start_date, senão updated_at
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        
        // SIMPLES: Status contém "andamento" - mesma lógica da listagem
        $in_progress_condition = "(LOWER(status) LIKE '%andamento%')";
        
        $conditions[] = $in_progress_condition;
        if ( $client ) {
            $group_col = $this->get_client_group_column();
            $conditions[] = $wpdb->prepare("{$group_col} = %s", $client);
        }
        if ( $assignee ) {
            $conditions[] = $wpdb->prepare("assignee = %s", $assignee);
        }
        
        // Adiciona condição de task_id válido
        $conditions[] = "task_id IS NOT NULL AND task_id <> ''";
        
        $where_clause = 'WHERE ' . implode( ' AND ', $conditions );
        
        // Conta tarefas únicas em andamento (DISTINCT task_id) em vez de entradas de tempo
        return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT task_id) FROM {$this->table_name} {$where_clause}" );
    }

    /**
     * Obtém o total de tarefas atrasadas
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return int
     */
    public function get_overdue_tasks( $start_date = null, $end_date = null, $client = null, $assignee = null ) {
        global $wpdb;
        // Condição mais flexível para tarefas atrasadas - busca por múltiplos critérios
        $overdue_condition = "(
            (due_date IS NOT NULL AND due_date < NOW()) 
            OR (start_date IS NOT NULL AND start_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
            OR (updated_at IS NOT NULL AND updated_at < DATE_SUB(NOW(), INTERVAL 14 DAY))
        ) AND NOT (LOWER(status) IN ('closed','complete','done','finished') 
            OR LOWER(status) LIKE '%conclu%' 
            OR LOWER(status) LIKE '%finaliz%')";
        
        // Combina condições de data com condições de atraso
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        $conditions[] = $overdue_condition;
        if ( $client ) {
            $group_col = $this->get_client_group_column();
            $conditions[] = $wpdb->prepare("{$group_col} = %s", $client);
        }
        if ( $assignee ) {
            $conditions[] = $wpdb->prepare("assignee = %s", $assignee);
        }
        
        // Adiciona condição de task_id válido
        $conditions[] = "task_id IS NOT NULL AND task_id <> ''";
        
        $where_clause = 'WHERE ' . implode( ' AND ', $conditions );
        
        // Conta tarefas únicas atrasadas (DISTINCT task_id) em vez de entradas de tempo
        return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT task_id) FROM {$this->table_name} {$where_clause}" );
    }

    /**
     * Constrói cláusula WHERE para filtros de data
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return string
     */
    private function build_date_where_clause( $start_date = null, $end_date = null ) {
        $conditions = array();
        
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(" . $this->table_name . ".execution_date >= '" . esc_sql( $start_date ) . "' OR (" . $this->table_name . ".execution_date IS NULL AND " . $this->table_name . ".start_date >= '" . esc_sql( $start_date ) . "') OR (" . $this->table_name . ".execution_date IS NULL AND " . $this->table_name . ".start_date IS NULL AND " . $this->table_name . ".updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(" . $this->table_name . ".execution_date <= '" . esc_sql( $end_date ) . "' OR (" . $this->table_name . ".execution_date IS NULL AND " . $this->table_name . ".start_date <= '" . esc_sql( $end_date ) . "') OR (" . $this->table_name . ".execution_date IS NULL AND " . $this->table_name . ".start_date IS NULL AND " . $this->table_name . ".updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        
        return ! empty( $conditions ) ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
    }

    /**
     * Obtém dados agrupados por responsável
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return array
     */
    public function get_data_by_assignee( $start_date = null, $end_date = null, $client = null ) {
        global $wpdb;
        
        // Verifica quais colunas de tempo existem na tabela
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );

        // Preferir somar o tempo por entrada (time_tracked_seconds).
        // Se não existir, usar o tempo gasto por tarefa (task_time_spent_seconds).
        // NÃO incluir user_period_time_spent_seconds, pois é acumulado e gera dupla contagem por linha.
        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        $where_clause = $this->build_date_where_clause( $start_date, $end_date );
        $assignee_condition = "assignee IS NOT NULL AND assignee <> ''";
        if ( $client ) {
            $group_col = $this->get_client_group_column();
            $assignee_condition .= $wpdb->prepare(" AND {$group_col} = %s", $client);
        }
        
        // Combina condições de data com condições de assignee
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        $conditions[] = $assignee_condition;
        
        $where_clause = 'WHERE ' . implode( ' AND ', $conditions );

        // Debug: Log da query para assignee - VERSÃO ATUALIZADA
        error_log( 'F2F Dashboard: Query assignee - ' . "SELECT assignee, SUM({$seconds_expr}) AS seconds, COUNT(*) as c FROM {$this->table_name} {$where_clause} GROUP BY assignee ORDER BY seconds DESC, c DESC LIMIT 10" );
        error_log( 'F2F Dashboard: WHERE clause usado: ' . $where_clause );
        error_log( 'F2F Dashboard: VERSÃO ATUALIZADA - usando execution_date como prioridade' );

        if ( ! empty( $seconds_expr ) ) {
            $by_assignee = $wpdb->get_results( 
                "SELECT assignee, SUM({$seconds_expr}) AS seconds, COUNT(*) as c 
                FROM {$this->table_name} 
                {$where_clause}
                GROUP BY assignee 
                ORDER BY seconds DESC, c DESC 
                LIMIT 10" 
            );

            if ( empty( $by_assignee ) ) {
                $by_assignee = $wpdb->get_results( 
                    "SELECT assignee, COUNT(*) as c 
                    FROM {$this->table_name} 
                    {$where_clause}
                    GROUP BY assignee 
                    ORDER BY c DESC 
                    LIMIT 10" 
                );
                return array( 'data' => $by_assignee, 'use_counts_only' => true );
            }

            return array( 'data' => $by_assignee, 'use_counts_only' => false );
        }

        // Fallback: se não temos colunas de tempo, usamos apenas contagem
        $by_assignee = $wpdb->get_results( 
            "SELECT assignee, COUNT(*) as c 
            FROM {$this->table_name} 
            {$where_clause}
            GROUP BY assignee 
            ORDER BY c DESC 
            LIMIT 10" 
        );

        return array( 'data' => $by_assignee, 'use_counts_only' => true );
    }

    /**
     * Obtém dados agrupados por cliente/projeto
     *
     * Soma preferencialmente time_tracked_seconds, com fallback para task_time_spent_seconds.
     * Não inclui user_period_time_spent_seconds para evitar dupla contagem.
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return array { data: resultados, use_counts_only: bool }
     */
    public function get_data_by_project( $start_date = null, $end_date = null, $assignee = null ) {
        global $wpdb;

        // Verifica colunas disponíveis
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        $group_col = $this->get_client_group_column();

        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        $where_clause = $this->build_date_where_clause( $start_date, $end_date );
        $project_condition = "{$group_col} IS NOT NULL AND {$group_col} <> ''";
        if ( $assignee ) {
            $project_condition .= $wpdb->prepare(" AND assignee = %s", $assignee);
        }
        
        // Combina condições de data com condições de projeto
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        $conditions[] = $project_condition;
        
        $where_clause = 'WHERE ' . implode( ' AND ', $conditions );

        // Debug: Log da query para projeto
        error_log( 'F2F Dashboard: Query projeto - ' . "SELECT {$group_col} AS client, SUM({$seconds_expr}) AS seconds, COUNT(*) as c FROM {$this->table_name} {$where_clause} GROUP BY {$group_col} ORDER BY seconds DESC, c DESC LIMIT 10" );

        if ( ! empty( $seconds_expr ) ) {
            $by_project = $wpdb->get_results(
                "SELECT {$group_col} AS client, SUM({$seconds_expr}) AS seconds, COUNT(*) as c 
                FROM {$this->table_name} 
                {$where_clause}
                GROUP BY {$group_col} 
                ORDER BY seconds DESC, c DESC 
                LIMIT 10"
            );

            if ( empty( $by_project ) ) {
                $by_project = $wpdb->get_results(
                    "SELECT {$group_col} AS client, COUNT(*) as c 
                    FROM {$this->table_name} 
                    {$where_clause}
                    GROUP BY {$group_col} 
                    ORDER BY c DESC 
                    LIMIT 10"
                );
                return array( 'data' => $by_project, 'use_counts_only' => true );
            }

            return array( 'data' => $by_project, 'use_counts_only' => false );
        }

        // Fallback se não há colunas de tempo
        $group_col = $this->get_client_group_column();
        $by_project = $wpdb->get_results(
            "SELECT {$group_col} AS client, COUNT(*) as c 
            FROM {$this->table_name} 
            {$where_clause}
            GROUP BY {$group_col} 
            ORDER BY c DESC 
            LIMIT 10"
        );

        return array( 'data' => $by_project, 'use_counts_only' => true );
    }

    /**
     * Obtém as tarefas mais recentes
     *
     * @param int $limit Número máximo de tarefas a retornar
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @return array
     */
    public function get_recent_tasks( $limit = 10, $start_date = null, $end_date = null, $client = null, $assignee = null ) {
        global $wpdb;
        $group_col = $this->get_client_group_column();
        
        // Combina condições de data - usa execution_date se disponível, senão start_date, senão updated_at
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        
        if ( $client ) {
            $conditions[] = $wpdb->prepare("{$group_col} = %s", $client);
        }
        if ( $assignee ) {
            $conditions[] = $wpdb->prepare("assignee = %s", $assignee);
        }
        
        // Filtra apenas tarefas que tenham task_id válido
        $conditions[] = "task_id IS NOT NULL AND task_id <> ''";
        
        $where_clause = ! empty( $conditions ) ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
        
        $sql = "SELECT name, status, assignee, project, {$group_col} AS client, due_date, updated_at, task_id 
                FROM {$this->table_name} 
                {$where_clause}
                ORDER BY updated_at DESC 
                LIMIT %d";
        
        return $wpdb->get_results( 
            $wpdb->prepare( $sql, $limit )
        );
    }

    /**
     * Formata segundos para o formato HH:MM:SS
     *
     * @param int $seconds Segundos a serem formatados
     * @return string
     */
    public function format_seconds_to_hhmmss( $seconds ) {
        $seconds = (int) $seconds;
        $h = floor( $seconds / 3600 );
        $m = floor( ( $seconds % 3600 ) / 60 );
        $s = $seconds % 60;
        return sprintf( '%02d:%02d:%02d', $h, $m, $s );
    }

    /**
     * Totais por um projeto específico (cliente)
     *
     * @param string $project Nome do projeto/cliente
     * @return array { seconds: int, count: int }
     */
    public function get_project_totals( $project ) {
        global $wpdb;
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE project = %s",
            $project
        ) );

        $seconds = 0;
        if ( ! empty( $seconds_expr ) ) {
            $seconds = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM({$seconds_expr}) FROM {$this->table_name} WHERE project = %s",
                $project
            ) );
        }

        return array( 'seconds' => $seconds, 'count' => $count );
    }

    /**
     * Lista de tarefas de um projeto
     *
     * @param string $project Projeto/cliente
     * @param int $limit Limite de registros
     * @return array
     */
    public function get_tasks_by_project( $project, $limit = 30 ) {
        global $wpdb;
        $group_col = $this->get_client_group_column();
        return $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT name, status, assignee, {$group_col} AS client, due_date, updated_at 
                FROM {$this->table_name} 
                WHERE {$group_col} = %s 
                ORDER BY updated_at DESC 
                LIMIT %d",
                $project,
                $limit
            )
        );
    }

    /**
     * Totais por cliente
     */
    public function get_client_totals( $client ) {
        global $wpdb;
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        $group_col = $this->get_client_group_column();
        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$group_col} = %s",
            $client
        ) );

        $seconds = 0;
        if ( ! empty( $seconds_expr ) ) {
            $seconds = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM({$seconds_expr}) FROM {$this->table_name} WHERE {$group_col} = %s",
                $client
            ) );
        }

        return array( 'seconds' => $seconds, 'count' => $count );
    }

    /**
     * Quebra por responsável dentro de um cliente
     */
    public function get_assignees_by_client( $client ) {
        global $wpdb;
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        $group_col = $this->get_client_group_column();
        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        if ( ! empty( $seconds_expr ) ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT assignee, SUM({$seconds_expr}) AS seconds, COUNT(*) as c 
                FROM {$this->table_name} 
                WHERE {$group_col} = %s AND assignee IS NOT NULL AND assignee <> '' 
                GROUP BY assignee 
                ORDER BY seconds DESC, c DESC",
                $client
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT assignee, COUNT(*) as c 
            FROM {$this->table_name} 
            WHERE {$group_col} = %s AND assignee IS NOT NULL AND assignee <> '' 
            GROUP BY assignee 
            ORDER BY c DESC",
            $client
        ) );
    }

    /**
     * Totais por cliente em um período
     *
     * @param string $client
     * @param string|null $start_date Y-m-d H:i:s
     * @param string|null $end_date Y-m-d H:i:s
     * @return array { seconds: int, count: int }
     */
    public function get_client_totals_in_period( $client, $start_date = null, $end_date = null ) {
        global $wpdb;
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        $group_col = $this->get_client_group_column();
        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        $where_date = $this->build_date_where_clause( $start_date, $end_date );
        $where = empty( $where_date ) ? 'WHERE' : $where_date . ' AND';

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} {$where} {$group_col} = %s",
            $client
        ) );

        $seconds = 0;
        if ( ! empty( $seconds_expr ) ) {
            $seconds = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM({$seconds_expr}) FROM {$this->table_name} {$where} {$group_col} = %s",
                $client
            ) );
        }

        return array( 'seconds' => $seconds, 'count' => $count );
    }

    /**
     * Responsáveis por cliente em um período
     */
    public function get_assignees_by_client_in_period( $client, $start_date = null, $end_date = null ) {
        global $wpdb;
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        $group_col = $this->get_client_group_column();
        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        $where_date = $this->build_date_where_clause( $start_date, $end_date );
        $where = empty( $where_date ) ? 'WHERE' : $where_date . ' AND';

        if ( ! empty( $seconds_expr ) ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT assignee, SUM({$seconds_expr}) AS seconds, COUNT(*) as c 
                 FROM {$this->table_name} 
                 {$where} {$group_col} = %s AND assignee IS NOT NULL AND assignee <> ''
                 GROUP BY assignee 
                 ORDER BY seconds DESC, c DESC",
                $client
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT assignee, COUNT(*) as c 
             FROM {$this->table_name} 
             {$where} {$group_col} = %s AND assignee IS NOT NULL AND assignee <> ''
             GROUP BY assignee 
             ORDER BY c DESC",
            $client
        ) );
    }

    /**
     * Quebra por responsável dentro de um projeto
     *
     * @param string $project Projeto/cliente
     * @return array
     */
    public function get_assignees_by_project( $project ) {
        global $wpdb;
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}", 0 );
        $seconds_expr = '';
        if ( in_array( 'time_tracked_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(time_tracked_seconds, 0)';
        } elseif ( in_array( 'task_time_spent_seconds', $columns, true ) ) {
            $seconds_expr = 'IFNULL(task_time_spent_seconds, 0)';
        }

        if ( ! empty( $seconds_expr ) ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT assignee, SUM({$seconds_expr}) AS seconds, COUNT(*) as c 
                FROM {$this->table_name} 
                WHERE project = %s AND assignee IS NOT NULL AND assignee <> '' 
                GROUP BY assignee 
                ORDER BY seconds DESC, c DESC",
                $project
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT assignee, COUNT(*) as c 
            FROM {$this->table_name} 
            WHERE project = %s AND assignee IS NOT NULL AND assignee <> '' 
            GROUP BY assignee 
            ORDER BY c DESC",
            $project
        ) );
    }

    /**
     * Lista distintos de clientes.
     */
    public function get_distinct_clients() {
        global $wpdb;
        $group_col = $this->get_client_group_column();
        return (array) $wpdb->get_col( "SELECT DISTINCT {$group_col} FROM {$this->table_name} WHERE {$group_col} IS NOT NULL AND {$group_col} <> '' ORDER BY {$group_col} ASC" );
    }

    /**
     * Lista distintos de responsáveis.
     */
    public function get_distinct_assignees() {
        global $wpdb;
        return (array) $wpdb->get_col( "SELECT DISTINCT assignee FROM {$this->table_name} WHERE assignee IS NOT NULL AND assignee <> '' ORDER BY assignee ASC" );
    }

    /**
     * Obtém lista detalhada de tarefas atrasadas
     *
     * @param string $start_date Data de início (Y-m-d H:i:s)
     * @param string $end_date Data de fim (Y-m-d H:i:s)
     * @param string $client Filtro por cliente
     * @param string $assignee Filtro por responsável
     * @return array
     */
    public function get_overdue_tasks_details( $start_date = null, $end_date = null, $client = null, $assignee = null ) {
        global $wpdb;
        
        // Condição mais flexível para tarefas atrasadas - busca por múltiplos critérios
        $overdue_condition = "(
            (due_date IS NOT NULL AND due_date < NOW()) 
            OR (start_date IS NOT NULL AND start_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
            OR (updated_at IS NOT NULL AND updated_at < DATE_SUB(NOW(), INTERVAL 14 DAY))
        ) AND NOT (LOWER(status) IN ('closed','complete','done','finished') 
            OR LOWER(status) LIKE '%conclu%' 
            OR LOWER(status) LIKE '%finaliz%')";
        
        // Combina condições de data com condições de atraso
        $conditions = array();
        if ( $start_date || $end_date ) {
            $date_condition = "(";
            $date_parts = array();
            
            if ( $start_date ) {
                $date_parts[] = "(execution_date >= '" . esc_sql( $start_date ) . "' OR (execution_date IS NULL AND start_date >= '" . esc_sql( $start_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at >= '" . esc_sql( $start_date ) . "'))";
            }
            if ( $end_date ) {
                $date_parts[] = "(execution_date <= '" . esc_sql( $end_date ) . "' OR (execution_date IS NULL AND start_date <= '" . esc_sql( $end_date ) . "') OR (execution_date IS NULL AND start_date IS NULL AND updated_at <= '" . esc_sql( $end_date ) . "'))";
            }
            
            $date_condition .= implode( ' AND ', $date_parts ) . ")";
            $conditions[] = $date_condition;
        }
        
        $conditions[] = $overdue_condition;
        
        if ( $client ) {
            $group_col = $this->get_client_group_column();
            $conditions[] = $wpdb->prepare("{$group_col} = %s", $client);
        }
        if ( $assignee ) {
            $conditions[] = $wpdb->prepare("assignee = %s", $assignee);
        }
        
        // Adiciona condição de task_id válido
        $conditions[] = "task_id IS NOT NULL AND task_id <> ''";
        
        $where_clause = 'WHERE ' . implode( ' AND ', $conditions );
        
        // Busca tarefas únicas atrasadas com detalhes
        // Ordena por mais recentes primeiro (DESC)
        $query = "SELECT DISTINCT 
                    task_id,
                    name,
                    status,
                    assignee,
                    {$this->get_client_group_column()} as client,
                    due_date,
                    start_date,
                    updated_at
                  FROM {$this->table_name} 
                  {$where_clause}
                  ORDER BY 
                    COALESCE(updated_at, start_date, due_date) DESC,
                    due_date DESC,
                    name ASC";
        
        return $wpdb->get_results( $query );
    }

    /**
     * Busca as horas trackadas de uma tarefa específica
     *
     * @param string $task_id ID da tarefa
     * @return array
     */
    public function get_task_hours( $task_id ) {
        global $wpdb;
        
        error_log( 'F2F Dashboard: Buscando horas para task_id: ' . $task_id );
        
        // Primeiro tenta buscar na tabela local usando os campos de tempo
        // Busca tanto por task_id quanto por entry_id, e também por nome da tarefa
        $query = "SELECT 
                    name,
                    assignee,
                    time_tracked_seconds,
                    task_time_spent_seconds,
                    user_period_time_spent_seconds,
                    updated_at,
                    entry_id,
                    task_id
                  FROM {$this->table_name} 
                  WHERE (task_id = %s OR entry_id = %s OR name LIKE %s)
                  ORDER BY updated_at DESC
                  LIMIT 5";
        
        $task_data = $wpdb->get_row( 
            $wpdb->prepare( $query, $task_id, $task_id, '%' . $task_id . '%' ),
            ARRAY_A
        );
        
        error_log( 'F2F Dashboard: Dados da tarefa encontrados: ' . print_r( $task_data, true ) );
        
        $results = array();
        
        if ( $task_data ) {
            // Usa o campo mais confiável para tempo trackado (prioridade)
            $time_seconds = 0;
            $description = 'Tempo trackado';
            
            // Prioriza task_time_spent_seconds (que deve conter as 4h 45m), depois time_tracked_seconds
            if ( ! empty( $task_data['task_time_spent_seconds'] ) && $task_data['task_time_spent_seconds'] > 0 ) {
                $time_seconds = $task_data['task_time_spent_seconds'];
                $description = 'Tempo gasto na tarefa';
            } elseif ( ! empty( $task_data['time_tracked_seconds'] ) && $task_data['time_tracked_seconds'] > 0 ) {
                $time_seconds = $task_data['time_tracked_seconds'];
                $description = 'Tempo trackado total';
            } elseif ( ! empty( $task_data['user_period_time_spent_seconds'] ) && $task_data['user_period_time_spent_seconds'] > 0 ) {
                $time_seconds = $task_data['user_period_time_spent_seconds'];
                $description = 'Tempo do usuário no período';
            }
            
            // Se encontrou horas, cria uma única entrada
            if ( $time_seconds > 0 ) {
                $hours = $time_seconds / 3600;
                
                $results[] = array(
                    'date' => $task_data['updated_at'],
                    'user' => $task_data['assignee'] ?: 'Usuário não definido',
                    'hours' => round( $hours, 2 ),
                    'description' => $description
                );
            } else {
                // Se não encontrou nenhum campo de tempo, mostra mensagem de debug
                $results[] = array(
                    'date' => $task_data['updated_at'],
                    'user' => 'Sistema',
                    'hours' => 0,
                    'description' => 'Nenhum campo de tempo encontrado na planilha'
                );
            }
        }
        
        // Se não encontrou horas na tabela local, busca no ClickUp
        if ( empty( $results ) ) {
            error_log( 'F2F Dashboard: Nenhuma hora encontrada na tabela local, tentando ClickUp...' );
            $results = $this->get_clickup_task_hours( $task_id );
            
            // Se ainda não encontrou, pode ser uma subtarefa - tenta buscar por nome
            if ( empty( $results ) ) {
                error_log( 'F2F Dashboard: Tentando buscar por nome da tarefa...' );
                $results = $this->search_task_by_name( $task_id );
            }
        }
        
        return $results;
    }
    
    /**
     * Busca horas de uma tarefa diretamente no ClickUp
     *
     * @param string $task_id ID da tarefa no ClickUp
     * @return array
     */
    private function get_clickup_task_hours( $task_id ) {
        error_log( 'F2F Dashboard: Buscando horas para task_id: ' . $task_id );
        
        // Inclui a classe da API ClickUp
        if ( ! class_exists( 'F2F_ClickUp_API' ) ) {
            require_once get_template_directory() . '/inc/class-clickup-api.php';
        }
        
        $clickup_api = F2F_ClickUp_API::get_instance();
        
        if ( ! $clickup_api->is_configured() ) {
            error_log( 'F2F Dashboard: API ClickUp não está configurada' );
            return array();
        }
        
        // Busca o tempo trackado da tarefa no ClickUp
        $time_data = $clickup_api->get_task_time( $task_id );
        
        if ( is_wp_error( $time_data ) ) {
            error_log( 'F2F Dashboard: Erro ao buscar tempo da tarefa no ClickUp: ' . $time_data->get_error_message() );
            return array();
        }
        
        error_log( 'F2F Dashboard: Dados de tempo recebidos: ' . print_r( $time_data, true ) );
        
        $results = array();
        
        // Processa as entradas de tempo
        if ( isset( $time_data['data'] ) && is_array( $time_data['data'] ) ) {
            foreach ( $time_data['data'] as $time_entry ) {
                if ( isset( $time_entry['duration'] ) && $time_entry['duration'] > 0 ) {
                    $hours = $time_entry['duration'] / 3600; // Converte milissegundos para horas
                    
                    $results[] = array(
                        'date' => isset( $time_entry['start'] ) ? date( 'Y-m-d H:i:s', $time_entry['start'] / 1000 ) : date( 'Y-m-d H:i:s' ),
                        'user' => isset( $time_entry['user']['username'] ) ? $time_entry['user']['username'] : 'Usuário não definido',
                        'hours' => round( $hours, 2 ),
                        'description' => isset( $time_entry['description'] ) ? $time_entry['description'] : 'Tempo trackado'
                    );
                }
            }
        } else {
            error_log( 'F2F Dashboard: Nenhuma entrada de tempo encontrada. Dados recebidos: ' . print_r( $time_data, true ) );
        }
        
        // Se não tem tempo trackado, retorna mensagem informativa
        if ( empty( $results ) ) {
            return array(
                array(
                    'date' => date( 'Y-m-d H:i:s' ),
                    'user' => 'Sistema',
                    'hours' => 0,
                    'description' => 'Nenhuma hora foi trackada para esta tarefa no ClickUp'
                )
            );
        }
        
        return $results;
    }
    
    /**
     * Busca tarefas em andamento
     *
     * @param int $limit Limite de resultados
     * @return array
     */
    public function get_in_progress_tasks_list( $limit = 50 ) {
        global $wpdb;
        $group_col = $this->get_client_group_column();
        
        // SIMPLES: Busca apenas tarefas com "andamento" no status
        // Como a tarefa na imagem: "4 em andamento"
        $sql = "SELECT DISTINCT
                    task_id,
                    name, 
                    status, 
                    assignee, 
                    project, 
                    {$group_col} AS client, 
                    due_date, 
                    updated_at
                FROM {$this->table_name} 
                WHERE LOWER(status) LIKE '%andamento%'
                AND task_id IS NOT NULL 
                AND task_id <> ''
                ORDER BY updated_at DESC 
                LIMIT %d";
        
        $results = $wpdb->get_results( 
            $wpdb->prepare( $sql, $limit )
        );
        
        return $results;
    }
    
    /**
     * Busca tarefa por nome (pode ser uma subtarefa)
     *
     * @param string $task_name Nome da tarefa
     * @return array
     */
    private function search_task_by_name( $task_name ) {
        global $wpdb;
        
        error_log( 'F2F Dashboard: Buscando tarefa por nome: ' . $task_name );
        
        // Busca na tabela local por nome similar
        $query = "SELECT 
                    name,
                    assignee,
                    time_tracked_seconds,
                    task_time_spent_seconds,
                    user_period_time_spent_seconds,
                    updated_at,
                    entry_id,
                    task_id
                  FROM {$this->table_name} 
                  WHERE name LIKE %s
                  ORDER BY updated_at DESC
                  LIMIT 1";
        
        $task_data = $wpdb->get_row( 
            $wpdb->prepare( $query, '%' . $task_name . '%' ),
            ARRAY_A
        );
        
        if ( $task_data ) {
            error_log( 'F2F Dashboard: Tarefa encontrada por nome: ' . print_r( $task_data, true ) );
            
            $results = array();
            
            // Processa os campos de tempo encontrados
            if ( ! empty( $task_data['time_tracked_seconds'] ) && $task_data['time_tracked_seconds'] > 0 ) {
                $hours = $task_data['time_tracked_seconds'] / 3600;
                $results[] = array(
                    'date' => $task_data['updated_at'],
                    'user' => $task_data['assignee'] ?: 'Usuário não definido',
                    'hours' => round( $hours, 2 ),
                    'description' => 'Tempo trackado total (encontrado por nome)'
                );
            }
            
            if ( ! empty( $task_data['task_time_spent_seconds'] ) && $task_data['task_time_spent_seconds'] > 0 ) {
                $hours = $task_data['task_time_spent_seconds'] / 3600;
                $results[] = array(
                    'date' => $task_data['updated_at'],
                    'user' => $task_data['assignee'] ?: 'Usuário não definido',
                    'hours' => round( $hours, 2 ),
                    'description' => 'Tempo gasto na tarefa (encontrado por nome)'
                );
            }
            
            if ( ! empty( $task_data['user_period_time_spent_seconds'] ) && $task_data['user_period_time_spent_seconds'] > 0 ) {
                $hours = $task_data['user_period_time_spent_seconds'] / 3600;
                $results[] = array(
                    'date' => $task_data['updated_at'],
                    'user' => $task_data['assignee'] ?: 'Usuário não definido',
                    'hours' => round( $hours, 2 ),
                    'description' => 'Tempo do usuário no período (encontrado por nome)'
                );
            }
            
            return $results;
        }
        
        error_log( 'F2F Dashboard: Nenhuma tarefa encontrada por nome' );
        return array();
    }
    
}