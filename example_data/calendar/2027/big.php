<?php

	for ($mon = 1; $mon < 13; $mon++) {
		$filename = sprintf("../../android/%d-%02d.txt", $year, $mon);
		$file = fopen ($filename, "r");
		if (!$file) {
				printf ( "<p>Unable to open file. %s\n",$filename);
				break;
		}
		
		// Fill in the $database with the whole year's data
		$last = 0;
		while ($line = fgets ($file, 4096)) {
			$exploded = explode("\t", $line);
			//DAY = 0 KENNEL = 1 TYPE = 2 TITLE = 3 RUN = 4 HARES = 5 TIME = 6 ADDRESS = 7 
			//MAPLINK = 8 HASHCASH = 9 TURDS = 10 TWEET = 11 TWILIGHT = 12 DATE = 13 DESC = 14 UPDATED = 15
	
			// set $date to match the day this line of data corresponds to
			// load the data into an array
			$day = (int)$exploded[0];
			if ($day == $last) {
				$i += 1;
			} else {
				$i = 0;
			}
			$last = $day;
			$database[$mon][$day][$i] = $exploded;
		}

	}
	
	// This function fills in each day in the calendar that has an event with html that displays an icon and 
	// some text such as the event title and the hare
	
function fillIn($month, $day, $year) {
	global $database;
		
  // do nothing if no event for this day (output newline to make html neat)
	
	if ($database[$month][$day][0] == NULL) {
		printf("\n");
	} else {
	
		
		if ($database[$month][$day][1] != NULL) {
			// multi-event day. Count the events.
			$events = 1;
			while ($database[$month][$day][$events] != NULL) {
			  $events += 1;
		}
			
		// display the double header icon along with the number of events for this day
		printf ('<a href="multi.php?month=%s&day=%s&year=%s" ><img src="double.png" /></a><br />%d Events Today', $month, $day, $year, $events);

		} else {
			
			// Single event day: get the icon file name
			$icon = $database[$month][$day][0][2];
				
			// if an FU event, link goes to their site, otherwise synthesize a link for this event
			if (strpos($database[$month][$day][0][8], "fuh3.com") === false) {					
				$link = sprintf('event.php?month=%s&day=%s&year=%s&no=1', $month, $day, $year);
			} else {
				$link = $database[$month][$day][0][8];
			}
				
			$title = $database[$month][$day][0][3];
			if (strlen($title) != 0) {
				$title = sprintf('<br />%s', $title);
			}			
					
			$hares = $database[$month][$day][0][5];
			if (strlen($hares) != 0) {
				$hares = sprintf('<br /><em>%s</em>', $hares);
			}				
				
			printf ("<a href=\"%s\"><img src=\"%s\" width=\"130px\" /></a>%s%s", $link, $icon, $title, $hares);
		}
	} 
}
?>
