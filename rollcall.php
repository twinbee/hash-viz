<?php
// ============================================
// ROLLCALL.PHP - Attendance Tracking for DFW Hash House Harriers
// Version 1.4
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

// Testing mode - bypasses time check
// Usage: ?test=1 or ?test=onin
$TESTING_MODE = (isset($_GET['test']) && ($_GET['test'] == '1' || $_GET['test'] == 'onin'));

// ============================================
// SECURITY: Sanitize hasher names
// ============================================
function sanitizeHasherName($name) {
	// Remove null bytes
	$name = str_replace(chr(0), '', $name);
	// Remove script tags
	$name = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $name);
	// Remove javascript: protocol
	$name = preg_replace('/javascript\s*:/i', '', $name);
	// Remove on* event handlers
	$name = preg_replace('/\bon\w+\s*=/i', '', $name);
	// Remove angle brackets entirely for names (no HTML needed in names)
	$name = preg_replace('/<[^>]*>/', '', $name);
	// Trim
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
	// Clean up time string
	$timeStr = trim($timeStr);
	$timeStr = preg_replace('/\s*(CST|CDT)\s*/', '', $timeStr);
	
	// Try to parse time
	$hour = 18; // Default 6 PM
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
// HELPER: Check if checkin is allowed
// ============================================
function isCheckinAllowed($year, $month, $day, $timeStr) {
	$eventTime = getEventTimestamp($year, $month, $day, $timeStr);
	$now = time();
	
	$windowStart = $eventTime - (10 * 60); // 10 minutes before
	$windowEnd = $eventTime + (4 * 60 * 60); // 4 hours after
	
	return ($now >= $windowStart && $now <= $windowEnd);
}

// ============================================
// HELPER: Get checkin window info
// ============================================
function getCheckinWindowInfo($year, $month, $day, $timeStr, $testingMode = false) {
	$eventTime = getEventTimestamp($year, $month, $day, $timeStr);
	$now = time();
	
	$windowStart = $eventTime - (10 * 60);
	$windowEnd = $eventTime + (4 * 60 * 60);
	
	// Testing mode forces window open
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
	$hashers = array_filter($hashers); // Remove empty
	sort($hashers, SORT_STRING | SORT_FLAG_CASE);
	
	return $hashers;
}

// ============================================
// HELPER: Save hashers list
// ============================================
function saveHashers($hashers) {
	// Create directory if needed
	if (!file_exists(ROLLCALL_DIR)) {
		mkdir(ROLLCALL_DIR, 0755, true);
	}
	
	$hashers = array_unique($hashers);
	sort($hashers, SORT_STRING | SORT_FLAG_CASE);
	
	$file = ROLLCALL_DIR . HASHERS_FILE;
	file_put_contents($file, implode("\n", $hashers) . "\n", LOCK_EX);
}

// ============================================
// HELPER: Find similar names using soundex
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
// HELPER: Check if name already exists (case insensitive)
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
// HELPER: Get attendance file path for an event
// ============================================
function getAttendanceFile($year, $month, $day, $no, $kennel) {
	// Sanitize kennel name for filename
	$kennelSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kennel);
	return ROLLCALL_DIR . sprintf("%d-%02d-%02d_%d_%s.txt", $year, $month, $day, $no, $kennelSafe);
}

