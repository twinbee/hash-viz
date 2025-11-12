import os
import calendar
from datetime import datetime, timedelta
import argparse
from datetime import datetime, timezone
from datetime import date 
import math 

# --- Constants for Dallas, TX ---
DALLAS_LATITUDE = 32.7767
DALLAS_LONGITUDE = -96.7970 # West is negative
# --- End Constants ---

# Dictionary to map month number to the specific moon name used in banner filenames
MOON_NAMES = {
    1: "Wolf", 2: "Snow", 3: "Worm", 4: "Pink", 5: "Flower", 6: "Strawberry",
    7: "Buck", 8: "Sturgeon", 9: "Harvest", 10: "Hunter", 11: "Beaver", 12: "Cold"
}

# Specification block for initial date and run numbers for each kennel
kennel_specs = {
    "Dallas Hash": {"initial_date": datetime(2024, 1, 6), "run_number": 1151},
    "Ft Worth Hash": {"initial_date": datetime(2024, 1, 13), "run_number": 999},
    "Dallas Urban Hash": {"initial_date": datetime(2024, 1, 3), "run_number": 731},
    "NO-NO-DUH": {"initial_date": datetime(2024, 1, 15), "run_number": 5}, 
    "YAKH3": {"initial_date": datetime(2024, 6, 2), "run_number": 1}, 
    "Full Moon Hash": {"initial_date": datetime(2024, 1, 25), "run_number": 63}
}

# Hashcash and schedule rules for each kennel
kennel_rules = {
    "Dallas Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$10.00 - Pay Online: Paypal $10", "day": "Saturday"},
    "Ft Worth Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$7.00 cash - Paypal $7 - Pay pal (FWH3) or Zelle 817-689-9363 - BYOB pre-lube beer", "day": "Saturday"},
    "Dallas Urban Hash": {"frequency": "weekly", "time": "6:30 PM", "hashcash": "", "day": "Wednesday"},
    "NO-NO-DUH": {"frequency": "bi-weekly", "time": "7:00 PM", "hashcash": "$7.00", "day": "Monday"}, 
    "YAKH3": {"frequency": "summer-sundays", "time": "12:00 PM", "hashcash": "", "day": "Sunday"}, 
    "Full Moon Hash": {"frequency": "full-moon", "time": "varies", "hashcash": "", "day": "full-moon"}
}

# Helper dictionary to map rule["day"] name to Python's weekday() (0=Mon, 6=Sun)
DAY_MAP = {
    "Monday": 0, "Tuesday": 1, "Wednesday": 2, "Thursday": 3,
    "Friday": 4, "Saturday": 5, "Sunday": 6
}

# List of month names for the yearly planning view (indexed 1-12)
MONTH_NAMES = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]

# NEW FUNCTION FOR FULL MOON ICON RETRIEVAL
def get_full_moon_icon(month):
    """Returns the icon filename based on the month number (1-12)."""
    return f"Calendar Icons-{str(month).zfill(2)}.png"

# Storing the icon information for each kennel
kennel_icons = {
    "Dallas Urban Hash": "DUMB.png",
    "NO-NO-DUH": "NoDHHH2.png", 
    "Dallas Hash": "dallas.png",
    "Ft Worth Hash": "ftworth.png",
    "Full Moon Hash": get_full_moon_icon
}

# Helper function to get the day of the week for a specific date
def get_day_of_week(year, month, day):
    # Returns 0 for Monday, 6 for Sunday
    return date(year, month, day).weekday()

# =========================================================================
# SPECIAL DATE CALCULATION FUNCTIONS (for 'red bar' and 'blue bar')
# =========================================================================

