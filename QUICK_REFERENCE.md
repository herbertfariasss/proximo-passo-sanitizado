# ⚡ Guia Rápido - Próximo Passo

Comandos e referências rápidas para desenvolvimento.

## 🚀 Início Rápido (5 minutos)

```bash
# 1. Iniciar XAMPP
- Abrir XAMPP Control Panel
- Start Apache
- Start MySQL

# 2. Criar banco de dados
- Acessar: http://localhost/phpmyadmin
- Criar banco: proximo_passo
- Importar: database/schema.sql

# 3. Configurar
- Copiar .env.example para .env
- Gerar JWT_SECRET localmente e preencher GROQ_API_KEY no .env
- Obter chave em: https://console.groq.com/keys

# 4. Acessar
http://localhost/proximo-passo/public/
```

## 📌 URLs Importantes

```
Frontend:    http://localhost/proximo-passo/public/
API Ping:    http://localhost/proximo-passo/api/ping
phpMyAdmin:  http://localhost/phpmyadmin
Groq Console: https://console.groq.com
```

## 🔑 Conta de Teste

Crie uma conta local pela tela de cadastro e use as credenciais escolhidas nos testes abaixo.

## 📡 Endpoints Principais

### Autenticação
```
POST /api/auth/register
POST /api/auth/login
GET  /api/auth/me
PUT  /api/auth/profile
```

### Tarefas
```
GET    /api/tasks
POST   /api/tasks
PUT    /api/tasks/:id
PUT    /api/tasks/:id/toggle
DELETE /api/tasks/:id
```

### Objetivos
```
GET    /api/goals
POST   /api/goals
PUT    /api/goals/:id
DELETE /api/goals/:id
```

### Decisões (IA)
```
POST /api/decisions/analyze        ⭐ Principal
GET  /api/decisions/history
POST /api/decisions/quick-suggest
```

## 🧪 Testar API com cURL

### Login
```bash
curl -X POST http://localhost/proximo-passo/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"usuario@example.com","password":"SUBSTITUA_PELA_SENHA_CADASTRADA"}'
```

### Analisar Decisão (substitua SEU_TOKEN)
```bash
curl -X POST http://localhost/proximo-passo/api/decisions/analyze \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{"description":"Comprar notebook de R$ 3500"}'
```

## 🛠️ Comandos MySQL Úteis

```sql
-- Ver todas as tabelas
SHOW TABLES;

-- Ver estrutura de uma tabela
DESCRIBE users;

-- Ver dados de teste
SELECT * FROM users;
SELECT * FROM tasks;
SELECT * FROM goals;

-- Limpar dados (cuidado!)
DELETE FROM decisions;
DELETE FROM tasks;
DELETE FROM goals;

-- Resetar auto_increment
ALTER TABLE decisions AUTO_INCREMENT = 1;
```

## 📝 Estrutura de Dados

### Criar Tarefa
```json
{
  "title": "Estudar PHP",
  "priority": "high",
  "due_date": "2026-10-15",
  "estimated_minutes": 120,
  "goal_id": 1
}
```

### Criar Objetivo
```json
{
  "name": "Aprender programação",
  "description": "Dominar PHP e MySQL",
  "progress": 0,
  "deadline": "2026-12-31"
}
```

### Analisar Decisão
```json
{
  "description": "Estou pensando em comprar X por R$ Y"
}
```

## 🐛 Resolver Problemas Rápido

### API não responde
```bash
# Verificar se Apache está rodando
# Verificar arquivo: C:\xampp\apache\logs\error.log
```

### Banco não conecta
```php
// Verificar em: api/config/database.php
private $username = 'root';
private $password = '';  // Geralmente vazio no XAMPP
```

### IA não funciona
Verifique se `GROQ_API_KEY` foi preenchida no arquivo `.env`.

### Token inválido
```
Fazer logout e login novamente
Token expira após 24 horas
```

## 📚 Código de Exemplo - Frontend

### Fazer Login
```javascript
async function login(email, senha) {
  try {
    return await API.login(email, senha);
  } catch (error) {
    console.error('Erro:', error.message);
  }
}
```

### Analisar Decisão
```javascript
async function analisar() {
  try {
    const response = await API.analyzeDecision(
      'Comprar um carro de R$ 50.000'
    );
    console.log('Análise:', response.analysis);
  } catch (error) {
    console.error('Erro:', error.message);
  }
}
```

