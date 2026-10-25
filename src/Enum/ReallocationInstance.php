<?php

namespace App\Enum;

enum ReallocationInstance: string
{
    case BOARD = 'board';
    case GENERAL_ASSEMBLY = 'general_assembly';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BOARD => 'Conseil d\'administration',
            self::GENERAL_ASSEMBLY => 'Assemblée générale',
            self::OTHER => 'Autre instance',
        };
    }

    /**
     * Forme courte pour les messages, ex. « réaffectation du 12/10/2026 (CA) ».
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::BOARD => 'CA',
            self::GENERAL_ASSEMBLY => 'AG',
            self::OTHER => 'Autre',
        };
    }
}
