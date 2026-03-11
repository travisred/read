<?php

$database = 'rss';
$sqliteFile = 'db/rss.db';

#$host = 'localhost';
#$user = 'root';
#$password = '';
#$database = 'clink';

/*
CREATE TABLE IF NOT EXISTS `rss` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` text,
  `url` text,
  `comments` text,
  `site` text,
  `is_read` tinyint(1) DEFAULT NULL,
  `is_starred` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2686 ;
*/

// SQLite
/*
CREATE TABLE IF NOT EXISTS rss (
id INTEGER PRIMARY KEY,
title TEXT,
url  TEXT,
comments TEXT,
site TEXT,
is_read INTEGER,
is_starred INTEGER)
*/