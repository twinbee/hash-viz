<?php
// ============================================
// EDIT.PHP - Event Editor for DFW Hash House Harriers
// Version 2.2
// ============================================

// Enable error reporting for debugging (comment out in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// ============================================
// TIMEZONE CONFIGURATION
// ============================================
// Server may be in different timezone than events (e.g., Pacific vs Central)
// Set this to the timezone where events actually occur
date_default_timezone_set('America/Chicago'); // Central Time for DFW

// ============================================
// MULTI-USER AUTHENTICATION CONFIGURATION
// ============================================
// Users are stored in a separate file to avoid overwriting during updates
// Use password.php to generate new password hashes

define('EDITPHP_VERSION', '2.2');

// Load users from separate file (in parent calendar/ directory)
require_once('users.php');

define('BACKUP_DIR', '../android/backups/');

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
	
	// Allowed tags: a, img, b, i, u, strike, strong, em, br, p, span, div
	// These are implicitly allowed by not removing them
	
	return $input;
}

// Sanitize input - remove null bytes and trim (for non-HTML fields)
function sanitizeInput($input) {
	if (is_array($input)) {
		return array_map('sanitizeInput', $input);
	}
	// Remove null bytes
	$input = str_replace(chr(0), '', $input);
	// Trim whitespace
	$input = trim($input);
	return $input;
}

// Validate URL - only allow http/https
function validateUrl($url) {
	$url = trim($url);
	if (empty($url)) return '';
	
	// Only allow http and https URLs
	if (!preg_match('/^https?:\/\//i', $url)) {
		// If no protocol, assume https
		if (preg_match('/^[a-zA-Z0-9]/', $url)) {
			$url = 'https://' . $url;
		} else {
			return '';
		}
	}
	
	// Basic URL validation - check for valid characters
	if (!preg_match('/^https?:\/\/[a-zA-Z0-9][-a-zA-Z0-9+&@#\/%?=~_|!:,.;]*$/i', $url)) {
		return '';
	}
	
	return $url;
}

