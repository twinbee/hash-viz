<?php
// ============================================
// CHECKIN.PHP - Personal Check-in for DFW Hash House Harriers
// Version 1.2
// ============================================

// ============================================
// TIMEZONE CONFIGURATION
// ============================================
// Server may be in different timezone than events (e.g., Pacific vs Central)
// Set this to the timezone where events actually occur
define('EVENT_TIMEZONE', 'America/Chicago'); // Central Time for DFW
date_default_timezone_set(EVENT_TIMEZONE);

// Data directory for attendance records
define('ROLLCALL_DIR', '../../android/rollcall/');
define('HASHERS_FILE', 'hashers.txt');
define('COOKIE_NAME', 'dfw_hasher_name');
define('COOKIE_EXPIRY', 365 * 24 * 60 * 60); // 1 year

// Testing mode - bypasses time check
$TESTING_MODE = (isset($_GET['test']) && ($_GET['test'] == '1' || $_GET['test'] == 'onin'));

// ============================================
// SECURITY: Sanitize hasher names
// ============================================
function sanitizeHasherName($name) {
	$name = str_replace(chr(0), '', $name);
	$name = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $name);
	$name = preg_replace('/javascript\s*:/i', '', $name);
	$name = preg_replace('/\bon\w+\s*=/i', '', $name);
	$name = preg_replace('/<[^>]*>/', '', $name);
	$name = trim($name);
	return $name;
}

// ============================================
// HELPER: Get event info from data file
// ============================================
function getEventInfo($year, $month, $day, $no) {
	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
	if (!file_exists($filename)) {
		return null;
	}
	
	$file = fopen($filename, "r");
	if (!$file) {
		return null;
	}
	
	$n = 0;
	$lastDay = "";
	$data = null;
	
	while ($line = fgets($file, 8192)) {
		$lineData = explode("\t", $line);
		$d = isset($lineData[0]) ? $lineData[0] : '';
		
		if ($d != $lastDay) {
			$n = 1;
		} else {
			$n += 1;
		}
		$lastDay = $d;
		
		if ($d == $day && $n == $no) {
			$data = $lineData;
			break;
		}
	}
	fclose($file);
	
	return $data;
}

// ============================================
// HELPER: Parse event time to timestamp
// ============================================
function getEventTimestamp($year, $month, $day, $timeStr) {
	$timeStr = trim($timeStr);
	$timeStr = preg_replace('/\s*(CST|CDT)\s*/', '', $timeStr);
	
	$hour = 18;
	$minute = 0;
	
	if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', $timeStr, $matches)) {
		$hour = intval($matches[1]);
		$minute = intval($matches[2]);
		if (isset($matches[3]) && strtoupper($matches[3]) == 'PM' && $hour < 12) {
			$hour += 12;
		}
		if (isset($matches[3]) && strtoupper($matches[3]) == 'AM' && $hour == 12) {
			$hour = 0;
		}
	}
	
	return mktime($hour, $minute, 0, $month, $day, $year);
}

// ============================================
// HELPER: Get checkin window info
// ============================================
function getCheckinWindowInfo($year, $month, $day, $timeStr, $testingMode = false) {
	$eventTime = getEventTimestamp($year, $month, $day, $timeStr);
	$now = time();
	
	$windowStart = $eventTime - (10 * 60);
	$windowEnd = $eventTime + (4 * 60 * 60);
	
	$isOpen = $testingMode || ($now >= $windowStart && $now <= $windowEnd);
	
	return array(
		'eventTime' => $eventTime,
		'windowStart' => $windowStart,
		'windowEnd' => $windowEnd,
		'isOpen' => $isOpen,
		'isBefore' => !$testingMode && ($now < $windowStart),
		'isAfter' => !$testingMode && ($now > $windowEnd),
		'testingMode' => $testingMode
	);
}

// ============================================
// HELPER: Load hashers list
// ============================================
function loadHashers() {
	$file = ROLLCALL_DIR . HASHERS_FILE;
	if (!file_exists($file)) {
		return array();
	}
	
	$hashers = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$hashers = array_map('trim', $hashers);
	$hashers = array_filter($hashers);
	sort($hashers, SORT_STRING | SORT_FLAG_CASE);
	
	return $hashers;
}

