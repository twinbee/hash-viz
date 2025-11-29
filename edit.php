<?php
// ============================================
// MULTI-USER AUTHENTICATION CONFIGURATION
// ============================================
// Multiple users with hashed passwords
// Use password_hash_generator.php to generate new password hashes

// Array of users: username => md5_hash
$USERS = array(
	'kitchen' => 'fdf7c86c3904f5d41c7b22e38acfd84e',
	'bdb' => '1828c25220d3606e0ef9b8b704f46a88',
	'FruityPebbles' => 'b12cbb0934293c4ade0c4114b8b602da',
	'MBennett' => 'b599f1365e1832f5e66c8e88f8fede78',
	'Fourplay' => '4e6175b953b7488a33cafe71db52c3ae'
);

define('BACKUP_DIR', '../../android/backups/');

// Simple MD5 verification (works on any PHP version)
function verify_password($password, $hash) {
	return md5($password) === $hash;
}

session_start();

// Login handler
if (isset($_POST['login'])) {
	$username = $_POST['username'];
	$password = $_POST['password'];
	
	// Check if user exists and verify password
	if (isset($USERS[$username]) && verify_password($password, $USERS[$username])) {
		$_SESSION['authenticated'] = true;
		$_SESSION['username'] = $username;
		$_SESSION['login_time'] = time();
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
function createBackup($filename) {
	if (!file_exists(BACKUP_DIR)) {
		mkdir(BACKUP_DIR, 0755, true);
	}
	
	$backupFile = BACKUP_DIR . basename($filename) . '.' . date('Y-m-d_H-i-s') . '.bak';
	
	if (copy($filename, $backupFile)) {
		return $backupFile;
	}
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
	$iconDir = dirname(__FILE__); // Current directory (calendar/YYYY/)
	
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
// HELPER FUNCTION: Calculate twilight time (approximate)
// ============================================
function getTwilightTime($day, $month, $year) {
	// Simple approximation for DFW area
	// This could be replaced with more accurate calculation
	$timestamp = mktime(12, 0, 0, $month, $day, $year);
	$dayOfYear = date('z', $timestamp);
	
	// Approximate sunset times for DFW (varies ~5:20 PM to 8:40 PM)
	// Winter solstice (~Dec 21) = earliest ~5:20 PM
	// Summer solstice (~Jun 21) = latest ~8:40 PM
	$minMinutes = 17 * 60 + 20; // 5:20 PM in minutes
	$maxMinutes = 20 * 60 + 40; // 8:40 PM in minutes
	
	// Calculate based on day of year (0 = Jan 1, ~172 = Jun 21, ~355 = Dec 21)
	$angle = ($dayOfYear - 172) * (2 * 3.14159 / 365);
	$twilightMinutes = $minMinutes + ($maxMinutes - $minMinutes) * (1 + cos($angle)) / 2;
	
	$hours = floor($twilightMinutes / 60);
	$minutes = round($twilightMinutes % 60);
	
	return sprintf('%d:%02d PM', $hours - 12, $minutes);
}

// ============================================
// MAIN SCRIPT
// ============================================

$year = isset($_GET["year"]) ? intval($_GET["year"]) : date('Y');
$month = isset($_GET["month"]) ? intval($_GET["month"]) : date('n');
$day = isset($_GET["day"]) ? intval($_GET["day"]) : date('j');
$no = isset($_GET["no"]) ? intval($_GET["no"]) : 0;

// Check if this is a new event (no=0 or action=new)
$isNewEvent = ($no == 0 || (isset($_GET['action']) && $_GET['action'] == 'new'));

$message = "";
$messageType = "";
$backupCreated = "";

// Get available icons for dropdown
$availableIcons = getIconFiles($year);

// Handle form submission for NEW event
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save']) && $isNewEvent) {
	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
	
	// Create backup before editing
	if (file_exists($filename)) {
		$backupFile = createBackup($filename);
		if ($backupFile) {
			$backupCreated = "Backup created: " . basename($backupFile);
		}
	}
	
	// Strip slashes from POST data if magic_quotes_gpc is enabled (PHP 5.2 issue)
	if (get_magic_quotes_gpc()) {
		$_POST = array_map('stripslashes', $_POST);
	}
	
	// Get the day from POST (user can select it for new events)
	$day = intval($_POST['day']);
	
	// Build description
	$desc = $_POST['desc'];
	$desc = str_replace("\r\n", "<br />", $desc);
	$desc = str_replace("\n", "<br />", $desc);
	$desc = str_replace("\r", "<br />", $desc);
	
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
	
	// Build edit link (will need to determine the event number after insertion)
	$editLink = sprintf(
		'<a href="http://dfwhhh.org/calendar/%d/edit.php?month=%d&day=%d&year=%d&no=%%d">edit</a>',
		$year, $month, $day, $year
	);
	
	$calendarLink = sprintf(
		'<a href="http://dfwhhh.org/calendar/%d/generate_ics.php?month=%d&day=%d&year=%d&no=%%d">add to calendar</a>',
		$year, $month, $day, $year
	);
	
	$weatherWidget = '<!-- WEATHER_START --><br /><br /><strong>Weather Forecast for ' . htmlspecialchars($weatherLocation) . ':</strong><br />';
	$weatherWidget .= '<iframe src="' . $weatherUrl . '" width="100%" height="600" frameborder="0" scrolling="yes" style="border: 1px solid #ccc;"></iframe>';
	$weatherWidget .= '<br />' . $editLink . ' | ' . $calendarLink;
	$weatherWidget .= '<!-- WEATHER_END -->';
	
	$desc .= $weatherWidget;
	
	// Convert address line breaks
	$address = str_replace("\r\n", "<br />", $address);
	$address = str_replace("\n", "<br />", $address);
	$address = str_replace("\r", "<br />", $address);
	
	// Auto-generate the date string
	$autoDate = generateDateString($day, $month, $year);
	
	// Get twilight time
	$twilight = getTwilightTime($day, $month, $year);
	
	// Build new event line
	$newEventData = array(
		$day,
		$_POST['kennel'],
		$_POST['type'],
		$_POST['title'],
		$_POST['run'],
		$_POST['hares'],
		$_POST['time'],
		$address,
		$_POST['maplink'],
		$_POST['hashcash'],
		$_POST['turds'],
		'', // tweet
		$twilight, // twilight
		$autoDate,
		$desc,
		date('n/j/y G:i') . ' (created by ' . $_SESSION['username'] . ')'
	);
	$newEventLine = implode("\t", $newEventData);
	
	// Read existing file or create new one
	$lines = array();
	$header = "DAY\tKENNEL\tICON\tTITLE\tRUN\tHARES\tTIME\tSTART\tMAP\tHASHCASH\tTURDS\tTWEET\tTWILIGHT\tDATE\tDESC\tUPDATE";
	
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
		// Redirect to event.php
		$eventUrl = sprintf(
			'event.php?year=%d&month=%d&day=%d&no=%d',
			$year, $month, $day, $eventNumber
		);
		header('Location: ' . $eventUrl);
		exit;
	} else {
		$message = "Error: Unable to write to file. Check file permissions.";
		$messageType = "error";
	}
}

// Handle form submission for EDITING existing event
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save']) && !$isNewEvent) {
	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
	
	// Create backup before editing
	$backupFile = createBackup($filename);
	if ($backupFile) {
		$backupCreated = "Backup created: " . basename($backupFile);
	} else {
		$message = "Warning: Could not create backup file.";
		$messageType = "error";
	}
	
	// Strip slashes from POST data if magic_quotes_gpc is enabled (PHP 5.2 issue)
	if (get_magic_quotes_gpc()) {
		$_POST = array_map('stripslashes', $_POST);
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
			
			// Build description with weather widget
			$desc = $_POST['desc'];
			$desc = str_replace("\r\n", "<br />", $desc);
			$desc = str_replace("\n", "<br />", $desc);
			$desc = str_replace("\r", "<br />", $desc);
			
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
			
			$editLink = sprintf(
				'<a href="http://dfwhhh.org/calendar/%d/edit.php?month=%d&day=%d&year=%d&no=%d">edit</a>',
				$year, $month, $day, $year, $no
			);
			
			$calendarLink = sprintf(
				'<a href="http://dfwhhh.org/calendar/%d/generate_ics.php?month=%d&day=%d&year=%d&no=%d">add to calendar</a>',
				$year, $month, $day, $year, $no
			);
			
			$weatherWidget = '<!-- WEATHER_START --><br /><br /><strong>Weather Forecast for ' . htmlspecialchars($weatherLocation) . ':</strong><br />';
			$weatherWidget .= '<iframe src="' . $weatherUrl . '" width="100%" height="600" frameborder="0" scrolling="yes" style="border: 1px solid #ccc;"></iframe>';
			$weatherWidget .= '<br />' . $editLink . ' | ' . $calendarLink;
			$weatherWidget .= '<!-- WEATHER_END -->';
			
			$desc .= $weatherWidget;
			
			$address = str_replace("\r\n", "<br />", $address);
			$address = str_replace("\n", "<br />", $address);
			$address = str_replace("\r", "<br />", $address);
			
			$autoDate = generateDateString($day, $month, $year);
			
			$updatedData = array(
				$day,
				$_POST['kennel'],
				$_POST['type'],
				$_POST['title'],
				$_POST['run'],
				$_POST['hares'],
				$_POST['time'],
				$address,
				$_POST['maplink'],
				$_POST['hashcash'],
				$_POST['turds'],
				'',
				'',
				$autoDate,
				$desc,
				date('n/j/y G:i') . ' (edited by ' . $_SESSION['username'] . ')' . '<br />' . $editLink
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
			$eventUrl = sprintf(
				'event.php?year=%d&month=%d&day=%d&no=%d',
				$year, $month, $day, $no
			);
			header('Location: ' . $eventUrl);
			exit;
		} else {
			$message = "Error: Unable to write to file. Check file permissions.";
			$messageType = "error";
		}
	}
}

