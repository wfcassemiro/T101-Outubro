<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Contato';
$page_description = 'Entre em contato com o Translators101. Estamos aqui para ajudar com suas dúvidas e sugestões.';

$success_message = '';
$error_message = '';

// Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Validações básicas
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email inválido.';
    } else {
        // Aqui você pode implementar o envio de email real
        // Por enquanto, vamos simular o sucesso
        
        // Salvar mensagem no banco (opcional)
        try {
            $stmt = $pdo->prepare("INSERT INTO access_logs (action, resource, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                'contact_form',
                "Name: $name, Email: $email, Subject: $subject",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch(PDOException $e) {
            // Falha silenciosa no log
        }
        
        $success_message = 'Mensagem enviada com sucesso! Retornaremos o contato em breve.';
        
        // Limpar campos após envio bem-sucedido
        $name = $email = $subject = $message = '';
    }
}

include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="glass-hero">
        <h1><i class="fas fa-envelope" style="margin-right: 10px;"></i>Entre em Contato</h1>
        <p>Estamos aqui para ajudar! Entre em contato conosco através de qualquer um dos canais abaixo.</p>
    </section>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">
        <!-- Informações de Contato -->
        <div>
            <h2 style="font-size: 2rem; font-weight: bold; margin-bottom: 30px; color: #fff;">
                <i class="fas fa-comments" style="margin-right: 10px; color: var(--brand-purple);"></i>
                Fale Conosco
            </h2>
            
            <!-- Canais de Contato -->
            <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 40px;">
                <div class="video-card">
                    <div style="display: flex; align-items: center; gap: 20px; padding: 25px;">
                        <div style="background: var(--brand-purple); border-radius: 50%; padding: 15px; flex-shrink: 0;">
                            <i class="fas fa-envelope" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; color: #fff;">Email</h3>
                            <a href="mailto:contato@translators101.com.br" style="color: var(--brand-purple); text-decoration: none; font-weight: 500;">
                                contato@translators101.com.br
                            </a>
                            <p style="color: #bbb; font-size: 0.9rem; margin-top: 5px;">Respondemos em até 24 horas</p>
                        </div>
                    </div>
                </div>
                
                <div class="video-card">
                    <div style="display: flex; align-items: center; gap: 20px; padding: 25px;">
                        <div style="background: #25d366; border-radius: 50%; padding: 15px; flex-shrink: 0;">
                            <i class="fab fa-whatsapp" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; color: #fff;">WhatsApp</h3>
                            <a href="https://wa.me/5519982600771" target="_blank" style="color: #25d366; text-decoration: none; font-weight: 500;">
                                (19) 98260-0771
                            </a>
                            <p style="color: #bbb; font-size: 0.9rem; margin-top: 5px;">Atendimento em horário comercial</p>
                        </div>
                    </div>
                </div>
                
                <div class="video-card">
                    <div style="display: flex; align-items: center; gap: 20px; padding: 25px;">
                        <div style="background: #3498db; border-radius: 50%; padding: 15px; flex-shrink: 0;">
                            <i class="fas fa-clock" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; color: #fff;">Horário de Atendimento</h3>
                            <p style="color: #fff; font-weight: 500;">Segunda a Sexta: 9h às 18h</p>
                            <p style="color: #bbb; font-size: 0.9rem; margin-top: 5px;">Horário de Brasília</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tipos de Suporte -->
            <div class="video-card">
                <div class="video-info">
                    <h3 style="font-size: 1.3rem; font-weight: 600; margin-bottom: 20px; color: #fff;">
                        <i class="fas fa-question-circle" style="margin-right: 10px; color: var(--brand-purple);"></i>
                        Como podemos ajudar?
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-question-circle" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                            <span style="color: #ddd;">Dúvidas sobre planos e assinaturas</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-cog" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                            <span style="color: #ddd;">Suporte técnico e problemas de acesso</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-lightbulb" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                            <span style="color: #ddd;">Sugestões de conteúdo e melhorias</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-certificate" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                            <span style="color: #ddd;">Problemas com certificados</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-handshake" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                            <span style="color: #ddd;">Parcerias e colaborações</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-building" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                            <span style="color: #ddd;">Soluções corporativas</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Formulário de Contato -->
        <div>
            <h2 style="font-size: 2rem; font-weight: bold; margin-bottom: 30px; color: #fff;">
                <i class="fas fa-paper-plane" style="margin-right: 10px; color: var(--brand-purple);"></i>
                Envie uma Mensagem
            </h2>
            
            <?php if ($success_message): ?>
                <div class="alert-success" style="margin-bottom: 25px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert-error" style="margin-bottom: 25px;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="vision-form">
                <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label for="name">Nome Completo *</label>
                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                required
                            />
                        </div>
                        
                        <div>
                            <label for="email">Email *</label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                required
                            />
                        </div>
                    </div>
                    
                    <div>
                        <label for="subject">Assunto *</label>
                        <select name="subject" id="subject" required>
                            <option value="">Selecione o assunto</option>
                            <option value="Dúvidas sobre planos" <?php echo ($subject ?? '') === 'Dúvidas sobre planos' ? 'selected' : ''; ?>>Dúvidas sobre planos</option>
                            <option value="Suporte técnico" <?php echo ($subject ?? '') === 'Suporte técnico' ? 'selected' : ''; ?>>Suporte técnico</option>
                            <option value="Problemas com certificados" <?php echo ($subject ?? '') === 'Problemas com certificados' ? 'selected' : ''; ?>>Problemas com certificados</option>
                            <option value="Sugestões de conteúdo" <?php echo ($subject ?? '') === 'Sugestões de conteúdo' ? 'selected' : ''; ?>>Sugestões de conteúdo</option>
                            <option value="Parcerias" <?php echo ($subject ?? '') === 'Parcerias' ? 'selected' : ''; ?>>Parcerias</option>
                            <option value="Soluções corporativas" <?php echo ($subject ?? '') === 'Soluções corporativas' ? 'selected' : ''; ?>>Soluções corporativas</option>
                            <option value="Cancelamento" <?php echo ($subject ?? '') === 'Cancelamento' ? 'selected' : ''; ?>>Cancelamento</option>
                            <option value="Outros" <?php echo ($subject ?? '') === 'Outros' ? 'selected' : ''; ?>>Outros</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="message">Mensagem *</label>
                        <textarea
                            name="message"
                            id="message"
                            rows="6"
                            placeholder="Descreva sua dúvida ou mensagem..."
                            required
                        ><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="cta-btn" style="width: 100%;">
                        <i class="fas fa-paper-plane" style="margin-right: 8px;"></i>Enviar Mensagem
                    </button>
                </form>
                
                <p style="color: #bbb; font-size: 0.9rem; margin-top: 15px; text-align: center;">
                    * Campos obrigatórios. Responderemos sua mensagem em até 24 horas.
                </p>
            </div>
        </div>
    </div>
    
    <!-- FAQ Rápido -->
    <section class="vision-form" style="margin-top: 60px;">
        <h2 style="font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 40px; color: #fff;">
            <i class="fas fa-question-circle" style="margin-right: 10px; color: var(--brand-purple);"></i>
            Perguntas Mais Frequentes
        </h2>
        
        <div class="video-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px; color: var(--brand-purple);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 15px; color: #fff;">Como cancelar minha assinatura?</h4>
                    <p style="color: #ddd; margin-bottom: 20px; font-size: 0.9rem;">
                        O cancelamento pode ser feito a qualquer momento sem complicações.
                    </p>
                    <a href="/faq.php#cancelamento" class="cta-btn" style="font-size: 0.9rem; padding: 8px 16px;">
                        Ver detalhes <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                    </a>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px; color: var(--brand-purple);">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 15px; color: #fff;">Como gerar certificados?</h4>
                    <p style="color: #ddd; margin-bottom: 20px; font-size: 0.9rem;">
                        Certificados são gerados automaticamente após assistir às palestras.
                    </p>
                    <a href="/faq.php#certificados" class="cta-btn" style="font-size: 0.9rem; padding: 8px 16px;">
                        Ver detalhes <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                    </a>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px; color: var(--brand-purple);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 15px; color: #fff;">Qual plano escolher?</h4>
                    <p style="color: #ddd; margin-bottom: 20px; font-size: 0.9rem;">
                        Todos os planos têm acesso completo. Quanto maior o período, maior a economia.
                    </p>
                    <a href="/planos.php" class="cta-btn" style="font-size: 0.9rem; padding: 8px 16px;">
                        Ver planos <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="/faq.php" class="cta-btn">
                <i class="fas fa-list" style="margin-right: 8px;"></i>Ver Todas as Perguntas
            </a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>
