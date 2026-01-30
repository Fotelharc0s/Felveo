-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gép: 127.0.0.1
-- Létrehozás ideje: 2026. Jan 29. 11:19
-- Kiszolgáló verziója: 10.4.32-MariaDB
-- PHP verzió: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Adatbázis: `felveteli`
--

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `altalanos_iskolak`
--

CREATE TABLE `altalanos_iskolak` (
  `om_azonosito` char(6) NOT NULL,
  `nev` varchar(150) NOT NULL,
  `cim` varchar(200) NOT NULL,
  `telefonszam` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `iranyitoszam` char(4) NOT NULL,
  `telepules` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `beallitasok`
--

CREATE TABLE `beallitasok` (
  `id` int(11) NOT NULL,
  `nev` varchar(100) NOT NULL,
  `ertek` varchar(500) NOT NULL,
  `leiras` varchar(500) DEFAULT NULL,
  `modositva` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `beallitasok`
--

INSERT INTO `beallitasok` (`id`, `nev`, `ertek`, `leiras`, `modositva`) VALUES
(1, 'max_pont_magyar_alapertelmezett', '50', 'Alapértelmezett maximum pontszám magyar', '2026-01-28 08:41:26'),
(2, 'max_pont_matematika_alapertelmezett', '50', 'Alapértelmezett maximum pontszám matematika', '2026-01-28 08:41:26'),
(3, 'dokumentumok_mappa', 'uploads/dokumentumok/', 'Feltöltött dokumentumok mappája', '2026-01-28 08:41:26');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `dokumentumok`
--

CREATE TABLE `dokumentumok` (
  `id` int(11) NOT NULL,
  `oktatasi_azonosito` char(11) NOT NULL,
  `targy_id` int(11) NOT NULL,
  `fajlnev` varchar(255) NOT NULL,
  `fajl_path` varchar(500) NOT NULL,
  `feltoltve` timestamp NOT NULL DEFAULT current_timestamp(),
  `modositva` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `eredmenyek`
--

CREATE TABLE `eredmenyek` (
  `id` int(11) NOT NULL,
  `oktatasi_azonosito` char(11) NOT NULL,
  `targy_id` int(11) DEFAULT NULL,
  `max_pont_magyar` int(11) DEFAULT 50,
  `max_pont_matematika` int(11) DEFAULT 50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `eredmenyek`
--

INSERT INTO `eredmenyek` (`id`, `oktatasi_azonosito`, `targy_id`, `max_pont_magyar`, `max_pont_matematika`) VALUES
(384, '72770184806', 1, 50, 50),
(385, '72770184806', 2, 50, 50),
(386, '12345678901', 1, 50, 50),
(387, '12345678901', 2, 50, 50),
(388, '23456789012', 1, 50, 50),
(389, '23456789012', 2, 50, 50);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `pontok`
--

CREATE TABLE `pontok` (
  `eredmeny_id` int(11) NOT NULL,
  `ponttipus_id` int(11) NOT NULL,
  `ertek` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `pontok`
--

INSERT INTO `pontok` (`eredmeny_id`, `ponttipus_id`, `ertek`) VALUES
(384, 2, 69),
(385, 2, 69),
(386, 2, 85),
(387, 2, 92),
(388, 2, 78),
(389, 2, 88);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `ponttipusok`
--

CREATE TABLE `ponttipusok` (
  `id` int(11) NOT NULL,
  `nev` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `ponttipusok`
--

INSERT INTO `ponttipusok` (`id`, `nev`) VALUES
(2, 'elert_pont'),
(3, 'hozott_pont'),
(1, 'max_pont'),
(4, 'szobeli_pont');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `rangsorolas`
--

CREATE TABLE `rangsorolas` (
  `id` int(11) NOT NULL,
  `oktatasi_azonosito` char(11) NOT NULL,
  `tanulmanyi_terulet_azonosito` char(4) NOT NULL,
  `helyezes` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `szemelyek`
--

CREATE TABLE `szemelyek` (
  `oktatasi_azonosito` char(11) NOT NULL,
  `nev` varchar(100) NOT NULL,
  `szuletesi_ido` date NOT NULL,
  `alt_iskola_om` char(6) DEFAULT NULL,
  `lakcim` varchar(200) NOT NULL,
  `anyja_neve` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `jelszo_hash` varchar(255) NOT NULL,
  `is_placeholder` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `szemelyek`
--

INSERT INTO `szemelyek` (`oktatasi_azonosito`, `nev`, `szuletesi_ido`, `alt_iskola_om`, `lakcim`, `anyja_neve`, `email`, `jelszo_hash`, `is_placeholder`) VALUES
('12345678901', 'Kiss Péter', '2008-04-12', NULL, '', 'Kiss Anna', 'kisspeter@gmail.com', '', 0),
('23456789012', 'Nagy Éva', '2009-06-01', NULL, '', 'Nagy Mária', 'nagyeva@gmail.com', '', 0),
('72770184806', 'Nagy Lajos', '2008-04-12', NULL, '', 'Kiss Anna', 'nagylajos@gmail.com', '', 0);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `szemely_tanulmanyi_teruletek`
--

CREATE TABLE `szemely_tanulmanyi_teruletek` (
  `oktatasi_azonosito` char(11) NOT NULL,
  `tanulmanyi_terulet_azonosito` char(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `tanulmanyi_teruletek`
--

CREATE TABLE `tanulmanyi_teruletek` (
  `azonosito` char(4) NOT NULL,
  `megnevezes` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `targyak`
--

CREATE TABLE `targyak` (
  `id` int(11) NOT NULL,
  `nev` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `targyak`
--

INSERT INTO `targyak` (`id`, `nev`) VALUES
(1, 'magyar'),
(2, 'matematika');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `telepulesek`
--

CREATE TABLE `telepulesek` (
  `id` int(11) NOT NULL,
  `iranyitoszam` char(4) NOT NULL,
  `nev` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- Indexek a kiírt táblákhoz
--

--
-- A tábla indexei `altalanos_iskolak`
--
ALTER TABLE `altalanos_iskolak`
  ADD PRIMARY KEY (`om_azonosito`);

--
-- A tábla indexei `beallitasok`
--
ALTER TABLE `beallitasok`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nev` (`nev`);

--
-- A tábla indexei `dokumentumok`
--
ALTER TABLE `dokumentumok`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_okt_targy` (`oktatasi_azonosito`,`targy_id`),
  ADD KEY `targy_id` (`targy_id`);

--
-- A tábla indexei `eredmenyek`
--
ALTER TABLE `eredmenyek`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oktatasi_azonosito` (`oktatasi_azonosito`),
  ADD KEY `fk_eredmeny_targy` (`targy_id`);

--
-- A tábla indexei `pontok`
--
ALTER TABLE `pontok`
  ADD PRIMARY KEY (`eredmeny_id`,`ponttipus_id`),
  ADD KEY `ponttipus_id` (`ponttipus_id`);

--
-- A tábla indexei `ponttipusok`
--
ALTER TABLE `ponttipusok`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nev` (`nev`);

--
-- A tábla indexei `rangsorolas`
--
ALTER TABLE `rangsorolas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `oktatasi_azonosito` (`oktatasi_azonosito`,`tanulmanyi_terulet_azonosito`),
  ADD KEY `tanulmanyi_terulet_azonosito` (`tanulmanyi_terulet_azonosito`);

--
-- A tábla indexei `szemelyek`
--
ALTER TABLE `szemelyek`
  ADD PRIMARY KEY (`oktatasi_azonosito`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `alt_iskola_om` (`alt_iskola_om`);

--
-- A tábla indexei `szemely_tanulmanyi_teruletek`
--
ALTER TABLE `szemely_tanulmanyi_teruletek`
  ADD PRIMARY KEY (`oktatasi_azonosito`,`tanulmanyi_terulet_azonosito`),
  ADD KEY `tanulmanyi_terulet_azonosito` (`tanulmanyi_terulet_azonosito`);

--
-- A tábla indexei `tanulmanyi_teruletek`
--
ALTER TABLE `tanulmanyi_teruletek`
  ADD PRIMARY KEY (`azonosito`);

--
-- A tábla indexei `targyak`
--
ALTER TABLE `targyak`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nev` (`nev`);

--
-- A tábla indexei `telepulesek`
--
ALTER TABLE `telepulesek`
  ADD PRIMARY KEY (`id`);

--
-- A kiírt táblák AUTO_INCREMENT értéke
--

--
-- AUTO_INCREMENT a táblához `beallitasok`
--
ALTER TABLE `beallitasok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT a táblához `dokumentumok`
--
ALTER TABLE `dokumentumok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `eredmenyek`
--
ALTER TABLE `eredmenyek`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=390;

--
-- AUTO_INCREMENT a táblához `ponttipusok`
--
ALTER TABLE `ponttipusok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT a táblához `rangsorolas`
--
ALTER TABLE `rangsorolas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `targyak`
--
ALTER TABLE `targyak`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT a táblához `telepulesek`
--
ALTER TABLE `telepulesek`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Megkötések a kiírt táblákhoz
--

--
-- Megkötések a táblához `dokumentumok`
--
ALTER TABLE `dokumentumok`
  ADD CONSTRAINT `dokumentumok_ibfk_1` FOREIGN KEY (`oktatasi_azonosito`) REFERENCES `szemelyek` (`oktatasi_azonosito`) ON DELETE CASCADE,
  ADD CONSTRAINT `dokumentumok_ibfk_2` FOREIGN KEY (`targy_id`) REFERENCES `targyak` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `eredmenyek`
--
ALTER TABLE `eredmenyek`
  ADD CONSTRAINT `fk_eredmeny_szemely` FOREIGN KEY (`oktatasi_azonosito`) REFERENCES `szemelyek` (`oktatasi_azonosito`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eredmeny_targy` FOREIGN KEY (`targy_id`) REFERENCES `targyak` (`id`);

--
-- Megkötések a táblához `pontok`
--
ALTER TABLE `pontok`
  ADD CONSTRAINT `pontok_ibfk_1` FOREIGN KEY (`eredmeny_id`) REFERENCES `eredmenyek` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pontok_ibfk_2` FOREIGN KEY (`ponttipus_id`) REFERENCES `ponttipusok` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Megkötések a táblához `rangsorolas`
--
ALTER TABLE `rangsorolas`
  ADD CONSTRAINT `fk_rangsor_szemely` FOREIGN KEY (`oktatasi_azonosito`) REFERENCES `szemelyek` (`oktatasi_azonosito`) ON DELETE CASCADE,
  ADD CONSTRAINT `rangsorolas_ibfk_2` FOREIGN KEY (`tanulmanyi_terulet_azonosito`) REFERENCES `tanulmanyi_teruletek` (`azonosito`) ON DELETE CASCADE;

--
-- Megkötések a táblához `szemelyek`
--
ALTER TABLE `szemelyek`
  ADD CONSTRAINT `szemelyek_ibfk_1` FOREIGN KEY (`alt_iskola_om`) REFERENCES `altalanos_iskolak` (`om_azonosito`);

--
-- Megkötések a táblához `szemely_tanulmanyi_teruletek`
--
ALTER TABLE `szemely_tanulmanyi_teruletek`
  ADD CONSTRAINT `fk_szemely_terulet_szemely` FOREIGN KEY (`oktatasi_azonosito`) REFERENCES `szemelyek` (`oktatasi_azonosito`) ON DELETE CASCADE,
  ADD CONSTRAINT `szemely_tanulmanyi_teruletek_ibfk_2` FOREIGN KEY (`tanulmanyi_terulet_azonosito`) REFERENCES `tanulmanyi_teruletek` (`azonosito`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