// Generate CSRF token
function generateCsrfToken() {
	if (!isset($_SESSION['csrf_token'])) {
		// PHP 5.2 compatible random token generation
		if (function_exists('openssl_random_pseudo_bytes')) {
			$_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
		} else {
			// Fallback for older PHP versions
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
	// PHP 5.6+ has hash_equals, fallback for older versions
	if (function_exists('hash_equals')) {
		return hash_equals($_SESSION['csrf_token'], $token);
	} else {
		// Timing-safe comparison for older PHP
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

// ============================================
// VALIDATION: Time field format
// Must start with "H:MM AM" or "H:MM PM" for EditHash 1.28 compatibility
// ============================================
function validateTimeFormat($time) {
	// Pattern: 1-2 digit hour, colon, 2 digit minute, space, AM or PM
	// Followed by optional space and additional text
	return preg_match('/^\d{1,2}:\d{2}\s*(AM|PM)/i', $time);
}

session_start();

// Regenerate session ID on login to prevent session fixation
function regenerateSession() {
	session_regenerate_id(true);
}

// Login handler
if (isset($_POST['login'])) {
	$username = sanitizeInput($_POST['username']);
	$password = $_POST['password']; // Don't sanitize password before hash check
	
	// Check if user exists and verify password
	if (isset($USERS[$username]) && verify_password($password, $USERS[$username])) {
		regenerateSession(); // Prevent session fixation
		$_SESSION['authenticated'] = true;
		$_SESSION['username'] = $username;
		$_SESSION['login_time'] = time();
		generateCsrfToken(); // Generate new CSRF token
		header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
		exit;
	} else {
		$loginError = "Invalid username or password";
		// Log failed login attempts
		error_log("Failed login attempt for user: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
	}
}

// Logout handler
if (isset($_GET['logout'])) {
	session_destroy();
	// Redirect to a clean URL without the event parameters to avoid loops
	$redirect_url = $_SERVER['PHP_SELF'];
	header('Location: ' . $redirect_url);
	exit;
}

// Session timeout (30 minutes)
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['login_time'])) {
	if (time() - $_SESSION['login_time'] > $timeout_duration) {
		session_destroy();
		header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&timeout=1');
		exit;
	}
	// Update last activity time
	$_SESSION['login_time'] = time();
}

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
	// Show login form
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="UTF-8">
		<title>Login Required</title>
		<style>
			body { font-family: Arial, sans-serif; background: #f5f5f5; }
			.login-container { 
				max-width: 500px; 
				margin: 50px auto; 
				padding: 30px; 
				background: white; 
				border-radius: 5px; 
				box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			}
			.login-container h2 { margin-top: 0; }
			.form-group { margin-bottom: 15px; }
			.form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
			.form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; }
			.btn-login { 
				width: 100%; 
				padding: 12px; 
				background: #4CAF50; 
				color: white; 
				border: none; 
				cursor: pointer; 
				font-size: 16px;
			}
			.btn-login:hover { background: #45a049; }
			.error { color: red; padding: 10px; background: #f8d7da; margin-bottom: 15px; border-radius: 3px; }
			.info { color: blue; padding: 10px; background: #d1ecf1; margin-bottom: 15px; border-radius: 3px; }
			.instructions-box {
				margin-top: 25px;
				padding: 15px;
				background: #f8f9fa;
				border: 1px solid #ddd;
				border-radius: 5px;
				font-size: 14px;
			}
			.instructions-box h3 {
				margin-top: 0;
				color: #333;
			}
			.instructions-box ol {
				margin: 0;
				padding-left: 20px;
			}
			.instructions-box li {
				margin-bottom: 10px;
			}
			.instructions-box a {
				color: #0066cc;
			}
			.instructions-box .note {
				font-size: 12px;
				color: #666;
				font-style: italic;
				margin-top: 10px;
			}
		</style>
	</head>
	<body>
		<div class="login-container">
			<h2>🔒 Authentication Required</h2>
			<?php if (isset($_GET['timeout'])): ?>
				<div class="info">Your session has expired. Please login again.</div>
			<?php endif; ?>
			<?php if (isset($loginError)): ?>
				<div class="error"><?php echo htmlspecialchars($loginError); ?></div>
			<?php endif; ?>
			<form method="POST">
				<div class="form-group">
					<label>Username:</label>
					<input type="text" name="username" required autofocus>
				</div>
				<div class="form-group">
					<label>Password:</label>
					<input type="password" name="password" required>
				</div>
				<button type="submit" name="login" class="btn-login">Login</button>
			</form>
			
			<div class="instructions-box">
				<h3>📋 How to Get an Account</h3>
				<ol>
					<li>Request an account from <a href="mailto:likesitinthekitchen@gmail.com">likesitinthekitchen@gmail.com</a>. Include your hasher name and the kennel you represent.</li>
					<li>Visit <a href="http://dfwhhh.org/calendar/password.php" target="_blank">http://dfwhhh.org/calendar/password.php</a> to generate your password hash. Copy the black background hash and send it to Kitchen.</li>
					<li>Once your account is created, you can use the "Edit" links on each event page to make changes.</li>
				</ol>
				<p class="note"><strong>Note:</strong> Kitchen and other admins will not know your password or be able to recover it. Passwords are not stored anywhere, so save it for yourself. If you forget it, just generate a new password!</p>
				<p class="note"><strong>Account Policy:</strong> Accounts are audited and added/removed on a yearly basis after elections, and on request of the associated kennel mis-management. Each calendar year maintains its own list of editors.</p>
			</div>
		</div>
	</body>
	</html>
	<?php
	exit;
}

// ============================================
// BACKUP FUNCTION
// ============================================
function createBackup($filename, $username = '', $eventInfo = '') {
	// Check if source file exists
	if (!file_exists($filename)) {
		error_log("Backup failed: Source file does not exist: " . $filename);
		return false;
	}
	
	// Create backup directory if needed
	if (!file_exists(BACKUP_DIR)) {
		if (!mkdir(BACKUP_DIR, 0755, true)) {
			error_log("Backup failed: Could not create backup directory: " . BACKUP_DIR);
			return false;
		}
	}
	
	// Check if backup directory is writable
	if (!is_writable(BACKUP_DIR)) {
		error_log("Backup failed: Backup directory is not writable: " . BACKUP_DIR);
		return false;
	}
	
	$backupFile = BACKUP_DIR . basename($filename) . '.' . date('Y-m-d_H-i-s') . '.bak';
	
	if (copy($filename, $backupFile)) {
		// Log who made the change and what event
		$logFile = BACKUP_DIR . 'changelog.log';
		$logEntry = date('Y-m-d H:i:s') . "\t" . $username . "\t" . basename($filename) . "\t" . $eventInfo . "\n";
		file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
		
		return $backupFile;
	}
	
	error_log("Backup failed: copy() failed from " . $filename . " to " . $backupFile);
	return false;
}

// ============================================
// HELPER FUNCTION: Generate date string from day/month/year
// ============================================
function generateDateString($day, $month, $year) {
	$timestamp = mktime(0, 0, 0, $month, $day, $year);
	return date('l, F d, Y', $timestamp);
}

// ============================================
// HELPER FUNCTION: Get available icon files
// ============================================
function getIconFiles($year) {
	$icons = array();
	// Icons are in calendar/YYYY/ directory (same as event.php)
	$iconDir = dirname(__FILE__) . '/' . $year;
	
	// Check if year directory exists
	if (!is_dir($iconDir)) {
		return array();
	}
	
	// Image extensions to look for
	$extensions = array('png', 'jpg', 'jpeg', 'gif', 'webp', 'svg');
	
	foreach ($extensions as $ext) {
		$pattern = $iconDir . '/*.' . $ext;
		$files = glob($pattern);
		if ($files) {
			foreach ($files as $file) {
				$icons[] = basename($file);
			}
		}
		// Also check uppercase extensions
		$pattern = $iconDir . '/*.' . strtoupper($ext);
		$files = glob($pattern);
		if ($files) {
			foreach ($files as $file) {
				$icons[] = basename($file);
			}
		}
	}
	
	// Remove duplicates and sort
	$icons = array_unique($icons);
	sort($icons);
	
	return $icons;
}

// ============================================
// HELPER FUNCTION: Get full moon icon based on month
// ============================================
function get_full_moon_icon($month, $year) {
	// Full moon icons use Calendar Icons-MM.png format
	return sprintf('Calendar Icons-%02d.png', $month);
}

// ============================================
// HELPER FUNCTION: Get kennel list with default icons
// ============================================
function getKennelList($month = null, $year = null) {
	$kennels = array(
		"7-ELEVEn hash house harriers" => "7-ELEVEn.png",
		"Bike Hash" => "bikeHash.png",
		"Dallas Hash" => "dallas.png",
		"Dallas Urban Hash" => "DUH.png",
		"Ft Worth Hash" => "ftworth.png",
		"Grapevine Quarterly Hash" => "GQhash.png",
		"NO-NO-DUH" => "nonoduh.png",
		"NODUH Hash" => "NoDHHH2.png",
		"YAKH3" => "YAKH3.png",
		"YakH3-HH" => "YAKH3-HH.png"
	);
	
	// Full Moon uses dynamic icon based on month
	if ($month !== null && $year !== null) {
		$kennels["Full Moon"] = get_full_moon_icon($month, $year);
	} else {
		$kennels["Full Moon"] = "Calendar Icons-01.png";
	}
	
	// Sort by kennel name (keys)
	ksort($kennels);
	
	return $kennels;
}

// ============================================
// HELPER FUNCTION: Get events on a specific day
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

// ============================================
// EDIT LOCK SYSTEM - Track who is editing what
// ============================================
define('LOCK_DIR', dirname(__FILE__) . '/locks');
define('LOCK_TIMEOUT', 1800); // 30 minutes - matches session timeout

function getLockFile($year, $month, $day, $no) {
	return LOCK_DIR . '/' . sprintf('%d-%02d-%02d-%d.lock', $year, $month, $day, $no);
}

function acquireEditLock($year, $month, $day, $no, $username) {
	// Create lock directory if needed
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
	
	// Check if lock has expired
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
		// Only refresh if we own the lock
		if ($lockData['user'] === $username) {
			$lockData['time'] = time();
			$lockData['timestamp'] = date('Y-m-d H:i:s');
			file_put_contents($lockFile, serialize($lockData), LOCK_EX);
			return true;
		}
	}
	return false;
}

// Include twilight calculator
require_once('twilight.php');

// ============================================
// HELPER FUNCTION: Count events on a given day
// ============================================
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

// ============================================
// HELPER FUNCTION: Find prev/next events for same kennel
// ============================================
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
	
	// Read all events for this kennel
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
		
		// Only track events for the same kennel
		if ($kennel == $currentKennel) {
			$events[] = array('day' => $d, 'no' => $n);
			
			// Check if this is the current event
			if ($d == $currentDay && $n == $currentNo) {
				$currentIndex = count($events) - 1;
			}
		}
	}
	fclose($file);
	
	// Find prev and next
	if ($currentIndex > 0) {
		$result['prev'] = $events[$currentIndex - 1];
	}
	if ($currentIndex >= 0 && $currentIndex < count($events) - 1) {
		$result['next'] = $events[$currentIndex + 1];
	}
	
	return $result;
}

// ============================================
// HELPER FUNCTION: Recursively strip slashes (for nested arrays like bring[])
// ============================================
function stripslashes_deep($value) {
	if (is_array($value)) {
		return array_map('stripslashes_deep', $value);
	}
	return stripslashes($value);
}

// ============================================
// MAIN SCRIPT
// ============================================

// Handle AJAX request for events on a day
if (isset($_GET['get_events_on_day'])) {
	$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
	$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
	$day = isset($_GET['day']) ? intval($_GET['day']) : date('j');
	
	header('Content-Type: application/json');
	$events = getEventsOnDay($year, $month, $day);
	echo json_encode($events);
	exit;
}

$year = isset($_GET["year"]) ? intval($_GET["year"]) : date('Y');
$month = isset($_GET["month"]) ? intval($_GET["month"]) : date('n');
$day = isset($_GET["day"]) ? intval($_GET["day"]) : date('j');
$no = isset($_GET["no"]) ? intval($_GET["no"]) : 0;

// Check if this is a new event (no=0 or action=new)
$isNewEvent = ($no == 0 || (isset($_GET['action']) && $_GET['action'] == 'new'));

// Check if this is a duplicate action
$isDuplicate = (isset($_GET['action']) && $_GET['action'] == 'duplicate');

// If duplicating, load source event data and set today's date
if ($isDuplicate && isset($_GET['source_year']) && isset($_GET['source_month']) && isset($_GET['source_day']) && isset($_GET['source_no'])) {
	$sourceYear = intval($_GET['source_year']);
	$sourceMonth = intval($_GET['source_month']);
	$sourceDay = intval($_GET['source_day']);
	$sourceNo = intval($_GET['source_no']);
	
	// Load source event data
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
				// Found the source event - store it
				$duplicateSourceData = $tempData;
				break;
			}
		}
		fclose($sourceFile);
	}
	
	// Set to today's date for the duplicate
	$year = date('Y');
	$month = date('n');
	$day = date('j');
	$no = 0; // New event
	$isNewEvent = true;
}

$message = "";
$messageType = "";
$backupCreated = "";

