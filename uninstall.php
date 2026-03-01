<?php
/**
 * Teams BLF Sync module - uninstall
 * Drops DB table and removes module config. Does NOT delete app directory or .env.
 */

if (!defined('FREEPBX_IS_A_BOOT') && !defined('FREEPBX_IS_AUTH')) {
    die('Direct access not permitted');
}

$db = FreePBX::Database();
$table = 'teamsblf_extensions';

try {
    $db->query("DROP TABLE IF EXISTS `{$table}`");
    $db->query("DROP TABLE IF EXISTS `teamsblf_settings`");
} catch (Exception $e) {
}
