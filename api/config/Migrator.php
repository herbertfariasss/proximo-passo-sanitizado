<?php
/**
 * ============================================
 * Migrator: aplica migrações SQL simples, sem destruir dados
 * ============================================
 *
 * Usa arquivos de database/migrations/*.sql escritos só com
 * CREATE TABLE IF NOT EXISTS. Se a tabela de teste já existe, não faz nada.
 */

class Migrator {
    /**
     * @param PDO    $conn       Conexão
     * @param string $probeTable Tabela que indica que a migração já foi aplicada
     * @param string $file       Nome do arquivo em database/migrations
     */
    public static function ensure($conn, $probeTable, $file) {
        try {
            $conn->query("SELECT 1 FROM {$probeTable} WHERE 1 = 0");
            return;
        } catch (PDOException $e) {
            // Tabela ausente: aplica a migração abaixo
        }
        
        $path = __DIR__ . '/../../database/migrations/' . $file;
        $sql = is_readable($path) ? file_get_contents($path) : '';
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || stripos($statement, 'USE ') === 0) continue;
            $conn->exec($statement);
        }
    }
}
?>
