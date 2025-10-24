<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Clientes - Dash-T101';
$page_description = 'Gerencie seus clientes de forma eficiente';
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_client':
                try {
                    $stmt = $pdo->prepare('INSERT INTO dash_clients (user_id, company, name, email, vat_number, phone, default_currency, address_line1, address_line2, address_line3, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                    $result = $stmt->execute([
                        $user_id,
                        $_POST['company_name'],
                        $_POST['contact_name'],
                        $_POST['contact_email'],
                        $_POST['vat_number'] ?? '',
                        $_POST['phone'] ?? '',
                        $_POST['default_currency'] ?? 'BRL',
                        $_POST['address_line1'] ?? '',
                        $_POST['address_line2'] ?? '',
                        $_POST['address_line3'] ?? '',
                        $_POST['notes'] ?? ''
                    ]);
                    
                    if ($result) {
                        $message = 'Cliente adicionado com sucesso!';
                    } else {
                        $error = 'Erro ao adicionar cliente.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
                
            case 'edit_client':
                try {
                    $stmt = $pdo->prepare('UPDATE dash_clients SET company = ?, name = ?, email = ?, vat_number = ?, phone = ?, default_currency = ?, address_line1 = ?, address_line2 = ?, address_line3 = ?, notes = ? WHERE id = ? AND user_id = ?');
                    $result = $stmt->execute([
                        $_POST['company_name'],
                        $_POST['contact_name'],
                        $_POST['contact_email'],
                        $_POST['vat_number'] ?? '',
                        $_POST['phone'] ?? '',
                        $_POST['default_currency'] ?? 'BRL',
                        $_POST['address_line1'] ?? '',
                        $_POST['address_line2'] ?? '',
                        $_POST['address_line3'] ?? '',
                        $_POST['notes'] ?? '',
                        $_POST['client_id'],
                        $user_id
                    ]);
                    
                    if ($result) {
                        $message = 'Cliente atualizado!';
                    } else {
                        $error = 'Erro ao atualizar cliente.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
                
            case 'delete_client':
                try {
                    $stmt = $pdo->prepare('DELETE FROM dash_clients WHERE id = ? AND user_id = ?');
                    $result = $stmt->execute([$_POST['client_id'], $user_id]);
                    
                    if ($result) {
                        $message = 'Cliente excluído!';
                    } else {
                        $error = 'Erro ao excluir cliente.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Obter lista de clientes
$search = $_GET['search'] ?? '';
$where_clause = 'WHERE user_id = ?';
$params = [$user_id];

if ($search) {
    $where_clause .= ' AND (company LIKE ? OR name LIKE ? OR email LIKE ?)';
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM dash_clients $where_clause ORDER BY company ASC");
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $clients = [];
    $error = 'Erro ao carregar clientes: ' . $e->getMessage();
}

// Obter cliente para edição se solicitado
$edit_client = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM dash_clients WHERE id = ? AND user_id = ?');
        $stmt->execute([$_GET['edit'], $user_id]);
        $edit_client = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $edit_client = null;
    }
}

include __DIR__ . '/../vision/includes/head.php';
?>

<?php include __DIR__ . '/../vision/includes/header.php'; ?>

<?php include __DIR__ . '/../vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="glass-hero">
        <h1><i class="fas fa-users" style="margin-right: 10px;"></i>Gerenciar Clientes</h1>
        <p>Organize e gerencie informações de todos os seus clientes em um só lugar.</p>
    </section>

    <!-- Mensagens -->
    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Formulário de Adicionar/Editar Cliente -->
    <div class="vision-form">
        <h2 style="font-size: 1.3rem; margin-bottom: 25px;">
            <i class="fas fa-<?php echo $edit_client ? 'edit' : 'user-plus'; ?>"></i>
            <?php echo $edit_client ? 'Editar Cliente' : 'Adicionar cliente'; ?>
        </h2>
        
        <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <input type="hidden" name="action" value="<?php echo $edit_client ? 'edit_client' : 'add_client'; ?>">
            <?php if ($edit_client): ?>
                <input type="hidden" name="client_id" value="<?php echo $edit_client['id']; ?>">
            <?php endif; ?>
            
            <div style="grid-column: span 2;">
                <label for="company_name">Nome da empresa *</label>
                <input type="text" name="company_name" id="company_name" required
                       value="<?php echo htmlspecialchars($edit_client['company'] ?? ''); ?>">
            </div>
            
            <div>
                <label for="contact_name">Nome do contato</label>
                <input type="text" name="contact_name" id="contact_name"
                       value="<?php echo htmlspecialchars($edit_client['name'] ?? ''); ?>">
            </div>
            
            <div>
                <label for="contact_email">E-mail do contato</label>
                <input type="email" name="contact_email" id="contact_email"
                       value="<?php echo htmlspecialchars($edit_client['email'] ?? ''); ?>">
            </div>
            
            <div>
                <label for="phone">Telefone</label>
                <input type="text" name="phone" id="phone"
                       value="<?php echo htmlspecialchars($edit_client['phone'] ?? ''); ?>">
            </div>
            
            <div>
                <label for="vat_number">CNPJ/CPF</label>
                <input type="text" name="vat_number" id="vat_number"
                       value="<?php echo htmlspecialchars($edit_client['vat_number'] ?? ''); ?>">
            </div>
            
            <div>
                <label for="default_currency">Moeda padrão</label>
                <select name="default_currency" id="default_currency">
                    <option value="BRL" <?php echo ($edit_client && $edit_client['default_currency'] == 'BRL') ? 'selected' : ''; ?>>Real (BRL)</option>
                    <option value="USD" <?php echo ($edit_client && $edit_client['default_currency'] == 'USD') ? 'selected' : ''; ?>>Dólar (USD)</option>
                    <option value="EUR" <?php echo ($edit_client && $edit_client['default_currency'] == 'EUR') ? 'selected' : ''; ?>>Euro (EUR)</option>
                </select>
            </div>
            
            <div style="grid-column: span 2;">
                <label for="address_line1">Endereço (Linha 1)</label>
                <input type="text" name="address_line1" id="address_line1"
                       value="<?php echo htmlspecialchars($edit_client['address_line1'] ?? ''); ?>"
                       placeholder="Rua, número">
            </div>
            
            <div>
                <label for="address_line2">Endereço (Linha 2)</label>
                <input type="text" name="address_line2" id="address_line2"
                       value="<?php echo htmlspecialchars($edit_client['address_line2'] ?? ''); ?>"
                       placeholder="Bairro, Complemento">
            </div>
            
            <div>
                <label for="address_line3">Endereço (Linha 3)</label>
                <input type="text" name="address_line3" id="address_line3"
                       value="<?php echo htmlspecialchars($edit_client['address_line3'] ?? ''); ?>"
                       placeholder="Cidade, Estado, CEP">
            </div>
            
            <div style="grid-column: span 2;">
                <label for="notes">Observações</label>
                <textarea name="notes" id="notes" rows="3"><?php echo htmlspecialchars($edit_client['notes'] ?? ''); ?></textarea>
            </div>
            
            <div style="grid-column: span 2; display: flex; gap: 15px;">
                <button type="submit" class="cta-btn">
                    <i class="fas fa-<?php echo $edit_client ? 'save' : 'plus'; ?>"></i>
                    <?php echo $edit_client ? 'Atualizar cliente' : 'Adicionar cliente'; ?>
                </button>
                <?php if ($edit_client): ?>
                    <a href="clients.php" class="cta-btn" style="background: rgba(255,255,255,0.1);">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Lista de Clientes -->
    <div class="vision-table">
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px; border-bottom: 1px solid var(--glass-border);">
            <h2 style="font-size: 1.3rem; margin: 0;">
                <i class="fas fa-list"></i> Lista de Clientes
            </h2>
            
            <!-- Filtros -->
            <div style="display: flex; gap: 10px;">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="Buscar clientes..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           style="padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 8px; color: white; font-size: 0.9rem;">
                    <button type="submit" class="cta-btn" style="font-size: 0.9rem; padding: 8px 15px;">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search): ?>
                        <a href="clients.php" class="cta-btn" style="background: rgba(255,255,255,0.1); font-size: 0.9rem; padding: 8px 15px;">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (empty($clients)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-users" style="font-size: 4rem; color: #666; margin-bottom: 20px;"></i>
                <h3 style="font-size: 1.3rem; margin-bottom: 10px;">Nenhum cliente encontrado</h3>
                <p style="color: #ccc;">
                    <?php echo $search ? 'Nenhum cliente encontrado com os critérios de busca.' : 'Nenhum cliente cadastrado ainda.'; ?>
                </p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Contato</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Moeda</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <div>
                                    <p style="font-weight: 600; margin-bottom: 5px;">
                                        <?php echo htmlspecialchars($client['company']); ?>
                                    </p>
                                    <?php if ($client['vat_number']): ?>
                                        <p style="color: #ccc; font-size: 0.85rem;">
                                            CNPJ/CPF: <?php echo htmlspecialchars($client['vat_number']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($client['name'] ?? '-'); ?></td>
                            <td>
                                <?php if ($client['email']): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" 
                                       style="color: var(--brand-purple); text-decoration: none;">
                                        <?php echo htmlspecialchars($client['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($client['phone']): ?>
                                    <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>" 
                                       style="color: var(--brand-purple); text-decoration: none;">
                                        <?php echo htmlspecialchars($client['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="tag"><?php echo htmlspecialchars($client['default_currency']); ?></span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="?edit=<?php echo $client['id']; ?>" 
                                       class="cta-btn" style="font-size: 0.8rem; padding: 6px 12px; background: var(--brand-purple);">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este cliente?')">
                                        <input type="hidden" name="action" value="delete_client">
                                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                        <button type="submit" class="cta-btn" style="font-size: 0.8rem; padding: 6px 12px; background: #e74c3c;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>
<style>
.cta-btn {
    display: inline-block;
    padding: 14px 28px;
    font-size: 1.1rem;
    font-weight: bold;
    border-radius: 30px;
    background: var(--brand-purple);
    color: #fff;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(142, 68, 173, 0.6);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    cursor: pointer;
}

element.style {
    color: var(--text-secondary) !important;
    text-decoration: none;
}

a:-webkit-any-link {
    color: var(--text-secondary) !important;
    cursor: pointer;
    text-decoration: underline;
}

.tag {
    background: rgba(142, 68, 173, 0.25);
    color: var(--text-secondary) !important; */
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

</style>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>