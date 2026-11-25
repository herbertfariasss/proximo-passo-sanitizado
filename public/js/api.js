/**
 * ============================================
 * API Client - Camada de Comunicação
 * ============================================
 * 
 * Este arquivo gerencia todas as chamadas HTTP para o backend
 * Centraliza: autenticação, headers, tratamento de erros
 */

// O frontend pode ser servido na raiz ou em um subdiretório
// (por exemplo, /proximo-passo/ ou /proximo-passo/public/index.php).
const currentPath = window.location.pathname;
const publicPathIndex = currentPath.indexOf('/public/');
const APP_BASE_PATH = publicPathIndex >= 0
    ? currentPath.slice(0, publicPathIndex)
    : currentPath.replace(/\/(?:index\.php)?$/, '').replace(/\/+$/, '');
const API_BASE = `${APP_BASE_PATH}/api`;

// Token de autenticação (armazenado no localStorage)
let authToken = localStorage.getItem('auth_token');

/**
 * Cliente API - Objeto principal
 */
const API = {
    
    // ============================================
    // CONFIGURAÇÃO
    // ============================================
    
    /**
     * Define o token de autenticação
     */
    setToken(token) {
        authToken = token;
        if (token) {
            localStorage.setItem('auth_token', token);
        } else {
            localStorage.removeItem('auth_token');
        }
    },
    
    /**
     * Remove token (logout)
     */
    clearToken() {
        authToken = null;
        localStorage.removeItem('auth_token');
    },
    
    /**
     * Obtém token atual
     */
    getToken() {
        return authToken;
    },
    
    /**
     * Monta headers padrão para requisições
     */
    getHeaders() {
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (authToken) {
            headers['Authorization'] = `Bearer ${authToken}`;
        }
        
        return headers;
    },
    
    /**
     * Faz requisição HTTP genérica
     */
    async request(method, endpoint, data = null) {
        const url = `${API_BASE}${endpoint}`;
        
        const options = {
            method: method,
            headers: this.getHeaders()
        };
        
        // Adicionar body para POST/PUT
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const body = await response.text();
            let json;
            try {
                json = JSON.parse(body);
            } catch {
                throw new Error('O servidor retornou uma resposta inválida. Tente novamente.');
            }
            
            // Se erro 401 (não autorizado), fazer logout
            const isAuthCall = endpoint === '/auth/login' || endpoint === '/auth/register';
            if (response.status === 401 && !isAuthCall) {
                this.clearToken();
                window.location.href = `${APP_BASE_PATH}/`;
                throw new Error('Sessão expirada. Faça login novamente.');
            }
            
            // Se resposta não foi ok, lançar erro
            if (!response.ok) {
                const err = new Error(json.message || json.error || 'Erro na requisição');
                err.status = response.status;
                throw err;
            }
            
            return json;
            
        } catch (error) {
            console.error(`Erro na requisição ${method} ${endpoint}:`, error);
            throw error;
        }
    },
    
    // ============================================
    // AUTENTICAÇÃO
    // ============================================
    
    /**
     * Registrar novo usuário
     */
    async register(name, email, password) {
        const response = await this.request('POST', '/auth/register', {
            name,
            email,
            password
        });
        
        if (response.token) {
            this.setToken(response.token);
        }
        
        return response;
    },
    
    /**
     * Fazer login
     */
    async login(email, password) {
        const response = await this.request('POST', '/auth/login', {
            email,
            password
        });
        
        if (response.token) {
            this.setToken(response.token);
        }
        
        return response;
    },
    
    /**
     * Obter dados do usuário logado
     */
    async getMe() {
        return await this.request('GET', '/auth/me');
    },
    
    /**
     * Atualizar perfil
     */
    async updateProfile(data) {
        return await this.request('PUT', '/auth/profile', data);
    },
    
    /**
     * Atualizar somente o tema (light, dark ou auto)
     */
    async updateTheme(theme) {
        return await this.request('PUT', '/auth/theme', { theme });
    },
    
    /**
     * Atualizar somente o nome de exibição
     */
    async updateName(name) {
        return await this.request('PUT', '/auth/name', { name });
    },
    
    /**
     * Encerrar sessão: revoga o token no servidor (best-effort)
     * e sempre remove o token local.
     */
    async logout() {
        const token = authToken;
        this.clearToken();
        if (!token) return;
        try {
            await fetch(`${API_BASE}/auth/logout`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                keepalive: true
            });
        } catch (e) {
            // Sem rede: o token local já foi removido
        }
    },
    
    // ============================================
    // CONVERSAS (histórico persistido no banco)
    // ============================================
    
    async getConversations() {
        return await this.request('GET', '/conversations');
    },
    
    async createConversation(title = null) {
        return await this.request('POST', '/conversations', title ? { title } : {});
    },
    
    async getConversation(id) {
        return await this.request('GET', `/conversations/${id}`);
    },
    
    async renameConversation(id, title) {
        return await this.request('PUT', `/conversations/${id}`, { title });
    },
    
    async deleteConversation(id) {
        return await this.request('DELETE', `/conversations/${id}`);
    },
    
    /**
     * Envia a mensagem e recebe a resposta da IA (ambas ficam salvas).
     * O client_id torna o envio idempotente: repetir com o mesmo
     * client_id nunca duplica mensagens.
     */
    async sendConversationMessage(id, content, clientId) {
        return await this.request('POST', `/conversations/${id}/messages`, {
            content,
            client_id: clientId
        });
    },
    
    /**
     * Alterar senha
     */
    async changePassword(currentPassword, newPassword) {
        return await this.request('PUT', '/auth/password', {
            current_password: currentPassword,
            new_password: newPassword
        });
    },
    
    // ============================================
    // TAREFAS
    // ============================================
    
    /**
     * Listar todas as tarefas
     */
    async getTasks(filters = {}) {
        let endpoint = '/tasks';
        const params = new URLSearchParams(filters);
        if (params.toString()) {
            endpoint += '?' + params.toString();
        }
        return await this.request('GET', endpoint);
    },
    
    /**
     * Buscar tarefa por ID
     */
    async getTask(id) {
        return await this.request('GET', `/tasks/${id}`);
    },
    
    /**
     * Criar nova tarefa
     */
    async createTask(data) {
        return await this.request('POST', '/tasks', data);
    },
    
    /**
     * Atualizar tarefa
     */
    async updateTask(id, data) {
        return await this.request('PUT', `/tasks/${id}`, data);
    },
    
    /**
     * Alternar status da tarefa (pending <-> done)
     */
    async toggleTask(id) {
        return await this.request('PUT', `/tasks/${id}/toggle`);
    },

    async completeTask(id) {
        return await this.request('PUT', `/tasks/${id}/complete`);
    },

    async cancelTask(id) {
        return await this.request('PUT', `/tasks/${id}/cancel`);
    },
    
    /**
     * Deletar tarefa
     */
    async deleteTask(id) {
        return await this.request('DELETE', `/tasks/${id}`);
    },
    
    /**
     * Obter tarefas atrasadas
     */
    async getOverdueTasks() {
        return await this.request('GET', '/tasks/overdue');
    },
    
    // ============================================
    // OBJETIVOS
    // ============================================
    
    /**
     * Listar todos os objetivos
     */
    async getGoals() {
        return await this.request('GET', '/goals');
    },
    
    /**
     * Listar apenas objetivos ativos
     */
    async getActiveGoals() {
        return await this.request('GET', '/goals/active');
    },
    
    /**
     * Buscar objetivo por ID
     */
    async getGoal(id) {
        return await this.request('GET', `/goals/${id}`);
    },
    
    /**
     * Criar novo objetivo
     */
    async createGoal(data) {
        return await this.request('POST', '/goals', data);
    },
    
    /**
     * Atualizar objetivo
     */
    async updateGoal(id, data) {
        return await this.request('PUT', `/goals/${id}`, data);
    },
    
    /**
     * Atualizar apenas progresso
     */
    async updateGoalProgress(id, progress) {
        return await this.request('PUT', `/goals/${id}/progress`, { progress });
    },

    async cancelGoal(id) {
        return await this.request('PUT', `/goals/${id}/cancel`);
    },
    
    /**
     * Deletar objetivo
     */
    async deleteGoal(id) {
        return await this.request('DELETE', `/goals/${id}`);
    },
    
    // ============================================
    // DECISÕES (IA)
    // ============================================
    
    /**
     * Analisar decisão com IA
     * Esta é a função PRINCIPAL do sistema!
     */
    async analyzeDecision(description) {
        return await this.request('POST', '/decisions/analyze', {
            description
        });
    },
    
    /**
     * Obter histórico de decisões
     */
    async getDecisionHistory(limit = 20) {
        return await this.request('GET', `/decisions/history?limit=${limit}`);
    },
    
    /**
     * Buscar decisão específica
     */
    async getDecision(id) {
        return await this.request('GET', `/decisions/${id}`);
    },
    
    /**
     * Deletar decisão do histórico
     */
    async deleteDecision(id) {
        return await this.request('DELETE', `/decisions/${id}`);
    },
    
    /**
     * Sugestões rápidas (tempo ou dinheiro)
     */
    async quickSuggest(type, value) {
        return await this.request('POST', '/decisions/quick-suggest', {
            type,  // 'time' ou 'money'
            value
        });
    },
    
    // ============================================
    // UTILIDADES
    // ============================================
    
    /**
     * Testa se API está acessível
     */
    async ping() {
        return await this.request('GET', '/ping');
    },
    
    /**
     * Verifica se usuário está autenticado
     */
    isAuthenticated() {
        return authToken !== null && authToken !== '';
    }
};

// Exportar para uso global
window.API = API;
