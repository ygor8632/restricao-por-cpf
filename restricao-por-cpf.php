<?php 
/*
Plugin Name: Restrição por CPF
Description: Bloqueia o acesso a páginas e libera conteúdo após validação de CPF via My JSON Server e altera o cargo do usuário.
Version: 1.9
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
                    mensagem.textContent = '✅ Acesso liberado!';
                    conteudo.style.display = 'block';
                } else {
                    mensagem.textContent = data.data || '❌ Erro ao validar CPF.';
                }
                botao.disabled = false;  // Reabilita o botão após a resposta
            })
            .catch(error => {
                console.error('Erro:', error);
                mensagem.textContent = '⚠️ Erro de comunicação com o servidor.';
                botao.disabled = false;
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

// Lógica de validação via My JSON Server
add_action('wp_ajax_rpc_validar_cpf', 'rpc_validar_cpf');
add_action('wp_ajax_nopriv_rpc_validar_cpf', 'rpc_validar_cpf');

function rpc_validar_cpf() {
    // Verifica o nonce para evitar ataques CSRF
    if (!isset($_POST['rpc_nonce']) || !wp_verify_nonce($_POST['rpc_nonce'], 'rpc_nonce_action')) {
        wp_send_json_error('Token de segurança inválido.');
    }

    $cpf = sanitize_text_field($_POST['cpf']);
    $user = wp_get_current_user(); // Pega o usuário logado

    // URL do My JSON Server
    $api_url = "https://my-json-server.typicode.com/ygor8632/restricao-por-cpf/cpfs?cpf={$cpf}";

    // Faz a requisição GET
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('Erro de comunicação com a API: ' . $error_message);
        wp_send_json_error('⚠️ Erro de comunicação com a API.');
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    error_log('Resposta da API: ' . print_r($body, true));

    if ($status_code === 200 && !empty($body)) {
        // Verifica o campo 'status' retornado
        $status = $body[0]['status'] ?? '';

        if ($status === 'valid') {
            // Atribui a função de "assinante"
            if (is_user_logged_in()) {
                $user_id = $user->ID;
                $user_data = [
                    'ID' => $user_id,
                    'role' => 'subscriber' // Altere o cargo conforme necessário
                ];
                wp_update_user($user_data); // Atualiza o cargo do usuário
            }

            wp_send_json_success();
        } else {
            wp_send_json_error('❌ CPF não autorizado.');
        }
    } else {
        wp_send_json_error('❌ CPF não encontrado na base de dados.');
    }
}
