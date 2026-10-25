<?php

namespace App\Enum;

/**
 * Sens réel d'une transaction : de l'argent reçu ou de l'argent payé.
 */
enum TransactionDirection
{
    case CASH_IN;
    case CASH_OUT;

    public function label(): string
    {
        return match ($this) {
            self::CASH_IN => 'Encaissement',
            self::CASH_OUT => 'Décaissement',
        };
    }
}
