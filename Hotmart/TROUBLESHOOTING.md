# 🔧 Troubleshooting - Sincronização Hotmart

## ❌ Problema: "Falha ao obter token" (OAuth)

### Sintomas
- Erro: "Falha ao obter token de acesso"
- Autenticação OAuth não funciona
- Subscriptions API retorna erro

### Causa
O `HOTMART_BASIC_AUTH` não está definido no arquivo de configuração.

### Solução

**Opção 1: Usar script de correção automática (RECOMENDADO)**

1. Acesse: `/Hotmart/fix_auth.php`
2. Clique em "Aplicar Correção Automaticamente"
3. Teste novamente em `/Hotmart/test_api.php`

**Opção 2: Correção manual**

1. Abra o arquivo `/public_html/v/config/hotmart.php`

2. Gere o BASIC_AUTH:
   ```php
   $basicAuth = base64_encode('CLIENT_ID:CLIENT_SECRET');
   // Substitua CLIENT_ID e CLIENT_SECRET pelos valores reais
   ```

3. Adicione esta linha no arquivo de configuração:
   ```php
   define('HOTMART_BASIC_AUTH', 'SEU_BASIC_AUTH_AQUI');
   ```

4. O arquivo deve ficar assim:
   ```php
   <?php
   // Credenciais Hotmart
   define('HOTMART_CLIENT_ID', 'f7f05ef5-bb55-46a2-a678-3c27627941d8');
   define('HOTMART_CLIENT_SECRET', '1d9e0fe5-efa9-4841-80a5-6e15be63b2e0');
   define('HOTMART_HOT_TOKEN', 'okqS9nRS9FXJiOPkijs40T9v2fp2Vz522f1c9c-5f8e-4c6c-aa14-e863b6f34dd2');
   define('HOTMART_SUBDOMAIN', 't101');
   define('HOTMART_BASIC_AUTH', 'ZjdmMDVlZjUtYmI1NS00NmEyLWE2NzgtM2MyNzYyNzk0MWQ4OjFkOWUwZmU1LWVmYTktNDg0MS04MGE1LTZlMTViZTYzYjJlMA==');
   ```

5. Salve o arquivo e teste novamente

---

## ⚠️ Problema: "Club Users obtidos com sucesso! Total de usuários: 0"

### Sintomas
- API Club retorna sucesso
- Mas 0 usuários são encontrados
- Sincronização não processa nenhum usuário

### Possíveis Causas

1. **Não há usuários no Hotmart Club**
   - Verifique no painel da Hotmart se há usuários cadastrados
   - Verifique se o subdomain 't101' está correto

2. **Usuários estão em assinaturas, não no Club**
   - O sistema já tem fallback para Subscriptions API
   - Mas Subscriptions precisa de OAuth (veja problema acima)

3. **Permissões da API**
   - Verifique se o HOT_TOKEN tem permissão para acessar usuários
   - Verifique no painel da Hotmart as permissões do token

### Soluções

**1. Verificar Subdomain**
```php
// Em config/hotmart.php, confirme:
define('HOTMART_SUBDOMAIN', 't101'); // Deve ser o subdomain correto
```

**2. Testar manualmente**
Acesse: `/Hotmart/test_api.php` e verifique:
- Seção "3. Testar Club Users API"
- Seção "4. Testar Subscriptions API"