// ============================================
// HELPER: Save hashers list
// ============================================
function saveHashers($hashers) {
	if (!file_exists(ROLLCALL_DIR)) {
		mkdir(ROLLCALL_DIR, 0755, true);
	}
	
	$hashers = array_unique($hashers);
	sort($hashers, SORT_STRING | SORT_FLAG_CASE);
	
	$file = ROLLCALL_DIR . HASHERS_FILE;
	file_put_contents($file, implode("\n", $hashers) . "\n", LOCK_EX);
}

// ============================================
// HELPER: Get attendance file path for an event
// ============================================
function getAttendanceFile($year, $month, $day, $no, $kennel) {
	$kennelSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kennel);
	return ROLLCALL_DIR . sprintf("%d-%02d-%02d_%d_%s.txt", $year, $month, $day, $no, $kennelSafe);
}

// ============================================
// HELPER: Load attendance for an event
// ============================================
function loadAttendance($year, $month, $day, $no, $kennel) {
	$file = getAttendanceFile($year, $month, $day, $no, $kennel);
	if (!file_exists($file)) {
		return array();
	}
	
	$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$attendance = array();
	foreach ($lines as $line) {
		$parts = explode("\t", $line);
		if (count($parts) >= 2) {
			$attendance[$parts[0]] = array(
				'timestamp' => $parts[1],
				'payment' => isset($parts[2]) ? $parts[2] : ''
			);
		}
	}
	
	return $attendance;
}

// ============================================
// HELPER: Save attendance for an event
// ============================================
function saveAttendance($year, $month, $day, $no, $kennel, $attendance) {
	if (!file_exists(ROLLCALL_DIR)) {
		mkdir(ROLLCALL_DIR, 0755, true);
	}
	
	$file = getAttendanceFile($year, $month, $day, $no, $kennel);
	
	$lines = array();
	foreach ($attendance as $name => $data) {
		if (is_array($data)) {
			$lines[] = $name . "\t" . $data['timestamp'] . "\t" . (isset($data['payment']) ? $data['payment'] : '');
		} else {
			$lines[] = $name . "\t" . $data . "\t";
		}
	}
	
	file_put_contents($file, implode("\n", $lines), LOCK_EX);
}

// ============================================
// TALLY SYSTEM - Maintains running totals
// ============================================
define('TALLY_FILE', 'tally.txt');

function loadTally() {
	$file = ROLLCALL_DIR . TALLY_FILE;
	if (!file_exists($file)) {
		return array();
	}
	
	$tally = array();
	$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		$parts = explode("\t", $line);
		if (count($parts) >= 2) {
			$name = $parts[0];
			$total = intval($parts[1]);
			$byKennel = array();
			
			if (isset($parts[2]) && !empty($parts[2])) {
				$kennelParts = explode(',', $parts[2]);
				foreach ($kennelParts as $kp) {
					$kv = explode(':', $kp);
					if (count($kv) == 2) {
						$byKennel[$kv[0]] = intval($kv[1]);
					}
				}
			}
			
			$tally[$name] = array(
				'total' => $total,
				'byKennel' => $byKennel
			);
		}
	}
	
	return $tally;
}

function saveTally($tally) {
	if (!file_exists(ROLLCALL_DIR)) {
		mkdir(ROLLCALL_DIR, 0755, true);
	}
	
	$file = ROLLCALL_DIR . TALLY_FILE;
	$lines = array();
	
	foreach ($tally as $name => $data) {
		$kennelStr = '';
		if (!empty($data['byKennel'])) {
			$kennelParts = array();
			foreach ($data['byKennel'] as $kennel => $count) {
				$kennelParts[] = $kennel . ':' . $count;
			}
			$kennelStr = implode(',', $kennelParts);
		}
		$lines[] = $name . "\t" . $data['total'] . "\t" . $kennelStr;
	}
	
	file_put_contents($file, implode("\n", $lines), LOCK_EX);
}

