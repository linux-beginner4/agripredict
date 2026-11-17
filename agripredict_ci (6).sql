-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 17 nov. 2026 à 06:48
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `agripredict_ci`
--

-- --------------------------------------------------------

--
-- Structure de la table `alertes`
--

CREATE TABLE `alertes` (
  `id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `date_alerte` date NOT NULL,
  `type` enum('mortalite','ponte','aliment','cout','vaccin') NOT NULL,
  `niveau` enum('info','attention','critique') NOT NULL DEFAULT 'attention',
  `message` varchar(255) NOT NULL,
  `vue` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `alertes`
--

INSERT INTO `alertes` (`id`, `lot_id`, `date_alerte`, `type`, `niveau`, `message`, `vue`) VALUES
(581, 12, '2026-11-02', 'mortalite', 'critique', 'Mortalité anormale le 02/11 : 7 morts (habituellement 0,2 par jour, soit 3,6 % de l\'effectif).', 0),
(582, 12, '2026-10-27', 'ponte', 'attention', 'Baisse de ponte du 27/10 au 30/10 (4 jours) : 70,8 % au plus bas contre 92,0 % habituellement (-23 %).', 0),
(583, 12, '2026-10-27', 'aliment', 'attention', 'Baisse de consommation d\'aliment du 27/10 au 30/10 : 99 g par sujet contre 115 g habituellement (-14 %). À surveiller : chaleur, eau, santé du lot.', 0),
(584, 12, '2026-11-06', 'cout', 'attention', 'Prix de l\'aliment en hausse : 615 FCFA/kg contre 520 FCFA/kg habituellement (+18 %).', 0),
(585, 12, '2026-11-12', 'vaccin', 'attention', 'Vaccin en retard : Bronchite infectieuse (rappel) (prévu le 12/11). Pensez à le réaliser ou à le replanifier avec votre vétérinaire.', 0),
(586, 12, '2026-11-16', 'vaccin', 'info', 'Vaccin à faire bientôt : Newcastle (rappel), prévu le 16/11.', 0),
(587, 13, '2026-10-20', 'mortalite', 'critique', 'Mortalité anormale le 20/10 : 14 morts (habituellement 0,3 par jour, soit 2,8 % de l\'effectif).', 0),
(588, 13, '2026-10-21', 'aliment', 'attention', 'Baisse de consommation d\'aliment du 21/10 au 22/10 : 76 g par sujet contre 84 g habituellement (-9 %). À surveiller : chaleur, eau, santé du lot.', 0);

-- --------------------------------------------------------

--
-- Structure de la table `contacts_veterinaires`
--

CREATE TABLE `contacts_veterinaires` (
  `id` int(11) NOT NULL,
  `elevage_id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `telephone` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

CREATE TABLE `depenses` (
  `id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `date_depense` date NOT NULL,
  `categorie` enum('aliment','sante','poussins','main_oeuvre','energie','autre') NOT NULL,
  `libelle` varchar(150) NOT NULL,
  `montant_fcfa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `depenses`
--

INSERT INTO `depenses` (`id`, `lot_id`, `date_depense`, `categorie`, `libelle`, `montant_fcfa`) VALUES
(105, 12, '2026-09-19', 'poussins', 'Achat de 200 poulettes prêtes à pondre', 760000),
(106, 12, '2026-09-25', 'aliment', 'Aliment pondeuse, semaine 1', 83694),
(107, 12, '2026-10-02', 'aliment', 'Aliment pondeuse, semaine 2', 82531),
(108, 12, '2026-10-09', 'aliment', 'Aliment pondeuse, semaine 3', 82530),
(109, 12, '2026-10-16', 'aliment', 'Aliment pondeuse, semaine 4', 82368),
(110, 12, '2026-10-23', 'aliment', 'Aliment pondeuse, semaine 5', 82253),
(111, 12, '2026-10-30', 'aliment', 'Aliment pondeuse, semaine 6', 76484),
(112, 12, '2026-11-06', 'aliment', 'Aliment pondeuse, semaine 7', 93791),
(113, 12, '2026-11-13', 'aliment', 'Aliment pondeuse, semaine 8', 92007),
(114, 12, '2026-09-29', 'sante', 'Vitamines et vaccin', 25000),
(115, 12, '2026-11-03', 'sante', 'Visite vétérinaire', 15000),
(116, 12, '2026-10-16', 'main_oeuvre', 'Salaire ouvrier (mois 1)', 40000),
(117, 12, '2026-11-13', 'main_oeuvre', 'Salaire ouvrier (mois 2)', 40000),
(118, 12, '2026-10-19', 'energie', 'Électricité du bâtiment', 15000),
(119, 13, '2026-10-01', 'poussins', 'Achat de 500 poussins chair', 325000),
(120, 13, '2026-10-01', 'energie', 'Chauffage (charbon, gaz)', 25000),
(121, 13, '2026-10-04', 'sante', 'Vitamines et anti-stress', 8000),
(122, 13, '2026-10-08', 'sante', 'Vaccin Gumboro', 20000),
(123, 13, '2026-10-15', 'sante', 'Vaccin Newcastle', 15000),
(124, 13, '2026-10-31', 'main_oeuvre', 'Salaire ouvrier', 30000),
(125, 13, '2026-10-07', 'aliment', 'Aliment chair, semaine 1', 39924),
(126, 13, '2026-10-14', 'aliment', 'Aliment chair, semaine 2', 89789),
(127, 13, '2026-10-21', 'aliment', 'Aliment chair, semaine 3', 133356),
(128, 13, '2026-10-28', 'aliment', 'Aliment chair, semaine 4', 178347),
(129, 13, '2026-11-04', 'aliment', 'Aliment chair, semaine 5', 229334),
(130, 13, '2026-11-11', 'aliment', 'Aliment chair, semaine 6', 279869);

-- --------------------------------------------------------

--
-- Structure de la table `diagnostics`
--

CREATE TABLE `diagnostics` (
  `id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `date_consultation` datetime NOT NULL,
  `symptomes` text NOT NULL,
  `touches` varchar(20) NOT NULL DEFAULT 'quelques',
  `duree_jours` int(11) DEFAULT NULL,
  `resultat` varchar(255) DEFAULT NULL,
  `urgence` varchar(20) NOT NULL DEFAULT 'surveiller'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `diagnostics`
--

INSERT INTO `diagnostics` (`id`, `lot_id`, `date_consultation`, `symptomes`, `touches`, `duree_jours`, `resultat`, `urgence`) VALUES
(1, 13, '2026-11-14 12:24:02', 'diarrhee_verte', 'quelques', NULL, 'Pasteurellose (choléra aviaire) / Maladie de Newcastle', 'surveiller');

-- --------------------------------------------------------

--
-- Structure de la table `elevages`
--

CREATE TABLE `elevages` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `localite` varchar(120) DEFAULT NULL,
  `type_elevage` varchar(30) NOT NULL DEFAULT 'volaille',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `elevages`
--

INSERT INTO `elevages` (`id`, `utilisateur_id`, `nom`, `localite`, `type_elevage`, `created_at`) VALUES
(1, 1, 'Volaille', NULL, 'volaille', '2026-11-09 18:58:22'),
(2, 2, 'rd', 'jh', 'volaille', '2026-11-14 12:03:58');

-- --------------------------------------------------------

--
-- Structure de la table `lots`
--

CREATE TABLE `lots` (
  `id` int(11) NOT NULL,
  `elevage_id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `type_production` enum('chair','pondeuse') NOT NULL,
  `effectif_initial` int(11) NOT NULL,
  `date_arrivee` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('en_cours','termine') NOT NULL DEFAULT 'en_cours',
  `est_demo` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `lots`
--

INSERT INTO `lots` (`id`, `elevage_id`, `nom`, `type_production`, `effectif_initial`, `date_arrivee`, `date_fin`, `statut`, `est_demo`) VALUES
(12, 1, 'Pondeuses bâtiment A (démo)', 'pondeuse', 200, '2026-09-19', NULL, 'en_cours', 1),
(13, 1, 'Poulets de chair lot 1 (démo)', 'chair', 500, '2026-10-01', '2026-11-11', 'termine', 1),
(14, 2, 'Docteur', 'pondeuse', 1, '2026-11-14', NULL, 'en_cours', 0);

-- --------------------------------------------------------

--
-- Structure de la table `suivis_quotidiens`
--

CREATE TABLE `suivis_quotidiens` (
  `id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `date_suivi` date NOT NULL,
  `mortalite` int(11) NOT NULL DEFAULT 0,
  `aliment_kg` decimal(8,2) NOT NULL DEFAULT 0.00,
  `eau_litres` decimal(8,2) DEFAULT NULL,
  `oeufs_produits` int(11) DEFAULT NULL,
  `poids_moyen_g` int(11) DEFAULT NULL,
  `remarque` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `suivis_quotidiens`
--

INSERT INTO `suivis_quotidiens` (`id`, `lot_id`, `date_suivi`, `mortalite`, `aliment_kg`, `eau_litres`, `oeufs_produits`, `poids_moyen_g`, `remarque`) VALUES
(393, 12, '2026-09-19', 1, 22.77, 52.00, 71, NULL, NULL),
(394, 12, '2026-09-20', 0, 22.32, 50.80, 77, NULL, NULL),
(395, 12, '2026-09-21', 1, 23.44, 50.10, 76, NULL, NULL),
(396, 12, '2026-09-22', 0, 23.33, 48.50, 86, NULL, NULL),
(397, 12, '2026-09-23', 0, 23.16, 50.80, 87, NULL, NULL),
(398, 12, '2026-09-24', 0, 22.63, 50.40, 95, NULL, NULL),
(399, 12, '2026-09-25', 0, 23.31, 51.30, 99, NULL, NULL),
(400, 12, '2026-09-26', 0, 22.69, 51.10, 106, NULL, NULL),
(401, 12, '2026-09-27', 1, 22.47, 48.30, 106, NULL, NULL),
(402, 12, '2026-09-28', 0, 23.23, 47.20, 112, NULL, NULL),
(403, 12, '2026-09-29', 0, 22.14, 47.40, 115, NULL, NULL),
(404, 12, '2026-09-30', 0, 22.85, 51.40, 121, NULL, NULL),
(405, 12, '2026-10-01', 0, 23.29, 51.10, 130, NULL, NULL),
(406, 12, '2026-10-02', 0, 22.04, 49.50, 134, NULL, NULL),
(407, 12, '2026-10-03', 0, 22.90, 51.60, 135, NULL, NULL),
(408, 12, '2026-10-04', 0, 22.90, 50.50, 144, NULL, NULL),
(409, 12, '2026-10-05', 0, 22.61, 46.90, 148, NULL, NULL),
(410, 12, '2026-10-06', 0, 22.67, 49.70, 154, NULL, NULL),
(411, 12, '2026-10-07', 0, 22.64, 48.20, 157, NULL, NULL),
(412, 12, '2026-10-08', 0, 22.59, 47.20, 165, NULL, NULL),
(413, 12, '2026-10-09', 0, 22.40, 49.70, 165, NULL, NULL),
(414, 12, '2026-10-10', 0, 22.31, 51.30, 172, NULL, NULL),
(415, 12, '2026-10-11', 0, 22.25, 48.30, 175, NULL, NULL),
(416, 12, '2026-10-12', 0, 22.98, 47.30, 180, NULL, NULL),
(417, 12, '2026-10-13', 0, 22.09, 48.90, 183, NULL, NULL),
(418, 12, '2026-10-14', 0, 23.30, 51.50, 181, NULL, NULL),
(419, 12, '2026-10-15', 0, 22.60, 48.80, 179, NULL, NULL),
(420, 12, '2026-10-16', 0, 22.86, 49.90, 180, NULL, NULL),
(421, 12, '2026-10-17', 0, 22.60, 51.20, 182, NULL, NULL),
(422, 12, '2026-10-18', 0, 22.88, 48.70, 179, NULL, NULL),
(423, 12, '2026-10-19', 1, 22.67, 50.60, 179, NULL, NULL),
(424, 12, '2026-10-20', 0, 21.99, 50.40, 182, NULL, NULL),
(425, 12, '2026-10-21', 0, 22.73, 49.80, 182, NULL, NULL),
(426, 12, '2026-10-22', 0, 23.10, 49.40, 179, NULL, NULL),
(427, 12, '2026-10-23', 1, 22.21, 50.00, 179, NULL, NULL),
(428, 12, '2026-10-24', 0, 22.25, 46.50, 179, NULL, NULL),
(429, 12, '2026-10-25', 0, 22.38, 50.80, 178, NULL, NULL),
(430, 12, '2026-10-26', 0, 22.70, 49.50, 179, NULL, NULL),
(431, 12, '2026-10-27', 0, 19.34, 49.80, 141, NULL, 'Forte chaleur'),
(432, 12, '2026-10-28', 0, 20.03, 46.80, 138, NULL, 'Forte chaleur'),
(433, 12, '2026-10-29', 0, 20.16, 49.50, 141, NULL, 'Forte chaleur'),
(434, 12, '2026-10-30', 0, 20.23, 47.20, 138, NULL, 'Forte chaleur'),
(435, 12, '2026-10-31', 0, 21.98, 50.70, 182, NULL, NULL),
(436, 12, '2026-11-01', 1, 22.74, 48.70, 177, NULL, NULL),
(437, 12, '2026-11-02', 7, 20.98, 48.80, 175, NULL, 'Mortalité anormale, vétérinaire appelé'),
(438, 12, '2026-11-03', 0, 22.10, 46.70, 157, NULL, NULL),
(439, 12, '2026-11-04', 0, 20.94, 45.40, 157, NULL, NULL),
(440, 12, '2026-11-05', 0, 22.12, 46.00, 155, NULL, NULL),
(441, 12, '2026-11-06', 0, 21.65, 47.30, 172, NULL, NULL),
(442, 12, '2026-11-07', 1, 21.01, 46.00, 168, NULL, NULL),
(443, 12, '2026-11-08', 0, 21.14, 45.80, 169, NULL, NULL),
(444, 12, '2026-11-09', 0, 21.30, 44.70, 169, NULL, NULL),
(445, 12, '2026-11-10', 0, 21.57, 45.30, 170, NULL, NULL),
(446, 12, '2026-11-11', 0, 21.60, 46.90, 173, NULL, NULL),
(447, 12, '2026-11-12', 0, 21.35, 46.00, 171, NULL, NULL),
(448, 12, '2026-11-13', 0, 21.63, 48.40, 170, NULL, NULL),
(449, 13, '2026-10-01', 0, 5.52, 15.30, NULL, 42, NULL),
(450, 13, '2026-10-02', 1, 7.33, 17.90, NULL, NULL, NULL),
(451, 13, '2026-10-03', 0, 9.52, 21.80, NULL, NULL, NULL),
(452, 13, '2026-10-04', 1, 11.45, 27.30, NULL, NULL, NULL),
(453, 13, '2026-10-05', 2, 13.75, 30.70, NULL, NULL, NULL),
(454, 13, '2026-10-06', 2, 15.14, 33.60, NULL, NULL, NULL),
(455, 13, '2026-10-07', 0, 17.13, 37.20, NULL, NULL, NULL),
(456, 13, '2026-10-08', 0, 20.12, 42.30, NULL, 177, NULL),
(457, 13, '2026-10-09', 1, 21.51, 43.30, NULL, NULL, NULL),
(458, 13, '2026-10-10', 1, 23.34, 46.60, NULL, NULL, NULL),
(459, 13, '2026-10-11', 0, 25.81, 54.20, NULL, NULL, NULL),
(460, 13, '2026-10-12', 0, 27.08, 54.30, NULL, NULL, NULL),
(461, 13, '2026-10-13', 0, 29.53, 60.70, NULL, NULL, NULL),
(462, 13, '2026-10-14', 0, 32.18, 65.40, NULL, NULL, NULL),
(463, 13, '2026-10-15', 0, 34.56, 66.40, NULL, 447, NULL),
(464, 13, '2026-10-16', 0, 36.64, 68.70, NULL, NULL, NULL),
(465, 13, '2026-10-17', 0, 36.97, 75.70, NULL, NULL, NULL),
(466, 13, '2026-10-18', 0, 39.05, 78.90, NULL, NULL, NULL),
(467, 13, '2026-10-19', 0, 41.21, 83.80, NULL, NULL, NULL),
(468, 13, '2026-10-20', 14, 42.07, 79.70, NULL, NULL, 'Coup de chaleur, mortalité élevée'),
(469, 13, '2026-10-21', 1, 36.20, 89.50, NULL, NULL, 'Baisse d\'appétit'),
(470, 13, '2026-10-22', 0, 36.32, 90.30, NULL, 841, 'Baisse d\'appétit'),
(471, 13, '2026-10-23', 1, 47.76, 94.20, NULL, NULL, NULL),
(472, 13, '2026-10-24', 0, 50.54, 93.30, NULL, NULL, NULL),
(473, 13, '2026-10-25', 0, 52.35, 96.60, NULL, NULL, NULL),
(474, 13, '2026-10-26', 0, 55.59, 108.60, NULL, NULL, NULL),
(475, 13, '2026-10-27', 1, 55.55, 109.20, NULL, NULL, NULL),
(476, 13, '2026-10-28', 1, 58.58, 112.60, NULL, NULL, NULL),
(477, 13, '2026-10-29', 0, 59.31, 114.10, NULL, 1412, NULL),
(478, 13, '2026-10-30', 0, 62.10, 112.80, NULL, NULL, NULL),
(479, 13, '2026-10-31', 0, 62.29, 121.30, NULL, NULL, NULL),
(480, 13, '2026-11-01', 0, 64.35, 123.40, NULL, NULL, NULL),
(481, 13, '2026-11-02', 0, 69.30, 124.30, NULL, NULL, NULL),
(482, 13, '2026-11-03', 0, 68.49, 129.20, NULL, NULL, NULL),
(483, 13, '2026-11-04', 0, 72.83, 130.90, NULL, NULL, NULL),
(484, 13, '2026-11-05', 0, 75.10, 143.80, NULL, 1960, NULL),
(485, 13, '2026-11-06', 0, 77.42, 140.00, NULL, NULL, NULL),
(486, 13, '2026-11-07', 0, 77.98, 145.00, NULL, NULL, NULL),
(487, 13, '2026-11-08', 0, 81.32, 144.20, NULL, NULL, NULL),
(488, 13, '2026-11-09', 0, 80.02, 151.50, NULL, NULL, NULL),
(489, 13, '2026-11-10', 1, 82.22, 161.90, NULL, NULL, NULL),
(490, 13, '2026-11-11', 0, 85.69, 152.40, NULL, 2394, NULL),
(491, 14, '2026-11-14', 0, 12.00, 12.00, 106, NULL, 'Chaleur');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `email`, `mot_de_passe`, `telephone`, `created_at`) VALUES
(1, 'Walid Billa', 'billawalid02@gmail.com', '$2y$10$ihPL2YuV0eJ23kv4fiwnvOG1JebvKKtOJFYfC6gMBzu16yBOyaohq', NULL, '2026-11-09 18:58:22'),
(2, 'Walid Billa', 'billawalid04@gmail.com', '$2y$10$ntQ.k4ondWzJG006iKKS5OSI82mksEzHy6RrM/ohzkOFD1gwN89HK', '12', '2026-11-14 12:03:58');

-- --------------------------------------------------------

--
-- Structure de la table `vaccinations`
--

CREATE TABLE `vaccinations` (
  `id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `age_jour` int(11) DEFAULT NULL,
  `date_prevue` date NOT NULL,
  `statut` enum('prevu','fait') NOT NULL DEFAULT 'prevu',
  `date_realisee` date DEFAULT NULL,
  `produit` varchar(100) DEFAULT NULL,
  `voie` varchar(30) DEFAULT NULL,
  `numero_flacon` varchar(60) DEFAULT NULL,
  `remarque` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `vaccinations`
--

INSERT INTO `vaccinations` (`id`, `lot_id`, `nom`, `age_jour`, `date_prevue`, `statut`, `date_realisee`, `produit`, `voie`, `numero_flacon`, `remarque`) VALUES
(27, 12, 'Newcastle (rappel)', 30, '2026-10-19', 'fait', '2026-10-20', 'Newcastle La Sota', 'eau', NULL, NULL),
(28, 12, 'Bronchite infectieuse (rappel)', 54, '2026-11-12', 'prevu', NULL, NULL, NULL, NULL, NULL),
(29, 12, 'Newcastle (rappel)', 58, '2026-11-16', 'prevu', NULL, NULL, NULL, NULL, NULL),
(30, 12, 'Newcastle (rappel)', 90, '2026-12-18', 'prevu', NULL, NULL, NULL, NULL, NULL),
(31, 13, 'Newcastle (1re dose)', 7, '2026-10-08', 'fait', '2026-10-08', 'Newcastle HB1', 'oculaire', NULL, NULL),
(32, 13, 'Gumboro (1re dose)', 14, '2026-10-15', 'fait', '2026-10-15', 'Gumboro intermédiaire', 'eau', NULL, NULL),
(33, 13, 'Newcastle (rappel)', 21, '2026-10-22', 'fait', '2026-10-23', 'Newcastle La Sota', 'eau', NULL, NULL),
(34, 14, 'Newcastle (rappel)', 30, '2026-12-14', 'prevu', NULL, NULL, NULL, NULL, NULL),
(35, 14, 'Newcastle (rappel)', 90, '2027-02-12', 'prevu', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `ventes`
--

CREATE TABLE `ventes` (
  `id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `date_vente` date NOT NULL,
  `produit` enum('poulet','oeufs','fientes','autre') NOT NULL,
  `quantite` decimal(10,2) NOT NULL,
  `prix_unitaire_fcfa` int(11) NOT NULL,
  `montant_fcfa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ventes`
--

INSERT INTO `ventes` (`id`, `lot_id`, `date_vente`, `produit`, `quantite`, `prix_unitaire_fcfa`, `montant_fcfa`) VALUES
(37, 12, '2026-09-25', 'oeufs', 19.00, 2400, 45600),
(38, 12, '2026-10-02', 'oeufs', 26.00, 2400, 62400),
(39, 12, '2026-10-09', 'oeufs', 34.00, 2400, 81600),
(40, 12, '2026-10-16', 'oeufs', 40.00, 2400, 96000),
(41, 12, '2026-10-23', 'oeufs', 40.00, 2400, 96000),
(42, 12, '2026-10-30', 'oeufs', 35.00, 2400, 84000),
(43, 12, '2026-11-06', 'oeufs', 37.00, 2400, 88800),
(44, 12, '2026-11-13', 'oeufs', 38.00, 2400, 91200),
(45, 13, '2026-11-11', 'poulet', 458.00, 3200, 1465600);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lot_id` (`lot_id`);

--
-- Index pour la table `contacts_veterinaires`
--
ALTER TABLE `contacts_veterinaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `elevage_id` (`elevage_id`);

--
-- Index pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_depenses_date` (`lot_id`,`date_depense`);

--
-- Index pour la table `diagnostics`
--
ALTER TABLE `diagnostics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_diag_lot` (`lot_id`,`date_consultation`);

--
-- Index pour la table `elevages`
--
ALTER TABLE `elevages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `lots`
--
ALTER TABLE `lots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `elevage_id` (`elevage_id`);

--
-- Index pour la table `suivis_quotidiens`
--
ALTER TABLE `suivis_quotidiens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_lot_date` (`lot_id`,`date_suivi`),
  ADD KEY `idx_suivis_date` (`lot_id`,`date_suivi`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `vaccinations`
--
ALTER TABLE `vaccinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vacc_lot_date` (`lot_id`,`date_prevue`);

--
-- Index pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lot_id` (`lot_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `alertes`
--
ALTER TABLE `alertes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=589;

--
-- AUTO_INCREMENT pour la table `contacts_veterinaires`
--
ALTER TABLE `contacts_veterinaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depenses`
--
ALTER TABLE `depenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT pour la table `diagnostics`
--
ALTER TABLE `diagnostics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `elevages`
--
ALTER TABLE `elevages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `lots`
--
ALTER TABLE `lots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `suivis_quotidiens`
--
ALTER TABLE `suivis_quotidiens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=492;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `vaccinations`
--
ALTER TABLE `vaccinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT pour la table `ventes`
--
ALTER TABLE `ventes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD CONSTRAINT `alertes_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `contacts_veterinaires`
--
ALTER TABLE `contacts_veterinaires`
  ADD CONSTRAINT `contacts_veterinaires_ibfk_1` FOREIGN KEY (`elevage_id`) REFERENCES `elevages` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD CONSTRAINT `depenses_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `diagnostics`
--
ALTER TABLE `diagnostics`
  ADD CONSTRAINT `diagnostics_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `elevages`
--
ALTER TABLE `elevages`
  ADD CONSTRAINT `elevages_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `lots`
--
ALTER TABLE `lots`
  ADD CONSTRAINT `lots_ibfk_1` FOREIGN KEY (`elevage_id`) REFERENCES `elevages` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `suivis_quotidiens`
--
ALTER TABLE `suivis_quotidiens`
  ADD CONSTRAINT `suivis_quotidiens_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `vaccinations`
--
ALTER TABLE `vaccinations`
  ADD CONSTRAINT `vaccinations_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