// ============================================
// EDIT LOCK HANDLING
// ============================================
$editLockWarning = '';
$publicationStatus = '';

// ============================================
// DETERMINE PUBLICATION STATUS
// ============================================
if ($isNewEvent) {
	$publicationStatus = '<span style="color: #856404;">📝 Unpublished (new)</span>';
} else {
	// Check if event has been edited (has an update timestamp)
	if (isset($data[15]) && !empty($data[15])) {
		$updateTime = htmlspecialchars($data[15]);
		$publicationStatus = '<span style="color: #0c5460;">✓ Published - Edited on ' . $updateTime . '</span>';
	} else {
		// Published but never edited
		$publicationStatus = '<span style="color: #155724;">✓ Published</span>';
	}
}
$currentLock = null;

// Handle lock refresh via AJAX
if (isset($_GET['refresh_lock']) && !$isNewEvent) {
	header('Content-Type: application/json');
	$refreshed = refreshEditLock($year, $month, $day, $no, $_SESSION['username']);
	echo json_encode(array('success' => $refreshed, 'remaining' => LOCK_TIMEOUT));
	exit;
}

// Handle explicit lock release
if (isset($_GET['release_lock']) && !$isNewEvent) {
	$currentLock = getEditLock($year, $month, $day, $no);
	if ($currentLock && $currentLock['user'] === $_SESSION['username']) {
		releaseEditLock($year, $month, $day, $no);
	}
	// Redirect back to calendar
	header('Location: $' . sprintf('%02d-%d.php', $month, $year));
	exit;
}

// Check for existing lock when editing (not new events)
if (!$isNewEvent) {
	$currentLock = getEditLock($year, $month, $day, $no);
	
	if ($currentLock && $currentLock['user'] !== $_SESSION['username']) {
		// Someone else is editing
		$editLockWarning = sprintf(
			'⚠️ Currently being edited by <strong>%s</strong> (started %s)',
			htmlspecialchars($currentLock['user']),
			htmlspecialchars($currentLock['timestamp'])
		);
	} else {
		// Acquire or refresh our lock
		acquireEditLock($year, $month, $day, $no, $_SESSION['username']);
	}
}

// Get available icons for dropdown
$availableIcons = getIconFiles($year);

// ============================================
// Handle ICON UPLOAD
// ============================================
$uploadedIcon = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['icon_upload']) && $_FILES['icon_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
	// Verify CSRF token
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	} else {
		$uploadError = '';
		$file = $_FILES['icon_upload'];
		
		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK) {
			$uploadError = 'Upload failed. Error code: ' . $file['error'];
		}
		
		// Check file size (max 500KB)
		if (empty($uploadError) && $file['size'] > 500 * 1024) {
			$uploadError = 'File too large. Maximum size is 500 KB.';
		}
		
		// Check file extension
		$allowedExts = array('png', 'jpg', 'jpeg', 'svg');
		$filename = strtolower($file['name']);
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		if (empty($uploadError) && !in_array($ext, $allowedExts)) {
			$uploadError = 'Invalid file type. Allowed: PNG, JPG, SVG.';
		}
		
		// Check MIME type
		if (empty($uploadError)) {
			$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
			if ($finfo) {
				$mimeType = finfo_file($finfo, $file['tmp_name']);
				finfo_close($finfo);
			} else {
				$mimeType = $file['type'];
			}
			$allowedMimes = array('image/png', 'image/jpeg', 'image/svg+xml');
			if (!in_array($mimeType, $allowedMimes)) {
				$uploadError = 'Invalid file type. Must be a valid image.';
			}
		}
		
		// Check dimensions for PNG/JPG (not SVG)
		if (empty($uploadError) && in_array($ext, array('png', 'jpg', 'jpeg'))) {
			$imageInfo = getimagesize($file['tmp_name']);
			if ($imageInfo === false) {
				$uploadError = 'Could not read image dimensions.';
			} else {
				$width = $imageInfo[0];
				$height = $imageInfo[1];
				
				// Check if resizing is needed (max 200x150)
				if ($width > 200 || $height > 150) {
					// Try to resize using GD
					if (function_exists('imagecreatetruecolor')) {
						// Calculate new dimensions (scale to fit 200x150)
						$ratio = min(200 / $width, 150 / $height);
						$newWidth = round($width * $ratio);
						$newHeight = round($height * $ratio);
						
						// Create new image
						$newImage = imagecreatetruecolor($newWidth, $newHeight);
						
						// Handle transparency for PNG
						if ($ext == 'png') {
							imagealphablending($newImage, false);
							imagesavealpha($newImage, true);
							$transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
							imagefill($newImage, 0, 0, $transparent);
						}
						
						// Load source image
						if ($ext == 'png') {
							$srcImage = imagecreatefrompng($file['tmp_name']);
						} else {
							$srcImage = imagecreatefromjpeg($file['tmp_name']);
						}
						
						if ($srcImage) {
							// Resize
							imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
							
							// Save to temp file
							$tempFile = $file['tmp_name'] . '_resized';
							if ($ext == 'png') {
								imagepng($newImage, $tempFile);
							} else {
								imagejpeg($newImage, $tempFile, 90);
							}
							
							imagedestroy($srcImage);
							imagedestroy($newImage);
							
							// Use resized file
							$file['tmp_name'] = $tempFile;
						} else {
							$uploadError = 'Could not process image for resizing.';
						}
					} else {
						$uploadError = 'Image too large (max 200x150). Server cannot resize - please resize before uploading.';
					}
				}
			}
		}
		
		// Move file to year directory (calendar/YYYY/)
		if (empty($uploadError)) {
			// Year directory should already exist (contains event.php)
			$yearDir = dirname(__FILE__) . '/' . $year;
			if (!is_dir($yearDir)) {
				$uploadError = 'Year directory does not exist: ' . $year;
			}
			
			if (empty($uploadError)) {
				// Sanitize filename
				$safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($file['name']));
				$destPath = $yearDir . '/' . $safeName;
				
				if (move_uploaded_file($file['tmp_name'], $destPath)) {
					$uploadedIcon = $safeName;
					$message = "Icon uploaded successfully to " . $year . "/: " . $safeName;
					$messageType = "success";
					// Refresh available icons
					$availableIcons = getIconFiles($year);
				} else {
					$uploadError = 'Failed to save uploaded file.';
				}
			}
			
			// Clean up temp resized file if it exists
			if (isset($tempFile) && file_exists($tempFile)) {
				unlink($tempFile);
			}
		}
		
		if (!empty($uploadError)) {
			$message = "Icon upload error: " . $uploadError;
			$messageType = "error";
		}
	}
}

// ============================================
// Handle DELETE action
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete']) && !$isNewEvent) {
	// Verify CSRF token
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	} else {
		$filename = sprintf("../android/%d-%02d.txt", $year, $month);
		
		// Create backup before deleting
		$eventInfo = "DELETE day=$day no=$no";
		$backupFile = createBackup($filename, $_SESSION['username'], $eventInfo);
		if ($backupFile) {
			$backupCreated = "Backup created: " . basename($backupFile);
		} else {
			$message = "Warning: Could not create backup file. Delete cancelled for safety.";
			$messageType = "error";
		}
		
		// Only proceed with delete if backup was successful
		if (!$backupFile) {
		// Skip delete - backup failed
	} else {
		// Read and process file
	$lines = file($filename, FILE_IGNORE_NEW_LINES);
	$newLines = array();
	$n = 0;
	$lastDay = "";
	$deleted = false;
	
	foreach ($lines as $index => $line) {
		if ($index == 0) {
			$newLines[] = $line; // Keep header
			continue;
		}
		
		$data = explode("\t", $line);
		$d = isset($data[0]) ? $data[0] : '';
		
		if ($d != $lastDay) {
			$n = 1;
		} else {
			$n += 1;
		}
		$lastDay = $d;
		
		// Skip the line we want to delete
		if ($d == $day && $n == $no) {
			$deleted = true;
			continue;
		}
		
		$newLines[] = $line;
	}
	
	if ($deleted) {
		$result = file_put_contents($filename, implode("\n", $newLines));
		if ($result !== false) {
			// Redirect to calendar after delete
			header('Location: /calendar');
			exit;
		} else {
			$message = "Error: Unable to delete event. Check file permissions.";
			$messageType = "error";
		}
	}
	} // end backup success check
	} // end CSRF check
}

