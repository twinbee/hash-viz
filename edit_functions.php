<?php
// ============================================
// EDIT_FUNCTIONS.PHP - Helper functions for edit.php
// Version 2.3
// ============================================

// ============================================
// SECURITY FUNCTIONS
// ============================================

// Sanitize output for HTML context (full escape)
function h($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Sanitize HTML - allow safe tags, remove dangerous content like javascript
function sanitizeHtml($input) {
	if (is_array($input)) {
		return array_map('sanitizeHtml', $input);
	}
	
	// Remove null bytes
	$input = str_replace(chr(0), '', $input);
	
	// Remove script tags and their contents
	$input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
	
	// Remove javascript: protocol from any attribute
	$input = preg_replace('/javascript\s*:/i', '', $input);
	
	// Remove vbscript: protocol
	$input = preg_replace('/vbscript\s*:/i', '', $input);
	
	// Remove data: protocol (can be used for XSS)
	$input = preg_replace('/data\s*:[^,]*base64/i', '', $input);
	
	// Remove on* event handlers (onclick, onerror, onload, etc.)
	$input = preg_replace('/\bon\w+\s*=/i', '', $input);
	
	// Remove style attributes (can contain expressions in old IE)
	$input = preg_replace('/\bstyle\s*=/i', '', $input);
	
	// Remove iframe, object, embed, form tags
	$input = preg_replace('/<(iframe|object|embed|form|meta|link|base)\b[^>]*>/i', '', $input);
	$input = preg_replace('/<\/(iframe|object|embed|form|meta|link|base)>/i', '', $input);
	
	// Remove expression() which was used for XSS in old IE
	$input = preg_replace('/expression\s*\(/i', '', $input);
	
	return $input;
}

// Sanitize input - remove null bytes and trim (for non-HTML fields)
function sanitizeInput($input) {
	if (is_array($input)) {
		return array_map('sanitizeInput', $input);
	}
	$input = str_replace(chr(0), '', $input);
	$input = trim($input);
	return $input;
}

// Validate URL - only allow http/https
function validateUrl($url) {
	$url = trim($url);
	if (empty($url)) return '';
	
	if (!preg_match('/^https?:\/\//i', $url)) {
		if (preg_match('/^[a-zA-Z0-9]/', $url)) {
			$url = 'https://' . $url;
		} else {
			return '';
		}
	}
	
	if (!preg_match('/^https?:\/\/[a-zA-Z0-9][-a-zA-Z0-9+&@#\/%?=~_|!:,.;]*$/i', $url)) {
		return '';
	}
	
	return $url;
}

// Generate CSRF token
function generateCsrfToken() {
	if (!isset($_SESSION['csrf_token'])) {
		if (function_exists('openssl_random_pseudo_bytes')) {
			$_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
		} else {
			$_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true) . mt_rand() . time());
		}
	}
	return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token) {
	if (!isset($_SESSION['csrf_token'])) {
		return false;
	}
	if (function_exists('hash_equals')) {
		return hash_equals($_SESSION['csrf_token'], $token);
	} else {
		$expected = $_SESSION['csrf_token'];
		if (strlen($expected) !== strlen($token)) {
			return false;
		}
		$result = 0;
		for ($i = 0; $i < strlen($expected); $i++) {
			$result |= ord($expected[$i]) ^ ord($token[$i]);
		}
		return $result === 0;
	}
}

// Simple MD5 verification (works on any PHP version)
function verify_password($password, $hash) {
	return md5($password) === $hash;
}

// Validate time format for EditHash 1.28 compatibility
function validateTimeFormat($time) {
	return preg_match('/^\d{1,2}:\d{2}\s*(AM|PM)/i', $time);
}

// Recursively strip slashes (for nested arrays like bring[])
function stripslashes_deep($value) {
	if (is_array($value)) {
		return array_map('stripslashes_deep', $value);
	}
	return stripslashes($value);
}

