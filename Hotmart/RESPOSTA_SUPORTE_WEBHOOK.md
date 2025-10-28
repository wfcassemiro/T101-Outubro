# 📧 Resposta ao Suporte Hotmart

## Resposta à Pergunta sobre Webhook vs API

---

Oi, obrigado pelo retorno!

Para esclarecer: estou fazendo **integração direta via API REST** (chamadas GET/POST em PHP), **NÃO via webhook**.

### 🔄 O QUE ESTOU FAZENDO

**Integração via API REST (Pull - eu busco os dados):**

```php
// 1. Autenticação OAuth
POST https://api-sec-vlc.hotmart.com/security/oauth/token
   → Obtenho access_token ✅ FUNCIONANDO

// 2. Buscar assinaturas
GET https://developers.hotmart.com/payments/api/v1/subscriptions
   → Retorna 41 assinantes ativos ✅ FUNCIONANDO

// 3. Buscar progresso (ESTE É O PROBLEMA)
GET https://developers.hotmart.com/club/api/v1/users/{ucode}/lessons
   → Retorna vazio ❌ NÃO FUNCIONA
```

### 📥 O QUE JÁ TENHO CONFIGURADO

**Sim, TAMBÉM tenho webhook configurado:**

- **URL do Webhook:** https://translators101.com/public_html/v/hotmart_webhook.php
- **Eventos recebidos:**
  - PURCHASE_COMPLETE
  - SUBSCRIPTION_CREATED
  - SUBSCRIPTION_CANCELLATION
  - Etc.

**O webhook funciona perfeitamente** e recebe dados de:
- Novas compras
- Criação de assinaturas
- Cancelamentos
- Dados do subscriber (name, email, ucode)

### ❓ QUAL A DIFERENÇA

#### Webhook (Push) ✅ FUNCIONANDO
```
Hotmart → envia evento → Meu servidor
Uso: Criar usuário quando há nova compra
```

#### API REST (Pull) ❌ NÃO FUNCIONA
```
Meu servidor → consulta → Hotmart API
Uso: Buscar progresso de aulas
```

### 🎯 O QUE PRECISO

**Pergunta específica:** Existe algum **webhook de progresso de aulas**?

Por exemplo, algo como:
- `LESSON_STARTED` - Quando usuário inicia aula
- `LESSON_COMPLETED` - Quando usuário completa aula
- `LESSON_PROGRESS` - Progresso percentual

**OU**

A API REST `/users/{ucode}/lessons` deveria funcionar mas não está retornando dados. Por quê?

### 🔍 DETALHES TÉCNICOS

**Webhook atual (hotmart_webhook.php):**
```php
// Recebe eventos via POST
$event = $_POST['event'] ?? null;
$data = $_POST['data'] ?? [];

switch($event) {
    case 'PURCHASE_COMPLETE':
        // Cria usuário no banco
        break;
    case 'SUBSCRIPTION_CREATED':
        // Atualiza dados de assinatura
        break;
    // ... outros eventos
}
```

**API REST atual (tentativa de buscar progresso):**
```php
// Busco dados via GET
$token = getAccessToken(); // ✅ Funciona
$subscriptions = getSubscriptions(); // ✅ Funciona
$progress = getUserProgress($ucode); // ❌ Vazio

// Endpoint tentado:
// GET /club/api/v1/users/{ucode}/lessons
// Retorna: "Nenhum dado de progresso encontrado"
```

### 📋 INFORMAÇÕES ADICIONAIS

**Configurações atuais:**

1. **Webhook Token:** okqS9nRS9FXJiOPkijs40T9v2fp2Vz522f1c9c-5f8e-4c6c-aa14-e863b6f34dd2
2. **Client ID:** f7f05ef5-bb55-46a2-a678-3c27627941d8
3. **Client Secret:** 1d9e0fe5-efa9-4841-80a5-6e15be63b2e0

**Webhook já configurado e recebendo:**
- ✅ Eventos de compra
- ✅ Eventos de assinatura
- ✅ Dados de subscriber
- ❌ **NÃO** recebe eventos de progresso de aulas

### ❓ DÚVIDAS PARA O SUPORTE

1. **Existe webhook de progresso de aulas?**
   - Se sim, qual o event type?
   - Como configurar?

2. **Se não existe webhook, a API REST deveria funcionar?**
   - Por que `/users/{ucode}/lessons` retorna vazio?
   - O produto precisa de alguma configuração específica?

3. **O produto está configurado para Club?**
   - Product ID: 4304019
   - É uma assinatura recorrente
   - Precisa migrar para formato Club?

### 🎯 OBJETIVO FINAL

Preciso obter dados de:
- Quais aulas o usuário assistiu
- Percentual de progresso em cada aula
- Se completou a aula
- Data de visualização

**Via webhook (preferível) OU via API REST**

---

Qual a melhor abordagem para o meu caso?

Agradeço a ajuda!
