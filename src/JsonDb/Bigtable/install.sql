--- CAMBIAR EL PREFIJO "phidias_jsondb" a lo que se necesite

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `phidias_jsondb_indexes` (
  `tableId` varchar(32) NOT NULL,
  `recordId` varchar(32) NOT NULL,
  `keyName` varchar(32) NOT NULL,
  `keyValue` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `phidias_jsondb_records` (
  `id` varchar(32) NOT NULL,
  `tableId` varchar(32) NOT NULL,
  `customId` varchar(32) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `keywords` mediumtext DEFAULT NULL,
  `authorId` varchar(32) DEFAULT NULL,
  `dateCreated` int(11) DEFAULT NULL,
  `dateModified` int(11) DEFAULT NULL,
  `dateDeleted` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `phidias_jsondb_indexes`
  ADD PRIMARY KEY (`tableId`,`recordId`,`keyName`,`keyValue`),
  ADD KEY `recordId` (`recordId`);

ALTER TABLE `phidias_jsondb_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tableId` (`tableId`),
  ADD KEY `authorId` (`authorId`);


ALTER TABLE `phidias_jsondb_indexes`
  ADD CONSTRAINT `phidias_jsondb_indexes_fk1` FOREIGN KEY (`recordId`) REFERENCES `phidias_jsondb_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;
