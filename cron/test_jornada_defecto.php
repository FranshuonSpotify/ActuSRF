<?php
declare(strict_types=1);

/* Comprueba sf_renderMatchesLastJornada(): Resultados debe abrir en la
   primera jornada con algún partido sin jugar, no siempre en la última.
   Uso:  /c/xampp/php/php.exe cron/test_jornada_defecto.php   (desde htdocs/) */

require_once __DIR__ . '/render.php';

$falló = false;
function comprobar(string $descripcion, bool $condicion): void {
    global $falló;
    echo ($condicion ? 'OK   ' : 'FAIL ') . $descripcion . "\n";
    if (!$condicion) $falló = true;
}

function partido(int $jornada, string $local, string $visitante, bool $fin): array {
    return [
        'jornada' => (string)$jornada, 'fecha' => '', 'estado' => $fin ? 'FINALIZADO' : 'PENDIENTE',
        'local' => $local, 'visitante' => $visitante, 'goles_l' => $fin ? 1 : 0, 'goles_v' => 0, 'detalles' => ' / ',
    ];
}

// Jornada 1 sin jugar todavía, aunque ya exista la 2: se abre en la 1.
[, $label] = sf_renderMatchesLastJornada([partido(1, 'A', 'B', false), partido(2, 'C', 'D', false)], []);
comprobar('con la jornada 1 sin jugar, abre en la 1', $label === 'Jornada 1');

// Jornada 1 completa, jornada 2 pendiente: avanza sola a la 2.
[, $label] = sf_renderMatchesLastJornada([partido(1, 'A', 'B', true), partido(2, 'C', 'D', false)], []);
comprobar('al completarse la jornada 1, avanza sola a la 2', $label === 'Jornada 2');

// Jornada 1 a medias (un partido sin jugar): se queda en la 1.
[, $label] = sf_renderMatchesLastJornada([partido(1, 'A', 'B', true), partido(1, 'C', 'D', false), partido(2, 'A', 'C', false)], []);
comprobar('con la jornada 1 a medias, se queda en la 1', $label === 'Jornada 1');

// Todo jugado: se queda en la última, como antes de este cambio.
[, $label] = sf_renderMatchesLastJornada([partido(1, 'A', 'B', true), partido(2, 'C', 'D', true), partido(3, 'A', 'D', true)], []);
comprobar('con todo jugado, se queda en la última', $label === 'Jornada 3');

exit($falló ? 1 : 0);
