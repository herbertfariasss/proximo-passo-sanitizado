<?php
class PresenceService {
    private const WINDOW = 300;
    private static function directory() { return sys_get_temp_dir() . '/proximo_passo_presence_' . hash('sha256', __DIR__); }
    private static function path($token) { return self::directory() . '/' . hash('sha256', $token) . '.json'; }
    public static function touch($userId, $token) {
        if (!$token) return;
        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return;
        @file_put_contents(self::path($token), json_encode(['user_id' => (int)$userId, 'seen' => time()]), LOCK_EX);
    }
    public static function remove($token) { if ($token) @unlink(self::path($token)); }
    public static function activeIds() {
        $ids = [];
        foreach (glob(self::directory() . '/*.json') ?: [] as $file) {
            $data = json_decode(@file_get_contents($file), true);
            if (!isset($data['seen'], $data['user_id']) || $data['seen'] < time() - self::WINDOW) {
                @unlink($file);
                continue;
            }
            $ids[(int)$data['user_id']] = true;
        }
        return array_keys($ids);
    }
}
