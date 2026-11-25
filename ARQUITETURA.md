# 📐 Arquitetura do Sistema - Próximo Passo

## 🏗️ Visão Geral

```
┌─────────────┐      HTTP/JSON       ┌─────────────┐      SQL        ┌──────────┐
│   FRONTEND  │ ←─────────────────→ │   BACKEND   │ ←──────────────→│  MySQL   │
│  (HTML/CSS/ │     REST API        │    (PHP)    │                 │          │
│     JS)     │                      └─────────────┘                 └──────────┘
└─────────────┘                             ↓
                                       HTTP/JSON
                                            ↓
                                    ┌──────────────┐
                                    │   Groq API   │
                                    │  (Llama 3)   │
                                    └──────────────┘
```

## 📁 Organização dos Arquivos

### Backend (API REST em PHP)

```
api/
├── config/
│   ├── database.php          → Conexão com MySQL (PDO)
│   └── config.php            → Constantes, funções auxiliares
│
├── models/                   → Representam tabelas do banco
│   ├── User.php             → CRUD de usuários
│   ├── Task.php             → CRUD de tarefas
│   ├── Goal.php             → CRUD de objetivos
│   └── Decision.php         → CRUD de decisões
│
├── controllers/              → Lógica de negócio
│   ├── AuthController.php   → Login, registro, perfil
│   ├── TaskController.php   → Gerenciar tarefas
│   ├── GoalController.php   → Gerenciar objetivos
│   └── DecisionController.php → IA e análise de decisões
│
├── services/                 → Serviços especializados
│   └── AIService.php        → Integração com Groq (IA)
│
├── middleware/
│   └── AuthMiddleware.php   → Validação de JWT
│
└── routes.php               → Roteador central (direciona requisições)
```

### Frontend (Interface)

```
public/
├── css/
│   └── styles.css           → Todos os estilos (tema claro/escuro)
│
├── js/
│   ├── api.js              → Cliente HTTP (fetch wrapper)
│   ├── app.js              → Lógica principal (futuro)
│   └── ui.js               → Funções de interface (futuro)
│
└── index.php               → Página única (SPA)
```

### Banco de Dados

```
database/
└── schema.sql              → CREATE TABLE de todas as tabelas
```

## 🔄 Fluxo de uma Requisição

### Exemplo: Analisar uma Decisão

```
1. FRONTEND
   └─→ Usuário digita: "Comprar notebook de R$ 3500"
   └─→ JavaScript chama: API.analyzeDecision(description)
   
2. api.js
   └─→ fetch('POST /api/decisions/analyze', { description: "..." })
   └─→ Header: Authorization: Bearer {token}
   
3. .htaccess
   └─→ Recebe: /api/decisions/analyze
   └─→ Redireciona para: api/routes.php
   
4. routes.php
   └─→ Identifica: POST /decisions/analyze
   └─→ Valida token via AuthMiddleware
   └─→ Extrai userId do token
   └─→ Chama: DecisionController->analyze(userId, data)
   
5. DecisionController.php
   └─→ Valida dados de entrada
   └─→ Busca contexto do usuário:
       • User::findById(userId)
       • Goal::getActiveByUserId(userId)
   └─→ Chama: AIService->analyzeDecision(description, context)
   
6. AIService.php
   └─→ Constrói prompt detalhado com:
       • Descrição da decisão
       • Renda, gastos, reserva do usuário
       • Objetivos ativos
       • Instruções para a IA
   └─→ Envia para: https://api.groq.com/openai/v1/chat/completions
   └─→ Aguarda resposta (2-5 segundos)
   └─→ Parseia JSON retornado
   
7. DecisionController.php (continua)
   └─→ Recebe análise da IA
   └─→ Salva no banco: Decision::create(...)
   └─→ Retorna JSON para frontend
   
8. api.js
   └─→ Recebe resposta
   └─→ Retorna promise resolvida
   
9. FRONTEND
   └─→ Exibe análise na tela
   └─→ Mostra: impacto financeiro, objetivos, alternativas
```

## 🔐 Fluxo de Autenticação (JWT)

```
┌──────────────────────────────────────────────────────────────┐
│                        REGISTRO                               │
└──────────────────────────────────────────────────────────────┘

Frontend                Backend                    Banco
   │                       │                          │
   │  POST /auth/register  │                          │
   ├──────────────────────→│                          │
   │  { name, email, pwd } │                          │
   │                       │  INSERT INTO users       │
   │                       ├─────────────────────────→│
   │                       │  (password_hash)         │
   │                       │                          │
   │                       │←─────────────────────────┤
   │                       │  userId = 1              │
   │                       │                          │
   │                       │  Gera JWT:               │
   │                       │  header.payload.signature│
   │                       │  (contém: userId, exp)   │
   │                       │                          │
   │←──────────────────────┤                          │
   │  { token, user }      │                          │
   │                       │                          │
   │  Salva token no       │                          │
   │  localStorage         │                          │
   │                       │                          │

┌──────────────────────────────────────────────────────────────┐
│                    REQUISIÇÃO PROTEGIDA                       │
└──────────────────────────────────────────────────────────────┘

Frontend                Backend                    Banco
   │                       │                          │
   │  GET /tasks           │                          │
   ├──────────────────────→│                          │
   │  Header:              │                          │
   │  Authorization:       │                          │
   │  Bearer eyJ0eXAi...   │                          │
   │                       │                          │
   │                       │  AuthMiddleware          │
   │                       │  valida assinatura       │
   │                       │  verifica expiração      │
   │                       │  extrai userId           │
   │                       │                          │
   │                       │  SELECT * FROM tasks     │
   │                       ├─────────────────────────→│
   │                       │  WHERE user_id = 1       │
   │                       │                          │
   │                       │←─────────────────────────┤
   │                       │  [ lista de tasks ]      │
   │←──────────────────────┤                          │
   │  { tasks: [...] }     │                          │
   │                       │                          │
```