function updateTally($hasherName, $kennel, $increment = 1) {
	$tally = loadTally();
	
	if (!isset($tally[$hasherName])) {
		$tally[$hasherName] = array('total' => 0, 'byKennel' => array());
	}
	
	$tally[$hasherName]['total'] += $increment;
	
	// Sanitize kennel name for storage
	$kennelSafe = str_replace(array(':', ','), array('-', '-'), $kennel);
	
	if (!isset($tally[$hasherName]['byKennel'][$kennelSafe])) {
		$tally[$hasherName]['byKennel'][$kennelSafe] = 0;
	}
	$tally[$hasherName]['byKennel'][$kennelSafe] += $increment;
	
	// Ensure counts don't go negative
	if ($tally[$hasherName]['total'] < 0) {
		$tally[$hasherName]['total'] = 0;
	}
	if ($tally[$hasherName]['byKennel'][$kennelSafe] < 0) {
		$tally[$hasherName]['byKennel'][$kennelSafe] = 0;
	}
	
	saveTally($tally);
}

// ============================================
// HELPER: Get all-time stats for a hasher (from tally)
// ============================================
function getHasherStats($hasherName) {
	$tally = loadTally();
	
	if (isset($tally[$hasherName])) {
		return $tally[$hasherName];
	}
	
	return array('total' => 0, 'byKennel' => array());
}

// ============================================
// HELPER: Get top hashers leaderboard (from tally)
// ============================================
function getTopHashers($limit = 10) {
	$tally = loadTally();
	
	$counts = array();
	foreach ($tally as $name => $data) {
		if ($data['total'] > 0) {
			$counts[$name] = $data['total'];
		}
	}
	
	arsort($counts);
	return array_slice($counts, 0, $limit, true);
}

// ============================================
// HELPER: Check if name exists (case insensitive)
// ============================================
function hasherExists($name, $hashers) {
	$nameLower = strtolower(trim($name));
	foreach ($hashers as $hasher) {
		if (strtolower(trim($hasher)) == $nameLower) {
			return true;
		}
	}
	return false;
}

// ============================================
// HELPER: Find similar names using soundex/metaphone
// ============================================
function findSimilarNames($newName, $existingNames, $limit = 5) {
	$similar = array();
	$newSoundex = soundex($newName);
	$newMetaphone = metaphone($newName);
	$newLower = strtolower($newName);
	
	foreach ($existingNames as $existing) {
		$score = 0;
		$existingLower = strtolower($existing);
		
		// Exact match (case insensitive)
		if ($newLower == $existingLower) {
			$score = 100;
		}
		// Soundex match
		elseif (soundex($existing) == $newSoundex) {
			$score = 70;
		}
		// Metaphone match
		elseif (metaphone($existing) == $newMetaphone) {
			$score = 60;
		}
		// Substring match
		elseif (strpos($existingLower, $newLower) !== false || strpos($newLower, $existingLower) !== false) {
			$score = 50;
		}
		// Levenshtein distance for short names
		elseif (strlen($newName) < 20 && strlen($existing) < 20) {
			$distance = levenshtein($newLower, $existingLower);
			if ($distance <= 3) {
				$score = 40 - ($distance * 10);
			}
		}
		// First word match
		else {
			$newWords = explode(' ', $newLower);
			$existingWords = explode(' ', $existingLower);
			if ($newWords[0] == $existingWords[0] && strlen($newWords[0]) > 2) {
				$score = 30;
			}
		}
		
		if ($score > 0) {
			$similar[$existing] = $score;
		}
	}
	
	arsort($similar);
	return array_slice(array_keys($similar), 0, $limit);
}

// ============================================
// MAIN SCRIPT
// ============================================

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$day = isset($_GET['day']) ? intval($_GET['day']) : date('j');
$no = isset($_GET['no']) ? intval($_GET['no']) : 1;

// Get event info
$eventData = getEventInfo($year, $month, $day, $no);
if (!$eventData) {
	die("Event not found.");
}

$kennel = isset($eventData[1]) ? trim($eventData[1]) : 'Unknown';
$eventTitle = isset($eventData[3]) ? trim($eventData[3]) : '';
$eventTime = isset($eventData[6]) ? trim($eventData[6]) : '6:00 PM';
$eventDate = isset($eventData[13]) ? trim($eventData[13]) : '';

// Get checkin window info
$windowInfo = getCheckinWindowInfo($year, $month, $day, $eventTime, $TESTING_MODE);

// Load hashers and attendance
$hashers = loadHashers();
$attendance = loadAttendance($year, $month, $day, $no, $kennel);

// Get remembered hasher name from cookie
$rememberedName = isset($_COOKIE[COOKIE_NAME]) ? sanitizeHasherName($_COOKIE[COOKIE_NAME]) : '';

