<?php
session_start();

// Corrige o caminho do database.php
require_once __DIR__ . '/../config/database.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar se ID da fatura foi fornecido
if (!isset($_GET['id'])) {
    header('Location: invoices.php');
    exit();
}

$invoice_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Definir idioma (padrão: português)
$lang = $_GET['lang'] ?? 'pt';
if (!in_array($lang, ['pt', 'en', 'es'])) {
    $lang = 'pt';
}

// Array de traduções
$translations = [
    'pt' => [
        'invoice_title' => 'Fatura',
        'bill_to' => 'Faturar para:',
        'company' => 'Empresa',
        'contact' => 'Contato',
        'email' => 'Email',
        'phone' => 'Telefone',
        'address' => 'Endereço',
        'invoice_details' => 'Detalhes da Fatura:',
        'issue_date' => 'Data de Emissão',
        'due_date' => 'Data de Vencimento',
        'description' => 'Descrição',
        'qty' => 'Quantidade',
        'unit_price' => 'Preço Unitário',
        'subtotal' => 'Subtotal',
        'tax' => 'Impostos',
        'discount' => 'Desconto',
        'total' => 'Total',
        'thank_you' => 'Obrigado',
        'auto_invoice' => 'Esta é uma fatura gerada automaticamente pelo Dash-T101.',
        'print_pt' => '🖨️ Imprimir em Português',
        'print_en' => '🖨️ Print in English',
        'print_es' => '🖨️ Imprimir en Español',
        'back' => '← Voltar',
        'print' => '🖨️ Imprimir',
        'status_pending' => 'Pendente',
        'status_paid' => 'Pago',
        'status_overdue' => 'Vencido',
        'status_cancelled' => 'Cancelado',
        'status_unknown' => 'Desconhecido'
    ],
    'en' => [
        'invoice_title' => 'Invoice',
        'bill_to' => 'Bill To:',
        'company' => 'Company',
        'contact' => 'Contact',
        'email' => 'Email',
        'phone' => 'Phone',
        'address' => 'Address',
        'invoice_details' => 'Invoice Details:',
        'issue_date' => 'Issue Date',
        'due_date' => 'Due Date',
        'description' => 'Description',
        'qty' => 'Quantity',
        'unit_price' => 'Unit Price',
        'subtotal' => 'Subtotal',
        'tax' => 'Taxes',
        'discount' => 'Discount',
        'total' => 'Total',
        'thank_you' => 'Thank you',
        'auto_invoice' => 'This invoice was generated automatically by Dash-T101.',
        'print_pt' => '🖨️ Imprimir em Português',
        'print_en' => '🖨️ Print in English',
        'print_es' => '🖨️ Imprimir en Español',
        'back' => '← Back',
        'print' => '🖨️ Print',
        'status_pending' => 'Pending',
        'status_paid' => 'Paid',
        'status_overdue' => 'Overdue',
        'status_cancelled' => 'Cancelled',
        'status_unknown' => 'Unknown'
    ],
    'es' => [
        'invoice_title' => 'Factura',
        'bill_to' => 'Facturar a:',
        'company' => 'Empresa',
        'contact' => 'Contacto',
        'email' => 'Correo',
        'phone' => 'Teléfono',
        'address' => 'Dirección',
        'invoice_details' => 'Detalles de la Factura:',
        'issue_date' => 'Fecha de Emisión',
        'due_date' => 'Fecha de Vencimiento',
        'description' => 'Descripción',
        'qty' => 'Cantidad',
        'unit_price' => 'Precio Unitario',
        'subtotal' => 'Subtotal',
        'tax' => 'Impuestos',
        'discount' => 'Descuento',
        'total' => 'Total',
        'thank_you' => 'Gracias',
        'auto_invoice' => 'Esta factura fue generada automáticamente por Dash-T101.',
        'print_pt' => '🖨️ Imprimir em Português',
        'print_en' => '🖨️ Print in English',
        'print_es' => '🖨️ Imprimir en Español',
        'back' => '← Volver',
        'print' => '🖨️ Imprimir',
        'status_pending' => 'Pendiente',
        'status_paid' => 'Pagado',
        'status_overdue' => 'Vencido',
        'status_cancelled' => 'Cancelado',
        'status_unknown' => 'Desconocido'
    ]
];

// Função para obter tradução
function t($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}

