# 🛠️ Guia de Instalação - Sincronização Hotmart Fase 1

## 📌 Pré-requisitos

- PHP 7.2+
- MySQL/MariaDB
- Acesso ao banco de dados Translators101
- Credenciais da API Hotmart configuradas
- Extensões PHP: PDO, PDO_MySQL, curl, json

## 🚀 Passos de Instalação

### Passo 1: Upload dos Arquivos

1. Faça upload da pasta `Hotmart` para o servidor
2. Coloque no mesmo nível da pasta `public_html`

```
servidor/
├── public_html/
│   └── v/
│       ├── config/
│       ├── hotmart.php
│       └── ...
└── Hotmart/          ← AQUI
    ├── database_migration.sql
    ├── HotmartProgressSync.php
    ├── test_sync.php
    ├── cron_sync.php
    └── README.md
```

### Passo 2: Executar Migração do Banco de Dados

**Opção A: Via phpMyAdmin**

1. Acesse phpMyAdmin do seu servidor
2. Selecione o banco de dados `u335416710_t101_db`
3. Clique na aba "SQL"
4. Abra o arquivo `Hotmart/database_migration.sql`
5. Copie todo o conteúdo
6. Cole na área de texto do phpMyAdmin
7. Clique em "Executar"

**Opção B: Via linha de comando (SSH)**

```bash
mysql -u u335416710_t101 -p u335416710_t101_db < Hotmart/database_migration.sql
```

Quando solicitado, digite a senha: `Pa392ap!`

### Passo 3: Verificar Permissões

```bash
chmod 755 Hotmart
chmod 644 Hotmart/*.php
chmod 644 Hotmart/*.sql
chmod 644 Hotmart/*.md
```

O diretório `logs/` será criado automaticamente com as permissões corretas.

### Passo 4: Verificar Instalação

1. Acesse no navegador:
   ```
   https://translators101.com/Hotmart/test_sync.php
   ```

2. Você deve ver a interface de sincronização

3. Verifique se as estatísticas aparecem (mesmo que zeradas inicialmente)

### Passo 5: Primeira Sincronização

1. Na interface web, clique em "Iniciar Sincronização"
2. Aguarde o processo concluir (pode levar alguns minutos)
3. Verifique os resultados:
   - Usuários processados
   - Registros de progresso criados
   - Eventuais erros

## ✅ Verificação Pós-Instalação

### Verificar Tabelas Criadas

```sql
SHOW TABLES LIKE 'hotmart_%';
```

Deve retornar:
- `hotmart_logs`
- `hotmart_subscriptions`
- `hotmart_sync_logs`
- `hotmart_user_progress`  ← NOVA
- `hotmart_lecture_mapping`  ← NOVA
- `hotmart_webhooks`

### Verificar Dados Sincronizados

```sql
-- Total de registros de progresso
SELECT COUNT(*) FROM hotmart_user_progress;

-- Usuários com progresso
SELECT COUNT(DISTINCT user_id) FROM hotmart_user_progress;

-- Palestras completadas
SELECT COUNT(*) FROM hotmart_user_progress WHERE is_completed = 1;

-- Última sincronização
SELECT * FROM hotmart_sync_logs 
WHERE sync_type = 'PROGRESS' 
ORDER BY started_at DESC 
LIMIT 1;
```

### Verificar Logs

```bash
tail -f Hotmart/logs/progress_sync.log
```

## 🔧 Configuração Opcional

### Automatizar Sincronização (Cron Job)

Para executar automaticamente a cada 6 horas:

```bash
crontab -e
```

Adicione a linha:

```bash
0 */6 * * * /usr/bin/php /caminho/completo/para/Hotmart/cron_sync.php >> /caminho/para/Hotmart/logs/cron.log 2>&1
```

**Importante**: Substitua `/caminho/completo/para/` pelo caminho real no servidor.

### Descobrir Caminho Completo

1. Acesse via SSH
2. Execute:
   ```bash
   pwd
   ```
3. Navegue até a pasta Hotmart e execute `pwd` novamente

## ⚠️ Troubleshooting

### Erro 500 ao acessar test_sync.php

**Possíveis causas:**

1. **Caminho dos arquivos incorreto**
   - Verificar se os caminhos em `test_sync.php` estão corretos
   - Ajustar linha:
     ```php
     require_once __DIR__ . '/../public_html/v/config/database.php';
     ```

2. **Permissões de arquivo**
   ```bash
   chmod 644 Hotmart/test_sync.php
   ```

3. **Verificar error log do PHP**
   ```bash
   tail -f /var/log/apache2/error.log
   # ou
   tail -f /var/log/php-fpm/error.log
   ```

### "Nenhum usuário encontrado"

**Verificações:**

1. Credenciais da Hotmart estão corretas?
   ```php
   // Em public_html/v/config/hotmart.php
   define('HOTMART_CLIENT_ID', '...');
   define('HOTMART_CLIENT_SECRET', '...');
   define('HOTMART_HOT_TOKEN', '...');
   ```

2. Testar manualmente a API:
   ```php
   $api = new HotmartAPI();
   $token = $api->getAccessToken();
   var_dump($token);
   ```

### Tabelas não foram criadas

1. Verificar se usuário tem permissões para criar tabelas
2. Executar comandos um por um no phpMyAdmin
3. Verificar mensagens de erro

### Nenhum progresso sincronizado

1. **Verificar se há usuários no banco local:**
   ```sql
   SELECT COUNT(*) FROM users WHERE hotmart_status = 'ACTIVE';
   ```

2. **Verificar logs da API:**
   ```bash
   tail -100 Hotmart/logs/progress_sync.log
   ```

3. **Testar API manualmente:**
   ```php
   $api = new HotmartAPI();
   $users = $api->getClubUsers('t101');
   var_dump($users);
   ```

## 📚 Recursos Adicionais

- [Documentação API Hotmart](https://developers.hotmart.com/docs/pt-BR/)
- [README.md](README.md) - Documentação completa
- Logs: `Hotmart/logs/progress_sync.log`

## 👍 Próximos Passos

Após confirmar que a Fase 1 está funcionando:

1. **Fase 2**: Exibir progresso no perfil dos usuários
2. **Fase 3**: Gerar certificados automaticamente

## 📞 Suporte

Se encontrar problemas:

1. Verificar logs em `Hotmart/logs/progress_sync.log`
2. Verificar tabela `hotmart_sync_logs`
3. Enviar mensagem com:
   - Descrição do problema
   - Mensagens de erro
   - Conteúdo relevante dos logs

---

✅ **Instalação concluída com sucesso quando:**
- Interface web carrega sem erros
- Estatísticas aparecem
- Primeira sincronização executa
- Dados aparecem em `hotmart_user_progress`
