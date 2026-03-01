<?php
/**
 * Teams BLF Sync module - install
 * Creates DB table, app directory, default config.
 */

if (!defined('FREEPBX_IS_A_BOOT') && !defined('FREEPBX_IS_AUTH')) {
    die('Direct access not permitted');
}

$db = FreePBX::Database();
$table = 'teamsblf_extensions';
$settingsTable = 'teamsblf_settings';

// Create tables if not exist
$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `extension` VARCHAR(20) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `extension` (`extension`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
try {
    $db->query($sql);
} catch (Exception $e) {
}

$sql2 = "CREATE TABLE IF NOT EXISTS `{$settingsTable}` (
    `k` VARCHAR(64) NOT NULL,
    `v` VARCHAR(2048) DEFAULT NULL,
    PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
try {
    $db->query($sql2);
} catch (Exception $e) {
}

$appDir = '/var/lib/asterisk/teams-sip-blf';
if (!is_dir($appDir)) {
    @mkdir($appDir, 0755, true);
    if (function_exists('posix_getpwuid') && function_exists('posix_getpwnam')) {
        $www = @posix_getpwnam('www-data');
        if ($www) {
            @chown($appDir, $www['uid']);
            @chgrp($appDir, $www['gid']);
        }
    }
}

// Set default app directory in settings if not already set
try {
    $sth = $db->prepare("INSERT IGNORE INTO `{$settingsTable}` (`k`, `v`) VALUES (?, ?)");
    $sth->execute(array('app_directory', $appDir));
} catch (Exception $e) {
}