**3. Verificar no painel Hotmart**
- Acesse [developers.hotmart.com](https://developers.hotmart.com)
- Verifique:
  - Quantidade de assinantes ativos
  - Se há usuários no Club
  - Permissões do token

---

## 🔍 Problema: "Nenhum usuário tem hotmart_ucode definido"

### Sintomas
- Banco mostra: "Total: 0 usuários" com hotmart_ucode
- Sincronização não encontra usuários locais

### Causa
Isso é **NORMAL** na primeira execução. O `hotmart_ucode` será preenchido:
1. Pelo webhook quando novos usuários forem criados
2. Pela primeira sincronização que encontrar correspondência por email

### Solução
**Não precisa fazer nada!** Na primeira sincronização bem-sucedida:
1. Sistema busca usuários da Hotmart
2. Para cada usuário encontrado, busca por email no banco local
3. Se encontrar, preenche o `hotmart_ucode` automaticamente
4. Nas próximas sincronizações, já terá o mapeamento

---

## 🚫 Problema: Erro 500 ao acessar test_sync.php

### Possíveis Causas e Soluções

**1. Caminhos dos arquivos incorretos**

Verifique se a estrutura está correta:
```
/
├── public_html/
│   └── v/
│       ├── config/
│       │   ├── database.php
│       │   └── hotmart.php
│       └── hotmart.php
└── Hotmart/
    ├── test_sync.php
    └── ...
```

**2. Permissões de arquivo**
```bash
chmod 644 Hotmart/*.php
chmod 755 Hotmart/
```

**3. Verificar logs de erro do PHP**
```bash
tail -f /var/log/php-fpm/error.log
# ou
tail -f /var/log/apache2/error.log
```

---

## 📊 Problema: Sincronização executa mas não salva dados

### Diagnóstico

1. **Verificar logs**
   ```bash
   tail -f Hotmart/logs/progress_sync.log
   ```

2. **Verificar banco de dados**
   ```sql
   SELECT * FROM hotmart_sync_logs 
   ORDER BY started_at DESC LIMIT 1;
   
   SELECT COUNT(*) FROM hotmart_user_progress;
   ```

3. **Verificar se tabelas existem**
   ```sql
   SHOW TABLES LIKE 'hotmart_%';
   ```

### Soluções

**Se tabelas não existem:**
```bash
mysql -u u335416710_t101 -p u335416710_t101_db < Hotmart/database_migration.sql
```

**Se não há progresso da API:**
- Usuários da Hotmart podem não ter assistido nenhuma aula ainda
- Verifique no painel da Hotmart se há dados de progresso

---

## 🔐 Problema: Credenciais inválidas

### Como verificar se credenciais estão corretas

1. **Acesse o painel da Hotmart**
   - [developers.hotmart.com](https://developers.hotmart.com)
   - Vá em "Minhas Aplicações"
   - Verifique CLIENT_ID e CLIENT_SECRET

2. **Teste as credenciais**
   - Acesse `/Hotmart/fix_auth.php`
   - Veja se consegue gerar o BASIC_AUTH
   - Teste a autenticação

3. **Verifique o HOT_TOKEN**
   - No painel da Hotmart
   - Verifique se o token está ativo
   - Verifique as permissões do token

### Se credenciais estão incorretas

1. Gere novas credenciais no painel da Hotmart
2. Atualize o arquivo `/public_html/v/config/hotmart.php`
3. Execute `/Hotmart/fix_auth.php` para gerar novo BASIC_AUTH
4. Teste em `/Hotmart/test_api.php`

---

## 🌐 Problema: Timeout ou erro de conexão

### Sintomas
- "cURL error: Operation timed out"
- "Failed to connect to api.hotmart.com"

### Soluções

**1. Verificar firewall do servidor**
```bash
# Testar conectividade
curl -I https://api.hotmart.com
curl -I https://developers.hotmart.com
```

**2. Verificar configuração PHP**
```php
// Verificar se curl está habilitado
phpinfo();
// Procure por "curl"
```

**3. Aumentar timeout**
Em `HotmartProgressSync.php`, linha do cURL, aumente:
```php
CURLOPT_TIMEOUT => 60, // Aumentar de 30 para 60
```

---

## 📝 Problema: Logs não são gerados

### Verificar permissões
```bash
# Verificar se diretório existe
ls -la Hotmart/logs/

# Se não existir, criar
mkdir -p Hotmart/logs
chmod 755 Hotmart/logs
```

### Verificar se PHP pode escrever
```php
// Teste manual
file_put_contents('Hotmart/logs/test.txt', 'teste');
```

---

## 🔄 Checklist de Diagnóstico Completo

Quando tiver problemas, siga esta ordem:

- [ ] 1. Acessar `/Hotmart/fix_auth.php` e verificar BASIC_AUTH
- [ ] 2. Acessar `/Hotmart/test_api.php` e verificar todos os testes
- [ ] 3. Verificar logs em `Hotmart/logs/progress_sync.log`
- [ ] 4. Verificar `hotmart_sync_logs` no banco de dados
- [ ] 5. Verificar credenciais no painel da Hotmart
- [ ] 6. Verificar permissões de arquivos e diretórios
- [ ] 7. Verificar logs de erro do PHP/Apache
- [ ] 8. Testar conectividade com APIs da Hotmart

---

## 📞 Ainda com problemas?

Se após seguir este guia o problema persistir:

1. **Reúna informações**:
   - Saída completa de `/Hotmart/test_api.php`
   - Últimas 50 linhas de `Hotmart/logs/progress_sync.log`
   - Última entrada de `hotmart_sync_logs` no banco
   - Mensagens de erro específicas

2. **Verifique a documentação oficial**:
   - [Documentação API Hotmart](https://developers.hotmart.com/docs/pt-BR/)

3. **Entre em contato com suporte da Hotmart**:
   - Verifique status das APIs
   - Confirme validade das credenciais

---

## ✅ Testes de Verificação

Após resolver problemas, execute estes testes:

```bash
# 1. Teste de autenticação
curl -X POST https://api-sec-vlc.hotmart.com/security/oauth/token \
  -H "Authorization: Basic SEU_BASIC_AUTH" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials&client_id=SEU_CLIENT_ID&client_secret=SEU_CLIENT_SECRET"

# 2. Teste de banco de dados
mysql -u u335416710_t101 -p -e "SELECT COUNT(*) FROM u335416710_t101_db.hotmart_user_progress;"

# 3. Teste de permissões
touch Hotmart/logs/test.txt && rm Hotmart/logs/test.txt
```

Se todos passarem, o sistema está pronto para uso! 🎉
