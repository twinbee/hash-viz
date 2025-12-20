<?php
// ============================================
// RUNFIXUP.PHP - Run Number Analysis and Correction Tool
// Version 1.0
// ============================================

date_default_timezone_set('America/Chicago');

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
	$input = trim($input);
	return $input;
}

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

function verify_password($password, $hash) {
	return md5($password) === $hash;
}

// ============================================
// AUTHENTICATION
// ============================================
require_once('users.php');

session_start();

if (isset($_POST['login'])) {
	$username = sanitizeInput($_POST['username']);
	$password = $_POST['password'];
	
	if (isset($USERS[$username]) && verify_password($password, $USERS[$username])) {
		session_regenerate_id(true);
		$_SESSION['rf_authenticated'] = true;
		$_SESSION['rf_username'] = $username;
		$_SESSION['rf_login_time'] = time();
	} else {
		$loginError = "Invalid username or password";
	}
}

if (isset($_GET['logout'])) {
	unset($_SESSION['rf_authenticated']);
	unset($_SESSION['rf_username']);
	unset($_SESSION['rf_login_time']);
	header('Location: runfixup.php');
	exit;
}

$timeout_duration = 1800;
if (isset($_SESSION['rf_login_time'])) {
	if (time() - $_SESSION['rf_login_time'] > $timeout_duration) {
		unset($_SESSION['rf_authenticated']);
		header('Location: runfixup.php?timeout=1');
		exit;
	}
	$_SESSION['rf_login_time'] = time();
}