try {
    // Buscar dados da fatura com informações do cliente (incluindo endereço completo)
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            c.company,
            c.name as client_contact_name,
            c.email as client_email,
            c.phone as client_phone,
            c.address_line1,
            c.address_line2,
            c.address_line3
        FROM dash_invoices i
        LEFT JOIN dash_clients c ON i.client_id = c.id
        WHERE i.id = ? AND i.user_id = ?
    ");
    $stmt->execute([$invoice_id, $user_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header('Location: invoices.php');
        exit();
    }

    // Buscar itens da fatura
    $stmt = $pdo->prepare("
        SELECT * FROM dash_invoice_items 
        WHERE invoice_id = ? 
        ORDER BY id
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar dados da empresa/usuário
    $stmt = $pdo->prepare("
        SELECT name, email
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $company_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ajuste de compatibilidade: se não houver 'company', usamos o próprio name
    $company_info['company'] = $company_info['name'];

} catch (PDOException $e) {
    die("❌ Erro ao carregar fatura para impressão: " . $e->getMessage());
}

// Montar endereço completo do cliente
$client_address = trim(
    ($invoice['address_line1'] ?? '') . "\n" .
    ($invoice['address_line2'] ?? '') . "\n" .
    ($invoice['address_line3'] ?? '')
);

// Função para formatar status
function getStatusBadge($status) {
    $badges = [
        'pending'   => [t('status_pending'), '#FF9500'],
        'paid'      => [t('status_paid'), '#34C759'],
        'overdue'   => [t('status_overdue'), '#FF3B30'],
        'cancelled' => [t('status_cancelled'), '#8E8E93']
    ];
    
    $info = $badges[$status] ?? [t('status_unknown'), '#8E8E93'];
    return ['text' => $info[0], 'color' => $info[1]];
}

$status_info = getStatusBadge($invoice['status']);

// Função para formatar data baseada no idioma
function formatDate($date, $lang) {
    $timestamp = strtotime($date);
    switch ($lang) {
        case 'en':
            return date('m/d/Y', $timestamp);
        case 'es':
            return date('d/m/Y', $timestamp);
        default: // pt
            return date('d/m/Y', $timestamp);
    }
}

// Função para formatar moeda baseada no idioma
function formatCurrency($amount, $lang) {
    switch ($lang) {
        case 'en':
            return '$' . number_format($amount, 2, '.', ',');
        case 'es':
            return '€' . number_format($amount, 2, ',', '.');
        default: // pt
            return 'R$ ' . number_format($amount, 2, ',', '.');
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('invoice_title'); ?> #<?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
            line-height: 1.6;
            padding: 40px 20px;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            position: relative;
        }

        .invoice-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .company-info {
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .company-details {
            font-size: 16px;
            opacity: 0.9;
        }

        .invoice-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .invoice-number {
            font-size: 24px;
            font-weight: 600;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .invoice-body {
            padding: 40px;
        }

        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .detail-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1d1d1f;
        }

        .detail-item {
            margin-bottom: 8px;
            font-size: 15px;
        }

        .detail-label {
            font-weight: 500;
            color: #86868b;
            margin-bottom: 4px;
        }

        .detail-value {
            color: #1d1d1f;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .items-table thead {
            background: #f5f5f7;
        }

        .items-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: #1d1d1f;
            border-bottom: 1px solid #e5e5e7;
        }

        .items-table td {
            padding: 16px;
            border-bottom: 1px solid #f5f5f7;
            font-size: 15px;
        }

        .items-table tbody tr:hover {
            background: #fafafa;
        }

        .text-right {
            text-align: right;
        }

        .totals-section {
            background: #f5f5f7;
            border-radius: 12px;
            padding: 24px;
            margin-top: 30px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 15px;
        }

        .total-row.final {
            border-top: 2px solid #d2d2d7;
            margin-top: 12px;
            padding-top: 16px;
            font-size: 18px;
            font-weight: 700;
            color: #1d1d1f;
        }

        .invoice-footer {
            background: #f5f5f7;
            padding: 30px 40px;
            text-align: center;
            font-size: 14px;
            color: #86868b;
        }

        .print-actions {
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .print-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .back-btn {
            background: #f5f5f7;
            color: #1d1d1f;
        }

        .back-btn:hover {
            background: #e5e5e7;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .current-lang {
            background: #34C759;
            box-shadow: 0 2px 8px rgba(52, 199, 89, 0.3);
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-actions {
                display: none;
            }
            
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }
        }

        @media (max-width: 768px) {
            .invoice-details {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .invoice-title {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .items-table {
                font-size: 14px;
            }
            
            .items-table th,
            .items-table td {
                padding: 12px 8px;
            }

            .print-actions {
                flex-direction: column;
                align-items: center;
            }

            .print-btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <a href="invoice_print.php?id=<?php echo $invoice_id; ?>&lang=pt" 
           class="print-btn <?php echo $lang === 'pt' ? 'current-lang' : ''; ?>">
            <?php echo $translations['pt']['print_pt']; ?>
        </a>
        <a href="invoice_print.php?id=<?php echo $invoice_id; ?>&lang=en" 
           class="print-btn <?php echo $lang === 'en' ? 'current-lang' : ''; ?>">
            <?php echo $translations['en']['print_en']; ?>
        </a>
        <a href="invoice_print.php?id=<?php echo $invoice_id; ?>&lang=es" 
           class="print-btn <?php echo $lang === 'es' ? 'current-lang' : ''; ?>">
            <?php echo $translations['es']['print_es']; ?>
        </a>
        <button class="print-btn" onclick="window.print()"><?php echo t('print'); ?></button>
        <button class="print-btn back-btn" onclick="history.back()"><?php echo t('back'); ?></button>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="header-content">
                <div class="company-info">
                    <div class="company-name">
                        <?php echo htmlspecialchars($company_info['company'] ?: $company_info['name']); ?>
                    </div>
                    <div class="company-details">
                        <?php echo htmlspecialchars($company_info['email']); ?>
                    </div>
                </div>
                
                <div class="invoice-title">
                    <div class="invoice-number">
                        <?php echo t('invoice_title'); ?> #<?php echo htmlspecialchars($invoice['invoice_number']); ?>
                    </div>
                    <div class="status-badge" style="color: <?php echo $status_info['color']; ?>">
                        <?php echo $status_info['text']; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="invoice-body">
            <div class="invoice-details">
                <div class="detail-section">
                    <h3><?php echo t('bill_to'); ?></h3>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('company'); ?></div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['company'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('contact'); ?></div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['client_contact_name'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('email'); ?></div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['client_email'] ?: 'N/A'); ?></div>
                    </div>
                    <?php if ($invoice['client_phone']): ?>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('phone'); ?></div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['client_phone']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client_address)): ?>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('address'); ?></div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($client_address)); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="detail-section">
                    <h3><?php echo t('invoice_details'); ?></h3>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('issue_date'); ?></div>
                        <div class="detail-value"><?php echo formatDate($invoice['issue_date'], $lang); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('due_date'); ?></div>
                        <div class="detail-value"><?php echo formatDate($invoice['due_date'], $lang); ?></div>
                    </div>
                    <?php if (!empty($invoice['description'] ?? '')): ?>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo t('description'); ?></div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($invoice['description'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th><?php echo t('description'); ?></th>
                        <th class="text-right"><?php echo t('qty'); ?></th>
                        <th class="text-right"><?php echo t('unit_price'); ?></th>
                        <th class="text-right"><?php echo t('total'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-right"><?php echo number_format($item['quantity'], 2, $lang === 'en' ? '.' : ',', $lang === 'en' ? ',' : '.'); ?></td>
                        <td class="text-right"><?php echo formatCurrency($item['unit_price'], $lang); ?></td>
                        <td class="text-right"><?php echo formatCurrency($item['quantity'] * $item['unit_price'], $lang); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="totals-section">
                <div class="total-row">
                    <span><?php echo t('subtotal'); ?>:</span>
                    <span><?php echo formatCurrency($invoice['subtotal'], $lang); ?></span>
                </div>
                <?php if (!empty($invoice['tax_amount'] ?? 0) && $invoice['tax_amount'] > 0): ?>
                <div class="total-row">
                    <span><?php echo t('tax'); ?>:</span>
                    <span><?php echo formatCurrency($invoice['tax_amount'], $lang); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($invoice['discount_amount'] ?? 0) && $invoice['discount_amount'] > 0): ?>
                <div class="total-row">
                    <span><?php echo t('discount'); ?>:</span>
                    <span>- <?php echo formatCurrency($invoice['discount_amount'], $lang); ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row final">
                    <span><?php echo t('total'); ?>:</span>
                    <span><?php echo formatCurrency($invoice['total_amount'], $lang); ?></span>
                </div>
            </div>
        </div>

        <div class="invoice-footer">
            <p><?php echo t('thank_you'); ?>!</p>
            <p><?php echo t('auto_invoice'); ?></p>
        </div>
    </div>

    <script>
        // Auto-focus para impressão se vier de um link direto
        if (document.referrer.includes('view_invoice.php')) {
            setTimeout(() => {
                if (confirm('<?php echo $lang === "en" ? "Would you like to print this invoice now?" : ($lang === "es" ? "¿Desea imprimir esta factura ahora?" : "Deseja imprimir esta fatura agora?"); ?>')) {
                    window.print();
                }
            }, 500);
        }
    </script>
</body>
</html>