# 🧪 Guia de Testes - Próximo Passo

Este guia ajuda você a testar o sistema passo a passo.

## 📋 Checklist de Instalação

Antes de começar os testes, certifique-se de que:

- [ ] XAMPP está instalado e rodando (Apache + MySQL)
- [ ] Banco de dados `proximo_passo` foi criado
- [ ] Script `schema.sql` foi executado com sucesso
- [ ] `JWT_SECRET` e `GROQ_API_KEY` foram configurados em `.env`
- [ ] Projeto está em `C:\xampp\htdocs\proximo-passo`

## 🔍 Teste 1: Verificar Banco de Dados

1. Acesse phpMyAdmin: http://localhost/phpmyadmin
2. Selecione o banco `proximo_passo`
3. Verifique se as tabelas foram criadas:
   - `usuarios`
   - `tarefas`
   - `objetivos`
   - `decisoes`
   - `logs_ia`
4. Em uma instalação nova, confira que a tabela `usuarios` está vazia.

## 🔍 Teste 2: Testar API (Backend)

### 2.1. Teste de Ping

Acesse no navegador:
```
http://localhost/proximo-passo/api/ping
```

**✅ Esperado:**
```json
{
  "success": true,
  "message": "API está funcionando!",
  "timestamp": "2026-10-07 20:30:00",
  "authenticated": false
}
```

### 2.2. Teste de Login (via Browser ou Postman)

Crie uma conta local na tela de cadastro e substitua os valores de exemplo abaixo.

**Método:** POST  
**URL:** http://localhost/proximo-passo/api/auth/login  
**Headers:** Content-Type: application/json  
**Body:**
```json
{
  "email": "usuario@example.com",
  "password": "SUBSTITUA_PELA_SENHA_CADASTRADA"
}
```

**✅ Esperado:**
```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "token": "<token retornado pela API>",
  "user": {
    "id": 1,
    "name": "Usuário de Teste",
    "email": "usuario@example.com",
    ...
  }
}
```

**💡 Dica:** Copie o token retornado, você vai usá-lo nos próximos testes!

### 2.3. Teste de Rota Protegida

**Método:** GET  
**URL:** http://localhost/proximo-passo/api/tasks  
**Headers:**
- Content-Type: application/json
- Authorization: Bearer {SEU_TOKEN_AQUI}

**✅ Esperado:**
```json
{
  "success": true,
  "stats": {
    "total": 5,
    "pending": 4,
    "estimated_time": 120
  },
  "tasks": [...]
}
```

## 🔍 Teste 3: Testar Frontend

### 3.1. Acessar Interface

Acesse: http://localhost/proximo-passo/public/

**✅ Esperado:**
- Tela de login/registro aparece
- Campos já vêm preenchidos com dados de teste
- Design responsivo (abra DevTools e teste em diferentes tamanhos)

### 3.2. Fazer Login

1. Clique em "Entrar"
2. Email e senha já devem estar preenchidos
3. Clique em "Entrar"

**✅ Esperado:**
- Notificação "Login realizado com sucesso!"
- Interface principal carrega
- Sidebar aparece (desktop) ou bottom nav (mobile)

### 3.3. Navegar pelas Telas

Clique em cada item do menu:
- ◆ Painel
- ✓ Tarefas
- ◎ Objetivos
- ⚡ Decisões
- ⚙ Configurações

**✅ Esperado:**
- Cada tela carrega
- URL não muda (SPA)
- Transição suave

## 🔍 Teste 4: Testar Integração com IA (MAIS IMPORTANTE!)

### 4.1. Verificar Chave do Groq

Confira se `GROQ_API_KEY` está preenchida no arquivo `.env`. Se precisar de uma chave:
1. Acesse: https://console.groq.com
2. Faça login (crie conta se necessário - é gratuito)
3. Vá em "API Keys"
4. Crie uma nova chave
5. Preencha `GROQ_API_KEY` no arquivo `.env`

### 4.2. Testar Análise de Decisão

**Usando Postman/Insomnia:**

**Método:** POST  
**URL:** http://localhost/proximo-passo/api/decisions/analyze  
**Headers:**
- Content-Type: application/json
- Authorization: Bearer {SEU_TOKEN}

**Body:**
```json
{
  "description": "Estou pensando em comprar um notebook de R$ 3.500 para estudos"
}
```

**✅ Esperado:**
- Tempo de resposta: 2-5 segundos
- Retorna JSON com análise completa:
```json
{
  "success": true,
  "decision_id": 1,
  "analysis": {
    "summary": "...",
    "financial": "...",
    "goals": "...",
    "planning": "...",
    "consequences": [...],
    "alternatives": [...]
  }
}
```

**❌ Possíveis Erros:**

1. **"Erro ao processar análise com IA"**
   - Verifique se a chave do Groq está correta
   - Teste a chave em: https://console.groq.com/playground

2. **"API rate limit exceeded"**
   - Aguarde alguns minutos
   - Limite gratuito: 14.400 requests/dia

3. **"Token inválido"**
   - Faça login novamente para obter novo token

### 4.3. Testar Histórico

**Método:** GET  
**URL:** http://localhost/proximo-passo/api/decisions/history  
**Headers:**
- Authorization: Bearer {SEU_TOKEN}

