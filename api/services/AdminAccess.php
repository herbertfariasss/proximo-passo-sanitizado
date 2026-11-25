<?php
class AdminAccess {
    public static function isAdmin($user) {
        $emails = array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', ''))));
        return $user && in_array(strtolower($user['email']), array_map('strtolower', $emails), true);
    }
}
