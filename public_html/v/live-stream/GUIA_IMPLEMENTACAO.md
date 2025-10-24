# 🎯 GUIA COMPLETO DE IMPLEMENTAÇÃO - ZOOM INTEGRATION

## ✅ ARQUIVOS CORRIGIDOS CRIADOS

Todos os arquivos corrigidos foram criados com sucesso em:
`/app/public_html/v/live-stream/`

### 📋 Lista de Arquivos:

1. **`zoom_config_fixed.php`** ✅
   - Configurações e credenciais do Zoom
   - Conexão com banco de dados
   - Função para criar tabela

2. **`zoom_auth_fixed.php`** ✅
   - Autenticação OAuth 2.0 CORRIGIDA
   - Sistema de cache de token
   - Logs detalhados de debug

3. **`zoom_functions_fixed.php`** ✅
   - Criar/ler/atualizar/deletar reuniões
   - Sincronizar reuniões
   - Gerenciar exibição no live

4. **`zoom_manage_fixed.php`** ✅
   - Painel administrativo completo
   - Interface moderna
   - Todas as funcionalidades

5. **`test_zoom_simple.php`** ✅
   - Teste rápido de autenticação
   - Visualização de logs
   - Diagnóstico completo

6. **`PATCH_INDEX_ZOOM.php`** ✅
   - Código para integrar no index.php (topo)

7. **`PATCH_PLAYER_ZOOM.php`** ✅
   - Código para integrar no index.php (player)

8. **`README_ZOOM_FIXED.md`** ✅
   - Documentação completa

## 🚀 PASSO A PASSO PARA IMPLEMENTAÇÃO

### PASSO 1: Testar a Autenticação

**Acesse:** `https://seusite.com/v/live-stream/test_zoom_simple.php`

Este teste vai:
- ✅ Tentar obter o token OAuth
- ✅ Testar a API do Zoom
- ✅ Mostrar logs detalhados

**Resultado esperado:**
```
✓ Token obtido com sucesso!
✓ API funcionando corretamente!
Informações do Usuário: {...}
```

Se houver erro, verifique:
- Credenciais em `zoom_config_fixed.php`
- Escopos no Zoom Marketplace
- Log de debug exibido na página

---

### PASSO 2: Acessar o Painel de Gerenciamento

**Acesse:** `https://seusite.com/v/live-stream/zoom_manage_fixed.php`

**Funções disponíveis:**

1. **Testar Conexão**
   - Clique em "Testar Autenticação"
   - Verifique se está tudo funcionando

2. **Criar Nova Reunião**
   - Preencha: Título, Data/Hora, Duração, Descrição
   - Clique em "Criar Reunião"
   - A reunião será criada no Zoom E salva no banco

3. **Adicionar Reunião Existente**
   - Cole o ID ou URL de uma reunião já criada
   - Clique em "Adicionar Reunião"
   - A reunião será importada para o sistema

4. **Sincronizar Reuniões**
   - Clique em "Sincronizar Agora"
   - Todas as reuniões da conta Zoom serão importadas

5. **Marcar para Exibição no Live**
   - Na lista de reuniões, clique em "Exibir"
   - A reunião ficará com borda verde e badge "AO VIVO"
   - Apenas UMA reunião pode estar marcada por vez

6. **Deletar Reunião**
   - Clique em "Deletar" na reunião desejada
   - A reunião será removida do Zoom e do banco

---

### PASSO 3: Integrar com o index.php

Agora vamos integrar as reuniões do Zoom no arquivo `index.php` principal.

#### 3.1. Modificar o Topo do index.php

**Localize a linha 4 do index.php:**
```php
require_once __DIR__ . '/../../config/database.php';
```

**Logo após ela, ADICIONE:**
```php
// Incluir funções do Zoom
require_once __DIR__ . '/zoom_functions_fixed.php';

// Verificar se há reunião do Zoom marcada para exibição
$currentZoomMeeting = getCurrentMeeting();
$meeting_type = 'embed'; // Valor padrão
```

**Depois, LOCALIZE as linhas ~36-49 (busca do embed code):**
```php
// Buscar embed code do banco de dados
$live_embed_code = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'live_embed_code'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $live_embed_code = $result['setting_value'];
    }
} catch (PDOException $e) {
    $live_embed_code = '';
}

// Live está ativa se temos embed code
$is_live_active = !empty(trim($live_embed_code));
```

**SUBSTITUA por:**
```php
// Se houver reunião do Zoom ativa, usar ela
if ($currentZoomMeeting) {
    $is_live_active = true;
    $meeting_type = 'zoom';
    writeToZoomLog("Reunião Zoom ativa: " . $currentZoomMeeting['topic']);
} else {
    // Buscar embed code do banco de dados
    $live_embed_code = '';
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'live_embed_code'");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result) {
            $live_embed_code = $result['setting_value'];
        }
    } catch (PDOException $e) {
        $live_embed_code = '';
    }
    
    $is_live_active = !empty(trim($live_embed_code));
    $meeting_type = 'embed';
}

// Buscar próximas reuniões do Zoom para a agenda
$upcomingZoomMeetings = getActiveMeetingsFromDatabase(5);
```

#### 3.2. Modificar a Seção do Player

**LOCALIZE a linha ~116 (início do player):**
```php
<div class="video-card player-card">
    <?php if ($is_live_active): ?>
        <div class="live-player">
            <div class="player-container">
                <?php echo $live_embed_code; ?>
            </div>
```