**✅ Esperado:**
- Lista com a decisão que você acabou de analisar

## 🔍 Teste 5: CRUD Completo (Tarefas)

### 5.1. Criar Tarefa

**POST** http://localhost/proximo-passo/api/tasks
```json
{
  "title": "Estudar PHP",
  "priority": "high",
  "due_date": "2026-10-15",
  "estimated_minutes": 120
}
```

**✅ Esperado:** Tarefa criada com ID

### 5.2. Listar Tarefas

**GET** http://localhost/proximo-passo/api/tasks

**✅ Esperado:** Nova tarefa aparece na lista

### 5.3. Atualizar Tarefa

**PUT** http://localhost/proximo-passo/api/tasks/6 (use o ID retornado)
```json
{
  "title": "Estudar PHP e MySQL",
  "priority": "high",
  "due_date": "2026-10-15",
  "estimated_minutes": 180,
  "status": "pending"
}
```

**✅ Esperado:** Tarefa atualizada

### 5.4. Toggle Status

**PUT** http://localhost/proximo-passo/api/tasks/6/toggle

**✅ Esperado:** Status muda de "pending" para "done"

### 5.5. Deletar Tarefa

**DELETE** http://localhost/proximo-passo/api/tasks/6

**✅ Esperado:** Tarefa removida

## 🔍 Teste 6: Testar Segurança

### 6.1. Tentar Acessar Rota Sem Token

**GET** http://localhost/proximo-passo/api/tasks  
**(SEM Header de Authorization)**

**✅ Esperado:**
```json
{
  "error": true,
  "message": "Token não fornecido"
}
```
**Status:** 401 Unauthorized

### 6.2. Tentar Acessar Recurso de Outro Usuário

1. Crie outro usuário
2. Faça login e pegue o token
3. Tente acessar uma tarefa do primeiro usuário

**✅ Esperado:**
```json
{
  "error": true,
  "message": "Você não tem permissão para acessar esta tarefa"
}
```
**Status:** 403 Forbidden

### 6.3. SQL Injection Test (Segurança)

**POST** http://localhost/proximo-passo/api/auth/login
```json
{
  "email": "' OR '1'='1",
  "password": "anything"
}
```

**✅ Esperado:**
```json
{
  "error": true,
  "message": "Email ou senha incorretos"
}
```

**❌ Se retornar usuário:** PROBLEMA! O PDO deve estar mal configurado.

## 🔍 Teste 7: Performance da IA

### 7.1. Medir Tempo de Resposta

Teste 5 vezes e anote os tempos:

1. "Comprar um carro de R$ 50.000"
2. "Fazer um curso de inglês de R$ 800"
3. "Viajar para a Europa gastando R$ 10.000"
4. "Investir R$ 5.000 em ações"
5. "Trocar de apartamento pagando R$ 500 a mais no aluguel"

**✅ Esperado:**
- Tempo médio: 2-5 segundos
- Todas retornam JSON válido
- Análise coerente com o contexto financeiro

### 7.2. Testar Contexto do Usuário

A IA deve considerar:
- Renda mensal do usuário
- Gastos mensais
- Reserva disponível
- Objetivos ativos

**Teste:** Compare duas análises:
1. Usuário com R$ 10.000 de reserva
2. Mesmo usuário após editar reserva para R$ 1.000

**✅ Esperado:** Análises diferentes considerando o contexto

## 📊 Checklist Final

Marque conforme concluir:

### Backend
- [ ] Banco criado e populado
- [ ] API ping funciona
- [ ] Login retorna token
- [ ] Rotas protegidas exigem token
- [ ] CRUD de tasks funciona
- [ ] CRUD de goals funciona
- [ ] IA analisa decisões
- [ ] Histórico salva corretamente

### Frontend
- [ ] Tela de login carrega
- [ ] Login funciona
- [ ] Navegação funciona
- [ ] Responsivo (mobile e desktop)
- [ ] Toast aparece
- [ ] Logout funciona

### Segurança
- [ ] Senhas são hasheadas (bcrypt)
- [ ] JWT expira após 24h
- [ ] Rotas protegidas bloqueiam acesso sem token
- [ ] Não é possível acessar recursos de outros usuários
- [ ] SQL Injection não funciona

### IA
- [ ] Chave do Groq configurada
- [ ] Análise retorna JSON válido
- [ ] Considera contexto do usuário
- [ ] Tempo de resposta < 10s
- [ ] Salva no histórico

## 🐛 Problemas Comuns

### "headers already sent"
- **Causa:** Output antes do PHP
- **Solução:** Remova qualquer echo ou espaço antes de `<?php`

### "Call to undefined function"
- **Causa:** Arquivo não incluído
- **Solução:** Verifique `require_once` nos controllers

### "JSON inválido"
- **Causa:** IA retornou texto em vez de JSON
- **Solução:** Ajuste o prompt em `AIService.php`

### IA não responde
- **Causa:** Chave inválida ou limite excedido
- **Solução:** Verifique no console do Groq

## 📞 Próximos Passos

Após todos os testes passarem:

1. **Implementar frontend completo** - Conectar interface com a API
2. **Adicionar mais features** - Gráficos, notificações, etc
3. **Deploy** - Colocar em produção
4. **Documentação** - Criar documentação da API

---

**Sucesso nos testes!** 🎉