def get_day_of_occurrence(year, month, day_of_week, occurrence):
    """
    Finds the date of the Nth occurrence of a weekday in a month.
    (day_of_week: 0=Mon, 6=Sun; occurrence: 1=1st, 2=2nd, -1=Last)
    """
    if occurrence > 0:
        d = date(year, month, 1)
        # Find the first occurrence of the target day
        days_to_add = (day_of_week - d.weekday() + 7) % 7
        d += timedelta(days=days_to_add)
        # Add weeks for the Nth occurrence
        if occurrence > 1:
            d += timedelta(weeks=occurrence - 1)
    else: # For last occurrence (-1)
        # Start at the 1st of the next month and go back one day, then find the target day
        if month == 12:
            d = date(year, 12, 31)
        else:
            d = date(year, month + 1, 1) - timedelta(days=1)

        # Find the last occurrence of the target day
        days_to_subtract = (d.weekday() - day_of_week + 7) % 7
        d -= timedelta(days=days_to_subtract)

    if d.month == month and d.year == year:
        return d
    return None

def get_special_dates_for_year(year):
    """
    Calculates all Major US, Drinking, Personal, EOP, and Hashmas Holidays for the given year.
    Returns: { (month, day): (Name, CSS_Class) }
    """
    special_dates = {} 
    
    # --- RED BAR Holidays (Major US & Drinking/Personal) ---
    
    # Fixed Date Red Holidays (Removed (D) designation)
    red_holidays = {
        (1, 1): "New Year's Day",
        (2, 14): "Valentine's Day", 
        (3, 17): "St. Patrick's Day",
        (5, 5): "Cinco de Mayo",
        (6, 19): "Juneteenth",
        (7, 4): "Independence Day",
        (7, 31): "Gispert's Birthday", # NEW: Gispert's Birthday
        (10, 31): "Halloween",
        (11, 11): "Veterans Day",
        (12, 25): "Christmas Day",
        (12, 31): "New Year's Eve"
    }
    
    for (m, d), name in red_holidays.items():
        special_dates[(m, d)] = (name, "holiday") # CHANGED: Class set to "holiday"

    # Floating Red Holidays
    
    # MLK Jr. Day: Third Monday in January (1, 3rd, Mon)
    mlk = get_day_of_occurrence(year, 1, 0, 3) 
    if mlk: special_dates[(mlk.month, mlk.day)] = ("MLK Jr. Day", "holiday")

    # Presidents' Day: Third Monday in February (2, 3rd, Mon)
    presidents = get_day_of_occurrence(year, 2, 0, 3)
    if presidents: special_dates[(presidents.month, presidents.day)] = ("Presidents' Day", "holiday")

    # Memorial Day: Last Monday in May (5, Last, Mon)
    memorial = get_day_of_occurrence(year, 5, 0, -1)
    if memorial: special_dates[(memorial.month, memorial.day)] = ("Memorial Day", "holiday")

    # Labor Day: First Monday in September (9, 1st, Mon)
    labor = get_day_of_occurrence(year, 9, 0, 1)
    if labor: special_dates[(labor.month, labor.day)] = ("Labor Day", "holiday")

    # Columbus Day: Second Monday in October (10, 2nd, Mon)
    columbus = get_day_of_occurrence(year, 10, 0, 2)
    if columbus: special_dates[(columbus.month, columbus.day)] = ("Columbus Day", "holiday")

    # Thanksgiving Day: Fourth Thursday in November (11, 4th, Thu)
    thanksgiving = get_day_of_occurrence(year, 11, 3, 4) 
    if thanksgiving: special_dates[(thanksgiving.month, thanksgiving.day)] = ("Thanksgiving Day", "holiday")


    # --- BLUE BAR Holidays (EOP and Hashmas) ---
    
    # EOP Days (14 days up to and including July 4th)
    july_4th = date(year, 7, 4)
    for i in range(14):
        eop_date = july_4th - timedelta(days=i)
        # EOP #14 is 13 days before July 4th. EOP #1 is July 4th.
        eop_num_countdown = 14 - i 
        eop_name = f"EOP #{eop_num_countdown}"
        # Skip if the date is already a Red Holiday (July 4th)
        if (eop_date.month, eop_date.day) not in red_holidays:
            special_dates[(eop_date.month, eop_date.day)] = (eop_name, "blue_bar") # CHANGED: Class set to "blue_bar"

    # Hashmas (12 days up to and including December 25th)
    christmas_day = date(year, 12, 25)
    for i in range(12):
        hashmas_date = christmas_day - timedelta(days=i)
        # Hashmas Day 12 is 11 days before Dec 25th. Hashmas Day 1 is Dec 25th.
        hashmas_num_countdown = 12 - i
        hashmas_name = f"Hashmas Day {hashmas_num_countdown}"
        # Skip if the date is already a Red Holiday (Christmas Day)
        if (hashmas_date.month, hashmas_date.day) not in red_holidays:
            special_dates[(hashmas_date.month, hashmas_date.day)] = (hashmas_name, "blue_bar") # CHANGED: Class set to "blue_bar"

    return special_dates