// Load current event data (for editing existing events)
$data = array('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '');

if (!$isNewEvent) {
	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
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
		$data = array_map('stripslashes', $data);
	}
}

//DAY = 0 KENNEL = 1 TYPE = 2 TITLE = 3 RUN = 4 HARES = 5 TIME = 6 ADDRESS = 7 
//MAPLINK = 8 HASHCASH = 9 TURDS = 10 TWEET = 11 TWILIGHT = 12 DATE = 13 DESC = 14 UPDATED = 15

$kennel = isset($data[1]) ? $data[1] : '';
$dateDisplay = isset($data[13]) && strlen($data[13]) > 0 ? $data[13] : generateDateString($day, $month, $year);
$currentIcon = isset($data[2]) ? trim($data[2]) : '';
$currentTurds = isset($data[10]) ? trim($data[10]) : '';

// Get days in the selected month (for new event day selector)
$daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

$pageTitle = $isNewEvent ? "Add New Event" : "Edit Event";
$formTitle = $isNewEvent ? "Add New Event" : "Edit Event";
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
	</style>
	
	<script>
	function updateIconPreview() {
		var select = document.getElementById('iconSelect');
		var preview = document.getElementById('iconPreview');
		if (select.value) {
			preview.src = select.value;
			preview.style.display = 'inline-block';
		} else {
			preview.style.display = 'none';
		}
	}
	</script>

	<title><?php echo $pageTitle; ?> - <?php echo $month; ?>/<?php echo $day; ?>/<?php echo $year; ?></title>
