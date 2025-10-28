# Sincronização de Progresso Hotmart - Fase 1

## 📚 Visão Geral

Este módulo implementa a **Fase 1** da sincronização de dados de progresso dos assinantes da Hotmart com o sistema Translators101.

### Objetivo da Fase 1
Obter os dados de progresso de cada assinante na Hotmart e armazená-los no banco de dados local.

## 📁 Estrutura de Arquivos

```
Hotmart/
├── database_migration.sql      # Script SQL para criar tabelas
├── HotmartProgressSync.php     # Classe principal de sincronização
├── test_sync.php                # Endpoint web para testes
├── README.md                    # Este arquivo
└── logs/                        # Diretório de logs (criado automaticamente)
```

## 🛠️ Instalação

### 1. Executar Migração do Banco de Dados

Execute o script SQL no banco de dados:

```bash
mysql -u u335416710_t101 -p u335416710_t101_db < database_migration.sql
```

Ou via phpMyAdmin:
1. Acesse phpMyAdmin
2. Selecione o banco `u335416710_t101_db`
3. Vá em "SQL"
4. Cole o conteúdo de `database_migration.sql`
5. Execute

### 2. Verificar Permissões

Certifique-se de que o diretório tem permissão de escrita para criar logs:

```bash
chmod 755 /caminho/para/Hotmart
```

## 🚀 Como Usar

### Via Interface Web

1. Acesse no navegador:
   ```
   https://seu-dominio.com/Hotmart/test_sync.php
   ```

2. Clique no botão "Iniciar Sincronização"

3. Acompanhe o progresso e os resultados na tela

### Via PHP (programação)

```php
require_once 'config/database.php';
require_once 'hotmart.php';
require_once 'Hotmart/HotmartProgressSync.php';

$hotmartApi = new HotmartAPI();
$syncManager = new HotmartProgressSync($pdo, $hotmartApi);

$result = $syncManager->syncAllProgress();

if ($result['success']) {
    echo "Sincronização concluída!";
    echo "Usuários processados: " . $result['users_processed'];
    echo "Registros de progresso: " . $result['progress_records'];
} else {
    echo "Erro: " . $result['message'];
}
```

### Via Cron Job (automação)

Para executar automaticamente a cada 6 horas:

```bash
0 */6 * * * php /caminho/para/Hotmart/cron_sync.php
```

## 📊 Tabelas do Banco de Dados

### hotmart_user_progress

Armazena o progresso de cada usuário em cada palestra.

**Campos principais:**
- `user_id`: ID do usuário local
- `lecture_id`: ID da palestra local
- `hotmart_user_id`: UUID do usuário na Hotmart
- `progress_percent`: Percentual de progresso (0-100)
- `is_completed`: Se completou a palestra
- `watch_time_seconds`: Tempo assistido em segundos
- `completed_at`: Data de conclusão
- `raw_data`: Dados brutos da API (JSON)

### hotmart_lecture_mapping

Mapeia palestras locais para lições da Hotmart.

**Campos principais:**
- `lecture_id`: ID da palestra local
- `hotmart_lesson_id`: ID da lesson na Hotmart
- `lecture_title`: Título da palestra

### hotmart_sync_logs

Registra histórico de sincronizações.

**Campos principais:**
- `sync_type`: Tipo (PROGRESS, MANUAL, WEBHOOK, SCHEDULED)
- `users_synced`: Número de usuários sincronizados
- `errors_count`: Número de erros
- `status`: Status (SUCCESS, PARTIAL, FAILED)

## 🔍 Como Funciona

### Fluxo de Sincronização

1. **Buscar Usuários**
   - Tenta obter usuários do Hotmart Club API
   - Se falhar, busca assinaturas ativas

2. **Para Cada Usuário**
   - Verifica se existe no banco local
   - Busca progresso via `getUserProgress()`
   - Processa dados retornados

3. **Salvar Progresso**
   - Tenta mapear lessons da Hotmart para lectures locais
   - Insere ou atualiza registros em `hotmart_user_progress`
   - Registra timestamp de sincronização

4. **Logging**
   - Gera logs detalhados em arquivo
   - Registra estatísticas em `hotmart_sync_logs`

## 📝 Logs

Os logs são salvos em:
```
Hotmart/logs/progress_sync.log
```

Formato:
```
[2025-10-28 15:30:45] [INFO] Iniciando sincronização de progresso
[2025-10-28 15:30:46] [INFO] Total de usuários encontrados: 150
[2025-10-28 15:30:47] [INFO] Processando usuário: João Silva (joao@email.com) - ID: abc-123
...
```

## ⚠️ Limitações Atuais (Fase 1)

1. **Mapeamento de Palestras**
   - Atualmente usa busca simples por título
   - Pode não encontrar correspondência exata
   - Será melhorado na Fase 2

2. **Novos Usuários**
   - Não cria usuários automaticamente
   - Depende do webhook para criar usuários

3. **Dados de Progresso**
   - Armazena dados brutos da API
   - Estrutura pode variar

## 🔧 Troubleshooting

### Erro: "Nenhum usuário encontrado"
- Verificar credenciais da Hotmart em `config/hotmart.php`
- Verificar se HOT_TOKEN está correto
- Verificar logs da API Hotmart

### Erro: "Falha ao conectar com banco de dados"
- Verificar credenciais em `config/database.php`
- Verificar se tabelas foram criadas
- Executar `database_migration.sql`

### Nenhum progresso sincronizado
- Verificar se usuários existem no banco local
- Verificar mapeamento de palestras
- Verificar logs em `Hotmart/logs/progress_sync.log`

## 🔜 Próximas Fases

### Fase 2: Exibir Progresso no Perfil
- Interface para visualizar progresso no perfil do usuário
- Gráficos e estatísticas
- Lista de palestras assistidas/completadas

### Fase 3: Geração de Certificados
- Gerar certificados automaticamente ao completar palestras
- Integração com sistema de certificados existente
- Notificações por email

## 👥 Suporte

Para dúvidas ou problemas:
1. Verificar logs em `Hotmart/logs/progress_sync.log`
2. Consultar documentação da API Hotmart
3. Verificar tabela `hotmart_sync_logs` no banco

## 📝 Notas Técnicas

- **Timeout**: Script configurado para 5 minutos (300s)
- **Rate Limiting**: Respeita limites da API Hotmart
- **Transactions**: Não usa transações para permitir progresso parcial
- **Charset**: UTF-8 em todos os lugares
