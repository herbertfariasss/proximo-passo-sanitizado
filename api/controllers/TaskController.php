<?php
/**
 * ============================================
 * Controller: Task (Tarefas)
 * ============================================
 * 
 * Este controller gerencia:
 * - CRUD completo de tarefas
 * - Filtros e listagens
 * - Toggle de status
 */

require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Goal.php';

class TaskController {
    private $db;
    private $taskModel;
    private $goalModel;
    
    public function __construct($db) {
        $this->db = $db;
        $this->taskModel = new Task($db);
        $this->goalModel = new Goal($db);
    }
    
    /**
     * GET /api/tasks
     * Lista todas as tarefas do usuário
     * 
     * Query params opcionais:
     * - status: pending, done, all (padrão: all)
     * - priority: high, med, low
     * - goal_id: filtrar por objetivo
     */
    public function getAll($userId) {
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        // Buscar tarefas
        if ($status && $status !== 'all') {
            $tasks = $this->taskModel->getByUserId($userId, $status);
        } else {
            $tasks = $this->taskModel->getByUserId($userId);
        }
        
        // Aplicar filtros adicionais
        if (isset($_GET['priority'])) {
            $tasks = array_filter($tasks, function($task) {
                return $task['priority'] === $_GET['priority'];
            });
        }
        
        if (isset($_GET['goal_id'])) {
            $goalId = intval($_GET['goal_id']);
            $tasks = array_filter($tasks, function($task) use ($goalId) {
                return $task['goal_id'] == $goalId;
            });
        }
        
        // Estatísticas
        $stats = [
            'total' => count($tasks),
            'pending' => $this->taskModel->countPending($userId),
            'estimated_time' => $this->taskModel->getTotalEstimatedTime($userId)
        ];
        
        jsonResponse([
            'success' => true,
            'stats' => $stats,
            'tasks' => array_values($tasks),
            'upcoming_tasks' => $this->taskModel->getUpcomingMonthlyTasks($userId)
        ], 200);
    }
    
    /**
     * GET /api/tasks/:id
     * Retorna uma tarefa específica
     */
    public function getById($userId, $taskId) {
        $task = $this->taskModel->findById($taskId);
        
        if (!$task) {
            errorResponse('Tarefa não encontrada', 404);
        }
        
        // Verificar se a tarefa pertence ao usuário
        if ($task['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para acessar esta tarefa', 403);
        }
        
        jsonResponse([
            'success' => true,
            'task' => $task
        ], 200);
    }
    
    /**
     * POST /api/tasks
     * Cria nova tarefa
     * 
     * Body esperado:
     * {
     *   "title": "Revisar orçamento",
     *   "priority": "high",
     *   "due_date": "2026-10-20",
     *   "estimated_minutes": 30,
     *   "goal_id": 1  // opcional
     * }
     */
    public function create($userId, $data) {
        // Validar campos obrigatórios
        validateRequired($data, ['title', 'priority', 'due_date']);
        
        // Mapear prioridades (API recebe em inglês, banco em português)
        $priorityMap = ['high' => 'alta', 'med' => 'media', 'low' => 'baixa'];
        $priority = isset($priorityMap[$data['priority']]) ? $priorityMap[$data['priority']] : 'media';
        
        // Validar data
        if (!$this->isValidDate($data['due_date'])) {
            errorResponse('Data inválida. Use formato: YYYY-MM-DD', 400);
        }
        
        // Validar objetivo (se fornecido)
        if (isset($data['goal_id']) && !empty($data['goal_id'])) {
            $goal = $this->goalModel->findById($data['goal_id']);
            if (!$goal || $goal['usuario_id'] != $userId) {
                errorResponse('Objetivo inválido', 400);
            }
        }
        
        // Preparar dados (mapeando para o banco)
        $taskData = [
            'user_id' => $userId,
            'title' => $data['title'],
            'priority' => $priority,
            'due_date' => $data['due_date'],
            'estimated_minutes' => isset($data['estimated_minutes']) ? intval($data['estimated_minutes']) : 30,
            'goal_id' => isset($data['goal_id']) ? $data['goal_id'] : null,
            'status' => 'pendente'
        ];
        
        // Criar tarefa
        $taskId = $this->taskModel->create($taskData);
        
        if (!$taskId) {
            errorResponse('Erro ao criar tarefa', 500);
        }
        
        // Buscar tarefa criada
        $task = $this->taskModel->findById($taskId);
        
        // Mapear de volta para inglês na resposta
        $task['priority'] = array_search($task['prioridade'], $priorityMap);
        $task['status'] = $task['status'] === 'pendente' ? 'pending' : 'done';
        
        jsonResponse([
            'success' => true,
            'message' => 'Tarefa criada com sucesso',
            'task' => $task
        ], 201);
    }
    