// ============================================
// HELPER: Load attendance for an event
// Format: name \t timestamp \t payment_method
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
	// Create directory if needed
	if (!file_exists(ROLLCALL_DIR)) {
		mkdir(ROLLCALL_DIR, 0755, true);
	}
	
	$file = getAttendanceFile($year, $month, $day, $no, $kennel);
	
	$lines = array();
	foreach ($attendance as $name => $data) {
		// Support both old format (string timestamp) and new format (array with timestamp and payment)
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
// Tally file format: name \t total \t kennel1:count1,kennel2:count2,...
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
	
	// Sanitize kennel name for storage (remove colons and commas which are delimiters)
	$kennelSafe = str_replace(array(':', ','), array('-', '-'), $kennel);
	
	if (!isset($tally[$hasherName]['byKennel'][$kennelSafe])) {
		$tally[$hasherName]['byKennel'][$kennelSafe] = 0;
	}
	$tally[$hasherName]['byKennel'][$kennelSafe] += $increment;
	
	// Ensure total doesn't go negative
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
// HELPER: Rebuild tally from all attendance files (run manually if needed)
// Usage: ?rebuild_tally=1
// ============================================
function rebuildTally() {
	$tally = array();
	
	if (!file_exists(ROLLCALL_DIR)) {
		return $tally;
	}
	
	$files = glob(ROLLCALL_DIR . "*.txt");
	foreach ($files as $file) {
		$basename = basename($file);
		if ($basename == HASHERS_FILE || $basename == TALLY_FILE) continue;
		
		// Extract kennel from filename: YYYY-MM-DD_N_KennelName.txt
		$fileBase = basename($file, '.txt');
		$fileParts = explode('_', $fileBase);
		$kennel = '';
		if (count($fileParts) >= 3) {
			$kennel = str_replace('_', ' ', implode('_', array_slice($fileParts, 2)));
			// Sanitize for tally storage
			$kennel = str_replace(array(':', ','), array('-', '-'), $kennel);
		}
		
		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			$parts = explode("\t", $line);
			if (count($parts) >= 1 && !empty($parts[0])) {
				$name = $parts[0];
				
				if (!isset($tally[$name])) {
					$tally[$name] = array('total' => 0, 'byKennel' => array());
				}
				
				$tally[$name]['total']++;
				
				if (!empty($kennel)) {
					if (!isset($tally[$name]['byKennel'][$kennel])) {
						$tally[$name]['byKennel'][$kennel] = 0;
					}
					$tally[$name]['byKennel'][$kennel]++;
				}
			}
		}
	}
	
	saveTally($tally);
	return $tally;
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

// Get checkin window info (pass testing mode)
$windowInfo = getCheckinWindowInfo($year, $month, $day, $eventTime, $TESTING_MODE);

// Load hashers and attendance
$hashers = loadHashers();
$attendance = loadAttendance($year, $month, $day, $no, $kennel);

// Handle checkin/checkout
$message = '';
$messageType = 'success';
$showConfirmAdd = false;
$newHasherName = '';
$similarNames = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	// Handle toggle checkin/checkout
	if (isset($_POST['toggle'])) {
		$hasherName = $_POST['toggle'];
		$paymentMethod = isset($_POST['payment_' . md5($hasherName)]) ? $_POST['payment_' . md5($hasherName)] : '';
		
		if ($windowInfo['isOpen']) {
			if (isset($attendance[$hasherName])) {
				// Check out - decrement tally
				unset($attendance[$hasherName]);
				updateTally($hasherName, $kennel, -1);
				$message = htmlspecialchars($hasherName) . " checked out.";
			} else {
				// Check in with payment method - increment tally
				$attendance[$hasherName] = array(
					'timestamp' => date('Y-m-d H:i:s'),
					'payment' => $paymentMethod
				);
				updateTally($hasherName, $kennel, 1);
				$message = htmlspecialchars($hasherName) . " checked in!";
			}
			saveAttendance($year, $month, $day, $no, $kennel, $attendance);
		} else {
			$message = "Check-in window is not open.";
			$messageType = 'error';
		}
	}
	
	// Handle payment update for already checked-in hasher
	if (isset($_POST['update_payment'])) {
		$hasherName = $_POST['update_payment'];
		$paymentMethod = isset($_POST['payment_' . md5($hasherName)]) ? $_POST['payment_' . md5($hasherName)] : '';
		
		if ($windowInfo['isOpen'] && isset($attendance[$hasherName])) {
			$attendance[$hasherName]['payment'] = $paymentMethod;
			saveAttendance($year, $month, $day, $no, $kennel, $attendance);
			$message = "Payment method updated for " . htmlspecialchars($hasherName) . ".";
		}
	}
	
	// Handle add new hasher - initial request
	if (isset($_POST['add_new']) && isset($_POST['new_hasher_name'])) {
		$newHasherName = sanitizeHasherName($_POST['new_hasher_name']);
		$newPaymentMethod = isset($_POST['new_payment']) ? $_POST['new_payment'] : '';
		
		if (empty($newHasherName)) {
			$message = "Please enter a hash name.";
			$messageType = 'error';
		} elseif (!$windowInfo['isOpen']) {
			$message = "Check-in window is not open.";
			$messageType = 'error';
		} elseif (hasherExists($newHasherName, $hashers)) {
			$message = "\"" . htmlspecialchars($newHasherName) . "\" already exists in the list. Please find your name and check in.";
			$messageType = 'error';
		} else {
			// Find similar names
			$similarNames = findSimilarNames($newHasherName, $hashers);
			if (count($similarNames) > 0) {
				$showConfirmAdd = true;
			} else {
				// No similar names, add directly
				$hashers[] = $newHasherName;
				saveHashers($hashers);
				$attendance[$newHasherName] = array(
					'timestamp' => date('Y-m-d H:i:s'),
					'payment' => $newPaymentMethod
				);
				saveAttendance($year, $month, $day, $no, $kennel, $attendance);
				updateTally($newHasherName, $kennel, 1);
				$message = "Welcome! \"" . htmlspecialchars($newHasherName) . "\" has been added and checked in!";
				$newHasherName = '';
				// Reload hashers
				$hashers = loadHashers();
			}
		}
	}
	
	// Handle confirmed add new hasher
	if (isset($_POST['confirm_add']) && isset($_POST['confirmed_name'])) {
		$newHasherName = sanitizeHasherName($_POST['confirmed_name']);
		$newPaymentMethod = isset($_POST['confirmed_payment']) ? $_POST['confirmed_payment'] : '';
		
		if (!empty($newHasherName) && $windowInfo['isOpen'] && !hasherExists($newHasherName, $hashers)) {
			$hashers[] = $newHasherName;
			saveHashers($hashers);
			$attendance[$newHasherName] = array(
				'timestamp' => date('Y-m-d H:i:s'),
				'payment' => $newPaymentMethod
			);
			saveAttendance($year, $month, $day, $no, $kennel, $attendance);
			updateTally($newHasherName, $kennel, 1);
			$message = "Welcome! \"" . htmlspecialchars($newHasherName) . "\" has been added and checked in!";
			$newHasherName = '';
			// Reload hashers
			$hashers = loadHashers();
		}
	}
}

