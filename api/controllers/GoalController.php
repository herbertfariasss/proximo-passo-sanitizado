<?php
/**
 * ============================================
 * Controller: Goal (Objetivos)
 * ============================================
 * 
 * Este controller gerencia:
 * - CRUD completo de objetivos
 * - Acompanhamento de progresso
 * - Listagens e estatísticas
 */

require_once __DIR__ . '/../models/Goal.php';
require_once __DIR__ . '/../models/Task.php';

class GoalController {
    private $db;
    private $goalModel;
    private $taskModel;
    
    public function __construct($db) {
        $this->db = $db;
        $this->goalModel = new Goal($db);
        $this->taskModel = new Task($db);
    }
    
    /**
     * GET /api/goals
     * Lista todos os objetivos do usuário
     */
    public function getAll($userId) {
        $goals = $this->goalModel->getByUserId($userId);
        
        // Adicionar estatísticas de tarefas para cada objetivo
        foreach ($goals as &$goal) {
            $tasks = $this->taskModel->getByGoalId($goal['id']);
            $goal['tasks_count'] = count($tasks);
            $goal['tasks_done'] = count(array_filter($tasks, function($t) {
                return $t['status'] === 'concluida' && !$t['cancelado_em'];
            }));
            $goal['monthly_plan'] = $this->taskModel->getMonthlyPlanSummary($userId, $goal['id']);
        }
        
        // Estatísticas gerais
        $stats = [
            'total' => count($goals),
            'active' => $this->goalModel->countActive($userId),
            'completed' => count(array_filter($goals, function($g) {
                return $g['progresso'] >= 100 && !$g['cancelado_em'];
            }))
        ];
        
        jsonResponse([
            'success' => true,
            'stats' => $stats,
            'goals' => $goals
        ], 200);
    }
    
    /**
     * GET /api/goals/:id
     * Retorna um objetivo específico
     */
    public function getById($userId, $goalId) {
        $goal = $this->goalModel->findById($goalId);
        
        if (!$goal) {
            errorResponse('Objetivo não encontrado', 404);
        }
        
        // Verificar permissão
        if ($goal['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para acessar este objetivo', 403);
        }
        
        // Adicionar tarefas relacionadas
        $goal['tasks'] = $this->taskModel->getByGoalId($goalId);
        
        jsonResponse([
            'success' => true,
            'goal' => $goal
        ], 200);
    }
    
