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
				max-width: 400px; 
				margin: 100px auto; 
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
// MAIN SCRIPT
// ============================================
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
		.success { color: green; padding: 10px; background: #d4edda; margin-bottom: 15px; border-radius: 3px; }
		.error { color: red; padding: 10px; background: #f8d7da; margin-bottom: 15px; border-radius: 3px; }
		.info { color: blue; padding: 10px; background: #d1ecf1; margin-bottom: 15px; border-radius: 3px; }
		.header { overflow: auto; margin-bottom: 20px; }
		.backup-info { font-size: 12px; color: #666; margin-top: 5px; }
		.user-info { float: left; color: #666; font-size: 14px; margin-top: 10px; }
	</style>

<?php
	$year = $_GET["year"];
	$month = $_GET["month"];
	$day = $_GET["day"];
	$no = $_GET["no"];
	
	$message = "";
	$messageType = "";
	$backupCreated = "";
	
	// Handle form submission
	if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
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
		// This prevents overwriting changes made by other users
		$lines = file($filename, FILE_IGNORE_NEW_LINES);
		$newLines = array();
		$n = 0;
		$lastDay = "";
		$lineIndex = 0;
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
				
				// Convert line endings to <br /> for description and address fields
				// Also convert existing <br> or <br/> tags to <br />
				$desc = $_POST['desc'];
				$desc = str_replace("<br />", "\n", $desc);
				$desc = str_replace("<br/>", "\n", $desc);
				$desc = str_replace("<br>", "\n", $desc);
				$desc = str_replace("\r\n", "<br />", $desc);
				$desc = str_replace("\n", "<br />", $desc);
				$desc = str_replace("\r", "<br />", $desc);
				
				// Remove any existing weather forecast block
				$desc = preg_replace('/<!-- WEATHER_START -->.*?<!-- WEATHER_END -->/s', '', $desc);
				
				// Add weather forecast to end of description
				// Extract location from address (Start address field) for weather
				$address = $_POST['address'];
				
				$address = str_replace("<br />", "\n", $address);
				$address = str_replace("<br/>", "\n", $address);
				$address = str_replace("<br>", "\n", $address);
				
				// Try to parse address for city/ZIP
				$weatherLocation = ''; 
				$weatherZip = '';
				
				// Try to find ZIP code (5 digits) - look for it anywhere in the address
				if (preg_match('/(\d{5})(?:-\d{4})?/', $address, $matches)) {
					$weatherZip = $matches[1];
					$weatherLocation = $weatherZip;
				}
				// If no ZIP, try to find city, state pattern (City, TX or City TX)
				else if (preg_match('/([A-Za-z\s]+),?\s*(?:TX|Texas)/i', $address, $matches)) {
					$cityName = trim($matches[1]);
					// Clean up city name - remove any leading/trailing non-letter characters
					$cityName = preg_replace('/[^A-Za-z\s]/', '', $cityName);
					$cityName = trim($cityName);
					if (!empty($cityName)) {
						$weatherLocation = $cityName . ', TX';
					}
				}
				
				// Default to Addison if no location found
				if (empty($weatherLocation)) {
					$weatherZip = '75001';
					$weatherLocation = 'Addison, TX';
				}
				
				// Default to Addison if no location found
				if (empty($weatherLocation)) {
					$weatherZip = '75001';
					$weatherLocation = 'Addison, TX';
				}
				
				// Use NWS forecast.weather.gov
				// Their search endpoint: https://forecast.weather.gov/zipcity.php?inputstring=75023
				$weatherQuery = $weatherLocation;
				$weatherUrl = 'https://forecast.weather.gov/zipcity.php?inputstring=' . urlencode($weatherQuery);
				
				// Build edit link
				$editLink = sprintf(
					'<a href="http://dfwhhh.org/calendar/%d/edit.php?month=%d&day=%d&year=%d&no=%d">edit</a>',
					$year, $month, $day, $year, $no
				);
				
				$weatherWidget = '<!-- WEATHER_START --><br /><br /><strong>Weather Forecast for ' . htmlspecialchars($weatherLocation) . ':</strong><br />';
				$weatherWidget .= '<iframe src="' . $weatherUrl . '" width="100%" height="600" frameborder="0" scrolling="yes" style="border: 1px solid #ccc;"></iframe>';
				$weatherWidget .= '<br />' . $editLink;
				$weatherWidget .= '<!-- WEATHER_END -->';
				
				$desc .= $weatherWidget;
				
				// Convert address line breaks
				$address = str_replace("\r\n", "<br />", $address);
				$address = str_replace("\n", "<br />", $address);
				$address = str_replace("\r", "<br />", $address);
				
				// Build edit link for the Update field (same link)
				$updateEditLink = '<br />' . $editLink;
				
				// Build updated line from POST data
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
					$_POST['date'],
					$desc,
					date('n/j/y G:i') . ' (edited by ' . $_SESSION['username'] . ')' . $updateEditLink
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
				$message = "✓ Event updated successfully!";
				$messageType = "success";
			} else {
				$message = "Error: Unable to write to file. Check file permissions.";
				$messageType = "error";
			}
		}
	}
	
	// Load current event data
	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
	$file = fopen($filename, "r");
	if (!$file) {
		echo "<p>Unable to open file.</p>";
		exit;
	}
	
	$n = 0;
	$lastDay = "";
	$data = array();
	
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
	
	//DAY = 0 KENNEL = 1 TYPE = 2 TITLE = 3 RUN = 4 HARES = 5 TIME = 6 ADDRESS = 7 
	//MAPLINK = 8 HASHCASH = 9 TURDS = 10 TWEET = 11 TWILIGHT = 12 DATE = 13 DESC = 14 UPDATED = 15
	
	$kennel = isset($data[1]) ? $data[1] : '';
	$dateDisplay = isset($data[13]) ? $data[13] : '';
	printf("<title>Edit %s for %s/%s/%s</title>\n", htmlspecialchars($kennel), $month, $day, $year);
?>
</head>
<body>
	<div id="container" class="edit-form">
		<div class="header">
			<div class="user-info">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
			<h1 style="clear: both;">Edit Event</h1>
			<a href="?logout=1" class="btn btn-logout">Logout</a>
		</div>
		<h2><?php echo htmlspecialchars($dateDisplay); ?></h2>
		
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
			<div class="form-group">
				<label>Kennel:</label>
				<input type="text" name="kennel" value="<?php echo htmlspecialchars($data[1]); ?>" required>
			</div>
			
			<div class="form-group">
				<label>Type/Icon:</label>
				<input type="text" name="type" value="<?php echo htmlspecialchars($data[2]); ?>">
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
				<input type="text" name="time" value="<?php echo htmlspecialchars($data[6]); ?>">
			</div>
			
			<div class="form-group">
				<label>Address:</label>
				<textarea name="address"><?php 
					$addr = $data[7];
					$addr = str_replace("<br />", "\n", $addr);
					$addr = str_replace("<br/>", "\n", $addr);
					$addr = str_replace("<br>", "\n", $addr);
					echo htmlspecialchars($addr); 
				?></textarea>
			</div>
			
			<div class="form-group">
				<label>Map Link:</label>
				<input type="text" name="maplink" value="<?php echo htmlspecialchars($data[8]); ?>">
			</div>
			
			<div class="form-group">
				<label>Hash Cash:</label>
				<input type="text" name="hashcash" value="<?php echo htmlspecialchars($data[9]); ?>">
			</div>
			
			<div class="form-group">
				<label>TURDs:</label>
				<input type="text" name="turds" value="<?php echo htmlspecialchars($data[10]); ?>">
			</div>
			
			<div class="form-group">
				<label>Date:</label>
				<input type="text" name="date" value="<?php echo htmlspecialchars($data[13]); ?>">
			</div>
			
			<div class="form-group">
				<label>Description:</label>
				<textarea name="desc" rows="10"><?php 
					$desc = $data[14];
					// Remove weather forecast block before editing
					$desc = preg_replace('/<!-- WEATHER_START -->.*?<!-- WEATHER_END -->/s', '', $desc);
					$desc = str_replace("<br />", "\n", $desc);
					$desc = str_replace("<br/>", "\n", $desc);
					$desc = str_replace("<br>", "\n", $desc);
					echo htmlspecialchars($desc); 
				?></textarea>
				<small style="color: #666;">Weather forecast will be automatically added at the end based on the address.</small>
			</div>
			
			<div class="form-group">
				<button type="submit" name="save" class="btn btn-save">💾 Save Changes</button>
				<a href="event.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>" class="btn btn-cancel">❌ Cancel</a>
			</div>
		</form>
		
		<p><small>Last updated: <?php echo isset($data[15]) ? htmlspecialchars($data[15]) : 'N/A'; ?></small></p>
	</div>
</body>
</html>