// ============================================
// BACKUP FUNCTION
// ============================================
function createBackup($filename, $username = '', $eventInfo = '') {
	if (!file_exists($filename)) {
		error_log("Backup failed: Source file does not exist: " . $filename);
		return false;
	}
	
	if (!file_exists(BACKUP_DIR)) {
		if (!mkdir(BACKUP_DIR, 0755, true)) {
			error_log("Backup failed: Could not create backup directory: " . BACKUP_DIR);
			return false;
		}
	}
	
	if (!is_writable(BACKUP_DIR)) {
		error_log("Backup failed: Backup directory is not writable: " . BACKUP_DIR);
		return false;
	}
	
	$backupFile = BACKUP_DIR . basename($filename) . '.' . date('Y-m-d_H-i-s') . '.bak';
	
	if (copy($filename, $backupFile)) {
		$logFile = BACKUP_DIR . 'changelog.log';
		$logEntry = date('Y-m-d H:i:s') . "\t" . $username . "\t" . basename($filename) . "\t" . $eventInfo . "\n";
		file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
		return $backupFile;
	}
	
	error_log("Backup failed: copy() failed from " . $filename . " to " . $backupFile);
	return false;
}

// ============================================
// DATE/TIME HELPERS
// ============================================
function generateDateString($day, $month, $year) {
	try {
		$date = new DateTime();
		$date->setDate($year, $month, $day);
		$date->setTime(0, 0, 0);
		return $date->format('l, F d, Y');
	} catch (Exception $e) {
		return sprintf('%s %d, %d', date('F', mktime(0, 0, 0, $month, 1, 2020)), $day, $year);
	}
}

// ============================================
// ICON HELPERS
// ============================================
function getIconFiles($year) {
	$icons = array();
	$iconDir = dirname(__FILE__) . '/' . $year;
	
	if (!is_dir($iconDir)) {
		return array();
	}
	
	$extensions = array('png', 'jpg', 'jpeg', 'gif', 'webp', 'svg');
	
	foreach ($extensions as $ext) {
		$pattern = $iconDir . '/*.' . $ext;
		$files = glob($pattern);
		if ($files) {
			foreach ($files as $file) {
				$icons[] = basename($file);
			}
		}
		$pattern = $iconDir . '/*.' . strtoupper($ext);
		$files = glob($pattern);
		if ($files) {
			foreach ($files as $file) {
				$icons[] = basename($file);
			}
		}
	}
	
	$icons = array_unique($icons);
	sort($icons);
	
	return $icons;
}

function get_full_moon_icon($month, $year) {
	return sprintf('Calendar Icons-%02d.png', $month);
}

// ============================================
// KENNEL HELPERS
// ============================================
function getKennelList($month = null, $year = null) {
	// Load kennels from defaults.txt
	$defaults = loadKennelDefaults();
	$kennels = array();
	
	foreach ($defaults as $name => $data) {
		$kennels[$name] = isset($data['icon']) && !empty($data['icon']) ? $data['icon'] : '';
	}
	
	// Sort by kennel name
	ksort($kennels);
	
	return $kennels;
}

