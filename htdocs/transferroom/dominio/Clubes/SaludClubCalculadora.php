<?php

declare(strict_types=1);

/**
 * Semáforo de salud del club (Fase 2, 02_ux_diseno/05_rediseno_pantalla_inicio_hub_navegacion_v3.md
 * §2.0): sin fórmula especificada en ningún documento — decisión propia,
 * documentada aquí para que se pueda revisar y ajustar.
 *
 * Combina dos señales objetivas que ya existen en el resto de la app:
 *   - Fichas activas frente a las 20 exigidas por mercado.md §2/§10.
 *   - Cap usado frente al Salary Cap de la temporada.
 *
 * ROJO:  fichas fuera de [15, 20] (plantilla gravemente incompleta o con
 *        exceso, no podría competir así) o el cap usado supera el máximo
 *        (estado imposible, siempre indica un error a corregir).
 * ÁMBAR: fichas distintas de 20 mientras el mercado sigue abierto (normal
 *        a mitad de construcción de plantilla, pero merece atención), o
 *        el cap usado supera el 97% (sin margen para imprevistos).
 * VERDE: 20 fichas exactas y cap usado dentro de un margen razonable.
 *
 * Pura y sin dependencias, igual que ContractEngine::cabeEnCap — testeable
 * de forma aislada.
 */
final class SaludClubCalculadora
{
    private const FICHAS_EXACTAS = 20;
    private const FICHAS_MINIMO_ACEPTABLE = 15;
    private const UMBRAL_CAP_SIN_MARGEN = 0.97;

    public static function calcular(int $fichasActivas, float $capUsado, float $capMaximo): array
    {
        if ($capMaximo <= 0) {
            return ['estado' => 'rojo', 'motivo' => 'Salary Cap no configurado.'];
        }

        $ratioCap = $capUsado / $capMaximo;

        if ($capUsado > $capMaximo) {
            return ['estado' => 'rojo', 'motivo' => 'El gasto salarial supera el Salary Cap.'];
        }

        if ($fichasActivas > self::FICHAS_EXACTAS || $fichasActivas < self::FICHAS_MINIMO_ACEPTABLE) {
            return ['estado' => 'rojo', 'motivo' => 'Plantilla muy alejada de las ' . self::FICHAS_EXACTAS . " fichas exigidas ({$fichasActivas})."];
        }

        if ($fichasActivas !== self::FICHAS_EXACTAS) {
            return ['estado' => 'ambar', 'motivo' => "Plantilla incompleta: {$fichasActivas}/" . self::FICHAS_EXACTAS . ' fichas.'];
        }

        if ($ratioCap > self::UMBRAL_CAP_SIN_MARGEN) {
            return ['estado' => 'ambar', 'motivo' => 'Salary Cap casi agotado, sin margen para imprevistos.'];
        }

        return ['estado' => 'verde', 'motivo' => 'Plantilla completa y cap saneado.'];
    }
}
