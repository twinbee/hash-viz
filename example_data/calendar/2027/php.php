<?php
	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
	$file = fopen ($filename, "r");
	if (!$file) {
			printf ( "<p>Unable to open file. %s\n",$filename);
	}
	
	
	$last = 0;
	while ($line = fgets ($file, 8192)) {
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
		$database[$day][$i] = $exploded;
	}
	
	
	// This function fills in each day in the calendar that has an event with html that displays an icon and 
	// some text such as the event title and the hare
	
	function fillIn($month, $day, $year) {
		global $database;
		
  	// do nothing if no event for this day (output newline to make html neat)
	
		if ($database[$day][0] == NULL) {
			printf("\n");
		} else {
	
			// check if at least 2 events today
			if ($database[$day][1] != NULL) {
				// multi-event day. Count the events.
				$events = 1;
				while ($database[$day][$events] != NULL) {
			 	 $events += 1;
				}
			
				// display the double header icon along with the number of events for this day
				printf ('<a href="multi.php?month=%s&day=%s&year=%s" ><img src="double.png" /></a><br />%d Events Today', $month, $day, $year, $events);
	
			} else {
				
				// single event day
				switch ($database[$day][0][2]) {
					case "du":
						$icon = "DUH.png";
						break;			
					case "dh":
						$icon = "dallas.png";
						break;
					case "fw":
						$icon = "ftworth.png";
						break;
					case "hh":
						$icon = sprintf("happy%d.png", (int)($day / 7) + 1);
						break;
					case "nd":
						$icon = "NoDHHH.png";
						break;
					case "sb":
						$icon = "Brunch.png";
						break;
					case "th":
						$icon = "tacoNiteHash.png";
						break;
					case "tn":
						$icon = "tacoNite.png";
						break;
					case "fu":
						$icon = "fuh3.png";
						break;
					case "fh":
						$icon = "fuBird.png";
						break;
					case "gq":
						$icon = "GQhash.png";
						break;
					case "ch":
						$icon = "contra.png";
						break;
					case "bh":
						$icon = "bikeHash.png";
						break;
					case "sh":
						$icon = "spit.png";
						break;			
					case "fm":
						$icon = "fullmoon.png";
						break;			
					case "xx":
						$icon = "special.png";
						break;
					case "tt":
						$icon = "tastyTuesdays.png";
						break;
					case "hx":
						$icon = "wtf.png";
						break;
					case "fp":
						$icon = "FWMPcrawl.png";
						break;
					case "pc":
						$icon = "crawl.png";
						break;
					case "hb":
						$icon = "happyBirthday.png";
						break;
					case "tr":
						$icon = "triviaTuesdays.png";
						break;
					case "pn":
						$icon = "poker.png";
						break;
					
						//None of the above cades match: It's not a code, it's an icon file name
									
					default: 
						$icon = $database[$day][0][2];
						break;
				}
				
				// if an FU event, link goes to their site, otherwise synthesize a link for this event
				if (strpos($database[$day][0][8], "fuh3.com") === false) {					
					$link = sprintf('event.php?month=%s&day=%s&year=%s&no=1', $month, $day, $year);
				} else {
					$link = $database[$day][0][8];
				}
				
				$title = $database[$day][0][3];
				if (strlen($title) != 0) {
					$title = sprintf('<br />%s', $title);
				}			
					
				$hares = $database[$day][0][5];
				if (strlen($hares) != 0) {
					$hares = sprintf('<br /><em>%s</em>', $hares);
				}				
				
				printf ("<a href=\"%s\"><img src=\"%s\" width=\"130px\" /></a>%s%s\n", $link, $icon, $title, $hares);
			}
		} 
	}
?>
