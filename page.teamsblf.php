<?php
if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}
$module = \FreePBX::Teamsblf();
echo $module->showPage();
?>
<script src="modules/teamsblf/assets/js/page.teamsblf.js"></script>
