<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /**
     * Converte Reaios (ex: 12.34) para Centavos (ex: 1234).
     *
     * @param float|string $reais
     * @return integer
     */
    public static function toCents(float|string $reais): int
    {
        if (!is_numeric($reais)) {
            throw new InvalidArgumentException("Invalid monetary value: $reais");
        }

        return (int) bcmul((string) $reais, '100', 0);
    }

    /**
     * Converte Centavos (ex: 1234) para Reais (ex: 12.34).
     *
     * @param integer $cents
     * @return float
     */
    public static function toReais(int $cents): float
    {
        return (float) bcdiv((string) $cents, '100', 2);
    }

    /**
     * Formata um valor em centavos para uma string representando reais (ex: 1234 -> 12.34).
     *
     * @param integer $cents
     * @return string
     */
    public static function formatToReais(int $cents): string
    {
        return number_format(self::toReais($cents), 2);
    }
}
