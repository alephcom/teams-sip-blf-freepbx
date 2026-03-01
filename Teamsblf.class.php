<?php
/**
 * Teams BLF Sync - BMO class
 * Manages extension→email list, reads from FreePBX, writes .env and extensions.json, download binary.
 */
namespace FreePBX\modules;

use BMO;
use PDO;
use Exception;

class Teamsblf implements BMO {

	private $db;
	private $FreePBX;
	private $settingsTable = 'teamsblf_settings';
	private $extTable = 'teamsblf_extensions';
	private $settingKeys = array(
		'app_directory', 'SIP_SERVER', 'SIP_TRANSPORT', 'SIP_USERNAME', 'SIP_PASSWORD',
		'SIP_CONTACT_IP', 'STUN_SERVERS', 'AZURE_TENANT_ID', 'AZURE_CLIENT_ID', 'AZURE_CLIENT_SECRET'
	);

	public function __construct($freepbx = null) {
		if ($freepbx === null) {
			throw new Exception('FreePBX object required');
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
	}

	public function getConfig($key) {
		$sth = $this->db->prepare("SELECT `v` FROM `{$this->settingsTable}` WHERE `k` = ?");
		$sth->execute(array($key));
		$row = $sth->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['v'] : null;
	}

	public function setConfig($key, $value) {
		$sth = $this->db->prepare("INSERT INTO `{$this->settingsTable}` (`k`, `v`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)");
		$sth->execute(array($key, (string)$value));
	}

	public function doConfigPageInit($page) {
		$request = $_REQUEST;
		// AJAX commands (return JSON)
		if (!empty($request['command'])) {
			header('Content-Type: application/json');
			switch ($request['command']) {
				case 'getStatus':
					echo json_encode($this->getInstallStatus());
					exit;
				case 'getExtensions':
					echo json_encode($this->getExtensionsWithFreePBXData());
					exit;
				case 'getSettings':
					echo json_encode($this->getAllSettings());
					exit;
				case 'downloadBinary':
					echo json_encode($this->downloadBinary());
					exit;
			}
		}
		if (isset($request['action']) && $request['action'] === 'saveSettings') {
			foreach ($this->settingKeys as $k) {
				if (isset($request[$k])) {
					$this->setConfig($k, $request[$k]);
				}
			}
			$this->writeExtensionsJson();
			$this->writeEnv();
		}
		if (isset($request['action']) && $request['action'] === 'addExtension' && !empty($request['extension']) && !empty($request['email'])) {
			$this->addExtension($request['extension'], $request['email']);
			$this->writeExtensionsJson();
		}
		if (isset($request['action']) && $request['action'] === 'editExtension' && isset($request['id'])) {
			$this->updateExtension((int)$request['id'], isset($request['extension']) ? $request['extension'] : '', isset($request['email']) ? $request['email'] : '');
			$this->writeExtensionsJson();
		}
		if (isset($request['action']) && $request['action'] === 'deleteExtension' && isset($request['id'])) {
			$this->deleteExtension((int)$request['id']);
			$this->writeExtensionsJson();
		}
		if (isset($request['action']) && $request['action'] === 'syncFromFreePBX') {
			$this->syncFromFreePBX();
			$this->writeExtensionsJson();
		}
	}

	public function install() {}
	public function uninstall() {}

	public function getActionBar($request) {
		return array();
	}

	public function getRightNav($request) {
		return '';
	}

	public function showPage() {
		$status = $this->getInstallStatus();
		$settings = $this->getAllSettings();
		$extensions = $this->getExtensionsWithFreePBXData();
		$statusHtml = $status['installed']
			? '<div class="alert alert-success">' . _('teams-sip-blf is installed') . ': ' . htmlspecialchars($status['path']) . '</div>'
			: '<div class="alert alert-warning">' . _('teams-sip-blf is not installed') . '. ' . _('Use the Download app button below or install manually from') . ' <a href="https://github.com/alephcom/teams-sip-blf/releases" target="_blank">GitHub releases</a> (' . _('e.g. sip-blf-sync-linux-amd64') . '), ' . _('place in') . ' /var/lib/asterisk/teams-sip-blf/ ' . _('as sip-blf-sync, chmod +x, then run from that directory') . '.</div>';
		$downloadBtn = '<button type="button" class="btn btn-primary" id="teamsblf-download-app">' . _('Download app') . '</button>';
		$syncBtn = '<button type="button" class="btn btn-default" id="teamsblf-sync-freepbx">' . _('Sync from FreePBX') . '</button>';
		$tableRows = '';
		foreach ($extensions as $e) {
			$id = $e['id'] ? (int)$e['id'] : '';
			$ext = htmlspecialchars($e['extension']);
			$name = htmlspecialchars($e['name']);
			$vm = htmlspecialchars($e['voicemail_email']);
			$email = htmlspecialchars($e['email']);
			$editBtn = $id ? '<button type="button" class="btn btn-xs btn-edit" data-id="' . $id . '" data-extension="' . $ext . '" data-email="' . $email . '">' . _('Edit') . '</button> ' : '';
			$delBtn = $id ? '<button type="button" class="btn btn-xs btn-danger btn-delete" data-id="' . $id . '">' . _('Delete') . '</button>' : '';
			$tableRows .= "<tr><td>{$ext}</td><td>{$name}</td><td>{$vm}</td><td>{$email}</td><td>{$editBtn}{$delBtn}</td></tr>";
		}
		$appDir = htmlspecialchars($settings['app_directory']);
		$sipServer = htmlspecialchars($settings['SIP_SERVER']);
		$sipTransport = htmlspecialchars($settings['SIP_TRANSPORT']);
		$sipUser = htmlspecialchars($settings['SIP_USERNAME']);
		$sipPass = htmlspecialchars($settings['SIP_PASSWORD']);
		$sipContact = htmlspecialchars($settings['SIP_CONTACT_IP']);
		$stun = htmlspecialchars($settings['STUN_SERVERS']);
		$tenant = htmlspecialchars($settings['AZURE_TENANT_ID']);
		$clientId = htmlspecialchars($settings['AZURE_CLIENT_ID']);
		$clientSecret = htmlspecialchars($settings['AZURE_CLIENT_SECRET']);
		ob_start();
		?>
		<div class="container-fluid">
			<h1><?php echo _('Teams BLF Sync'); ?></h1>
			<div id="teamsblf-status"><?php echo $statusHtml; ?></div>
			<?php if (!$status['installed']) { echo $downloadBtn; } ?>
			<hr/>
			<h2><?php echo _('Extensions'); ?></h2>
			<?php echo $syncBtn; ?>
			<table class="table table-striped table-bordered" id="teamsblf-extensions-table">
				<thead>
					<tr>
						<th><?php echo _('Extension'); ?></th>
						<th><?php echo _('Name'); ?></th>
						<th><?php echo _('Voicemail email'); ?></th>
						<th><?php echo _('Configured email'); ?></th>
						<th><?php echo _('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php echo $tableRows; ?>
				</tbody>
			</table>
			<p><button type="button" class="btn btn-success" id="teamsblf-add-extension"><?php echo _('Add extension'); ?></button></p>
			<hr/>
			<h2><?php echo _('Settings'); ?></h2>
			<form id="teamsblf-settings-form" class="form-horizontal">
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php echo _('App directory'); ?></label>
					<div class="col-sm-6"><input type="text" class="form-control" name="app_directory" value="<?php echo $appDir; ?>" readonly /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">SIP_SERVER</label>
					<div class="col-sm-6"><input type="text" class="form-control" name="SIP_SERVER" value="<?php echo $sipServer; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">SIP_TRANSPORT</label>
					<div class="col-sm-6"><input type="text" class="form-control" name="SIP_TRANSPORT" value="<?php echo $sipTransport; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">SIP_USERNAME</label>
					<div class="col-sm-6"><input type="text" class="form-control" name="SIP_USERNAME" value="<?php echo $sipUser; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">SIP_PASSWORD</label>
					<div class="col-sm-6"><input type="password" class="form-control" name="SIP_PASSWORD" value="<?php echo $sipPass; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">SIP_CONTACT_IP</label>
					<div class="col-sm-6"><input type="text" class="form-control" name="SIP_CONTACT_IP" value="<?php echo $sipContact; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">STUN_SERVERS</label>
					<div class="col-sm-6"><input type="text" class="form-control" name="STUN_SERVERS" value="<?php echo $stun; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">AZURE_TENANT_ID</label>
					<div class="col-sm-6"><input type="text" class="form-control" name="AZURE_TENANT_ID" value="<?php echo $tenant; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">AZURE_CLIENT_ID</label>
					<div class="col-sm-6"><input type="text" class="form-control" name="AZURE_CLIENT_ID" value="<?php echo $clientId; ?>" /></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">AZURE_CLIENT_SECRET</label>
					<div class="col-sm-6"><input type="password" class="form-control" name="AZURE_CLIENT_SECRET" value="<?php echo $clientSecret; ?>" /></div>
				</div>
				<div class="form-group">
					<div class="col-sm-offset-2 col-sm-6">
						<button type="submit" class="btn btn-primary"><?php echo _('Save'); ?></button>
					</div>
				</div>
			</form>
		</div>
		<!-- Modal Add/Edit -->
		<div class="modal fade" id="teamsblf-edit-modal" tabindex="-1">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal">&times;</button>
						<h4 class="modal-title" id="teamsblf-modal-title"><?php echo _('Add extension'); ?></h4>
					</div>
					<div class="modal-body">
						<input type="hidden" id="teamsblf-edit-id" />
						<div class="form-group">
							<label>Extension</label>
							<input type="text" class="form-control" id="teamsblf-edit-extension" />
						</div>
						<div class="form-group">
							<label>Configured email (UPN)</label>
							<input type="text" class="form-control" id="teamsblf-edit-email" />
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
						<button type="button" class="btn btn-primary" id="teamsblf-save-edit"><?php echo _('Save'); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Extensions CRUD */
	public function getExtensions() {
		$sth = $this->db->query("SELECT `id`, `extension`, `email` FROM `{$this->extTable}` ORDER BY `extension`");
		return $sth ? $sth->fetchAll(PDO::FETCH_ASSOC) : array();
	}

	public function addExtension($extension, $email) {
		$extension = trim($extension);
		$email = trim($email);
		if ($extension === '' || $email === '' || strpos($email, '@') === false) {
			return false;
		}
		$sth = $this->db->prepare("INSERT INTO `{$this->extTable}` (`extension`, `email`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `email` = VALUES(`email`)");
		$sth->execute(array($extension, $email));
		return true;
	}

	public function updateExtension($id, $extension, $email) {
		$extension = trim($extension);
		$email = trim($email);
		if ($email === '' || strpos($email, '@') === false) {
			return false;
		}
		$sth = $this->db->prepare("UPDATE `{$this->extTable}` SET `extension` = ?, `email` = ? WHERE `id` = ?");
		$sth->execute(array($extension, $email, $id));
		return true;
	}

	public function deleteExtension($id) {
		$sth = $this->db->prepare("DELETE FROM `{$this->extTable}` WHERE `id` = ?");
		$sth->execute(array($id));
		return true;
	}

	/** FreePBX: list extensions with name and voicemail email */
	public function getFreePBXExtensions() {
		$list = array();
		try {
			// Try Core module first
			if (class_exists('FreePBX\modules\Core\Core')) {
				$core = $this->FreePBX->Core;
				if ($core && method_exists($core, 'getAllUsers')) {
					$users = $core->getAllUsers();
					foreach ($users as $u) {
						$ext = isset($u['extension']) ? $u['extension'] : (isset($u['id']) ? $u['id'] : null);
						if ($ext) {
							$list[$ext] = array(
								'extension' => $ext,
								'name' => isset($u['name']) ? $u['name'] : (isset($u['description']) ? $u['description'] : ''),
								'voicemail_email' => ''
							);
						}
					}
				}
			}
		} catch (Exception $e) {
		}

		if (empty($list)) {
			// Fallback: query users table if it exists
			try {
				$tables = $this->db->query("SHOW TABLES LIKE 'users'")->fetchAll(PDO::FETCH_COLUMN);
				if (!empty($tables)) {
					$sth = $this->db->query("SELECT extension, name FROM users");
					if ($sth) {
						while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
							$ext = isset($row['extension']) ? $row['extension'] : (isset($row['id']) ? $row['id'] : null);
							if ($ext) {
								$list[$ext] = array(
									'extension' => $ext,
									'name' => isset($row['name']) ? $row['name'] : '',
									'voicemail_email' => ''
								);
							}
						}
					}
				}
			} catch (Exception $e) {
			}
		}

		// Voicemail email: try voicemail.conf (format: ext => pass,name,email or ext=pass,name,email)
		$vmConf = '/etc/asterisk/voicemail.conf';
		if (file_exists($vmConf) && is_readable($vmConf)) {
			$content = file_get_contents($vmConf);
			$context = '';
			foreach (preg_split('/\r?\n/', $content) as $line) {
				$line = trim($line);
				if (preg_match('/^\[([^\]]+)\]$/', $line, $m)) {
					$context = $m[1];
					continue;
				}
				if (strtolower($context) === 'general') continue;
				// Match "1234 => pass,name,email" or "1234=pass,name,email"
				if (preg_match('/^([0-9]+)\s*=>?\s*(.+)$/', $line, $m)) {
					$ext = $m[1];
					$rest = trim($m[2]);
					if (substr($rest, 0, 1) === '>') {
						$rest = trim(substr($rest, 1));
					}
					$parts = array_map('trim', explode(',', $rest));
					$emailParts = isset($parts[2]) ? explode('|', $parts[2]) : array();
					$email = isset($emailParts[0]) ? trim($emailParts[0]) : '';
					if ($email !== '' && strpos($email, '@') !== false) {
						if (isset($list[$ext])) {
							$list[$ext]['voicemail_email'] = $email;
						} else {
							$list[$ext] = array('extension' => $ext, 'name' => '', 'voicemail_email' => $email);
						}
					}
				}
			}
		}

		return array_values($list);
	}

	/** Prepopulate: add rows from FreePBX; set configured email to voicemail email when adding new */
	public function syncFromFreePBX() {
		$existing = array();
		foreach ($this->getExtensions() as $e) {
			$existing[$e['extension']] = $e['email'];
		}
		$fpbx = $this->getFreePBXExtensions();
		foreach ($fpbx as $e) {
			$ext = $e['extension'];
			if (!isset($existing[$ext])) {
				$email = !empty($e['voicemail_email']) ? $e['voicemail_email'] : '';
				if ($email !== '') {
					$this->addExtension($ext, $email);
				}
			}
		}
		return true;
	}

	/** Build list for UI: extension, name, voicemail_email, configured email */
	public function getExtensionsWithFreePBXData() {
		$fpbx = array();
		foreach ($this->getFreePBXExtensions() as $e) {
			$fpbx[$e['extension']] = $e;
		}
		$ours = $this->getExtensions();
		$out = array();
		foreach ($ours as $o) {
			$ext = $o['extension'];
			$out[] = array(
				'id' => $o['id'],
				'extension' => $ext,
				'name' => isset($fpbx[$ext]['name']) ? $fpbx[$ext]['name'] : '',
				'voicemail_email' => isset($fpbx[$ext]['voicemail_email']) ? $fpbx[$ext]['voicemail_email'] : '',
				'email' => $o['email']
			);
		}
		// Add FreePBX extensions we don't have in our table yet (so UI can show them with empty configured email)
		foreach ($fpbx as $ext => $e) {
			$has = false;
			foreach ($ours as $o) {
				if ($o['extension'] === $ext) { $has = true; break; }
			}
			if (!$has) {
				$out[] = array(
					'id' => null,
					'extension' => $ext,
					'name' => $e['name'],
					'voicemail_email' => $e['voicemail_email'],
					'email' => ''
				);
			}
		}
		usort($out, function ($a, $b) { return strcmp($a['extension'], $b['extension']); });
		return $out;
	}

	public function writeExtensionsJson() {
		$dir = rtrim($this->getConfig('app_directory') ? $this->getConfig('app_directory') : '/var/lib/asterisk/teams-sip-blf', '/');
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		if (!is_writable($dir)) {
			return array('ok' => false, 'error' => 'Directory not writable');
		}
		$path = $dir . '/extensions.json';
		$rows = $this->getExtensions();
		$arr = array();
		foreach ($rows as $r) {
			$arr[] = array('extension' => $r['extension'], 'email' => $r['email']);
		}
		$flags = 0;
		if (defined('JSON_PRETTY_PRINT')) {
			$flags |= JSON_PRETTY_PRINT;
		}
		if (defined('JSON_UNESCAPED_SLASHES')) {
			$flags |= JSON_UNESCAPED_SLASHES;
		}
		$json = json_encode($arr, $flags);
		if (file_put_contents($path, $json, LOCK_EX) === false) {
			return array('ok' => false, 'error' => 'Write failed');
		}
		return array('ok' => true);
	}

	public function writeEnv() {
		$dir = rtrim($this->getConfig('app_directory') ? $this->getConfig('app_directory') : '/var/lib/asterisk/teams-sip-blf', '/');
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		if (!is_writable($dir)) {
			return array('ok' => false, 'error' => 'Directory not writable');
		}
		$extPath = $dir . '/extensions.json';
		$statePath = $dir . '/presence-state.json';
		$lines = array(
			'# Generated by Teams BLF Sync module',
			'SIP_SERVER=' . ($this->getConfig('SIP_SERVER') ? $this->getConfig('SIP_SERVER') : '192.168.1.1:5060'),
			'SIP_TRANSPORT=' . ($this->getConfig('SIP_TRANSPORT') ? $this->getConfig('SIP_TRANSPORT') : 'udp'),
			'SIP_USERNAME=' . ($this->getConfig('SIP_USERNAME') ? $this->getConfig('SIP_USERNAME') : ''),
			'SIP_PASSWORD=' . ($this->getConfig('SIP_PASSWORD') ? $this->getConfig('SIP_PASSWORD') : ''),
			'SIP_CONTACT_IP=' . ($this->getConfig('SIP_CONTACT_IP') ? $this->getConfig('SIP_CONTACT_IP') : '127.0.0.1'),
			'STUN_SERVERS=' . ($this->getConfig('STUN_SERVERS') ? $this->getConfig('STUN_SERVERS') : 'stun.l.google.com,stun2.l.google.com'),
			'AZURE_TENANT_ID=' . ($this->getConfig('AZURE_TENANT_ID') ? $this->getConfig('AZURE_TENANT_ID') : ''),
			'AZURE_CLIENT_ID=' . ($this->getConfig('AZURE_CLIENT_ID') ? $this->getConfig('AZURE_CLIENT_ID') : ''),
			'AZURE_CLIENT_SECRET=' . ($this->getConfig('AZURE_CLIENT_SECRET') ? $this->getConfig('AZURE_CLIENT_SECRET') : ''),
			'EXTENSIONS_JSON=' . $extPath,
			'PRESENCE_STATE_JSON=' . $statePath
		);
		$content = implode("\n", $lines) . "\n";
		if (file_put_contents($dir . '/.env', $content, LOCK_EX) === false) {
			return array('ok' => false, 'error' => 'Write .env failed');
		}
		return array('ok' => true);
	}

	/** Check if teams-sip-blf binary is installed */
	public function getInstallStatus() {
		$dir = rtrim($this->getConfig('app_directory') ? $this->getConfig('app_directory') : '/var/lib/asterisk/teams-sip-blf', '/');
		$paths = array($dir . '/sip-blf-sync', $dir . '/sip-blf-sync-linux-amd64');
		foreach ($paths as $p) {
			if (file_exists($p) && is_executable($p)) {
				return array('installed' => true, 'path' => $p);
			}
		}
		return array('installed' => false, 'dir' => $dir);
	}

	/** Download binary from GitHub releases */
	public function downloadBinary() {
		$dir = rtrim($this->getConfig('app_directory') ? $this->getConfig('app_directory') : '/var/lib/asterisk/teams-sip-blf', '/');
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		if (!is_writable($dir)) {
			return array('ok' => false, 'error' => 'Directory not writable');
		}
		$url = 'https://api.github.com/repos/alephcom/teams-sip-blf/releases/latest';
		$ctx = stream_context_create(array(
			'http' => array('header' => "User-Agent: FreePBX-TeamsBLF/0.0.1\r\n", 'timeout' => 30)
		));
		$json = @file_get_contents($url, false, $ctx);
		if ($json === false) {
			return array('ok' => false, 'error' => 'Failed to fetch release info');
		}
		$data = json_decode($json, true);
		if (!$data || empty($data['assets'])) {
			return array('ok' => false, 'error' => 'No release assets');
		}
		$assetUrl = null;
		foreach ($data['assets'] as $a) {
			if (isset($a['name']) && $a['name'] === 'sip-blf-sync-linux-amd64') {
				$assetUrl = isset($a['browser_download_url']) ? $a['browser_download_url'] : null;
				break;
			}
		}
		if (!$assetUrl) {
			return array('ok' => false, 'error' => 'Linux binary not found in release');
		}
		$bin = @file_get_contents($assetUrl, false, $ctx);
		if ($bin === false) {
			return array('ok' => false, 'error' => 'Download failed');
		}
		$path = $dir . '/sip-blf-sync';
		if (file_put_contents($path, $bin, LOCK_EX) === false) {
			return array('ok' => false, 'error' => 'Write failed');
		}
		@chmod($path, 0755);
		return array('ok' => true, 'path' => $path);
	}

	/** All settings for the form */
	public function getAllSettings() {
		$out = array();
		foreach ($this->settingKeys as $k) {
			$v = $this->getConfig($k);
			$out[$k] = ($v !== null && $v !== '') ? $v : '';
		}
		if (empty($out['app_directory'])) {
			$out['app_directory'] = '/var/lib/asterisk/teams-sip-blf';
		}
		return $out;
	}
}
