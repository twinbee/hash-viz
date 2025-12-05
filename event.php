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
	<link href="print.css" rel="stylesheet" type="text/css" media="print" />

  <?php
	// Include twilight calculator
	require_once('twilight.php');
	
	// Rollcall data directory
	define('ROLLCALL_DIR', '../../android/rollcall/');
	
	// Function to load attendance for an event
	function loadEventAttendance($year, $month, $day, $no, $kennel) {
		$kennelSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kennel);
		$file = ROLLCALL_DIR . sprintf("%d-%02d-%02d_%d_%s.txt", $year, $month, $day, $no, $kennelSafe);
		
		if (!file_exists($file)) {
			return array();
		}
		
		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$names = array();
		foreach ($lines as $line) {
			$parts = explode("\t", $line);
			if (count($parts) >= 1 && !empty($parts[0])) {
				$names[] = $parts[0];
			}
		}
		
		sort($names, SORT_STRING | SORT_FLAG_CASE);
		return $names;
	}
	
	$a = array(1=>0,2=>31,3=>59,4=>90,5=>120,6=>151,7=>181,8=>212,9=>243,10=>273,11=>304,12=>334);
	$begin = 66;
	$end = 304;
	
	$year = $_GET["year"];
	$month = $_GET["month"];
	$day = $_GET["day"];
	$no = $_GET["no"];	
	$n = 0;
	
	$lastDay = "";
	
	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
		$file = fopen ($filename, "r");
		if (!$file) {
				echo "<p>Unable to open file.\n</p>";
				exit;
		}
		
		while ($line = fgets ($file, 8192)) {
			$data = explode("\t", $line);
			//DAY = 0 KENNEL = 1 TYPE = 2 TITLE = 3 RUN = 4 HARES = 5 TIME = 6 ADDRESS = 7 
			//MAPLINK = 8 HASHCASH = 9 TURDS = 10 TWEET = 11 TWILIGHT = 12 DATE = 13 DESC = 14 UPDATED = 15

			$d = $data[0];			
			if ($d != $lastDay) {
				$n = 1;
			} else {
				$n += 1;
			}
			$lastDay = $d;
			
			if ( $d == $day && $n == $no) break;
		}
	  
		$data[6] = str_replace("CDT", "", str_replace("CST", "", $data[6])); // lops off CDT or CST
		
		// Calculate twilight dynamically for this date
		$twilightTime = getTwilightEnd($day, $month, $year);
		
		// Generate weather forecast location from address
		$weatherLocation = '';
		$address = isset($data[7]) ? $data[7] : '';
		
		// Try to find ZIP code (5 digits)
		if (preg_match('/(\d{5})(?:-\d{4})?/', $address, $matches)) {
			$weatherLocation = $matches[1];
		}
		// If no ZIP, try to find city, state pattern
		else if (preg_match('/([A-Za-z\s]+),?\s*(?:TX|Texas)/i', $address, $matches)) {
			$cityName = trim($matches[1]);
			$cityName = preg_replace('/[^A-Za-z\s]/', '', $cityName);
			$cityName = trim($cityName);
			if (!empty($cityName)) {
				$weatherLocation = $cityName . ', TX';
			}
		}
		
		// Default to Addison if no location found
		if (empty($weatherLocation)) {
			$weatherLocation = 'Addison, TX';
		}
		
		$weatherUrl = 'https://forecast.weather.gov/zipcity.php?inputstring=' . urlencode($weatherLocation);
		
		// Generate edit link
		$editLink = sprintf(
			'http://dfwhhh.org/calendar/%d/edit.php?month=%d&day=%d&year=%d&no=%d',
			$year, $month, $day, $year, $no
		);
		
		// Generate calendar link
		$calendarLink = sprintf(
			'http://dfwhhh.org/calendar/%d/generate_ics.php?month=%d&day=%d&year=%d&no=%d',
			$year, $month, $day, $year, $no
		);
	
		printf ("\t<title>%s for %s/%s/%s</title>\n", $data[1], $month, $day, $year);
		print("</head>\n");
		print("<body>\n");
		print("\t<div id=\"container\">\n");
		
		// Generate back to calendar link for specific month
		$calendarMonthLink = sprintf('$%d-%d.php', $month, $year);
		
		// Navigation link back to calendar
		printf("\t\t<p class=\"nav-links\"><a href=\"%s\">&laquo; Back to Calendar</a></p>\n", $calendarMonthLink);
		
		printf ("\t\t<h1>%s</h1>\n", $data[1]);
		printf ("\t\t<h2>%s</h2>\n", $data[13]);

		if (strlen($data[4]) > 0) printf ("\t\t<h3>Hash Run No %s</h3>\n", $data[4]);
		
		if (strlen($data[15]) > 2) {
			$updatedText = str_replace("<br />", "", $data[15]);
			printf ("\t\t<h4>End of twilight: %s<br />Updated: %s (<a href=\"%s\">edit</a>)</h4>\n", $twilightTime, $updatedText, $editLink);
		} else {
			printf ("\t\t<h4>End of twilight: %s</h4>\n", $twilightTime);
		}	
		
		printf ("\t\t<hr />\n\t\t<h6>%s</h6>\n\t\t<hr />\n", strlen($data[3]) > 0 ? $data[3] : "Nothing yet");
		printf ("\t\t<h5><em>Time:</em> %s</h5>\n", strlen($data[6]) > 0 ? $data[6] : "Nothing yet");
		if (strlen($data[7]) > 0) {
			printf ("\t\t<h5><em>Start address:</em> %s</h5>\n", $data[7]);
		} else {
			printf ("\t\t<h5><em>Start address:</em> Nothing yet</h5>\n");
		}
		if (strlen($data[8]) > 0) {
			printf ("\t\t<h5><em>Map:</em> <a href=\"%s\" target=\"_blank\">Get Map</a></h5>\n", $data[8]);
		} else {
			printf ("\t\t<h5><em>Map:</em> Nothing yet</h5>\n");
		}
		if (strlen($data[5]) > 0) {
			printf ("\t\t<h5><em>Hares:</em> %s</h5>\n", $data[5]);
		} else {
			printf ("\t\t<h5><em>Hares:</em> Nothing yet</h5>\n");
		}
		if (strlen($data[9]) > 0) {
			printf ("\t\t<h5><em>Hash cash:</em> %s</h5>\n", $data[9]);
		} else {
			printf ("\t\t<h5><em>Hash cash:</em> Nothing yet</h5>\n");
		}
		if (strlen($data[10]) > 0) {
			printf ("\t\t<h5><em>TURDs? (Dogs):</em> %s</h5>\n", $data[10]);
		} else {
			printf ("\t\t<h5><em>TURDs? (Dogs):</em> Nothing yet</h5>\n");
		}
		if (strlen($data[14]) > 0) {
			// Remove weather widget from description if present (it's displayed separately)
			$descriptionText = $data[14];
			$descriptionText = preg_replace('/<!-- WEATHER_START -->.*?<!-- WEATHER_END -->/s', '', $descriptionText);
			printf ("\t\t<h5><em>Description:</em> %s</h5>\n", $descriptionText);
		} else {
			printf ("\t\t<h5><em>Description:</em> Nothing yet</h5>\n");
		}
		
		// Load and display check-ins
		$kennel = isset($data[1]) ? trim($data[1]) : '';
		$checkedInNames = loadEventAttendance($year, $month, $day, $no, $kennel);
		if (count($checkedInNames) > 0) {
			printf("\t\t<h5><em>Check-ins (%d):</em> %s</h5>\n", 
				count($checkedInNames), 
				htmlspecialchars(implode(', ', $checkedInNames))
			);
		}
		
		// Horizontal rule before action links
		printf("\t\t<hr />\n");
		
		// Generate back to calendar link for specific month
		$calendarMonthLink = sprintf('$%d-%d.php', $month, $year);
		
		// Generate rollcall link
		$rollcallLink = sprintf(
			'rollcall.php?month=%d&day=%d&year=%d&no=%d',
			$month, $day, $year, $no
		);
		
		// Generate checkin link
		$checkinLink = sprintf(
			'checkin.php?month=%d&day=%d&year=%d&no=%d',
			$month, $day, $year, $no
		);
		
		// Add action links
		printf("\t\t<p style=\"margin-top: 15px;\">\n");
		printf("\t\t\t<a href=\"%s\">edit</a> | \n", $editLink);
		printf("\t\t\t<a href=\"%s\">check in</a> | \n", $checkinLink);
		printf("\t\t\t<a href=\"%s\">roll call</a> | \n", $rollcallLink);
		printf("\t\t\t<a href=\"%s\">download calendar invite</a> | \n", $calendarLink);
		printf("\t\t\t<a href=\"%s\">back to calendar</a>\n", $calendarMonthLink);
		printf("\t\t</p>\n");
		
		// Horizontal rule before weather forecast section
		printf("\t\t<hr />\n");
		
		// Add weather forecast section
		printf("\t\t<br />\n");
		printf("\t\t<h5><strong>Weather Forecast for %s:</strong></h5>\n", htmlspecialchars($weatherLocation));
		printf("\t\t<iframe src=\"%s\" width=\"100%%\" height=\"600\" frameborder=\"0\" scrolling=\"yes\" style=\"border: 1px solid #ccc;\"></iframe>\n", $weatherUrl);

		?>
    
		
	</div>
</body>
</html>