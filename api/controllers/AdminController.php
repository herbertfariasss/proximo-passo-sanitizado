<?php
require_once __DIR__ . '/../services/AdminAccess.php';
require_once __DIR__ . '/../services/PresenceService.php';
require_once __DIR__ . '/../services/AIUsageService.php';
class AdminController {
    private $db;
    public function __construct($db) { $this->db = $db; }
    public function dashboard($userId) {
        $stmt = $this->db->prepare('SELECT id, email FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        if (!AdminAccess::isAdmin($stmt->fetch())) errorResponse('Acesso restrito ao administrador', 403);
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $from = $this->dateParam('from', $timezone);
        $to = $this->dateParam('to', $timezone);
        if ($from && $to && $from > $to) errorResponse('A data inicial deve ser anterior à final', 400);
        $until = $to ? $to->modify('+1 day') : null;
        $active = array_fill_keys(PresenceService::activeIds(), true);
        $users = $this->db->query('SELECT id, nome, email, criado_em FROM usuarios ORDER BY criado_em DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $usage = AIUsageService::totals($from ? $from->getTimestamp() : null, $until ? $until->getTimestamp() : null);
        $totalUsd = 0.0;
        $pricedCalls = 0;
        foreach ($users as &$user) {
            $id = (int)$user['id'];
            $user['online'] = isset($active[$id]);
            $user['ai_cost_usd'] = round($usage[$id]['usd'] ?? 0, 8);
            $user['ai_calls'] = $usage[$id]['calls'] ?? 0;
            $user['ai_unpriced_calls'] = $usage[$id]['unpriced_calls'] ?? 0;
            $user['ai_average_usd'] = $user['ai_calls'] > $user['ai_unpriced_calls'] ? round($user['ai_cost_usd'] / ($user['ai_calls'] - $user['ai_unpriced_calls']), 8) : null;
            $totalUsd += $user['ai_cost_usd'];
            $pricedCalls += $user['ai_calls'] - $user['ai_unpriced_calls'];
        }
        unset($user);
        $online = count(array_filter($users, function($user) { return $user['online']; }));
        $counts = [];
        foreach (['novos_usuarios' => 'usuarios', 'objetivos' => 'objetivos', 'tarefas' => 'tarefas', 'decisoes' => 'decisoes'] as $key => $table) {
            $sql = 'SELECT COUNT(*) FROM ' . $table;
            $where = [];
            $params = [];
            if ($from) { $where[] = 'criado_em >= :from'; $params[':from'] = $from->format('Y-m-d H:i:s'); }
            if ($until) { $where[] = 'criado_em < :until'; $params[':until'] = $until->format('Y-m-d H:i:s'); }
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $countStmt = $this->db->prepare($sql);
            $countStmt->execute($params);
            $counts[$key] = (int)$countStmt->fetchColumn();
        }
        $activity = [];
        $activeDays = [];
        $activeMonth = [];
        $eventSql = "SELECT usuario_id, DATE(criado_em) AS dia, MAX(criado_em) AS ultimo, COUNT(*) AS total FROM (SELECT usuario_id, criado_em FROM tarefas UNION ALL SELECT usuario_id, criado_em FROM objetivos UNION ALL SELECT usuario_id, criado_em FROM decisoes) eventos GROUP BY usuario_id, DATE(criado_em)";
        foreach ($this->db->query($eventSql)->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $id = (int)$event['usuario_id'];
            $date = $event['dia'];
            $activity[$id]['last'] = max($activity[$id]['last'] ?? '', $event['ultimo']);
            $activity[$id]['dates'][$date] = true;
            if (($from && $date < $from->format('Y-m-d')) || ($to && $date > $to->format('Y-m-d'))) continue;
            $activity[$id]['period_count'] = ($activity[$id]['period_count'] ?? 0) + (int)$event['total'];
            $activeDays[$date][$id] = true;
            $activeMonth[substr($date, 0, 7)][$id] = true;
        }
        $today = new DateTimeImmutable('today', $timezone);
        $eligible = 0;
        $returned = 0;
        foreach ($users as &$user) {
            $id = (int)$user['id'];
            $user['last_activity'] = $activity[$id]['last'] ?? null;
            $user['activity_count'] = $activity[$id]['period_count'] ?? 0;
            $signup = DateTimeImmutable::createFromFormat('!Y-m-d', substr($user['criado_em'], 0, 10), $timezone);
            if (!$signup || $signup > $today->modify('-7 days')) continue;
            if (($from && $signup < $from) || ($to && $signup > $to)) continue;
            $eligible++;
            for ($offset = 1; $offset <= 7; $offset++) {
                if (isset($activity[$id]['dates'][$signup->modify('+' . $offset . ' days')->format('Y-m-d')])) { $returned++; break; }
            }
        }
        unset($user);
        $taskStats = $this->periodStats('tarefas', "COUNT(*) AS total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS completed", $from, $until);
        $goalStats = $this->periodStats('objetivos', 'COUNT(*) AS total, AVG(progresso) AS average_progress', $from, $until);
        $counts['active_today'] = count($activeDays[$today->format('Y-m-d')] ?? []);
        $counts['active_month'] = count($activeMonth[$today->format('Y-m')] ?? []);
        $counts['task_completion_rate'] = $taskStats['total'] ? round(100 * $taskStats['completed'] / $taskStats['total'], 1) : 0;
        $counts['goal_average_progress'] = $goalStats['total'] ? round((float)$goalStats['average_progress'], 1) : 0;
        $counts['tasks_completed'] = (int)($taskStats['completed'] ?? 0);
        $counts['retention_7d'] = $eligible ? round(100 * $returned / $eligible, 1) : null;
        $counts['retention_eligible'] = $eligible;
        $counts['ai_average_usd'] = $pricedCalls ? round($totalUsd / $pricedCalls, 8) : null;
        $chartFrom = $from ?: (new DateTimeImmutable('today', $timezone))->modify('-29 days');
        $chartUntil = $until ?: (new DateTimeImmutable('tomorrow', $timezone));
        $chartStart = $chartUntil->modify('-365 days');
        if ($chartFrom < $chartStart) $chartFrom = $chartStart;
        $chart = [];
        for ($day = $chartFrom; $day < $chartUntil; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $chart[$date] = ['date' => $date, 'usuarios' => 0, 'decisoes' => 0, 'ativos' => count($activeDays[$date] ?? []), 'custo' => 0];
        }
        foreach (['usuarios' => 'usuarios', 'decisoes' => 'decisoes'] as $key => $table) {
            $seriesStmt = $this->db->prepare('SELECT DATE(criado_em) AS dia, COUNT(*) AS total FROM ' . $table . ' WHERE criado_em >= :from AND criado_em < :until GROUP BY DATE(criado_em)');
            $seriesStmt->execute([':from' => $chartFrom->format('Y-m-d H:i:s'), ':until' => $chartUntil->format('Y-m-d H:i:s')]);
            foreach ($seriesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($chart[$row['dia']])) $chart[$row['dia']][$key] = (int)$row['total'];
            }
        }
        foreach (AIUsageService::dailyCosts($chartFrom->getTimestamp(), $chartUntil->getTimestamp()) as $day => $cost) {
            if (isset($chart[$day])) $chart[$day]['custo'] = round($cost, 8);
        }
        jsonResponse(['success' => true, 'summary' => array_merge(['total' => count($users), 'online' => $online, 'offline' => count($users) - $online, 'ai_cost_usd' => round($totalUsd, 8)], $counts), 'users' => $users, 'chart' => array_values($chart), 'presence_window_seconds' => 300], 200);
    }
    private function dateParam($name, $timezone) {
        if (!isset($_GET[$name]) || $_GET[$name] === '') return null;
        $value = (string)$_GET[$name];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if (!$date || $date->format('Y-m-d') !== $value) errorResponse('Data inválida: ' . $name, 400);
        return $date;
    }
    private function periodStats($table, $fields, $from, $until) {
        $sql = 'SELECT ' . $fields . ' FROM ' . $table;
        $where = [];
        $params = [];
        if ($from) { $where[] = 'criado_em >= :from'; $params[':from'] = $from->format('Y-m-d H:i:s'); }
        if ($until) { $where[] = 'criado_em < :until'; $params[':until'] = $until->format('Y-m-d H:i:s'); }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