// ============================================
// Handle form submission for NEW event
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save']) && $isNewEvent) {
	// Verify CSRF token
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	// Validate year range (current year through 2050)
	} else if (isset($_POST['year'])) {
		$submittedYear = intval($_POST['year']);
		$currentYear = date('Y');
		if ($submittedYear < $currentYear || $submittedYear > 2050) {
			$message = "Year must be between " . $currentYear . " and 2050.";
			$messageType = "error";
		} else {
			// Update year from POST if valid
			$year = $submittedYear;
			$month = isset($_POST['month']) ? intval($_POST['month']) : $month;
		}
	}
	
	// Only proceed if no errors so far
	if (empty($message)) {
	// Validate time format for EditHash 1.28 compatibility
	if (!empty($_POST['time']) && !validateTimeFormat($_POST['time'])) {
		$message = "Time must start with format \"H:MM AM\" or \"H:MM PM\" (e.g., \"7:00 PM\" or \"2:00 PM Trail starts\") for compatibility with EditHash 1.28 app.";
		$messageType = "error";
	} else {
		$filename = sprintf("../android/%d-%02d.txt", $year, $month);
		
		// Strip slashes from POST data if magic_quotes_gpc is enabled (PHP 5.2 issue)
		if (get_magic_quotes_gpc()) {
			$_POST = stripslashes_deep($_POST);
		}
		
		// Sanitize all inputs
		$_POST = sanitizeInput($_POST);
		
		// Handle custom kennel input
		if (isset($_POST['kennel']) && $_POST['kennel'] === '--- New Kennel ---') {
			if (!empty($_POST['kennel_custom'])) {
				$_POST['kennel'] = trim($_POST['kennel_custom']);
			} else {
				$message = "Please enter a name for the new kennel.";
				$messageType = "error";
			}
		}
		
		// Only proceed if no kennel error
		if (empty($message)) {
		
		// Get the day from POST (user can select it for new events)
		$day = intval($_POST['day']);
		
		// Create backup before editing (if file exists)
		if (file_exists($filename)) {
			$eventInfo = "NEW day=$day kennel=" . $_POST['kennel'];
			$backupFile = createBackup($filename, $_SESSION['username'], $eventInfo);
			if ($backupFile) {
				$backupCreated = "Backup created: " . basename($backupFile);
			} else {
				// For new events, we can proceed even if backup fails (file might not exist yet)
				error_log("Warning: Could not create backup for new event, proceeding anyway");
			}
		}
		
		// Build Trail Type line
		$trailTypeLine = '';
		if (isset($_POST['trailtype']) && !empty($_POST['trailtype'])) {
			$trailTypeLine = '<br /><br />Trail Type: ' . $_POST['trailtype'];
		}
		
		// Build Bring line from checkboxes
		$bringLine = '';
		if (isset($_POST['bring']) && is_array($_POST['bring']) && count($_POST['bring']) > 0) {
			$bringLine = '<br /><br />Bring: ' . implode(', ', $_POST['bring']);
		}
		
		// Build description - sanitize HTML (allow safe tags, remove XSS)
		$desc = sanitizeHtml($_POST['desc']);
		$desc = str_replace("\r\n", "<br />", $desc);
		$desc = str_replace("\n", "<br />", $desc);
		$desc = str_replace("\r", "<br />", $desc);
		
		// Append Trail Type and Bring lines to description
		$desc = $desc . $trailTypeLine . $bringLine;
		
		// Get address for weather lookup
		$address = $_POST['address'];
		$weatherLocation = '';
	
	if (preg_match('/(\d{5})(?:-\d{4})?/', $address, $matches)) {
		$weatherLocation = $matches[1];
	} else if (preg_match('/([A-Za-z\s]+),?\s*(?:TX|Texas)/i', $address, $matches)) {
		$cityName = trim($matches[1]);
		$cityName = preg_replace('/[^A-Za-z\s]/', '', $cityName);
		$cityName = trim($cityName);
		if (!empty($cityName)) {
			$weatherLocation = $cityName . ', TX';
		}
	}
	
	if (empty($weatherLocation)) {
		$weatherLocation = 'Addison, TX';
	}
	
	$weatherUrl = 'https://forecast.weather.gov/zipcity.php?inputstring=' . urlencode($weatherLocation);
	
	$weatherWidget = '<!-- WEATHER_START --><br /><br /><strong>Weather Forecast for ' . htmlspecialchars($weatherLocation) . ':</strong><br />';
	$weatherWidget .= '<iframe src="' . $weatherUrl . '" width="100%" height="600" frameborder="0" scrolling="yes" style="border: 1px solid #ccc;"></iframe>';
	$weatherWidget .= '<!-- WEATHER_END -->';
	
	$desc .= $weatherWidget;
	
	// Sanitize and convert address line breaks
	$address = sanitizeHtml($address);
	$address = str_replace("\r\n", "<br />", $address);
	$address = str_replace("\n", "<br />", $address);
	$address = str_replace("\r", "<br />", $address);
	
	// Auto-generate the date string
	$autoDate = generateDateString($day, $month, $year);
	
	// Get twilight time
	$twilight = getTwilightEnd($day, $month, $year);
	
	// Validate maplink URL
	$maplink = validateUrl($_POST['maplink']);
	
	// Get RSVP setting
	$rsvpEnabled = isset($_POST['rsvp_enabled']) ? '1' : '';
	
	// Build new event line
	$newEventData = array(
		$day,
		sanitizeHtml($_POST['kennel']),
		sanitizeHtml($_POST['type']),
		sanitizeHtml($_POST['title']),
		sanitizeHtml($_POST['run']),
		sanitizeHtml($_POST['hares']),
		sanitizeHtml($_POST['time']),
		$address,
		$maplink,
		sanitizeHtml($_POST['hashcash']),
		sanitizeHtml($_POST['turds']),
		'', // tweet
		$twilight, // twilight
		$autoDate,
		$desc,
		date('n/j/y G:i') . ' (' . $_SESSION['username'] . ') ' . EDITPHP_VERSION,
		$rsvpEnabled
	);
	$newEventLine = implode("\t", $newEventData);
	
	// Read existing file or create new one
	$lines = array();
	$header = "DAY\tKENNEL\tICON\tTITLE\tRUN\tHARES\tTIME\tSTART\tMAP\tHASHCASH\tTURDs\tTWEET\tTWILIGHT\tDATE\tDESC\tUPDATE";
	
	if (file_exists($filename)) {
		$lines = file($filename, FILE_IGNORE_NEW_LINES);
	} else {
		$lines[] = $header;
	}
	
	// Find the right position to insert (sorted by day)
	$insertIndex = 1; // After header
	$eventNumber = 1;
	
	for ($i = 1; $i < count($lines); $i++) {
		$lineData = explode("\t", $lines[$i]);
		$lineDay = isset($lineData[0]) ? intval($lineData[0]) : 0;
		
		if ($lineDay < $day) {
			$insertIndex = $i + 1;
		} else if ($lineDay == $day) {
			$insertIndex = $i + 1;
			$eventNumber++;
		} else {
			break;
		}
	}
	
	// Update edit links with correct event number
	$newEventLine = str_replace('no=%d', 'no=' . $eventNumber, $newEventLine);
	
	// Insert the new line
	array_splice($lines, $insertIndex, 0, $newEventLine);
	
	// Write back to file
	$result = file_put_contents($filename, implode("\n", $lines));
	if ($result !== false) {
		// Redirect to event.php (lives in calendar/YYYY/ directory)
		$eventUrl = sprintf(
			'%d/event.php?year=%d&month=%d&day=%d&no=%d',
			$year, $year, $month, $day, $eventNumber
		);
		header('Location: ' . $eventUrl);
		exit;
	} else {
		$message = "Error: Unable to write to file. Check file permissions.";
		$messageType = "error";
	}
	} // end kennel validation check
	} // end validation check
	} // end CSRF check
}

