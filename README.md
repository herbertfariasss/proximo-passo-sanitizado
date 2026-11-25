# 🎯 Próximo Passo

Sistema web de tomada de decisões integrado com IA para auxiliar no planejamento financeiro e pessoal.

## 📋 Sobre o Projeto

O **Próximo Passo** é um sistema que ajuda usuários a tomar decisões mais conscientes através de análise inteligente usando IA. O sistema:

- 📊 Gerencia tarefas e objetivos pessoais
- 💰 Analisa decisões financeiras com contexto personalizado
- 🤖 Usa IA via Groq para análises neutras e objetivas
- ⚙️ Permite escolher respostas curtas, normais ou técnicas nas configurações da IA
- 📱 Interface responsiva (funciona em mobile e desktop)
- 🎨 Tema claro/escuro automático

## 🛠️ Tecnologias Utilizadas

### Backend
- **PHP 7.4+** - Linguagem principal
- **MySQL 5.7+** - Banco de dados
- **PDO** - Acesso seguro ao banco (previne SQL Injection)
- **JWT** - Autenticação stateless

### Frontend
- **HTML5** - Estrutura
- **CSS3** - Estilização (variáveis CSS, responsive)
- **JavaScript (Vanilla)** - Lógica do cliente

### IA
- **Groq API** - Inferência rápida
- **Llama 3.1 70B** - Modelo de linguagem (gratuito)

## 📁 Estrutura do Projeto

```
proximo-passo/
├── api/                        # Backend (API REST)
│   ├── config/                 # Configurações
│   │   ├── database.php        # Conexão com MySQL
│   │   └── config.php          # Configurações gerais
│   ├── models/                 # Modelos (representam tabelas)
│   │   ├── User.php
│   │   ├── Task.php
│   │   ├── Goal.php
│   │   └── Decision.php
│   ├── controllers/            # Controllers (lógica de negócio)
│   │   ├── AuthController.php
│   │   ├── TaskController.php
│   │   ├── GoalController.php
│   │   └── DecisionController.php
│   ├── services/               # Serviços especializados
│   │   └── AIService.php       # Integração com IA
│   ├── middleware/             # Middlewares
│   │   └── AuthMiddleware.php  # Validação de JWT
│   └── routes.php              # Roteador da API
├── public/                     # Frontend (arquivos públicos)
│   ├── css/
│   │   └── styles.css
│   ├── js/
│   │   ├── api.js              # Comunicação com backend
│   │   ├── app.js              # Lógica principal
│   │   └── ui.js               # Funções de interface
│   └── index.php               # Página inicial
├── database/
│   └── schema.sql              # Estrutura do banco
├── .htaccess                   # Configuração Apache
├── .env.example                # Template de configuração
└── README.md                   # Este arquivo
```

## 🚀 Como Instalar e Rodar

### Pré-requisitos

1. **XAMPP** ou **WAMP** (inclui Apache, PHP e MySQL)
   - Download XAMPP: https://www.apachefriends.org/
   
2. **Conta no Groq** (gratuita)
   - Criar conta: https://console.groq.com
   - Obter API Key: https://console.groq.com/keys

### Passo 1: Clonar/Baixar o Projeto

```bash
# Execute este comando dentro da pasta htdocs do XAMPP
git clone https://github.com/herbertfariasss/proximo-passo-sanitizado.git proximo-passo
```

### Passo 2: Criar o Banco de Dados

1. Abra o **phpMyAdmin**: http://localhost/phpmyadmin
2. Clique em "Novo" para criar um banco de dados
3. Nome: `proximo_passo`
4. Cotejamento: `utf8mb4_unicode_ci`
5. Vá na aba "SQL" e cole o conteúdo do arquivo `database/schema.sql`
6. Clique em "Executar"

### Passo 3: Configurar o Projeto

1. Copie o arquivo `.env.example` e renomeie a cópia para `.env` (na raiz do projeto)
2. Abra o `.env` e preencha (os segredos ficam SOMENTE aqui, nunca no código):
   ```env
   # Segredo do login (JWT), mínimo de 32 caracteres. Gere um com:
   #   php -r "echo bin2hex(random_bytes(32));"
   JWT_SECRET=...

   # Sua chave do Groq (obtenha em https://console.groq.com/keys)
   GROQ_API_KEY=...
   ```
