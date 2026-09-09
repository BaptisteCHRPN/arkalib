<?php

namespace App\Enum;

enum OrganizationRole: string
{
    case ADMIN = 'admin';
    case TREASURER = 'treasurer';
    case READER = 'reader';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::TREASURER => 'Trésorier',
            self::READER => 'Lecteur',
        };
    }

    /**
     * Les rôles sont hiérarchiques : un admin peut tout ce que peut un trésorier,
     * qui peut tout ce que peut un lecteur. Permet d'écrire `$role->includes(READER)`
     * dans les Voters plutôt que d'énumérer les cas.
     */
    public function includes(self $other): bool
    {
        return $this->level() >= $other->level();
    }

    private function level(): int
    {
        return match ($this) {
            self::READER => 1,
            self::TREASURER => 2,
            self::ADMIN => 3,
        };
    }
}
