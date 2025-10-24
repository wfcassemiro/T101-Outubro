<?php
/**
 * PATCH PARA A SEÇÃO DO PLAYER NO INDEX.PHP
 * 
 * INSTRUÇÕES:
 * Este código deve substituir a seção do player (aproximadamente linhas 116-144)
 * 
 * Localize no seu index.php:
 * <div class="video-card player-card">
 *     <?php if ($is_live_active): ?>
 *         <div class="live-player">
 *             <div class="player-container">
 *                 <?php echo $live_embed_code; ?>
 *             </div>
 *         ...
 *     <?php else: ?>
 *         <div class="offline-player">
 *         ...
 * 
 * E substitua por este código:
 */
?>

<!-- =============================================== -->
<!-- INÍCIO DO CÓDIGO DO PLAYER COM ZOOM -->
<!-- =============================================== -->

<div class="video-card player-card">
    <?php if ($is_live_active): ?>
        <?php if ($meeting_type === 'zoom' && $currentZoomMeeting): ?>
            <!-- REUNIÃO ZOOM ATIVA -->
            <div class="zoom-meeting-header" style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 12px 12px 0 0;
                margin: -16px -16px 16px -16px;
            ">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <div style="
                        background: #48bb78;
                        width: 12px;
                        height: 12px;
                        border-radius: 50%;
                        animation: pulse 1.5s infinite;
                    "></div>
                    <span style="font-weight: 600; font-size: 14px;">REUNIÃO AO VIVO</span>
                </div>
                <h3 style="margin: 0; font-size: 24px; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($currentZoomMeeting['topic']); ?>
                </h3>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 14px; opacity: 0.95;">
                    <div>
                        <i class="fas fa-calendar"></i>
                        <?php echo date('d/m/Y', strtotime($currentZoomMeeting['start_time'])); ?>
                    </div>
                    <div>
                        <i class="fas fa-clock"></i>
                        <?php echo date('H:i', strtotime($currentZoomMeeting['start_time'])); ?>
                    </div>
                    <div>
                        <i class="fas fa-hourglass-half"></i>
                        <?php echo $currentZoomMeeting['duration']; ?> minutos
                    </div>
                </div>
                <?php if (!empty($currentZoomMeeting['agenda'])): ?>
                    <div style="margin-top: 12px; font-size: 14px; opacity: 0.9;">
                        <?php echo htmlspecialchars($currentZoomMeeting['agenda']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="live-player">
                <!-- Botão para abrir em nova janela -->
                <div style="text-align: center; padding: 20px; background: rgba(0,0,0,0.8); border-radius: 10px;">
                    <p style="color: white; margin-bottom: 15px; font-size: 16px;">
                        <i class="fas fa-info-circle"></i>
                        Para participar da reunião, clique no botão abaixo:
                    </p>
                    <a href="<?php echo htmlspecialchars($currentZoomMeeting['join_url']); ?>" 
                       target="_blank" 
                       class="cta-btn"
                       style="
                           background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                           color: white;
                           padding: 15px 40px;
                           border-radius: 30px;
                           text-decoration: none;
                           display: inline-flex;
                           align-items: center;
                           gap: 10px;
                           font-weight: 600;
                           font-size: 16px;
                           box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
                           transition: all 0.3s ease;
                       "
                       onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 25px rgba(102, 126, 234, 0.6)';"
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 20px rgba(102, 126, 234, 0.4)';">
                        <i class="fas fa-video" style="font-size: 20px;"></i>
                        <span>Entrar na Reunião Zoom</span>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <p style="color: #999; margin-top: 15px; font-size: 13px;">
                        A reunião será aberta em uma nova janela
                    </p>
                </div>
                
                <!-- Iframe alternativo (pode não funcionar devido a restrições do Zoom) -->
                <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 10px; text-align: center;">
                    <p style="color: #ccc; font-size: 13px; margin-bottom: 10px;">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Dica:</strong> Para melhor experiência, use o aplicativo Zoom instalado
                    </p>
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 10px;">
                        <span style="color: #999; font-size: 12px;">ID da Reunião: <?php echo $currentZoomMeeting['meeting_id']; ?></span>
                        <?php if (!empty($currentZoomMeeting['password'])): ?>
                            <span style="color: #999; font-size: 12px;">Senha: <?php echo htmlspecialchars($currentZoomMeeting['password']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- EMBED CODE PADRÃO -->
            <div class="live-player">
                <div class="player-container">
                    <?php echo $live_embed_code; ?>
                </div>
                <div class="player-controls">
                    <button class="control-btn" onclick="toggleFullscreen()">
                        <i class="fas fa-expand"></i> Tela Cheia
                    </button>
                    <button class="control-btn" onclick="togglePictureInPicture()">
                        <i class="fas fa-external-link-alt"></i> PiP
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- PLAYER OFFLINE -->
        <div class="offline-player">
            <div class="offline-content">
                <i class="fas fa-video-slash"></i>
                <h3>Transmissão Offline</h3>
                <p>No momento não há transmissões ao vivo.</p>
                <p>Fique atento às nossas redes sociais para saber quando a próxima live começará!</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i> Instagram</a>
                    <a href="#" class="social-link"><i class="fab fa-youtube"></i> YouTube</a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin"></i> LinkedIn</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- =============================================== -->
<!-- FIM DO CÓDIGO DO PLAYER COM ZOOM -->
<!-- =============================================== -->

<!-- CSS ADICIONAL PARA O ZOOM (adicione no <style> ou em arquivo separado) -->
<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

.zoom-meeting-header {
    position: relative;
    overflow: hidden;
}

.zoom-meeting-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    100% { left: 100%; }
}
</style>

<?php
/**
 * OPCIONAL: Adicionar seção de próximas reuniões Zoom
 * Adicione este código antes do fechamento da div.main-content (antes da última seção)
 */
?>

<!-- =============================================== -->
<!-- SEÇÃO DE PRÓXIMAS REUNIÕES ZOOM (OPCIONAL) -->
<!-- =============================================== -->

<?php if (!empty($upcomingZoomMeetings) && count($upcomingZoomMeetings) > 0): ?>
<div class="video-card" style="margin-top: 40px;">
    <h2 style="display: flex; align-items: center; gap: 10px; color: #fff; margin-bottom: 25px;">
        <i class="fas fa-calendar-check"></i>
        Próximas Reuniões Zoom
    </h2>
    
    <div class="lectures-grid" style="
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    ">
        <?php foreach ($upcomingZoomMeetings as $meeting): ?>
            <?php
            $isFuture = strtotime($meeting['start_time']) > time();
            $isHappening = strtotime($meeting['start_time']) <= time() && 
                          strtotime($meeting['start_time']) + ($meeting['duration'] * 60) >= time();
            ?>
            <div class="lecture-card" style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 25px;
                border-radius: 15px;
                position: relative;
                <?php echo $meeting['show_live'] ? 'border: 3px solid #48bb78; box-shadow: 0 0 25px rgba(72, 187, 120, 0.5);' : ''; ?>
            ">
                <?php if ($meeting['show_live']): ?>
                    <div style="
                        position: absolute;
                        top: 15px;
                        right: 15px;
                        background: #48bb78;
                        color: white;
                        padding: 5px 12px;
                        border-radius: 20px;
                        font-size: 11px;
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        gap: 5px;
                    ">
                        <span style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 1.5s infinite;"></span>
                        NO AR
                    </div>
                <?php endif; ?>
                
                <h4 style="margin-bottom: 15px; font-size: 18px; padding-right: 70px;">
                    <?php echo htmlspecialchars($meeting['topic']); ?>
                </h4>
                
                <div style="font-size: 14px; opacity: 0.95; margin: 10px 0;">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d/m/Y', strtotime($meeting['start_time'])); ?>
                </div>
                
                <div style="font-size: 14px; opacity: 0.95; margin: 10px 0;">
                    <i class="fas fa-clock"></i>
                    <?php echo date('H:i', strtotime($meeting['start_time'])); ?>h
                    (<?php echo $meeting['duration']; ?> min)
                </div>
                
                <?php if (!empty($meeting['agenda'])): ?>
                    <div style="
                        font-size: 13px;
                        opacity: 0.9;
                        margin-top: 12px;
                        padding-top: 12px;
                        border-top: 1px solid rgba(255,255,255,0.2);
                    ">
                        <?php echo htmlspecialchars($meeting['agenda']); ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 20px;">
                    <?php if ($isHappening): ?>
                        <a href="<?php echo htmlspecialchars($meeting['join_url']); ?>" 
                           target="_blank" 
                           class="cta-btn" 
                           style="
                               background: #48bb78;
                               color: white;
                               padding: 12px 24px;
                               border-radius: 10px;
                               text-decoration: none;
                               display: inline-flex;
                               align-items: center;
                               gap: 8px;
                               font-weight: 600;
                               font-size: 14px;
                           ">
                            <i class="fas fa-play-circle"></i>
                            Entrar Agora
                        </a>
                    <?php elseif ($isFuture): ?>
                        <a href="<?php echo htmlspecialchars($meeting['join_url']); ?>" 
                           target="_blank" 
                           class="cta-btn" 
                           style="
                               background: white;
                               color: #667eea;
                               padding: 12px 24px;
                               border-radius: 10px;
                               text-decoration: none;
                               display: inline-flex;
                               align-items: center;
                               gap: 8px;
                               font-weight: 600;
                               font-size: 14px;
                           ">
                            <i class="fas fa-calendar-plus"></i>
                            Ver Detalhes
                        </a>
                    <?php else: ?>
                        <span style="color: rgba(255,255,255,0.6); font-size: 13px;">
                            <i class="fas fa-check-circle"></i> Reunião encerrada
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- =============================================== -->
<!-- FIM DA SEÇÃO DE PRÓXIMAS REUNIÕES ZOOM -->
<!-- =============================================== -->