// Handle rebuild tally request (admin function)
if (isset($_GET['rebuild_tally']) && $_GET['rebuild_tally'] == '1') {
	$tally = rebuildTally();
	$message = "Tally rebuilt from " . count($tally) . " hashers.";
}

// Get top hashers for leaderboard
$topHashers = getTopHashers(10);

// Auto-rebuild tally if empty but attendance files exist
if (count($topHashers) == 0 && file_exists(ROLLCALL_DIR)) {
	$attendanceFiles = glob(ROLLCALL_DIR . "*.txt");
	// Filter out hashers.txt and tally.txt (PHP 5.2 compatible)
	$filteredFiles = array();
	foreach ($attendanceFiles as $f) {
		$base = basename($f);
		if ($base != HASHERS_FILE && $base != TALLY_FILE) {
			$filteredFiles[] = $f;
		}
	}
	if (count($filteredFiles) > 0) {
		rebuildTally();
		$topHashers = getTopHashers(10);
	}
}

$maxCount = count($topHashers) > 0 ? max($topHashers) : 1;

// Head count
$headCount = count($attendance);

// Payment method options
$paymentOptions = array('', 'Cash', 'PayPal', 'Venmo', 'Zelle', 'Cash App');
?>
<!DOCTYPE html>
<html>
<head>
	<meta name="HandheldFriendly" content="true" />
	<meta name="MobileOptimized" content="320" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Roll Call - <?php echo htmlspecialchars($kennel); ?> - <?php echo $month; ?>/<?php echo $day; ?>/<?php echo $year; ?></title>
	
	<style>
		* { box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			max-width: 800px;
			margin: 0 auto;
			padding: 15px;
			background: #f5f5f5;
		}
		.header {
			background: #4CAF50;
			color: white;
			padding: 15px;
			border-radius: 5px;
			margin-bottom: 15px;
		}
		.header h1 {
			margin: 0 0 5px 0;
			font-size: 1.5em;
		}
		.header h2 {
			margin: 0;
			font-size: 1.1em;
			font-weight: normal;
			opacity: 0.9;
		}
		.nav-links {
			margin-bottom: 15px;
		}
		.nav-links a {
			color: #4CAF50;
			text-decoration: none;
		}
		.instructions {
			background: #e3f2fd;
			border: 1px solid #2196F3;
			border-radius: 5px;
			padding: 15px;
			margin-bottom: 15px;
		}
		.instructions h3 {
			margin-top: 0;
			color: #1565C0;
		}
		.instructions ul {
			margin-bottom: 0;
			padding-left: 20px;
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
		.head-count {
			background: #fff3e0;
			border: 2px solid #ff9800;
			border-radius: 5px;
			padding: 15px;
			margin-bottom: 15px;
			text-align: center;
		}
		.head-count .count {
			font-size: 3em;
			font-weight: bold;
			color: #e65100;
		}
		.head-count .label {
			color: #f57c00;
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
			color: #333;
		}
		.leaderboard-item {
			display: flex;
			align-items: center;
			margin-bottom: 8px;
		}
		.leaderboard-rank {
			width: 30px;
			font-weight: bold;
			color: #666;
		}
		.leaderboard-name {
			width: 150px;
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
		}
		.leaderboard-bar {
			height: 100%;
			background: linear-gradient(90deg, #4CAF50, #8BC34A);
			border-radius: 3px;
			transition: width 0.3s;
		}
		.leaderboard-count {
			width: 40px;
			text-align: right;
			font-weight: bold;
		}
		.message {
			padding: 10px 15px;
			border-radius: 5px;
			margin-bottom: 15px;
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
		.confirm-box {
			background: #fff3e0;
			border: 2px solid #ff9800;
			border-radius: 5px;
			padding: 15px;
			margin-bottom: 15px;
		}
		.confirm-box h4 {
			margin-top: 0;
			color: #e65100;
		}
		.confirm-box .similar-list {
			background: white;
			padding: 10px;
			border-radius: 3px;
			margin: 10px 0;
		}
		.confirm-box .similar-list li {
			padding: 5px 0;
		}
		.confirm-box .buttons {
			margin-top: 15px;
		}
		.confirm-box button {
			padding: 10px 20px;
			border: none;
			border-radius: 5px;
			cursor: pointer;
			font-size: 14px;
			margin-right: 10px;
		}
		.confirm-box .btn-confirm {
			background: #4CAF50;
			color: white;
		}
		.confirm-box .btn-cancel {
			background: #9e9e9e;
			color: white;
		}
		.add-new-section {
			background: #e8eaf6;
			border: 1px solid #3f51b5;
			border-radius: 5px;
			padding: 15px;
			margin-top: 15px;
		}
		.add-new-section h4 {
			margin-top: 0;
			color: #3f51b5;
		}
		.add-new-section input[type="text"] {
			width: calc(100% - 120px);
			padding: 10px;
			font-size: 16px;
			border: 1px solid #ddd;
			border-radius: 5px;
		}
		.add-new-section button {
			padding: 10px 20px;
			background: #3f51b5;
			color: white;
			border: none;
			border-radius: 5px;
			cursor: pointer;
			font-size: 14px;
		}
		.add-new-section button:hover {
			background: #303f9f;
		}
		.payment-select {
			padding: 5px 8px;
			font-size: 12px;
			border: 1px solid #ccc;
			border-radius: 3px;
			background: white;
			margin-left: 10px;
			min-width: 80px;
		}
		.payment-badge {
			font-size: 10px;
			padding: 2px 6px;
			border-radius: 3px;
			background: #e3f2fd;
			color: #1565c0;
			margin-left: 8px;
		}
		.rollcall {
			background: white;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 15px;
		}
		.rollcall h3 {
			margin-top: 0;
		}
		.search-box {
			width: 100%;
			padding: 10px;
			font-size: 16px;
			border: 1px solid #ddd;
			border-radius: 5px;
			margin-bottom: 15px;
		}
		.hasher-list {
			max-height: 500px;
			overflow-y: auto;
		}
		.hasher-item {
			display: flex;
			align-items: center;
			padding: 10px;
			border-bottom: 1px solid #eee;
			cursor: pointer;
			transition: background 0.2s;
		}
		.hasher-item:hover {
			background: #f5f5f5;
		}
		.hasher-item.checked-in {
			background: #e8f5e9;
		}
		.hasher-item.checked-in:hover {
			background: #c8e6c9;
		}
		.hasher-checkbox {
			width: 24px;
			height: 24px;
			margin-right: 10px;
			cursor: pointer;
		}
		.hasher-name {
			flex: 1;
			font-size: 16px;
		}
		.hasher-time {
			font-size: 12px;
			color: #666;
		}
		.disabled {
			opacity: 0.5;
			pointer-events: none;
		}
		.checked-list {
			margin-top: 15px;
			padding-top: 15px;
			border-top: 2px solid #4CAF50;
		}
		.checked-list h4 {
			margin-top: 0;
			color: #4CAF50;
		}
	</style>
</head>
<body>
	<div class="nav-links">
		<a href="event.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>">&laquo; Back to Event</a>
	</div>
	
	<div class="header">
		<h1>🏃 Roll Call - <?php echo htmlspecialchars($kennel); ?></h1>
		<h2><?php echo htmlspecialchars($eventDate); ?> <?php echo htmlspecialchars($eventTime); ?></h2>
		<?php if ($eventTitle): ?>
		<h2><?php echo htmlspecialchars($eventTitle); ?></h2>
		<?php endif; ?>
	</div>
	
	<div class="instructions">
		<h3>📋 How to Check In</h3>
		<ul>
			<li>Find your hash name in the list below (use search to filter)</li>
			<li>Select your payment method and tap the checkbox to check in</li>
			<li>Check-in opens <strong>10 minutes before</strong> and closes <strong>4 hours after</strong> the event start time</li>
			<li>Check in at the On-In so we know you made it back safely!</li>
			<li>Your attendance counts toward your hash stats</li>
		</ul>
	</div>
	
	<?php if ($windowInfo['testingMode']): ?>
	<div class="window-status window-open" style="background: #fff3e0; border-color: #ff9800; color: #e65100;">
		🧪 TESTING MODE - CHECK-IN ALWAYS OPEN<br>
		<small>Normal window: <?php echo date('g:i A', $windowInfo['windowStart']); ?> - <?php echo date('g:i A', $windowInfo['windowEnd']); ?></small>
	</div>
	<?php elseif ($windowInfo['isOpen']): ?>
	<div class="window-status window-open">
		✅ CHECK-IN IS OPEN<br>
		<small>Until <?php echo date('g:i A', $windowInfo['windowEnd']); ?></small>
	</div>
	<?php elseif ($windowInfo['isBefore']): ?>
	<div class="window-status window-closed">
		⏳ CHECK-IN OPENS AT <?php echo date('g:i A', $windowInfo['windowStart']); ?><br>
		<small>Event starts at <?php echo htmlspecialchars($eventTime); ?></small>
	</div>
	<?php else: ?>
	<div class="window-status window-closed">
		🔒 CHECK-IN IS CLOSED<br>
		<small>Window closed at <?php echo date('g:i A', $windowInfo['windowEnd']); ?></small>
	</div>
	<?php endif; ?>
	
	<div class="head-count">
		<div class="count"><?php echo $headCount; ?></div>
		<div class="label">Hashers Checked In</div>
	</div>
	
	<?php if (count($topHashers) > 0): ?>
	<div class="leaderboard">
		<h3>🏆 Top 10 Hashers (All-Time Check-ins)</h3>
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
	</div>
	<?php endif; ?>
	
	<?php if ($message): ?>
	<div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
	<?php endif; ?>
	
	<?php if ($showConfirmAdd): ?>
	<div class="confirm-box">
		<h4>⚠️ Similar Names Found</h4>
		<p>Are you sure you want to add "<strong><?php echo htmlspecialchars($newHasherName); ?></strong>"?</p>
		<p>These similar names already exist in the list:</p>
		<ul class="similar-list">
			<?php foreach ($similarNames as $similar): ?>
			<li><?php echo htmlspecialchars($similar); ?></li>
			<?php endforeach; ?>
		</ul>
		<p>If one of these is you, please cancel and check in with your existing name.</p>
		<div class="buttons">
			<form method="POST" style="display: inline;">
				<input type="hidden" name="confirmed_name" value="<?php echo htmlspecialchars($newHasherName); ?>">
				<select name="confirmed_payment" class="payment-select" style="margin-right: 10px;">
					<?php foreach ($paymentOptions as $opt): ?>
					<option value="<?php echo htmlspecialchars($opt); ?>"><?php echo $opt ? htmlspecialchars($opt) : '-- Unpaid --'; ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" name="confirm_add" class="btn-confirm">✅ Yes, Add New Name</button>
			</form>
			<form method="GET" style="display: inline;">
				<input type="hidden" name="year" value="<?php echo $year; ?>">
				<input type="hidden" name="month" value="<?php echo $month; ?>">
				<input type="hidden" name="day" value="<?php echo $day; ?>">
				<input type="hidden" name="no" value="<?php echo $no; ?>">
				<?php if ($TESTING_MODE): ?><input type="hidden" name="test" value="1"><?php endif; ?>
				<button type="submit" class="btn-cancel">❌ Cancel</button>
			</form>
		</div>
	</div>
	<?php endif; ?>
	
	<div class="rollcall <?php echo !$windowInfo['isOpen'] ? 'disabled' : ''; ?>">
		<h3>📝 Hasher Roll Call</h3>
		
		<input type="text" class="search-box" id="searchBox" placeholder="🔍 Search for your hash name..." oninput="filterHashers()">
		
		<?php if ($headCount > 0): ?>
		<div class="checked-list">
			<h4>✅ Checked In (<?php echo $headCount; ?>)</h4>
			<div class="hasher-list">
				<?php 
				$checkedNames = array_keys($attendance);
				sort($checkedNames, SORT_STRING | SORT_FLAG_CASE);
				foreach ($checkedNames as $hasher): 
					$hasherData = $attendance[$hasher];
					$timestamp = is_array($hasherData) ? $hasherData['timestamp'] : $hasherData;
					$payment = is_array($hasherData) && isset($hasherData['payment']) ? $hasherData['payment'] : '';
				?>
				<form method="POST" style="margin:0;">
					<div class="hasher-item checked-in">
						<input type="checkbox" class="hasher-checkbox" checked onclick="this.form.querySelector('button[name=toggle]').click();">
						<span class="hasher-name"><?php echo htmlspecialchars($hasher); ?></span>
						<select name="payment_<?php echo md5($hasher); ?>" class="payment-select" onchange="this.form.querySelector('button[name=update_payment]').click();">
							<?php foreach ($paymentOptions as $opt): ?>
							<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($payment == $opt) ? 'selected' : ''; ?>>
								<?php echo $opt ? htmlspecialchars($opt) : '-- Unpaid --'; ?>
							</option>
							<?php endforeach; ?>
						</select>
						<span class="hasher-time"><?php echo date('g:i A', strtotime($timestamp)); ?></span>
						<button type="submit" name="toggle" value="<?php echo htmlspecialchars($hasher); ?>" style="display:none;"></button>
						<button type="submit" name="update_payment" value="<?php echo htmlspecialchars($hasher); ?>" style="display:none;"></button>
					</div>
				</form>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		
		<div class="hasher-list" id="hasherList">
			<?php foreach ($hashers as $hasher): ?>
			<?php if (isset($attendance[$hasher])) continue; // Skip already checked in ?>
			<form method="POST" style="margin:0;" class="hasher-form">
				<div class="hasher-item" data-name="<?php echo htmlspecialchars(strtolower($hasher)); ?>">
					<input type="checkbox" class="hasher-checkbox" onclick="this.form.querySelector('button').click();">
					<span class="hasher-name"><?php echo htmlspecialchars($hasher); ?></span>
					<select name="payment_<?php echo md5($hasher); ?>" class="payment-select" onclick="event.stopPropagation();">
						<?php foreach ($paymentOptions as $opt): ?>
						<option value="<?php echo htmlspecialchars($opt); ?>"><?php echo $opt ? htmlspecialchars($opt) : '-- Unpaid --'; ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" name="toggle" value="<?php echo htmlspecialchars($hasher); ?>" style="display:none;"></button>
				</div>
			</form>
			<?php endforeach; ?>
		</div>
		
		<?php if ($windowInfo['isOpen']): ?>
		<div class="add-new-section">
			<h4>➕ Not on the list? Add yourself:</h4>
			<form method="POST">
				<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
					<input type="text" name="new_hasher_name" placeholder="Enter your hash name..." value="<?php echo htmlspecialchars($newHasherName); ?>" style="flex: 1; min-width: 200px;">
					<select name="new_payment" class="payment-select" style="margin-left: 0;">
						<?php foreach ($paymentOptions as $opt): ?>
						<option value="<?php echo htmlspecialchars($opt); ?>"><?php echo $opt ? htmlspecialchars($opt) : '-- Unpaid --'; ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" name="add_new">Add & Check In</button>
				</div>
			</form>
			<p style="margin-top: 10px; font-size: 12px; color: #666;">
				New to hashing? Welcome! Enter your hash name (or "Just [YourName]" if you don't have one yet).
			</p>
		</div>
		<?php endif; ?>
	</div>
	
	<script>
	function filterHashers() {
		var search = document.getElementById('searchBox').value.toLowerCase();
		var items = document.querySelectorAll('.hasher-form');
		
		items.forEach(function(form) {
			var item = form.querySelector('.hasher-item');
			var name = item.getAttribute('data-name');
			if (name && name.indexOf(search) !== -1) {
				form.style.display = '';
			} else {
				form.style.display = 'none';
			}
		});
	}
	</script>
</body>
</html>