**SUBSTITUA toda a seção do player (até o fechamento da div.video-card) pelo conteúdo do arquivo:**
`PATCH_PLAYER_ZOOM.php`

Ou copie manualmente o código que verifica:
```php
<?php if ($meeting_type === 'zoom' && $currentZoomMeeting): ?>
    <!-- Exibir reunião Zoom -->
<?php else: ?>
    <!-- Exibir embed padrão -->
<?php endif; ?>
```

#### 3.3. Adicionar Seção de Próximas Reuniões (Opcional)

No final do arquivo, antes do fechamento de `</div>` da `main-content`, adicione o código de "SEÇÃO DE PRÓXIMAS REUNIÕES ZOOM" do arquivo `PATCH_PLAYER_ZOOM.php`.

---

### PASSO 4: Testar a Integração

1. **Criar uma reunião de teste:**
   - Acesse `zoom_manage_fixed.php`
   - Crie uma reunião para agora ou para daqui a poucos minutos
   - Marque a reunião com "Exibir"

2. **Acessar o index.php:**
   - Vá para: `https://seusite.com/v/live-stream/index.php`
   - Você deve ver:
     - Badge "AO VIVO"
     - Informações da reunião
     - Botão "Entrar na Reunião Zoom"

3. **Testar o botão:**
   - Clique em "Entrar na Reunião Zoom"
   - Deve abrir a reunião em nova janela

---

## 📊 FLUXO COMPLETO DE USO

```
1. Administrador acessa zoom_manage_fixed.php
   ↓
2. Cria reunião OU sincroniza reuniões existentes
   ↓
3. Marca reunião para "Exibir" no live
   ↓
4. Usuários acessam index.php
   ↓
5. Veem a reunião Zoom marcada
   ↓
6. Clicam em "Entrar na Reunião"
   ↓
7. Participam da reunião Zoom
```

---

## 🔧 ESTRUTURA DO BANCO DE DADOS

A tabela `zoom_meetings` será criada automaticamente com os campos:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT | ID interno |
| meeting_id | BIGINT | ID da reunião no Zoom |
| topic | VARCHAR(255) | Título da reunião |
| start_time | DATETIME | Data/hora de início |
| duration | INT | Duração em minutos |
| join_url | TEXT | URL para participar |
| password | VARCHAR(50) | Senha da reunião |
| agenda | TEXT | Descrição/agenda |
| show_live | TINYINT(1) | Exibir no live? (0 ou 1) |
| is_active | TINYINT(1) | Reunião ativa? (0 ou 1) |
| created_at | TIMESTAMP | Data de criação |

---

## 🎨 PERSONALIZAÇÃO

### Alterar Cores do Zoom no Player

No arquivo `PATCH_PLAYER_ZOOM.php`, você pode alterar:

```php
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

Para suas cores preferidas.

### Alterar Texto do Botão

Procure por:
```php
<span>Entrar na Reunião Zoom</span>
```

E altere para o texto desejado.

---

## 🆘 SOLUÇÃO DE PROBLEMAS

### Problema: "Não foi possível obter token de autenticação"

**Solução:**
1. Acesse `test_zoom_simple.php`
2. Verifique o log exibido
3. Confirme as credenciais em `zoom_config_fixed.php`
4. Verifique os escopos no Zoom Marketplace

### Problema: Reunião não aparece no site

**Solução:**
1. Verifique se a reunião está marcada com "Exibir" (botão verde)
2. Confirme que o horário está correto
3. Verifique no banco: `SELECT * FROM zoom_meetings WHERE show_live = 1`
4. Confira se o código foi adicionado corretamente no index.php

### Problema: Página em branco ou erro

**Solução:**
1. Ative display_errors no PHP temporariamente
2. Verifique o log do PHP: `/var/log/php_errors.log`
3. Verifique se todos os arquivos `*_fixed.php` estão no lugar
4. Confirme que o banco de dados está acessível

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [ ] 1. Testar autenticação com `test_zoom_simple.php`
- [ ] 2. Acessar `zoom_manage_fixed.php` e fazer teste de conexão
- [ ] 3. Criar tabela no banco de dados (automático ao acessar o painel)
- [ ] 4. Criar uma reunião de teste
- [ ] 5. Adicionar código no topo do `index.php`
- [ ] 6. Adicionar código do player no `index.php`
- [ ] 7. Adicionar seção de próximas reuniões (opcional)
- [ ] 8. Testar no navegador
- [ ] 9. Marcar reunião para exibição
- [ ] 10. Verificar se aparece no live stream

---

## 📞 SUPORTE

**Arquivos de log para verificar:**
- `/v/live-stream/zoom_debug_log.txt` - Log detalhado das operações
- `/tmp/zoom_token_cache.json` - Cache do token (pode deletar para forçar renovação)

**URLs importantes:**
- Teste: `/v/live-stream/test_zoom_simple.php`
- Painel: `/v/live-stream/zoom_manage_fixed.php`
- Live: `/v/live-stream/index.php`

---

## 🎉 CONCLUSÃO

Após seguir todos os passos, você terá:
- ✅ Integração Zoom funcionando
- ✅ Painel administrativo completo
- ✅ Reuniões exibidas no live stream
- ✅ Sistema de logs para debug
- ✅ Sincronização automática de reuniões

**A integração está completa e pronta para uso!**
