CREATE TABLE IF NOT EXISTS `mod_pvewhmcs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `config` varchar(255),
  `vnc_secret` varchar(255),
  `start_id` int(10) unsigned,
  PRIMARY KEY (`id`)
);
INSERT INTO `mod_pvewhmcs` (`id`, `config`) VALUES	(1, NULL);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_ip_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pool_id` int(11) NOT NULL DEFAULT '0',
  `ipaddress` varchar(255) NOT NULL DEFAULT '0',
  `mask` varchar(255) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ipaddress` (`ipaddress`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_ip_pools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `gateway` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8 NOT NULL COMMENT 'Plan - Name',
  `vmtype` varchar(8) NOT NULL COMMENT 'Guest Type - KVM or LXC',
  `ostype` varchar(8) DEFAULT NULL COMMENT 'OS - Type',
  `cpus` tinyint(2) unsigned DEFAULT NULL,
  `cpuemu` varchar(30) CHARACTER SET utf8 DEFAULT NULL COMMENT 'Emulated CPU Type eg. coreduo | core2duo | kvm32 | kvm64 | qemu32 | qemu64 | phenom | host',
  `cores` tinyint(2) unsigned DEFAULT NULL COMMENT 'Cores per Socket',
  `cpulimit` smallint(5) unsigned DEFAULT NULL COMMENT 'Limit vs Host CPU (max)',
  `cpuunits` smallint(5) unsigned DEFAULT NULL,
  `memory` smallint(5) unsigned NOT NULL,
  `swap` smallint(5) unsigned DEFAULT NULL,
  `disk` smallint(5) unsigned DEFAULT NULL,
  `diskformat` varchar(10) DEFAULT NULL,
  `diskcache` varchar(20) DEFAULT NULL,
  `disktype` varchar(20) DEFAULT NULL,
  `storage` varchar(20) DEFAULT 'local',
  `diskio` varchar(20) DEFAULT '0',
  `netmode` varchar(10) DEFAULT NULL,
  `bridge` varchar(20) NOT NULL DEFAULT 'vmbr',
  `vmbr` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `netmodel` varchar(10) DEFAULT NULL,
  `netrate` varchar(5) DEFAULT '0',
  `firewall` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `bw` int(6) unsigned DEFAULT '0',
  `kvm` tinyint(1) unsigned DEFAULT '0',
  `onboot` tinyint(1) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `mod_pvewhmcs_vms` (
  `id` int(10) unsigned NOT NULL,
  `vmid` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vtype` varchar(255) NOT NULL,
  `ipaddress` varchar(255) NOT NULL,
  `subnetmask` varchar(255) NOT NULL,
  `gateway` varchar(255) NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
);