## 🗄️ Modelo de Dados

```sql
┌──────────────┐
│    users     │
├──────────────┤
│ id (PK)      │
│ name         │
│ email        │
│ password_hash│
│ monthly_...  │
│ savings      │
│ risk_profile │
└──────────────┘
       │
       │ 1:N
       ├───────────────┬───────────────┬──────────────┐
       │               │               │              │
       ↓               ↓               ↓              ↓
┌────────────┐  ┌────────────┐  ┌────────────┐  ┌──────────┐
│   tasks    │  │   goals    │  │ decisions  │  │ ai_logs  │
├────────────┤  ├────────────┤  ├────────────┤  ├──────────┤
│ id (PK)    │  │ id (PK)    │  │ id (PK)    │  │ id (PK)  │
│ user_id(FK)│  │ user_id(FK)│  │ user_id(FK)│  │ user_id  │
│ goal_id(FK)│  │ name       │  │ description│  │ prompt   │
│ title      │  │ progress   │  │ amount     │  │ response │
│ priority   │  │ deadline   │  │ analysis   │  │ tokens   │
│ due_date   │  └────────────┘  └────────────┘  └──────────┘
│ status     │         ↑
└────────────┘         │
       │               │
       └───────────────┘
           N:1
```

## 🎨 Padrões Utilizados

### MVC (Model-View-Controller)

```
┌────────────┐
│    VIEW    │  → Frontend (HTML/CSS/JS)
│  (public/) │    Exibe dados e captura input
└────────────┘
       ↕
┌────────────┐
│ CONTROLLER │  → api/controllers/
│            │    Recebe requisições
│            │    Orquestra lógica
│            │    Retorna respostas
└────────────┘
       ↕
┌────────────┐
│   MODEL    │  → api/models/
│            │    Acessa banco de dados
│            │    Representa entidades
└────────────┘
```

### Repository Pattern (Models)

Cada Model encapsula acesso aos dados:
- `User::findById($id)` → SELECT
- `Task::create($data)` → INSERT
- `Goal::update($id, $data)` → UPDATE
- `Decision::delete($id)` → DELETE

### Service Layer

Lógica complexa separada em Services:
- `AIService` → Integração com IA externa
- `AnalysisService` → (futuro) Lógica de análise financeira

## 🔒 Segurança Implementada

```
1. SQL Injection Prevention
   └─→ PDO com Prepared Statements
   └─→ Todos os parâmetros são "bindados"

2. Password Security
   └─→ password_hash() com BCRYPT
   └─→ Nunca armazena senha em texto puro
   └─→ password_verify() para validação

3. Authentication
   └─→ JWT (JSON Web Token)
   └─→ Assinado com chave secreta
   └─→ Expira em 24 horas
   └─→ Stateless (não usa sessões)

4. Authorization
   └─→ Middleware valida token em toda rota
   └─→ Verifica propriedade dos recursos
   └─→ user_id do token vs user_id do recurso

5. Input Validation
   └─→ validateRequired() para campos obrigatórios
   └─→ htmlspecialchars() para XSS prevention
   └─→ Validação de tipos (email, date, enum)

6. HTTPS Ready
   └─→ Cookie secure flag (quando em produção)
   └─→ Headers CORS configuráveis
```

## 🚀 Tecnologias Chave

| Camada | Tecnologia | Motivo |
|--------|-----------|--------|
| **Backend** | PHP 7.4+ | Fácil deploy, bem documentado |
| **Database** | MySQL 5.7+ | Relacional, robusto, gratuito |
| **ORM** | PDO | Nativo do PHP, seguro |
| **Auth** | JWT | Stateless, escalável |
| **API** | REST | Padrão de mercado, simples |
| **Frontend** | Vanilla JS | Sem dependências, leve |
| **CSS** | Custom (Vars) | Tema automático, responsivo |
| **IA** | Groq + Llama 3 | Rápido, gratuito, inteligente |

## 📊 Performance

| Operação | Tempo Esperado |
|----------|---------------|
| Login/Register | < 100ms |
| CRUD Operations | < 50ms |
| List Queries | < 100ms |
| AI Analysis | 2-5 segundos |
| Page Load | < 500ms |

## 🔄 Ciclo de Vida de uma Decisão

```
1. CRIAÇÃO
   User digita → Frontend valida → API recebe → Controller processa
   
2. CONTEXTO
   Busca dados do usuário → Busca objetivos ativos → Monta contexto
   
3. PROMPT
   Constrói prompt detalhado → Adiciona instruções → Formata JSON
   
4. IA
   Envia para Groq → Aguarda resposta → Valida JSON retornado
   
5. PARSING
   Extrai campos → Valida estrutura → Aplica fallback se necessário
   
6. PERSISTÊNCIA
   Extrai valor monetário → Salva no banco → Registra log
   
7. RESPOSTA
   Retorna JSON → Frontend renderiza → Exibe para usuário
```

## 🎯 Próximos Passos no Desenvolvimento

1. **Frontend Completo** - Implementar todas as telas
2. **WebSockets** - Notificações em tempo real
3. **Cache** - Redis para performance
4. **Testes** - PHPUnit para backend
5. **CI/CD** - GitHub Actions
6. **Docker** - Containerização
7. **API Docs** - Swagger/OpenAPI

---

**Este documento serve como referência arquitetural do projeto.**
