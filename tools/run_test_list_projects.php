<?php
// Arquivo temporário para executar handler de listagem de projetos via CLI PHP
require __DIR__ . '/../../../../wp-load.php';
// Chama a action que retorna JSON e dá exit
try {
    do_action('wp_ajax_f2f_test_list_projects');
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}