    /**
     * POST /api/goals
     * Cria novo objetivo
     * 
     * Body esperado:
     * {
     *   "name": "Reserva de emergência",
     *   "description": "Guardar 6 meses de custo de vida",
     *   "progress": 0,
     *   "deadline": "2026-12-31"
     * }
     */
    public function create($userId, $data) {
        // Validar campos obrigatórios
        validateRequired($data, ['name', 'deadline']);
        
        // Validar data
        if (!$this->isValidDate($data['deadline'])) {
            errorResponse('Data inválida. Use formato: YYYY-MM-DD', 400);
        }
        
        // Validar progresso
        $progress = isset($data['progress']) ? intval($data['progress']) : 0;
        if ($progress < 0 || $progress > 100) {
            errorResponse('Progresso deve estar entre 0 e 100', 400);
        }
        
        // Preparar dados
        $goalData = [
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'progress' => $progress,
            'deadline' => $data['deadline']
        ];
        
        // Criar objetivo
        $goalId = $this->goalModel->create($goalData);
        
        if (!$goalId) {
            errorResponse('Erro ao criar objetivo', 500);
        }
        
        // Buscar objetivo criado
        $goal = $this->goalModel->findById($goalId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Objetivo criado com sucesso',
            'goal' => $goal
        ], 201);
    }
    
    /**
     * PUT /api/goals/:id
     * Atualiza um objetivo
     */
    public function update($userId, $goalId, $data) {
        $goal = $this->goalModel->findById($goalId);
        
        if (!$goal) {
            errorResponse('Objetivo não encontrado', 404);
        }
        
        // Verificar permissão
        if ($goal['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para editar este objetivo', 403);
        }
        if ($goal['cancelado_em']) errorResponse('Objetivo cancelado não pode ser concluído', 409);
        
        // Validar data se fornecida
        if (isset($data['deadline']) && !$this->isValidDate($data['deadline'])) {
            errorResponse('Data inválida', 400);
        }
        
        // Validar progresso se fornecido
        if (isset($data['progress'])) {
            $progress = intval($data['progress']);
            if ($progress < 0 || $progress > 100) {
                errorResponse('Progresso deve estar entre 0 e 100', 400);
            }
        }
        
        // Preparar dados para atualização
        $updateData = [
            'name' => $data['name'] ?? $goal['name'],
            'description' => $data['description'] ?? $goal['description'],
            'progress' => isset($data['progress']) ? intval($data['progress']) : $goal['progress'],
            'deadline' => $data['deadline'] ?? $goal['deadline']
        ];
        
        // Atualizar
        $success = $this->goalModel->update($goalId, $updateData);
        
        if (!$success) {
            errorResponse('Erro ao atualizar objetivo', 500);
        }
        
        // Buscar objetivo atualizado
        $goal = $this->goalModel->findById($goalId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Objetivo atualizado com sucesso',
            'goal' => $goal
        ], 200);
    }
    
    /**
     * PUT /api/goals/:id/progress
     * Atualiza apenas o progresso do objetivo
     * 
     * Body: { "progress": 75 }
     */
    public function updateProgress($userId, $goalId, $data) {
        $goal = $this->goalModel->findById($goalId);
        
        if (!$goal) {
            errorResponse('Objetivo não encontrado', 404);
        }
        
        // Verificar permissão
        if ($goal['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para editar este objetivo', 403);
        }
        if ($goal['cancelado_em']) errorResponse('Objetivo cancelado não pode ser concluído', 409);
        
        // Validar progresso
        validateRequired($data, ['progress']);
        $progress = intval($data['progress']);
        
        if ($progress < 0 || $progress > 100) {
            errorResponse('Progresso deve estar entre 0 e 100', 400);
        }
        
        // Atualizar
        $success = $this->goalModel->updateProgress($goalId, $progress);
        
        if (!$success) {
            errorResponse('Erro ao atualizar progresso', 500);
        }
        
        // Buscar objetivo atualizado
        $goal = $this->goalModel->findById($goalId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Progresso atualizado com sucesso',
            'goal' => $goal
        ], 200);
    }
    
    /**
     * DELETE /api/goals/:id
     * Deleta um objetivo
     */
    public function delete($userId, $goalId) {
        $goal = $this->goalModel->findById($goalId);
        
        if (!$goal) {
            errorResponse('Objetivo não encontrado', 404);
        }
        
        // Verificar permissão
        if ($goal['usuario_id'] != $userId) {
            errorResponse('Você não tem permissão para deletar este objetivo', 403);
        }
        
        // Deletar
        $success = $this->goalModel->delete($goalId);
        
        if (!$success) {
            errorResponse('Erro ao deletar objetivo', 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Objetivo deletado com sucesso'
        ], 200);
    }
    
    /**
     * GET /api/goals/active
     * Retorna apenas objetivos ativos (progresso < 100%)
     */
    public function cancel($userId, $goalId) {
        $goal = $this->goalModel->findById($goalId);
        if (!$goal || (int) $goal['usuario_id'] !== (int) $userId) errorResponse('Objetivo não encontrado', 404);
        if (!$this->goalModel->cancel($goalId, $userId)) errorResponse('Somente objetivos ativos podem ser cancelados', 409);
        jsonResponse(['success' => true, 'message' => 'Objetivo cancelado'], 200);
    }

    public function getActive($userId) {
        $goals = $this->goalModel->getActiveByUserId($userId);
        
        // Adicionar estatísticas de tarefas
        foreach ($goals as &$goal) {
            $tasks = $this->taskModel->getByGoalId($goal['id']);
            $goal['tasks_count'] = count($tasks);
            $goal['tasks_done'] = count(array_filter($tasks, function($t) {
                return $t['status'] === 'concluida' && !$t['cancelado_em'];
            }));
        }
        
        jsonResponse([
            'success' => true,
            'count' => count($goals),
            'goals' => $goals
        ], 200);
    }
    
    /**
     * GET /api/goals/upcoming-deadlines
     * Retorna objetivos com prazo próximo (< 30 dias)
     */
    public function getUpcomingDeadlines($userId) {
        $goals = $this->goalModel->getUpcomingDeadlines($userId);
        
        jsonResponse([
            'success' => true,
            'count' => count($goals),
            'goals' => $goals
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

