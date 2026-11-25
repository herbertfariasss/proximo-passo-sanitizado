# 🔄 Tradução dos Campos - Próximo Passo

## ✅ Banco de Dados ATUALIZADO para Português

O banco de dados agora está **100% em português**:

### Tabelas e Campos

```
usuarios (anteriormente users)
├── id
├── nome (era: name)
├── email
├── senha_hash (era: password_hash)
├── renda_mensal (era: monthly_income)
├── gastos_mensais (era: monthly_expenses)
├── reserva (era: savings)
├── horas_livres_semana (era: weekly_free_hours)
├── perfil_risco (era: risk_profile)
├── tema (era: theme)
├── criado_em (era: created_at)
└── atualizado_em (era: updated_at)

objetivos (anteriormente goals)
├── id
├── usuario_id (era: user_id)
├── nome (era: name)
├── descricao (era: description)
├── progresso (era: progress)
├── prazo (era: deadline)
├── criado_em
└── atualizado_em

tarefas (anteriormente tasks)
├── id
├── usuario_id
├── objetivo_id (era: goal_id)
├── titulo (era: title)
├── prioridade (era: priority) → 'alta', 'media', 'baixa'
├── data_vencimento (era: due_date)
├── tempo_estimado (era: estimated_minutes)
├── status → 'pendente', 'concluida'
├── criado_em
└── atualizado_em

decisoes (anteriormente decisions)
├── id
├── usuario_id
├── descricao (era: description)
├── valor (era: amount)
├── resultado_analise (era: analysis_result)
└── criado_em

logs_ia (anteriormente ai_logs)
├── id
├── usuario_id
├── tipo_requisicao
├── prompt
├── resposta
├── provedor
├── tokens_usados
└── criado_em
```

## 🚀 COMO INSTALAR AGORA

### 1. Importar o Banco
```sql
1. Acesse http://localhost/phpmyadmin
2. Clique em "SQL" (aba superior)
3. Copie TODO o conteúdo de: database/schema.sql
4. Cole na área de texto
5. Clique em "Executar"
```

### 2. Verificar se Funcionou
```sql
USE proximo_passo;
SHOW TABLES;
-- Deve mostrar: usuarios, objetivos, tarefas, decisoes, logs_ia

SELECT * FROM usuarios;
-- Em um banco recém-criado, a tabela está vazia.
```

## ⚠️ IMPORTANTE: Mapeamento API

A **API ainda recebe dados em INGLÊS** (para manter compatibilidade com frontend), mas **salva em PORTUGUÊS** no banco.

### Exemplo: Criar Tarefa

**Você envia (API em inglês):**
```json
{
  "title": "Estudar PHP",
  "priority": "high",
  "due_date": "2026-10-20",
  "estimated_minutes": 120
}
```

**Backend salva no banco (português):**
```sql
INSERT INTO tarefas (titulo, prioridade, data_vencimento, tempo_estimado)
VALUES ('Estudar PHP', 'alta', '2026-10-20', 120)
```

**Você recebe de volta (API em inglês):**
```json
{
  "id": 1,
  "title": "Estudar PHP",
  "priority": "high",
  "due_date": "2026-10-20",
  "estimated_minutes": 120,
  "status": "pending"
}
```

## 📝 Mapeamento de Valores

### Prioridades
| API (inglês) | Banco (português) |
|--------------|-------------------|
| high | alta |
| med | media |
| low | baixa |

### Status
| API (inglês) | Banco (português) |
|--------------|-------------------|
| pending | pendente |
| done | concluida |

### Perfil de Risco
| Banco (português) |
|-------------------|
| conservador |
| moderado |
| arrojado |

## ✅ Models Atualizados

Todos os Models foram atualizados para usar os nomes em português:
- ✅ User.php → usa tabela 'usuarios'
- ✅ Task.php → usa tabela 'tarefas'
- ✅ Goal.php → usa tabela 'objetivos'
- ✅ Decision.php → usa tabela 'decisoes'

## 🧪 Testar Agora

Após importar o banco:

```bash
1. Acesse: http://localhost/proximo-passo/api/ping
   ✅ Deve retornar: {"success": true, ...}

2. Cadastre uma conta local no frontend e teste o login com suas credenciais:
   POST http://localhost/proximo-passo/api/auth/login
   Body: {"email": "usuario@example.com", "password": "SUBSTITUA_PELA_SENHA_CADASTRADA"}
   ✅ Deve retornar token

3. Acesse frontend: http://localhost/proximo-passo/public/
   ✅ Deve carregar tela de login
```

## 🐛 Se Der Erro "Table doesn't exist"

```sql
-- No phpMyAdmin, execute:
DROP DATABASE IF EXISTS proximo_passo;

-- Depois importe o schema.sql novamente
```

---

**Agora pode importar o banco de dados normalmente!** 🎉
