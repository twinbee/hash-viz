<?php
// ============================================
// RECOVER.PHP - Event Recovery for DFW Hash House Harriers
// Version 1.0
// ============================================
// Compares backup files against current data files to find:
// - DELETED events (exist in backup but not in current)
// - LESSENED events (have less data in current than backup)

// ============================================
// TIMEZONE CONFIGURATION
// ============================================
date_default_timezone_set('America/Chicago'); // Central Time for DFW

// ============================================
// CONFIGURATION
// ============================================
define('BACKUP_DIR', '../../android/backups/');
define('DATA_DIR', '../../android/');

// Load users from separate file
require_once('users.php');

// ============================================
// SECURITY FUNCTIONS
// ============================================
function h($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function sanitizeInput($input) {
	if (is_array($input)) {
		return array_map('sanitizeInput', $input);
	}
	$input = str_replace(chr(0), '', $input);
	return trim($input);
}

// Generate CSRF token (PHP 5.2 compatible)
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

// Simple MD5 verification
function verify_password($password, $hash) {
	return md5($password) === $hash;
}

session_start();

// Regenerate session ID on login
function regenerateSession() {
	session_regenerate_id(true);
}

// Login handler
if (isset($_POST['login'])) {
	$username = sanitizeInput($_POST['username']);
	$password = $_POST['password'];
	
	if (isset($USERS[$username]) && verify_password($password, $USERS[$username])) {
		regenerateSession();
		$_SESSION['authenticated'] = true;
		$_SESSION['username'] = $username;
		$_SESSION['login_time'] = time();
		generateCsrfToken();
		header('Location: ' . $_SERVER['PHP_SELF']);
		exit;
	} else {
		$loginError = "Invalid username or password";
		error_log("Failed login attempt for user: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
	}
}

// Logout handler
if (isset($_GET['logout'])) {
	session_destroy();
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

// Session timeout (30 minutes)
$timeout_duration = 1800;
if (isset($_SESSION['login_time'])) {
	if (time() - $_SESSION['login_time'] > $timeout_duration) {
		session_destroy();
		header('Location: ' . $_SERVER['PHP_SELF'] . '?timeout=1');
		exit;
	}
	$_SESSION['login_time'] = time();
}

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="UTF-8">
		<title>Recovery Tool - Login Required</title>
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
		</style>
	</head>
	<body>
		<div class="login-container">
			<h2>🔒 Recovery Tool - Authentication Required</h2>
			<?php if (isset($_GET['timeout'])): ?>
				<div class="info">Your session has expired. Please login again.</div>
			<?php endif; ?>
			<?php if (isset($loginError)): ?>
				<div class="error"><?php echo h($loginError); ?></div>
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
		</div>
	</body>
	</html>
	<?php
	exit;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

// Parse event line into array
function parseEventLine($line) {
	return explode("\t", rtrim($line, "\r\n"));
}

// Create unique key for an event (day + kennel + title + run number)
function getEventKey($data) {
	$day = isset($data[0]) ? trim($data[0]) : '';
	$kennel = isset($data[1]) ? trim($data[1]) : '';
	$title = isset($data[3]) ? trim($data[3]) : '';
	$run = isset($data[4]) ? trim($data[4]) : '';
	return $day . '|' . strtolower($kennel) . '|' . strtolower($title) . '|' . $run;
}

// Calculate content length (non-empty fields)
function getContentLength($data) {
	$length = 0;
	foreach ($data as $field) {
		$length += strlen(trim($field));
	}
	return $length;
}

// Load events from a file
function loadEventsFromFile($filepath) {
	$events = array();
	if (!file_exists($filepath)) {
		return $events;
	}
	
	$lines = file($filepath, FILE_IGNORE_NEW_LINES);
	foreach ($lines as $lineNum => $line) {
		$trimmedLine = trim($line);
		if (empty($trimmedLine)) continue;
		$data = parseEventLine($line);
		$key = getEventKey($data);
		$events[$key] = array(
			'line' => $lineNum,
			'data' => $data,
			'raw' => $line,
			'length' => getContentLength($data)
		);
	}
	return $events;
}

// Get all backup files for a specific data file
function getBackupsForFile($dataFilename) {
	$backups = array();
	$pattern = BACKUP_DIR . basename($dataFilename) . '.*.bak';
	$files = glob($pattern);
	
	if ($files) {
		foreach ($files as $file) {
			// Extract date from filename: YYYY-MM.txt.YYYY-MM-DD_HH-ii-ss.bak
			if (preg_match('/\.(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.bak$/', $file, $matches)) {
				$dateStr = str_replace('_', ' ', str_replace('-', ':', substr($matches[1], 0, 10) . ' ' . substr($matches[1], 11)));
				$dateStr = str_replace(':', '-', substr($dateStr, 0, 10)) . substr($dateStr, 10);
				$timestamp = strtotime(str_replace('_', ' ', $matches[1]));
				$backups[] = array(
					'file' => $file,
					'date' => $matches[1],
					'timestamp' => $timestamp,
					'display_date' => date('M j, Y g:i A', $timestamp)
				);
			}
		}
		// Sort by timestamp descending (newest first)
		// PHP 5.2 compatible - use simple bubble sort instead of usort with create_function
		for ($i = 0; $i < count($backups) - 1; $i++) {
			for ($j = 0; $j < count($backups) - $i - 1; $j++) {
				if ($backups[$j]['timestamp'] < $backups[$j + 1]['timestamp']) {
					$temp = $backups[$j];
					$backups[$j] = $backups[$j + 1];
					$backups[$j + 1] = $temp;
				}
			}
		}
	}
	
	return $backups;
}

// Get all data files
function getDataFiles() {
	$files = array();
	$pattern = DATA_DIR . '*.txt';
	$found = glob($pattern);
	
	if ($found) {
		foreach ($found as $file) {
			// Only include month data files (YYYY-MM.txt format)
			if (preg_match('/\d{4}-\d{2}\.txt$/', $file)) {
				$files[] = $file;
			}
		}
		sort($files);
	}
	
	return $files;
}

// Find problems by comparing current data to backups
function findProblems($dataFile) {
	$problems = array();
	$currentEvents = loadEventsFromFile($dataFile);
	$backups = getBackupsForFile($dataFile);
	
	// Track which backup has the best version of each event
	$bestVersions = array();
	
	foreach ($backups as $backup) {
		$backupEvents = loadEventsFromFile($backup['file']);
		
		foreach ($backupEvents as $key => $backupEvent) {
			// Check if event is missing or lessened in current
			$isMissing = !isset($currentEvents[$key]);
			$isLessened = false;
			
			if (!$isMissing && isset($currentEvents[$key])) {
				// Compare content length
				if ($backupEvent['length'] > $currentEvents[$key]['length']) {
					$isLessened = true;
				}
			}
			
			if ($isMissing || $isLessened) {
				// Only track the best (most content) version
				if (!isset($bestVersions[$key]) || $backupEvent['length'] > $bestVersions[$key]['backup_length']) {
					$bestVersions[$key] = array(
						'key' => $key,
						'type' => $isMissing ? 'DELETED' : 'LESSENED',
						'backup_file' => $backup['file'],
						'backup_date' => $backup['display_date'],
						'backup_timestamp' => $backup['timestamp'],
						'backup_data' => $backupEvent['data'],
						'backup_raw' => $backupEvent['raw'],
						'backup_length' => $backupEvent['length'],
						'current_data' => $isMissing ? null : $currentEvents[$key]['data'],
						'current_length' => $isMissing ? 0 : $currentEvents[$key]['length'],
						'day' => isset($backupEvent['data'][0]) ? $backupEvent['data'][0] : '?',
						'kennel' => isset($backupEvent['data'][1]) ? $backupEvent['data'][1] : 'Unknown',
						'title' => isset($backupEvent['data'][3]) ? $backupEvent['data'][3] : 'Untitled'
					);
				}
			}
		}
	}
	
	// Convert to indexed array and sort by day
	$problems = array_values($bestVersions);
	// PHP 5.2 compatible sort by day
	for ($i = 0; $i < count($problems) - 1; $i++) {
		for ($j = 0; $j < count($problems) - $i - 1; $j++) {
			if (intval($problems[$j]['day']) > intval($problems[$j + 1]['day'])) {
				$temp = $problems[$j];
				$problems[$j] = $problems[$j + 1];
				$problems[$j + 1] = $temp;
			}
		}
	}
	
	return $problems;
}

// Recover an event from backup
function recoverEvent($dataFile, $backupRaw, $eventDay) {
	// Read current file
	$lines = array();
	if (file_exists($dataFile)) {
		$lines = file($dataFile, FILE_IGNORE_NEW_LINES);
	}
	
	// Find the right position to insert (sorted by day)
	$insertPos = count($lines);
	$backupData = parseEventLine($backupRaw);
	$backupKey = getEventKey($backupData);
	
	for ($i = 0; $i < count($lines); $i++) {
		$lineData = parseEventLine($lines[$i]);
		$lineDay = isset($lineData[0]) ? intval($lineData[0]) : 0;
		$lineKey = getEventKey($lineData);
		
		// If this is the same event (updating), replace it
		if ($lineKey === $backupKey) {
			$lines[$i] = $backupRaw;
			// Write file
			file_put_contents($dataFile, implode("\n", $lines) . "\n", LOCK_EX);
			return true;
		}
		
		// Find insert position (first day greater than backup day)
		if ($lineDay > intval($eventDay) && $insertPos === count($lines)) {
			$insertPos = $i;
		}
	}
	
	// Insert at position
	array_splice($lines, $insertPos, 0, array($backupRaw));
	
	// Write file
	file_put_contents($dataFile, implode("\n", $lines) . "\n", LOCK_EX);
	return true;
}

// ============================================
// HANDLE RECOVERY ACTION
// ============================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recover'])) {
	if (!verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security token invalid. Please try again.";
		$messageType = 'error';
	} else {
		$dataFile = sanitizeInput($_POST['data_file']);
		$backupRaw = $_POST['backup_raw']; // Don't sanitize - preserve exact content
		$eventDay = sanitizeInput($_POST['event_day']);
		
		// Validate data file path
		if (strpos(realpath(dirname($dataFile)), realpath(DATA_DIR)) !== 0 && 
			strpos($dataFile, DATA_DIR) !== 0) {
			$message = "Invalid file path.";
			$messageType = 'error';
		} else {
			if (recoverEvent($dataFile, $backupRaw, $eventDay)) {
				$message = "Event recovered successfully!";
				$messageType = 'success';
				// Log the recovery
				$logFile = BACKUP_DIR . 'recovery.log';
				$logEntry = date('Y-m-d H:i:s') . "\t" . $_SESSION['username'] . "\t" . basename($dataFile) . "\tDay " . $eventDay . "\n";
				file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
			} else {
				$message = "Failed to recover event.";
				$messageType = 'error';
			}
		}
	}
}

// ============================================
// GET SELECTED FILE OR DEFAULT
// ============================================
$selectedFile = isset($_GET['file']) ? sanitizeInput($_GET['file']) : '';
$dataFiles = getDataFiles();
$problems = array();

if (!empty($selectedFile) && in_array(DATA_DIR . $selectedFile, $dataFiles)) {
	$problems = findProblems(DATA_DIR . $selectedFile);
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Event Recovery Tool</title>
	<style>
		* { box-sizing: border-box; }
		body { 
			font-family: Arial, sans-serif; 
			background: #f5f5f5; 
			margin: 0;
			padding: 20px;
		}
		.container {
			max-width: 1200px;
			margin: 0 auto;
		}
		h1 {
			color: #333;
			margin-bottom: 5px;
		}
		.subtitle {
			color: #666;
			margin-bottom: 20px;
		}
		.header-bar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20px;
		}
		.logout-link {
			color: #666;
			text-decoration: none;
		}
		.logout-link:hover {
			color: #333;
		}
		.file-selector {
			background: white;
			padding: 20px;
			border-radius: 5px;
			margin-bottom: 20px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		.file-selector select {
			padding: 10px;
			font-size: 16px;
			margin-right: 10px;
		}
		.file-selector button {
			padding: 10px 20px;
			background: #2196F3;
			color: white;
			border: none;
			cursor: pointer;
			font-size: 16px;
			border-radius: 3px;
		}
		.file-selector button:hover {
			background: #1976D2;
		}
		.message {
			padding: 15px;
			border-radius: 5px;
			margin-bottom: 20px;
		}
		.message.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		.message.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		.no-problems {
			background: white;
			padding: 40px;
			text-align: center;
			border-radius: 5px;
			color: #666;
		}
		.problem-card {
			background: white;
			border-radius: 5px;
			margin-bottom: 15px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			overflow: hidden;
		}
		.problem-header {
			padding: 15px 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.problem-header.deleted {
			background: #ffebee;
			border-left: 4px solid #f44336;
		}
		.problem-header.lessened {
			background: #fff3e0;
			border-left: 4px solid #ff9800;
		}
		.problem-title {
			font-weight: bold;
			font-size: 16px;
		}
		.problem-badge {
			padding: 4px 12px;
			border-radius: 20px;
			font-size: 12px;
			font-weight: bold;
			text-transform: uppercase;
		}
		.problem-badge.deleted {
			background: #f44336;
			color: white;
		}
		.problem-badge.lessened {
			background: #ff9800;
			color: white;
		}
		.problem-body {
			padding: 15px 20px;
			border-top: 1px solid #eee;
		}
		.problem-meta {
			color: #666;
			font-size: 14px;
			margin-bottom: 10px;
		}
		.problem-details {
			display: flex;
			gap: 20px;
			margin-bottom: 15px;
		}
		.detail-box {
			flex: 1;
			padding: 10px;
			background: #f5f5f5;
			border-radius: 3px;
			font-size: 13px;
		}
		.detail-box h4 {
			margin: 0 0 5px 0;
			font-size: 12px;
			color: #666;
			text-transform: uppercase;
		}
		.detail-box.backup {
			background: #e8f5e9;
			border: 1px solid #c8e6c9;
		}
		.detail-box.current {
			background: #ffebee;
			border: 1px solid #ffcdd2;
		}
		.field-list {
			margin: 0;
			padding: 0;
			list-style: none;
		}
		.field-list li {
			padding: 3px 0;
			border-bottom: 1px solid rgba(0,0,0,0.05);
			word-break: break-word;
		}
		.field-list li:last-child {
			border-bottom: none;
		}
		.field-label {
			font-weight: bold;
			color: #666;
		}
		.recover-btn {
			background: #4CAF50;
			color: white;
			border: none;
			padding: 10px 20px;
			cursor: pointer;
			border-radius: 3px;
			font-size: 14px;
		}
		.recover-btn:hover {
			background: #388E3C;
		}
		.backup-date {
			color: #666;
			font-size: 13px;
		}
		.stats {
			display: flex;
			gap: 20px;
			margin-bottom: 20px;
		}
		.stat-box {
			background: white;
			padding: 15px 25px;
			border-radius: 5px;
			text-align: center;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		.stat-box .number {
			font-size: 2em;
			font-weight: bold;
		}
		.stat-box .label {
			color: #666;
			font-size: 14px;
		}
		.stat-box.deleted .number { color: #f44336; }
		.stat-box.lessened .number { color: #ff9800; }
		.length-comparison {
			font-size: 12px;
			color: #666;
			margin-top: 5px;
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="header-bar">
			<div>
				<h1>🔧 Event Recovery Tool</h1>
				<p class="subtitle">Compare backups and recover deleted or modified events</p>
			</div>
			<div>
				<span style="color: #666;">Logged in as <?php echo h($_SESSION['username']); ?></span>
				 | 
				<a href="?logout=1" class="logout-link">Logout</a>
			</div>
		</div>
		
		<?php if ($message): ?>
		<div class="message <?php echo $messageType; ?>"><?php echo h($message); ?></div>
		<?php endif; ?>
		
		<div class="file-selector">
			<form method="GET">
				<label><strong>Select Month File:</strong></label>
				<select name="file">
					<option value="">-- Select a file --</option>
					<?php foreach ($dataFiles as $file): ?>
					<option value="<?php echo h(basename($file)); ?>" <?php echo (basename($file) === $selectedFile) ? 'selected' : ''; ?>>
						<?php echo h(basename($file)); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<button type="submit">Scan for Problems</button>
			</form>
		</div>
		
		<?php if (!empty($selectedFile)): ?>
			<?php 
			$deletedCount = 0;
			$lessenedCount = 0;
			foreach ($problems as $p) {
				if ($p['type'] === 'DELETED') $deletedCount++;
				else $lessenedCount++;
			}
			?>
			
			<div class="stats">
				<div class="stat-box deleted">
					<div class="number"><?php echo $deletedCount; ?></div>
					<div class="label">Deleted Events</div>
				</div>
				<div class="stat-box lessened">
					<div class="number"><?php echo $lessenedCount; ?></div>
					<div class="label">Lessened Events</div>
				</div>
			</div>
			
			<?php if (count($problems) === 0): ?>
			<div class="no-problems">
				<h3>✅ No Problems Found</h3>
				<p>All events in <?php echo h($selectedFile); ?> match or exceed their backup versions.</p>
			</div>
			<?php else: ?>
				<?php foreach ($problems as $problem): ?>
				<div class="problem-card">
					<div class="problem-header <?php echo strtolower($problem['type']); ?>">
						<div>
							<div class="problem-title">
								Day <?php echo h($problem['day']); ?>: <?php echo h($problem['kennel']); ?>
								<?php if ($problem['title']): ?> - <?php echo h($problem['title']); ?><?php endif; ?>
							</div>
							<div class="backup-date">Best backup from: <?php echo h($problem['backup_date']); ?></div>
						</div>
						<span class="problem-badge <?php echo strtolower($problem['type']); ?>"><?php echo $problem['type']; ?></span>
					</div>
					<div class="problem-body">
						<div class="problem-details">
							<div class="detail-box backup">
								<h4>📦 Backup Version (<?php echo $problem['backup_length']; ?> chars)</h4>
								<ul class="field-list">
									<?php 
									$fieldNames = array('Day', 'Kennel', 'Type', 'Title', 'Run#', 'Hares', 'Time', 'Address', 'MapLink', 'HashCash', 'TURDs', 'Tweet', 'Twilight', 'Date', 'Description');
									foreach ($problem['backup_data'] as $i => $val): 
										$trimVal = trim($val);
										if (empty($trimVal)) continue;
									?>
									<li><span class="field-label"><?php echo isset($fieldNames[$i]) ? $fieldNames[$i] : "Field $i"; ?>:</span> <?php echo h(substr($val, 0, 100)); ?><?php echo strlen($val) > 100 ? '...' : ''; ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php if ($problem['type'] === 'LESSENED'): ?>
							<div class="detail-box current">
								<h4>📄 Current Version (<?php echo $problem['current_length']; ?> chars)</h4>
								<ul class="field-list">
									<?php foreach ($problem['current_data'] as $i => $val): 
										$trimVal = trim($val);
										if (empty($trimVal)) continue;
									?>
									<li><span class="field-label"><?php echo isset($fieldNames[$i]) ? $fieldNames[$i] : "Field $i"; ?>:</span> <?php echo h(substr($val, 0, 100)); ?><?php echo strlen($val) > 100 ? '...' : ''; ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php else: ?>
							<div class="detail-box current">
								<h4>📄 Current Version</h4>
								<p style="color: #999; font-style: italic;">Event does not exist in current file</p>
							</div>
							<?php endif; ?>
						</div>
						
						<form method="POST" onsubmit="return confirm('Are you sure you want to recover this event from the backup?');">
							<input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
							<input type="hidden" name="data_file" value="<?php echo h(DATA_DIR . $selectedFile); ?>">
							<input type="hidden" name="backup_raw" value="<?php echo h($problem['backup_raw']); ?>">
							<input type="hidden" name="event_day" value="<?php echo h($problem['day']); ?>">
							<button type="submit" name="recover" class="recover-btn">
								🔄 Recover from <?php echo h($problem['backup_date']); ?>
							</button>
						</form>
					</div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		<?php else: ?>
		<div class="no-problems">
			<h3>📂 Select a File</h3>
			<p>Choose a month file above to scan for deleted or modified events.</p>
		</div>
		<?php endif; ?>
	</div>
</body>
</html>