if (!isset($_SESSION['rf_authenticated']) || $_SESSION['rf_authenticated'] !== true) {
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Run Fixup - Login</title>
		<style>
			body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
			.login-container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
			.login-container h2 { margin-top: 0; }
			input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin: 8px 0 16px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
			.btn-login { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
			.btn-login:hover { background: #45a049; }
			.error { color: #d9534f; margin-bottom: 15px; }
			.timeout { color: #f0ad4e; margin-bottom: 15px; }
		</style>
	</head>
	<body>
		<div class="login-container">
			<h2>🔢 Run Number Fixup</h2>
			<?php if (isset($loginError)): ?>
			<div class="error"><?php echo h($loginError); ?></div>
			<?php endif; ?>
			<?php if (isset($_GET['timeout'])): ?>
			<div class="timeout">Your session has expired. Please log in again.</div>
			<?php endif; ?>
			<form method="POST">
				<label>Username:</label>
				<input type="text" name="username" required autofocus>
				<label>Password:</label>
				<input type="password" name="password" required>
				<button type="submit" name="login" class="btn-login">Login</button>
			</form>
		</div>
	</body>
	</html>
	<?php
	exit;
}

// ============================================
// LOAD KENNELS FROM DEFAULTS.TXT
// ============================================
function loadKennels() {
	$defaultsFile = dirname(__FILE__) . '/defaults.txt';
	
	if (!file_exists($defaultsFile)) {
		return array();
	}
	
	$kennels = array();
	$lines = file($defaultsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	
	foreach ($lines as $idx => $line) {
		if ($idx == 0 && strpos($line, 'DAY') === 0) {
			continue;
		}
		
		$parts = explode("\t", $line);
		$name = isset($parts[1]) ? trim($parts[1]) : '';
		
		if (!empty($name)) {
			$kennels[] = $name;
		}
	}
	
	sort($kennels);
	return $kennels;
}

// ============================================
// GET ALL EVENTS FOR A KENNEL
// ============================================
function getKennelEvents($kennelName) {
	$events = array();
	$androidDir = dirname(__FILE__) . '/../android/';
	
	// Find all month files
	$files = glob($androidDir . '*.txt');
	if (!$files) {
		return $events;
	}
	
	foreach ($files as $file) {
		$basename = basename($file);
		// Match YYYY-MM.txt format
		if (!preg_match('/^(\d{4})-(\d{2})\.txt$/', $basename, $matches)) {
			continue;
		}
		
		$fileYear = intval($matches[1]);
		$fileMonth = intval($matches[2]);
		
		$lines = file($file, FILE_IGNORE_NEW_LINES);
		$n = 0;
		$lastDay = "";
		
		foreach ($lines as $idx => $line) {
			if ($idx == 0 && strpos($line, 'DAY') === 0) {
				continue;
			}
			
			$parts = explode("\t", $line);
			$day = isset($parts[0]) ? trim($parts[0]) : '';
			$kennel = isset($parts[1]) ? trim($parts[1]) : '';
			
			if ($day != $lastDay) {
				$n = 1;
			} else {
				$n++;
			}
			$lastDay = $day;
			
			if ($kennel == $kennelName) {
				$runNum = isset($parts[4]) ? trim($parts[4]) : '';
				$address = isset($parts[7]) ? trim($parts[7]) : '';
				$desc = isset($parts[14]) ? trim($parts[14]) : '';
				$title = isset($parts[3]) ? trim($parts[3]) : '';
				
				// Create date for sorting
				$dateStr = sprintf('%04d-%02d-%02d', $fileYear, $fileMonth, intval($day));
				
				$events[] = array(
					'date' => $dateStr,
					'year' => $fileYear,
					'month' => $fileMonth,
					'day' => intval($day),
					'no' => $n,
					'run' => $runNum,
					'address' => $address,
					'desc' => $desc,
					'title' => $title,
					'file' => $file
				);
			}
		}
	}
	
	// Sort by date
	usort($events, create_function('$a, $b', 'return strcmp($a["date"], $b["date"]);'));
	
	return $events;
}

// ============================================
// ANALYZE EVENTS
// ============================================
function analyzeEvents($events) {
	$analysis = array(
		'total' => count($events),
		'first_event' => null,
		'first_run_number' => null,
		'no_location' => array(),
		'joke_runs' => array(),
		'cancelled' => array(),
		'actual_runs' => array(),
		'next_run' => null,
		'recommended_next' => null,
		'future_runs' => array()
	);
	
	if (empty($events)) {
		return $analysis;
	}
	
	$today = date('Y-m-d');
	$lastActualRunNumber = null;
	$runSequence = array(); // Track run numbers to detect real 69s
	
	// First pass: collect all run numbers to detect patterns
	foreach ($events as $event) {
		if (!empty($event['run']) && is_numeric($event['run'])) {
			$runSequence[] = array(
				'date' => $event['date'],
				'run' => intval($event['run'])
			);
		}
	}
	
	// Second pass: analyze each event
	foreach ($events as $idx => $event) {
		$isNoLocation = empty($event['address']);
		$isCancelled = (stripos($event['desc'], 'cancel') !== false || 
		                stripos($event['title'], 'cancel') !== false);
		
		// Check for joke run numbers (containing 69)
		$isJokeRun = false;
		if (!empty($event['run']) && strpos($event['run'], '69') !== false) {
			// Check if it's a legitimate 69 by looking at surrounding runs
			$runNum = intval($event['run']);
			$isLegitimate = false;
			
			// Look for 68 before or 70 after in the sequence
			foreach ($runSequence as $i => $seq) {
				if ($seq['date'] == $event['date'] && $seq['run'] == $runNum) {
					// Check previous
					if ($i > 0 && $runSequence[$i-1]['run'] == $runNum - 1) {
						$isLegitimate = true;
					}
					// Check next
					if ($i < count($runSequence) - 1 && $runSequence[$i+1]['run'] == $runNum + 1) {
						$isLegitimate = true;
					}
					break;
				}
			}
			
			if (!$isLegitimate) {
				$isJokeRun = true;
			}
		}
		
		// Categorize
		if ($isNoLocation) {
			$analysis['no_location'][] = $event;
		}
		if ($isCancelled) {
			$analysis['cancelled'][] = $event;
		}
		if ($isJokeRun) {
			$analysis['joke_runs'][] = $event;
		}
		
		// Track actual runs (has location, not cancelled, not joke)
		$isActualRun = !$isNoLocation && !$isCancelled && !$isJokeRun;
		
		if ($isActualRun) {
			$analysis['actual_runs'][] = $event;
			
			// Track first event
			if ($analysis['first_event'] === null) {
				$analysis['first_event'] = $event;
				if (!empty($event['run']) && is_numeric($event['run'])) {
					$analysis['first_run_number'] = intval($event['run']);
				}
			}
			
			// Track last actual run number before today
			if ($event['date'] < $today && !empty($event['run']) && is_numeric($event['run'])) {
				$lastActualRunNumber = intval($event['run']);
			}
		}
		
		// Find next run (first event on or after today)
		if ($event['date'] >= $today && $analysis['next_run'] === null) {
			$analysis['next_run'] = $event;
		}
		
		// Collect future runs
		if ($event['date'] >= $today) {
			$analysis['future_runs'][] = $event;
		}
	}
	
	// Calculate recommended next run number
	if ($lastActualRunNumber !== null) {
		$analysis['recommended_next'] = $lastActualRunNumber + 1;
	} else if ($analysis['first_run_number'] !== null) {
		// If no past runs, use first run number + count of actual runs
		$analysis['recommended_next'] = $analysis['first_run_number'] + count($analysis['actual_runs']) - 1;
	}
	
	return $analysis;
}

// ============================================
// FIX FUTURE RUN NUMBERS
// ============================================
function fixFutureRunNumbers($kennelName, $startingRunNumber) {
	$events = getKennelEvents($kennelName);
	$today = date('Y-m-d');
	$currentRun = intval($startingRunNumber);
	$fixed = 0;
	$errors = array();
	
	// Group events by file for efficient updating
	$fileUpdates = array();
	
	foreach ($events as $event) {
		if ($event['date'] < $today) {
			continue;
		}
		
		// Skip no-location, cancelled, and joke runs
		$isNoLocation = empty($event['address']);
		$isCancelled = (stripos($event['desc'], 'cancel') !== false || 
		                stripos($event['title'], 'cancel') !== false);
		$isJokeRun = false;
		if (!empty($event['run']) && strpos($event['run'], '69') !== false) {
			$runNum = intval($event['run']);
			if ($runNum == 69 || $runNum == 6969 || $runNum == 696969) {
				$isJokeRun = true;
			}
		}
		
		if ($isNoLocation || $isCancelled || $isJokeRun) {
			continue;
		}
		
		// This is an actual run - assign the next run number
		$file = $event['file'];
		if (!isset($fileUpdates[$file])) {
			$fileUpdates[$file] = array();
		}
		
		$fileUpdates[$file][] = array(
			'day' => $event['day'],
			'no' => $event['no'],
			'new_run' => $currentRun
		);
		
		$currentRun++;
		$fixed++;
	}
	
	// Apply updates to files
	foreach ($fileUpdates as $file => $updates) {
		$lines = file($file, FILE_IGNORE_NEW_LINES);
		$newLines = array();
		$n = 0;
		$lastDay = "";
		
		foreach ($lines as $idx => $line) {
			if ($idx == 0 && strpos($line, 'DAY') === 0) {
				$newLines[] = $line;
				continue;
			}
			
			$parts = explode("\t", $line);
			$day = isset($parts[0]) ? trim($parts[0]) : '';
			$kennel = isset($parts[1]) ? trim($parts[1]) : '';
			
			if ($day != $lastDay) {
				$n = 1;
			} else {
				$n++;
			}
			$lastDay = $day;
			
			// Check if this line needs updating
			$needsUpdate = false;
			$newRunNum = null;
			
			if ($kennel == $kennelName) {
				foreach ($updates as $update) {
					if ($update['day'] == intval($day) && $update['no'] == $n) {
						$needsUpdate = true;
						$newRunNum = $update['new_run'];
						break;
					}
				}
			}
			
			if ($needsUpdate) {
				// Ensure we have enough columns
				while (count($parts) < 16) {
					$parts[] = '';
				}
				$parts[4] = strval($newRunNum);
				$newLines[] = implode("\t", $parts);
			} else {
				$newLines[] = $line;
			}
		}
		
		$result = file_put_contents($file, implode("\n", $newLines), LOCK_EX);
		if ($result === false) {
			$errors[] = "Failed to write to " . basename($file);
		}
	}
	
	return array(
		'fixed' => $fixed,
		'errors' => $errors
	);
}

// ============================================
// MAIN LOGIC
// ============================================
$kennels = loadKennels();
$selectedKennel = isset($_GET['kennel']) ? sanitizeInput($_GET['kennel']) : '';
$analysis = null;
$message = '';
$messageType = '';

if (!empty($selectedKennel)) {
	$events = getKennelEvents($selectedKennel);
	$analysis = analyzeEvents($events);
}

// Handle fix action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fix_runs'])) {
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request.";
		$messageType = "error";
	} else {
		$kennelToFix = sanitizeInput($_POST['kennel']);
		$startingRun = intval($_POST['starting_run']);
		
		if (empty($kennelToFix) || $startingRun < 1) {
			$message = "Invalid kennel or run number.";
			$messageType = "error";
		} else {
			$result = fixFutureRunNumbers($kennelToFix, $startingRun);
			
			if (empty($result['errors'])) {
				$message = "Successfully updated {$result['fixed']} future run numbers for " . h($kennelToFix) . ".";
				$messageType = "success";
				
				// Refresh analysis
				$selectedKennel = $kennelToFix;
				$events = getKennelEvents($selectedKennel);
				$analysis = analyzeEvents($events);
			} else {
				$message = "Errors occurred: " . implode(", ", $result['errors']);
				$messageType = "error";
			}
		}
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Run Number Fixup Tool</title>
	<style>
		body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
		.container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { margin-top: 0; color: #333; }
		h2 { color: #555; border-bottom: 1px solid #eee; padding-bottom: 10px; }
		h3 { color: #666; margin-top: 25px; }
		
		.user-info { float: right; color: #666; font-size: 14px; }
		.nav-links { margin-bottom: 20px; }
		.nav-links a { color: #0066cc; text-decoration: none; margin-right: 15px; }
		
		.form-group { margin-bottom: 20px; }
		.form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
		.form-group select, .form-group input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
		.form-group select { min-width: 300px; }
		
		.btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
		.btn-primary { background: #007bff; color: white; }
		.btn-primary:hover { background: #0069d9; }
		.btn-success { background: #28a745; color: white; }
		.btn-success:hover { background: #218838; }
		.btn-warning { background: #ffc107; color: #333; }
		
		.message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
		.message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		.message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
		
		.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
		.stat-box { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
		.stat-box.warning { border-left-color: #ffc107; }
		.stat-box.danger { border-left-color: #dc3545; }
		.stat-box.success { border-left-color: #28a745; }
		.stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
		.stat-value { font-size: 24px; font-weight: bold; color: #333; }
		.stat-detail { font-size: 12px; color: #888; margin-top: 5px; }
		
		.event-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; }
		.event-item { padding: 10px 15px; border-bottom: 1px solid #eee; font-size: 13px; }
		.event-item:last-child { border-bottom: none; }
		.event-item:hover { background: #f8f9fa; }
		.event-item a { text-decoration: none; color: inherit; }
		.event-item a:hover { color: #007bff; }
		.event-date { font-weight: bold; color: #333; }
		.event-run { color: #007bff; margin-left: 10px; }
		.event-title { color: #555; margin-left: 10px; }
		.event-note { color: #888; font-style: italic; margin-left: 10px; }
		
		.recommendation-box { background: #e7f3ff; border: 2px solid #007bff; padding: 20px; border-radius: 5px; margin: 25px 0; }
		.recommendation-box h3 { margin-top: 0; color: #0056b3; }
		
		.fix-form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-top: 20px; }
		.fix-form input[type="number"] { width: 100px; }
		
		.collapsible { cursor: pointer; user-select: none; }
		.collapsible:after { content: ' ▼'; font-size: 10px; }
		.collapsible.collapsed:after { content: ' ▶'; }
		.collapse-content { display: block; }
		.collapse-content.hidden { display: none; }
	</style>
</head>
<body>
	<div class="container">
		<div class="user-info">
			Logged in as: <strong><?php echo h($_SESSION['rf_username']); ?></strong>
			| <a href="?logout=1">Logout</a>
		</div>
		
		<h1>🔢 Run Number Fixup Tool</h1>
		
		<div class="nav-links">
			<a href="edit.php">← Back to Event Editor</a>
			| <a href="kenneldefaults.php">🏠 Kennel Defaults</a>
		</div>
		
		<?php if (!empty($message)): ?>
		<div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
		<?php endif; ?>
		
		<form method="GET" class="form-group">
			<label>Select Kennel to Analyze:</label>
			<select name="kennel" onchange="this.form.submit()">
				<option value="">-- Select Kennel --</option>
				<?php foreach ($kennels as $k): ?>
				<option value="<?php echo h($k); ?>" <?php echo ($selectedKennel == $k) ? 'selected' : ''; ?>>
					<?php echo h($k); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</form>
		
		<?php if ($analysis): ?>
		<h2>Analysis for <?php echo h($selectedKennel); ?></h2>
		
		<div class="stats-grid">
			<div class="stat-box">
				<div class="stat-label">Total Events</div>
				<div class="stat-value"><?php echo $analysis['total']; ?></div>
			</div>
			<div class="stat-box success">
				<div class="stat-label">Actual Runs</div>
				<div class="stat-value"><?php echo count($analysis['actual_runs']); ?></div>
			</div>
			<div class="stat-box <?php echo count($analysis['no_location']) > 0 ? 'warning' : ''; ?>">
				<div class="stat-label">No Location</div>
				<div class="stat-value"><?php echo count($analysis['no_location']); ?></div>
				<div class="stat-detail">Won't count toward run #</div>
			</div>
			<div class="stat-box <?php echo count($analysis['cancelled']) > 0 ? 'danger' : ''; ?>">
				<div class="stat-label">Cancelled</div>
				<div class="stat-value"><?php echo count($analysis['cancelled']); ?></div>
				<div class="stat-detail">Won't count toward run #</div>
			</div>
			<div class="stat-box <?php echo count($analysis['joke_runs']) > 0 ? 'warning' : ''; ?>">
				<div class="stat-label">Joke Run #s (69)</div>
				<div class="stat-value"><?php echo count($analysis['joke_runs']); ?></div>
				<div class="stat-detail">Won't count toward run #</div>
			</div>
		</div>
		
		<?php if ($analysis['first_event']): ?>
		<div class="stat-box" style="margin-bottom: 20px;">
			<div class="stat-label">First Known Event</div>
			<div class="stat-value"><?php echo h($analysis['first_event']['date']); ?></div>
			<div class="stat-detail">
				Run #<?php echo h($analysis['first_run_number'] ? $analysis['first_run_number'] : 'unknown'); ?>
			</div>
		</div>
		<?php endif; ?>
		
		<?php if ($analysis['next_run']): ?>
		<div class="recommendation-box">
			<h3>📅 Next Scheduled Run</h3>
			<p>
				<strong>Date:</strong> <?php echo h($analysis['next_run']['date']); ?><br>
				<strong>Current Run #:</strong> <?php echo h($analysis['next_run']['run'] ? $analysis['next_run']['run'] : '(not set)'); ?><br>
				<strong>Recommended Run #:</strong> <span style="font-size: 20px; color: #28a745; font-weight: bold;"><?php echo $analysis['recommended_next'] ? $analysis['recommended_next'] : 'Unable to calculate'; ?></span>
			</p>
			
			<?php if ($analysis['recommended_next']): ?>
			<div class="fix-form">
				<form method="POST">
					<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
					<input type="hidden" name="kennel" value="<?php echo h($selectedKennel); ?>">
					
					<label>Starting Run Number for Next Run:</label><br>
					<input type="number" name="starting_run" value="<?php echo $analysis['recommended_next']; ?>" min="1" style="margin: 10px 0;">
					<br><br>
					<button type="submit" name="fix_runs" class="btn btn-success" onclick="return confirm('This will update all future run numbers for <?php echo h($selectedKennel); ?> starting from the number you entered.\n\nEvents without a location, cancelled events, and joke run numbers will be skipped.\n\nContinue?');">
						🔧 Fix Future Run Numbers for This Kennel
					</button>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php else: ?>
		<div class="recommendation-box" style="border-color: #ffc107; background: #fff8e1;">
			<h3>⚠️ No Future Runs Found</h3>
			<p>No events found on or after today's date for this kennel.</p>
		</div>
		<?php endif; ?>
		
		<!-- Collapsible sections for details -->
		<?php if (count($analysis['no_location']) > 0): ?>
		<h3 class="collapsible" onclick="toggleCollapse(this)">📍 Events Without Location (<?php echo count($analysis['no_location']); ?>)</h3>
		<div class="collapse-content">
			<div class="event-list">
				<?php foreach ($analysis['no_location'] as $event): ?>
				<div class="event-item">
					<a href="<?php echo $event['year']; ?>/event.php?year=<?php echo $event['year']; ?>&month=<?php echo $event['month']; ?>&day=<?php echo $event['day']; ?>&no=<?php echo $event['no']; ?>" target="_blank">
						<span class="event-date"><?php echo h($event['date']); ?></span>
						<?php if ($event['run']): ?>
						<span class="event-run">Run #<?php echo h($event['run']); ?></span>
						<?php endif; ?>
						<?php if ($event['title']): ?>
						<span class="event-title"><?php echo h($event['title']); ?></span>
						<?php endif; ?>
					</a>
					<span class="event-note">No address set</span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		
		<?php if (count($analysis['cancelled']) > 0): ?>
		<h3 class="collapsible" onclick="toggleCollapse(this)">❌ Cancelled Events (<?php echo count($analysis['cancelled']); ?>)</h3>
		<div class="collapse-content">
			<div class="event-list">
				<?php foreach ($analysis['cancelled'] as $event): ?>
				<div class="event-item">
					<a href="<?php echo $event['year']; ?>/event.php?year=<?php echo $event['year']; ?>&month=<?php echo $event['month']; ?>&day=<?php echo $event['day']; ?>&no=<?php echo $event['no']; ?>" target="_blank">
						<span class="event-date"><?php echo h($event['date']); ?></span>
						<?php if ($event['run']): ?>
						<span class="event-run">Run #<?php echo h($event['run']); ?></span>
						<?php endif; ?>
						<?php if ($event['title']): ?>
						<span class="event-title"><?php echo h($event['title']); ?></span>
						<?php endif; ?>
					</a>
					<span class="event-note">Contains "cancel" in title/description</span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		
		<?php if (count($analysis['joke_runs']) > 0): ?>
		<h3 class="collapsible" onclick="toggleCollapse(this)">😂 Joke Run Numbers (<?php echo count($analysis['joke_runs']); ?>)</h3>
		<div class="collapse-content">
			<div class="event-list">
				<?php foreach ($analysis['joke_runs'] as $event): ?>
				<div class="event-item">
					<a href="<?php echo $event['year']; ?>/event.php?year=<?php echo $event['year']; ?>&month=<?php echo $event['month']; ?>&day=<?php echo $event['day']; ?>&no=<?php echo $event['no']; ?>" target="_blank">
						<span class="event-date"><?php echo h($event['date']); ?></span>
						<span class="event-run">Run #<?php echo h($event['run']); ?></span>
						<?php if ($event['title']): ?>
						<span class="event-title"><?php echo h($event['title']); ?></span>
						<?php endif; ?>
					</a>
					<span class="event-note">Contains 69 (not in sequence)</span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		
		<?php if (count($analysis['future_runs']) > 0): ?>
		<h3 class="collapsible" onclick="toggleCollapse(this)">📆 All Future Events (<?php echo count($analysis['future_runs']); ?>)</h3>
		<div class="collapse-content">
			<div class="event-list">
				<?php foreach ($analysis['future_runs'] as $event): ?>
				<?php 
				$isSkipped = empty($event['address']) || 
				             stripos($event['desc'], 'cancel') !== false ||
				             stripos($event['title'], 'cancel') !== false ||
				             (strpos($event['run'], '69') !== false);
				?>
				<div class="event-item" style="<?php echo $isSkipped ? 'opacity: 0.6;' : ''; ?>">
					<a href="<?php echo $event['year']; ?>/event.php?year=<?php echo $event['year']; ?>&month=<?php echo $event['month']; ?>&day=<?php echo $event['day']; ?>&no=<?php echo $event['no']; ?>" target="_blank">
						<span class="event-date"><?php echo h($event['date']); ?></span>
						<span class="event-run">Run #<?php echo h($event['run'] ? $event['run'] : '(none)'); ?></span>
						<?php if ($event['title']): ?>
						<span class="event-title"><?php echo h($event['title']); ?></span>
						<?php endif; ?>
					</a>
					<?php if ($isSkipped): ?>
					<span class="event-note">(will be skipped)</span>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		
		<?php endif; ?>
	</div>
	
	<script>
	function toggleCollapse(element) {
		element.classList.toggle('collapsed');
		var content = element.nextElementSibling;
		content.classList.toggle('hidden');
	}
	</script>
</body>
</html>