3. Se o seu MySQL tiver senha ou usuário diferente, ajuste `DB_USER` e `DB_PASS` no mesmo `.env`
   (o padrão do XAMPP é `root` sem senha).
4. `APP_ENV=development` no seu computador. Em um servidor real use `APP_ENV=production`:
   o debug fica desligado e os erros não aparecem na tela.
5. `AI_RATE_LIMIT_PER_MINUTE` e `AI_RATE_LIMIT_PER_HOUR` limitam quantas análises cada
   usuário pode pedir (protege a cota da API de IA).

> Sem `JWT_SECRET` válido a API responde "Servidor mal configurado". Sem `GROQ_API_KEY`
> o login funciona, mas as análises da IA falham (o motivo aparece no log).

### Passo 4: Iniciar o Servidor

1. Abra o **XAMPP Control Panel**
2. Inicie o **Apache**
3. Inicie o **MySQL**
4. Acesse: http://localhost/proximo-passo/public/

### Passo 5: Testar a API

Acesse: http://localhost/proximo-passo/api/ping

Deve retornar:
```json
{
  "success": true,
  "message": "API está funcionando!",
  "timestamp": "2026-10-07 20:00:00"
}
```

## 🧪 Testando com Dados de Exemplo

O arquivo `database/schema.sql` cria as tabelas do projeto. Na tela inicial, use **Criar conta** para cadastrar um usuário local. Entre com as credenciais que cadastrou e crie objetivos e tarefas de teste pela interface.

## 📚 Documentação da API

### Autenticação

```
POST /api/auth/register
Body: { "name": "...", "email": "...", "password": "..." }

POST /api/auth/login
Body: { "email": "...", "password": "..." }
Retorna: { "token": "...", "user": {...} }

GET /api/auth/me
Header: Authorization: Bearer {token}
```

### Tarefas

```
GET    /api/tasks                    # Listar todas
POST   /api/tasks                    # Criar nova
GET    /api/tasks/:id                # Buscar específica
PUT    /api/tasks/:id                # Atualizar
PUT    /api/tasks/:id/toggle         # Alternar status
DELETE /api/tasks/:id                # Deletar
```

### Objetivos

```
GET    /api/goals                    # Listar todos
POST   /api/goals                    # Criar novo
GET    /api/goals/:id                # Buscar específico
PUT    /api/goals/:id                # Atualizar
PUT    /api/goals/:id/progress       # Atualizar progresso
DELETE /api/goals/:id                # Deletar
```

### Decisões (IA)

```
POST   /api/decisions/analyze        # Analisar decisão
Body: { "description": "Quero comprar um celular de R$ 2000" }

GET    /api/decisions/history        # Histórico de decisões

POST   /api/decisions/quick-suggest  # Sugestões rápidas
Body: { "type": "time", "value": 60 }
```

## 🔧 Resolução de Problemas

### Erro: "Rota não encontrada"
- Certifique-se que o módulo `mod_rewrite` está ativado no Apache
- Verifique se o arquivo `.htaccess` está na raiz do projeto

### Erro: "Erro de conexão com o banco"
- Verifique se o MySQL está rodando
- Confirme usuário e senha em `api/config/database.php`
- Certifique-se que o banco `proximo_passo` foi criado

### Erro: "Token inválido"
- O token JWT expira após 24 horas
- Faça login novamente para obter um novo token

### IA não responde / Erro 401 na API Groq
- Verifique se sua chave do Groq (`GROQ_API_KEY`) está correta no arquivo `.env`
- Confirme que sua conta Groq está ativa
- Verifique se não excedeu o limite gratuito (14.400 requests/dia)

### Erro: "JSON inválido"
- Certifique-se de enviar `Content-Type: application/json` no header
- Valide o JSON antes de enviar (use jsonlint.com)

## 📖 Conceitos para Estudar

Este projeto usa conceitos importantes de desenvolvimento web:

