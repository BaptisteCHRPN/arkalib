<?php

namespace App\Repository\Trait;

use Doctrine\ORM\QueryBuilder;

/**
 * Recherche textuelle simple pour les annuaires du back-office.
 *
 * Les champs sont concaténés plutôt que testés un à un : une réclamation donne
 * « Marie Dupont » d'un bloc, chaîne qu'aucune colonne ne contient à elle
 * seule. Un terme vide ne filtre rien, ce qui fait de la méthode le point
 * d'entrée unique de la liste.
 */
trait SearchesTextFieldsTrait
{
    /**
     * @param string[] $fields propriétés de l'entité, sans l'alias ; une valeur
     *                         contenant un point est prise telle quelle, ce qui
     *                         permet de chercher sur une entité jointe — au
     *                         requêteur d'ajouter la jointure correspondante.
     */
    private function searchQueryBuilder(?string $term, array $fields, string $alias = 'e'): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($alias)->orderBy($alias . '.id', 'DESC');

        $term = trim((string) $term);

        if ('' === $term || [] === $fields) {
            return $queryBuilder;
        }

        return $queryBuilder
            ->andWhere($this->haystack($fields, $alias) . " LIKE :searchPattern ESCAPE '!'")
            ->setParameter('searchPattern', $this->likePattern($term));
    }

    /**
     * Les jokers SQL saisis au clavier sont neutralisés : sans ça, « % »
     * ramènerait toute la table et un email contenant « _ » ramènerait des
     * comptes qui ne lui ressemblent pas. On échappe avec « ! » plutôt qu'avec
     * l'antislash, dont le sens varie entre MySQL et SQLite.
     */
    private function likePattern(string $term): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
    }

    /**
     * @param string[] $fields
     */
    private function haystack(array $fields, string $alias): string
    {
        $parts = [];

        foreach ($fields as $field) {
            $expression = str_contains($field, '.') ? $field : $alias . '.' . $field;
            $parts[] = sprintf("COALESCE(%s, '')", $expression);
        }

        // CONCAT exige au moins deux arguments ; un champ seul se compare tel quel.
        if (1 === count($parts)) {
            return $parts[0];
        }

        return 'CONCAT(' . implode(", ' ', ", $parts) . ')';
    }
}
