<?php
// ============================================
// EDIT.PHP - Event Editor for DFW Hash House Harriers
// Version 2.3
// ============================================

date_default_timezone_set('America/Chicago');

define('EDITPHP_VERSION', '2.3');
define('BACKUP_DIR', '../android/backups/');

// Include helper functions
require_once('edit_functions.php');

// Include twilight calculator
require_once('twilight.php');

// Include authentication (will exit if not logged in)
require_once('edit_auth.php');

// ============================================
// AJAX HANDLERS
// ============================================
if (isset($_GET['get_events_on_day'])) {
	$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
	$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
	$day = isset($_GET['day']) ? intval($_GET['day']) : date('j');
	
	header('Content-Type: application/json');
	$events = getEventsOnDay($year, $month, $day);
	echo json_encode($events);
	exit;
}

// ============================================
// INITIALIZE VARIABLES
// ============================================
$year = isset($_GET["year"]) ? intval($_GET["year"]) : date('Y');
$month = isset($_GET["month"]) ? intval($_GET["month"]) : date('n');
$day = isset($_GET["day"]) ? intval($_GET["day"]) : date('j');
$no = isset($_GET["no"]) ? intval($_GET["no"]) : 0;

$isNewEvent = ($no == 0 || (isset($_GET['action']) && $_GET['action'] == 'new'));
$isDuplicate = (isset($_GET['action']) && $_GET['action'] == 'duplicate');

// Handle duplication
if ($isDuplicate && isset($_GET['source_year']) && isset($_GET['source_month']) && isset($_GET['source_day']) && isset($_GET['source_no'])) {
	$sourceYear = intval($_GET['source_year']);
	$sourceMonth = intval($_GET['source_month']);
	$sourceDay = intval($_GET['source_day']);
	$sourceNo = intval($_GET['source_no']);
	
	$sourceFilename = sprintf("../android/%d-%02d.txt", $sourceYear, $sourceMonth);
	$sourceFile = fopen($sourceFilename, "r");
	if ($sourceFile) {
		$n = 0;
		$lastDay = "";
		while ($line = fgets($sourceFile, 8192)) {
			$tempData = explode("\t", $line);
			$d = isset($tempData[0]) ? $tempData[0] : '';
			
			if ($lastDay != $d) {
				$n = 1;
				$lastDay = $d;
			} else {
				$n++;
			}
			
			if ($d == $sourceDay && $n == $sourceNo) {
				$duplicateSourceData = $tempData;
				break;
			}
		}
		fclose($sourceFile);
	}
	
	$year = date('Y');
	$month = date('n');
	$day = date('j');
	$no = 0;
	$isNewEvent = true;
}

$message = "";
$messageType = "";
$backupCreated = "";

// ============================================
// EDIT LOCK HANDLING
// ============================================
$editLockWarning = '';
$currentLock = null;

if (isset($_GET['refresh_lock']) && !$isNewEvent) {
	header('Content-Type: application/json');
	$refreshed = refreshEditLock($year, $month, $day, $no, $_SESSION['username']);
	echo json_encode(array('success' => $refreshed, 'remaining' => LOCK_TIMEOUT));
	exit;
}

if (isset($_GET['release_lock']) && !$isNewEvent) {
	$currentLock = getEditLock($year, $month, $day, $no);
	if ($currentLock && $currentLock['user'] === $_SESSION['username']) {
		releaseEditLock($year, $month, $day, $no);
	}
	header('Location: $' . sprintf('%02d-%d.php', $month, $year));
	exit;
}

if (!$isNewEvent) {
	$currentLock = getEditLock($year, $month, $day, $no);
	
	if ($currentLock && $currentLock['user'] !== $_SESSION['username']) {
		$editLockWarning = sprintf(
			'⚠️ Currently being edited by <strong>%s</strong> (started %s)',
			htmlspecialchars($currentLock['user']),
			htmlspecialchars($currentLock['timestamp'])
		);
	} else {
		acquireEditLock($year, $month, $day, $no, $_SESSION['username']);
	}
}

