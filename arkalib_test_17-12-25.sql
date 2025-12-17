-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 17 déc. 2025 à 22:39
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `arkalib_test`
--

-- --------------------------------------------------------

--
-- Structure de la table `budget`
--

DROP TABLE IF EXISTS `budget`;
CREATE TABLE IF NOT EXISTS `budget` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint NOT NULL,
  `organization_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_73F2F77B32C8A3DE` (`organization_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `budget`
--

INSERT INTO `budget` (`id`, `name`, `start_date`, `end_date`, `is_active`, `organization_id`) VALUES
(1, 'Budget 2026', '2026-01-01', '2026-12-31', 1, 2),
(2, 'Budget 2025', '2025-01-01', '2025-12-31', 1, 2);

-- --------------------------------------------------------

--
-- Structure de la table `budget_line`
--

DROP TABLE IF EXISTS `budget_line`;
CREATE TABLE IF NOT EXISTS `budget_line` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_expense` tinyint NOT NULL,
  `descrption` longtext NOT NULL,
  `amount` double NOT NULL,
  `created_at` datetime NOT NULL,
  `is_active` tinyint NOT NULL,
  `budget_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_ABD0B6A636ABA6B8` (`budget_id`),
  KEY `IDX_ABD0B6A612469DE2` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `category`
--

DROP TABLE IF EXISTS `category`;
CREATE TABLE IF NOT EXISTS `category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20251217080257', '2025-12-17 17:09:50', 264),
('DoctrineMigrations\\Version20251217112303', '2025-12-17 17:09:50', 189),
('DoctrineMigrations\\Version20251217112901', '2025-12-17 17:09:50', 63),
('DoctrineMigrations\\Version20251217113115', '2025-12-17 17:09:50', 60),
('DoctrineMigrations\\Version20251217134505', '2025-12-17 17:09:50', 58),
('DoctrineMigrations\\Version20251217151612', '2025-12-17 17:09:50', 46),
('DoctrineMigrations\\Version20251217154357', '2025-12-17 18:31:34', 99),
('DoctrineMigrations\\Version20251217170942', NULL, NULL),
('DoctrineMigrations\\Version20251217183128', NULL, NULL),
('DoctrineMigrations\\Version20251217213436', NULL, NULL),
('DoctrineMigrations\\Version20251217213827', '2025-12-17 21:38:35', 913);

-- --------------------------------------------------------

--
-- Structure de la table `messenger_messages`
--

DROP TABLE IF EXISTS `messenger_messages`;
CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `organization`
--

DROP TABLE IF EXISTS `organization`;
CREATE TABLE IF NOT EXISTS `organization` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `is_active` tinyint NOT NULL,
  `description` longtext,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `organization`
--

INSERT INTO `organization` (`id`, `name`, `created_at`, `is_active`, `description`) VALUES
(1, 'Paris natation', '2025-12-17 17:23:19', 1, 'Une association de natation parisienne.'),
(2, 'Sport pour tous', '2025-12-17 17:24:48', 1, 'Une associoation multisport.');

-- --------------------------------------------------------

--
-- Structure de la table `organization_user`
--

DROP TABLE IF EXISTS `organization_user`;
CREATE TABLE IF NOT EXISTS `organization_user` (
  `organization_id` int NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`organization_id`,`user_id`),
  KEY `IDX_B49AE8D432C8A3DE` (`organization_id`),
  KEY `IDX_B49AE8D4A76ED395` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `organization_user`
--

INSERT INTO `organization_user` (`organization_id`, `user_id`) VALUES
(1, 1),
(2, 2);

-- --------------------------------------------------------

--
-- Structure de la table `reset_password_request`
--

DROP TABLE IF EXISTS `reset_password_request`;
CREATE TABLE IF NOT EXISTS `reset_password_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `selector` varchar(20) NOT NULL,
  `hashed_token` varchar(100) NOT NULL,
  `requested_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_7CE748AA76ED395` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `role`
--

DROP TABLE IF EXISTS `role`;
CREATE TABLE IF NOT EXISTS `role` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_role` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transaction`
--

DROP TABLE IF EXISTS `transaction`;
CREATE TABLE IF NOT EXISTS `transaction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `amount` double NOT NULL,
  `payment_method` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `is_active` tinyint NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transaction_budget_line`
--

DROP TABLE IF EXISTS `transaction_budget_line`;
CREATE TABLE IF NOT EXISTS `transaction_budget_line` (
  `transaction_id` int NOT NULL,
  `budget_line_id` int NOT NULL,
  PRIMARY KEY (`transaction_id`,`budget_line_id`),
  KEY `IDX_1759A38B2FC0CB0F` (`transaction_id`),
  KEY `IDX_1759A38B8FF83FA3` (`budget_line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint NOT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `user`
--

INSERT INTO `user` (`id`, `email`, `roles`, `password`, `is_verified`, `firstname`, `lastname`) VALUES
(1, 'admin@test.fr', '[\"ROLE_ADMIN\"]', '$2y$13$SJR124cYNNkWM2ZiLQhetOT2YfHSz4FxgURxu2k6AKrbpQxWtXb0O', 1, 'Jean', 'PATRON'),
(2, 'test@test.fr', '[]', '$2y$13$1q/8W4wXPVvdn8zwvm.tvezOBVqvxa0Nvumlvs9eTynfclqCN0WGC', 1, 'Baptiste', 'CHERPIN');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `budget`
--
ALTER TABLE `budget`
  ADD CONSTRAINT `FK_73F2F77B32C8A3DE` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`);

--
-- Contraintes pour la table `budget_line`
--
ALTER TABLE `budget_line`
  ADD CONSTRAINT `FK_ABD0B6A612469DE2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`),
  ADD CONSTRAINT `FK_ABD0B6A636ABA6B8` FOREIGN KEY (`budget_id`) REFERENCES `budget` (`id`);

--
-- Contraintes pour la table `organization_user`
--
ALTER TABLE `organization_user`
  ADD CONSTRAINT `FK_B49AE8D432C8A3DE` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_B49AE8D4A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `transaction_budget_line`
--
ALTER TABLE `transaction_budget_line`
  ADD CONSTRAINT `FK_1759A38B2FC0CB0F` FOREIGN KEY (`transaction_id`) REFERENCES `transaction` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_1759A38B8FF83FA3` FOREIGN KEY (`budget_line_id`) REFERENCES `budget_line` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