# =========================================================================
# SUNSET CALCULATION FUNCTIONS (for 'twilight' field)
# =========================================================================

def is_dst_dallas(date_obj):
    """Checks if a given datetime object is within the US DST period."""
    year = date_obj.year
    
    # DST Start: Second Sunday in March
    march_first = date(year, 3, 1)
    first_sunday_march = march_first + timedelta(days=(6 - march_first.weekday()) % 7) 
    dst_start_date = first_sunday_march + timedelta(weeks=1)

    # DST End: First Sunday in November
    november_first = date(year, 11, 1)
    dst_end_date = november_first + timedelta(days=(6 - november_first.weekday()) % 7) 
    
    event_date = date_obj.date()
    
    is_dst_active = (event_date >= dst_start_date) and (event_date < dst_end_date)
    return is_dst_active

def calculate_sunset_time_dallas(date_obj):
    """Calculates the sunset time for Dallas, TX for a given date."""
    
    N = date_obj.timetuple().tm_yday - 1
    M = (0.9856 * N) + 357.5291
    M = M % 360
    C = (1.9148 * math.sin(math.radians(M))) + (0.0200 * math.sin(math.radians(2 * M))) + (0.0003 * math.sin(math.radians(3 * M)))
    L_sun = M + 282.9404 + C
    L_sun = L_sun % 360
    Dec = math.degrees(math.asin(math.sin(math.radians(23.44)) * math.sin(math.radians(L_sun))))

    try:
        cos_H = (math.sin(math.radians(-0.833)) - math.sin(math.radians(DALLAS_LATITUDE)) * math.sin(math.radians(Dec))) / \
                (math.cos(math.radians(DALLAS_LATITUDE)) * math.cos(math.radians(Dec)))
    except ValueError:
        return "" 

    if cos_H > 1 or cos_H < -1:
        return "" 

    H = math.degrees(math.acos(cos_H)) / 15.0 

    RA = math.degrees(math.atan2(math.cos(math.radians(23.44)) * math.sin(math.radians(L_sun)), math.cos(math.radians(L_sun)))) / 15.0
    RA = RA % 24
    
    UT_set = 12 + H - RA - (DALLAS_LONGITUDE / 15.0)
    UT_set = UT_set % 24
    
    time_offset = 6 
    local_hour = UT_set - time_offset
    
    if is_dst_dallas(date_obj):
        local_hour += 1 

    hour = int(local_hour) % 24
    minute = int(round((local_hour - int(local_hour)) * 60))
    
    if minute >= 60:
        minute -= 60
        hour += 1
    elif minute < 0:
        minute += 60
        hour -= 1
        
    hour = hour % 24
    
    try:
        final_time = datetime(date_obj.year, date_obj.month, date_obj.day, hour, minute)
    except ValueError:
        return "" 
    
    return final_time.strftime("%#I:%M %p").replace(' 0', ' ')