</head>
<body>
	<div id="container" class="edit-form">
		<div class="nav-links">
			<a href="/calendar">&laquo; Back to Calendar</a>
			<?php if (!$isNewEvent): ?>
			| <a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&action=new">➕ Add New Event</a>
			<?php endif; ?>
		</div>
		
		<div class="header">
			<div class="user-info">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
			<h1 style="clear: both;"><?php echo $formTitle; ?></h1>
			<a href="?logout=1" class="btn btn-logout">Logout</a>
		</div>
		
		<?php if ($isNewEvent): ?>
		<div class="new-event-banner">
			📅 Creating a new event for <strong><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></strong>
		</div>
		<?php else: ?>
		<h2><?php echo htmlspecialchars($dateDisplay); ?></h2>
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
		
		<form method="POST" action="">
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
					<select name="year" id="yearSelect" disabled>
						<option value="<?php echo $year; ?>"><?php echo $year; ?></option>
					</select>
				</div>
				<small style="color: #666;">Note: Year is determined by the calendar folder.</small>
			</div>
			<?php endif; ?>
			
			<div class="form-group">
				<label>Kennel:</label>
				<input type="text" name="kennel" value="<?php echo htmlspecialchars($data[1]); ?>" required>
			</div>
			
			<div class="form-group">
				<label>Icon:</label>
				<select name="type" id="iconSelect" onchange="updateIconPreview()">
					<option value="">-- Select Icon --</option>
					<?php foreach ($availableIcons as $icon): ?>
					<option value="<?php echo htmlspecialchars($icon); ?>" <?php echo ($currentIcon == $icon) ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($icon); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<img id="iconPreview" class="icon-preview" src="<?php echo htmlspecialchars($currentIcon); ?>" style="<?php echo empty($currentIcon) ? 'display:none;' : ''; ?>">
				<?php if (empty($availableIcons)): ?>
				<small style="color: #999;">No icon files found in calendar/<?php echo $year; ?>/ folder.</small>
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
				<input type="text" name="time" value="<?php echo htmlspecialchars(isset($data[6]) ? $data[6] : '7:00 PM'); ?>">
			</div>
			
			<div class="form-group">
				<label>Address:</label>
				<textarea name="address"><?php 
					$addr = isset($data[7]) ? $data[7] : '';
					$addr = str_replace("<br />", "\n", $addr);
					$addr = str_replace("<br/>", "\n", $addr);
					$addr = str_replace("<br>", "\n", $addr);
					echo htmlspecialchars($addr); 
				?></textarea>
			</div>
			
			<div class="form-group">
				<label>Map Link:</label>
				<input type="text" name="maplink" value="<?php echo htmlspecialchars(isset($data[8]) ? $data[8] : ''); ?>">
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
				<textarea name="desc" rows="10"><?php 
					$desc = isset($data[14]) ? $data[14] : '';
					$desc = preg_replace('/<!-- WEATHER_START -->.*?<!-- WEATHER_END -->/s', '', $desc);
					$desc = str_replace("<br />", "\n", $desc);
					$desc = str_replace("<br/>", "\n", $desc);
					$desc = str_replace("<br>", "\n", $desc);
					echo htmlspecialchars($desc); 
				?></textarea>
				<small style="color: #666;">Note: Weather forecast, edit link, and calendar invite are added automatically.</small>
			</div>
			
			<div class="form-group">
				<button type="submit" name="save" class="btn btn-save">💾 <?php echo $isNewEvent ? 'Create Event' : 'Save Changes'; ?></button>
				<?php if ($isNewEvent): ?>
				<a href="/calendar" class="btn btn-cancel">❌ Cancel</a>
				<?php else: ?>
				<a href="event.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>" class="btn btn-cancel">❌ Cancel</a>
				<?php endif; ?>
			</div>
		</form>
		
		<?php if (!$isNewEvent): ?>
		<p><small>Last updated: <?php echo isset($data[15]) ? htmlspecialchars($data[15]) : 'N/A'; ?></small></p>
		<?php endif; ?>
	</div>
</body>
</html>