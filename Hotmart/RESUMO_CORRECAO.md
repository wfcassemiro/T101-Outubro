# 📋 RESUMO DA CORREÇÃO - Parâmetro subdomain adicionado

## ✅ ARQUIVO CORRIGIDO

**Localização:** `/app/temp_repo/public_html/v/hotmart.php`

**Descrição:** Arquivo principal da classe `HotmartAPI` responsável por todas as chamadas à API da Hotmart.

---

## 🔍 O QUE FOI CORRIGIDO

### Função Modificada: `getUserProgress()`

**Linha da função:** 212-249

### Mudança Principal:

**ANTES:**
- Parâmetro: `getUserProgress($userId)`
- Chamadas SEM o parâmetro `subdomain`
- URL gerada: `https://developers.hotmart.com/club/api/v1/users/ABC123/lessons`

**DEPOIS:**
- Parâmetro: `getUserProgress($userId, $subdomain = null)`
- Chamadas COM o parâmetro `subdomain=t101`
- URL gerada: `https://developers.hotmart.com/club/api/v1/users/ABC123/lessons?subdomain=t101`

---

## 📝 CÓDIGO COMPLETO CORRIGIDO

```php
public function getUserProgress($userId, $subdomain = null) {
    // Obter subdomain das constantes se não fornecido
    if (empty($subdomain)) {
        $subdomain = defined('HOTMART_SUBDOMAIN') ? HOTMART_SUBDOMAIN : 't101';
    }
    
    error_log("[HOTMART_USER_PROGRESS] Buscando progresso para usuário: {$userId} no subdomain: {$subdomain}");
    
    // $userId pode ser ucode ou numeric
    $candidates = [];
    
    // se parece UUID (contém '-'), tente como ucode
    if (strpos($userId, '-') !== false) {
        $candidates[] = ['url' => "/users/{$userId}/lessons", 'params' => ['subdomain' => $subdomain], 'useHotToken' => true];
        $candidates[] = ['url' => "/users/{$userId}/modules/pages", 'params' => ['subdomain' => $subdomain, 'status'=>'COMPLETED'], 'useHotToken' => true];
    }
    
    // tentar subscriber_code (curto) e id numérico também
    $candidates[] = ['url' => "/users/{$userId}/lessons", 'params' => ['subdomain' => $subdomain], 'useHotToken' => true];
    $candidates[] = ['url' => "/users/{$userId}/modules/pages", 'params' => ['subdomain' => $subdomain, 'status'=>'COMPLETED'], 'useHotToken' => true];
    
    // depois tentar com access token (useHotToken=false)
    foreach ($candidates as $c) {
        $params = $c['params'] ?? [];
        $res = $this->_makeRequest($c['url'], 'GET', [], $params, $this->clubBaseUrl, $c['useHotToken']);
        if ($res['success'] && isset($res['data']) && !empty($res['data'])) {
            // Check if we have items or pages data
            if (isset($res['data']['items']) || isset($res['data']['pages']) || (is_array($res['data']) && count($res['data']) > 0)) {
                error_log("[HOTMART_USER_PROGRESS_SUCCESS] Progresso encontrado para usuário {$userId}");
                return $res;
            }
        }
        error_log("[HOTMART_USER_PROGRESS_ATTEMPT] Tentativa falhou para {$c['url']}: " . json_encode($res));
    }
    
    error_log("[HOTMART_USER_PROGRESS_FAIL] Nenhuma tentativa retornou dados válidos para usuário {$userId}");
    return ['success' => false, 'message' => 'Nenhum dado de progresso encontrado'];
}
```

---

## 📊 ENDPOINTS AFETADOS

Agora as seguintes URLs incluem o parâmetro `subdomain`:

1. ✅ `GET /users/{ucode}/lessons?subdomain=t101`
2. ✅ `GET /users/{ucode}/modules/pages?subdomain=t101&status=COMPLETED`

---

## 🎯 CONFORME DOCUMENTAÇÃO HOTMART

A correção agora segue o formato exato especificado pela documentação oficial:

```bash
curl --location --request GET 'https://developers.hotmart.com/club/api/v1/users/{user_id}/lessons?subdomain=my-subdomain' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer :access_token'
```

---

## 🧪 COMO TESTAR A CORREÇÃO

### Opção 1: Via Interface Web
Acesse no navegador:
```
http://seu-dominio/v/hotmart/test_sync_local.php
```

### Opção 2: Via Linha de Comando
```bash
cd /app/temp_repo/public_html/v/hotmart
php test_sync_local.php
```

### O que verificar nos logs:
- ✅ URLs devem incluir `?subdomain=t101`
- ✅ Respostas HTTP 200 devem retornar dados de progresso
- ✅ Mensagem "[HOTMART_USER_PROGRESS_SUCCESS] Progresso encontrado"

---

## 📍 CONFIGURAÇÃO DO SUBDOMAIN

O valor do subdomain é obtido na seguinte ordem de prioridade:

1. **Parâmetro da função** `getUserProgress($userId, 't101')`
2. **Constante** `HOTMART_SUBDOMAIN` (em `/app/temp_repo/public_html/v/config/hotmart.php`)
3. **Valor padrão** `'t101'` (caso nenhum dos anteriores esteja disponível)

---

## 📂 ARQUIVOS RELACIONADOS

- **Arquivo principal corrigido:** `/app/temp_repo/public_html/v/hotmart.php`
- **Arquivo de configuração:** `/app/temp_repo/public_html/v/config/hotmart.php`
- **Arquivo de sincronização:** `/app/Hotmart/HotmartProgressSyncLocal.php`
- **Arquivo de teste:** `/app/Hotmart/test_sync_local.php`

---

## ⚠️ IMPORTANTE

Esta correção é CRÍTICA para o funcionamento da API. Sem o parâmetro `subdomain`, a API da Hotmart não consegue identificar qual área de membros consultar, resultando em respostas vazias.

---

## 📧 PARA O SUPORTE HOTMART

Quando o suporte perguntou: *"essa configuração do API que fez foi via webhook ou você só integrou esse PHP mesmo?"*

**Resposta técnica:**

A integração é feita **diretamente via REST API em PHP** (não via webhook). O sistema faz chamadas HTTP do tipo GET/POST aos seguintes endpoints:

1. **Autenticação OAuth 2.0:**
   - `POST https://api-sec-vlc.hotmart.com/security/oauth/token`
   - Grant type: `client_credentials`

2. **Buscar Assinaturas:**
   - `GET https://developers.hotmart.com/payments/api/v1/subscriptions`
   - Autenticação: Bearer Token

3. **Buscar Progresso do Usuário:**
   - `GET https://developers.hotmart.com/club/api/v1/users/{ucode}/lessons?subdomain=t101`
   - Autenticação: HOT Token

**Tipo de integração:** Pull (busca ativa) - O sistema consulta a API periodicamente para obter dados.

**Correção realizada:** Adição do parâmetro `subdomain=t101` nas requisições de progresso, conforme documentação oficial da API Club.

---

**Data:** 2025  
**Status:** ✅ CORRIGIDO  
**Próximo passo:** Testar e verificar se a API retorna dados de progresso