// ============================================
// Handle form submission for EDITING existing event
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save']) && !$isNewEvent) {
	// Verify CSRF token
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	// Validate time format for EditHash 1.28 compatibility
	} else if (!empty($_POST['time']) && !validateTimeFormat($_POST['time'])) {
		$message = "Time must start with format \"H:MM AM\" or \"H:MM PM\" (e.g., \"7:00 PM\" or \"2:00 PM Trail starts\") for compatibility with EditHash 1.28 app.";
		$messageType = "error";
	} else {
		$filename = sprintf("../android/%d-%02d.txt", $year, $month);
		
		// Strip slashes from POST data if magic_quotes_gpc is enabled (PHP 5.2 issue)
		if (get_magic_quotes_gpc()) {
			$_POST = stripslashes_deep($_POST);
		}
		
		// Sanitize all inputs
		$_POST = sanitizeInput($_POST);
		
		// Handle custom kennel input
		if (isset($_POST['kennel']) && $_POST['kennel'] === '--- New Kennel ---') {
			if (!empty($_POST['kennel_custom'])) {
				$_POST['kennel'] = trim($_POST['kennel_custom']);
			} else {
				$message = "Please enter a name for the new kennel.";
				$messageType = "error";
			}
		}
		
		// Only proceed if no kennel error
		if (empty($message)) {
		
		// Create backup before editing
		$eventInfo = "EDIT day=$day no=$no kennel=" . $_POST['kennel'];
		$backupFile = createBackup($filename, $_SESSION['username'], $eventInfo);
		if ($backupFile) {
			$backupCreated = "Backup created: " . basename($backupFile);
		} else {
			$message = "Warning: Could not create backup file.";
			$messageType = "error";
		}
		
		// RELOAD the file to get the latest version before writing
		$lines = file($filename, FILE_IGNORE_NEW_LINES);
		$newLines = array();
		$n = 0;
		$lastDay = "";
		$targetLineIndex = -1;
		
		// Find the target line
		foreach ($lines as $index => $line) {
			if ($index == 0) {
				$newLines[] = $line; // Keep header
				continue;
			}
			
			$data = explode("\t", $line);
			$d = isset($data[0]) ? $data[0] : '';
			
			if ($d != $lastDay) {
				$n = 1;
			} else {
				$n += 1;
			}
			$lastDay = $d;
			
			if ($d == $day && $n == $no) {
				$targetLineIndex = $index;
				
				// Build Trail Type line
				$trailTypeLine = '';
				if (isset($_POST['trailtype']) && !empty($_POST['trailtype'])) {
					$trailTypeLine = '<br /><br />Trail Type: ' . $_POST['trailtype'];
				}
				
				// Build Bring line from checkboxes
				$bringLine = '';
				if (isset($_POST['bring']) && is_array($_POST['bring']) && count($_POST['bring']) > 0) {
					$bringLine = '<br /><br />Bring: ' . implode(', ', $_POST['bring']);
				}
			
			// Build description - sanitize HTML (allow safe tags, remove XSS)
			$desc = sanitizeHtml($_POST['desc']);
			$desc = str_replace("\r\n", "<br />", $desc);
			$desc = str_replace("\n", "<br />", $desc);
			$desc = str_replace("\r", "<br />", $desc);
			
			// Append Trail Type and Bring lines to description
			$desc = $desc . $trailTypeLine . $bringLine;
			
			// Get address for weather lookup
			$address = $_POST['address'];
			$weatherLocation = '';
			
			if (preg_match('/(\d{5})(?:-\d{4})?/', $address, $matches)) {
				$weatherLocation = $matches[1];
			} else if (preg_match('/([A-Za-z\s]+),?\s*(?:TX|Texas)/i', $address, $matches)) {
				$cityName = trim($matches[1]);
				$cityName = preg_replace('/[^A-Za-z\s]/', '', $cityName);
				$cityName = trim($cityName);
				if (!empty($cityName)) {
					$weatherLocation = $cityName . ', TX';
				}
			}
			
			if (empty($weatherLocation)) {
				$weatherLocation = 'Addison, TX';
			}
			
			$weatherUrl = 'https://forecast.weather.gov/zipcity.php?inputstring=' . urlencode($weatherLocation);
			
			$weatherWidget = '<!-- WEATHER_START --><br /><br /><strong>Weather Forecast for ' . htmlspecialchars($weatherLocation) . ':</strong><br />';
			$weatherWidget .= '<iframe src="' . $weatherUrl . '" width="100%" height="600" frameborder="0" scrolling="yes" style="border: 1px solid #ccc;"></iframe>';
			$weatherWidget .= '<!-- WEATHER_END -->';
			
			$desc .= $weatherWidget;
			
			// Sanitize and convert address line breaks
			$address = sanitizeHtml($address);
			$address = str_replace("\r\n", "<br />", $address);
			$address = str_replace("\n", "<br />", $address);
			$address = str_replace("\r", "<br />", $address);
			
			$autoDate = generateDateString($day, $month, $year);
			
			// Validate maplink URL
			$maplink = validateUrl($_POST['maplink']);
			
			// Get RSVP setting
			$rsvpEnabled = isset($_POST['rsvp_enabled']) ? '1' : '';
			
			// Sanitize all fields to remove XSS while allowing safe HTML
			$updatedData = array(
				$day,
				sanitizeHtml($_POST['kennel']),
				sanitizeHtml($_POST['type']),
				sanitizeHtml($_POST['title']),
				sanitizeHtml($_POST['run']),
				sanitizeHtml($_POST['hares']),
				sanitizeHtml($_POST['time']),
				$address,
				$maplink,
				sanitizeHtml($_POST['hashcash']),
				sanitizeHtml($_POST['turds']),
				'',
				'',
				$autoDate,
				$desc,
				date('n/j/y G:i') . ' (' . $_SESSION['username'] . ') ' . EDITPHP_VERSION,
				$rsvpEnabled
			);
			$updatedLine = implode("\t", $updatedData);
			$newLines[] = $updatedLine;
		} else {
			$newLines[] = $line;
		}
	}
	
	// Write back to file
	if ($targetLineIndex >= 0) {
		$result = file_put_contents($filename, implode("\n", $newLines));
		if ($result !== false) {
			// Release edit lock
			releaseEditLock($year, $month, $day, $no);
			$eventUrl = sprintf(
				'%d/event.php?year=%d&month=%d&day=%d&no=%d',
				$year, $year, $month, $day, $no
			);
			header('Location: ' . $eventUrl);
			exit;
		} else {
			$message = "Error: Unable to write to file. Check file permissions.";
			$messageType = "error";
		}
	}
	} // end kennel validation check
	} // end CSRF check
}

