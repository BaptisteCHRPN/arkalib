-- Jeu de données de test pour l'organisation "Raquettes Asso" (organization.id = 20)
-- Crée un budget prévisionnel avec des catégories (dont une vide et une sous-catégorie vide),
-- des lignes de dépenses/recettes réparties dessus, des lignes directement sur une catégorie
-- parente, des lignes non catégorisées, et une ligne inactive (pour vérifier qu'elle est bien
-- filtrée par la vue prévisionnelle).
--
-- À importer sur la base locale (arkalib_test) avant de tester la vue budget.

START TRANSACTION;

-- Budget
INSERT INTO budget (name, start_date, end_date, is_active, organization_id, slug, is_closed, created_at, updated_at)
VALUES ('Budget prévisionnel 2026', '2026-01-01', '2026-12-31', 1, 20, 'budget-previsionnel-2026-raquettes-asso', 0, NOW(), NOW());
SET @budget_id = LAST_INSERT_ID();

-- Catégories racines
INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Alimentation', @budget_id, NULL, NOW(), NOW());
SET @cat_alimentation = LAST_INSERT_ID();

INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Cotisations', @budget_id, NULL, NOW(), NOW());
SET @cat_cotisations = LAST_INSERT_ID();

INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Équipement', @budget_id, NULL, NOW(), NOW());
SET @cat_equipement = LAST_INSERT_ID();

-- Catégorie racine volontairement vide (aucune ligne, aucun enfant)
INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Événements', @budget_id, NULL, NOW(), NOW());
SET @cat_evenements = LAST_INSERT_ID();

-- Sous-catégories d'Alimentation
INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Restaurant', @budget_id, @cat_alimentation, NOW(), NOW());
SET @cat_restaurant = LAST_INSERT_ID();

INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Courses', @budget_id, @cat_alimentation, NOW(), NOW());
SET @cat_courses = LAST_INSERT_ID();

-- Sous-catégorie volontairement vide (aucune ligne)
INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Sorties', @budget_id, @cat_alimentation, NOW(), NOW());
SET @cat_sorties = LAST_INSERT_ID();

-- Sous-catégorie d'Équipement
INSERT INTO category (name, budget_id, parent_category_id, created_at, updated_at) VALUES ('Raquettes', @budget_id, @cat_equipement, NOW(), NOW());
SET @cat_raquettes = LAST_INSERT_ID();

-- Lignes : Alimentation > Restaurant
INSERT INTO budget_line (name, is_expense, description, amount, is_active, budget_id, category_id, created_at, updated_at) VALUES
('Repas équipe seniors', 1, 'Repas après tournoi', 80.00, 1, @budget_id, @cat_restaurant, NOW(), NOW()),
('Repas équipe jeunes', 1, NULL, 45.00, 1, @budget_id, @cat_restaurant, NOW(), NOW());

-- Lignes : Alimentation > Courses
INSERT INTO budget_line (name, is_expense, description, amount, is_active, budget_id, category_id, created_at, updated_at) VALUES
('Courses semaine 1', 1, 'Boissons et snacks', 90.00, 1, @budget_id, @cat_courses, NOW(), NOW()),
('Courses semaine 2', 1, NULL, 80.00, 1, @budget_id, @cat_courses, NOW(), NOW());

-- Ligne inactive sur Courses : ne doit PAS apparaître dans la vue (is_active = 0)
INSERT INTO budget_line (name, is_expense, description, amount, is_active, budget_id, category_id, created_at, updated_at) VALUES
('Ancienne dépense annulée', 1, 'Ne doit pas apparaître (is_active=0)', 60.00, 0, @budget_id, @cat_courses, NOW(), NOW());

-- Lignes directement sur la catégorie racine Cotisations (pas de sous-catégorie)
INSERT INTO budget_line (name, is_expense, description, amount, is_active, budget_id, category_id, created_at, updated_at) VALUES
('Cotisations adultes', 0, 'Licences saison 2026', 3200.00, 1, @budget_id, @cat_cotisations, NOW(), NOW()),
('Cotisations enfants', 0, NULL, 1500.00, 1, @budget_id, @cat_cotisations, NOW(), NOW());

-- Lignes directement sur Équipement (parent) + lignes sur sa sous-catégorie Raquettes
INSERT INTO budget_line (name, is_expense, description, amount, is_active, budget_id, category_id, created_at, updated_at) VALUES
('Filets de terrain', 1, NULL, 250.00, 1, @budget_id, @cat_equipement, NOW(), NOW()),
('Subvention matériel', 0, 'Subvention mairie', 400.00, 1, @budget_id, @cat_equipement, NOW(), NOW());

INSERT INTO budget_line (name, is_expense, description, amount, is_active, budget_id, category_id, created_at, updated_at) VALUES
('Raquettes club débutants', 1, 'Lot de 10 raquettes', 320.00, 1, @budget_id, @cat_raquettes, NOW(), NOW()),
('Raquettes compétition', 1, NULL, 540.00, 1, @budget_id, @cat_raquettes, NOW(), NOW());

-- Lignes non catégorisées (category_id NULL)
INSERT INTO budget_line (name, is_expense, description, amount, is_active, budget_id, category_id, created_at, updated_at) VALUES
('Frais bancaires', 1, NULL, 25.00, 1, @budget_id, NULL, NOW(), NOW()),
('Don ponctuel', 0, 'Don d''un sponsor local', 200.00, 1, @budget_id, NULL, NOW(), NOW());

COMMIT;