# =========================================================================
# Full Moon Date Calculation
# =========================================================================
def calculate_full_moons_for_year(target_year):
    """
    Calculates approximate full moon dates for the target year using the mean synodic period.
    """
    REF_DATE = datetime(2024, 1, 25, 12)
    SYNODIC_MONTH = 29.530588 

    start_of_target_year = datetime(target_year, 1, 1)
    days_to_target = (start_of_target_year - REF_DATE).total_seconds() / (60*60*24)
    approx_lunations = round(days_to_target / SYNODIC_MONTH)

    current_fm_time = REF_DATE + timedelta(days=approx_lunations * SYNODIC_MONTH)

    while current_fm_time.year >= target_year:
        current_fm_time -= timedelta(days=SYNODIC_MONTH)

    fm_dates_set = set()

    for i in range(15): 
        current_fm_time += timedelta(days=SYNODIC_MONTH)

        if current_fm_time.year == target_year:
            fm_date = current_fm_time.date()
            fm_dates_set.add((fm_date.month, fm_date.day))
        elif current_fm_time.year > target_year:
            break

    return sorted(list(fm_dates_set))
# =========================================================================


# Function to calculate next event based on frequency
def calculate_next_event(kennel, start_date, current_date, frequency):
    if frequency == "weekly":
        delta = timedelta(weeks=1)
    elif frequency == "bi-weekly":
        delta = timedelta(weeks=2)
    elif frequency == "summer-sundays":
        delta = timedelta(weeks=4)
    elif frequency == "full-moon":
        return None
    else:
        return None

    next_event = start_date
    while next_event.date() < current_date.date():
        next_event += delta
        
    kennel_day_name = kennel_rules[kennel]["day"]
    kennel_day_of_week = DAY_MAP.get(kennel_day_name)
    
    if next_event.date() > current_date.date():
        next_event -= delta

    if next_event.date() < current_date.date():
        next_event += delta
        
    return next_event if next_event.date() == current_date.date() else None