// Load kennel defaults from defaults.txt
function loadKennelDefaults() {
	$defaultsFile = dirname(__FILE__) . '/defaults.txt';
	
	if (!file_exists($defaultsFile)) {
		return array();
	}
	
	$defaults = array();
	$lines = file($defaultsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	
	foreach ($lines as $idx => $line) {
		if ($idx == 0 && strpos($line, 'DAY') === 0) {
			continue;
		}
		
		$parts = explode("\t", $line);
		$name = isset($parts[1]) ? trim($parts[1]) : '';
		
		if (!empty($name)) {
			$defaults[$name] = array(
				'icon' => isset($parts[2]) ? trim($parts[2]) : '',
				'time' => isset($parts[6]) ? trim($parts[6]) : '',
				'start' => isset($parts[7]) ? trim($parts[7]) : '',
				'map' => isset($parts[8]) ? trim($parts[8]) : '',
				'hashcash' => isset($parts[9]) ? trim($parts[9]) : '',
				'turds' => isset($parts[10]) ? trim($parts[10]) : '',
				'desc' => isset($parts[14]) ? trim($parts[14]) : ''
			);
		}
	}
	
	return $defaults;
}

// ============================================
// EVENT HELPERS
// ============================================
function getEventsOnDay($year, $month, $day) {
	$filename = sprintf("../android/%d-%02d.txt", $year, $month);
	$events = array();
	
	if (!file_exists($filename)) {
		return $events;
	}
	
	$file = fopen($filename, "r");
	if (!$file) {
		return $events;
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
		
		if ($d == $day) {
			$events[] = array(
				'no' => $n,
				'kennel' => isset($tempData[1]) ? $tempData[1] : '',
				'time' => isset($tempData[6]) ? $tempData[6] : ''
			);
		}
	}
	fclose($file);
	
	return $events;
}

function countEventsOnDay($filename, $targetDay) {
	$count = 0;
	if (!file_exists($filename)) {
		return 0;
	}
	
	$file = fopen($filename, "r");
	if (!$file) {
		return 0;
	}
	
	while ($line = fgets($file, 8192)) {
		$data = explode("\t", $line);
		$d = isset($data[0]) ? $data[0] : '';
		if ($d == $targetDay) {
			$count++;
		}
	}
	fclose($file);
	
	return $count;
}

function findKennelEvents($filename, $currentDay, $currentNo, $currentKennel) {
	$result = array('prev' => null, 'next' => null);
	
	if (!file_exists($filename)) {
		return $result;
	}
	
	$file = fopen($filename, "r");
	if (!$file) {
		return $result;
	}
	
	$events = array();
	$n = 0;
	$lastDay = "";
	$currentIndex = -1;
	
	while ($line = fgets($file, 8192)) {
		$data = explode("\t", $line);
		$d = isset($data[0]) ? $data[0] : '';
		$kennel = isset($data[1]) ? trim($data[1]) : '';
		
		if ($d != $lastDay) {
			$n = 1;
		} else {
			$n += 1;
		}
		$lastDay = $d;
		
		if ($kennel == $currentKennel) {
			$events[] = array('day' => $d, 'no' => $n);
			
			if ($d == $currentDay && $n == $currentNo) {
				$currentIndex = count($events) - 1;
			}
		}
	}
	fclose($file);
	
	if ($currentIndex > 0) {
		$result['prev'] = $events[$currentIndex - 1];
	}
	if ($currentIndex >= 0 && $currentIndex < count($events) - 1) {
		$result['next'] = $events[$currentIndex + 1];
	}
	
	return $result;
}

// ============================================
// EDIT LOCK SYSTEM
// ============================================
define('LOCK_DIR', dirname(__FILE__) . '/locks');
define('LOCK_TIMEOUT', 1800);

function getLockFile($year, $month, $day, $no) {
	return LOCK_DIR . '/' . sprintf('%d-%02d-%02d-%d.lock', $year, $month, $day, $no);
}

function acquireEditLock($year, $month, $day, $no, $username) {
	if (!is_dir(LOCK_DIR)) {
		mkdir(LOCK_DIR, 0755, true);
	}
	
	$lockFile = getLockFile($year, $month, $day, $no);
	$lockData = array(
		'user' => $username,
		'time' => time(),
		'timestamp' => date('Y-m-d H:i:s')
	);
	
	file_put_contents($lockFile, serialize($lockData), LOCK_EX);
	return true;
}

function getEditLock($year, $month, $day, $no) {
	$lockFile = getLockFile($year, $month, $day, $no);
	
	if (!file_exists($lockFile)) {
		return null;
	}
	
	$lockData = unserialize(file_get_contents($lockFile));
	
	if (time() - $lockData['time'] > LOCK_TIMEOUT) {
		releaseEditLock($year, $month, $day, $no);
		return null;
	}
	
	return $lockData;
}

function releaseEditLock($year, $month, $day, $no) {
	$lockFile = getLockFile($year, $month, $day, $no);
	if (file_exists($lockFile)) {
		unlink($lockFile);
	}
}

function refreshEditLock($year, $month, $day, $no, $username) {
	$lockFile = getLockFile($year, $month, $day, $no);
	
	if (file_exists($lockFile)) {
		$lockData = unserialize(file_get_contents($lockFile));
		if ($lockData['user'] === $username) {
			$lockData['time'] = time();
			$lockData['timestamp'] = date('Y-m-d H:i:s');
			file_put_contents($lockFile, serialize($lockData), LOCK_EX);
			return true;
		}
	}
	return false;
}