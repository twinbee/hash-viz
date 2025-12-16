<?php
// ============================================
// EDIT_HANDLERS.PHP - Form submission handlers for edit.php
// Version 2.3
// ============================================

// ============================================
// Handle ICON UPLOAD
// ============================================
$uploadedIcon = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['icon_upload']) && $_FILES['icon_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	} else {
		$uploadError = '';
		$file = $_FILES['icon_upload'];
		
		if ($file['error'] !== UPLOAD_ERR_OK) {
			$uploadError = 'Upload failed. Error code: ' . $file['error'];
		}
		
		if (empty($uploadError) && $file['size'] > 500 * 1024) {
			$uploadError = 'File too large. Maximum size is 500 KB.';
		}
		
		$allowedExts = array('png', 'jpg', 'jpeg', 'svg');
		$filename = strtolower($file['name']);
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		if (empty($uploadError) && !in_array($ext, $allowedExts)) {
			$uploadError = 'Invalid file type. Allowed: PNG, JPG, SVG.';
		}
		
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
		
		if (empty($uploadError) && in_array($ext, array('png', 'jpg', 'jpeg'))) {
			$imageInfo = getimagesize($file['tmp_name']);
			if ($imageInfo === false) {
				$uploadError = 'Could not read image dimensions.';
			} else {
				$width = $imageInfo[0];
				$height = $imageInfo[1];
				
				if ($width > 200 || $height > 150) {
					if (function_exists('imagecreatetruecolor')) {
						$ratio = min(200 / $width, 150 / $height);
						$newWidth = round($width * $ratio);
						$newHeight = round($height * $ratio);
						
						$newImage = imagecreatetruecolor($newWidth, $newHeight);
						
						if ($ext == 'png') {
							imagealphablending($newImage, false);
							imagesavealpha($newImage, true);
							$transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
							imagefill($newImage, 0, 0, $transparent);
						}
						
						if ($ext == 'png') {
							$srcImage = imagecreatefrompng($file['tmp_name']);
						} else {
							$srcImage = imagecreatefromjpeg($file['tmp_name']);
						}
						
						if ($srcImage) {
							imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
							
							$tempFile = $file['tmp_name'] . '_resized';
							if ($ext == 'png') {
								imagepng($newImage, $tempFile);
							} else {
								imagejpeg($newImage, $tempFile, 90);
							}
							
							imagedestroy($srcImage);
							imagedestroy($newImage);
							
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
		
		if (empty($uploadError)) {
			$yearDir = dirname(__FILE__) . '/' . $year;
			if (!is_dir($yearDir)) {
				$uploadError = 'Year directory does not exist: ' . $year;
			}
			
			if (empty($uploadError)) {
				$safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($file['name']));
				$destPath = $yearDir . '/' . $safeName;
				
				if (move_uploaded_file($file['tmp_name'], $destPath)) {
					$uploadedIcon = $safeName;
					$message = "Icon uploaded successfully to " . $year . "/: " . $safeName;
					$messageType = "success";
					$availableIcons = getIconFiles($year);
				} else {
					$uploadError = 'Failed to save uploaded file.';
				}
			}
			
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
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	} else {
		$filename = sprintf("../android/%d-%02d.txt", $year, $month);
		
		$eventInfo = "DELETE day=$day no=$no";
		$backupFile = createBackup($filename, $_SESSION['username'], $eventInfo);
		if ($backupFile) {
			$backupCreated = "Backup created: " . basename($backupFile);
		} else {
			$message = "Warning: Could not create backup file. Delete cancelled for safety.";
			$messageType = "error";
		}
		
		if ($backupFile) {
			$lines = file($filename, FILE_IGNORE_NEW_LINES);
			$newLines = array();
			$n = 0;
			$lastDay = "";
			$deleted = false;
			
			foreach ($lines as $index => $line) {
				if ($index == 0) {
					$newLines[] = $line;
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
					$deleted = true;
					continue;
				}
				
				$newLines[] = $line;
			}
			
			if ($deleted) {
				$result = file_put_contents($filename, implode("\n", $newLines));
				if ($result !== false) {
					header('Location: /calendar');
					exit;
				} else {
					$message = "Error: Unable to delete event. Check file permissions.";
					$messageType = "error";
				}
			}
		}
	}
}

// ============================================
// Handle form submission for NEW event
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save']) && $isNewEvent) {
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	} else if (isset($_POST['year'])) {
		$submittedYear = intval($_POST['year']);
		$currentYear = date('Y');
		if ($submittedYear < $currentYear || $submittedYear > 2050) {
			$message = "Year must be between " . $currentYear . " and 2050.";
			$messageType = "error";
		} else {
			$year = $submittedYear;
			$month = isset($_POST['month']) ? intval($_POST['month']) : $month;
		}
	}
	
	if (empty($message)) {
		// TIME IS REQUIRED
		if (empty($_POST['time']) || !validateTimeFormat($_POST['time'])) {
			$message = "Time is REQUIRED and must start with format \"H:MM AM\" or \"H:MM PM\" (e.g., \"7:00 PM\" or \"2:00 PM Trail starts\"). Blank time will cause EditHash 1.28 to delete the event.";
			$messageType = "error";
		} else {
			$filename = sprintf("../android/%d-%02d.txt", $year, $month);
			
			if (get_magic_quotes_gpc()) {
				$_POST = stripslashes_deep($_POST);
			}
			
			$_POST = sanitizeInput($_POST);
			
			if (empty($message)) {
				$day = intval($_POST['day']);
				
				if (file_exists($filename)) {
					$eventInfo = "NEW day=$day kennel=" . $_POST['kennel'];
					$backupFile = createBackup($filename, $_SESSION['username'], $eventInfo);
					if ($backupFile) {
						$backupCreated = "Backup created: " . basename($backupFile);
					}
				}
				
				$trailTypeLine = '';
				if (isset($_POST['trailtype']) && !empty($_POST['trailtype'])) {
					$trailTypeLine = '<br /><br />Trail Type: ' . $_POST['trailtype'];
				}
				
				$bringLine = '';
				if (isset($_POST['bring']) && is_array($_POST['bring']) && count($_POST['bring']) > 0) {
					$bringLine = '<br /><br />Bring: ' . implode(', ', $_POST['bring']);
				}
				
				$desc = sanitizeHtml($_POST['desc']);
				$desc = str_replace("\r\n", "<br />", $desc);
				$desc = str_replace("\n", "<br />", $desc);
				$desc = str_replace("\r", "<br />", $desc);
				$desc = $desc . $trailTypeLine . $bringLine;
				
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
				
				$address = sanitizeHtml($address);
				$address = str_replace("\r\n", "<br />", $address);
				$address = str_replace("\n", "<br />", $address);
				$address = str_replace("\r", "<br />", $address);
				
				$autoDate = generateDateString($day, $month, $year);
				$twilight = getTwilightEnd($day, $month, $year);
				$maplink = validateUrl($_POST['maplink']);
				$rsvpEnabled = isset($_POST['rsvp_enabled']) ? '1' : '';
				
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
					'',
					$twilight,
					$autoDate,
					$desc,
					date('n/j/y G:i') . ' (' . $_SESSION['username'] . ') ' . EDITPHP_VERSION,
					$rsvpEnabled
				);
				$newEventLine = implode("\t", $newEventData);
				
				$lines = array();
				$header = "DAY\tKENNEL\tICON\tTITLE\tRUN\tHARES\tTIME\tSTART\tMAP\tHASHCASH\tTURDs\tTWEET\tTWILIGHT\tDATE\tDESC\tUPDATE";
				
				if (file_exists($filename)) {
					$lines = file($filename, FILE_IGNORE_NEW_LINES);
				} else {
					$lines[] = $header;
				}
				
				$insertIndex = 1;
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
				
				$newEventLine = str_replace('no=%d', 'no=' . $eventNumber, $newEventLine);
				array_splice($lines, $insertIndex, 0, $newEventLine);
				
				$result = file_put_contents($filename, implode("\n", $lines));
				if ($result !== false) {
					$eventUrl = sprintf('%d/event.php?year=%d&month=%d&day=%d&no=%d', $year, $year, $month, $day, $eventNumber);
					header('Location: ' . $eventUrl);
					exit;
				} else {
					$message = "Error: Unable to write to file. Check file permissions.";
					$messageType = "error";
				}
			}
		}
	}
}

// ============================================
// Handle form submission for EDITING existing event
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save']) && !$isNewEvent) {
	if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
		$message = "Security error: Invalid request. Please try again.";
		$messageType = "error";
	// TIME IS REQUIRED
	} else if (empty($_POST['time']) || !validateTimeFormat($_POST['time'])) {
		$message = "Time is REQUIRED and must start with format \"H:MM AM\" or \"H:MM PM\" (e.g., \"7:00 PM\" or \"2:00 PM Trail starts\"). Blank time will cause EditHash 1.28 to delete the event.";
		$messageType = "error";
	} else {
		$filename = sprintf("../android/%d-%02d.txt", $year, $month);
		
		if (get_magic_quotes_gpc()) {
			$_POST = stripslashes_deep($_POST);
		}
		
		$_POST = sanitizeInput($_POST);
		
		if (empty($message)) {
			$eventInfo = "EDIT day=$day no=$no kennel=" . $_POST['kennel'];
			$backupFile = createBackup($filename, $_SESSION['username'], $eventInfo);
			if ($backupFile) {
				$backupCreated = "Backup created: " . basename($backupFile);
			} else {
				$message = "Warning: Could not create backup file.";
				$messageType = "error";
			}
			
			$lines = file($filename, FILE_IGNORE_NEW_LINES);
			$newLines = array();
			$n = 0;
			$lastDay = "";
			$targetLineIndex = -1;
			
			foreach ($lines as $index => $line) {
				if ($index == 0) {
					$newLines[] = $line;
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
					
					$trailTypeLine = '';
					if (isset($_POST['trailtype']) && !empty($_POST['trailtype'])) {
						$trailTypeLine = '<br /><br />Trail Type: ' . $_POST['trailtype'];
					}
					
					$bringLine = '';
					if (isset($_POST['bring']) && is_array($_POST['bring']) && count($_POST['bring']) > 0) {
						$bringLine = '<br /><br />Bring: ' . implode(', ', $_POST['bring']);
					}
					
					$desc = sanitizeHtml($_POST['desc']);
					$desc = str_replace("\r\n", "<br />", $desc);
					$desc = str_replace("\n", "<br />", $desc);
					$desc = str_replace("\r", "<br />", $desc);
					$desc = $desc . $trailTypeLine . $bringLine;
					
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
					
					$address = sanitizeHtml($address);
					$address = str_replace("\r\n", "<br />", $address);
					$address = str_replace("\n", "<br />", $address);
					$address = str_replace("\r", "<br />", $address);
					
					$autoDate = generateDateString($day, $month, $year);
					$maplink = validateUrl($_POST['maplink']);
					$rsvpEnabled = isset($_POST['rsvp_enabled']) ? '1' : '';
					
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
			
			if ($targetLineIndex >= 0) {
				$result = file_put_contents($filename, implode("\n", $newLines));
				if ($result !== false) {
					releaseEditLock($year, $month, $day, $no);
					$eventUrl = sprintf('%d/event.php?year=%d&month=%d&day=%d&no=%d', $year, $year, $month, $day, $no);
					header('Location: ' . $eventUrl);
					exit;
				} else {
					$message = "Error: Unable to write to file. Check file permissions.";
					$messageType = "error";
				}
			}
		}
	}
}