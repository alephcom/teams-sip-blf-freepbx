/**
 * Teams BLF Sync - page JS
 * AJAX: getStatus, getExtensions, save settings, add/edit/delete extension, sync from FreePBX, download binary.
 */
(function($) {
	'use strict';

	var baseUrl = 'config.php?display=teamsblf&';

	function ajax(command, data) {
		data = data || {};
		data.command = command;
		return $.get(baseUrl, data);
	}

	function post(data) {
		return $.post(baseUrl, data);
	}

	function refreshStatus() {
		ajax('getStatus').done(function(res) {
			var $s = $('#teamsblf-status');
			if (res.installed) {
				$s.html('<div class="alert alert-success">teams-sip-blf is installed: ' + (res.path || '') + '</div>');
			} else {
				$s.html('<div class="alert alert-warning">teams-sip-blf is not installed. Use the Download app button below or install manually from <a href="https://github.com/alephcom/teams-sip-blf/releases" target="_blank">GitHub releases</a> (e.g. sip-blf-sync-linux-amd64), place in /var/lib/asterisk/teams-sip-blf/ as sip-blf-sync, chmod +x, then run from that directory.</div>');
			}
		});
	}

	function refreshTable() {
		ajax('getExtensions').done(function(rows) {
			var $tbody = $('#teamsblf-extensions-table tbody');
			$tbody.empty();
			rows.forEach(function(e) {
				var editBtn = e.id ? '<button type="button" class="btn btn-xs btn-edit" data-id="' + e.id + '" data-extension="' + (e.extension || '') + '" data-email="' + (e.email || '') + '">Edit</button> ' : '';
				var delBtn = e.id ? '<button type="button" class="btn btn-xs btn-danger btn-delete" data-id="' + e.id + '">Delete</button>' : '';
				$tbody.append(
					$('<tr></tr>').append(
						$('<td></td>').text(e.extension || ''),
						$('<td></td>').text(e.name || ''),
						$('<td></td>').text(e.voicemail_email || ''),
						$('<td></td>').text(e.email || ''),
						$('<td></td>').html(editBtn + delBtn)
					)
				);
			});
		});
	}

	function openAddModal() {
		$('#teamsblf-modal-title').text('Add extension');
		$('#teamsblf-edit-id').val('');
		$('#teamsblf-edit-extension').val('').prop('readonly', false);
		$('#teamsblf-edit-email').val('');
		$('#teamsblf-edit-modal').modal('show');
	}

	function openEditModal(id, extension, email) {
		$('#teamsblf-modal-title').text('Edit extension');
		$('#teamsblf-edit-id').val(id);
		$('#teamsblf-edit-extension').val(extension).prop('readonly', true);
		$('#teamsblf-edit-email').val(email || '');
		$('#teamsblf-edit-modal').modal('show');
	}

	$(document).ready(function() {
		// Download app
		$(document).on('click', '#teamsblf-download-app', function() {
			var $btn = $(this);
			$btn.prop('disabled', true);
			ajax('downloadBinary').done(function(res) {
				if (res.ok) {
					alert('Downloaded to ' + (res.path || ''));
					refreshStatus();
					location.reload();
				} else {
					alert('Error: ' + (res.error || 'Unknown'));
				}
			}).fail(function() {
				alert('Request failed');
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// Sync from FreePBX
		$(document).on('click', '#teamsblf-sync-freepbx', function() {
			post({ action: 'syncFromFreePBX' }).done(function() {
				refreshTable();
			});
		});

		// Add extension
		$(document).on('click', '#teamsblf-add-extension', function() {
			openAddModal();
		});

		// Edit (delegate)
		$(document).on('click', '.btn-edit', function() {
			var id = $(this).data('id');
			var ext = $(this).data('extension');
			var email = $(this).data('email');
			openEditModal(id, ext, email);
		});

		// Delete (delegate)
		$(document).on('click', '.btn-delete', function() {
			if (!confirm('Delete this extension?')) return;
			var id = $(this).data('id');
			post({ action: 'deleteExtension', id: id }).done(function() {
				refreshTable();
			});
		});

		// Save add/edit
		$(document).on('click', '#teamsblf-save-edit', function() {
			var id = $('#teamsblf-edit-id').val();
			var extension = $('#teamsblf-edit-extension').val().trim();
			var email = $('#teamsblf-edit-email').val().trim();
			if (!email || email.indexOf('@') === -1) {
				alert('Please enter a valid email.');
				return;
			}
			if (id) {
				post({ action: 'editExtension', id: id, extension: extension, email: email }).done(function() {
					$('#teamsblf-edit-modal').modal('hide');
					refreshTable();
				});
			} else {
				if (!extension) {
					alert('Please enter extension.');
					return;
				}
				post({ action: 'addExtension', extension: extension, email: email }).done(function() {
					$('#teamsblf-edit-modal').modal('hide');
					refreshTable();
				});
			}
		});

		// Save settings
		$('#teamsblf-settings-form').on('submit', function(e) {
			e.preventDefault();
			var data = { action: 'saveSettings' };
			$(this).find('input[name]').each(function() {
				data[$(this).attr('name')] = $(this).val();
			});
			post(data).done(function() {
				alert('Settings saved. extensions.json and .env written to app directory.');
			});
		});
	});
})(jQuery);
