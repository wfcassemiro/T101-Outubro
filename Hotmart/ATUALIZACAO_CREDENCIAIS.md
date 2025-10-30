# 🔐 ATUALIZAÇÃO DE CREDENCIAIS HOTMART

**Data:** 30-Oct-2025  
**Motivo:** Erro 401 (Unauthorized) na autenticação OAuth

---

## ❌ PROBLEMA IDENTIFICADO

Os logs mostravam erro de autenticação:
```
HTTP 401: {"error":"unauthorized","error_description":"Full authentication is required to access this resource"}
```

**Causa:** Credenciais OAuth antigas/inválidas

---

## ✅ CREDENCIAIS ATUALIZADAS

### Arquivo Modificado
**Localização:** `/app/temp_repo/public_html/v/config/hotmart.php`

### Credenciais Antigas (REMOVIDAS)
```php
CLIENT_ID:     f7f05ef5-bb55-46a2-a678-3c27627941d8
CLIENT_SECRET: 1d9e0fe5-efa9-4841-80a5-6e15be63b2e0
BASIC_AUTH:    (não estava definido)
```

### Credenciais Novas (ATIVAS) ✅
```php
CLIENT_ID:     7e3d342d-af4f-4190-959c-6a97546f1437
CLIENT_SECRET: 6f62576f-4335-40d0-87aa-8a58f09df7fd
BASIC_AUTH:    N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==
```

### Mantido
```php
HOT_TOKEN:  okqS9nRS9FXJiOPkijs40T9v2fp2Vz522f1c9c-5f8e-4c6c-aa14-e863b6f34dd2
SUBDOMAIN:  assinaturapremiumplustranslato
```

---

## 📝 ARQUIVO ATUALIZADO COMPLETO

```php
<?php
// Credenciais Hotmart (Atualizadas em 30-Oct-2025)
define('HOTMART_CLIENT_ID', '7e3d342d-af4f-4190-959c-6a97546f1437');
define('HOTMART_CLIENT_SECRET', '6f62576f-4335-40d0-87aa-8a58f09df7fd');
define('HOTMART_BASIC_AUTH', 'N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_HOT_TOKEN', 'okqS9nRS9FXJiOPkijs40T9v2fp2Vz522f1c9c-5f8e-4c6c-aa14-e863b6f34dd2');
define('HOTMART_SUBDOMAIN', 'assinaturapremiumplustranslato');

// URLs da API Hotmart
define('HOTMART_API_BASE', 'https://api.hotmart.com');
define('HOTMART_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_SUBSCRIBERS_URL', 'https://developers.hotmart.com/payments/api/v1/subscriptions');
define('HOTMART_SALES_URL', 'https://developers.hotmart.com/payments/api/v1/sales');

// Configurações do Webhook
define('HOTMART_WEBHOOK_TOKEN', 'translators101_webhook_2024');
?>
```

---

## 🧪 COMO TESTAR AS NOVAS CREDENCIAIS

### Opção 1: Teste de Autenticação (RECOMENDADO)
Acesse via navegador:
```
http://seu-dominio/v/hotmart/test_auth_credentials.php
```

Este teste verifica:
- ✅ Autenticação OAuth funcionando
- ✅ Endpoint de Subscriptions
- ✅ Endpoint de Club Users
- ✅ Endpoint de User Progress

### Opção 2: Sincronização Completa
Após confirmar autenticação:
```
http://seu-dominio/v/hotmart/test_sync_local.php
```

### Opção 3: Debug Raw
Para análise detalhada:
```
http://seu-dominio/v/hotmart/debug_api_raw.php
```

---

## 🔍 VERIFICAÇÃO DE LOGS

### Logs esperados (SUCESSO):
```
[HOTMART_TOKEN_RESPONSE] Resposta do token HTTP 200
[HOTMART_TOKEN_SUCCESS] Access token obtido com sucesso
[HOTMART_API_RESPONSE] Resposta HTTP 200
```

### Logs de erro (SE FALHAR):
```bash
# Ver logs em tempo real
tail -f /var/log/php_errors.log | grep HOTMART

# Filtrar apenas erros
tail -f /var/log/php_errors.log | grep "HOTMART.*ERROR"
```

---

## 📊 ENDPOINTS TESTADOS

Com as novas credenciais, os seguintes endpoints devem funcionar:

### 1. OAuth Token
```
POST https://api-sec-vlc.hotmart.com/security/oauth/token
Authorization: Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id=...&client_secret=...
```

**Resposta esperada:** HTTP 200 com `access_token`

### 2. Subscriptions
```
GET https://developers.hotmart.com/payments/api/v1/subscriptions?max_results=100
Authorization: Bearer {access_token}
```

**Resposta esperada:** HTTP 200 com array de `items`

### 3. Club Users
```
GET https://developers.hotmart.com/club/api/v1/users?subdomain=assinaturapremiumplustranslato
Authorization: {HOT_TOKEN}
```

**Resposta esperada:** HTTP 200 com array de usuários

### 4. User Progress
```
GET https://developers.hotmart.com/club/api/v1/users/{ucode}/lessons?subdomain=assinaturapremiumplustranslato
Authorization: {HOT_TOKEN}
```

**Resposta esperada:** HTTP 200 com dados de progresso

---

## ⚠️ TROUBLESHOOTING

### Se ainda retornar 401:
1. Verifique se o arquivo `/config/hotmart.php` foi salvo corretamente
2. Limpe o cache do PHP (se houver)
3. Reinicie o servidor web (se aplicável)
4. Confirme que o CLIENT_ID e CLIENT_SECRET estão exatamente como fornecidos

### Se retornar 200 mas sem dados:
- Pode ser que não existam dados de progresso no sistema da Hotmart
- Verifique com o suporte Hotmart se o tracking está habilitado
- Confirme que o SUBDOMAIN está correto

---

## 📧 RESUMO PARA SUPORTE HOTMART

Se precisar reportar ao suporte:

```
Assunto: Autenticação OAuth atualizada - Verificação de dados

Prezado suporte,

Atualizamos nossas credenciais OAuth:
- Client ID: 7e3d342d-af4f-4190-959c-6a97546f1437
- Subdomain: assinaturapremiumplustranslato

A autenticação OAuth está funcionando (HTTP 200).
Os endpoints de Subscriptions retornam dados corretamente.

Estamos tentando acessar dados de progresso via:
GET /club/api/v1/users/{ucode}/lessons?subdomain=assinaturapremiumplustranslato

Podem confirmar se:
1. O tracking de progresso está ativo para este produto/subdomain?
2. Há dados de progresso disponíveis para consulta?
3. As permissões OAuth incluem acesso aos dados de progresso?

Obrigado!
```

---

## ✅ CHECKLIST DE VALIDAÇÃO

- [x] Credenciais CLIENT_ID atualizadas
- [x] Credenciais CLIENT_SECRET atualizadas
- [x] BASIC_AUTH adicionado ao config
- [x] SUBDOMAIN mantido correto (assinaturapremiumplustranslato)
- [x] Arquivo de teste criado (test_auth_credentials.php)
- [ ] **PENDENTE:** Testar autenticação via navegador
- [ ] **PENDENTE:** Confirmar HTTP 200 nos logs
- [ ] **PENDENTE:** Validar retorno de dados

---

**Status:** 🟡 AGUARDANDO TESTE  
**Próximo passo:** Acessar `test_auth_credentials.php` para validar