# Function to generate TSV event data for each month based on the rules
def generate_tsv_events(month, year, kennel_run_numbers):
    events = []
    start_of_month = datetime(year, month, 1)
    end_of_month = datetime(year, month, calendar.monthrange(year, month)[1])

    current_utc_time = datetime.now(timezone.utc).strftime('%m/%d/%Y %H:%M UTC')

    for kennel, spec in kennel_specs.items():
        start_date = spec["initial_date"]
        run_number = kennel_run_numbers[kennel]
        rule = kennel_rules[kennel]

        # --- Full Moon Hash Logic ---
        if rule["frequency"] == "full-moon":
            full_moon_dates = calculate_full_moons_for_year(year)

            for fm_month, fm_day in full_moon_dates:
                if fm_month == month:
                    next_event = datetime(year, fm_month, fm_day, 19, 0, 0)

                    if start_of_month.date() <= next_event.date() <= end_of_month.date():
                        
                        sunset_time_str = calculate_sunset_time_dallas(next_event) 

                        event = {
                            "day": next_event.day,
                            "kennel": kennel,
                            "title": "Full Moon Hash",
                            "run": run_number,
                            "hares": "", 
                            "time": "7:00 PM (time may vary)",
                            "start": "", 
                            "map": "",
                            "hashcash": rule["hashcash"],
                            "turds": "Yes",
                            "tweet": "",
                            "twilight": sunset_time_str, 
                            "date": next_event,
                            "desc": "", 
                            "update": next_event.strftime("%m/%d/%Y %H:%M")
                        }
                        events.append(event)
                        run_number += 1
            kennel_run_numbers[kennel] = run_number
            continue

        # --- Regular Event Logic (Weekly/Bi-Weekly/Summer-Sundays) ---
        current_date = start_of_month
        while current_date <= end_of_month:

            kennel_day_name = rule["day"]
            kennel_day_of_week = DAY_MAP.get(kennel_day_name)
            current_day_of_week = current_date.weekday()

            if rule["frequency"] == "summer-sundays" and current_date.month not in [6, 7, 8]:
                current_date += timedelta(days=1)
                continue

            if current_day_of_week != kennel_day_of_week:
                current_date += timedelta(days=1)
                continue

            expected_event_date_dt = calculate_next_event(kennel, start_date, current_date, rule["frequency"])

            if expected_event_date_dt and expected_event_date_dt.date() == current_date.date():
                
                time_str = rule["time"]
                
                sunset_time_str = calculate_sunset_time_dallas(datetime(year, month, current_date.day))

                event = {
                    "day": current_date.day,
                    "kennel": kennel,
                    "title": f"{kennel} Run",
                    "run": run_number,
                    "hares": "", 
                    "time": time_str,
                    "start": "", 
                    "map": "",
                    "hashcash": rule["hashcash"],
                    "turds": "Yes",
                    "tweet": "",
                    "twilight": sunset_time_str, 
                    "date": datetime(year, month, current_date.day),
                    "desc": "", 
                    "update": current_date.strftime("%m/%d/%Y %H:%M")
                }
                events.append(event)
                run_number += 1
                
                if rule["frequency"] == "weekly":
                    current_date += timedelta(weeks=1)
                elif rule["frequency"] == "bi-weekly":
                    current_date += timedelta(weeks=2)
                elif rule["frequency"] == "summer-sundays":
                    current_date += timedelta(weeks=4)
                else:
                    current_date += timedelta(days=1) 
            else:
                current_date += timedelta(days=1)


        # Update the master run numbers for the next month
        kennel_run_numbers[kennel] = run_number

    # Sort all events by date
    events.sort(key=lambda x: x["date"])

    # Format into TSV rows
    rows = []
    # TSV Header (must be consistent)
    header = ["day", "kennel", "icon", "title", "run", "hares", "time", "start", "map", "hashcash", "turds", "tweet", "twilight", "date", "desc", "update"]
    rows.append("\t".join(header))

    # Format each event into a TSV row
    for event in events:
        icon_source = kennel_icons.get(event['kennel'], "")
        if callable(icon_source):
            icon = icon_source(event['date'].month)
        else:
            icon = icon_source

        date_str = event['date'].strftime('%A, %B %d, %Y')
        current_revision = "(calgen 1.7)"
        update_info = f"{current_revision} {current_utc_time}"
        
        row = [
            str(event['day']),
            event['kennel'],
            icon,
            event['title'],
            str(event['run']),
            event['hares'],
            event['time'],
            event['start'],
            event['map'],
            event['hashcash'],
            event['turds'],
            event['tweet'],
            event['twilight'], 
            date_str,
            event['desc'],
            update_info
        ]
        rows.append("\t".join(row))

    return "\n".join(rows)

# Function to generate the HTML for event rows in the MONTHLY PHP file
def generate_event_rows(month, year):
    # ... (remains the same)
    first_day_of_week = date(year, month, 1).weekday()
    num_days = calendar.monthrange(year, month)[1]

    # Adjust for PHP table generation: first_day_of_week needs to be 0=Sunday, 6=Saturday
    php_first_day_of_week = (first_day_of_week + 1) % 7

    html_rows = ""
    day_count = 1
    
    html_rows += "\t\t\t\t\t<tr>\n"

    for i in range(php_first_day_of_week):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'

    while day_count <= num_days:
        
        if (php_first_day_of_week + day_count - 1) % 7 == 0 and day_count > 1:
            html_rows += '\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n'

        js_id = f"j{month-1}{day_count}"
        day_class = "day" 

        html_rows += f'\t\t\t\t\t\t<td class="{day_class}">\n'
        html_rows += f'\t\t\t\t\t\t\t<table class="inner" id="{js_id}">\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += f'\t\t\t\t\t\t\t\t\t<td class="dom">{day_count}</td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t\t<td class="event">\n'
        html_rows += f'\t\t\t\t\t\t\t\t\t\t<?php fillIn({month}, {day_count}, {year}); ?>\n'
        html_rows += '\t\t\t\t\t\t\t\t\t</td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t</table>\n'
        html_rows += '\t\t\t\t\t\t</td>\n'
        
        day_count += 1

    last_day_of_week = (php_first_day_of_week + num_days - 1) % 7
    for i in range(last_day_of_week, 6):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'

    html_rows += '\t\t\t\t\t</tr>\n'
    
    return html_rows


