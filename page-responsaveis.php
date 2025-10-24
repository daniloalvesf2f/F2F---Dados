<?php
/**
 * Template Name: Responsáveis - ClickUp
 * Description: Página mostrando todos os responsáveis com suas métricas
 */

// Verificar se o usuário é administrador
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(home_url('/client-login/'));
    exit;
}

get_header();

$api = F2F_ClickUp_API::get_instance();
$is_configured = $api->is_configured();

// Buscar dados dos responsáveis
$responsaveis_data = array();
$error_message = '';

if ($is_configured) {
    // Busca workspace padrão
    $default_workspace = get_option('f2f_clickup_default_workspace', '');
    
    if ($default_workspace) {
        // Busca membros do time
        $members = $api->get_team_members($default_workspace);
        
        if (!is_wp_error($members)) {
            foreach ($members as $member) {
                $user_data = isset($member['user']) ? $member['user'] : $member;
                
                if (!isset($user_data['id'])) continue;
                
                // Dados básicos do usuário
                $responsavel = array(
                    'id' => $user_data['id'],
                    'name' => isset($user_data['username']) ? $user_data['username'] : 'Sem nome',
                    'email' => isset($user_data['email']) ? $user_data['email'] : '',
                    'color' => isset($user_data['color']) ? $user_data['color'] : '#667eea',
                    'initials' => isset($user_data['initials']) ? $user_data['initials'] : substr($user_data['username'], 0, 2),
                    'profile_picture' => isset($user_data['profilePicture']) ? $user_data['profilePicture'] : '',
                    'total_seconds' => 0,
                    'task_count' => 0
                );
                
                $responsaveis_data[$user_data['id']] = $responsavel;
            }
            
            // Busca dados do banco local para agregar horas trackadas
            global $wpdb;
            $table = $wpdb->prefix . 'f2f_clickup_data';
            
            // Verifica se a tabela existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            
            if ($table_exists) {
                // Busca tempo total por responsável
                $time_data = $wpdb->get_results(
                    "SELECT assignee, 
                            SUM(IFNULL(time_tracked_seconds, 0)) as total_seconds,
                            COUNT(*) as task_count
                     FROM {$table}
                     WHERE assignee IS NOT NULL AND assignee != ''
                     GROUP BY assignee"
                );
                
                // Agregar dados do banco com dados da API
                foreach ($time_data as $data) {
                    // Tentar encontrar o responsável pelo nome
                    foreach ($responsaveis_data as $id => $resp) {
                        if (strtolower($resp['name']) === strtolower($data->assignee)) {
                            $responsaveis_data[$id]['total_seconds'] = (int) $data->total_seconds;
                            $responsaveis_data[$id]['task_count'] = (int) $data->task_count;
                            break;
                        }
                    }
                }
            }
        } else {
            $error_message = $members->get_error_message();
        }
    } else {
        $error_message = 'Workspace não configurado. Configure em: F2F Dashboard → ClickUp API';
    }
}

// Ordenar por horas trackadas (maior para menor)
usort($responsaveis_data, function($a, $b) {
    return $b['total_seconds'] - $a['total_seconds'];
});
?>