1. **REST API** - Arquitetura de APIs
2. **MVC Pattern** - Model-View-Controller
3. **JWT** - JSON Web Tokens para autenticação
4. **PDO** - PHP Data Objects (acesso seguro ao banco)
5. **CORS** - Cross-Origin Resource Sharing
6. **Prepared Statements** - Prevenção de SQL Injection
7. **Password Hashing** - Segurança de senhas (bcrypt)
8. **Prompt Engineering** - Construção de prompts para IA

## 📝 Próximos Passos / Melhorias

- [ ] Implementar refresh tokens para segurança adicional
- [ ] Adicionar paginação nas listagens
- [ ] Criar dashboard com gráficos
- [ ] Adicionar anexos/arquivos nas decisões
- [ ] Implementar notificações de prazo
- [ ] Adicionar testes automatizados
- [ ] Criar versão PWA (Progressive Web App)

## 👨‍💻 Autor

Projeto desenvolvido para fins acadêmicos.

## Painel administrativo

Defina `ADMIN_EMAILS` no `.env` com o e-mail de uma conta **já cadastrada** (ou vários e-mails separados por vírgula). Essa conta entra pela mesma tela de login e abre o painel automaticamente. O acesso à API administrativa também é validado no servidor.

O painel exibe contas cadastradas, data de cadastro, presença recente e totais de objetivos, tarefas e decisões. “Online” significa que houve uma requisição autenticada nos últimos cinco minutos; é uma estimativa de atividade, não uma confirmação de que a aba está aberta. A presença é guardada em arquivos temporários do servidor e reinicia quando esses arquivos são apagados. Nenhuma migração ou mudança no esquema do banco é necessária.

O custo estimado da IA por usuário começa a ser contabilizado após esta versão. Cada resposta bem-sucedida da Groq com dados de uso é registrada em arquivos temporários do servidor. Para `openai/gpt-oss-120b`, o cálculo usa as tarifas por milhão de tokens: US$ 0,15 de entrada, US$ 0,075 de entrada em cache e US$ 0,60 de saída. Outros modelos aparecem nas chamadas, mas sem custo calculado. O valor é indicativo, em USD, não inclui histórico anterior nem substitui a fatura da Groq. Apagar os arquivos temporários reinicia os totais.

O painel permite filtrar os indicadores de período por hoje, últimos sete dias, este mês, mês passado ou datas personalizadas. Os totais de contas e a presença online/desconectada continuam mostrando a situação atual; os demais cartões e o custo por usuário usam o período selecionado.

Os gráficos do painel usam React e Recharts no padrão de composição do shadcn/ui. Para reconstruir o módulo após editar `charts/src/main.jsx`, execute `npm ci` e `npm run build` na pasta `charts`. O gráfico de atividade e o de custo seguem o filtro de datas; sem filtro, mostram os últimos 30 dias. Para intervalos longos, o gráfico mostra no máximo os últimos 365 dias, enquanto os cartões mantêm os totais completos do período.

Atividade por usuário considera tarefas, objetivos e decisões criados. “Ativos hoje” e “Ativos neste mês” contam usuários distintos com pelo menos uma dessas ações. A taxa de conclusão mostra a situação atual das tarefas criadas no período selecionado; o progresso médio usa os objetivos criados nesse período. Retenção em 7 dias é a proporção de contas com pelo menos 7 dias que tiveram uma nova atividade entre o 1º e o 7º dia após o cadastro. Sem contas elegíveis, o indicador mostra “—”. O custo médio por chamada considera apenas chamadas com preço calculado. Não são criadas novas tabelas nem campos.

## 📄 Licença

Este projeto é open source e está disponível para uso educacional.

## 🆘 Suporte

Se tiver dúvidas ou problemas:

1. Verifique a seção "Resolução de Problemas" acima
2. Revise os comentários nos arquivos (estão bem documentados)
3. Teste a API isoladamente usando Postman ou Insomnia
4. Verifique os logs do Apache (em `xampp/apache/logs/error.log`)

---

**Dica:** Use o navegador com DevTools (F12) aberto para ver requisições e erros no console.
