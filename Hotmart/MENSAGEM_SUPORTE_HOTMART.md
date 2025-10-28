# 📧 Mensagem para Suporte Hotmart - Template

## Assunto
Problema com API de Progresso de Aulas - getUserProgress retorna vazio

---

## Mensagem

Olá equipe Hotmart,

Estou desenvolvendo uma integração com a API da Hotmart para obter dados de progresso dos alunos nas aulas do meu produto, mas estou enfrentando dificuldades.

### 📋 INFORMAÇÕES DO PRODUTO

- **Produto:** Assinatura Premium Plus Translators101
- **Product ID:** 4304019
- **Subdomain:** t101
- **Tipo:** Assinatura recorrente

### 🔧 TENTATIVAS REALIZADAS

Testei todos os endpoints seguindo a documentação oficial:

#### 1. Club Users API
```
GET https://developers.hotmart.com/club/api/v1/users?subdomain=t101
Authorization: Bearer [token]
```
**Resultado:** Retorna vazio (success: true, data: [])

#### 2. getUserProgress sem subdomain
```
GET https://developers.hotmart.com/club/api/v1/users/{ucode}/lessons
Authorization: Bearer [token]
```
**Resultado:** "Nenhum dado de progresso encontrado"

#### 3. getUserProgress com subdomain (conforme documentação)
```
GET https://developers.hotmart.com/club/api/v1/users/{ucode}/lessons?subdomain=t101
Authorization: Bearer [token]
```
**Resultado:** HTTP 400 - "invalid_parameter"

### ✅ TESTES BEM-SUCEDIDOS

- ✅ Autenticação OAuth funcionando
- ✅ API Subscriptions retorna 41 assinaturas ativas com ucodes válidos
- ✅ Todos os tokens e credenciais corretos

### ❌ PROBLEMA

A API de progresso (`/users/{ucode}/lessons`) **não retorna dados** para nenhum dos 41 assinantes ativos testados, mesmo usando ucodes válidos obtidos da API de Subscriptions.

### ❓ DÚVIDAS

1. **O produto precisa estar configurado como "Club" ou "Membership"?**
   - Atualmente é uma assinatura recorrente
   - Existe alguma configuração específica necessária?

2. **As aulas precisam estar vinculadas ao Hotmart Club?**
   - As aulas estão hospedadas em plataforma própria (v.translators101.com)
   - Como vincular as aulas ao Club para tracking?

3. **O tracking de progresso está habilitado?**
   - Existe alguma configuração no painel que precisa ser ativada?
   - É necessário usar player específico da Hotmart?

4. **O subdomain 't101' está correto?**
   - Como verificar/configurar o subdomain do Club?

5. **Permissões da API**
   - As credenciais atuais têm permissão para acessar dados de progresso?
   - Preciso de alguma permissão adicional?

### 📊 DADOS TÉCNICOS

**Client ID:** f7f05ef5-bb55-46a2-a678-3c27627941d8

**Exemplo de ucode testado:** f20287fc-cf71-4fbd-afbf-9d076b41e403
(Subscriber: Suellen Sato - suki.satou@gmail.com)

**Endpoints utilizados:**
- https://api-sec-vlc.hotmart.com/security/oauth/token (✅ funcionando)
- https://developers.hotmart.com/payments/api/v1/subscriptions (✅ funcionando)
- https://developers.hotmart.com/club/api/v1/users (⚠️ vazio)
- https://developers.hotmart.com/club/api/v1/users/{ucode}/lessons (❌ sem dados)

### 🎯 OBJETIVO

Preciso obter dados de progresso das aulas para:
1. Exibir progresso no perfil dos usuários
2. Gerar certificados automaticamente ao completar aulas
3. Enviar relatórios de conclusão

### 📚 DOCUMENTAÇÃO CONSULTADA

- https://developers.hotmart.com/docs/pt-BR/v1/club/get-lessons-club/
- https://developers.hotmart.com/docs/pt-BR/v1/club/get-users-club/

### ❓ QUESTÃO PRINCIPAL

**O produto "Assinatura Premium Plus Translators101" está configurado para usar o Hotmart Club e tracking de progresso?**

Se não, quais são os passos necessários para habilitar essa funcionalidade?

---

Agradeço a atenção e aguardo retorno!

Atenciosamente,
[Seu Nome]
Translators101
contato@translators101.com
