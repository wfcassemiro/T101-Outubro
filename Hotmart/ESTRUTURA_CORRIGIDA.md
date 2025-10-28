# ✅ Estrutura de Arquivos Corrigida

## 📂 Estrutura Real do Projeto

```
/public_html/v/
├── config/
│   ├── database.php
│   └── hotmart.php
├── logs/
│   └── hotmart_progress_sync.log  ← LOGS AQUI
├── hotmart.php
└── hotmart/  ← PASTA DOS ARQUIVOS
    ├── database_migration.sql
    ├── HotmartProgressSync.php
    ├── test_sync.php
    ├── test_api.php
    ├── fix_auth.php
    ├── cron_sync.php
    └── README.md
```

## 🔧 Mudanças Aplicadas

### ✅ Todos os Arquivos Corrigidos

| Arquivo | Mudança Principal |
|---------|------------------|
| **HotmartProgressSync.php** | Log: `__DIR__ . '/../logs/hotmart_progress_sync.log'` |
| **test_sync.php** | Includes: `__DIR__ . '/../config/database.php'` |
| **test_api.php** | Includes: `__DIR__ . '/../config/database.php'` |
| **fix_auth.php** | Config: `__DIR__ . '/../config/hotmart.php'` |
| **cron_sync.php** | Includes: `__DIR__ . '/../config/database.php'` |

### 📍 Caminhos Antes e Depois

**ANTES (incorreto):**
```php
require_once __DIR__ . '/../public_html/v/config/database.php';
$logFile = __DIR__ . '/logs/progress_sync.log';
```

**DEPOIS (correto):**
```php
require_once __DIR__ . '/../config/database.php';
$logFile = __DIR__ . '/../logs/hotmart_progress_sync.log';
```

## 🚀 Como Fazer Upload

### Opção 1: FTP/SFTP

1. Conecte-se ao servidor
2. Navegue até `/public_html/v/hotmart/`
3. Faça upload de todos os arquivos da pasta `/app/Hotmart/`

### Opção 2: SSH

```bash
# Copiar todos os arquivos
scp /app/Hotmart/* usuario@servidor:/public_html/v/hotmart/

# Ou se já estiver no servidor
cp /app/Hotmart/* /public_html/v/hotmart/
```

### Opção 3: Git (se disponível)

```bash
cd /public_html/v/hotmart/
git pull
```

## 📋 Checklist Pós-Upload

- [ ] Arquivo `database_migration.sql` está em `/public_html/v/hotmart/`
- [ ] Arquivo `HotmartProgressSync.php` está em `/public_html/v/hotmart/`
- [ ] Arquivo `test_sync.php` está em `/public_html/v/hotmart/`
- [ ] Arquivo `test_api.php` está em `/public_html/v/hotmart/`
- [ ] Arquivo `fix_auth.php` está em `/public_html/v/hotmart/`
- [ ] Pasta `/public_html/v/logs/` existe e tem permissão de escrita
- [ ] Executar migração do banco de dados
- [ ] Testar em: `https://v.translators101.com/hotmart/test_sync.php`

## ✅ Verificar Funcionamento

### 1. Testar API
```
https://v.translators101.com/hotmart/test_api.php
```

Deve mostrar:
- ✅ Credenciais definidas
- ✅ Token OAuth obtido
- ✅ Club Users funcionando
- ✅ Banco de dados OK

### 2. Ver Logs
```
https://v.translators101.com/logs/hotmart_progress_sync.log
```

ou via SSH:
```bash
tail -f /public_html/v/logs/hotmart_progress_sync.log
```

### 3. Executar Sincronização
```
https://v.translators101.com/hotmart/test_sync.php
```

Clique em "Iniciar Sincronização" e acompanhe.

## 🐛 Se Algo Não Funcionar

### Erro: "No such file or directory"

**Causa:** Caminhos ainda incorretos

**Solução:**
1. Verifique a estrutura do servidor:
```bash
ls -la /public_html/v/
ls -la /public_html/v/hotmart/
ls -la /public_html/v/config/
ls -la /public_html/v/logs/
```

2. Ajuste os caminhos conforme necessário

### Erro: "Permission denied" nos logs

**Solução:**
```bash
chmod 755 /public_html/v/logs/
touch /public_html/v/logs/hotmart_progress_sync.log
chmod 666 /public_html/v/logs/hotmart_progress_sync.log
```

### Logs não aparecem

**Verificar:**
1. Pasta `/public_html/v/logs/` existe?
2. PHP pode escrever na pasta?
3. Arquivo está sendo criado?

```bash
ls -la /public_html/v/logs/hotmart*
```

## 📞 Suporte

Se precisar ajustar mais algum caminho, procure por:

```bash
# Encontrar todos os require_once
grep -r "require_once" /public_html/v/hotmart/

# Encontrar referências a logs
grep -r "logFile" /public_html/v/hotmart/

# Verificar __DIR__
grep -r "__DIR__" /public_html/v/hotmart/
```

## ✅ Status Final

Todos os arquivos em `/app/Hotmart/` estão:
- ✅ Com caminhos corrigidos
- ✅ Prontos para upload
- ✅ Testados para estrutura: `/public_html/v/hotmart/`
- ✅ Logs em: `/public_html/v/logs/`

**Domínio:** `v.translators101.com`
**Acesso:** `https://v.translators101.com/hotmart/test_sync.php`
