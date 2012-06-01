SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `statedecoded`
--

-- --------------------------------------------------------

--
-- Table structure for table `definitions`
--

CREATE TABLE IF NOT EXISTS `definitions` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `law_id` int(10) unsigned NOT NULL,
  `term` varchar(64) collate utf8_bin NOT NULL,
  `definition` text collate utf8_bin NOT NULL,
  `scope` enum('section','chapter','title','global') collate utf8_bin NOT NULL default 'chapter',
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `law_id` (`law_id`,`term`,`scope`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=7423 ;

-- --------------------------------------------------------

--
-- Table structure for table `editions`
--

CREATE TABLE IF NOT EXISTS `editions` (
  `id` tinyint(3) unsigned NOT NULL auto_increment,
  `year` year(4) NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `year` (`year`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=5 ;

-- --------------------------------------------------------

--
-- Table structure for table `laws`
--

CREATE TABLE IF NOT EXISTS `laws` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `structure_id` smallint(3) unsigned default NULL,
  `edition_id` tinyint(3) unsigned NOT NULL,
  `section` varchar(16) collate utf8_bin NOT NULL,
  `catch_line` varchar(255) collate utf8_bin NOT NULL,
  `history` text collate utf8_bin,
  `order_by` varchar(24) collate utf8_bin default NULL,
  `named_act` enum('y','n') collate utf8_bin NOT NULL default 'n',
  `text` text collate utf8_bin,
  `repealed` enum('y','n') collate utf8_bin NOT NULL default 'n',
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `section` (`section`),
  KEY `chapter_id` (`structure_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=30827 ;

-- --------------------------------------------------------

--
-- Table structure for table `laws_meta`
--

CREATE TABLE IF NOT EXISTS `laws_meta` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `law_id` int(11) NOT NULL,
  `key` varchar(255) collate utf8_bin NOT NULL,
  `value` longtext collate utf8_bin NOT NULL,
  `date_created` date NOT NULL,
  `date_modified` timestamp NOT NULL default '0000-00-00 00:00:00' on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `law_id` (`law_id`),
  KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `laws_references`
--

CREATE TABLE IF NOT EXISTS `laws_references` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `section_id` int(10) unsigned NOT NULL,
  `target_section_number` varchar(16) collate utf8_bin NOT NULL,
  `target_section_id` int(10) unsigned NOT NULL,
  `mentions` tinyint(3) unsigned NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `overlap` (`section_id`,`target_section_number`),
  KEY `target_section_number` (`target_section_number`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=31936 ;

-- --------------------------------------------------------

--
-- Table structure for table `laws_views`
--

CREATE TABLE IF NOT EXISTS `laws_views` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `section_id` int(10) NOT NULL,
  `user_id` smallint(5) unsigned default NULL,
  `ip_address` int(10) unsigned default NULL,
  `date` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `section_id` (`section_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=297665 ;

-- --------------------------------------------------------

--
-- Table structure for table `Sphinx`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`web`@`localhost` SQL SECURITY DEFINER VIEW `statedecoded`.`Sphinx` AS select `statedecoded`.`laws`.`id` AS `id`,`statedecoded`.`laws`.`section` AS `section`,`statedecoded`.`laws`.`catch_line` AS `catch_line`,`statedecoded`.`laws`.`text` AS `text`,`statedecoded`.`chapters`.`number` AS `chapter_number`,`statedecoded`.`chapters`.`name` AS `chapter_name`,`statedecoded`.`titles`.`number` AS `title_number`,`statedecoded`.`titles`.`name` AS `title_name`,group_concat(`statedecoded`.`tags`.`text` separator ',') AS `tags` from (((`statedecoded`.`laws` left join `statedecoded`.`chapters` on((`statedecoded`.`laws`.`chapter_id` = `statedecoded`.`chapters`.`id`))) left join `statedecoded`.`titles` on((`statedecoded`.`chapters`.`title_id` = `statedecoded`.`titles`.`id`))) left join `statedecoded`.`tags` on((`statedecoded`.`laws`.`id` = `statedecoded`.`tags`.`law_id`))) group by `statedecoded`.`laws`.`id`;

-- --------------------------------------------------------

--
-- Table structure for table `structure`
--

CREATE TABLE IF NOT EXISTS `structure` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(128) collate utf8_bin default NULL,
  `number` varchar(16) collate utf8_bin NOT NULL,
  `label` varchar(32) collate utf8_bin NOT NULL,
  `parent_id` smallint(5) unsigned default NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=6238 ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `structure_unified`
--
CREATE TABLE IF NOT EXISTS `structure_unified` (
`s1_id` smallint(5) unsigned
,`s1_name` varchar(128)
,`s1_number` varchar(16)
,`s1_label` varchar(32)
,`s2_id` smallint(5) unsigned
,`s2_name` varchar(128)
,`s2_number` varchar(16)
,`s2_label` varchar(32)
);
-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE IF NOT EXISTS `tags` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `law_id` mediumint(8) unsigned NOT NULL,
  `section_number` varchar(16) collate utf8_bin NOT NULL,
  `text` varchar(36) collate utf8_bin NOT NULL,
  `user_id` smallint(5) unsigned default NULL,
  `ip_address` int(10) unsigned default NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `law_id` (`law_id`,`text`),
  KEY `main_select` (`law_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=25310 ;

-- --------------------------------------------------------

--
-- Table structure for table `text`
--

CREATE TABLE IF NOT EXISTS `text` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `law_id` int(10) unsigned NOT NULL,
  `sequence` smallint(5) unsigned NOT NULL,
  `text` text collate utf8_bin NOT NULL,
  `type` enum('section','table') collate utf8_bin NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `law_id` (`law_id`,`sequence`),
  FULLTEXT KEY `text` (`text`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=115676 ;

-- --------------------------------------------------------

--
-- Table structure for table `text_sections`
--

CREATE TABLE IF NOT EXISTS `text_sections` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `text_id` int(10) unsigned NOT NULL,
  `identifier` varchar(3) collate utf8_bin NOT NULL,
  `sequence` tinyint(3) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `text_id` (`text_id`,`identifier`,`sequence`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=101937 ;

-- --------------------------------------------------------

--
-- Structure for view `structure_unified`
--
DROP TABLE IF EXISTS `structure_unified`;

CREATE ALGORITHM=UNDEFINED DEFINER=`web`@`localhost` SQL SECURITY DEFINER VIEW `structure_unified` AS select `s1`.`id` AS `s1_id`,`s1`.`name` AS `s1_name`,`s1`.`number` AS `s1_number`,`s1`.`label` AS `s1_label`,`s2`.`id` AS `s2_id`,`s2`.`name` AS `s2_name`,`s2`.`number` AS `s2_number`,`s2`.`label` AS `s2_label` from (`structure` `s1` left join `structure` `s2` on((`s2`.`id` = `s1`.`parent_id`))) order by `s2`.`number`,`s1`.`number`;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