# This template is for the yearly planning file (e.g., planning.php)
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

# This template is for the month-by-month files (e.g., $01-2026.php)
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
    include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,150,91" href="{prev_link}" alt="Previous Month" />
    <area shape="rect" coords="957,0,1107,91" href="{next_link}" alt="Next Month" />
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

# FIX: New function to generate the single, contiguous year grid for planning.php
def generate_year_grid_for_planning(year):
    """Generates the full-year daily grid for the planning.php file, marking holidays in red/blue."""
    
    # NEW: Calculate all special dates for the year
    special_dates = get_special_dates_for_year(year) # { (month, day): (Name, CSS_Class: "holiday" or "blue_bar") }
    
    first_day_of_year = date(year, 1, 1)
    # Python weekday(): 0=Mon, 6=Sun. PHP calendar table: 0=Sun, 6=Sat.
    php_first_day_of_week = (first_day_of_year.weekday() + 1) % 7
    
    start_date = date(year, 1, 1)
    end_date = date(year, 12, 31)
    
    html_rows = ""
    current_date = start_date

    # 1. Start the first week row
    html_rows += '\t\t\t\t\t<tr>\n'
    
    # 2. Fill in empty cells before the first day of the year
    for i in range(php_first_day_of_week):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'
        
    day_counter = php_first_day_of_week
    
    while current_date <= end_date:
        
        # 3. Check if a new row is needed (it's Sunday)
        if day_counter % 7 == 0 and current_date != start_date:
            html_rows += '\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n'
            
        # --- MODIFICATION FOR SPECIAL DATES (RED/BLUE) ---
        date_key = (current_date.month, current_date.day)
        special_info = special_dates.get(date_key)
        
        if special_info:
            info_text = special_info[0] # Name
            day_class = special_info[1] # CSS_Class ("holiday" or "blue_bar") -> Outer TD Class for Full-Bar Color
            dom_class = day_class # Inner TD Class for Holiday Name/Day
        else:
            day_class = "day" 
            info_text = ""
            dom_class = "dom" # Default inner TD Class
        # ---------------------------------
        
        # Determine the day text: MonthName DayOfMonth (only for the first day of the month) or just DayOfMonth
        dom_text = str(current_date.day)
        if current_date.day == 1:
            dom_text = f"{MONTH_NAMES[current_date.month]} {current_date.day}"

        # Outer TD uses the day_class (holiday, blue_bar, or day) for background color
        html_rows += f'\t\t\t\t\t\t<td class="{day_class}">\n'
        html_rows += '\t\t\t\t\t\t\t<table class="inner">\n' 
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        
        # NEW DOM/Holiday row structure (combining holiday name and day number)
        if special_info:
            # Combined structure: <td class="holiday"><span class="tag">Holiday Name</span>DayNumber</td>
            html_rows += f'\t\t\t\t\t\t\t\t\t<td class="{dom_class}"><span class="tag">{info_text}</span>{dom_text}</td>\n'
        else:
            # Original structure for non-holidays: <td class="dom">DayNumber</td>
            html_rows += f'\t\t\t\t\t\t\t\t\t<td class="{dom_class}">{dom_text}</td>\n'

        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        # PHP call to fill in events
        html_rows += f'\t\t\t\t\t\t\t\t<td class="event"> <?php fillIn({current_date.month}, {current_date.day}, {year}); ?></td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        # REMOVED the old "info" <tr> to match the requested two-row format
        html_rows += '\t\t\t\t\t\t\t</table>\n'
        html_rows += '\t\t\t\t\t\t</td>\n'
        
        current_date += timedelta(days=1)
        day_counter += 1

    # 4. Fill in remaining empty cells at the end of the last week
    last_day_of_week = (day_counter - 1) % 7 
    if last_day_of_week != 6: 
        for i in range(last_day_of_week, 6):
            html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'
            
    # End the last week row
    html_rows += '\t\t\t\t\t</tr>\n'
    
    return html_rows

