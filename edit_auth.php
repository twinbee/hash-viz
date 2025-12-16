<?php
// ============================================
// EDIT_AUTH.PHP - Authentication for edit.php
// Version 2.3
// ============================================

// Load users from separate file
require_once('users.php');

session_start();

// Regenerate session ID on login to prevent session fixation
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
		header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
		exit;
	} else {
		$loginError = "Invalid username or password";
		error_log("Failed login attempt for user: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
	}
}

// Logout handler
if (isset($_GET['logout'])) {
	session_destroy();
	$redirect_url = $_SERVER['PHP_SELF'];
	header('Location: ' . $redirect_url);
	exit;
}

// Session timeout (30 minutes)
$timeout_duration = 1800;
if (isset($_SESSION['login_time'])) {
	if (time() - $_SESSION['login_time'] > $timeout_duration) {
		session_destroy();
		header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&timeout=1');
		exit;
	}
	$_SESSION['login_time'] = time();
}

// Check authentication - show login form if not authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
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
			.instructions-box h3 { margin-top: 0; color: #333; }
			.instructions-box ol { margin: 0; padding-left: 20px; }
			.instructions-box li { margin-bottom: 10px; }
			.instructions-box a { color: #0066cc; }
			.instructions-box .note { font-size: 12px; color: #666; font-style: italic; margin-top: 10px; }
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