// Handle actions
$message = '';
$messageType = 'success';
$showSimilarNames = false;
$similarNames = array();
$pendingName = '';

// Payment options
$paymentOptions = array('', 'Cash', 'PayPal', 'Venmo', 'Zelle', 'Cash App');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	// Handle setting/changing name - initial request
	if (isset($_POST['set_name'])) {
		$newName = sanitizeHasherName($_POST['hasher_name']);
		if (!empty($newName)) {
			// Check if name already exists
			if (hasherExists($newName, $hashers)) {
				// Exact match - just set it
				setcookie(COOKIE_NAME, $newName, time() + COOKIE_EXPIRY, '/');
				$rememberedName = $newName;
				$message = "Welcome back, " . htmlspecialchars($newName) . "!";
			} else {
				// Check for similar names
				$similarNames = findSimilarNames($newName, $hashers);
				if (count($similarNames) > 0) {
					$showSimilarNames = true;
					$pendingName = $newName;
				} else {
					// No similar names, add directly
					setcookie(COOKIE_NAME, $newName, time() + COOKIE_EXPIRY, '/');
					$rememberedName = $newName;
					$hashers[] = $newName;
					saveHashers($hashers);
					$message = "Welcome, " . htmlspecialchars($newName) . "!";
				}
			}
		}
	}
	
	// Handle confirmed new name
	if (isset($_POST['confirm_name'])) {
		$newName = sanitizeHasherName($_POST['confirmed_name']);
		if (!empty($newName)) {
			setcookie(COOKIE_NAME, $newName, time() + COOKIE_EXPIRY, '/');
			$rememberedName = $newName;
			if (!hasherExists($newName, $hashers)) {
				$hashers[] = $newName;
				saveHashers($hashers);
			}
			$message = "Welcome, " . htmlspecialchars($newName) . "!";
		}
	}
	
	// Handle selecting existing similar name
	if (isset($_POST['use_existing'])) {
		$existingName = sanitizeHasherName($_POST['existing_name']);
		if (!empty($existingName) && hasherExists($existingName, $hashers)) {
			setcookie(COOKIE_NAME, $existingName, time() + COOKIE_EXPIRY, '/');
			$rememberedName = $existingName;
			$message = "Welcome back, " . htmlspecialchars($existingName) . "!";
		}
	}
	
	// Handle check-in
	if (isset($_POST['checkin']) && !empty($rememberedName)) {
		$paymentMethod = isset($_POST['payment']) ? $_POST['payment'] : '';
		
		if ($windowInfo['isOpen']) {
			if (!isset($attendance[$rememberedName])) {
				$attendance[$rememberedName] = array(
					'timestamp' => date('Y-m-d H:i:s'),
					'payment' => $paymentMethod
				);
				saveAttendance($year, $month, $day, $no, $kennel, $attendance);
				updateTally($rememberedName, $kennel, 1);
				$message = "You're checked in! 🎉";
			} else {
				// Update payment method
				$attendance[$rememberedName]['payment'] = $paymentMethod;
				saveAttendance($year, $month, $day, $no, $kennel, $attendance);
				$message = "Payment method updated!";
			}
		} else {
			$message = "Check-in window is not open.";
			$messageType = 'error';
		}
	}
	
	// Handle check-out
	if (isset($_POST['checkout']) && !empty($rememberedName)) {
		if ($windowInfo['isOpen'] && isset($attendance[$rememberedName])) {
			unset($attendance[$rememberedName]);
			saveAttendance($year, $month, $day, $no, $kennel, $attendance);
			updateTally($rememberedName, $kennel, -1);
			$message = "You've been checked out.";
		}
	}
	
	// Handle forget me
	if (isset($_POST['forget'])) {
		setcookie(COOKIE_NAME, '', time() - 3600, '/');
		$rememberedName = '';
		$message = "Your name has been cleared from this device.";
	}
}

// Check if user is checked in
$isCheckedIn = !empty($rememberedName) && isset($attendance[$rememberedName]);
$currentPayment = $isCheckedIn && is_array($attendance[$rememberedName]) ? $attendance[$rememberedName]['payment'] : '';

// Get user stats
$userStats = !empty($rememberedName) ? getHasherStats($rememberedName) : null;

// Head count
$headCount = count($attendance);

