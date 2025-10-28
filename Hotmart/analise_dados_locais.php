<?php
/**
 * SOLUÇÃO FINAL - Obter progresso dos DADOS LOCAIS
 * Já que a API Hotmart não retorna dados, usar lecture_views
 */

require_once __DIR__ . '/../config/database.php';

echo "=== SOLUÇÃO FINAL: Progresso via Dados Locais ===\n\n";

// 1. Verificar estrutura da tabela lecture_views
echo "1. Verificando tabela lecture_views...\n";
$stmt = $pdo->query("DESCRIBE lecture_views");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Colunas disponíveis:\n";
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}
echo "\n";

// 2. Contar total de registros
echo "2. Total de visualizações registradas...\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM lecture_views");
$total = $stmt->fetch()['total'];
echo "Total: {$total} registros\n\n";

// 3. Buscar progresso de alguns usuários
echo "3. Exemplo de progresso de usuários...\n\n";
$stmt = $pdo->query("
    SELECT 
        u.name,
        u.email,
        l.title as lecture_title,
        lv.progress_percent,
        lv.completed,
        lv.watch_time_seconds,
        lv.last_watched_at,
        lv.completed_at
    FROM lecture_views lv
    JOIN users u ON lv.user_id = u.id
    JOIN lectures l ON lv.lecture_id = l.id
    WHERE lv.progress_percent > 0
    ORDER BY lv.last_watched_at DESC
    LIMIT 10
");

$views = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($views)) {
    echo "⚠️ Nenhum registro de visualização encontrado\n";
} else {
    foreach ($views as $view) {
        echo "Usuário: {$view['name']} ({$view['email']})\n";
        echo "  Palestra: {$view['lecture_title']}\n";
        echo "  Progresso: {$view['progress_percent']}%\n";
        echo "  Completado: " . ($view['completed'] ? 'Sim' : 'Não') . "\n";
        echo "  Tempo assistido: " . gmdate("H:i:s", $view['watch_time_seconds']) . "\n";
        echo "  Última visualização: {$view['last_watched_at']}\n";
        if ($view['completed_at']) {
            echo "  Data de conclusão: {$view['completed_at']}\n";
        }
        echo "\n";
    }
}

// 4. Estatísticas gerais
echo "4. Estatísticas gerais...\n\n";

// Total de usuários com progresso
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM lecture_views");
$usersWithProgress = $stmt->fetch()['total'];
echo "Usuários com progresso registrado: {$usersWithProgress}\n";

// Total de palestras assistidas
$stmt = $pdo->query("SELECT COUNT(DISTINCT lecture_id) as total FROM lecture_views");
$lecturesWatched = $stmt->fetch()['total'];
echo "Palestras com visualizações: {$lecturesWatched}\n";

// Total de conclusões
$stmt = $pdo->query("SELECT COUNT(*) as total FROM lecture_views WHERE completed = 1");
$completions = $stmt->fetch()['total'];
echo "Palestras completadas: {$completions}\n\n";

// 5. Top 5 usuários mais ativos
echo "5. Top 5 usuários mais ativos...\n\n";
$stmt = $pdo->query("
    SELECT 
        u.name,
        u.email,
        COUNT(*) as palestras_assistidas,
        SUM(CASE WHEN lv.completed = 1 THEN 1 ELSE 0 END) as completadas,
        SUM(lv.watch_time_seconds) as tempo_total
    FROM lecture_views lv
    JOIN users u ON lv.user_id = u.id
    GROUP BY u.id, u.name, u.email
    ORDER BY palestras_assistidas DESC
    LIMIT 5
");

$topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($topUsers as $i => $user) {
    echo ($i + 1) . ". {$user['name']} ({$user['email']})\n";
    echo "   Palestras assistidas: {$user['palestras_assistidas']}\n";
    echo "   Completadas: {$user['completadas']}\n";
    echo "   Tempo total: " . gmdate("H:i:s", $user['tempo_total']) . "\n\n";
}

// 6. CONCLUSÃO
echo "\n=== CONCLUSÃO ===\n\n";

if ($total > 0) {
    echo "🎉 SUCESSO! Sistema JÁ tem dados de progresso!\n\n";
    echo "Os dados estão em 'lecture_views' e incluem:\n";
    echo "  ✓ Progresso percentual\n";
    echo "  ✓ Status de conclusão\n";
    echo "  ✓ Tempo assistido\n";
    echo "  ✓ Data de última visualização\n";
    echo "  ✓ Data de conclusão\n\n";
    
    echo "✅ RECOMENDAÇÃO:\n";
    echo "Usar estes dados locais para as Fases 2 e 3:\n";
    echo "  - FASE 2: Exibir progresso no perfil do usuário\n";
    echo "  - FASE 3: Gerar certificados automaticamente\n\n";
    
    echo "VANTAGENS:\n";
    echo "  ✓ Dados já existem e estão completos\n";
    echo "  ✓ Não depende da API Hotmart\n";
    echo "  ✓ Implementação imediata\n";
    echo "  ✓ Mais rápido e confiável\n";
} else {
    echo "⚠️ Tabela lecture_views existe mas está vazia\n\n";
    echo "Possíveis motivos:\n";
    echo "  - Sistema de tracking não está ativo\n";
    echo "  - Usuários não assistiram palestras ainda\n";
    echo "  - Dados em outra tabela\n";
}

echo "\n=== Fim da Análise ===\n";
