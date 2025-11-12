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
	
		printf ("\t<title>%s for %s/%s/%s</title>\n", $data[1], $month, $day, $year);
		print("</head>\n");
		print("<body>\n");
		print("\t<div id=\"container\">\n");
		printf ("\t\t<h1>%s</h1>\n", $data[1]);
		printf ("\t\t<h2>%s</h2>\n", $data[13]);

		if (strlen($data[4]) > 0) printf ("\t\t<h3>Hash Run № %s</h3>\n", $data[4]);
		
		if (strlen($data[15]) > 2) {
			printf ("\t\t<h4>End of twilight: %s<br />Updated: %s</h4>\n", $data[12], str_replace("<br />", "", $data[15]));
		} else {
			printf ("\t\t<h4>End of twilight: %s</h4>\n", $data[12]);
		}	
		
		printf ("\t\t<hr />\n\t\t<h6>%s</h6>\n\t\t<hr />\n", $data[3]);
		printf ("\t\t<h5><em>Time:</em> %s</h5>\n", $data[6]);
		if (strlen($data[7]) > 0) printf ("\t\t<h5><em>Start address:</em> %s</h5>\n", $data[7]);
		if (strlen($data[8]) > 0) printf ("\t\t<h5><em>Map:</em> <a href=\"%s\" target=\"_blank\">Get Map</a></h5>\n", $data[8]);
		if (strlen($data[5]) > 0) printf ("\t\t<h5><em>Hares:</em> %s</h5>\n", $data[5]);
		if (strlen($data[9]) > 0) printf ("\t\t<h5><em>Hash cash:</em> %s</h5>\n", $data[9]);
		if (strlen($data[10]) > 0) printf ("\t\t<h5><em>TURDs:<div class=\"hide\">TURDs are Tethered Urban Running Dogs. “Not Friendly” means that the ON-IN is at a bar and dogs are not allowed. “Friendly” means that dogs are welcome at the ON-IN.</div></em> %s</h5>\n", $data[10]);
		if (strlen($data[14]) > 0) printf ("\t\t<h5><em>Description:</em> %s</h5>\n", $data[14]);

		?>
    
		
	</div>
</body>
</html>