<div class="f2f-dashboard-wrapper">
    <div class="container-fluid px-4 py-4">
        
        <!-- Header -->
        <div class="dashboard-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="dashboard-title">
                        <i class="fas fa-users me-2"></i>
                        Responsáveis
                    </h1>
                    <p class="dashboard-subtitle">Equipe e métricas de desempenho</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync me-1"></i> Atualizar
                    </button>
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
                            Para visualizar os responsáveis, você precisa configurar sua API Token do ClickUp.
                            <a href="<?php echo admin_url('admin.php?page=f2f-clickup-api'); ?>" class="alert-link">
                                Clique aqui para configurar
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif (!empty($error_message)) : ?>
        <!-- Erro -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-times-circle fa-2x me-3"></i>
                    <div>
                        <h4 class="alert-heading">Erro ao buscar dados</h4>
                        <p class="mb-0"><?php echo esc_html($error_message); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else : ?>
        
        <!-- Estatísticas Gerais -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo count($responsaveis_data); ?></h3>
                        <p class="stat-label">Membros da Equipe</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        $total_hours = array_sum(array_column($responsaveis_data, 'total_seconds')) / 3600;
                        ?>
                        <h3 class="stat-value"><?php echo number_format($total_hours, 1); ?>h</h3>
                        <p class="stat-label">Horas Totais</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        $total_tasks = array_sum(array_column($responsaveis_data, 'task_count'));
                        ?>
                        <h3 class="stat-value"><?php echo $total_tasks; ?></h3>
                        <p class="stat-label">Tarefas Totais</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        $avg_hours = count($responsaveis_data) > 0 ? $total_hours / count($responsaveis_data) : 0;
                        ?>
                        <h3 class="stat-value"><?php echo number_format($avg_hours, 1); ?>h</h3>
                        <p class="stat-label">Média por Pessoa</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cards dos Responsáveis -->
        <div class="row g-4">
            <?php if (empty($responsaveis_data)) : ?>
                <div class="col-12">
                    <div class="alert alert-info text-center py-5">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h4>Nenhum responsável encontrado</h4>
                        <p class="mb-0">Importe dados do ClickUp ou adicione membros ao seu workspace.</p>
                    </div>
                </div>
            <?php else : ?>
                <?php foreach ($responsaveis_data as $responsavel) : ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="responsavel-card">
                            <div class="responsavel-header">
                                <div class="responsavel-avatar">
                                    <?php if (!empty($responsavel['profile_picture'])) : ?>
                                        <img src="<?php echo esc_url($responsavel['profile_picture']); ?>" 
                                             alt="<?php echo esc_attr($responsavel['name']); ?>"
                                             class="avatar-img">
                                    <?php else : ?>
                                        <div class="avatar-placeholder" 
                                             style="background-color: <?php echo esc_attr($responsavel['color']); ?>">
                                            <?php echo esc_html(strtoupper($responsavel['initials'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="responsavel-info">
                                    <h3 class="responsavel-name">
                                        <?php echo esc_html($responsavel['name']); ?>
                                    </h3>
                                    <?php if (!empty($responsavel['email'])) : ?>
                                        <p class="responsavel-email">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo esc_html($responsavel['email']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="responsavel-stats">
                                <div class="stat-item">
                                    <div class="stat-icon-small">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-details">
                                        <span class="stat-value-large">
                                            <?php
                                            $hours = floor($responsavel['total_seconds'] / 3600);
                                            $minutes = floor(($responsavel['total_seconds'] % 3600) / 60);
                                            echo sprintf('%dh %02dm', $hours, $minutes);
                                            ?>
                                        </span>
                                        <span class="stat-label-small">Tempo Total</span>
                                    </div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon-small">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <div class="stat-details">
                                        <span class="stat-value-large">
                                            <?php echo number_format($responsavel['task_count']); ?>
                                        </span>
                                        <span class="stat-label-small">Tarefas</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Barra de Progresso Relativa -->
                            <?php if ($total_hours > 0) : ?>
                                <div class="responsavel-progress">
                                    <div class="progress-label">
                                        <span>Contribuição</span>
                                        <span><?php echo number_format(($responsavel['total_seconds'] / 3600 / $total_hours) * 100, 1); ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" 
                                             style="width: <?php echo ($responsavel['total_seconds'] / 3600 / $total_hours) * 100; ?>%; background-color: <?php echo esc_attr($responsavel['color']); ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<style>
/* Estatísticas Gerais */
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-icon.bg-success {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.stat-icon.bg-warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
}

.stat-icon.bg-info {
    background: linear-gradient(135deg, #17a2b8, #007bff);
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    color: #2d3748;
}

.stat-label {
    margin: 0;
    color: #718096;
    font-size: 0.875rem;
}

/* Cards dos Responsáveis */
.responsavel-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
}

.responsavel-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.responsavel-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #f7fafc;
}

.responsavel-avatar {
    flex-shrink: 0;
}

.avatar-img {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e2e8f0;
}

.avatar-placeholder {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    font-weight: 700;
    border: 3px solid rgba(255,255,255,0.3);
}

.responsavel-info {
    flex: 1;
    min-width: 0;
}

.responsavel-name {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 0.25rem 0;
    color: #2d3748;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.responsavel-email {
    font-size: 0.875rem;
    color: #718096;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.responsavel-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-item {
    background: #f7fafc;
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stat-icon-small {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    flex-shrink: 0;
}

.stat-details {
    display: flex;
    flex-direction: column;
}

.stat-value-large {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2d3748;
    line-height: 1;
}

.stat-label-small {
    font-size: 0.75rem;
    color: #718096;
    margin-top: 0.25rem;
}

.responsavel-progress {
    margin-top: 1rem;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: #4a5568;
    font-weight: 600;
}

.progress {
    height: 8px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    transition: width 0.6s ease;
    border-radius: 10px;
}

/* Responsividade */
@media (max-width: 768px) {
    .stat-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .responsavel-card {
        padding: 1.5rem;
    }
    
    .avatar-img, .avatar-placeholder {
        width: 60px;
        height: 60px;
    }
    
    .responsavel-name {
        font-size: 1.1rem;
    }
}

/* Animação de Entrada */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.responsavel-card {
    animation: fadeInUp 0.5s ease forwards;
}

.responsavel-card:nth-child(1) { animation-delay: 0.1s; }
.responsavel-card:nth-child(2) { animation-delay: 0.2s; }
.responsavel-card:nth-child(3) { animation-delay: 0.3s; }
.responsavel-card:nth-child(4) { animation-delay: 0.4s; }
.responsavel-card:nth-child(5) { animation-delay: 0.5s; }
.responsavel-card:nth-child(6) { animation-delay: 0.6s; }
</style>

<?php get_footer(); ?>

