<?php
/**
 * Kennel Defaults Editor v1.1
 * Manages kennel templates using defaults.txt (same format as event data)
 * 
 * defaults.txt columns:
 * DAY	KENNEL	ICON	TITLE	RUN	HARES	TIME	START	MAP	HASHCASH	TURDS	TWEET	TWILIGHT	DATE	DESC	UPDATE
 * 0    1       2      3      4    5      6      7      8    9         10     11     12        13    14    15
 * 
 * Meaningful defaults: ICON(2), TIME(6), START(7), MAP(8), HASHCASH(9), TURDS(10), DESC(14)
 */

// Timezone configuration
date_default_timezone_set('America/Chicago');

// ============================================
// SECURITY FUNCTIONS
// ============================================
function sanitizeInput($input) {
	if (is_array($input)) {
		return array_map('sanitizeInput', $input);
	}
	$input = trim($input);
	$input = stripslashes($input);
	return $input;
}

function h($str) {
	return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// CSRF Protection
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

// Simple MD5 verification
function verify_password($password, $hash) {
	return md5($password) === $hash;
}

// ============================================
// AUTHENTICATION
// ============================================
require_once(dirname(__FILE__) . '/users.php');

session_start();

// Login handler
if (isset($_POST['login'])) {
	$username = sanitizeInput($_POST['username']);
	$password = $_POST['password'];
	
	if (isset($USERS[$username]) && verify_password($password, $USERS[$username])) {
		session_regenerate_id(true);
		$_SESSION['kd_authenticated'] = true;
		$_SESSION['kd_username'] = $username;
		$_SESSION['kd_login_time'] = time();
	} else {
		$loginError = "Invalid username or password";
		error_log("Failed kenneldefaults login attempt for user: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
	}
}

// Logout handler
if (isset($_GET['logout'])) {
	unset($_SESSION['kd_authenticated']);
	unset($_SESSION['kd_username']);
	unset($_SESSION['kd_login_time']);
	header('Location: kenneldefaults.php');
	exit;
}

// Session timeout (30 minutes)
$timeout_duration = 1800;
if (isset($_SESSION['kd_login_time'])) {
	if (time() - $_SESSION['kd_login_time'] > $timeout_duration) {
		unset($_SESSION['kd_authenticated']);
		header('Location: kenneldefaults.php?timeout=1');
		exit;
	}
	$_SESSION['kd_login_time'] = time();
}

// Check authentication
if (!isset($_SESSION['kd_authenticated']) || $_SESSION['kd_authenticated'] !== true) {
	// Show login form
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Kennel Defaults - Login</title>
		<style>
			body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
			.login-container { 
				max-width: 400px; margin: 50px auto; background: white; 
				padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
			}
			.login-container h2 { margin-top: 0; }
			input[type="text"], input[type="password"] { 
				width: 100%; padding: 12px; margin: 8px 0 16px 0; 
				border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; 
			}
			.btn-login { 
				width: 100%; padding: 12px; background: #4CAF50; color: white; 
				border: none; border-radius: 5px; cursor: pointer; font-size: 16px; 
			}
			.btn-login:hover { background: #45a049; }
			.error { color: #d9534f; margin-bottom: 15px; }
			.timeout { color: #f0ad4e; margin-bottom: 15px; }
		</style>
	</head>
	<body>
		<div class="login-container">
			<h2>🏠 Kennel Defaults</h2>
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
// KENNEL DATA FUNCTIONS
// ============================================
define('DEFAULTS_FILE', dirname(__FILE__) . '/defaults.txt');

// Column indices
define('COL_DAY', 0);
define('COL_KENNEL', 1);
define('COL_ICON', 2);
define('COL_TITLE', 3);
define('COL_RUN', 4);
define('COL_HARES', 5);
define('COL_TIME', 6);
define('COL_START', 7);
define('COL_MAP', 8);
define('COL_HASHCASH', 9);
define('COL_TURDS', 10);
define('COL_TWEET', 11);
define('COL_TWILIGHT', 12);
define('COL_DATE', 13);
define('COL_DESC', 14);
define('COL_UPDATE', 15);

function loadKennelsRaw() {
	if (!file_exists(DEFAULTS_FILE)) {
		return array('header' => '', 'kennels' => array());
	}
	
	$kennels = array();
	$lines = file(DEFAULTS_FILE, FILE_IGNORE_NEW_LINES);
	$header = '';
	
	foreach ($lines as $idx => $line) {
		// Save header
		if ($idx == 0 && strpos($line, 'DAY') === 0) {
			$header = $line;
			continue;
		}
		
		$parts = explode("\t", $line);
		$name = isset($parts[COL_KENNEL]) ? trim($parts[COL_KENNEL]) : '';
		
		if (!empty($name)) {
			$kennels[$name] = array(
				'raw' => $parts,
				'id' => isset($parts[COL_DAY]) ? trim($parts[COL_DAY]) : '',
				'name' => $name,
				'icon' => isset($parts[COL_ICON]) ? trim($parts[COL_ICON]) : '',
				'title' => isset($parts[COL_TITLE]) ? trim($parts[COL_TITLE]) : '',
				'time' => isset($parts[COL_TIME]) ? trim($parts[COL_TIME]) : '',
				'start' => isset($parts[COL_START]) ? trim($parts[COL_START]) : '',
				'map' => isset($parts[COL_MAP]) ? trim($parts[COL_MAP]) : '',
				'hashcash' => isset($parts[COL_HASHCASH]) ? trim($parts[COL_HASHCASH]) : '',
				'turds' => isset($parts[COL_TURDS]) ? trim($parts[COL_TURDS]) : '',
				'desc' => isset($parts[COL_DESC]) ? trim($parts[COL_DESC]) : '',
				'update' => isset($parts[COL_UPDATE]) ? trim($parts[COL_UPDATE]) : ''
			);
		}
	}
	
	ksort($kennels);
	return array('header' => $header, 'kennels' => $kennels);
}

function saveKennels($header, $kennels) {
	$lines = array();
	if (!empty($header)) {
		$lines[] = $header;
	} else {
		$lines[] = "DAY\tKENNEL\tICON\tTITLE\tRUN\tHARES\tTIME\tSTART\tMAP\tHASHCASH\tTURDS\tTWEET\tTWILIGHT\tDATE\tDESC\tUPDATE";
	}
	
	// Sort by ID
	uasort($kennels, create_function('$a, $b', 'return intval($a["id"]) - intval($b["id"]);'));
	
	foreach ($kennels as $k) {
		// Rebuild line from raw data if available, otherwise create new
		if (isset($k['raw']) && is_array($k['raw'])) {
			$parts = $k['raw'];
			// Ensure we have enough columns
			while (count($parts) < 16) {
				$parts[] = '';
			}
		} else {
			$parts = array_fill(0, 16, '');
		}
		
		// Update the editable fields
		$parts[COL_DAY] = $k['id'];
		$parts[COL_KENNEL] = $k['name'];
		$parts[COL_ICON] = $k['icon'];
		$parts[COL_TIME] = $k['time'];
		$parts[COL_START] = $k['start'];
		$parts[COL_MAP] = $k['map'];
		$parts[COL_HASHCASH] = $k['hashcash'];
		$parts[COL_TURDS] = $k['turds'];
		$parts[COL_DESC] = $k['desc'];
		$parts[COL_UPDATE] = $k['update'];
		
		$lines[] = implode("\t", $parts);
	}
	
	file_put_contents(DEFAULTS_FILE, implode("\n", $lines), LOCK_EX);
}

function getNextKennelId($kennels) {
	$maxId = 0;
	foreach ($kennels as $k) {
		$id = intval($k['id']);
		if ($id > $maxId) $maxId = $id;
	}
	return sprintf('%02d', $maxId + 1);
}

// Get available icon files from current year folder
function getIconFiles() {
	$icons = array();
	$currentYear = date('Y');
	$iconDir = dirname(__FILE__) . '/' . $currentYear;
	
	// Fall back to current directory if year folder doesn't exist
	if (!is_dir($iconDir)) {
		$iconDir = dirname(__FILE__);
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

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================
$message = '';
$messageType = '';

$data = loadKennelsRaw();
$header = $data['header'];
$kennels = $data['kennels'];

// Handle save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request.";
		$messageType = "error";
	} else {
		$kennelName = trim($_POST['kennel_name']);
		$isNew = isset($_POST['is_new']) && $_POST['is_new'] == '1';
		
		if (empty($kennelName)) {
			$message = "Kennel name is required.";
			$messageType = "error";
		} else {
			// Get or create kennel data
			if (isset($kennels[$kennelName])) {
				$k = $kennels[$kennelName];
			} else {
				$k = array(
					'id' => getNextKennelId($kennels),
					'name' => $kennelName,
					'raw' => array_fill(0, 16, '')
				);
			}
			
			// Update fields
			$k['icon'] = isset($_POST['icon']) ? trim($_POST['icon']) : '';
			$k['time'] = isset($_POST['time']) ? trim($_POST['time']) : '';
			$k['start'] = isset($_POST['start']) ? str_replace("\n", "<br />", trim($_POST['start'])) : '';
			$k['map'] = isset($_POST['map']) ? trim($_POST['map']) : '';
			$k['hashcash'] = isset($_POST['hashcash']) ? trim($_POST['hashcash']) : '';
			$k['turds'] = isset($_POST['turds']) ? trim($_POST['turds']) : '';
			$k['desc'] = isset($_POST['desc']) ? str_replace("\n", "<br />", trim($_POST['desc'])) : '';
			$k['update'] = date('n/j/y G:i') . ' (' . $_SESSION['kd_username'] . ')';
			
			$kennels[$kennelName] = $k;
			saveKennels($header, $kennels);
			
			$message = "Kennel \"" . h($kennelName) . "\" saved successfully.";
			$messageType = "success";
			
			// Reload
			$data = loadKennelsRaw();
			$header = $data['header'];
			$kennels = $data['kennels'];
		}
	}
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request.";
		$messageType = "error";
	} else {
		$kennelName = trim($_POST['kennel_name']);
		if (isset($kennels[$kennelName])) {
			unset($kennels[$kennelName]);
			saveKennels($header, $kennels);
			$message = "Kennel \"" . h($kennelName) . "\" deleted.";
			$messageType = "success";
		} else {
			$message = "Could not delete kennel.";
			$messageType = "error";
		}
	}
}

$availableIcons = getIconFiles();

// Icon path for current year (for preview URLs)
$currentYear = date('Y');
$iconPath = $currentYear . '/';
if (!is_dir(dirname(__FILE__) . '/' . $currentYear)) {
	$iconPath = ''; // Fall back to same directory
}

// Get selected kennel for editing
$selectedKennel = null;
$editKennelName = isset($_GET['edit']) ? trim($_GET['edit']) : '';
if (!empty($editKennelName) && $editKennelName !== '__new__' && isset($kennels[$editKennelName])) {
	$selectedKennel = $kennels[$editKennelName];
}
$isNewKennel = (isset($_GET['edit']) && $_GET['edit'] === '__new__');

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Kennel Defaults Editor</title>
	<style>
		body { 
			font-family: Arial, sans-serif; 
			background: #f5f5f5; 
			margin: 0; 
			padding: 20px; 
		}
		.container { 
			max-width: 1000px; 
			margin: 0 auto; 
			background: white; 
			padding: 30px; 
			border-radius: 10px; 
			box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
		}
		h1 { margin-top: 0; color: #333; }
		h2 { color: #555; border-bottom: 1px solid #eee; padding-bottom: 10px; }
		
		.user-info { 
			float: right; 
			color: #666; 
			font-size: 14px; 
		}
		
		.message { 
			padding: 15px; 
			border-radius: 5px; 
			margin-bottom: 20px; 
		}
		.message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		.message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
		
		.two-column {
			display: flex;
			gap: 30px;
		}
		.column-left {
			flex: 0 0 250px;
		}
		.column-right {
			flex: 1;
		}
		
		.kennel-list {
			border: 1px solid #ddd;
			border-radius: 5px;
			max-height: 500px;
			overflow-y: auto;
		}
		.kennel-list a {
			display: block;
			padding: 10px 15px;
			text-decoration: none;
			color: #333;
			border-bottom: 1px solid #eee;
		}
		.kennel-list a:last-child { border-bottom: none; }
		.kennel-list a:hover { background: #f5f5f5; }
		.kennel-list a.selected { background: #e3f2fd; font-weight: bold; }
		
		.form-group { margin-bottom: 20px; }
		.form-group label { 
			display: block; 
			font-weight: bold; 
			margin-bottom: 5px; 
			color: #333; 
		}
		.form-group input[type="text"],
		.form-group textarea,
		.form-group select { 
			width: 100%; 
			padding: 10px; 
			border: 1px solid #ddd; 
			border-radius: 5px; 
			box-sizing: border-box;
			font-size: 14px;
		}
		.form-group textarea { min-height: 80px; resize: vertical; }
		.form-group small { 
			display: block; 
			color: #888; 
			margin-top: 5px; 
			font-style: italic;
		}
		.no-default {
			color: #aaa;
		}
		
		.btn { 
			padding: 10px 20px; 
			border: none; 
			border-radius: 5px; 
			cursor: pointer; 
			font-size: 14px;
			text-decoration: none;
			display: inline-block;
		}
		.btn-save { background: #4CAF50; color: white; }
		.btn-save:hover { background: #45a049; }
		.btn-delete { background: #dc3545; color: white; }
		.btn-delete:hover { background: #c82333; }
		.btn-new { background: #007bff; color: white; }
		.btn-new:hover { background: #0069d9; }
		
		.icon-preview {
			max-width: 100px;
			max-height: 75px;
			margin-top: 10px;
			border: 1px solid #ddd;
			border-radius: 3px;
		}
		
		.updated-info {
			font-size: 12px;
			color: #666;
			margin-top: 15px;
			padding-top: 15px;
			border-top: 1px solid #eee;
		}
		
		.button-group {
			display: flex;
			gap: 10px;
			margin-top: 20px;
		}
		
		@media (max-width: 768px) {
			.two-column { flex-direction: column; }
			.column-left { flex: none; }
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="user-info">
			Logged in as: <strong><?php echo h($_SESSION['kd_username']); ?></strong>
			| <a href="?logout=1">Logout</a>
		</div>
		
		<h1>🏠 Kennel Defaults Editor</h1>
		<p>Manage default values for each kennel. These defaults are offered when creating new events in edit.php.</p>
		
		<?php if (!empty($message)): ?>
		<div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
		<?php endif; ?>
		
		<div class="two-column">
			<div class="column-left">
				<h2>Kennels</h2>
				<a href="?edit=__new__" class="btn btn-new" style="width: 100%; text-align: center; margin-bottom: 15px; box-sizing: border-box;">+ Add New Kennel</a>
				
				<div class="kennel-list">
					<?php if (empty($kennels)): ?>
					<div style="padding: 15px; color: #666;">No kennels defined yet.</div>
					<?php else: ?>
					<?php foreach ($kennels as $name => $k): ?>
					<a href="?edit=<?php echo urlencode($name); ?>" class="<?php echo ($editKennelName === $name) ? 'selected' : ''; ?>">
						<?php echo h($name); ?>
					</a>
					<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
			
			<div class="column-right">
				<?php if ($isNewKennel || $selectedKennel): ?>
				<h2><?php echo $isNewKennel ? 'Add New Kennel' : 'Edit: ' . h($editKennelName); ?></h2>
				
				<form method="POST">
					<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
					<input type="hidden" name="is_new" value="<?php echo $isNewKennel ? '1' : '0'; ?>">
					
					<div class="form-group">
						<label>Kennel Name: *</label>
						<input type="text" name="kennel_name" value="<?php echo h($selectedKennel ? $selectedKennel['name'] : ''); ?>" required <?php echo ($selectedKennel ? 'readonly' : ''); ?>>
						<?php if ($selectedKennel): ?>
						<small>Kennel name cannot be changed. Create a new kennel if needed.</small>
						<?php endif; ?>
					</div>
					
					<div class="form-group">
						<label>Default Icon:</label>
						<select name="icon" id="iconSelect" onchange="updateIconPreview()">
							<option value="">-- No default --</option>
							<?php foreach ($availableIcons as $icon): ?>
							<option value="<?php echo h($icon); ?>" <?php echo ($selectedKennel && $selectedKennel['icon'] === $icon) ? 'selected' : ''; ?>>
								<?php echo h($icon); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<?php if ($selectedKennel && !empty($selectedKennel['icon'])): ?>
						<img id="iconPreview" class="icon-preview" src="<?php echo h($iconPath . $selectedKennel['icon']); ?>">
						<?php else: ?>
						<img id="iconPreview" class="icon-preview" style="display: none;">
						<small class="no-default">No default for <?php echo h($selectedKennel ? $selectedKennel['name'] : 'this kennel'); ?></small>
						<?php endif; ?>
					</div>
					
					<div class="form-group">
						<label>Default Time:</label>
						<input type="text" name="time" value="<?php echo h($selectedKennel ? $selectedKennel['time'] : ''); ?>" placeholder="e.g., 2:00 PM or 7:00 PM">
						<?php if (!$selectedKennel || empty($selectedKennel['time'])): ?>
						<small class="no-default">No default for <?php echo h($selectedKennel ? $selectedKennel['name'] : 'this kennel'); ?></small>
						<?php else: ?>
						<small>Format: H:MM AM/PM (for EditHash 1.28 compatibility)</small>
						<?php endif; ?>
					</div>
					
					<div class="form-group">
						<label>Default Hash Cash:</label>
						<textarea name="hashcash" placeholder="e.g., $7.00 cash - PayPal link"><?php echo h($selectedKennel ? $selectedKennel['hashcash'] : ''); ?></textarea>
						<?php if (!$selectedKennel || empty($selectedKennel['hashcash'])): ?>
						<small class="no-default">No default for <?php echo h($selectedKennel ? $selectedKennel['name'] : 'this kennel'); ?></small>
						<?php endif; ?>
					</div>
					
					<div class="form-group">
						<label>Default TURDs (Dogs):</label>
						<input type="text" name="turds" value="<?php echo h($selectedKennel ? $selectedKennel['turds'] : ''); ?>" placeholder="e.g., Yes, keep on leash">
						<?php if (!$selectedKennel || empty($selectedKennel['turds'])): ?>
						<small class="no-default">No default for <?php echo h($selectedKennel ? $selectedKennel['name'] : 'this kennel'); ?></small>
						<?php endif; ?>
					</div>
					
					<div class="form-group">
						<label>Default Address:</label>
						<textarea name="start" placeholder="e.g., 123 Main St, Dallas TX 75201"><?php echo h($selectedKennel ? str_replace('<br />', "\n", $selectedKennel['start']) : ''); ?></textarea>
						<?php if (!$selectedKennel || empty($selectedKennel['start'])): ?>
						<small class="no-default">No default for <?php echo h($selectedKennel ? $selectedKennel['name'] : 'this kennel'); ?></small>
						<?php else: ?>
						<small>Useful for recurring events at the same location.</small>
						<?php endif; ?>
					</div>
					
					<div class="form-group">
						<label>Default Map Link:</label>
						<input type="text" name="map" value="<?php echo h($selectedKennel ? $selectedKennel['map'] : ''); ?>" placeholder="https://maps.google.com/...">
						<?php if (!$selectedKennel || empty($selectedKennel['map'])): ?>
						<small class="no-default">No default for <?php echo h($selectedKennel ? $selectedKennel['name'] : 'this kennel'); ?></small>
						<?php endif; ?>
					</div>
					
					<div class="form-group">
						<label>Default Description:</label>
						<textarea name="desc" rows="4" placeholder="Boilerplate description for this kennel's events"><?php echo h($selectedKennel ? str_replace('<br />', "\n", $selectedKennel['desc']) : ''); ?></textarea>
						<?php if (!$selectedKennel || empty($selectedKennel['desc'])): ?>
						<small class="no-default">No default for <?php echo h($selectedKennel ? $selectedKennel['name'] : 'this kennel'); ?></small>
						<?php else: ?>
						<small>Useful for recurring events like Trivia, Movie Night, etc.</small>
						<?php endif; ?>
					</div>
					
					<div class="button-group">
						<button type="submit" name="save" class="btn btn-save">💾 Save Kennel</button>
						<?php if ($selectedKennel): ?>
						<button type="submit" name="delete" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this kennel?\n\nThis only removes it from defaults - existing events are not affected.');">🗑️ Delete</button>
						<?php endif; ?>
					</div>
					
					<?php if ($selectedKennel && !empty($selectedKennel['update'])): ?>
					<div class="updated-info">
						Last updated: <?php echo h($selectedKennel['update']); ?>
					</div>
					<?php endif; ?>
				</form>
				<?php else: ?>
				<h2>Select a Kennel</h2>
				<p>Select a kennel from the list to edit its defaults, or click "Add New Kennel" to create one.</p>
				<p>Fields left blank will show <span class="no-default">"No default"</span> and won't be pre-filled when creating events in edit.php.</p>
				<p><strong>Available default fields:</strong></p>
				<ul>
					<li><strong>Icon</strong> - Kennel logo image</li>
					<li><strong>Time</strong> - Default start time</li>
					<li><strong>Hash Cash</strong> - Typical payment info</li>
					<li><strong>TURDs</strong> - Dog policy</li>
					<li><strong>Address</strong> - For recurring locations</li>
					<li><strong>Map Link</strong> - Google Maps URL</li>
					<li><strong>Description</strong> - Boilerplate text for recurring events</li>
				</ul>
				<?php endif; ?>
			</div>
		</div>
		
		<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
			<a href="edit.php">← Back to Event Editor</a>
		</div>
	</div>
	
	<script>
	var iconPath = '<?php echo $iconPath; ?>';
	function updateIconPreview() {
		var select = document.getElementById('iconSelect');
		var preview = document.getElementById('iconPreview');
		if (select && preview) {
			if (select.value) {
				preview.src = iconPath + select.value;
				preview.style.display = 'inline-block';
			} else {
				preview.style.display = 'none';
			}
		}
	}
	</script>
</body>
</html>