# FIX: Updated generate_planning_php to use the new grid function
def generate_planning_php(year, kennel_run_numbers):
    php_content = HTML_HEAD_PLANNING.format(year=year)
    
    # NEW: Generate the full year grid with holiday marking
    php_content += generate_year_grid_for_planning(year)
    
    php_content += HTML_FOOTER_PLANNING

    # NOTE: The file is generated in calendar/{year}/planning.php
    planning_file_path = f"calendar/{year}/planning.php"
    os.makedirs(os.path.dirname(planning_file_path), exist_ok=True)
    with open(planning_file_path, 'w') as php_file:
        php_file.write(php_content)
    
    return planning_file_path


def generate_files_for_month(month, year, kennel_run_numbers):
    # Determine next/previous month and year for links
    prev_month = month - 1
    prev_year = year
    if prev_month == 0:
        prev_month = 12
        prev_year -= 1
        
    next_month = month + 1
    next_year = year
    if next_month == 13:
        next_month = 1
        next_year += 1
        
    # Generate link paths
    prev_month_link = f"../{prev_year}/${str(prev_month).zfill(2)}-{prev_year}.php"
    next_month_link = f"../{next_year}/${str(next_month).zfill(2)}-{next_year}.php"

    # --- Generate TSV File ---
    tsv_content = generate_tsv_events(month, year, kennel_run_numbers)
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
    os.makedirs(os.path.dirname(tsv_file_path), exist_ok=True)
    with open(tsv_file_path, 'w') as tsv_file:
        tsv_file.write(tsv_content)

    # --- Generate PHP File ---
    month_name = MONTH_NAMES[month]
    year_short = year % 100
    
    # FIX: Use the specific moon name for the banner image file
    moon_name = MOON_NAMES.get(month, month_name) 
    image_file = f"{str(month).zfill(2)}-{moon_name}_moon@4x.png"
    
    php_head = HTML_HEAD_MONTH.format(
        month_name=month_name,
        month=month, 
        year=year,
        year_short=year_short,
        image_file=image_file, 
        prev_link=prev_month_link,
        next_link=next_month_link
    )
    
    php_rows = generate_event_rows(month, year)
    
    php_content = php_head + php_rows + HTML_FOOTER_MONTH
    
    php_file_path = f"calendar/{year}/${str(month).zfill(2)}-{year}.php"
    os.makedirs(os.path.dirname(php_file_path), exist_ok=True)
    with open(php_file_path, 'w') as php_file:
        php_file.write(php_content)

    return php_file_path, tsv_file_path

# Main function to generate files for an entire year
def generate_files_for_year(year):
    # Initialize run numbers for each kennel
    kennel_run_numbers = {kennel: spec["run_number"] for kennel, spec in kennel_specs.items()}

    print(f"Generating monthly files for {year}...")
    for month in range(1, 13):
        # generate_files_for_month updates kennel_run_numbers for the next month
        php_file_path, tsv_file_path = generate_files_for_month(month, year, kennel_run_numbers)
        print(f"Generated: {php_file_path} and {tsv_file_path}")

    # Generate the yearly planning file after all monthly events have been calculated
    print(f"Generating full-year planning file...")
    planning_file_path = generate_planning_php(year, kennel_run_numbers) 
    print(f"Generated: {planning_file_path}")


if __name__ == "__main__":
    # Argument parser to get year input from the command line
    parser = argparse.ArgumentParser(description="Generate calendar and android files for the given year.")
    parser.add_argument("year", type=int, help="The year for which to generate the files (e.g., 2038).")
    args = parser.parse_args()
    
    generate_files_for_year(args.year)