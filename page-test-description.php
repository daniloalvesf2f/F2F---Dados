<?php
/**
 * Template Name: Test Description Pull
 * Página de teste para puxar descrições do Taskrow
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permissão
if (!current_user_can('manage_options')) {
    wp_die('Você não tem permissão para acessar esta página.');
}

get_header();

// Configurações da API
$host_name = get_option('f2f_taskrow_host_name', '');
$api_token = get_option('f2f_taskrow_api_token', '');

// Valores padrão para teste
$default_client = 'GLP';
$default_job = '116';
$default_task = '209563';

// Processar requisição se formulário foi enviado
$json_response = null;
$error_message = null;
$description = null;
$description_source = null;
$debug_info = null;

// Função auxiliar para buscar recursivamente o primeiro campo TaskItemComment
function f2f_find_taskitemcomment_recursive($data, &$path = []) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $path[] = $key;
            if ($key === 'TaskItemComment' && is_string($value) && $value !== '') {
                return ['value' => $value, 'path' => $path];
            }
            $found = f2f_find_taskitemcomment_recursive($value, $path);
            if ($found) {
                return $found;
            }
            array_pop($path);
        }
    }
    return null;
}

if (isset($_POST['test_description']) && wp_verify_nonce($_POST['_wpnonce'], 'test_description_pull')) {
    $client_nickname = sanitize_text_field($_POST['client_nickname']);
    $job_number = sanitize_text_field($_POST['job_number']);
    $task_number = sanitize_text_field($_POST['task_number']);

    if (!$host_name || !$api_token) {
        $error_message = 'Host ou Token não configurados (veja Configurações da API).';
    } elseif ($client_nickname && $job_number && $task_number) {
        // Gerar connectionID (GUID)
        $connection_id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        $url = 'https://' . $host_name . '/api/v1/Task/TaskDetail?clientNickname=' . rawurlencode($client_nickname) . '&jobNumber=' . intval($job_number) . '&taskNumber=' . intval($task_number) . '&connectionID=' . rawurlencode($connection_id);

        $response = wp_remote_request($url, array(
            'method' => 'GET',
            'headers' => array(
                '__identifier'   => $api_token,
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
                'User-Agent'     => 'WordPress/F2F Taskrow Description Test'
            ),
            'timeout' => 45,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            $error_message = 'Erro na requisição: ' . $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code < 200 || $code >= 300) {
                $error_message = 'HTTP Error ' . $code;

                // Debug detalhado para erro 403
                $debug_info = array(
                    'url' => $url,
                    'code' => $code,
                    'host' => $host_name,
                    'token_length' => strlen($api_token),
                    'token_preview' => substr($api_token, 0, 20) . '...',
                    'response_body' => $body
                );

                // Tentar decodificar a resposta de erro
                $error_response = json_decode($body, true);
                if ($error_response) {
                    $json_response = $error_response; // Mostrar o JSON de erro
                }
            } else {
                $json_response = json_decode($body, true);
                $json_error = json_last_error();
                if ($json_error !== JSON_ERROR_NONE) {
                    $error_message = 'Falha ao decodificar JSON: ' . json_last_error_msg();
                    $debug_info = array(
                        'url' => $url,
                        'raw_body' => $body,
                        'json_error' => json_last_error_msg(),
                    );
                } else {
                    // Extrair a descrição conforme documentação e ampliar fallbacks
                    // 1. Campo direto
                    if (isset($json_response['TaskData']['TaskItemComment']) && is_string($json_response['TaskData']['TaskItemComment'])) {
                        $description = $json_response['TaskData']['TaskItemComment'];
                        $description_source = 'TaskData.TaskItemComment';
                    }
                    // 2. TaskItems (lista genérica)
                    if (!$description && isset($json_response['TaskData']['TaskItems'][0]['TaskItemComment'])) {
                        $description = $json_response['TaskData']['TaskItems'][0]['TaskItemComment'];
                        $description_source = 'TaskData.TaskItems[0].TaskItemComment';
                    }
                    // 3. NewTaskItems
                    if (!$description && isset($json_response['TaskData']['NewTaskItems'][0]['TaskItemComment'])) {
                        $description = $json_response['TaskData']['NewTaskItems'][0]['TaskItemComment'];
                        $description_source = 'TaskData.NewTaskItems[0].TaskItemComment';
                    }
                    // 4. ExternalTaskItems
                    if (!$description && isset($json_response['TaskData']['ExternalTaskItems'][0]['TaskItemComment'])) {
                        $description = $json_response['TaskData']['ExternalTaskItems'][0]['TaskItemComment'];
                        $description_source = 'TaskData.ExternalTaskItems[0].TaskItemComment';
                    }
                    // 5. Busca recursiva em qualquer lugar
                    if (!$description) {
                        $path = [];
                        $found = f2f_find_taskitemcomment_recursive($json_response, $path);
                        if ($found) {
                            $description = $found['value'];
                            $description_source = implode(' > ', $found['path']);
                        }
                    }
                }
            }
        }
    } else {
        $error_message = 'Por favor, preencha todos os campos.';
    }
}
?>

<div class="wrap" style="max-width: 1400px; margin: 20px auto; padding: 20px;">
    <h1>🔍 Test Description Pull - Taskrow API</h1>

    <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2>Configurações da API</h2>
        <p><strong>Host:</strong> <?php echo esc_html($host_name ?: 'Não configurado'); ?></p>
        <p><strong>API Token:</strong> <?php echo $api_token ? '✅ Configurado' : '❌ Não configurado'; ?></p>
        <?php if (!$host_name || !$api_token): ?>
            <div style="margin-top:15px; background:#fff3cd; color:#856404; padding:10px; border:1px solid #ffeeba; border-radius:4px;">
                ⚠ Configure o Host e Token em Opções antes de testar.
            </div>
        <?php endif; ?>
    </div>

    <form method="post"
        style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <?php wp_nonce_field('test_description_pull'); ?>

        <h2>Puxar Descrição de Task</h2>
        <p style="color: #666; margin-bottom: 20px;">Preencha os dados abaixo para buscar a descrição (TaskItemComment)
            de uma task específica.</p>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Client Nickname:</label>
            <input type="text" name="client_nickname"
                value="<?php echo isset($_POST['client_nickname']) ? esc_attr($_POST['client_nickname']) : $default_client; ?>"
                style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
            <small style="display: block; color: #666; margin-top: 5px;">Exemplo: GLP, ChegoLa, ProjetoStella,
                Medtronic</small>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Job Number:</label>
            <input type="text" name="job_number"
                value="<?php echo isset($_POST['job_number']) ? esc_attr($_POST['job_number']) : $default_job; ?>"
                style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
            <small style="display: block; color: #666; margin-top: 5px;">Número do projeto/job</small>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Task Number:</label>
            <input type="text" name="task_number"
                value="<?php echo isset($_POST['task_number']) ? esc_attr($_POST['task_number']) : $default_task; ?>"
                style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
            <small style="display: block; color: #666; margin-top: 5px;">Número da task específica</small>
        </div>

        <button type="submit" name="test_description"
            style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
            🚀 Buscar Descrição
        </button>
    </form>

    <?php if ($error_message): ?>
        <div
            style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border: 1px solid #f5c6cb; margin-bottom: 20px;">
            <strong>❌ Erro:</strong> <?php echo esc_html($error_message); ?>

            <?php if (isset($debug_info)): ?>
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; font-weight: bold; padding: 5px;">🔍 Ver Detalhes de Debug</summary>
                    <div
                        style="background: #fff; padding: 10px; border-radius: 4px; margin-top: 10px; font-family: monospace; font-size: 12px;">
                        <p><strong>URL:</strong> <?php echo esc_html($debug_info['url']); ?></p>
                        <p><strong>Host:</strong> <?php echo esc_html($debug_info['host']); ?></p>
                        <p><strong>Token Length:</strong> <?php echo esc_html($debug_info['token_length']); ?> caracteres</p>
                        <p><strong>Token Preview:</strong> <?php echo esc_html($debug_info['token_preview']); ?></p>
                        <p><strong>HTTP Code:</strong> <?php echo esc_html($debug_info['code']); ?></p>
                        <p><strong>Response Body:</strong></p>
                        <pre
                            style="background: #f5f5f5; padding: 10px; overflow-x: auto;"><?php echo esc_html($debug_info['response_body']); ?></pre>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($description): ?>
        <div
            style="background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; border: 1px solid #c3e6cb; margin-bottom: 20px;">
            <h2>✅ Descrição Encontrada!</h2>
            <?php if ($description_source): ?>
                <p style="font-size:13px; margin-top:5px;">Origem localizada: <code><?php echo esc_html($description_source); ?></code></p>
            <?php endif; ?>

            <h3>TaskItemComment (com HTML):</h3>
            <div
                style="background: #fff; padding: 15px; border-radius: 4px; margin-bottom: 15px; max-height: 300px; overflow-y: auto;">
                <pre
                    style="white-space: pre-wrap; word-wrap: break-word; font-size: 12px;"><?php echo esc_html($description); ?></pre>
            </div>

            <h3>TaskItemComment (HTML renderizado):</h3>
            <div style="background: #fff; padding: 15px; border-radius: 4px; margin-bottom: 15px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd;">
                <?php echo wp_kses_post($description); ?>
            </div>

            <h3>TaskItemComment (apenas texto):</h3>
            <div style="background: #fff; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                <?php echo nl2br(esc_html(strip_tags($description))); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($json_response): ?>
        <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>📋 Resposta Completa da API (JSON)</h2>

            <div style="margin-bottom: 20px;">
                <h3>🔑 Estrutura do JSON:</h3>
                <div style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace;">
                    <?php echo implode(', ', array_keys($json_response)); ?>
                </div>
            </div>

            <?php if (isset($json_response['TaskData'])): ?>
                <div style="margin-bottom: 20px;">
                    <h3>🔑 Campos em TaskData:</h3>
                    <div
                        style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                        <?php echo implode(', ', array_keys($json_response['TaskData'])); ?>
                    </div>
                </div>

                <?php if (isset($json_response['TaskData']['TaskItemComment'])): ?>
                    <div
                        style="background: #d4edda; padding: 15px; border-radius: 4px; border: 1px solid #c3e6cb; margin-bottom: 20px;">
                        <p>✅ <strong>TaskItemComment encontrado diretamente em TaskData</strong></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($json_response['TaskData']['NewTaskItems'])): ?>
                    <div style="margin-bottom: 20px;">
                        <h3>📦 NewTaskItems:</h3>
                        <p>Total de items: <?php echo count($json_response['TaskData']['NewTaskItems']); ?></p>
                        <?php if (!empty($json_response['TaskData']['NewTaskItems'])): ?>
                            <div
                                style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                                Campos do primeiro item:
                                <?php echo implode(', ', array_keys($json_response['TaskData']['NewTaskItems'][0])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($json_response['TaskData']['ExternalTaskItems'])): ?>
                    <div style="margin-bottom: 20px;">
                        <h3>📦 ExternalTaskItems:</h3>
                        <p>Total de items: <?php echo count($json_response['TaskData']['ExternalTaskItems']); ?></p>
                        <?php if (!empty($json_response['TaskData']['ExternalTaskItems'])): ?>
                            <div
                                style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                                Campos do primeiro item:
                                <?php echo implode(', ', array_keys($json_response['TaskData']['ExternalTaskItems'][0])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <details style="margin-top: 20px;">
                <summary
                    style="cursor: pointer; padding: 10px; background: #0073aa; color: white; border-radius: 4px; font-weight: bold;">
                    📄 Ver JSON Completo (clique para expandir)
                </summary>
                <pre
                    style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 600px; overflow-y: auto; margin-top: 10px; font-size: 11px;"><?php echo esc_html(json_encode($json_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </details>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>