### Listar Tarefas
```javascript
async function listarTarefas() {
  try {
    const response = await API.getTasks({ status: 'pending' });
    console.log('Tarefas:', response.tasks);
  } catch (error) {
    console.error('Erro:', error.message);
  }
}
```

## 🎨 CSS - Variáveis Úteis

```css
/* Cores principais */
--primary: #5B4FE8;
--ok: #1F9E6D;
--warn: #B8862E;
--danger: #C24444;

/* Usar em componentes */
.meu-botao {
  background: var(--primary);
  color: var(--primary-ink);
  border-radius: var(--radius);
}
```

## 🔐 Segurança

### Gerar Chave JWT Aleatória
```bash
php -r "echo bin2hex(random_bytes(32));"
```

Coloque a chave gerada em `JWT_SECRET` no arquivo `.env`.

### Hash de Senha (PHP)
```php
// Criar hash
$hash = password_hash('senha123', PASSWORD_BCRYPT);

// Verificar
password_verify('senha123', $hash); // true
```

## 📊 Status HTTP

```
200 OK              - Sucesso
201 Created         - Recurso criado
400 Bad Request     - Dados inválidos
401 Unauthorized    - Não autenticado
403 Forbidden       - Sem permissão
404 Not Found       - Não encontrado
500 Internal Error  - Erro no servidor
```

## 🎯 Próximas Features a Implementar

```
1. [ ] Dashboard com estatísticas
2. [ ] Gráficos de progresso
3. [ ] Filtros avançados de tarefas
4. [ ] Notificações de prazo
5. [ ] Exportar relatórios (PDF)
6. [ ] Modo offline (PWA)
7. [ ] Dark mode manual
8. [ ] Anexos nas decisões
```

## 📞 Links Úteis

```
Documentação PHP:      https://www.php.net/manual/pt_BR/
MySQL Docs:            https://dev.mysql.com/doc/
MDN Web Docs:          https://developer.mozilla.org/pt-BR/
Groq Docs:             https://console.groq.com/docs
JWT Debugger:          https://jwt.io/
JSON Validator:        https://jsonlint.com/
Regex Tester:          https://regex101.com/
```

## 💡 Dicas de Desenvolvimento

### Debug no Backend
```php
// Em qualquer lugar do código
error_log("Debug: " . print_r($variavel, true));

// Ver log em: C:\xampp\apache\logs\error.log
```

### Debug no Frontend
```javascript
// No console do navegador (F12)
console.log('Debug:', variavel);
console.table(arrayDeObjetos);
debugger; // Pausa execução
```

### Limpar Cache do Navegador
```
Ctrl + Shift + Delete (Chrome/Edge)
Ou
Ctrl + F5 (hard refresh)
```

## ⚙️ Configurações Importantes

### php.ini (XAMPP)
```ini
; Aumentar limite de upload (se precisar)
upload_max_filesize = 20M
post_max_size = 20M

; Exibir erros (desenvolvimento)
display_errors = On
error_reporting = E_ALL

; Timezone
date.timezone = America/Sao_Paulo
```

### .htaccess
```apache
# Já está configurado, mas se precisar debugar:
RewriteEngine On
RewriteBase /proximo-passo/

# Ver se mod_rewrite está ativo:
# httpd.conf → LoadModule rewrite_module
```

## 🎓 Para Estudar Mais

```
1. REST API Design
2. JWT Authentication
3. SQL Injection Prevention
4. XSS Prevention
5. CORS
6. HTTP Methods (GET, POST, PUT, DELETE)
7. Status Codes
8. Prompt Engineering para IA
9. MVC Pattern
10. Repository Pattern
```

## 🏆 Checklist do Projeto

```
Backend:
✅ Banco de dados estruturado
✅ API REST funcional
✅ Autenticação JWT
✅ CRUD completo
✅ Integração com IA
✅ Segurança (SQL Injection, XSS)

Frontend:
✅ Interface responsiva
✅ Tema claro/escuro
✅ Login/Registro
✅ Comunicação com API
⏳ Implementação completa das telas

Documentação:
✅ README.md
✅ TESTES.md
✅ ARQUITETURA.md
✅ Comentários no código
```

---

**Salve este arquivo como referência rápida durante o desenvolvimento!** 📌
