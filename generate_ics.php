<?php
/**
 * ICS Calendar File Generator
 * Generates .ics files for hash events
 */

$year = $_GET["year"];
$month = $_GET["month"];
$day = $_GET["day"];
$no = $_GET["no"];

// Load event data
$filename = sprintf("../android/%d-%02d.txt", $year, $month);
$file = fopen($filename, "r");
if (!$file) {
	die("Unable to open file.");
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

// Strip slashes if magic_quotes_gpc is enabled
if (get_magic_quotes_gpc()) {
	$data = array_map('stripslashes', $data);
}

//DAY = 0 KENNEL = 1 TYPE = 2 TITLE = 3 RUN = 4 HARES = 5 TIME = 6 ADDRESS = 7 
//MAPLINK = 8 HASHCASH = 9 TURDS = 10 TWEET = 11 TWILIGHT = 12 DATE = 13 DESC = 14 UPDATED = 15

// Parse event data
$kennel = isset($data[1]) ? $data[1] : 'Hash Event';
$title = isset($data[3]) ? $data[3] : '';
$eventTitle = $kennel . ($title ? ' - ' . $title : '');
$time = isset($data[6]) ? $data[6] : '7:00 PM';
$address = isset($data[7]) ? $data[7] : '';
$maplink = isset($data[8]) ? $data[8] : '';
$description = isset($data[14]) ? $data[14] : '';

// Clean up address and description (remove <br /> tags)
$address = str_replace("<br />", "\n", $address);
$address = str_replace("<br/>", "\n", $address);
$address = str_replace("<br>", "\n", $address);
$address = strip_tags($address);

$description = str_replace("<br />", "\n", $description);
$description = str_replace("<br/>", "\n", $description);
$description = str_replace("<br>", "\n", $description);
// Remove weather forecast block from description
$description = preg_replace('/<!-- WEATHER_START -->.*?<!-- WEATHER_END -->/s', '', $description);
$description = strip_tags($description);

// Parse time (try to extract hour)
$eventHour = 19; // default 7 PM
$eventMinute = 0;
if (preg_match('/(\d{1,2}):?(\d{2})?\s*(AM|PM)/i', $time, $matches)) {
	$eventHour = (int)$matches[1];
	$eventMinute = isset($matches[2]) ? (int)$matches[2] : 0;
	$ampm = strtoupper($matches[3]);
	
	if ($ampm == 'PM' && $eventHour != 12) {
		$eventHour += 12;
	} elseif ($ampm == 'AM' && $eventHour == 12) {
		$eventHour = 0;
	}
}

// Create date/time for ICS format (YYYYMMDDTHHMMSS)
$eventDate = sprintf("%04d%02d%02dT%02d%02d00", $year, $month, $day, $eventHour, $eventMinute);
$eventDateEnd = sprintf("%04d%02d%02dT%02d%02d00", $year, $month, $day, $eventHour + 3, $eventMinute); // 3 hour duration

// Generate unique ID
$uid = sprintf("%d%02d%02d-%d@dfwhhh.org", $year, $month, $day, $no);

// Current timestamp for DTSTAMP
$now = gmdate('Ymd\THis\Z');

// Build ICS content
$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//DFW Hash House Harriers//NONSGML Event//EN\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";
$ics .= "BEGIN:VEVENT\r\n";
$ics .= "UID:" . $uid . "\r\n";
$ics .= "DTSTAMP:" . $now . "\r\n";
$ics .= "DTSTART:" . $eventDate . "\r\n";
$ics .= "DTEND:" . $eventDateEnd . "\r\n";
$ics .= "SUMMARY:" . escapeICS($eventTitle) . "\r\n";
$ics .= "DESCRIPTION:" . escapeICS($description) . "\r\n";
$ics .= "LOCATION:" . escapeICS($address) . "\r\n";
if ($maplink) {
	$ics .= "URL:" . $maplink . "\r\n";
}
$ics .= "STATUS:CONFIRMED\r\n";
$ics .= "END:VEVENT\r\n";
$ics .= "END:VCALENDAR\r\n";

// Function to escape ICS special characters
function escapeICS($text) {
	$text = str_replace("\\", "\\\\", $text);
	$text = str_replace(",", "\\,", $text);
	$text = str_replace(";", "\\;", $text);
	$text = str_replace("\n", "\\n", $text);
	$text = str_replace("\r", "", $text);
	return $text;
}

// Send headers to download the file
$filename = sprintf("%s_%d-%02d-%02d.ics", str_replace(' ', '_', $kennel), $year, $month, $day);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($ics));

echo $ics;
?>