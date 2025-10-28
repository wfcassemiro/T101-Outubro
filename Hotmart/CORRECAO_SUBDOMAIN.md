# Correção: Adição do parâmetro subdomain nas chamadas de progresso

## ✅ Problema Identificado

A API da Hotmart estava retornando "Nenhum dado de progresso encontrado" porque o parâmetro `subdomain` não estava sendo enviado nas requisições de progresso do usuário, conforme exigido pela documentação oficial.

## 📝 Arquivo Corrigido

**Local:** `/app/temp_repo/public_html/v/hotmart.php`

Este é o arquivo PRINCIPAL da integração com Hotmart que contém a classe `HotmartAPI`.

## 🔧 Alterações Realizadas

### Função: `getUserProgress()`

**Antes:**
```php
public function getUserProgress($userId) {
    // Chamadas sem o parâmetro subdomain
    $candidates[] = ['url' => "/users/{$userId}/lessons", 'useHotToken' => true];
}
```

**Depois:**
```php
public function getUserProgress($userId, $subdomain = null) {
    // Obter subdomain das constantes se não fornecido
    if (empty($subdomain)) {
        $subdomain = defined('HOTMART_SUBDOMAIN') ? HOTMART_SUBDOMAIN : 't101';
    }
    
    // Chamadas COM o parâmetro subdomain
    $candidates[] = ['url' => "/users/{$userId}/lessons", 'params' => ['subdomain' => $subdomain], 'useHotToken' => true];
}
```

## 📊 Endpoints Afetados

Agora TODAS as chamadas de progresso incluem `?subdomain=t101`:

1. ✅ `GET /users/{ucode}/lessons?subdomain=t101`
2. ✅ `GET /users/{ucode}/modules/pages?subdomain=t101&status=COMPLETED`

## 🎯 Formato Esperado pela Hotmart

Conforme documentação oficial:
```bash
curl --location --request GET 'https://developers.hotmart.com/club/api/v1/users/{user_id}/lessons?subdomain=my-subdomain' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer :access_token'
```

## 📍 Configuração do Subdomain

O subdomain é obtido de:
1. **Parâmetro da função** (se fornecido)
2. **Constante HOTMART_SUBDOMAIN** (definida em `/app/temp_repo/public_html/v/config/hotmart.php`)
3. **Valor padrão** `'t101'` (fallback)

## 🧪 Como Testar

Após essa correção, execute:

```bash
# Acesse via navegador:
http://seu-dominio/v/hotmart/test_sync_local.php

# Ou via linha de comando:
cd /app/temp_repo/public_html/v/hotmart
php test_sync_local.php
```

Verifique nos logs se as URLs agora incluem `?subdomain=t101`.

## 📝 Logs de Referência

Antes:
```
[HOTMART_API_REQUEST] Fazendo requisição GET para: https://developers.hotmart.com/club/api/v1/users/abc123/lessons
```

Depois:
```
[HOTMART_API_REQUEST] Fazendo requisição GET para: https://developers.hotmart.com/club/api/v1/users/abc123/lessons?subdomain=t101
```

---

**Data da correção:** 2025
**Arquivo modificado:** `/app/temp_repo/public_html/v/hotmart.php`
**Função modificada:** `getUserProgress($userId, $subdomain = null)`
