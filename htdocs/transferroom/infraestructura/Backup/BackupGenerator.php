<?php

declare(strict_types=1);

/**
 * Copia de seguridad manual descargable (05-transfer_room_docs/01_bloqueante_primer_mercado/10).
 * Volcado en PHP puro, sin invocar mysqldump: en un hosting compartido
 * (IONOS es el destino previsto) shell_exec suele estar deshabilitado por
 * seguridad, así que esto tiene que funcionar sin él.
 */
final class BackupGenerator
{
    public static function generarSql(PDO $db): string
    {
        $tablas = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $sql = "-- Copia de seguridad de Transfer Room — " . date('Y-m-d H:i:s') . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tablas as $tabla) {
            $sql .= "-- --------------------------------------------------\n";
            $sql .= "-- Tabla: {$tabla}\n";
            $sql .= "-- --------------------------------------------------\n";

            $crear = $db->query("SHOW CREATE TABLE `{$tabla}`")->fetch();
            $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n" . $crear['Create Table'] . ";\n\n";

            $filas = $db->query("SELECT * FROM `{$tabla}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as $fila) {
                $columnas = array_map(fn ($c) => "`{$c}`", array_keys($fila));
                $valores = array_map(function ($v) use ($db) {
                    return $v === null ? 'NULL' : $db->quote((string) $v);
                }, array_values($fila));

                $sql .= "INSERT INTO `{$tabla}` (" . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /** Carpeta donde se persisten los backups, fuera de publico/ para que nunca sea accesible por URL directa. */
    public static function carpetaDestino(): string
    {
        return __DIR__ . '/../../db/backups';
    }

    /**
     * Genera el SQL y lo deja también en disco (histórico, punto 6 del inventario),
     * en vez de generar-y-descargar sin dejar rastro de los anteriores.
     */
    public static function generarYGuardar(PDO $db): array
    {
        $sql = self::generarSql($db);
        $carpeta = self::carpetaDestino();
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        $nombre = 'transferroom_backup_' . date('Y-m-d_His') . '.sql';
        file_put_contents($carpeta . '/' . $nombre, $sql);

        return ['nombre' => $nombre, 'sql' => $sql, 'tamano' => strlen($sql)];
    }

    /** Lee un backup ya guardado por su nombre de fichero, validando que existe dentro de la carpeta destino (nunca una ruta libre). */
    public static function leerDeDisco(string $nombreArchivo): ?string
    {
        $ruta = self::carpetaDestino() . '/' . basename($nombreArchivo);

        return is_file($ruta) ? file_get_contents($ruta) : null;
    }
}
