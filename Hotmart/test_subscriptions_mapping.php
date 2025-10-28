<?php
/**
 * Script de teste usando SUBSCRIPTIONS (não Club)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';

echo "=== Teste de Mapeamento via SUBSCRIPTIONS ===\n\n";

$api = new HotmartAPI();

// 1. Buscar Subscriptions ATIVAS
echo "1. Buscando Subscriptions ATIVAS...\n";
$subsResult = $api->getSubscriptions('ACTIVE');
echo "Success: " . ($subsResult['success'] ? 'true' : 'false') . "\n";

if ($subsResult['success']) {
    if (isset($subsResult['data']['items'])) {
        $subscriptions = $subsResult['data']['items'];
        echo "Assinaturas encontradas: " . count($subscriptions) . "\n\n";
        
        // Mostrar estrutura da primeira
        if (!empty($subscriptions)) {
            echo "Estrutura da primeira assinatura:\n";
            echo json_encode($subscriptions[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        
        // Criar mapeamento email -> ucode
        echo "2. Criando mapeamento email → ucode...\n\n";
        $emailMap = [];
        
        foreach ($subscriptions as $subscription) {
            $email = strtolower(trim($subscription['subscriber']['email'] ?? ''));
            
            // Tentar diferentes caminhos para o ucode
            $ucode = $subscription['subscriber']['ucode'] 
                    ?? $subscription['subscriber']['subscriber_code']
                    ?? $subscription['subscriber']['code']
                    ?? null;
            
            if ($email && $ucode) {
                $emailMap[$email] = $ucode;
                echo "✓ {$email} → {$ucode}\n";
            } else {
                echo "✗ Assinatura sem ucode válido (email: {$email})\n";
            }
        }
        
        echo "\nTotal mapeado: " . count($emailMap) . " emails\n\n";
        
        // 3. Testar com usuários do banco
        echo "3. Testando com usuários do banco...\n\n";
        $stmt = $pdo->query("
            SELECT email, name, hotmart_subscription_id
            FROM users 
            WHERE is_active = 1 
            AND hotmart_subscription_id IS NOT NULL
            LIMIT 5
        ");
        
        $localUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $foundCount = 0;
        $progressCount = 0;
        
        foreach ($localUsers as $localUser) {
            $email = strtolower(trim($localUser['email']));
            echo "Usuário: {$localUser['name']} ({$email})\n";
            echo "  subscription_id no banco: {$localUser['hotmart_subscription_id']}\n";
            
            if (isset($emailMap[$email])) {
                $foundCount++;
                $ucode = $emailMap[$email];
                echo "  ✓ Encontrado no mapeamento! ucode: {$ucode}\n";
                
                // Testar getUserProgress
                echo "  → Buscando progresso com ucode...\n";
                $progressResult = $api->getUserProgress($ucode);
                echo "  ← Success: " . ($progressResult['success'] ? 'true' : 'false') . "\n";
                
                if ($progressResult['success'] && isset($progressResult['data'])) {
                    $data = $progressResult['data'];
                    
                    if (is_array($data) && !empty($data)) {
                        $progressCount++;
                        $dataCount = count($data);
                        echo "  ✓✓ PROGRESSO ENCONTRADO: {$dataCount} itens!\n";
                        
                        // Mostrar primeiro item
                        echo "  Exemplo de item:\n";
                        echo "  " . json_encode($data[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                    } else {
                        echo "  ○ API retornou sucesso mas data vazio\n";
                    }
                } else {
                    echo "  ✗ Sem progresso ou API falhou\n";
                    echo "  Mensagem: " . ($progressResult['message'] ?? 'N/A') . "\n";
                }
            } else {
                echo "  ✗ Email NÃO encontrado no mapeamento\n";
                echo "  (Este email não tem assinatura ativa)\n";
            }
            echo "\n";
        }
        
        // 4. Resumo
        echo "\n=== RESUMO ===\n";
        echo "Assinaturas ATIVAS na Hotmart: " . count($subscriptions) . "\n";
        echo "Emails mapeados: " . count($emailMap) . "\n";
        echo "Usuários testados: " . count($localUsers) . "\n";
        echo "Encontrados no mapeamento: {$foundCount}\n";
        echo "Com progresso disponível: {$progressCount}\n\n";
        
        if ($progressCount > 0) {
            echo "🎉 SUCESSO! Conseguimos obter progresso!\n";
            echo "Solução: Usar Subscriptions para mapear email → ucode\n";
        } else {
            echo "⚠️ Mapeamento funciona mas nenhum progresso encontrado\n";
            echo "Possível causa: Usuários não assistiram aulas ainda\n";
        }
        
    } else {
        echo "✗ Sem 'items' na resposta de Subscriptions\n";
        echo "Resposta: " . json_encode($subsResult, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "✗ Falha ao buscar Subscriptions\n";
    echo "Mensagem: " . ($subsResult['message'] ?? 'N/A') . "\n";
}

echo "\n=== Fim do Teste ===\n";