// Get available icons and kennel defaults
$availableIcons = getIconFiles($year);
$kennelDefaults = loadKennelDefaults();

// Include form handlers (icon upload, delete, save)
require_once('edit_handlers.php');

// ============================================
// LOAD EVENT DATA
// ============================================
$data = array('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '');
$eventsOnThisDay = 0;

if ($isDuplicate && isset($duplicateSourceData)) {
	$data = $duplicateSourceData;
	$data[4] = '';
} else if (!$isNewEvent) {
	$filename = sprintf("../android/%d-%02d.txt", $year, $month);
	$eventsOnThisDay = countEventsOnDay($filename, $day);
	
	$file = fopen($filename, "r");
	if (!$file) {
		echo "<p>Unable to open file.</p>";
		exit;
	}
	
	$n = 0;
	$lastDay = "";
	
	while ($line = fgets($file, 8192)) {
		$tempData = explode("\t", $line);
		$d = isset($tempData[0]) ? $tempData[0] : '';
		
		if ($d != $lastDay) {
			$n = 1;
		} else {
			$n += 1;
		}
		$lastDay = $d;
		
		if ($d == $day && $n == $no) {
			$data = $tempData;
			break;
		}
	}
	fclose($file);
	
	if (get_magic_quotes_gpc()) {
		$data = stripslashes_deep($data);
	}
	
	$kennelEvents = findKennelEvents($filename, $day, $no, isset($data[1]) ? trim($data[1]) : '');
}

// Extract current values
$kennel = isset($data[1]) ? $data[1] : '';
$dateDisplay = isset($data[13]) && strlen($data[13]) > 0 ? $data[13] : generateDateString($day, $month, $year);
$currentIcon = isset($data[2]) ? trim($data[2]) : '';
$currentTurds = isset($data[10]) ? trim($data[10]) : '';
$currentRsvp = isset($data[16]) ? trim($data[16]) : '';

$daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

$pageTitle = $isNewEvent ? "Add New Event" : "Edit Event";
$formTitle = $isNewEvent ? "Add New Event" : "Edit Event";

$hasPrev = (!$isNewEvent && $no > 1);
$hasNext = (!$isNewEvent && $no < $eventsOnThisDay);
$prevNo = $no - 1;
$nextNo = $no + 1;

// Publication status
$publicationStatus = '';
if ($isNewEvent) {
	$publicationStatus = '<span style="color: #856404;">📝 Unpublished (new)</span>';
} else {
	if (isset($data[15]) && !empty($data[15])) {
		$updateTime = htmlspecialchars($data[15]);
		$publicationStatus = '<span style="color: #0c5460;">✓ Published - Edited on ' . $updateTime . '</span>';
	} else {
		$publicationStatus = '<span style="color: #155724;">✓ Published</span>';
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta name="HandheldFriendly" content="true" />
	<meta name="MobileOptimized" content="320" />
	<meta name="Viewport" content="width=device-width" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta http-equiv="pragma" content="no-cache" />

	<link href="desktop.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="mobile.css" rel="stylesheet" type="text/css" media="(max-width:400px)" />
	
	<style>
		.edit-form { max-width: 800px; margin: 20px auto; padding: 20px; }
		.form-group { margin-bottom: 15px; }
		.form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
		.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; }
		.form-group textarea { min-height: 100px; }
		.btn { padding: 10px 20px; margin: 5px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
		.btn-save { background: #4CAF50; color: white; border: none; }
		.btn-cancel { background: #f44336; color: white; border: none; }
		.btn-logout { background: #666; color: white; border: none; float: right; }
		.btn-new { background: #2196F3; color: white; border: none; }
		.btn-delete { background: #dc3545; color: white; border: none; }
		.btn-duplicate { background: #17a2b8; color: white; border: none; }
		.btn-recover { background: #ff9800; color: white; border: none; }
		.btn-nav { background: #6c757d; color: white; border: none; padding: 8px 15px; font-size: 14px; }
		.btn-nav:hover { background: #5a6268; }
		.btn-nav-disabled { background: #ccc; color: #666; cursor: not-allowed; }
		.success { color: green; padding: 10px; background: #d4edda; margin-bottom: 15px; border-radius: 3px; }
		.error { color: red; padding: 10px; background: #f8d7da; margin-bottom: 15px; border-radius: 3px; }
		.info { color: blue; padding: 10px; background: #d1ecf1; margin-bottom: 15px; border-radius: 3px; }
		.header { overflow: auto; margin-bottom: 20px; }
		.backup-info { font-size: 12px; color: #666; margin-top: 5px; }
		.user-info { float: left; color: #666; font-size: 14px; margin-top: 10px; }
		.nav-links { margin-bottom: 15px; }
		.nav-links a { color: #0066cc; text-decoration: none; margin-right: 15px; }
		.icon-preview { display: inline-block; vertical-align: middle; margin-left: 10px; max-height: 30px; }
		.date-selectors { display: flex; gap: 10px; }
		.date-selectors select { width: auto; flex: 1; }
		.new-event-banner { background: #e3f2fd; border: 1px solid #2196F3; color: #1565c0; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
		.event-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 3px; }
		.event-nav-info { font-size: 14px; color: #666; }
		.action-buttons { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; }
		.delete-section { margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 3px; }
		.delete-section h4 { margin-top: 0; color: #856404; }
		.checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 3px; }
		.checkbox-label { display: flex; align-items: center; font-weight: normal; cursor: pointer; }
		.checkbox-label input[type="checkbox"] { width: auto; margin-right: 6px; }
		.kennel-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 10px; background: #e8f4e8; border: 1px solid #4CAF50; border-radius: 3px; }
		.kennel-nav-info { font-size: 14px; color: #2e7d32; font-weight: bold; }
	</style>
	
	<script>
	var formChanged = false;
	var sessionTimeout = <?php echo LOCK_TIMEOUT; ?>;
	var sessionStart = <?php echo time(); ?>;
	var isNewEvent = <?php echo $isNewEvent ? 'true' : 'false'; ?>;
	var kennelDefaults = <?php echo json_encode($kennelDefaults); ?>;
	
	function updateSessionTimer() {
		var elapsed = Math.floor(Date.now() / 1000) - sessionStart;
		var remaining = sessionTimeout - elapsed;
		
		if (remaining <= 0) {
			document.getElementById('sessionTimer').innerHTML = '<span style="color: #d9534f;">Session expired - please refresh</span>';
			return;
		}
		
		var minutes = Math.floor(remaining / 60);
		var seconds = remaining % 60;
		var display = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
		
		var timerEl = document.getElementById('sessionTimer');
		if (timerEl) {
			if (remaining < 300) {
				timerEl.innerHTML = '<span style="color: #d9534f;">⏱️ ' + display + '</span>';
			} else {
				timerEl.innerHTML = '⏱️ ' + display;
			}
		}
	}
	
	function refreshLock() {
		if (isNewEvent) return;
		var xhr = new XMLHttpRequest();
		xhr.open('GET', window.location.pathname + '?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>&refresh_lock=1', true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4 && xhr.status === 200) {
				sessionStart = Math.floor(Date.now() / 1000);
			}
		};
		xhr.send();
	}
	
	function updateIconPreview() {
		var select = document.getElementById('iconSelect');
		var preview = document.getElementById('iconPreview');
		var year = preview.getAttribute('data-year');
		if (select.value) {
			preview.src = year + '/' + select.value;
			preview.style.display = 'inline-block';
		} else {
			preview.style.display = 'none';
		}
	}
	
	function updateKennelSelection() {
		var kennelSelect = document.getElementById('kennelSelect');
		var iconSelect = document.getElementById('iconSelect');
		var selectedOption = kennelSelect.options[kennelSelect.selectedIndex];
		var selectedKennel = kennelSelect.value;
		
		if (!selectedKennel) return;
		
		// Auto-select default icon if available
		var defaultIcon = selectedOption.getAttribute('data-icon');
		if (defaultIcon) {
			for (var i = 0; i < iconSelect.options.length; i++) {
				if (iconSelect.options[i].value === defaultIcon) {
					iconSelect.selectedIndex = i;
					updateIconPreview();
					break;
				}
			}
		}
		
		// Check for defaults in defaults.txt and offer to load them
		if (selectedKennel && kennelDefaults[selectedKennel]) {
			var defaults = kennelDefaults[selectedKennel];
			var hasDefaults = [];
			
			if (defaults.icon) hasDefaults.push('Icon');
			if (defaults.time) hasDefaults.push('Time');
			if (defaults.start) hasDefaults.push('Address');
			if (defaults.map) hasDefaults.push('Map');
			if (defaults.hashcash) hasDefaults.push('Hash Cash');
			if (defaults.turds) hasDefaults.push('TURDs');
			if (defaults.desc) hasDefaults.push('Description');
			
			if (hasDefaults.length > 0) {
				var loadDefaults = confirm(
					'Load defaults for ' + selectedKennel + '?\n\n' +
					'Available defaults: ' + hasDefaults.join(', ') + '\n\n' +
					'Click OK to load defaults into empty fields, Cancel to skip.'
				);
				
				if (loadDefaults) {
					applyKennelDefaults(defaults);
				}
			}
		}
	}
	
	function applyKennelDefaults(defaults) {
		var iconSelect = document.getElementById('iconSelect');
		
		if (defaults.icon) {
			for (var i = 0; i < iconSelect.options.length; i++) {
				if (iconSelect.options[i].value === defaults.icon) {
					iconSelect.selectedIndex = i;
					updateIconPreview();
					break;
				}
			}
		}
		
		if (defaults.time) {
			var timeInput = document.querySelector('input[name="time"]');
			if (timeInput && (!timeInput.value || timeInput.value === '7:00 PM')) {
				timeInput.value = defaults.time;
			}
		}
		
		if (defaults.start) {
			var addressInput = document.getElementById('addressField');
			if (addressInput && !addressInput.value.trim()) {
				addressInput.value = defaults.start.replace(/<br\s*\/?>/gi, '\n');
			}
		}
		
		if (defaults.map) {
			var mapInput = document.getElementById('maplinkInput');
			if (mapInput && !mapInput.value.trim()) {
				mapInput.value = defaults.map;
			}
		}
		
		if (defaults.hashcash) {
			var hashcashInput = document.querySelector('input[name="hashcash"]');
			if (hashcashInput && !hashcashInput.value.trim()) {
				hashcashInput.value = defaults.hashcash;
			}
		}
		
		if (defaults.turds) {
			var turdsSelect = document.querySelector('select[name="turds"]');
			if (turdsSelect && !turdsSelect.value) {
				for (var i = 0; i < turdsSelect.options.length; i++) {
					if (turdsSelect.options[i].value === defaults.turds) {
						turdsSelect.selectedIndex = i;
						break;
					}
				}
			}
		}
		
		if (defaults.desc) {
			var descInput = document.querySelector('textarea[name="desc"]');
			if (descInput && !descInput.value.trim()) {
				descInput.value = defaults.desc.replace(/<br\s*\/?>/gi, '\n');
			}
		}
		
		markChanged();
	}
	
	function updateDatePreview() {
		var monthSelect = document.getElementById('monthSelect');
		var daySelect = document.getElementById('daySelect');
		var yearSelect = document.getElementById('yearSelect');
		var dayOfWeekSpan = document.getElementById('dayOfWeek');
		var otherEventsSpan = document.getElementById('otherEvents');
		
		if (!monthSelect || !daySelect || !yearSelect) return;
		
		var month = parseInt(monthSelect.value);
		var day = parseInt(daySelect.value);
		var year = parseInt(yearSelect.value);
		
		var date = new Date(year, month - 1, day);
		var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		var dayName = days[date.getDay()];
		
		dayOfWeekSpan.textContent = 'This is a ' + dayName + '. ';
		
		var xhr = new XMLHttpRequest();
		xhr.open('GET', '?get_events_on_day=1&year=' + year + '&month=' + month + '&day=' + day, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4 && xhr.status === 200) {
				try {
					var events = JSON.parse(xhr.responseText);
					if (events.length === 0) {
						otherEventsSpan.innerHTML = 'No other events on this day.';
					} else {
						var count = events.length;
						var links = [];
						for (var i = 0; i < events.length; i++) {
							var evt = events[i];
							var url = year + '/event.php?year=' + year + '&month=' + month + '&day=' + day + '&no=' + evt.no;
							links.push('<a href="' + url + '" target="_blank">' + evt.kennel + '</a>');
						}
						otherEventsSpan.innerHTML = count + ' Other Event' + (count > 1 ? 's' : '') + ': ' + links.join(', ');
					}
				} catch (e) {
					otherEventsSpan.textContent = '';
				}
			}
		};
		xhr.send();
	}
	
	function autoGenMapLink() {
		var addressField = document.getElementById('addressField');
		var maplinkField = document.getElementById('maplinkInput');
		
		if (!addressField || !maplinkField) {
			alert('Could not find address or map link field.');
			return;
		}
		
		var address = addressField.value.trim();
		
		if (!address) {
			alert('Please enter an address first.');
			addressField.focus();
			return;
		}
		
		address = address.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
		var googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address);
		maplinkField.value = googleMapsUrl;
		formChanged = true;
		
		maplinkField.style.background = '#d4edda';
		setTimeout(function() { maplinkField.style.background = ''; }, 1000);
	}
	
	function confirmDelete() {
		return confirm('Are you sure you want to delete this event?\n\nThis action cannot be undone (but a backup will be created).');
	}
	
	function confirmRecovery() {
		var message = '⚠️ EVENT RECOVERY TOOL\n\nThis tool is designed to restore events that have been:\n• Accidentally deleted\n• Accidentally reverted to an earlier version\n• Lost due to editing errors\n\nDO NOT USE if:\n• You are just browsing\n• The event data is currently correct\n• You want to modify an existing event (use Edit instead)\n\nContinue to Event Recovery?';
		if (confirm(message)) {
			formChanged = false;
			return true;
		}
		return false;
	}
	
	function markChanged() { formChanged = true; }
	function allowLeave() { formChanged = false; return true; }
	
	window.onbeforeunload = function(e) {
		if (formChanged) {
			var message = 'You have unsaved changes. Are you sure you want to leave?';
			e.returnValue = message;
			return message;
		}
	};
	
	window.onload = function() {
		setInterval(updateSessionTimer, 1000);
		updateSessionTimer();
		
		if (!isNewEvent) {
			setInterval(refreshLock, 300000);
		}
		
		var form = document.getElementById('editForm');
		if (form) {
			var inputs = form.querySelectorAll('input, textarea, select');
			for (var i = 0; i < inputs.length; i++) {
				inputs[i].addEventListener('change', markChanged);
				inputs[i].addEventListener('keyup', markChanged);
			}
		}
		
		var monthSelect = document.getElementById('monthSelect');
		var daySelect = document.getElementById('daySelect');
		var yearSelect = document.getElementById('yearSelect');
		
		if (monthSelect && daySelect && yearSelect) {
			monthSelect.addEventListener('change', updateDatePreview);
			daySelect.addEventListener('change', updateDatePreview);
			yearSelect.addEventListener('change', updateDatePreview);
			updateDatePreview();
		}
	};
	</script>

	<title><?php echo $pageTitle; ?> - <?php echo $month; ?>/<?php echo $day; ?>/<?php echo $year; ?></title>
</head>
<body>
	<div id="container" class="edit-form">
		<div class="nav-links">
			<a href="/calendar">&laquo; Back to Calendar</a>
			<?php if (!$isNewEvent): ?>
			| <a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&action=new">➕ Add New Event on <?php echo $month; ?>/<?php echo $day; ?></a>
			<?php endif; ?>
			| <a href="kenneldefaults.php">🏠 Kennel Defaults</a>
		</div>
		
		<div class="header">
			<div class="user-info">
				Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
				<span id="sessionTimer" style="margin-left: 15px; color: #666;"></span>
			</div>
			<h1 style="clear: both;"><?php echo $formTitle; ?></h1>
			<a href="?logout=1" class="btn btn-logout">Logout</a>
		</div>
		
		<?php if (!empty($editLockWarning)): ?>
		<div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
			<?php echo $editLockWarning; ?>
			<br><small>You can still edit, but be aware your changes may conflict with theirs.</small>
		</div>
		<?php endif; ?>
		
		<?php if (!empty($publicationStatus)): ?>
		<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-size: 14px;">
			<strong>Publication Status:</strong> <?php echo $publicationStatus; ?>
		</div>
		<?php endif; ?>
		
		<?php if ($isNewEvent): ?>
		<div class="new-event-banner">
			📅 Creating a new event for <strong><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></strong>
		</div>
		<?php else: ?>
		<h2><?php echo htmlspecialchars($dateDisplay); ?></h2>
		
		<?php if ($eventsOnThisDay > 1): ?>
		<div class="event-nav">
			<div>
				<?php if ($hasPrev): ?>
				<a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $prevNo; ?>" class="btn btn-nav">◀ Prev Event</a>
				<?php else: ?>
				<span class="btn btn-nav btn-nav-disabled">◀ Prev Event</span>
				<?php endif; ?>
			</div>
			<div class="event-nav-info">Event <?php echo $no; ?> of <?php echo $eventsOnThisDay; ?> on this day</div>
			<div>
				<?php if ($hasNext): ?>
				<a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $nextNo; ?>" class="btn btn-nav">Next Event ▶</a>
				<?php else: ?>
				<span class="btn btn-nav btn-nav-disabled">Next Event ▶</span>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
		
		<?php if (isset($kennelEvents) && ($kennelEvents['prev'] || $kennelEvents['next'])): ?>
		<div class="kennel-nav">
			<div>
				<?php if ($kennelEvents['prev']): ?>
				<a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $kennelEvents['prev']['day']; ?>&no=<?php echo $kennelEvents['prev']['no']; ?>" class="btn btn-nav">◀ Prev <?php echo htmlspecialchars($kennel); ?></a>
				<?php else: ?>
				<span class="btn btn-nav btn-nav-disabled">◀ Prev <?php echo htmlspecialchars($kennel); ?></span>
				<?php endif; ?>
			</div>
			<div class="kennel-nav-info"><?php echo htmlspecialchars($kennel); ?> Events</div>
			<div>
				<?php if ($kennelEvents['next']): ?>
				<a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $kennelEvents['next']['day']; ?>&no=<?php echo $kennelEvents['next']['no']; ?>" class="btn btn-nav">Next <?php echo htmlspecialchars($kennel); ?> ▶</a>
				<?php else: ?>
				<span class="btn btn-nav btn-nav-disabled">Next <?php echo htmlspecialchars($kennel); ?> ▶</span>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
		<?php endif; ?>
		
		<?php if ($message): ?>
			<div class="<?php echo $messageType; ?>">
				<?php echo $message; ?>
				<?php if ($backupCreated): ?><div class="backup-info"><?php echo $backupCreated; ?></div><?php endif; ?>
			</div>
		<?php endif; ?>
		
		<?php if (!$message && file_exists(BACKUP_DIR)): ?>
			<div class="info">ℹ️ Automatic backup will be created before saving changes.</div>
		<?php endif; ?>
		
		<?php include('edit_form.php'); ?>
	</div>
</body>
</html>