<?php
class AIUsageService {
    private static function directory() { return sys_get_temp_dir() . '/proximo_passo_ai_usage_' . hash('sha256', __DIR__); }
    public static function record($userId, $model, $usage) {
        if ($userId <= 0 || !is_array($usage)) return;
        $input = (int)($usage['prompt_tokens'] ?? 0);
        $output = (int)($usage['completion_tokens'] ?? 0);
        if ($input + $output <= 0) return;
        $cached = min($input, max(0, (int)($usage['prompt_tokens_details']['cached_tokens'] ?? 0)));
        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return;
        $entry = ['model' => $model, 'input' => $input, 'cached' => $cached, 'output' => $output, 'at' => time()];
        @file_put_contents($dir . '/' . (int)$userId . '.jsonl', json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    public static function totals($from = null, $until = null) {
        $totals = [];
        foreach (glob(self::directory() . '/*.jsonl') ?: [] as $file) {
            $id = (int)basename($file, '.jsonl');
            if ($id <= 0) continue;
            $totals[$id] = ['usd' => 0.0, 'calls' => 0, 'unpriced_calls' => 0];
            $handle = @fopen($file, 'r');
            if (!$handle) continue;
            while (($line = fgets($handle)) !== false) {
                $item = json_decode($line, true);
                if (!is_array($item)) continue;
                $at = (int)($item['at'] ?? 0);
                if (($from !== null && $at < $from) || ($until !== null && $at >= $until)) continue;
                $totals[$id]['calls']++;
                if (($item['model'] ?? '') !== 'openai/gpt-oss-120b') { $totals[$id]['unpriced_calls']++; continue; }
                $input = max(0, (int)($item['input'] ?? 0));
                $cached = min($input, max(0, (int)($item['cached'] ?? 0)));
                $output = max(0, (int)($item['output'] ?? 0));
                $totals[$id]['usd'] += (($input - $cached) * 0.15 + $cached * 0.075 + $output * 0.60) / 1000000;
            }
            fclose($handle);
        }
        return $totals;
    }
    public static function dailyCosts($from, $until) {
        $days = [];
        foreach (glob(self::directory() . '/*.jsonl') ?: [] as $file) {
            $handle = @fopen($file, 'r');
            if (!$handle) continue;
            while (($line = fgets($handle)) !== false) {
                $item = json_decode($line, true);
                if (!is_array($item) || ($item['model'] ?? '') !== 'openai/gpt-oss-120b') continue;
                $at = (int)($item['at'] ?? 0);
                if ($at < $from || $at >= $until) continue;
                $date = (new DateTimeImmutable('@' . $at))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
                $input = max(0, (int)($item['input'] ?? 0));
                $cached = min($input, max(0, (int)($item['cached'] ?? 0)));
                $output = max(0, (int)($item['output'] ?? 0));
                $days[$date] = ($days[$date] ?? 0) + (($input - $cached) * 0.15 + $cached * 0.075 + $output * 0.60) / 1000000;
            }
            fclose($handle);
        }
        return $days;
    }
}
