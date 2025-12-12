<?php
/**
 * Password Hash Generator
 * Generates MD5 hashes compatible with edit.php authentication
 */

$username = '';
$password = '';
$hash = '';
$output = '';
$saveStatus = '';

// File to store hashes
$hashLogFile = 'password_hashes.txt';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate'])) {
	$username = isset($_POST['username']) ? trim($_POST['username']) : '';
	$password = isset($_POST['password']) ? $_POST['password'] : '';
	
	if (!empty($username) && !empty($password)) {
		$hash = md5($password);
		$output = sprintf("'%s' => '%s'", $username, $hash);
		
		// Create log entry - paste-ready format with date as comment
		$timestamp = date('Y-m-d H:i:s');
		$logEntry = sprintf("\t%s, // %s\n", $output, $timestamp);
		
		// Save to local file
		if (file_put_contents($hashLogFile, $logEntry, FILE_APPEND | LOCK_EX) !== false) {
			$saveStatus = 'success';
		} else {
			$saveStatus = 'error';
		}
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Password Hash Generator</title>
	<style>
		body { 
			font-family: Arial, sans-serif; 
			background: #f5f5f5; 
			padding: 20px;
		}
		.container { 
			max-width: 500px; 
			margin: 50px auto; 
			padding: 30px; 
			background: white; 
			border-radius: 5px; 
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
		}
		h1 { 
			margin-top: 0; 
			color: #333;
		}
		.form-group { 
			margin-bottom: 15px; 
		}
		.form-group label { 
			display: block; 
			margin-bottom: 5px; 
			font-weight: bold; 
		}
		.form-group input { 
			width: 100%; 
			padding: 10px; 
			box-sizing: border-box; 
			border: 1px solid #ddd; 
			font-size: 16px;
		}
		.btn-generate { 
			width: 100%; 
			padding: 12px; 
			background: #4CAF50; 
			color: white; 
			border: none; 
			cursor: pointer; 
			font-size: 16px;
		}
		.btn-generate:hover { 
			background: #45a049; 
		}
		.output-box {
			margin-top: 20px;
			padding: 15px;
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-radius: 3px;
		}
		.output-box h3 {
			margin-top: 0;
			color: #333;
		}
		.output-code {
			font-family: 'Courier New', monospace;
			font-size: 14px;
			background: #2d2d2d;
			color: #f8f8f2;
			padding: 15px;
			border-radius: 3px;
			word-break: break-all;
			user-select: all;
		}
		.instructions {
			margin-top: 15px;
			padding: 10px;
			background: #d1ecf1;
			border-radius: 3px;
			font-size: 14px;
			color: #0c5460;
		}
		.hash-only {
			margin-top: 10px;
			font-family: 'Courier New', monospace;
			font-size: 12px;
			color: #666;
		}
		.status-success {
			margin-top: 10px;
			padding: 8px;
			background: #d4edda;
			color: #155724;
			border-radius: 3px;
			font-size: 14px;
		}
		.status-error {
			margin-top: 10px;
			padding: 8px;
			background: #f8d7da;
			color: #721c24;
			border-radius: 3px;
			font-size: 14px;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>🔐 Password Hash Generator</h1>
		<p>Generate MD5 password hashes for use in edit.php</p>
		
		<form method="POST">
			<div class="form-group">
				<label>Username:</label>
				<input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
			</div>
			<div class="form-group">
				<label>Password:</label>
				<input type="text" name="password" value="<?php echo htmlspecialchars($password); ?>" required>
			</div>
			<button type="submit" name="generate" class="btn-generate">Generate Hash</button>
		</form>
		
		<?php if (!empty($output)): ?>
		<div class="output-box">
			<h3>✓ Generated Hash</h3>
			<p>Copy this line into the <code>$USERS</code> array in edit.php:</p>
			<div class="output-code"><?php echo htmlspecialchars($output); ?></div>
			<div class="hash-only">Hash only: <?php echo htmlspecialchars($hash); ?></div>
			
			<?php if ($saveStatus == 'success'): ?>
				<div class="status-success">✓ Saved to <?php echo htmlspecialchars($hashLogFile); ?></div>
			<?php else: ?>
				<div class="status-error">✗ Failed to save to file</div>
			<?php endif; ?>
			
			<div class="instructions">
				<strong>Instructions:</strong><br>
				1. Open edit.php<br>
				2. Find the <code>$USERS = array(</code> section near the top<br>
				3. Add a comma after the last entry<br>
				4. Paste the line above<br>
				5. Save the file
			</div>
		</div>
		<?php endif; ?>
	</div>
</body>
</html>