// Get top hashers for leaderboard
$topHashers = getTopHashers(5); // Show top 5 on personal page
$maxCount = count($topHashers) > 0 ? max($topHashers) : 1;
?>
<!DOCTYPE html>
<html>
<head>
	<meta name="HandheldFriendly" content="true" />
	<meta name="MobileOptimized" content="320" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Check In - <?php echo htmlspecialchars($kennel); ?></title>
	
	<!-- Google Font for script -->
	<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
	
	<style>
		* { box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			max-width: 500px;
			margin: 0 auto;
			padding: 15px;
			background: #f5f5f5;
		}
		.nav-links {
			margin-bottom: 15px;
		}
		.nav-links a {
			color: #4CAF50;
			text-decoration: none;
		}
		
		/* Name tag sticker style */
		.nametag {
			background: white;
			border: 4px solid #e53935;
			border-radius: 10px;
			padding: 0;
			margin-bottom: 20px;
			overflow: hidden;
			box-shadow: 0 4px 15px rgba(0,0,0,0.2);
		}
		.nametag-header {
			background: #e53935;
			color: white;
			text-align: center;
			padding: 15px;
			font-size: 1.4em;
			font-weight: bold;
			text-transform: uppercase;
			letter-spacing: 2px;
		}
		.nametag-body {
			background: white;
			padding: 20px;
			text-align: center;
			min-height: 120px;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.nametag-name {
			font-family: 'Dancing Script', cursive;
			font-size: 2.5em;
			color: #333;
			word-break: break-word;
		}
		.nametag-empty {
			color: #999;
			font-style: italic;
			font-size: 1.2em;
		}
		.nametag-footer {
			background: #e53935;
			height: 20px;
		}
		
		.event-info {
			background: #4CAF50;
			color: white;
			padding: 15px;
			border-radius: 5px;
			margin-bottom: 15px;
			text-align: center;
		}
		.event-info h2 {
			margin: 0 0 5px 0;
			font-size: 1.2em;
		}
		.event-info p {
			margin: 0;
			opacity: 0.9;
		}
		
		.window-status {
			padding: 15px;
			border-radius: 5px;
			margin-bottom: 15px;
			text-align: center;
			font-weight: bold;
		}
		.window-open {
			background: #c8e6c9;
			border: 2px solid #4CAF50;
			color: #2e7d32;
		}
		.window-closed {
			background: #ffcdd2;
			border: 2px solid #f44336;
			color: #c62828;
		}
		.window-test {
			background: #fff3e0;
			border: 2px solid #ff9800;
			color: #e65100;
		}
		
		.message {
			padding: 10px 15px;
			border-radius: 5px;
			margin-bottom: 15px;
			text-align: center;
		}
		.message.success {
			background: #c8e6c9;
			border: 1px solid #4CAF50;
			color: #2e7d32;
		}
		.message.error {
			background: #ffcdd2;
			border: 1px solid #f44336;
			color: #c62828;
		}
		
		.checkin-status {
			text-align: center;
			padding: 20px;
			margin-bottom: 15px;
			border-radius: 5px;
		}
		.checkin-status.checked-in {
			background: #e8f5e9;
			border: 2px solid #4CAF50;
		}
		.checkin-status.not-checked {
			background: #fafafa;
			border: 2px solid #ddd;
		}
		.checkin-status .status-icon {
			font-size: 3em;
			margin-bottom: 10px;
		}
		.checkin-status .status-text {
			font-size: 1.2em;
			font-weight: bold;
		}
		
		.action-section {
			background: white;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 20px;
			margin-bottom: 15px;
		}
		.action-section h3 {
			margin-top: 0;
		}
		
		.form-group {
			margin-bottom: 15px;
		}
		.form-group label {
			display: block;
			margin-bottom: 5px;
			font-weight: bold;
		}
		.form-group input, .form-group select {
			width: 100%;
			padding: 12px;
			font-size: 16px;
			border: 1px solid #ddd;
			border-radius: 5px;
		}
		
		.btn {
			display: inline-block;
			padding: 12px 24px;
			font-size: 16px;
			border: none;
			border-radius: 5px;
			cursor: pointer;
			text-decoration: none;
			margin: 5px;
		}
		.btn-primary {
			background: #4CAF50;
			color: white;
		}
		.btn-primary:hover {
			background: #388E3C;
		}
		.btn-danger {
			background: #f44336;
			color: white;
		}
		.btn-secondary {
			background: #9e9e9e;
			color: white;
		}
		.btn-block {
			display: block;
			width: 100%;
		}
		
		/* Modal Styles */
		.modal {
			display: none;
			position: fixed;
			z-index: 1000;
			left: 0;
			top: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0,0,0,0.5);
			justify-content: center;
			align-items: center;
		}
		.modal-content {
			background: white;
			padding: 25px;
			border-radius: 10px;
			width: 90%;
			max-width: 350px;
			text-align: center;
		}
		.modal-content h3 {
			margin-top: 0;
			margin-bottom: 10px;
		}
		.btn-payment {
			background: #4CAF50;
			color: white;
			margin-bottom: 10px;
			padding: 15px;
			font-size: 18px;
		}
		.btn-payment:hover {
			background: #388E3C;
		}
		.btn-unpaid {
			background: #ff9800;
			color: white;
			margin-bottom: 10px;
			padding: 15px;
			font-size: 16px;
		}
		.btn-unpaid:hover {
			background: #f57c00;
		}
		
		.stats-section {
			background: white;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 15px;
			margin-bottom: 15px;
		}
		.stats-section h3 {
			margin-top: 0;
			color: #333;
		}
		.stat-item {
			display: flex;
			justify-content: space-between;
			padding: 8px 0;
			border-bottom: 1px solid #eee;
		}
		.stat-item:last-child {
			border-bottom: none;
		}
		.stat-value {
			font-weight: bold;
			color: #4CAF50;
		}
		.stat-total {
			font-size: 2em;
			text-align: center;
			color: #4CAF50;
			margin: 15px 0;
		}
		
		.head-count {
			background: #fff3e0;
			border: 2px solid #ff9800;
			border-radius: 5px;
			padding: 15px;
			text-align: center;
			margin-bottom: 15px;
		}
		.head-count .count {
			font-size: 2.5em;
			font-weight: bold;
			color: #e65100;
		}
		.head-count .label {
			color: #f57c00;
		}
		
			.change-name {
			text-align: center;
			margin-top: 15px;
			padding-top: 15px;
			border-top: 1px solid #eee;
		}
		.change-name a {
			color: #666;
			font-size: 14px;
		}
		
		.leaderboard {
			background: white;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 15px;
			margin-bottom: 15px;
		}
		.leaderboard h3 {
			margin-top: 0;
			margin-bottom: 15px;
		}
		.leaderboard-item {
			display: flex;
			align-items: center;
			margin-bottom: 8px;
			font-size: 14px;
		}
		.leaderboard-rank {
			width: 25px;
			font-weight: bold;
			color: #666;
		}
		.leaderboard-name {
			width: 120px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.leaderboard-bar-container {
			flex: 1;
			height: 20px;
			background: #eee;
			border-radius: 3px;
			margin: 0 10px;
			overflow: hidden;
		}
		.leaderboard-bar {
			height: 100%;
			background: linear-gradient(90deg, #4CAF50, #81C784);
			border-radius: 3px;
		}
		.leaderboard-count {
			width: 30px;
			text-align: right;
			font-weight: bold;
			color: #4CAF50;
		}
	</style>
</head>
<body>
	<div class="nav-links">
		<a href="event.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>">&laquo; Back to Event</a>
		 | 
		<a href="rollcall.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?><?php echo $TESTING_MODE ? '&test=1' : ''; ?>">Full Roll Call</a>
	</div>
	
	<!-- Name Tag -->
	<div class="nametag">
		<div class="nametag-header">Hi, My Name Is</div>
		<div class="nametag-body">
			<?php if (!empty($rememberedName)): ?>
			<div class="nametag-name"><?php echo htmlspecialchars($rememberedName); ?></div>
			<?php else: ?>
			<div class="nametag-empty">Enter your hash name below</div>
			<?php endif; ?>
		</div>
		<div class="nametag-footer"></div>
	</div>
	
	<!-- Event Info -->
	<div class="event-info">
		<h2><?php echo htmlspecialchars($kennel); ?></h2>
		<p><?php echo htmlspecialchars($eventDate); ?> @ <?php echo htmlspecialchars($eventTime); ?></p>
		<?php if ($eventTitle): ?>
		<p><?php echo htmlspecialchars($eventTitle); ?></p>
		<?php endif; ?>
	</div>
	
	<!-- Window Status -->
	<?php if ($windowInfo['testingMode']): ?>
	<div class="window-status window-test">
		🧪 TESTING MODE - CHECK-IN ALWAYS OPEN
	</div>
	<?php elseif ($windowInfo['isOpen']): ?>
	<div class="window-status window-open">
		✅ CHECK-IN IS OPEN
	</div>
	<?php elseif ($windowInfo['isBefore']): ?>
	<div class="window-status window-closed">
		⏳ Opens at <?php echo date('g:i A', $windowInfo['windowStart']); ?>
	</div>
	<?php else: ?>
	<div class="window-status window-closed">
		🔒 CHECK-IN CLOSED
	</div>
	<?php endif; ?>
	
	<?php if ($message): ?>
	<div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
	<?php endif; ?>
	
	<!-- Head Count -->
	<div class="head-count">
		<div class="count"><?php echo $headCount; ?></div>
		<div class="label">Hashers Checked In</div>
	</div>
	
	<?php if ($showSimilarNames): ?>
	<!-- Similar Names Confirmation -->
	<div class="action-section" style="background: #fff3e0; border-color: #ff9800;">
		<h3>🤔 Did You Mean...?</h3>
		<p>You entered: <strong><?php echo htmlspecialchars($pendingName); ?></strong></p>
		<p>We found similar names:</p>
		
		<?php foreach ($similarNames as $similar): ?>
		<form method="POST" style="margin-bottom: 10px;">
			<input type="hidden" name="existing_name" value="<?php echo htmlspecialchars($similar); ?>">
			<button type="submit" name="use_existing" class="btn btn-primary btn-block">
				I'm "<?php echo htmlspecialchars($similar); ?>"
			</button>
		</form>
		<?php endforeach; ?>
		
		<hr style="margin: 20px 0;">
		<p>None of these? Add yourself as a new hasher:</p>
		<form method="POST">
			<input type="hidden" name="confirmed_name" value="<?php echo htmlspecialchars($pendingName); ?>">
			<button type="submit" name="confirm_name" class="btn btn-secondary btn-block">
				➕ Add "<?php echo htmlspecialchars($pendingName); ?>" as new
			</button>
		</form>
	</div>
	
	<?php elseif (empty($rememberedName)): ?>
	<!-- Set Name Form -->
	<div class="action-section">
		<h3>👋 Who Are You?</h3>
		<form method="POST">
			<div class="form-group">
				<label>Your Hash Name:</label>
				<input type="text" name="hasher_name" placeholder="Enter your hash name..." required autofocus>
			</div>
			<button type="submit" name="set_name" class="btn btn-primary btn-block">That's Me!</button>
		</form>
		<p style="margin-top: 15px; font-size: 13px; color: #666; text-align: center;">
			Your name will be remembered on this device for future check-ins.
		</p>
	</div>
	
	<?php else: ?>
	
	<!-- Check-in Status -->
	<div class="checkin-status <?php echo $isCheckedIn ? 'checked-in' : 'not-checked'; ?>">
		<div class="status-icon"><?php echo $isCheckedIn ? '✅' : '⬜'; ?></div>
		<div class="status-text">
			<?php echo $isCheckedIn ? 'You are CHECKED IN!' : 'Not checked in yet'; ?>
		</div>
		<?php if ($isCheckedIn && $currentPayment): ?>
		<div style="margin-top: 10px; color: #666;">Paid via: <?php echo htmlspecialchars($currentPayment); ?></div>
		<?php endif; ?>
	</div>
	
	<!-- Check-in/out Form -->
	<?php if ($windowInfo['isOpen']): ?>
	<div class="action-section">
		<?php if (!$isCheckedIn): ?>
		<!-- Check-in button triggers modal -->
		<button type="button" id="checkinBtn" class="btn btn-primary btn-block">✅ Check Me In!</button>
		<?php else: ?>
		<!-- Already checked in - show update form -->
		<form method="POST">
			<div class="form-group">
				<label>💵 Paid How?</label>
				<select name="payment">
					<?php foreach ($paymentOptions as $opt): ?>
					<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($currentPayment == $opt) ? 'selected' : ''; ?>>
						<?php echo $opt ? htmlspecialchars($opt) : '-- Unpaid --'; ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<button type="submit" name="checkin" class="btn btn-primary btn-block">💾 Update Payment</button>
			<button type="submit" name="checkout" class="btn btn-danger btn-block" style="margin-top: 10px;">❌ Check Out</button>
		</form>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	
	<!-- Payment Modal -->
	<div id="paymentModal" class="modal">
		<div class="modal-content">
			<h3>💵 How are you paying?</h3>
			<p style="color: #666; margin-bottom: 20px;">Please select your payment method</p>
			<form method="POST" id="checkinForm">
				<?php foreach ($paymentOptions as $opt): ?>
				<?php if ($opt === ''): ?>
				<button type="submit" name="checkin" class="btn btn-block btn-unpaid" onclick="document.getElementById('paymentField').value='';">
					😬 I Haven't Paid Yet
				</button>
				<hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
				<?php else: ?>
				<button type="submit" name="checkin" class="btn btn-block btn-payment" onclick="document.getElementById('paymentField').value='<?php echo htmlspecialchars($opt); ?>';">
					<?php echo htmlspecialchars($opt); ?>
				</button>
				<?php endif; ?>
				<?php endforeach; ?>
				<input type="hidden" name="payment" id="paymentField" value="">
				<button type="button" class="btn btn-secondary btn-block" style="margin-top: 15px;" onclick="closeModal()">Cancel</button>
			</form>
		</div>
	</div>
	
	<script>
	var modal = document.getElementById('paymentModal');
	var checkinBtn = document.getElementById('checkinBtn');
	
	if (checkinBtn) {
		checkinBtn.onclick = function() {
			modal.style.display = 'flex';
		}
	}
	
	function closeModal() {
		modal.style.display = 'none';
	}
	
	window.onclick = function(event) {
		if (event.target == modal) {
			closeModal();
		}
	}
	</script>
	
	<!-- Stats Section -->
	<?php if ($userStats && $userStats['total'] > 0): ?>
	<div class="stats-section">
		<h3>📊 Your Hash Stats</h3>
		<div class="stat-total"><?php echo $userStats['total']; ?> Check-ins</div>
		
		<?php if (count($userStats['byKennel']) > 0): ?>
		<h4 style="margin-bottom: 10px;">By Kennel:</h4>
		<?php 
		arsort($userStats['byKennel']);
		foreach ($userStats['byKennel'] as $kennelName => $count): 
		?>
		<div class="stat-item">
			<span><?php echo htmlspecialchars($kennelName); ?></span>
			<span class="stat-value"><?php echo $count; ?></span>
		</div>
		<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php elseif ($userStats): ?>
	<div class="stats-section">
		<h3>📊 Your Hash Stats</h3>
		<p style="text-align: center; color: #666;">No check-ins recorded yet. This is your first!</p>
	</div>
	<?php endif; ?>
	
	<!-- Leaderboard -->
	<?php if (count($topHashers) > 0): ?>
	<div class="leaderboard">
		<h3>🏆 Top Hashers</h3>
		<?php $rank = 1; foreach ($topHashers as $name => $count): ?>
		<div class="leaderboard-item">
			<div class="leaderboard-rank"><?php echo $rank; ?>.</div>
			<div class="leaderboard-name" title="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></div>
			<div class="leaderboard-bar-container">
				<div class="leaderboard-bar" style="width: <?php echo ($count / $maxCount) * 100; ?>%"></div>
			</div>
			<div class="leaderboard-count"><?php echo $count; ?></div>
		</div>
		<?php $rank++; endforeach; ?>
		<p style="text-align: center; margin-top: 10px; margin-bottom: 0;">
			<a href="rollcall.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?><?php echo $TESTING_MODE ? '&test=1' : ''; ?>" style="color: #666; font-size: 12px;">View full leaderboard →</a>
		</p>
	</div>
	<?php endif; ?>
	
	<!-- Change Name -->
	<div class="action-section">
		<div class="change-name">
			<p style="margin-bottom: 10px;">Not <?php echo htmlspecialchars($rememberedName); ?>?</p>
			<form method="POST" style="display: inline;">
				<button type="submit" name="forget" class="btn btn-secondary">🔄 Change Name</button>
			</form>
		</div>
	</div>
	
	<?php endif; ?>
	
</body>
</html>