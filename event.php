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
	// Timezone configuration - DFW is Central Time
	date_default_timezone_set('America/Chicago');
	
	// Include twilight calculator
	require_once('twilight.php');
	
	// Rollcall data directory
	define('ROLLCALL_DIR', '../../android/rollcall/');
	
	// Function to load attendance for an event (returns array with name and comment)
	function loadEventAttendance($year, $month, $day, $no, $kennel) {
		$kennelSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kennel);
		$file = ROLLCALL_DIR . sprintf("%d-%02d-%02d_%d_%s.txt", $year, $month, $day, $no, $kennelSafe);
		
		if (!file_exists($file)) {
			return array();
		}
		
		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$attendees = array();
		foreach ($lines as $line) {
			$parts = explode("\t", $line);
			if (count($parts) >= 1 && !empty($parts[0])) {
				$attendees[] = array(
					'name' => $parts[0],
					'comment' => isset($parts[3]) ? $parts[3] : ''
				);
			}
		}
		
		// Sort by name (case-insensitive)
		usort($attendees, create_function('$a, $b', 'return strcasecmp($a["name"], $b["name"]);'));
		return $attendees;
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
	
	// Helper function to load events from a month file
	function loadMonthEvents($year, $month) {
		$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
		if (!file_exists($filename)) {
			return array();
		}
		
		$file = fopen($filename, "r");
		if (!$file) {
			return array();
		}
		
		$events = array();
		$lastDay = "";
		$n = 0;
		
		while ($line = fgets($file, 8192)) {
			$lineData = explode("\t", $line);
			$d = isset($lineData[0]) ? trim($lineData[0]) : '';
			
			// Skip header rows or non-numeric day values
			if (!is_numeric($d)) {
				continue;
			}
			
			if ($d != $lastDay) {
				$n = 1;
			} else {
				$n += 1;
			}
			$lastDay = $d;
			
			$events[] = array(
				'year' => $year,
				'month' => $month,
				'day' => $d, 
				'no' => $n, 
				'data' => $lineData
			);
		}
		fclose($file);
		
		return $events;
	}
	
	// Load current month events
	$allEvents = loadMonthEvents($year, $month);
	
	// Find current event
	$currentEventIndex = -1;
	$data = null;
	foreach ($allEvents as $idx => $evt) {
		if ($evt['day'] == $day && $evt['no'] == $no) {
			$currentEventIndex = $idx;
			$data = $evt['data'];
			break;
		}
	}
	
	if ($data === null) {
		echo "<p>Event not found.\n</p>";
		exit;
	}
	
	// Find prev/next events (including cross-month navigation)
	$prevEvent = null;
	$nextEvent = null;
	
	if ($currentEventIndex > 0) {
		// Previous event in same month
		$prevEvent = $allEvents[$currentEventIndex - 1];
	} else {
		// Try to get last event from previous month
		$prevMonth = $month - 1;
		$prevYear = $year;
		if ($prevMonth < 1) {
			$prevMonth = 12;
			$prevYear = $year - 1;
		}
		$prevMonthEvents = loadMonthEvents($prevYear, $prevMonth);
		if (count($prevMonthEvents) > 0) {
			$prevEvent = $prevMonthEvents[count($prevMonthEvents) - 1];
		}
	}
	
	if ($currentEventIndex >= 0 && $currentEventIndex < count($allEvents) - 1) {
		// Next event in same month
		$nextEvent = $allEvents[$currentEventIndex + 1];
	} else {
		// Try to get first event from next month
		$nextMonth = $month + 1;
		$nextYear = $year;
		if ($nextMonth > 12) {
			$nextMonth = 1;
			$nextYear = $year + 1;
		}
		$nextMonthEvents = loadMonthEvents($nextYear, $nextMonth);
		if (count($nextMonthEvents) > 0) {
			$nextEvent = $nextMonthEvents[0];
		}
	}
	
	// Find prev/next events for SAME KENNEL (using column 1 - kennel name)
	$currentKennel = isset($data[1]) ? trim($data[1]) : '';
	$prevKennelEvent = null;
	$nextKennelEvent = null;
	
	// Search backwards for previous kennel event
	// First check current month before this event
	for ($i = $currentEventIndex - 1; $i >= 0; $i--) {
		if (isset($allEvents[$i]['data'][1]) && trim($allEvents[$i]['data'][1]) === $currentKennel) {
			$prevKennelEvent = $allEvents[$i];
			break;
		}
	}
	
	// If not found, search previous months (up to 6 months back)
	if ($prevKennelEvent === null) {
		for ($m = 1; $m <= 6; $m++) {
			$searchMonth = $month - $m;
			$searchYear = $year;
			while ($searchMonth < 1) {
				$searchMonth += 12;
				$searchYear--;
			}
			$searchEvents = loadMonthEvents($searchYear, $searchMonth);
			// Search from end of month backwards
			for ($i = count($searchEvents) - 1; $i >= 0; $i--) {
				if (isset($searchEvents[$i]['data'][1]) && trim($searchEvents[$i]['data'][1]) === $currentKennel) {
					$prevKennelEvent = $searchEvents[$i];
					break 2; // Break both loops
				}
			}
		}
	}
	
	// Search forwards for next kennel event
	// First check current month after this event
	for ($i = $currentEventIndex + 1; $i < count($allEvents); $i++) {
		if (isset($allEvents[$i]['data'][1]) && trim($allEvents[$i]['data'][1]) === $currentKennel) {
			$nextKennelEvent = $allEvents[$i];
			break;
		}
	}
	
	// If not found, search next months (up to 6 months ahead)
	if ($nextKennelEvent === null) {
		for ($m = 1; $m <= 6; $m++) {
			$searchMonth = $month + $m;
			$searchYear = $year;
			while ($searchMonth > 12) {
				$searchMonth -= 12;
				$searchYear++;
			}
			$searchEvents = loadMonthEvents($searchYear, $searchMonth);
			// Search from start of month forwards
			for ($i = 0; $i < count($searchEvents); $i++) {
				if (isset($searchEvents[$i]['data'][1]) && trim($searchEvents[$i]['data'][1]) === $currentKennel) {
					$nextKennelEvent = $searchEvents[$i];
					break 2; // Break both loops
				}
			}
		}
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
		$calendarMonthLink = sprintf('$%02d-%d.php', $month, $year);
		
		// Navigation link back to calendar
		printf("\t\t<p class=\"nav-links\"><a href=\"%s\">&laquo; Back to Calendar</a></p>\n", $calendarMonthLink);
		
		// Get kennel icon from event data (column 2)
		$kennelName = $data[1];
		$iconFile = isset($data[2]) && strlen(trim($data[2])) > 0 ? trim($data[2]) : '';
		
		// Display kennel name with icon
		if (!empty($iconFile)) {
			printf("\t\t<div style=\"display: flex; align-items: center; gap: 15px; margin-bottom: 10px;\">\n");
			printf("\t\t\t<img src=\"%s\" alt=\"%s\" style=\"width: 200px; height: auto;\">\n", htmlspecialchars($iconFile), htmlspecialchars($kennelName));
			printf("\t\t\t<h1 style=\"margin: 0;\">%s</h1>\n", $data[1]);
			printf("\t\t</div>\n");
		} else {
			printf ("\t\t<h1>%s</h1>\n", $data[1]);
		}
		printf ("\t\t<h2>%s</h2>\n", $data[13]);

		if (strlen($data[4]) > 0) printf ("\t\t<h3>Hash Run No %s</h3>\n", $data[4]);
		
		if (strlen($data[15]) > 2) {
			$updatedText = str_replace("<br />", "", $data[15]);
			printf ("\t\t<h4>End of twilight: %s<br />Updated: %s (<a href=\"%s\">edit</a>)</h4>\n", $twilightTime, $updatedText, $editLink);
		} else {
			printf ("\t\t<h4>End of twilight: %s</h4>\n", $twilightTime);
		}	
		
		// Only display title if it exists
		if (strlen($data[3]) > 0) {
			printf ("\t\t<hr />\n\t\t<h6>%s</h6>\n\t\t<hr />\n", $data[3]);
		} else {
			printf ("\t\t<hr />\n");
		}
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
		$checkedInAttendees = loadEventAttendance($year, $month, $day, $no, $kennel);
		if (count($checkedInAttendees) > 0) {
			$displayNames = array();
			foreach ($checkedInAttendees as $attendee) {
				$display = htmlspecialchars($attendee['name']);
				if (!empty($attendee['comment'])) {
					$display .= ' <em style="color:#666;">(' . htmlspecialchars($attendee['comment']) . ')</em>';
				}
				$displayNames[] = $display;
			}
			printf("\t\t<h5><em>Check-ins (%d):</em> %s</h5>\n", 
				count($checkedInAttendees), 
				implode(', ', $displayNames)
			);
		}
		
		// Check if early check-in (RSVP) is enabled (field 16)
		$earlyCheckinEnabled = isset($data[16]) && trim($data[16]) == '1';
		
		// Show early check-in notice if enabled
		if ($earlyCheckinEnabled) {
			printf("\t\t<h5><em>📋 Early check-in is open - use check-in to RSVP!</em></h5>\n");
		}
		
		// Horizontal rule before action links
		printf("\t\t<hr />\n");
		
		// Generate prev/next event links
		$prevEventLink = null;
		$nextEventLink = null;
		$prevEventLabel = null;
		$nextEventLabel = null;
		
		if ($prevEvent) {
			$prevEventLink = sprintf(
				'event.php?year=%d&month=%d&day=%d&no=%d',
				$prevEvent['year'], $prevEvent['month'], $prevEvent['day'], $prevEvent['no']
			);
			$prevKennel = isset($prevEvent['data'][1]) ? trim($prevEvent['data'][1]) : '';
			$prevEventLabel = sprintf('%d/%d %s', $prevEvent['month'], $prevEvent['day'], $prevKennel);
		}
		
		if ($nextEvent) {
			$nextEventLink = sprintf(
				'event.php?year=%d&month=%d&day=%d&no=%d',
				$nextEvent['year'], $nextEvent['month'], $nextEvent['day'], $nextEvent['no']
			);
			$nextKennel = isset($nextEvent['data'][1]) ? trim($nextEvent['data'][1]) : '';
			$nextEventLabel = sprintf('%d/%d %s', $nextEvent['month'], $nextEvent['day'], $nextKennel);
		}
		
		// Add prev/next navigation
		printf("\t\t<p style=\"margin-top: 15px; display: flex; justify-content: space-between; align-items: center;\">\n");
		if ($prevEventLink) {
			printf("\t\t\t<a href=\"%s\" style=\"text-decoration: none;\">◀ %s</a>\n", $prevEventLink, htmlspecialchars($prevEventLabel));
		} else {
			printf("\t\t\t<span style=\"color: #ccc;\">◀ Previous</span>\n");
		}
		if ($nextEventLink) {
			printf("\t\t\t<a href=\"%s\" style=\"text-decoration: none;\">%s ▶</a>\n", $nextEventLink, htmlspecialchars($nextEventLabel));
		} else {
			printf("\t\t\t<span style=\"color: #ccc;\">Next ▶</span>\n");
		}
		printf("\t\t</p>\n");
		
		// Add kennel-specific prev/next navigation
		$prevKennelLink = null;
		$nextKennelLink = null;
		$prevKennelLabel = null;
		$nextKennelLabel = null;
		
		if ($prevKennelEvent) {
			$prevKennelLink = sprintf(
				'event.php?year=%d&month=%d&day=%d&no=%d',
				$prevKennelEvent['year'], $prevKennelEvent['month'], $prevKennelEvent['day'], $prevKennelEvent['no']
			);
			$prevKennelLabel = sprintf('%d/%d', $prevKennelEvent['month'], $prevKennelEvent['day']);
		}
		
		if ($nextKennelEvent) {
			$nextKennelLink = sprintf(
				'event.php?year=%d&month=%d&day=%d&no=%d',
				$nextKennelEvent['year'], $nextKennelEvent['month'], $nextKennelEvent['day'], $nextKennelEvent['no']
			);
			$nextKennelLabel = sprintf('%d/%d', $nextKennelEvent['month'], $nextKennelEvent['day']);
		}
		
		// Only show kennel nav if there's at least one link
		if ($prevKennelLink || $nextKennelLink) {
			printf("\t\t<p style=\"margin-top: 10px; display: flex; justify-content: space-between; align-items: center; background: #f0f0f0; padding: 8px 12px; border-radius: 5px; font-size: 14px;\">\n");
			if ($prevKennelLink) {
				printf("\t\t\t<a href=\"%s\" style=\"text-decoration: none; color: #666;\">◀◀ %s <em>%s</em></a>\n", $prevKennelLink, htmlspecialchars($prevKennelLabel), htmlspecialchars($currentKennel));
			} else {
				printf("\t\t\t<span style=\"color: #ccc;\">◀◀ Previous %s</span>\n", htmlspecialchars($currentKennel));
			}
			if ($nextKennelLink) {
				printf("\t\t\t<a href=\"%s\" style=\"text-decoration: none; color: #666;\"><em>%s</em> %s ▶▶</a>\n", $nextKennelLink, htmlspecialchars($currentKennel), htmlspecialchars($nextKennelLabel));
			} else {
				printf("\t\t\t<span style=\"color: #ccc;\">Next %s ▶▶</span>\n", htmlspecialchars($currentKennel));
			}
			printf("\t\t</p>\n");
		}
		
		// Generate back to calendar link for specific month
		$calendarMonthLink = sprintf('$%02d-%d.php', $month, $year);
		
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
		printf("\t\t\t<a href=\"%s\">download invite</a> | \n", $calendarLink);
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