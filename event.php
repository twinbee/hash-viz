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
		
		// Get map link for iframe
		$mapLink = isset($data[8]) ? trim($data[8]) : '';
	
		printf ("\t<title>%s for %s/%s/%s</title>\n", $data[1], $month, $day, $year);
		print("</head>\n");
		print("<body>\n");
		print("\t<div id=\"container\">\n");
		
		// Navigation link back to calendar
		printf("\t\t<p class=\"nav-links\"><a href=\"/calendar\">&laquo; Back to Calendar</a></p>\n");
		
		printf ("\t\t<h1>%s</h1>\n", $data[1]);
		printf ("\t\t<h2>%s</h2>\n", $data[13]);

		if (strlen($data[4]) > 0) printf ("\t\t<h3>Hash Run No %s</h3>\n", $data[4]);
		
		if (strlen($data[15]) > 2) {
			printf ("\t\t<h4>End of twilight: %s<br />Updated: %s</h4>\n", $data[12], str_replace("<br />", "", $data[15]));
		} else {
			printf ("\t\t<h4>End of twilight: %s</h4>\n", $data[12]);
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
		
		// Horizontal rule before weather forecast section
		printf("\t\t<hr />\n");
		
		// Add weather forecast section
		printf("\t\t<br />\n");
		printf("\t\t<h5><strong>Weather Forecast for %s:</strong></h5>\n", htmlspecialchars($weatherLocation));
		printf("\t\t<iframe src=\"%s\" width=\"100%%\" height=\"600\" frameborder=\"0\" scrolling=\"yes\" style=\"border: 1px solid #ccc;\"></iframe>\n", $weatherUrl);
		
		// Add map iframe if map link is provided
		if (!empty($mapLink)) {
			printf("\t\t<br /><br />\n");
			printf("\t\t<h5><strong>Map:</strong></h5>\n");
			printf("\t\t<iframe src=\"%s\" width=\"100%%\" height=\"450\" frameborder=\"0\" scrolling=\"yes\" style=\"border: 1px solid #ccc;\"></iframe>\n", htmlspecialchars($mapLink));
		}
		
		// Add action links
		printf("\t\t<p style=\"margin-top: 15px;\">\n");
		printf("\t\t\t<a href=\"%s\">edit</a> | \n", $editLink);
		printf("\t\t\t<a href=\"%s\">add to calendar</a> | \n", $calendarLink);
		printf("\t\t\t<a href=\"/calendar\">back to calendar</a>\n");
		printf("\t\t</p>\n");

		?>
    
		
	</div>
</body>
</html>