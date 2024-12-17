<?php
/*
Plugin Name: Restrição por CPF
Description: Bloqueia o acesso a páginas e libera conteúdo após validação de CPF via API externa.
Version: 1.7
Author: Ygor Souza
*/

if (!defined('ABSPATH')) {
    exit;
}

// Adiciona o shortcode para exibir a página restrita
add_shortcode('pagina_restrita', 'rpc_render_pagina_restrita');

function rpc_render_pagina_restrita() {
    ob_start(); ?>
    <div id="rpc-area-restrita">
        <form id="rpc-form-cpf" method="POST">
            <label for="cpf">Digite seu CPF:</label>
            <input type="text" id="cpf" name="cpf" maxlength="11" required>
            <input type="hidden" name="rpc_nonce" value="<?php echo wp_create_nonce('rpc_nonce_action'); ?>">
            <button type="submit">Verificar</button>
        </form>
        <div id="rpc-mensagem"></div>
        <div id="rpc-conteudo-restrito" style="display: none;">
            <h2>Bem-vindo!</h2>
            <p>Esse é o conteúdo protegido.</p>
        </div>
    </div>
    <script>
        document.getElementById('rpc-form-cpf').addEventListener('submit', function(event) {
            event.preventDefault();

            let cpf = document.getElementById('cpf').value;
            let nonce = document.querySelector('[name="rpc_nonce"]').value;

            const botao = document.querySelector('button');
            const mensagem = document.getElementById('rpc-mensagem');
            const conteudo = document.getElementById('rpc-conteudo-restrito');

            botao.disabled = true;  // Desabilita o botão enquanto processa
            mensagem.textContent = 'Verificando CPF...';

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'rpc_validar_cpf',
                    cpf: cpf,
                    rpc_nonce: nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mensagem.textContent = 'Acesso liberado!';
                    conteudo.style.display = 'block';
                } else {
                    mensagem.textContent = data.data || 'Erro ao validar CPF.';
                }
                botao.disabled = false;  // Reabilita o botão após a resposta
            })
            .catch(error => {
                console.error('Erro:', error);
                mensagem.textContent = 'Erro de comunicação com o servidor.';
                botao.disabled = false;
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

// Lógica de validação via API externa
add_action('wp_ajax_rpc_validar_cpf', 'rpc_validar_cpf');
add_action('wp_ajax_nopriv_rpc_validar_cpf', 'rpc_validar_cpf');

function rpc_validar_cpf() {
    // Verifica o nonce para evitar ataques CSRF
    if (!isset($_POST['rpc_nonce']) || !wp_verify_nonce($_POST['rpc_nonce'], 'rpc_nonce_action')) {
        wp_send_json_error('Token de segurança inválido.');
    }

    $cpf = sanitize_text_field($_POST['cpf']);


    $api_url = 'http://127.0.0.1:3000/validar-cpf';  
    $api_key = ''; 

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => "Bearer $api_key",  
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode(['cpf' => $cpf]),
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message(); 
        error_log('Erro de comunicação com a API: ' . $error_message); 
        wp_send_json_error('Erro de comunicação com a API: ' . $error_message);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    error_log('Resposta da API: ' . print_r($body, true)); 

    if ($status_code === 200 && isset($body['valid'])) {
        if ($body['valid']) {
            wp_send_json_success(); 
        } else {
            wp_send_json_error('CPF não autorizado.');
        }
    } else {
        wp_send_json_error('Erro ao validar CPF. Código da resposta: ' . $status_code);
    }
}
