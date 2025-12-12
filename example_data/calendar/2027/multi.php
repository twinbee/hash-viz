<!DOCTYPE html>
<html>
<head>
<meta name="HandheldFriendly" content="true" />
	<meta name="MobileOptimized" content="320" />
	<meta name="Viewport" content="width=device-width" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta http-equiv="pragma" content="no-cache" />
 <link href="multi.css" rel="stylesheet" media="screen" type="text/css">
<?php 

	$month = $_GET["month"];
	$day = $_GET["day"];
	$year = $_GET["year"];
	 
	date_default_timezone_set('America/Chicago');
	$date = new DateTime();
	$date->setDate($year, $month, $day);
	$da = getdate($date->format('U'));
	$fullDate = sprintf("%s, %s %s, %s", $da['weekday'], $da['month'], $day, $year);
	  	

	$filename = sprintf("../../android/%d-%02d.txt", $year, $month);
	$file = fopen ($filename, "r");
	if (!$file) {
		printf ( "<p>Unable to open file. %s\n",$filename);
	}

	printf("<title>Hash events for %d/%d/%d</title>\n", $month, $day, $year);
	printf("</head>\n<body>\n<div id=\"head\">\n\t%s\n</div>\n", $fullDate);
	printf("<div class=\"container\">\n\n");
	
	// loop once for each event
	
	$no = 0;	
	while ($line = fgets ($file, 4096)) {
		
		$data = explode("\t", $line);
		//DAY = 0 KENNEL = 1 TYPE = 2 TITLE = 3 RUN = 4 HARES = 5 TIME = 6 ADDRESS = 7 
		//MAPLINK = 8 HASHCASH = 9 TURDS = 10 TWEET = 11 TWILIGHT = 12 DATE = 13 DESC = 14 

		// only process events for this day
		if ($data[0] == $day) {
			
			$no += 1;
			
				switch ($data[2]) {
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
						$icon = $data[2];
						break;
				}
				
			//FU Hashes follow the link to their website
			if (strpos($data[8], "fuh3.com") === false) {					
				// Not an FU event: synthesize a link for this website			 
				$link = sprintf("event.php?month=%s&day=%s&year=%s&no=%0d", $month, $day, $year, $no);
			} else {	
				// FU event: the map link goes directly to their site			 
				$link = $data[8];
		}
							
		printf("\t<div class=\"event\">\n");
		printf("\t\t<div class=\"time\">\n\t\t\t%s\n\t\t</div>\n", $data[6]);
		printf("\t\t<a href=\"%s\" target=\"_blank\"><img src=\"%s\" width=\"130px\" /></a>%s<br /><em>%s</em> <br />", 
				$link, $icon, $data[3], $data[5]);
		printf("\t</div>\n\n");

		}
		
	}
	fclose($file);
?>

<div>
<body>
<html>