// Load current event data (for editing existing events)
$data = array('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '');
$eventsOnThisDay = 0;

// If duplicating, use the source data but clear run number
if ($isDuplicate && isset($duplicateSourceData)) {
	$data = $duplicateSourceData;
	$data[4] = ''; // Clear run number
	// Keep all other fields from source event
} else if (!$isNewEvent) {
	$filename = sprintf("../android/%d-%02d.txt", $year, $month);
	
	// Count events on this day for prev/next navigation
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
	
	// Strip slashes from data if magic_quotes_gpc is enabled
	if (get_magic_quotes_gpc()) {
		$data = stripslashes_deep($data);
	}
	
	// Find prev/next events for the same kennel
	$kennelEvents = findKennelEvents($filename, $day, $no, isset($data[1]) ? trim($data[1]) : '');
}

//DAY = 0 KENNEL = 1 TYPE = 2 TITLE = 3 RUN = 4 HARES = 5 TIME = 6 ADDRESS = 7 
//MAPLINK = 8 HASHCASH = 9 TURDS = 10 TWEET = 11 TWILIGHT = 12 DATE = 13 DESC = 14 UPDATED = 15 RSVP = 16

$kennel = isset($data[1]) ? $data[1] : '';
$dateDisplay = isset($data[13]) && strlen($data[13]) > 0 ? $data[13] : generateDateString($day, $month, $year);
$currentIcon = isset($data[2]) ? trim($data[2]) : '';
$currentTurds = isset($data[10]) ? trim($data[10]) : '';
$currentRsvp = isset($data[16]) ? trim($data[16]) : '';

// Get days in the selected month (for new event day selector)
$daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

$pageTitle = $isNewEvent ? "Add New Event" : "Edit Event";
$formTitle = $isNewEvent ? "Add New Event" : "Edit Event";

// Calculate prev/next event numbers
$hasPrev = (!$isNewEvent && $no > 1);
$hasNext = (!$isNewEvent && $no < $eventsOnThisDay);
$prevNo = $no - 1;
$nextNo = $no + 1;
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
		.form-group input, .form-group textarea, .form-group select { 
			width: 100%; 
			padding: 8px; 
			box-sizing: border-box; 
		}
		.form-group textarea { min-height: 100px; }
		.btn { 
			padding: 10px 20px; 
			margin: 5px; 
			cursor: pointer; 
			font-size: 16px; 
			text-decoration: none;
			display: inline-block;
		}
		.btn-save { background: #4CAF50; color: white; border: none; }
		.btn-cancel { background: #f44336; color: white; border: none; }
		.btn-logout { background: #666; color: white; border: none; float: right; }
		.btn-new { background: #2196F3; color: white; border: none; }
		.btn-delete { background: #dc3545; color: white; border: none; }
		.btn-duplicate { background: #17a2b8; color: white; border: none; }
		.btn-duplicate:hover { background: #138496; }
		.btn-recover { background: #ff9800; color: white; border: none; }
		.btn-recover:hover { background: #e68900; }
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
		.nav-links a:hover { text-decoration: underline; }
		.icon-preview { 
			display: inline-block; 
			vertical-align: middle; 
			margin-left: 10px;
			max-height: 30px;
		}
		.date-selectors { display: flex; gap: 10px; }
		.date-selectors select { width: auto; flex: 1; }
		.new-event-banner {
			background: #e3f2fd;
			border: 1px solid #2196F3;
			color: #1565c0;
			padding: 10px;
			border-radius: 3px;
			margin-bottom: 15px;
		}
		.event-nav {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
			padding: 10px;
			background: #f8f9fa;
			border-radius: 3px;
		}
		.event-nav-info {
			font-size: 14px;
			color: #666;
		}
		.action-buttons {
			margin-top: 20px;
			padding-top: 15px;
			border-top: 1px solid #ddd;
		}
		.delete-section {
			margin-top: 20px;
			padding: 15px;
			background: #fff3cd;
			border: 1px solid #ffc107;
			border-radius: 3px;
		}
		.delete-section h4 {
			margin-top: 0;
			color: #856404;
		}
		.checkbox-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
			gap: 8px;
			padding: 10px;
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-radius: 3px;
		}
		.checkbox-label {
			display: flex;
			align-items: center;
			font-weight: normal;
			cursor: pointer;
		}
		.checkbox-label input[type="checkbox"] {
			width: auto;
			margin-right: 6px;
		}
		.kennel-nav {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
			padding: 10px;
			background: #e8f4e8;
			border: 1px solid #4CAF50;
			border-radius: 3px;
		}
		.kennel-nav-info {
			font-size: 14px;
			color: #2e7d32;
			font-weight: bold;
		}
	</style>
	
	<script>
	var formChanged = false;
	
	// Session timeout countdown
	var sessionTimeout = <?php echo LOCK_TIMEOUT; ?>; // 30 minutes in seconds
	var sessionStart = <?php echo time(); ?>;
	var isNewEvent = <?php echo $isNewEvent ? 'true' : 'false'; ?>;
	
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
			// Show warning color when under 5 minutes
			if (remaining < 300) {
				timerEl.innerHTML = '<span style="color: #d9534f;">⏱️ ' + display + '</span>';
			} else {
				timerEl.innerHTML = '⏱️ ' + display;
			}
		}
	}
	
	// Refresh the edit lock periodically (every 5 minutes)
	function refreshLock() {
		if (isNewEvent) return;
		
		var xhr = new XMLHttpRequest();
		xhr.open('GET', window.location.pathname + '?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>&refresh_lock=1', true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4 && xhr.status === 200) {
				// Lock refreshed - reset session timer
				sessionStart = Math.floor(Date.now() / 1000);
			}
		};
		xhr.send();
	}
	
	// Start timers when page loads
	window.onload = function() {
		// Update session timer every second
		setInterval(updateSessionTimer, 1000);
		updateSessionTimer();
		
		// Refresh lock every 5 minutes
		if (!isNewEvent) {
			setInterval(refreshLock, 300000);
		}
	};
	
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
		var kennelCustom = document.getElementById('kennelCustom');
		var iconSelect = document.getElementById('iconSelect');
		var selectedOption = kennelSelect.options[kennelSelect.selectedIndex];
		
		if (kennelSelect.value === '--- New Kennel ---') {
			// Show custom kennel input
			kennelCustom.style.display = 'block';
			kennelCustom.required = true;
			kennelSelect.required = false;
		} else {
			// Hide custom kennel input
			kennelCustom.style.display = 'none';
			kennelCustom.required = false;
			kennelSelect.required = true;
			
			// Auto-select default icon if available
			var defaultIcon = selectedOption.getAttribute('data-icon');
			if (defaultIcon) {
				// Find and select the matching icon in the dropdown
				for (var i = 0; i < iconSelect.options.length; i++) {
					if (iconSelect.options[i].value === defaultIcon) {
						iconSelect.selectedIndex = i;
						updateIconPreview();
						break;
					}
				}
			}
		}
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
		
		// Calculate day of week
		var date = new Date(year, month - 1, day);
		var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		var dayName = days[date.getDay()];
		
		dayOfWeekSpan.textContent = 'This is a ' + dayName + '. ';
		
		// Fetch events on this day via AJAX
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
		// Get address from textarea
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
		
		// Clean up address for URL encoding
		// Remove line breaks and extra spaces
		address = address.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
		
		// Generate Google Maps link
		var googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address);
		
		// Set the map link field
		maplinkField.value = googleMapsUrl;
		
		// Mark form as changed
		formChanged = true;
		
		// Visual feedback
		maplinkField.style.background = '#d4edda';
		setTimeout(function() {
			maplinkField.style.background = '';
		}, 1000);
	}
	
	function confirmDelete() {
		return confirm('Are you sure you want to delete this event?\n\nThis action cannot be undone (but a backup will be created).');
	}
	
	function confirmRecovery() {
		var message = '⚠️ EVENT RECOVERY TOOL\n\n' +
			'This tool is designed to restore events that have been:\n' +
			'• Accidentally deleted\n' +
			'• Accidentally reverted to an earlier version\n' +
			'• Lost due to editing errors\n\n' +
			'DO NOT USE if:\n' +
			'• You are just browsing\n' +
			'• The event data is currently correct\n' +
			'• You want to modify an existing event (use Edit instead)\n\n' +
			'The recovery tool compares current data against backups to find missing or reduced information.\n\n' +
			'Continue to Event Recovery?';
		
		if (confirm(message)) {
			// Mark form as unchanged so we don't get the "unsaved changes" warning
			formChanged = false;
			return true;
		}
		return false;
	}
	
	// Track form changes
	function markChanged() {
		formChanged = true;
	}
	
	// Warn before leaving if there are unsaved changes
	window.onbeforeunload = function(e) {
		if (formChanged) {
			var message = 'You have unsaved changes. Are you sure you want to leave?';
			e.returnValue = message;
			return message;
		}
	};
	
	// Don't warn when submitting the form
	function allowLeave() {
		formChanged = false;
		return true;
	}
	
	// Attach change listeners after page loads
	window.onload = function() {
		var form = document.getElementById('editForm');
		if (form) {
			var inputs = form.querySelectorAll('input, textarea, select');
			for (var i = 0; i < inputs.length; i++) {
				inputs[i].addEventListener('change', markChanged);
				inputs[i].addEventListener('keyup', markChanged);
			}
		}
		
		// Add date preview update listeners for new events
		var monthSelect = document.getElementById('monthSelect');
		var daySelect = document.getElementById('daySelect');
		var yearSelect = document.getElementById('yearSelect');
		
		if (monthSelect && daySelect && yearSelect) {
			monthSelect.addEventListener('change', updateDatePreview);
			daySelect.addEventListener('change', updateDatePreview);
			yearSelect.addEventListener('change', updateDatePreview);
			
			// Initial update
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
			<div class="event-nav-info">
				Event <?php echo $no; ?> of <?php echo $eventsOnThisDay; ?> on this day
			</div>
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
			<div class="kennel-nav-info">
				<?php echo htmlspecialchars($kennel); ?> Events
			</div>
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
				<?php if ($backupCreated): ?>
					<div class="backup-info"><?php echo $backupCreated; ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<?php if (!$message && file_exists(BACKUP_DIR)): ?>
			<div class="info">
				ℹ️ Automatic backup will be created before saving changes.
			</div>
		<?php endif; ?>
		
		<form method="POST" action="" id="editForm" enctype="multipart/form-data" onsubmit="return allowLeave();">
			<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
			<?php if ($isNewEvent): ?>
			<div class="form-group">
				<label>Date:</label>
				<div class="date-selectors">
					<select name="month" id="monthSelect">
						<?php for ($m = 1; $m <= 12; $m++): ?>
						<option value="<?php echo $m; ?>" <?php echo ($m == $month) ? 'selected' : ''; ?>>
							<?php echo date('F', mktime(0, 0, 0, $m, 1, $year)); ?>
						</option>
						<?php endfor; ?>
					</select>
					<select name="day" id="daySelect">
						<?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
						<option value="<?php echo $d; ?>" <?php echo ($d == $day) ? 'selected' : ''; ?>>
							<?php echo $d; ?>
						</option>
						<?php endfor; ?>
					</select>
					<select name="year" id="yearSelect">
						<?php 
						$currentYear = date('Y');
						for ($y = $currentYear; $y <= 2050; $y++): ?>
						<option value="<?php echo $y; ?>" <?php echo ($y == $year) ? 'selected' : ''; ?>>
							<?php echo $y; ?>
						</option>
						<?php endfor; ?>
					</select>
				</div>
				<small style="color: #666;">You can create events from <?php echo $currentYear; ?> through 2050.</small>
			<div id="datePreview" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
				<strong id="dayOfWeek"></strong>
				<span id="otherEvents"></span>
			</div>
			</div>
			<?php endif; ?>
			
			<div class="form-group">
				<label>Kennel:</label>
				<select name="kennel" id="kennelSelect" onchange="updateKennelSelection()" required>
					<option value="">-- Select Kennel --</option>
					<?php 
					$kennelList = getKennelList($month, $year);
					$currentKennel = isset($data[1]) ? trim($data[1]) : '';
					$kennelFound = false;
					foreach ($kennelList as $kennelName => $defaultIcon): 
						$selected = ($currentKennel == $kennelName) ? 'selected' : '';
						if ($selected) $kennelFound = true;
					?>
					<option value="<?php echo htmlspecialchars($kennelName); ?>" data-icon="<?php echo htmlspecialchars($defaultIcon); ?>" <?php echo $selected; ?>>
						<?php echo htmlspecialchars($kennelName); ?>
					</option>
					<?php endforeach; ?>
					<option value="--- New Kennel ---">--- New Kennel ---</option>
					<?php if (!$kennelFound && !empty($currentKennel)): ?>
					<option value="<?php echo htmlspecialchars($currentKennel); ?>" selected>
						<?php echo htmlspecialchars($currentKennel); ?> (custom)
					</option>
					<?php endif; ?>
				</select>
				<input type="text" name="kennel_custom" id="kennelCustom" placeholder="Enter new kennel name" style="display: none; margin-top: 5px;">
			</div>
			
			<div class="form-group">
				<label>Icon:</label>
				<select name="type" id="iconSelect" onchange="updateIconPreview()">
					<option value="">-- Select Icon --</option>
					<?php foreach ($availableIcons as $icon): ?>
					<option value="<?php echo htmlspecialchars($icon); ?>" <?php echo ($currentIcon == $icon || $uploadedIcon == $icon) ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($icon); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<img id="iconPreview" class="icon-preview" src="<?php echo !empty($currentIcon) ? htmlspecialchars($year . '/' . $currentIcon) : ''; ?>" style="<?php echo empty($currentIcon) ? 'display:none;' : ''; ?>" data-year="<?php echo $year; ?>">
				<div style="margin-top: 10px;">
					<label style="display: inline-block; padding: 8px 15px; background: #6c757d; color: white; border-radius: 5px; cursor: pointer; font-size: 14px;">
						📤 Upload Icon...
						<input type="file" name="icon_upload" accept=".png,.jpg,.jpeg,.svg" style="display: none;" onchange="this.form.submit();">
					</label>
					<small style="display: block; color: #666; margin-top: 5px;">PNG, JPG, or SVG. Max 200x150px, 500KB. Will auto-resize if needed.</small>
				</div>
				<?php if (empty($availableIcons)): ?>
				<small style="color: #999;">No icon files found in <?php echo $year; ?>/ folder.</small>
				<?php endif; ?>
			</div>
			
			<div class="form-group">
				<label>Title:</label>
				<input type="text" name="title" value="<?php echo htmlspecialchars($data[3]); ?>">
			</div>
			
			<div class="form-group">
				<label>Run Number:</label>
				<input type="text" name="run" value="<?php echo htmlspecialchars($data[4]); ?>">
			</div>
			
			<div class="form-group">
				<label>Hares:</label>
				<input type="text" name="hares" value="<?php echo htmlspecialchars($data[5]); ?>">
			</div>
			
			<div class="form-group">
				<label>Time:</label>
				<input type="text" name="time" value="<?php echo htmlspecialchars(isset($data[6]) ? $data[6] : '7:00 PM'); ?>" placeholder="7:00 PM">
				<small style="color: #666;">Must start with "H:MM AM" or "H:MM PM" (e.g., "7:00 PM" or "2:00 PM Trail starts"). Required for EditHash 1.28 compatibility.</small>
			</div>
			
			<div class="form-group">
				<label>Address:</label>
				<textarea name="address" id="addressField"><?php 
					$addr = isset($data[7]) ? $data[7] : '';
					$addr = str_replace("<br />", "\n", $addr);
					$addr = str_replace("<br/>", "\n", $addr);
					$addr = str_replace("<br>", "\n", $addr);
					echo htmlspecialchars($addr); 
				?></textarea>
			</div>
			
			<div class="form-group">
				<label>Map Link:</label>
				<div style="display: flex; gap: 10px; align-items: center;">
					<input type="text" name="maplink" id="maplinkInput" value="<?php echo htmlspecialchars(isset($data[8]) ? $data[8] : ''); ?>" style="flex: 1;">
					<button type="button" onclick="autoGenMapLink()" class="btn" style="background: #28a745; color: white; padding: 8px 15px; white-space: nowrap;">🗺️ AutoGen</button>
				</div>
				<small style="color: #666;">Click AutoGen to create a Google Maps link from the address above.</small>
			</div>
			
			<div class="form-group">
				<label>Hash Cash:</label>
				<input type="text" name="hashcash" value="<?php echo htmlspecialchars(isset($data[9]) ? $data[9] : ''); ?>">
			</div>
			
			<div class="form-group">
				<label>TURDs? (Dogs):</label>
				<select name="turds">
					<option value="" <?php echo ($currentTurds == '') ? 'selected' : ''; ?>>-- Select --</option>
					<option value="Yes" <?php echo ($currentTurds == 'Yes') ? 'selected' : ''; ?>>Yes</option>
					<option value="No" <?php echo ($currentTurds == 'No') ? 'selected' : ''; ?>>No</option>
					<option value="Trail-Only" <?php echo ($currentTurds == 'Trail-Only') ? 'selected' : ''; ?>>Trail-Only</option>
				</select>
			</div>
			
			<div class="form-group">
				<label>Description:</label>
				<textarea name="desc" rows="10" placeholder="What do the hounds need to know about this trail?"><?php 
					$desc = isset($data[14]) ? $data[14] : '';
					$desc = preg_replace('/<!-- WEATHER_START -->.*?<!-- WEATHER_END -->/s', '', $desc);
					// Remove existing Bring: line since it's now handled by checkboxes
					$desc = preg_replace('/Bring:\s*[^\n<]+\s*/i', '', $desc);
					// Remove existing Trail Type line since it's now handled by dropdown
					$desc = preg_replace('/Trail Type:\s*(A to A\'?|A to B)\s*/i', '', $desc);
					$desc = str_replace("<br />", "\n", $desc);
					$desc = str_replace("<br/>", "\n", $desc);
					$desc = str_replace("<br>", "\n", $desc);
					$desc = trim($desc);
					echo htmlspecialchars($desc); 
				?></textarea>
				<small style="color: #666;">Note: Weather forecast, edit link, and calendar invite are added automatically.</small>
			</div>
			
			<div class="form-group">
				<label>Trail Type:</label>
				<?php
				// Parse existing trail type from description
				$currentTrailType = '';
				$descText = isset($data[14]) ? $data[14] : '';
				if (preg_match('/Trail Type:\s*(A to A\'?|A to B)/i', $descText, $trailMatch)) {
					$currentTrailType = trim($trailMatch[1]);
				}
				?>
				<select name="trailtype">
					<option value="" <?php echo ($currentTrailType == '') ? 'selected' : ''; ?>>-- Select --</option>
					<option value="A to A" <?php echo ($currentTrailType == 'A to A') ? 'selected' : ''; ?>>A to A</option>
					<option value="A to A'" <?php echo ($currentTrailType == "A to A'") ? 'selected' : ''; ?>>A to A'</option>
					<option value="A to B" <?php echo ($currentTrailType == 'A to B') ? 'selected' : ''; ?>>A to B</option>
				</select>
			</div>
			
			<div class="form-group">
				<label class="checkbox-label" style="display: inline-flex; align-items: center; gap: 8px;">
					<input type="checkbox" name="rsvp_enabled" value="1" <?php echo ($currentRsvp == '1') ? 'checked' : ''; ?>>
					<span>✅ Allow early check-in (RSVP)</span>
				</label>
				<small style="display: block; color: #666; margin-top: 5px;">Removes time restriction so hashers can check in early as an RSVP.</small>
			</div>
			
			<div class="form-group">
				<label>Bring:</label>
				<div class="checkbox-grid">
					<?php
					$bringOptions = array(
						'Flashlight', 'Extra Shoes', 'Extra Clothes', 'Anti-Shiggy',
						'Glowsticks', 'Virgins', 'On-In $', 'Bug Spray',
						'Swimsuit', 'Birthday Suit', 'DART', 'Pre-lube',
						'Leash', 'Trash Bags', 'BYOB', 'BYOE',
						'Vessel', 'Bowl/Spoon', 'Cash', 'Side Dish', 'Chair'
					);
					
					// Parse existing bring items from description
					$existingBring = array();
					$descText = isset($data[14]) ? $data[14] : '';
					if (preg_match('/Bring:\s*([^<\n]+)/i', $descText, $bringMatch)) {
						$existingBring = array_map('trim', explode(',', $bringMatch[1]));
					}
					
					foreach ($bringOptions as $option):
						$checkboxId = 'bring_' . strtolower(str_replace(' ', '_', $option));
						$isChecked = in_array($option, $existingBring) ? 'checked' : '';
					?>
					<label class="checkbox-label">
						<input type="checkbox" name="bring[]" value="<?php echo htmlspecialchars($option); ?>" <?php echo $isChecked; ?>>
						<?php echo htmlspecialchars($option); ?>
					</label>
					<?php endforeach; ?>
				</div>
			</div>
			
			<div class="form-group">
				<button type="submit" name="save" class="btn btn-save">💾 <?php echo $isNewEvent ? 'Create Event' : 'Save Changes'; ?></button>
				<?php if ($isNewEvent): ?>
				<a href="/calendar" class="btn btn-cancel">❌ Cancel</a>
				<?php else: ?>
				<a href="<?php echo $year; ?>/event.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>" class="btn btn-cancel">❌ Cancel</a>
				<?php endif; ?>
			</div>
		</form>
		
		<?php if (!$isNewEvent): ?>
		<div class="action-buttons">
			<a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&action=new" class="btn btn-new">➕ Add New Event on This Day</a>
		</div>
		
		<div class="delete-section">
			<h4>⚠️ Danger Zone</h4>
			<p>Permanently delete this event. A backup will be created before deletion.</p>
			<form method="POST" action="" onsubmit="formChanged = false; return confirmDelete();">
				<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
				<button type="submit" name="delete" class="btn btn-delete">🗑️ Delete Event</button>
			</form>
			<br>
			<p>Create a copy of this event with today's date and no run number.</p>
			<form method="GET" action="edit.php" style="display: inline;">
				<input type="hidden" name="action" value="duplicate">
				<input type="hidden" name="source_year" value="<?php echo $year; ?>">
				<input type="hidden" name="source_month" value="<?php echo $month; ?>">
				<input type="hidden" name="source_day" value="<?php echo $day; ?>">
				<input type="hidden" name="source_no" value="<?php echo $no; ?>">
				<button type="submit" class="btn btn-duplicate">📋 Duplicate Event</button>
			</form>
			<br>
			<p>Restore a previously deleted event from backup.</p>
			<form method="GET" action="recover.php" onsubmit="return confirmRecovery();" style="display: inline;">
				<button type="submit" class="btn btn-recover">🔄 Event Recovery</button>
			</form>
		</div>
		
		<p><small>Last updated: <?php echo isset($data[15]) ? htmlspecialchars($data[15]) : 'N/A'; ?></small></p>
		<?php endif; ?>
	</div>
</body>
</html>