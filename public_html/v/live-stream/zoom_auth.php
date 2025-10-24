<?php
/**
 * Sistema de Autenticação OAuth 2.0 Server-to-Server do Zoom
 * com Geração de Log para Diagnóstico
 *
 * Observações importantes:
 * - Este script usa o fluxo "account_credentials" (Server-to-Server OAuth).
 * - O Zoom exige que os parâmetros `grant_type` e `account_id` sejam enviados
 *   no corpo do POST como application/x-www-form-urlencoded.
 * - Escopos granulares (usar exatamente o formato que você já usa no projeto):
 *   Exemplo de formato granular:
 *     meeting:write:meeting:admin
 *     meeting:read:meeting:admin
 *     meeting:update:meeting:admin
 *     meeting:delete:meeting:admin
 *     user:read:user:admin
 */

require_once 'zoom_config.php';

// --- FUNÇÃO DE LOG ---
function writeToZoomLog($message) {
    $logFile = __DIR__ . '/zoom_debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] " . $message . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
// ----

function getZoomAccessToken($forceRefresh = false) {
    $cacheFile = sys_get_temp_dir() . '/zoom_token_cache.json';

    // Inicia o log para esta requisição
    writeToZoomLog("==== NOVA REQUISIÇÃO DE TOKEN ====");

    if (!$forceRefresh && file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if (isset($cacheData['expires_at']) && $cacheData['expires_at'] > (time() + 300)) {
            writeToZoomLog("LOG: Usando token válido do cache. Expira em: " . date('Y-m-d H:i:s', $cacheData['expires_at']));
            return $cacheData['access_token'];
        } else {
            writeToZoomLog("LOG: Token em cache expirado ou inválido. Solicitando um novo.");
        }
    } else {
        writeToZoomLog("LOG: Cache vazio ou refresh forçado. Solicitando um novo token.");
    }

    // Preparar credenciais e corpo do POST corretamente (form-urlencoded)
    $credentials = base64_encode(ZOOM_CLIENT_ID . ':' . ZOOM_CLIENT_SECRET);
    $requestUrl = ZOOM_OAUTH_TOKEN_URL; // enviar parâmetros no body
    $postFields = http_build_query([
        'grant_type' => 'account_credentials',
        'account_id' => ZOOM_ACCOUNT_ID
    ]);

    writeToZoomLog("URL da Requisição: " . $requestUrl);
    writeToZoomLog("Cabeçalho de Autorização: Basic " . substr($credentials, 0, 15) . "... (base64 ocultado no log)");

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $requestUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        writeToZoomLog("ERRO cURL: " . $curlErr);
        return false;
    }

    writeToZoomLog("LOG: Resposta HTTP Code: " . $httpCode);
    writeToZoomLog("LOG: Resposta Bruta da API: " . $response);

    $data = json_decode($response, true);

    if ($httpCode !== 200 || !isset($data['access_token'])) {
        // Log detalhado para diagnóstico
        $reason = $data['reason'] ?? ($data['error'] ?? ($data['message'] ?? 'Sem motivo informado'));
        writeToZoomLog("ERRO: Falha ao obter token. HTTP Code: {$httpCode}. Motivo: " . $reason);
        writeToZoomLog("ERRO: Resposta decodificada: " . json_encode($data));
        return false;
    }

    // Logar os escopos recebidos (se houver)
    if (isset($data['scope'])) {
        writeToZoomLog("IMPORTANTE: Escopos recebidos no token: " . $data['scope']);
    } else {
        writeToZoomLog("AVISO: A resposta do token não continha o campo 'scope'.");
    }

    $cacheData = [
        'access_token' => $data['access_token'],
        'expires_at' => time() + ($data['expires_in'] ?? 3600),
        'created_at' => time()
    ];
    file_put_contents($cacheFile, json_encode($cacheData));

    writeToZoomLog("LOG: Novo token obtido e salvo em cache com sucesso.");
    writeToZoomLog("====");

    return $data['access_token'];
}

function zoomApiRequest($endpoint, $method = 'GET', $data = null, $retry = true) {
    $token = getZoomAccessToken();
    if (!$token) {
        return ['success' => false, 'error' => 'Não foi possível obter token de autenticação'];
    }

    $url = ZOOM_API_BASE_URL . $endpoint;

    $ch = curl_init();
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'];
    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ];

    if (strtoupper($method) === 'POST') {
        $curlOptions[CURLOPT_POST] = true;
        if ($data) $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif (strtoupper($method) === 'PATCH' || strtoupper($method) === 'PUT') {
        $curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
        if ($data) $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif (strtoupper($method) === 'DELETE') {
        $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }

    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => 'Erro de conexão: ' . $curlErr];
    }

    $responseData = json_decode($response, true);

    // Se token expirou / inválido: tentar refresh uma vez
    if ($httpCode === 401 && $retry) {
        writeToZoomLog("LOG: 401 recebida, tentando renovar token e reexecutar requisição.");
        $newToken = getZoomAccessToken(true);
        if ($newToken) {
            return zoomApiRequest($endpoint, $method, $data, false);
        }
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $responseData, 'http_code' => $httpCode];
    } else {
        $errorMessage = $responseData['message'] ?? ($responseData['error'] ?? 'Erro desconhecido (HTTP ' . $httpCode . ')');
        // Mensagem de ajuda para escopos
        if (strpos(strtolower(json_encode($responseData)), 'scope') !== false && strpos($errorMessage, 'does not contain scopes') !== false) {
            $errorMessage .= ' - SOLUÇÃO: Configure os escopos granulares no Zoom App Marketplace.';
        }
        return ['success' => false, 'error' => $errorMessage, 'http_code' => $httpCode, 'response' => $responseData];
    }
}