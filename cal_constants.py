# cal_constants.py

from datetime import datetime

# --- Geographical Constants for Dallas, TX ---
DALLAS_LATITUDE = 32.7767
DALLAS_LONGITUDE = -96.7970 # West is negative
# --- End Geographical Constants ---

# --- Full Moon Calculation Constants ---
REF_FM_DATE = datetime(2024, 1, 25, 12)
SYNODIC_MONTH = 29.530588 

# Dictionary to map month number to the specific moon name used in banner filenames
MOON_NAMES = {
    1: "Wolf", 2: "Snow", 3: "Worm", 4: "Pink", 5: "Flower", 6: "Strawberry",
    7: "Buck", 8: "Sturgeon", 9: "Harvest", 10: "Hunter", 11: "Beaver", 12: "Cold"
}

# Specification block for initial date and run numbers for each kennel
KENNEL_SPECS = {
    "Dallas Hash": {"initial_date": datetime(2024, 1, 6), "run_number": 1214},
    "Ft Worth Hash": {"initial_date": datetime(2024, 1, 13), "run_number": 1048},
    "Dallas Urban Hash": {"initial_date": datetime(2024, 1, 3), "run_number": 834},
    "NO-NO-DUH": {"initial_date": datetime(2024, 1, 15), "run_number": 5}, 
    "YAKH3": {"initial_date": datetime(2024, 6, 2), "run_number": 1}, 
    "Full Moon Hash": {"initial_date": datetime(2024, 1, 25), "run_number": 87},
    "7-ELEVEn hash house harriers": {"initial_date": datetime(2024, 7, 11), "run_number": 1} # Initial run for continuous count
}

# Hashcash and schedule rules for each kennel
KENNEL_RULES = {
    "Dallas Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$10.00 - Pay Online: Paypal $10", "day": "Saturday"},
    "Ft Worth Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$7.00 cash - Paypal $7 - Pay pal (FWH3) or Zelle 817-689-9363 - BYOB pre-lube beer", "day": "Saturday"},
    "Dallas Urban Hash": {"frequency": "weekly", "time": "6:30 PM", "hashcash": "", "day": "Wednesday"},
    "NO-NO-DUH": {"frequency": "monthly", "time": "7:00 PM", "hashcash": "$7.00", "day": "Monday"}, 
    "YAKH3": {"frequency": "summer-sundays", "time": "12:00 PM", "hashcash": "", "day": "Sunday"}, 
    "Full Moon Hash": {"frequency": "full-moon", "time": "varies", "hashcash": "", "day": "full-moon"},
    "7-ELEVEn hash house harriers": {"frequency": "fixed-dates", "time": "7:00 PM", "hashcash": "$7.11", "day": "irrelevant"} # Fixed run dates 7/11 and 11/7
}

# Helper dictionary to map rule["day"] name to Python's weekday() (0=Mon, 6=Sun)
DAY_MAP = {
    "Monday": 0, "Tuesday": 1, "Wednesday": 2, "Thursday": 3,
    "Friday": 4, "Saturday": 5, "Sunday": 6
}

# List of month names for the yearly planning view (indexed 1-12)
MONTH_NAMES = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]

# Function for full moon icon retrieval
def get_full_moon_icon(month):
    """Returns the icon filename based on the month number (1-12)."""
    return f"Calendar Icons-{str(month).zfill(2)}.png"

# Storing the icon information for each kennel
KENNEL_ICONS = {
    "Dallas Urban Hash": "DUH.png",
    "NO-NO-DUH": "nonoduh.png", 
    "Dallas Hash": "dallas.png",
    "Ft Worth Hash": "ftworth.png",
    "Full Moon Hash": get_full_moon_icon,
    "7-ELEVEn hash house harriers": "7-ELEVEn.png" 
}

# --- HTML Templates ---
HTML_HEAD_PLANNING = """
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtmltransitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<title>Yearly Planning Calendar - {year}</title>
<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />
<?php
    $year={year};
    include 'big.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,1107,91" href="planning.php" alt="Planning Calendar" />
</map>
<div class=container>
    <table class="overall"  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="planning.png" alt=""  border="0" usemap="#Map"/></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table class="main"  border="0" cellspacing="0" cellpadding="0">
                    <tr >
                        <th>Sunday</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
"""

HTML_FOOTER_PLANNING = """
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
"""

HTML_HEAD_MONTH = """
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
<title>{month_name}, {year} Hash Events</title>
<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />

<script language="JavaScript">
// script to highlight todays date via style override
var d = new Date();
var id = "j" + d.getMonth() + d.getDate();
      if (d.getYear() % 100 == {year_short}) document.write('<style type="text/css" media="screen"></style>');

			// script to open navagation window
			function openNav() {{
			window.open("/calendar/Nav/index.html", "nav", "width=320, height=1040, top=0, left=0");
			}}
		</script>

<?php
    $year={year};
    $month={month};
    // Links are passed from Python for correct cross-year referencing
    $prev_link="{prev_link}";
    $next_link="{next_link}";
    include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,150,91" href="{prev_link}" alt="Previous Month" />
    <!-- FIXED: Using coordinates 957,0,807,91 as requested for the Next Month button -->
    <area shape="rect" coords="957,0,807,91" href="{next_link}" alt="Next Month" />
</map>
<div class=container>
    <table class="overall"  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="{image_file}" alt="{month_name}"  border="0" usemap="#Map"/></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table class="main"  border="0" cellspacing="0" cellpadding="0">
                    <tr >
                        <th>Sunday</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
"""
HTML_FOOTER_MONTH = """
                </table>
            </td>
        </tr>
    </table>
</div>

<tr id="nav">
			<td>
			<div id="menu">
				<a href="/index.html">home</a>&nbsp;&nbsp;&nbsp;&nbsp;
				calendar&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Events/index.html">events</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Maps/index.html">maps</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Our_Idiots/index.html">our idiots</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Write-Ups/index.html">write-ups</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Road_Trip/index.html">road trip</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="planning.php">year</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/mobile/index.php">mobile</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="#" onclick="openNav();">nav</a>&nbsp;&nbsp;&nbsp;&nbsp;
			</div>
			</td>
		</tr>
</body>
</html>
"""