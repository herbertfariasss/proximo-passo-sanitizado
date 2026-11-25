<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Próximo Passo</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- Toast -->
    <div class="toast" id="toast"></div>
    
    <!-- Auth Screen -->
    <div id="auth-screen" class="auth-screen">
        <div class="auth-card">
            <div class="auth-brand">Próximo <span>Passo</span></div>
            <p class="auth-subtitle">Seu assistente para decisões conscientes</p>
            
            <div class="auth-tabs">
                <button id="tab-login" class="active" onclick="switchAuthTab('login')">Entrar</button>
                <button id="tab-register" onclick="switchAuthTab('register')">Criar conta</button>
            </div>
            
            <div id="form-login" class="auth-form">
                <div class="field"><label>Email</label><input type="email" id="login-email" placeholder="usuario@example.com"></div>
                <div class="field"><label>Senha</label><input type="password" id="login-password" placeholder="••••••"></div>
                <button class="btn btn-full" onclick="doLogin()">Entrar</button>
            </div>
            
            <div id="form-register" class="auth-form" style="display:none">
                <div class="field"><label>Nome</label><input type="text" id="register-name" placeholder="João Silva"></div>
                <div class="field"><label>Email</label><input type="email" id="register-email" placeholder="usuario@example.com"></div>
                <div class="field"><label>Senha</label><input type="password" id="register-password" placeholder="Mínimo 6 caracteres"></div>
                <button class="btn btn-full" onclick="doRegister()">Criar conta</button>
            </div>
        </div>
    </div>
    
    <!-- Chat App -->
    <div id="admin-screen" class="admin-screen" style="display:none">
        <header class="admin-header"><div><strong>Próximo <span>Passo</span></strong><small>Painel administrativo</small></div><button class="btn btn-ghost" onclick="doLogout()">Sair</button></header>
        <main class="admin-main">
            <div class="admin-heading"><div><h1>Visão geral</h1><p>Contas e atividade do sistema</p></div><button class="btn" onclick="loadAdminDashboard()">Atualizar</button></div>
            <div class="admin-filters"><label for="admin-period">Período</label><select id="admin-period" onchange="changeAdminPeriod()"><option value="all">Todo o período</option><option value="today">Hoje</option><option value="7days">Últimos 7 dias</option><option value="month">Este mês</option><option value="lastMonth">Mês passado</option><option value="custom">Personalizado</option></select><div id="admin-custom-dates" class="admin-custom-dates" hidden><label for="admin-from">De</label><input id="admin-from" type="date"><label for="admin-to">Até</label><input id="admin-to" type="date"><button class="btn" onclick="loadAdminDashboard()">Aplicar</button></div></div>
            <div id="admin-error" role="alert"></div>
            <div class="admin-cards" id="admin-cards"></div>
            <div id="admin-charts" aria-label="Gráficos de atividade, custo e presença"></div>
            <section class="admin-panel"><div class="admin-panel-heading"><h2>Usuários</h2><div class="admin-table-controls"><input id="admin-search" type="search" placeholder="Buscar nome ou e-mail" oninput="renderAdminUsers()" aria-label="Buscar usuários"><select id="admin-sort" onchange="renderAdminUsers()" aria-label="Ordenar usuários"><option value="recent">Cadastro recente</option><option value="oldest">Cadastro antigo</option><option value="activity">Última atividade</option><option value="decisions">Mais atividades no período</option><option value="cost">Maior custo da IA</option><option value="name">Nome</option></select></div></div><p class="admin-hint">Atividade: tarefas, objetivos ou decisões criados. Retenção: nova atividade entre o 1º e o 7º dia após o cadastro, entre contas com pelo menos 7 dias. Online: atividade autenticada nos últimos 5 minutos. Custos em USD são estimativas desde o início do registro de tokens.</p><div class="admin-table-wrap"><table><thead><tr><th>Usuário</th><th>Status</th><th>Membro desde</th><th>Última atividade</th><th>Atividades</th><th>Chamadas da IA</th><th>Custo estimado</th><th>Média/chamada</th></tr></thead><tbody id="admin-users"></tbody></table></div></section>
        </main>
    </div>
    <div id="app-screen" class="app-screen" style="display:none">
        <!-- Sidebar Context (Desktop) -->
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <span class="sidebar-title">Contexto</span>
                <button class="sidebar-close" onclick="toggleSidebar()" aria-label="Fechar"><span data-icon="x" data-size="18"></span></button>
            </div>
            
            <div class="sidebar-section conv-section">
                <div class="sidebar-label-row">
                    <div class="sidebar-label">Conversas</div>
                    <button class="icon-btn" id="new-chat-btn" onclick="newConversation()" aria-label="Nova conversa" title="Nova conversa"><span data-icon="plus" data-size="16"></span></button>
                </div>
                <div id="conversation-list" class="conv-list" role="list" aria-live="polite">Carregando conversas...</div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-label">Perfil</div>
                <div class="sidebar-item">
                    <div class="sidebar-item-name" id="sidebar-name">—</div>
                    <div class="sidebar-item-sub" id="sidebar-email">—</div>
                </div>
                <div class="sidebar-stats">
                    <div class="sidebar-stat">
                        <span class="sidebar-stat-value" id="stat-reserva">R$ 0</span>
                        <span class="sidebar-stat-label">Reserva</span>
                    </div>
                    <div class="sidebar-stat">
                        <span class="sidebar-stat-value" id="stat-sobra">R$ 0</span>
                        <span class="sidebar-stat-label">Livre após metas/mês</span>
                    </div>
                    <div class="sidebar-stat">
                        <span class="sidebar-stat-value" id="stat-horas">0h</span>
                        <span class="sidebar-stat-label">Livre/sem</span>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-label sidebar-label-row"><span>Objetivos Ativos</span><button class="sidebar-manage" onclick="openPlanner('goals')">Ver todos</button></div>
                <div id="sidebar-goals" class="sidebar-list">Carregando...</div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-label sidebar-label-row"><span>Tarefas Pendentes</span><button class="sidebar-manage" onclick="openPlanner('tasks')">Ver todas</button></div>
                <div id="sidebar-tasks" class="sidebar-list">Carregando...</div>
            </div>
            
            <div class="sidebar-footer">
                <button class="btn btn-ghost btn-full" onclick="goSettings()"><span data-icon="settings" data-size="16"></span>Configurações</button>
            </div>
        </aside>
        <div id="sidebar-resizer" class="sidebar-resizer" role="separator" aria-label="Redimensionar barra lateral" aria-orientation="vertical" aria-valuemin="240" aria-valuenow="300" tabindex="0" title="Arraste para ajustar a largura da barra lateral"></div>
        
        <!-- Sidebar Overlay (Mobile) -->
        <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
        
        <!-- Main Chat Area -->
        <main class="chat-main">
            <!-- Header -->
            <header class="chat-header">
                <button class="header-toggle" onclick="toggleSidebar()" aria-label="Abrir contexto"><span data-icon="menu" data-size="22"></span></button>
                <div class="header-info">
                    <h1>Próximo <span>Passo</span></h1>
                    <p class="header-subtitle" id="header-status">Como posso ajudar?</p>
                </div>
                <button class="header-settings header-profile-btn" id="header-profile-btn" onclick="openProfile()" aria-label="Abrir meu perfil" title="Meu perfil" aria-haspopup="dialog">
                    <span class="header-avatar" id="header-avatar" aria-hidden="true">?</span>
                    <span class="header-avatar-dot" aria-hidden="true"></span>
                </button>
            </header>
            
            <!-- Chat Messages -->
            <div class="chat-messages" id="chat-messages">
                <!-- Welcome Message -->
                <div class="message assistant welcome" id="welcome-message">
                    <div class="message-avatar"><span data-icon="bot" data-size="20"></span></div>
                    <div class="message-content">
                        <p>Olá! Sou seu assistente para decisões conscientes.</p>
                        <p>Me conte uma situação e eu analiso o impacto financeiro, nos seus objetivos e no seu planejamento — sem escolher por você.</p>
                        <div class="quick-suggestions" id="welcome-suggestions">
                            <button class="suggestion-chip" data-text="Quero comprar um notebook de R$ 3.000 para trabalho"><span data-icon="laptop" data-size="16"></span>Notebook R$ 3.000</button>
                            <button class="suggestion-chip" data-text="Vale a pena fazer um intercâmbio de 6 meses?"><span data-icon="globe" data-size="16"></span>Intercâmbio 6 meses</button>
                            <button class="suggestion-chip" data-text="Devo financiar um carro de R$ 40.000?"><span data-icon="car" data-size="16"></span>Carro R$ 40.000</button>
                            <button class="suggestion-chip" data-text="Tenho R$ 500 livres, o que fazer?"><span data-icon="wallet" data-size="16"></span>R$ 500 livres</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Typing Indicator -->
            <div class="typing-indicator" id="typing-indicator" style="display:none">
                <div class="typing-avatar"><span data-icon="bot" data-size="20"></span></div>
                <div class="typing-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>
            
            <!-- Input Area -->
            <form class="chat-input-area" id="chat-form" onsubmit="sendMessage(event)">
                <div class="input-wrapper">
                    <button type="button" class="input-action" onclick="attachContext()" title="Anexar contexto" aria-label="Anexar tarefa/objetivo"><span data-icon="paperclip" data-size="20"></span></button>
                    <textarea 
                        id="message-input" 
                        placeholder="Descreva sua decisão... (ex: quero comprar um celular de R$ 2.000)"
                        rows="1"
                        autocomplete="off"
                    ></textarea>
                </div>
                <button type="submit" class="send-btn" id="send-btn" aria-label="Enviar">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                    </svg>
                </button>
            </form>
        </main>

        <!-- Goals and Tasks Manager -->
        <div class="modal-overlay" id="planner-modal" onclick="closeModal(event)">
            <div class="modal-content planner-modal" role="dialog" aria-modal="true" aria-labelledby="planner-title" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h2 id="planner-title">Objetivos e tarefas</h2>
                    <button class="modal-close" onclick="closePlanner()" aria-label="Fechar"><span data-icon="x" data-size="18"></span></button>
                </div>
                <div class="planner-tabs" role="tablist" aria-label="Tipo de item">
                    <button id="planner-tab-goals" class="planner-tab" onclick="showPlannerTab('goals')" role="tab">Objetivos</button>
                    <button id="planner-tab-tasks" class="planner-tab" onclick="showPlannerTab('tasks')" role="tab">Tarefas</button>
                </div>
                <div class="modal-body planner-body" id="planner-content"></div>
            </div>
        </div>
        
        <!-- Profile Modal -->
        <div class="modal-overlay" id="profile-modal" onclick="closeModal(event)">
            <div class="modal-content profile-modal" role="dialog" aria-modal="true" aria-labelledby="profile-title" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h2 id="profile-title">Meu perfil</h2>
                    <button class="modal-close" onclick="closeProfileModal()" aria-label="Fechar"><span data-icon="x" data-size="18"></span></button>
                </div>
                <div class="modal-body profile-body" id="profile-content"></div>
            </div>
        </div>
        
        <!-- Logout Confirm Modal -->
        <div class="modal-overlay confirm-overlay" id="logout-modal" onclick="closeModal(event)">
            <div class="modal-content confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="logout-title" aria-describedby="logout-text" onclick="event.stopPropagation()">
                <div class="confirm-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </div>
                <h3 id="logout-title">Sair da conta</h3>
                <p id="logout-text">Tem certeza de que deseja sair da sua conta?</p>
                <p class="confirm-note" id="logout-note" style="display:none"></p>
                <div class="confirm-actions">
                    <button class="btn btn-ghost" id="logout-cancel" onclick="closeLogoutConfirm()">Cancelar</button>
                    <button class="btn btn-danger-solid" id="logout-confirm" onclick="confirmLogout()">Sim, sair</button>
                </div>
            </div>
        </div>
        
        <!-- Delete Conversation Confirm Modal -->
        <div class="modal-overlay confirm-overlay" id="delete-modal" onclick="closeModal(event)">
            <div class="modal-content confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="delete-title" aria-describedby="delete-text" onclick="event.stopPropagation()">
                <div class="confirm-icon"><span data-icon="trash" data-size="26"></span></div>
                <h3 id="delete-title">Excluir conversa</h3>
                <p id="delete-text">Tem certeza de que deseja excluir esta conversa? Todas as mensagens serão apagadas e essa ação não pode ser desfeita.</p>
                <p class="confirm-note" id="delete-name"></p>
                <div class="confirm-actions">
                    <button class="btn btn-ghost" id="delete-cancel" onclick="closeDeleteConfirm()">Cancelar</button>
                    <button class="btn btn-danger-solid" id="delete-confirm" onclick="confirmDeleteConversation()">Excluir</button>
                </div>
            </div>
        </div>
        
        <!-- Settings Modal -->
        <div class="modal-overlay" id="settings-modal" onclick="closeModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h2><span data-icon="settings" data-size="20"></span>Configurações</h2>
                    <button class="modal-close" onclick="closeSettingsModal()"><span data-icon="x" data-size="18"></span></button>
                </div>
                <div class="modal-body" id="settings-content"></div>
            </div>
        </div>
        
        <!-- Context Attach Modal -->
        <div class="modal-overlay" id="context-modal" onclick="closeModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h2><span data-icon="paperclip" data-size="20"></span>Anexar Contexto</h2>
                    <button class="modal-close" onclick="closeContextModal()"><span data-icon="x" data-size="18"></span></button>
                </div>
                <div class="modal-body" id="context-content"></div>
            </div>
        </div>
    </div>
    
    <script src="js/api.js"></script>
    <script>
        // ============================================
        // ÍCONES SVG (substituem emojis)
        // ============================================
        const ICON_PATHS = {
            bot: '<rect x="3" y="8" width="18" height="12" rx="3"/><path d="M12 8V5"/><circle cx="12" cy="3.5" r="1.2"/><path d="M9 14h.01M15 14h.01M9.5 17.5h5"/>',
            laptop: '<rect x="4" y="5" width="16" height="11" rx="2"/><path d="M2 20h20"/>',
            globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>',
            car: '<path d="M5 16l1.5-5.5A2 2 0 0 1 8.4 9h7.2a2 2 0 0 1 1.9 1.5L19 16"/><rect x="3" y="16" width="18" height="4" rx="1.5"/><path d="M7.5 18h.01M16.5 18h.01"/>',
            wallet: '<path d="M20 7H5a2 2 0 0 1 0-4h13v4"/><path d="M3 5v14a2 2 0 0 0 2 2h15V7"/><path d="M16.5 14h.01"/>',
            paperclip: '<path d="M21 11l-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
            x: '<path d="M18 6L6 18M6 6l12 12"/>',
            menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
            user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            alert: '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
            clipboard: '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
            target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
            calendar: '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            refresh: '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/><path d="M3 12A9 9 0 0 1 18.5 5.8L21 8"/><path d="M21 3v5h-5"/>',
            lightbulb: '<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/>',
            clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
            contrast: '<circle cx="12" cy="12" r="9"/><path d="M12 3v18a9 9 0 0 0 0-18z" fill="currentColor"/>',
            palette: '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.7-.8 1.7-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.8-1.7 1.7-1.7H16a5 5 0 0 0 5-5c0-3.9-4-7.2-9-7.2z"/><path d="M7.5 11h.01M10.5 7h.01M15.5 7.5h.01"/>',
            plus: '<path d="M12 5v14M5 12h14"/>',
            trash: '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
            pencil: '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
            circle: '<circle cx="12" cy="12" r="9"/>',
            check: '<path d="M20 6L9 17l-5-5"/>',
            bolt: '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
            settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'
        };
        
        function icon(name, size = 18) {
            const paths = ICON_PATHS[name] || ICON_PATHS.check;
            return `<svg class="ic" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${paths}</svg>`;
        }
        
        // Substitui <span data-icon="nome" data-size="18"> pelo SVG
        function hydrateIcons(root = document) {
            root.querySelectorAll('[data-icon]').forEach(el => {
                el.outerHTML = icon(el.dataset.icon, Number(el.dataset.size) || 18);
            });
        }
        
        // Ícones vindos da API (aceita nomes e os símbolos antigos)
        const API_ICON_MAP = { check: 'check', bolt: 'bolt', wallet: 'wallet', '✓': 'check', '⚡': 'bolt', '💰': 'wallet', '○': 'circle', '◎': 'target' };
        function iconFromApi(value) {
            return icon(API_ICON_MAP[value] || 'check', 22);
        }
        
        // Texto vindo da IA: remove emojis e escapa HTML
        function escapeAI(text) {
            const clean = String(text ?? '')
                .replace(/[\p{Extended_Pictographic}\uFE0F\u200D]/gu, '')
                .replace(/[ \t]{2,}/g, ' ')
                .trim();
            return escapeHtml(clean);
        }
        
        // ============================================
        // ESTADO GLOBAL
        // ============================================
        let state = {
            user: null,
            tasks: [],
            upcomingTasks: [],
            goals: [],
            chatHistory: [],
            isAnalyzing: false,
            attachedContext: null,
            conversations: [],
            currentConversationId: null,
            convLoading: true,
            convError: null,
            pending: null
        };
        
        // ============================================
        // INICIALIZAÇÃO
        // ============================================
        async function init() {
            applyTheme();
            
            if (API.isAuthenticated()) {
                try {
                    const response = await API.getMe();
                    state.user = response.user;
                    applyTheme(); // tema salvo no banco
                    document.getElementById('auth-screen').style.display = 'none';
                    if (state.user.is_admin) {
                        document.getElementById('app-screen').style.display = 'none';
                        document.getElementById('admin-screen').style.display = 'block';
                        import(`${APP_BASE_PATH}/js/admin-charts/charts.js?v=4`).catch(error => {
                            console.error('Erro ao carregar gráficos:', error);
                            document.getElementById('admin-charts').textContent = 'Não foi possível carregar os gráficos.';
                        });
                        await loadAdminDashboard();
                        clearInterval(window.adminRefreshTimer);
                        window.adminRefreshTimer = setInterval(loadAdminDashboard, 60000);
                        return;
                    }
                    document.getElementById('admin-screen').style.display = 'none';
                    document.getElementById('app-screen').style.display = 'flex';
                    await loadInitialData();
                    renderSidebar();
                    updateHeaderAvatar();
                    updateHeaderStatus();
                    if (welcomeHtml === null) welcomeHtml = document.getElementById('welcome-message')?.outerHTML || '';
                    await loadConversations();
                    await restoreConversationFromHash();
                    focusInput();
                } catch (error) {
                    console.error('Erro:', error);
                    showAuthScreen();
                }
            } else {
                showAuthScreen();
            }
            
        }
        
        // ============================================
        // LISTENERS (registrados uma única vez)
        // ============================================
        function setupListeners() {
            setupSidebarResize();
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Enter para enviar, Shift+Enter para nova linha
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage(e);
                }
            });
            
            // Login/registro com Enter
            ['login-email', 'login-password'].forEach(id => {
                document.getElementById(id).addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
            });
            ['register-name', 'register-email', 'register-password'].forEach(id => {
                document.getElementById(id).addEventListener('keydown', e => { if (e.key === 'Enter') doRegister(); });
            });
            
            // Fechar modais com ESC
            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape') return;
                // Camadas: confirmação > edição de nome > demais modais
                if (isModalOpen('delete-modal')) return closeDeleteConfirm();
                if (isModalOpen('logout-modal')) return closeLogoutConfirm();
                if (profileEditing && isModalOpen('profile-modal')) return cancelEditName();
                closeProfileModal();
                closeSettingsModal();
                closeContextModal();
                closePlanner();
                closeSidebar();
            });
            
            // Proteção de páginas privadas: se a sessão foi encerrada
            // (outra aba ou botão "voltar"), volta para a tela de login.
            window.addEventListener('pageshow', e => {
                if (e.persisted && !API.isAuthenticated()) window.location.reload();
            });
            window.addEventListener('storage', e => {
                if ((e.key === 'auth_token' || e.key === null) && !e.newValue) window.location.reload();
            });
        }
        
        function showAuthScreen() {
            document.getElementById('auth-screen').style.display = 'flex';
            document.getElementById('app-screen').style.display = 'none';
            document.getElementById('admin-screen').style.display = 'none';
        }

        let adminUsers = [];
        let adminChartData = null;
        window.addEventListener('admin-charts-ready', () => { if (adminChartData) window.renderAdminCharts(adminChartData); });
        function adminDate(date) {
            const y = date.getFullYear(), m = String(date.getMonth() + 1).padStart(2, '0'), d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }
        function changeAdminPeriod() {
            const custom = document.getElementById('admin-period').value === 'custom';
            document.getElementById('admin-custom-dates').hidden = !custom;
            if (!custom) loadAdminDashboard();
        }
        function adminPeriodQuery() {
            const period = document.getElementById('admin-period').value;
            const today = new Date();
            let from = '', to = '';
            if (period === 'today') from = to = adminDate(today);
            if (period === '7days') { const start = new Date(today); start.setDate(start.getDate() - 6); from = adminDate(start); to = adminDate(today); }
            if (period === 'month') { from = adminDate(new Date(today.getFullYear(), today.getMonth(), 1)); to = adminDate(today); }
            if (period === 'lastMonth') { from = adminDate(new Date(today.getFullYear(), today.getMonth() - 1, 1)); to = adminDate(new Date(today.getFullYear(), today.getMonth(), 0)); }
            if (period === 'custom') {
                from = document.getElementById('admin-from').value;
                to = document.getElementById('admin-to').value;
                if (!from || !to || from > to) throw new Error('Informe um intervalo de datas válido.');
            }
            return from ? `?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}` : '';
        }
        async function loadAdminDashboard() {
            try {
                const result = await API.request('GET', '/admin/dashboard' + adminPeriodQuery());
                adminUsers = result.users;
                adminChartData = result;
                if (window.renderAdminCharts) window.renderAdminCharts(result);
                const labels = [['total', 'Usuários cadastrados'], ['active_month', 'Ativos neste mês'], ['retention_7d', 'Retenção em 7 dias'], ['ai_cost_usd', 'Custo da IA no período']];
                document.getElementById('admin-cards').innerHTML = labels.map(([key, label]) => {
                    const value = result.summary[key];
                    const display = value === null ? '—' : key.startsWith('ai_') ? formatAdminCost(value) : ['task_completion_rate', 'goal_average_progress', 'retention_7d'].includes(key) ? `${Number(value).toLocaleString('pt-BR')}%` : Number(value).toLocaleString('pt-BR');
                    return `<article class="admin-card${key.startsWith('ai_') ? ' admin-card-cost' : ''}"><span>${label}</span><strong>${display}</strong></article>`;
                }).join('');
                document.getElementById('admin-error').textContent = '';
                renderAdminUsers();
            } catch (error) {
                document.getElementById('admin-error').textContent = error.message || 'Não foi possível atualizar o painel.';
            }
        }
        function formatAdminCost(value) {
            return 'US$ ' + Number(value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 4, maximumFractionDigits: 6});
        }
        function renderAdminUsers() {
            const query = document.getElementById('admin-search').value.trim().toLocaleLowerCase('pt-BR');
            const users = adminUsers.filter(user => `${user.nome} ${user.email}`.toLocaleLowerCase('pt-BR').includes(query));
            const sort = document.getElementById('admin-sort').value;
            const numeric = { recent: ['criado_em', -1], oldest: ['criado_em', 1], activity: ['last_activity', -1], decisions: ['activity_count', -1], cost: ['ai_cost_usd', -1] };
            users.sort((a, b) => sort === 'name' ? a.nome.localeCompare(b.nome, 'pt-BR') : (() => {
                const [key, direction] = numeric[sort];
                const av = a[key] ?? '', bv = b[key] ?? '';
                return direction * (typeof av === 'number' || typeof bv === 'number' ? Number(av) - Number(bv) : String(av).localeCompare(String(bv)));
            })());
            const dateText = value => value ? new Date(value.replace(' ', 'T')).toLocaleDateString('pt-BR') : '—';
            document.getElementById('admin-users').innerHTML = users.length ? users.map(user => `<tr><td><strong>${escapeHtml(user.nome)}</strong><small>${escapeHtml(user.email)}</small></td><td><span class="admin-status ${user.online ? 'online' : ''}">${user.online ? 'Online' : 'Desconectado'}</span></td><td>${dateText(user.criado_em)}</td><td>${dateText(user.last_activity)}</td><td>${Number(user.activity_count).toLocaleString('pt-BR')}</td><td>${Number(user.ai_calls).toLocaleString('pt-BR')}</td><td>${formatAdminCost(user.ai_cost_usd)}${user.ai_unpriced_calls ? '<small>Inclui chamadas sem preço</small>' : ''}</td><td>${user.ai_average_usd === null ? '—' : formatAdminCost(user.ai_average_usd)}</td></tr>`).join('') : '<tr><td colspan="8">Nenhum usuário encontrado.</td></tr>';
        }
        
        async function loadInitialData() {
            try {
                const [tasksRes, goalsRes] = await Promise.all([
                    API.getTasks(),
                    API.getGoals()
                ]);
                state.tasks = tasksRes.tasks || [];
                state.upcomingTasks = tasksRes.upcoming_tasks || [];
                state.goals = goalsRes.goals || [];
            } catch (error) {
                console.error('Erro ao carregar:', error);
            }
        }
        
        // ============================================
        // AUTH
        // ============================================
        function switchAuthTab(tab) {
            console.log('switchAuthTab called with:', tab);
            const loginTab = document.getElementById('tab-login');
            const registerTab = document.getElementById('tab-register');
            const loginForm = document.getElementById('form-login');
            const registerForm = document.getElementById('form-register');
            
            if (!loginTab || !registerTab || !loginForm || !registerForm) {
                console.error('Elementos não encontrados!', { loginTab, registerTab, loginForm, registerForm });
                return;
            }
            
            if (tab === 'login') {
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
            } else {
                loginTab.classList.remove('active');
                registerTab.classList.add('active');
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
            }
        }
        
        async function doLogin() {
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            if (!email || !password) return toast('Preencha email e senha');
            
            try {
                await API.login(email, password);
                toast('Bem-vindo!');
                init();
            } catch (error) {
                toast(error.message);
            }
        }
        
        async function doRegister() {
            const name = document.getElementById('register-name').value;
            const email = document.getElementById('register-email').value;
            const password = document.getElementById('register-password').value;
            if (!name || !email || !password) return toast('Preencha todos os campos');
            
            try {
                await API.register(name, email, password);
                toast('Conta criada!');
                init();
            } catch (error) {
                toast(error.message);
            }
        }
        
        // Encerra a sessão (revoga o token no servidor, apaga o token local)
        // e recarrega a página inicial, que mostra a tela de login e
        // descarta qualquer dado privado que ainda estivesse em memória.
        async function doLogout() {
            await API.logout();
            state = { user: null, tasks: [], upcomingTasks: [], goals: [], chatHistory: [], isAnalyzing: false, attachedContext: null };
            window.location.replace(`${APP_BASE_PATH}/`);
        }
        
        // ============================================
        // PERFIL DO USUÁRIO
        // ============================================
        let profileEditing = false;
        let profileSaving = false;
        let logoutMode = 'logout';
        
        const ICONS = {
            pencil: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
            mail: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-10 6L2 7"/></svg>',
            shield: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
            calendar: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
            lock: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
            logout: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
            switchUser: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M17 11l2 2 4-4"/></svg>',
            settings: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'
        };
        
        function isModalOpen(id) {
            const el = document.getElementById(id);
            return !!el && el.style.display === 'flex';
        }
        
        function userInitial(name) {
            const first = [...String(name || '').trim()][0];
            return first ? first.toUpperCase() : '?';
        }
        
        function fmtMemberSince(value) {
            if (!value) return '—';
            const d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d)) return '—';
            return d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        }
        
        function updateHeaderAvatar() {
            const el = document.getElementById('header-avatar');
            if (el && state.user) el.textContent = userInitial(state.user.nome);
        }
        
        // Atualiza tudo que mostra dados do usuário
        function refreshUserUI() {
            renderSidebar();
            updateHeaderAvatar();
            if (isModalOpen('profile-modal')) renderProfile();
        }
        
        function openProfile() {
            if (!state.user) return;
            profileEditing = false;
            profileSaving = false;
            renderProfile();
            document.getElementById('profile-modal').style.display = 'flex';
        }
        
        function closeProfileModal() {
            profileEditing = false;
            document.getElementById('profile-modal').style.display = 'none';
        }
        
        function renderProfile() {
            const u = state.user;
            if (!u) return;
            
            const nameBlock = profileEditing ? `
                <div class="profile-edit">
                    <label for="p-name" class="sr-only">Nome de usuário</label>
                    <input type="text" id="p-name" maxlength="100" value="${escapeHtml(u.nome)}" autocomplete="name" placeholder="Seu nome">
                    <div class="profile-edit-error" id="p-error" role="alert"></div>
                    <div class="profile-edit-actions">
                        <button class="btn btn-ghost" id="p-cancel" onclick="cancelEditName()">Cancelar</button>
                        <button class="btn btn-primary" id="p-save" onclick="saveName()">Salvar alterações</button>
                    </div>
                </div>` : `
                <div class="profile-name-row">
                    <h3 class="profile-name">${escapeHtml(u.nome)}</h3>
                    <button class="icon-btn" onclick="startEditName()" aria-label="Editar nome de usuário" title="Editar perfil">${ICONS.pencil}</button>
                </div>
                <button class="btn btn-ghost btn-sm profile-edit-btn" onclick="startEditName()">Editar perfil</button>`;
            
            document.getElementById('profile-content').innerHTML = `
                <div class="profile-hero">
                    <div class="profile-avatar" aria-hidden="true">${escapeHtml(userInitial(u.nome))}</div>
                    ${nameBlock}
                    <span class="status-chip"><span class="status-dot"></span>Conectado</span>
                </div>
                
                <div class="profile-info">
                    <div class="profile-info-row">
                        <span class="profile-info-icon">${ICONS.mail}</span>
                        <div class="profile-info-text">
                            <span class="profile-info-label">E-mail da conta</span>
                            <span class="profile-info-value">${escapeHtml(u.email)}</span>
                        </div>
                        <span class="profile-lock" title="O e-mail é gerenciado pela autenticação e não pode ser editado aqui">${ICONS.lock}</span>
                    </div>
                    <div class="profile-info-row">
                        <span class="profile-info-icon">${ICONS.shield}</span>
                        <div class="profile-info-text">
                            <span class="profile-info-label">Status da conta</span>
                            <span class="profile-info-value ok-text">Conectado</span>
                        </div>
                    </div>
                    <div class="profile-info-row">
                        <span class="profile-info-icon">${ICONS.calendar}</span>
                        <div class="profile-info-text">
                            <span class="profile-info-label">Membro desde</span>
                            <span class="profile-info-value">${escapeHtml(fmtMemberSince(u.criado_em))}</span>
                        </div>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <button class="profile-action" onclick="profileOpenSettings()">${ICONS.settings}<span>Configurações</span></button>
                    <button class="profile-action" onclick="openLogoutConfirm('switch')">${ICONS.switchUser}<span>Trocar de conta</span></button>
                    <button class="profile-action danger" onclick="openLogoutConfirm('logout')">${ICONS.logout}<span>Sair da conta</span></button>
                </div>
            `;
            
            if (profileEditing) {
                const input = document.getElementById('p-name');
                input.focus();
                input.select();
                input.addEventListener('keydown', e => {
                    if (e.key === 'Enter') { e.preventDefault(); saveName(); }
                });
            }
        }
        
        function startEditName() {
            profileEditing = true;
            renderProfile();
        }
        
        function cancelEditName() {
            if (profileSaving) return;
            profileEditing = false;
            renderProfile();
        }
        
        async function saveName() {
            if (profileSaving) return;
            const input = document.getElementById('p-name');
            const errorEl = document.getElementById('p-error');
            const saveBtn = document.getElementById('p-save');
            const cancelBtn = document.getElementById('p-cancel');
            const name = input.value.replace(/\s+/g, ' ').trim();
            
            if (name.length < 2) { errorEl.textContent = 'O nome deve ter pelo menos 2 caracteres.'; input.focus(); return; }
            if (name.length > 100) { errorEl.textContent = 'O nome deve ter no máximo 100 caracteres.'; input.focus(); return; }
            if (name === state.user.nome) { cancelEditName(); return; }
            
            profileSaving = true;
            errorEl.textContent = '';
            saveBtn.disabled = true;
            cancelBtn.disabled = true;
            saveBtn.textContent = 'Salvando...';
            
            try {
                const res = await API.updateName(name);
                // Usa o que o servidor gravou (fonte da verdade)
                Object.assign(state.user, res.user || { nome: name });
                profileSaving = false;
                profileEditing = false;
                refreshUserUI();
                toast('Nome atualizado!');
            } catch (error) {
                profileSaving = false;
                saveBtn.disabled = false;
                cancelBtn.disabled = false;
                saveBtn.textContent = 'Salvar alterações';
                errorEl.textContent = error.message || 'Não foi possível salvar. Tente novamente.';
            }
        }
        
        function profileOpenSettings() {
            closeProfileModal();
            openSettingsModal();
        }
        
        function openLogoutConfirm(mode = 'logout') {
            logoutMode = mode;
            const note = document.getElementById('logout-note');
            if (mode === 'switch') {
                note.textContent = 'Você será levado à tela de login para entrar com outra conta.';
                note.style.display = 'block';
            } else {
                note.style.display = 'none';
            }
            const btn = document.getElementById('logout-confirm');
            btn.disabled = false;
            btn.textContent = 'Sim, sair';
            document.getElementById('logout-modal').style.display = 'flex';
            document.getElementById('logout-cancel').focus();
        }
        
        function closeLogoutConfirm() {
            document.getElementById('logout-modal').style.display = 'none';
        }
        
        async function confirmLogout() {
            const btn = document.getElementById('logout-confirm');
            btn.disabled = true;
            btn.textContent = 'Saindo...';
            await doLogout();
        }
        
        // ============================================
        // SIDEBAR & HEADER
        // ============================================
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-overlay').classList.toggle('open');
        }
        
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebar-overlay').classList.remove('open');
        }

        function setupSidebarResize() {
            const app = document.getElementById('app-screen');
            const handle = document.getElementById('sidebar-resizer');
            const storageKey = 'pp_sidebar_width';
            const minWidth = 240;
            const maxWidth = () => Math.max(minWidth, Math.min(560, window.innerWidth - 420));
            const clamp = value => Math.max(minWidth, Math.min(maxWidth(), value));
            const setWidth = value => {
                const width = clamp(value);
                app.style.setProperty('--sidebar-width', `${width}px`);
                handle.setAttribute('aria-valuenow', String(width));
                handle.setAttribute('aria-valuemax', String(maxWidth()));
                return width;
            };
            let saved = 300;
            try { saved = Number(localStorage.getItem(storageKey)) || 300; } catch (e) {}
            setWidth(saved);
            let dragging = false;
            handle.addEventListener('pointerdown', event => {
                if (window.innerWidth <= 768) return;
                dragging = true;
                handle.setPointerCapture(event.pointerId);
                document.body.classList.add('sidebar-resizing');
                event.preventDefault();
            });
            handle.addEventListener('pointermove', event => {
                if (dragging) setWidth(event.clientX);
            });
            const finish = event => {
                if (!dragging) return;
                dragging = false;
                document.body.classList.remove('sidebar-resizing');
                const width = event.type === 'pointercancel'
                    ? Number(handle.getAttribute('aria-valuenow')) || 300
                    : setWidth(event.clientX);
                try { localStorage.setItem(storageKey, String(width)); } catch (e) {}
            };
            handle.addEventListener('pointerup', finish);
            handle.addEventListener('pointercancel', finish);
            handle.addEventListener('keydown', event => {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                event.preventDefault();
                const current = Number(handle.getAttribute('aria-valuenow')) || 300;
                const next = setWidth(current + (event.key === 'ArrowRight' ? 20 : -20));
                try { localStorage.setItem(storageKey, String(next)); } catch (e) {}
            });
            window.addEventListener('resize', () => setWidth(Number(handle.getAttribute('aria-valuenow')) || 300));
        }

        let plannerTab = 'goals';
        let plannerBusy = false;

        function openPlanner(tab = 'goals') {
            plannerTab = tab;
            closeSidebar();
            document.getElementById('planner-modal').style.display = 'flex';
            renderPlanner();
        }

        function closePlanner() {
            document.getElementById('planner-modal').style.display = 'none';
        }

        function showPlannerTab(tab) {
            plannerTab = tab;
            renderPlanner();
        }

        function renderPlanner() {
            for (const tab of ['goals', 'tasks']) {
                const button = document.getElementById(`planner-tab-${tab}`);
                button.classList.toggle('active', plannerTab === tab);
                button.setAttribute('aria-selected', plannerTab === tab ? 'true' : 'false');
            }
            const isGoal = plannerTab === 'goals';
            const items = isGoal ? state.goals : state.tasks;
            const active = items.filter(item => !item.cancelado_em && (isGoal ? Number(item.progresso) < 100 : item.status === 'pendente'));
            const completed = items.filter(item => !item.cancelado_em && (isGoal ? Number(item.progresso) >= 100 : item.status === 'concluida'));
            const canceled = items.filter(item => !!item.cancelado_em);
            const card = (item, status) => {
                const id = Number(item.id);
                const name = escapeHtml(isGoal ? item.nome : item.titulo);
                const linkedGoal = !isGoal && item.objetivo_id
                    ? state.goals.find(goal => Number(goal.id) === Number(item.objetivo_id))
                    : null;
                const progress = Math.max(0, Math.min(100, Number(item.progresso) || 0));
                const detail = isGoal
                    ? `<div class="planner-progress"><div class="planner-progress-track"><span style="width:${progress}%"></span></div><strong>${progress}%</strong></div>
                       <div class="planner-meta">${Number(item.tasks_done || 0)} de ${Number(item.tasks_count || 0)} tarefa(s) concluída(s)${item.prazo ? ' · Prazo ' + escapeHtml(fmtDate(item.prazo)) : ''}</div>
                       ${item.monthly_plan ? `<div class="planner-link">Aporte mensal: ${Number(item.monthly_plan.amount).toLocaleString('pt-BR', {style:'currency', currency:'BRL'})} · Dia ${Number(item.monthly_plan.day)} · Origem: ${{sobra:'sobra mensal',reserva:'reserva',outro:'outra fonte'}[item.monthly_plan.funding_source] || 'a confirmar'}</div>
                       ${item.monthly_plan.projected_completion_date ? `<div class="planner-meta">Conclusão prevista: ${escapeHtml(fmtFullDate(item.monthly_plan.projected_completion_date))}</div>` : ''}` : ''}`
                    : `<div class="planner-meta"><span>Vencimento: <strong>${escapeHtml(fmtDate(item.data_vencimento))}</strong></span><span>Prioridade: ${escapeHtml(item.prioridade)}</span></div>
                       ${linkedGoal ? `<div class="planner-link">Objetivo: ${escapeHtml(linkedGoal.nome)}</div>` : ''}
                       ${status === 'completed' ? `<div class="planner-completed-date">Concluída em: ${item.concluida_em ? escapeHtml(new Date(item.concluida_em.replace(' ', 'T')).toLocaleDateString('pt-BR')) : 'data não registrada'}</div>` : ''}`;
                const description = isGoal && item.descricao ? `<p class="planner-description">${escapeHtml(item.descricao)}</p>` : '';
                return `<article class="planner-card">
                    <div class="planner-card-top"><strong>${name}</strong><span class="planner-status ${status}">${status === 'active' ? (isGoal ? 'Ativo' : 'Pendente') : status === 'completed' ? 'Concluído' : 'Cancelado'}</span></div>
                    <div class="planner-detail">${detail}</div>${description}
                    ${status === 'active' ? `<div class="planner-actions"><button class="btn btn-sm" onclick="managePlannerItem('${plannerTab}',${id},'complete')" ${plannerBusy ? 'disabled' : ''}>Concluir</button><button class="btn btn-ghost btn-sm planner-cancel" onclick="managePlannerItem('${plannerTab}',${id},'cancel')" ${plannerBusy ? 'disabled' : ''}>Cancelar</button></div>` : ''}
                </article>`;
            };
            const section = (title, list, status) => `<section class="planner-section"><h3>${title} <span>${list.length}</span></h3>${list.length ? list.map(item => card(item, status)).join('') : '<p class="planner-empty">Nenhum item aqui.</p>'}</section>`;
            const future = !isGoal ? (state.upcomingTasks || []).map(item => {
                const goal = state.goals.find(g => Number(g.id) === Number(item.objetivo_id));
                return `<article class="planner-card planner-card-future"><div class="planner-card-top"><strong>${escapeHtml(item.titulo)}</strong><span class="planner-status">Planejada</span></div>
                    <div class="planner-meta">Prevista para ${escapeHtml(fmtFullDate(item.data_vencimento))}${goal ? ' · Objetivo: ' + escapeHtml(goal.nome) : ''}</div></article>`;
            }).join('') : '';
            document.getElementById('planner-content').innerHTML =
                section(isGoal ? 'Objetivos ativos' : 'Tarefas pendentes', active, 'active') +
                (!isGoal && future ? `<section class="planner-section"><h3>Próximos meses <span>${state.upcomingTasks.length}</span></h3><p class="planner-section-note">Prévia do plano mensal. Cada tarefa ficará disponível no mês correspondente.</p>${future}</section>` : '') +
                section('Concluídos', completed, 'completed') + section('Cancelados', canceled, 'canceled');
        }

        async function managePlannerItem(type, id, action) {
            if (plannerBusy) return;
            const isGoal = type === 'goals';
            const item = (isGoal ? state.goals : state.tasks).find(row => Number(row.id) === id);
            if (!item) return;
            const label = isGoal ? item.nome : item.titulo;
            if (action === 'cancel' && !window.confirm(`Cancelar ${isGoal ? 'o objetivo' : 'a tarefa'} “${label}”? O item ficará no histórico de cancelados.`)) return;
            plannerBusy = true;
            renderPlanner();
            try {
                if (action === 'cancel') await (isGoal ? API.cancelGoal(id) : API.cancelTask(id));
                else await (isGoal ? API.updateGoalProgress(id, 100) : API.completeTask(id));
                await loadInitialData();
                renderSidebar();
                updateHeaderStatus();
                toast(action === 'cancel' ? 'Item cancelado' : 'Item concluído');
            } catch (error) {
                toast(friendlyError(error));
            } finally {
                plannerBusy = false;
                renderPlanner();
            }
        }
        
        function renderSidebar() {
            const u = state.user;
            if (!u) return;
            
            document.getElementById('sidebar-name').textContent = u.nome;
            document.getElementById('sidebar-email').textContent = u.email;
            
            const comprometido = state.goals.filter(g => !g.cancelado_em && Number(g.progresso) < 100 && g.monthly_plan?.funding_source === 'sobra')
                .reduce((total, g) => total + Number(g.monthly_plan.amount || 0), 0);
            const sobra = Number(u.renda_mensal) - Number(u.gastos_mensais) - comprometido;
            document.getElementById('stat-reserva').textContent = fmtMoneyShort(u.reserva);
            document.getElementById('stat-sobra').textContent = fmtMoneyShort(sobra);
            document.getElementById('stat-horas').textContent = u.horas_livres_semana + 'h';
            
            // Objetivos ativos
            const activeGoals = state.goals.filter(g => g.progresso < 100 && !g.cancelado_em);
            const goalsHtml = activeGoals.length ? activeGoals.map(g => `
                <div class="sidebar-goal">
                    <div class="sidebar-goal-name">${escapeHtml(g.nome)}</div>
                    <div class="sidebar-goal-progress">
                        <div class="sidebar-progress-bar"><span style="width:${g.progresso}%"></span></div>
                        <span class="sidebar-progress-text">${g.progresso}%</span>
                    </div>
                </div>
            `).join('') : '<div class="sidebar-empty">Nenhum objetivo ativo</div>';
            document.getElementById('sidebar-goals').innerHTML = goalsHtml;
            
            // Tarefas pendentes (próximas 5)
            const pendingTasks = state.tasks.filter(t => t.status === 'pendente' && !t.cancelado_em)
                .sort((a,b) => new Date(a.data_vencimento) - new Date(b.data_vencimento))
                .slice(0, 5);
            const tasksHtml = pendingTasks.length ? pendingTasks.map(t => `
                <div class="sidebar-task">
                    <div class="sidebar-task-title">${escapeHtml(t.titulo)}</div>
                    <div class="sidebar-task-meta">
                        <span class="sidebar-task-priority priority-${t.prioridade}">${t.prioridade}</span>
                        <span>${fmtDate(t.data_vencimento)}</span>
                    </div>
                </div>
            `).join('') : '<div class="sidebar-empty">Nenhuma tarefa pendente</div>';
            document.getElementById('sidebar-tasks').innerHTML = tasksHtml;
        }
        
        function updateHeaderStatus() {
            const pending = state.tasks.filter(t => t.status === 'pendente' && !t.cancelado_em).length;
            const overdue = state.tasks.filter(t => t.status === 'pendente' && !t.cancelado_em && daysUntil(t.data_vencimento) < 0).length;
            
            let status = 'Como posso ajudar?';
            if (overdue > 0) status = `${overdue} tarefa${overdue>1?'s':''} atrasada${overdue>1?'s':''}`;
            else if (pending > 0) status = `${pending} tarefa${pending>1?'s':''} pendente${pending>1?'s':''}`;
            
            const statusEl = document.getElementById('header-status');
            statusEl.textContent = status;
            statusEl.classList.toggle('status-warn', overdue > 0);
        }
        
        // ============================================
        // CHAT
        // ============================================
        const chatMessages = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const typingIndicator = document.getElementById('typing-indicator');
        
        function focusInput() {
            setTimeout(() => messageInput?.focus(), 100);
        }
        
        function removeWelcome() {
            document.getElementById('welcome-message')?.remove();
        }
        
        function showWelcome() {
            chatMessages.innerHTML = welcomeHtml || '';
        }
        
        async function sendMessage(e) {
            e.preventDefault();
            const text = messageInput.value.trim();
            if (!text) return;
            if (state.isAnalyzing) return toast('Aguarde a resposta atual');
            
            state.isAnalyzing = true;
            sendBtn.disabled = true;
            
            // Cria a conversa no banco se ainda não houver uma aberta
            let conversationId = state.currentConversationId;
            if (!conversationId) {
                try {
                    const created = await API.createConversation();
                    conversationId = created.conversation.id;
                    state.currentConversationId = conversationId;
                    setConversationHash(conversationId);
                    upsertConversation(created.conversation);
                } catch (error) {
                    state.isAnalyzing = false;
                    sendBtn.disabled = false;
                    toast(friendlyError(error)); // o texto continua no campo
                    return;
                }
            }
            
            removeWelcome();
            const clientId = newClientId();
            addMessage('user', text, false, { clientId });
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            await deliverMessage(conversationId, text, clientId);
        }
        
        // Envia (ou reenvia, com o mesmo client_id) e mostra a resposta da IA
        async function deliverMessage(conversationId, text, clientId) {
            state.isAnalyzing = true;
            sendBtn.disabled = true;
            state.pending = { conversationId, clientId };
            if (state.currentConversationId === conversationId) showTyping(true);
            
            try {
                const res = await API.sendConversationMessage(conversationId, text, clientId);
                if (res.user) {
                    state.user = res.user;
                    if (isModalOpen('profile-modal')) renderProfile();
                }
                if (res.assistant_message.formato === 'texto') {
                    await loadInitialData();
                    renderSidebar();
                }
                upsertConversation(res.conversation);
                
                // Só desenha se o usuário ainda estiver nesta conversa
                if (state.currentConversationId === conversationId) {
                    tagUserBubble(clientId, res.user_message.id);
                    const reply = res.assistant_message;
                    if (!document.querySelector(`[data-msg-id="${reply.id}"]`)) {
                        addMessage('assistant', reply.analise || reply.conteudo, !!reply.analise, { id: reply.id });
                    }
                }
            } catch (error) {
                if (state.currentConversationId === conversationId) {
                    addRetryBubble(friendlyError(error), conversationId, text, clientId);
                }
            } finally {
                state.pending = null;
                state.isAnalyzing = false;
                sendBtn.disabled = false;
                showTyping(false);
                focusInput();
                closeSidebar();
            }
        }
        
        // Guarda dados dos envios sem resposta para o botão "Tentar novamente"
        const retryStore = {};
        
        function addRetryBubble(message, conversationId, text, clientId) {
            retryStore[clientId] = { conversationId, text };
            document.querySelectorAll(`[data-retry-for="${clientId}"]`).forEach(el => el.remove());
            const div = addMessage('assistant', message, false, { error: true });
            div.dataset.retryFor = clientId;
            const content = div.querySelector('.message-content');
            const btn = document.createElement('button');
            btn.className = 'btn btn-ghost btn-sm retry-btn';
            btn.innerHTML = icon('refresh', 14) + 'Tentar novamente';
            btn.onclick = () => retryMessage(clientId);
            content.appendChild(btn);
            scrollToBottom();
        }
        
        async function retryMessage(clientId) {
            const item = retryStore[clientId];
            if (!item || state.isAnalyzing) return;
            document.querySelectorAll(`[data-retry-for="${clientId}"]`).forEach(el => el.remove());
            await deliverMessage(item.conversationId, item.text, clientId);
        }
        
        function tagUserBubble(clientId, messageId) {
            const el = document.querySelector(`[data-client-id="${clientId}"]`);
            if (el) el.dataset.msgId = messageId;
        }
        
        function addMessage(role, content, isAnalysis = false, meta = {}) {
            const div = document.createElement('div');
            div.className = `message ${role}${meta.error ? ' message-error' : ''}`;
            if (meta.id) div.dataset.msgId = meta.id;
            if (meta.clientId) div.dataset.clientId = meta.clientId;
            
            if (role === 'user') {
                div.innerHTML = `
                    <div class="message-avatar user-avatar">${escapeHtml(userInitial(state.user?.nome))}</div>
                    <div class="message-content user-content"><p>${escapeHtml(content)}</p></div>
                `;
            } else {
                div.innerHTML = `
                    <div class="message-avatar">${icon('bot', 20)}</div>
                    <div class="message-content">${isAnalysis ? analysisHtml(content) : `<p>${escapeHtml(content)}</p>`}</div>
                `;
            }
            
            chatMessages.appendChild(div);
            scrollToBottom();
            return div;
        }
        
        // ============================================
        // CONVERSAS (persistidas no banco)
        // ============================================
        let openSeq = 0;          // descarta respostas de aberturas antigas
        let welcomeHtml = null;   // HTML da tela inicial (para "Nova conversa")
        let renamingId = null;
        let renameCancelled = false;
        let pendingDeleteId = null;
        
        function newClientId() {
            if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
            return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
        }
        
        function friendlyError(error) {
            // fetch rejeita com TypeError (Chrome: "Failed to fetch", Safari: "Load failed",
            // Firefox: "NetworkError...") quando não há conexão; erros do servidor têm status.
            const networkError = error && error.status === undefined &&
                (error.name === 'TypeError' || /failed to fetch|networkerror|load failed|network request failed/i.test(error.message || ''));
            if (networkError) return 'Sem conexão com o servidor. Verifique sua internet e tente novamente.';
            return (error && error.message) || 'Erro inesperado. Tente novamente.';
        }
        
        function fmtRelative(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d)) return '';
            const min = Math.floor((Date.now() - d.getTime()) / 60000);
            if (min < 1) return 'agora';
            if (min < 60) return `há ${min} min`;
            const h = Math.floor(min / 60);
            if (h < 24) return `há ${h} h`;
            const days = Math.floor(h / 24);
            if (days < 7) return `há ${days} d`;
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
        }
        
        function setConversationHash(id) {
            const url = id ? `#c=${id}` : `${location.pathname}${location.search}`;
            try { history.replaceState(null, '', url); } catch (e) {}
        }
        
        function upsertConversation(conversation) {
            state.conversations = [conversation, ...state.conversations.filter(c => c.id !== conversation.id)];
            renderConversationList();
        }
        
        async function loadConversations() {
            state.convLoading = true;
            state.convError = null;
            renderConversationList();
            try {
                const res = await API.getConversations();
                state.conversations = res.conversations || [];
            } catch (error) {
                state.convError = friendlyError(error);
            } finally {
                state.convLoading = false;
                renderConversationList();
            }
        }
        
        function renderConversationList() {
            const el = document.getElementById('conversation-list');
            if (!el) return;
            
            if (state.convLoading) { el.innerHTML = '<div class="sidebar-empty">Carregando conversas...</div>'; return; }
            if (state.convError) {
                el.innerHTML = `<div class="sidebar-empty">${escapeHtml(state.convError)}</div>
                    <button class="btn btn-ghost btn-sm conv-retry" onclick="loadConversations()">Tentar novamente</button>`;
                return;
            }
            if (!state.conversations.length) {
                el.innerHTML = '<div class="sidebar-empty">Nenhuma conversa ainda. Envie uma mensagem para começar.</div>';
                return;
            }
            
            el.innerHTML = state.conversations.map(c => {
                const active = c.id === state.currentConversationId ? ' active' : '';
                if (c.id === renamingId) {
                    return `<div class="conv-item editing${active}" role="listitem">
                        <input class="conv-rename-input" id="conv-rename" type="text" maxlength="150" value="${escapeHtml(c.titulo)}" aria-label="Novo título da conversa">
                    </div>`;
                }
                return `<div class="conv-item${active}" role="listitem">
                    <button class="conv-main" onclick="openConversation(${c.id})" title="${escapeHtml(c.titulo)}" ${active ? 'aria-current="true"' : ''}>
                        <span class="conv-title">${escapeHtml(c.titulo)}</span>
                        <span class="conv-time">${escapeHtml(fmtRelative(c.atualizado_em_iso))}</span>
                    </button>
                    <div class="conv-actions">
                        <button class="icon-btn" onclick="startRenameConversation(${c.id})" aria-label="Renomear conversa" title="Renomear">${icon('pencil', 14)}</button>
                        <button class="icon-btn danger" onclick="askDeleteConversation(${c.id})" aria-label="Excluir conversa" title="Excluir">${icon('trash', 14)}</button>
                    </div>
                </div>`;
            }).join('');
            
            if (renamingId !== null) {
                const input = document.getElementById('conv-rename');
                if (input) {
                    input.focus();
                    input.select();
                    input.addEventListener('keydown', e => {
                        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
                        if (e.key === 'Escape') { e.stopPropagation(); renameCancelled = true; input.blur(); }
                    });
                    input.addEventListener('blur', () => commitRename(renamingId, input.value));
                }
            }
        }
        
        function newConversation() {
            openSeq++;
            state.currentConversationId = null;
            setConversationHash(null);
            showWelcome();
            showTyping(false);
            renderConversationList();
            closeSidebar();
            focusInput();
        }
        
        async function restoreConversationFromHash() {
            const m = /^#c=(\d+)$/.exec(location.hash);
            if (m) await openConversation(Number(m[1]), { force: true });
        }
        
        async function openConversation(id, opts = {}) {
            if (state.currentConversationId === id && !opts.force) { closeSidebar(); return; }
            const seq = ++openSeq;
            state.currentConversationId = id;
            setConversationHash(id);
            renderConversationList();
            closeSidebar();
            showTyping(false);
            chatMessages.innerHTML = '<div class="chat-state"><span class="spinner"></span>Carregando conversa...</div>';
            
            try {
                const res = await API.getConversation(id);
                if (seq !== openSeq) return; // o usuário já abriu outra conversa
                renderConversationMessages(res.conversation, res.messages);
            } catch (error) {
                if (seq !== openSeq) return;
                if (error.status === 404) {
                    state.conversations = state.conversations.filter(c => c.id !== id);
                    toast('Conversa não encontrada');
                    newConversation();
                    return;
                }
                chatMessages.innerHTML = `<div class="chat-state error"><p>${escapeHtml(friendlyError(error))}</p>
                    <button class="btn btn-ghost" onclick="openConversation(${id}, { force: true })">Tentar novamente</button></div>`;
            }
        }
        
        function renderConversationMessages(conversation, messages) {
            upsertConversationInPlace(conversation);
            chatMessages.innerHTML = '';
            
            if (!messages.length) { showWelcome(); return; }
            
            messages.forEach(m => {
                if (m.remetente === 'usuario') {
                    addMessage('user', m.conteudo, false, { id: m.id, clientId: m.client_id });
                } else if (m.formato === 'analise' && m.analise) {
                    addMessage('assistant', m.analise, true, { id: m.id });
                } else {
                    addMessage('assistant', m.conteudo, false, { id: m.id });
                }
            });
            
            // Última mensagem sem resposta: ainda processando ou falhou
            const last = messages[messages.length - 1];
            if (last.remetente === 'usuario') {
                const inFlight = state.pending && state.pending.conversationId === conversation.id && state.pending.clientId === last.client_id;
                if (inFlight) {
                    showTyping(true);
                } else {
                    addRetryBubble('Esta mensagem ainda não tem resposta.', conversation.id, last.conteudo, last.client_id);
                }
            }
            scrollToBottom();
        }
        
        // Atualiza título/data sem reordenar a lista
        function upsertConversationInPlace(conversation) {
            const i = state.conversations.findIndex(c => c.id === conversation.id);
            if (i >= 0) state.conversations[i] = conversation;
            else state.conversations.unshift(conversation);
            renderConversationList();
        }
        
        function startRenameConversation(id) {
            renamingId = id;
            renameCancelled = false;
            renderConversationList();
        }
        
        async function commitRename(id, value) {
            renamingId = null;
            const current = state.conversations.find(c => c.id === id);
            const title = String(value || '').replace(/\s+/g, ' ').trim();
            
            if (renameCancelled || !current || !title || title === current.titulo) {
                renameCancelled = false;
                renderConversationList();
                return;
            }
            
            try {
                const res = await API.renameConversation(id, title);
                upsertConversation(res.conversation);
                toast('Conversa renomeada');
            } catch (error) {
                renderConversationList();
                toast(friendlyError(error));
            }
        }
        
        function askDeleteConversation(id) {
            const c = state.conversations.find(x => x.id === id);
            if (!c) return;
            pendingDeleteId = id;
            document.getElementById('delete-name').textContent = c.titulo;
            const btn = document.getElementById('delete-confirm');
            btn.disabled = false;
            btn.textContent = 'Excluir';
            document.getElementById('delete-modal').style.display = 'flex';
            document.getElementById('delete-cancel').focus();
        }
        
        function closeDeleteConfirm() {
            pendingDeleteId = null;
            document.getElementById('delete-modal').style.display = 'none';
        }
        
        async function confirmDeleteConversation() {
            const id = pendingDeleteId;
            if (id === null) return;
            const btn = document.getElementById('delete-confirm');
            btn.disabled = true;
            btn.textContent = 'Excluindo...';
            
            try {
                await API.deleteConversation(id);
                state.conversations = state.conversations.filter(c => c.id !== id);
                closeDeleteConfirm();
                if (state.currentConversationId === id) newConversation();
                else renderConversationList();
                toast('Conversa excluída');
            } catch (error) {
                if (error.status === 404) {
                    state.conversations = state.conversations.filter(c => c.id !== id);
                    closeDeleteConfirm();
                    if (state.currentConversationId === id) newConversation(); else renderConversationList();
                    return;
                }
                btn.disabled = false;
                btn.textContent = 'Excluir';
                toast(friendlyError(error));
            }
        }
        
        function showTyping(show) {
            typingIndicator.style.display = show ? 'flex' : 'none';
            if (show) scrollToBottom();
        }
        
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Suggestion chips
        document.addEventListener('click', e => {
            const chip = e.target.closest('.suggestion-chip');
            if (chip) {
                messageInput.value = chip.dataset.text;
                messageInput.style.height = 'auto';
                sendMessage({ preventDefault: () => {} });
            }
        });
        
        // ============================================
        // ANÁLISE HTML
        // ============================================
        function analysisHtml(a) {
            if (a.response_style === 'short') {
                const details = analysisHtml({...a, response_style: 'normal', summary: ''});
                return `<div class="analysis-section analysis-short"><h4>${icon('clipboard')}Em resumo</h4><p>${escapeAI(a.summary || '')}</p></div><details class="analysis-more"><summary>Ver detalhes</summary>${details}</details>`;
            }
            let html = '';
            
            if (a.summary) html += `<div class="analysis-section"><h4>${icon('clipboard')}Resumo</h4><p>${escapeAI(a.summary)}</p></div>`;
            if (a.financial) html += `<div class="analysis-section"><h4>${icon('wallet')}Financeiro</h4><p>${escapeAI(a.financial)}</p></div>`;
            if (a.goals) html += `<div class="analysis-section"><h4>${icon('target')}Objetivos</h4><p>${escapeAI(a.goals)}</p></div>`;
            if (a.planning) html += `<div class="analysis-section"><h4>${icon('calendar')}Planejamento</h4><p>${escapeAI(a.planning)}</p></div>`;
            
            if (a.consequences?.length) {
                html += `<div class="analysis-section"><h4>${icon('alert')}Consequências</h4><ul class="consequences-list">${a.consequences.map(c => `<li>${escapeAI(c)}</li>`).join('')}</ul></div>`;
            }
            
            if (a.alternatives?.length) {
                html += `<div class="analysis-section"><h4>${icon('refresh')}Alternativas</h4><div class="alternatives-grid">${a.alternatives.map(alt => `
                    <div class="alternative-card">
                        <h5>${escapeAI(alt.title)}</h5>
                        <p>${escapeAI(alt.desc)}</p>
                    </div>
                `).join('')}</div></div>`;
            }
            
            html += `<p class="analysis-disclaimer">${icon('lightbulb', 16)}Essas alternativas são neutras — a escolha é sua.</p>`;
            
            return html;
        }
        
        // ============================================
        // QUICK ACTIONS (sugestões rápidas)
        // ============================================
        async function runQuick(kind) {
            const input = document.getElementById(kind === 'time' ? 'qtime' : 'qmoney');
            const value = Number(input?.value) || 0;
            if (!value) return toast('Digite um valor');
            
            try {
                const response = await API.quickSuggest(kind, value);
                const items = response.suggestions || [];
                
                removeWelcome();
                
                if (items.length) {
                    addMessage('user', kind === 'time' ? `Tenho ${value} min livres` : `Tenho R$ ${value} disponíveis`);
                    let html = '<div class="quick-results"><h4>' + (kind === 'time' ? icon('clock') + 'Sugestões de tarefas' : icon('wallet') + 'Sugestões financeiras') + '</h4><div class="quick-results-list">';
                    items.forEach(i => {
                        html += `<div class="quick-result-item"><span class="quick-result-icon">${iconFromApi(i.icon)}</span><div><strong>${escapeAI(i.title)}</strong><br><small>${escapeAI(i.desc)}</small></div></div>`;
                    });
                    html += '</div></div>';
                    addMessage('assistant', html, false);
                } else {
                    toast('Nenhuma sugestão encontrada');
                }
            } catch (error) {
                toast(error.message);
            }
        }
        
        // ============================================
        // CONTEXT MODAL
        // ============================================
        function attachContext() {
            openContextModal();
        }
        
        function openContextModal() {
            const pendingTasks = state.tasks.filter(t => t.status === 'pendente' && !t.cancelado_em);
            const activeGoals = state.goals.filter(g => g.progresso < 100 && !g.cancelado_em);
            
            let html = '<div class="context-tabs">';
            html += '<button class="context-tab active" data-tab="tasks">Tarefas (' + pendingTasks.length + ')</button>';
            html += '<button class="context-tab" data-tab="goals">Objetivos (' + activeGoals.length + ')</button>';
            html += '</div>';
            
            html += '<div class="context-panel active" id="ctx-tasks">';
            if (pendingTasks.length) {
                pendingTasks.forEach(t => {
                    html += `<div class="context-item" role="button" tabindex="0" onclick="selectContext('task','${t.id}')">
                        <div><strong>${escapeHtml(t.titulo)}</strong><br><small>${t.prioridade} · ${fmtDate(t.data_vencimento)} · ${t.tempo_estimado}min</small></div>
                    </div>`;
                });
            } else {
                html += '<p class="context-empty">Nenhuma tarefa pendente</p>';
            }
            html += '</div>';
            
            html += '<div class="context-panel" id="ctx-goals">';
            if (state.goals.length) {
                state.goals.forEach(g => {
                    html += `<div class="context-item" role="button" tabindex="0" onclick="selectContext('goal','${g.id}')">
                        <div><strong>${escapeHtml(g.nome)}</strong><br><small>${g.progresso}% · ${fmtDate(g.prazo)}</small></div>
                    </div>`;
                });
            } else {
                html += '<p class="context-empty">Nenhum objetivo</p>';
            }
            html += '</div>';
            
            html += '<div class="modal-actions"><button class="btn btn-ghost" onclick="closeContextModal()">Cancelar</button></div>';
            
            document.getElementById('context-content').innerHTML = html;
            document.getElementById('context-modal').style.display = 'flex';
            
            // Tab switching
            document.querySelectorAll('.context-tab').forEach(btn => {
                btn.onclick = () => {
                    document.querySelectorAll('.context-tab').forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.context-panel').forEach(p => p.classList.remove('active'));
                    btn.classList.add('active');
                    document.getElementById('ctx-' + btn.dataset.tab).classList.add('active');
                };
            });
        }
        
        function selectContext(type, id) {
            const item = type === 'task' 
                ? state.tasks.find(t => String(t.id) === String(id))
                : state.goals.find(g => String(g.id) === String(id));
            
            if (!item) return;
            
            state.attachedContext = { type, id, ...item };
            const msg = type === 'task' ? `Anexou tarefa: ${item.titulo}` : `Anexou objetivo: ${item.nome}`;
            toast(msg);
            closeContextModal();
            
            // Pré-preencher input
            const prefix = type === 'task' 
                ? `Sobre a tarefa "${item.titulo}" (${item.prioridade}, ${fmtDate(item.data_vencimento)}): `
                : `Sobre o objetivo "${item.nome}" (${item.progresso}%): `;
            messageInput.value = prefix;
            messageInput.style.height = 'auto';
            focusInput();
        }
        
        function closeContextModal() {
            document.getElementById('context-modal').style.display = 'none';
        }
        
        // ============================================
        // SETTINGS MODAL
        // ============================================
        function goSettings() {
            openSettingsModal();
        }
        
        async function openSettingsModal() {
            const u = state.user;
            document.getElementById('settings-content').innerHTML = `
                <div class="settings-section">
                    <h3>${icon('user', 16)}Perfil</h3>
                    <div class="field"><label>Nome</label><input type="text" id="s-name" value="${escapeHtml(u.nome)}"></div>
                    <div class="field"><label>Email</label><input type="email" id="s-email" value="${escapeHtml(u.email)}" readonly disabled aria-describedby="s-email-hint"><small class="field-hint" id="s-email-hint">O e-mail é gerenciado pela autenticação e não pode ser alterado aqui.</small></div>
                </div>
                
                <div class="settings-section">
                    <h3>${icon('wallet', 16)}Financeiro</h3>
                    <div class="settings-grid">
                        <div class="field"><label>Renda mensal</label><input type="number" step="0.01" id="s-income" value="${u.renda_mensal}"></div>
                        <div class="field"><label>Gastos mensais</label><input type="number" step="0.01" id="s-expenses" value="${u.gastos_mensais}"></div>
                        <div class="field"><label>Reserva</label><input type="number" step="0.01" id="s-savings" value="${u.reserva}"></div>
                        <div class="field"><label>Perfil de risco</label>
                            <select id="s-risk">
                                <option value="conservador" ${u.perfil_risco==='conservador'?'selected':''}>Conservador</option>
                                <option value="moderado" ${u.perfil_risco==='moderado'?'selected':''}>Moderado</option>
                                <option value="arrojado" ${u.perfil_risco==='arrojado'?'selected':''}>Arrojado</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>${icon('clock', 16)}Disponibilidade</h3>
                    <div class="field"><label>Horas livres/semana</label><input type="number" id="s-hours" value="${u.horas_livres_semana}"></div>
                </div>

                <div class="settings-section">
                    <h3>${icon('bot', 16)}Configurações da IA</h3>
                    <p class="settings-help">Escolha o nível de detalhe das próximas análises. Os cálculos e as verificações continuam os mesmos.</p>
                    <div class="field"><label for="s-ai-style">Tipo de resposta</label>
                        <select id="s-ai-style">
                            <option value="short" ${u.estilo_resposta_ia==='short'?'selected':''}>Direta / curta — pontos essenciais em poucas frases</option>
                            <option value="normal" ${!u.estilo_resposta_ia || u.estilo_resposta_ia==='normal'?'selected':''}>Normal — foco no que mais importa</option>
                            <option value="technical" ${u.estilo_resposta_ia==='technical'?'selected':''}>Técnica / mais longa — análise e cálculos detalhados</option>
                        </select>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>${icon('palette', 16)}Tema</h3>
                    <div class="theme-options">
                        <button class="theme-btn ${u.tema==='light'?'active':''}" onclick="setTheme('light')">${icon('sun', 16)}Claro</button>
                        <button class="theme-btn ${u.tema==='dark'?'active':''}" onclick="setTheme('dark')">${icon('moon', 16)}Escuro</button>
                        <button class="theme-btn ${u.tema==='auto'?'active':''}" onclick="setTheme('auto')">${icon('contrast', 16)}Auto</button>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-ghost" onclick="closeSettingsModal()">Cancelar</button>
                    <button class="btn" onclick="saveSettings()">Salvar</button>
                </div>
            `;
            document.getElementById('settings-modal').style.display = 'flex';
        }
        
        async function saveSettings() {
            try {
                const data = {
                    name: document.getElementById('s-name').value.trim() || state.user.nome,
                    monthly_income: Number(document.getElementById('s-income').value) || 0,
                    monthly_expenses: Number(document.getElementById('s-expenses').value) || 0,
                    savings: Number(document.getElementById('s-savings').value) || 0,
                    risk_profile: document.getElementById('s-risk').value,
                    weekly_free_hours: Number(document.getElementById('s-hours').value) || 0,
                    ai_response_style: document.getElementById('s-ai-style').value
                };
                
                await API.updateProfile(data);
                Object.assign(state.user, {
                    nome: data.name,
                    renda_mensal: data.monthly_income,
                    gastos_mensais: data.monthly_expenses,
                    reserva: data.savings,
                    perfil_risco: data.risk_profile,
                    horas_livres_semana: data.weekly_free_hours,
                    estilo_resposta_ia: data.ai_response_style
                });
                refreshUserUI();
                toast('Salvo!');
                closeSettingsModal();
            } catch (error) {
                toast(error.message);
            }
        }
        
        function closeSettingsModal() {
            document.getElementById('settings-modal').style.display = 'none';
        }
        
        function closeModal(e) {
            if (e.target === e.currentTarget) {
                if (e.currentTarget.id === 'delete-modal') return closeDeleteConfirm();
                if (e.currentTarget.id === 'logout-modal') return closeLogoutConfirm();
                closeProfileModal();
                closeSettingsModal();
                closeContextModal();
                closePlanner();
            }
        }
        
        // ============================================
        // TEMA
        // ============================================
        // Dica local só para a primeira pintura da página (evita "piscar" no tema errado
        // antes de a API responder). A fonte da verdade é sempre o banco de dados.
        const THEME_HINT_KEY = 'pp_theme';
        const VALID_THEMES = ['light', 'dark', 'auto'];
        
        function applyTheme() {
            let theme = state.user?.tema;
            if (!VALID_THEMES.includes(theme)) {
                try { theme = localStorage.getItem(THEME_HINT_KEY); } catch (e) { theme = null; }
            }
            if (!VALID_THEMES.includes(theme)) theme = 'auto';
            
            document.documentElement.setAttribute('data-theme', theme);
            if (state.user && VALID_THEMES.includes(state.user.tema)) {
                try { localStorage.setItem(THEME_HINT_KEY, state.user.tema); } catch (e) {}
            }
        }
        
        function markActiveTheme(t) {
            document.querySelectorAll('.theme-btn').forEach(b => {
                const isActive = b.textContent.includes(t === 'light' ? 'Claro' : t === 'dark' ? 'Escuro' : 'Auto');
                b.classList[isActive ? 'add' : 'remove']('active');
            });
        }
        
        async function setTheme(t) {
            if (!VALID_THEMES.includes(t)) return;
            const previous = state.user.tema;
            
            // Aplica na hora; se o servidor não confirmar, volta ao tema anterior
            state.user.tema = t;
            applyTheme();
            markActiveTheme(t);
            
            try {
                const res = await API.updateTheme(t);
                if (res.user && VALID_THEMES.includes(res.user.tema)) state.user.tema = res.user.tema;
            } catch (error) {
                state.user.tema = previous;
                applyTheme();
                markActiveTheme(previous);
                toast(friendlyError(error));
            }
        }
        
        // ============================================
        // UTILS
        // ============================================
        function fmtMoney(n) { return "R$ " + Number(n).toLocaleString("pt-BR", {minimumFractionDigits:2}); }
        function fmtMoneyShort(n) { return "R$ " + Number(n || 0).toLocaleString("pt-BR", {maximumFractionDigits:0}); }
        function fmtDate(d) { const dt = new Date(d + "T00:00:00"); return dt.toLocaleDateString("pt-BR", {day:"2-digit", month:"short"}); }
        function fmtFullDate(d) { const dt = new Date(d + "T00:00:00"); return dt.toLocaleDateString("pt-BR", {day:"2-digit", month:"2-digit", year:"numeric"}); }
        function daysUntil(d) { return Math.ceil((new Date(d + "T00:00:00") - new Date(new Date().toDateString())) / 86400000); }
        function escapeHtml(text) {
            const entities = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => entities[m]);
        }
        
        function toast(msg) {
            const el = document.getElementById('toast');
            el.textContent = msg; el.classList.add('show');
            clearTimeout(window._tt); window._tt = setTimeout(() => el.classList.remove('show'), 2500);
        }
        
        // ============================================
        // INICIAR
        // ============================================
        document.addEventListener('DOMContentLoaded', () => { hydrateIcons(); setupListeners(); init(); });
    </script>
</body>
</html>