    /**
     * PUT /api/tasks/:id
     * Atualiza uma tarefa
     */
    public function update($userId, $taskId, $data) {
        $task = $this->taskModel->findById($taskId);
        
        if (!$task) {
            errorResponse('Tarefa não encontrada', 404);
        }
        
        // Verificar permissão
        if ($task['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para editar esta tarefa', 403);
        }
        
        // Validar campos se fornecidos
        if (isset($data['priority'])) {
            $validPriorities = ['high', 'med', 'low'];
            if (!in_array($data['priority'], $validPriorities)) {
                errorResponse('Prioridade inválida', 400);
            }
        }
        
        if (isset($data['due_date']) && !$this->isValidDate($data['due_date'])) {
            errorResponse('Data inválida', 400);
        }
        
        if (isset($data['status'])) {
            $validStatus = ['pending', 'done'];
            if (!in_array($data['status'], $validStatus)) {
                errorResponse('Status inválido', 400);
            }
        }
        
        // Preparar dados para atualização (manter valores atuais se não fornecidos)
        $updateData = [
            'title' => $data['title'] ?? $task['title'],
            'priority' => $data['priority'] ?? $task['priority'],
            'due_date' => $data['due_date'] ?? $task['due_date'],
            'estimated_minutes' => isset($data['estimated_minutes']) ? intval($data['estimated_minutes']) : $task['estimated_minutes'],
            'goal_id' => isset($data['goal_id']) ? $data['goal_id'] : $task['goal_id'],
            'status' => $data['status'] ?? $task['status']
        ];
        
        // Atualizar
        $success = $this->taskModel->update($taskId, $updateData);
        
        if (!$success) {
            errorResponse('Erro ao atualizar tarefa', 500);
        }
        $this->goalModel->syncProgressFromTasks($userId, $task['objetivo_id']);
        if ($updateData['goal_id'] != $task['objetivo_id']) $this->goalModel->syncProgressFromTasks($userId, $updateData['goal_id']);
        
        // Buscar tarefa atualizada
        $task = $this->taskModel->findById($taskId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Tarefa atualizada com sucesso',
            'task' => $task
        ], 200);
    }
    
    /**
     * PUT /api/tasks/:id/toggle
     * Alterna status da tarefa (pending <-> done)
     */
    public function toggleStatus($userId, $taskId) {
        $task = $this->taskModel->findById($taskId);
        
        if (!$task) {
            errorResponse('Tarefa não encontrada', 404);
        }
        
        // Verificar permissão
        if ($task['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para alterar esta tarefa', 403);
        }
        
        // Alternar status
        $success = $this->taskModel->toggleStatus($taskId);
        
        if (!$success) {
            errorResponse('Erro ao alterar status', 500);
        }
        $this->goalModel->syncProgressFromTasks($userId, $task['objetivo_id']);
        
        // Buscar tarefa atualizada
        $task = $this->taskModel->findById($taskId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Status alterado com sucesso',
            'task' => $task
        ], 200);
    }
    
    /**
     * DELETE /api/tasks/:id
     * Deleta uma tarefa
     */
    public function complete($userId, $taskId) {
        $task = $this->taskModel->findById($taskId);
        if (!$task || (int) $task['usuario_id'] !== (int) $userId) errorResponse('Tarefa não encontrada', 404);
        if (!$this->taskModel->complete($taskId, $userId)) errorResponse('Tarefa cancelada não pode ser concluída', 409);
        $this->goalModel->syncProgressFromTasks($userId, $task['objetivo_id']);
        jsonResponse(['success' => true, 'message' => 'Tarefa concluída'], 200);
    }

    public function cancel($userId, $taskId) {
        $task = $this->taskModel->findById($taskId);
        if (!$task || (int) $task['usuario_id'] !== (int) $userId) errorResponse('Tarefa não encontrada', 404);
        if (!$this->taskModel->cancel($taskId, $userId)) errorResponse('Somente tarefas pendentes podem ser canceladas', 409);
        jsonResponse(['success' => true, 'message' => 'Tarefa cancelada'], 200);
    }

    public function delete($userId, $taskId) {
        $task = $this->taskModel->findById($taskId);
        
        if (!$task) {
            errorResponse('Tarefa não encontrada', 404);
        }
        
        // Verificar permissão
        if ($task['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para deletar esta tarefa', 403);
        }
        
        // Deletar
        $success = $this->taskModel->delete($taskId);
        
        if (!$success) {
            errorResponse('Erro ao deletar tarefa', 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Tarefa deletada com sucesso'
        ], 200);
    }
    
    /**
     * GET /api/tasks/overdue
     * Retorna tarefas atrasadas
     */
    public function getOverdue($userId) {
        $tasks = $this->taskModel->getOverdue($userId);
        
        jsonResponse([
            'success' => true,
            'count' => count($tasks),
            'tasks' => $tasks
        ], 200);
    }
    
    /**
     * Valida formato de data
     */
    private function isValidDate($date) {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
?>

