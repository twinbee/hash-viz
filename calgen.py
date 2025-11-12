
import os
import calendar
from datetime import datetime, timedelta
import argparse
from datetime import datetime, timezone
from datetime import date # Added for get_day_of_week helper

# Dictionary to map month number to the specific moon name used in banner filenames
MOON_NAMES = {
    1: "Wolf", 2: "Snow", 3: "Worm", 4: "Pink", 5: "Flower", 6: "Strawberry",
    7: "Buck", 8: "Sturgeon", 9: "Harvest", 10: "Hunter", 11: "Beaver", 12: "Cold"
}

# Specification block for initial date and run numbers for each kennel
# 1) FIX: Replaced "NODUH Hash" with "NO-NO-DUH" using the user's provided start date/run number
kennel_specs = {
    "Dallas Hash": {"initial_date": datetime(2024, 1, 6), "run_number": 1151},
    "Ft Worth Hash": {"initial_date": datetime(2024, 1, 13), "run_number": 999},
    "Dallas Urban Hash": {"initial_date": datetime(2024, 1, 3), "run_number": 731},
    "NO-NO-DUH": {"initial_date": datetime(2024, 1, 15), "run_number": 5}, # NEW HASH
    # "NODUH Hash": {"initial_date": datetime(2024, 1, 8), "run_number": 319}, # OLD HASH REMOVED
    "YAKH3": {"initial_date": datetime(2024, 6, 2), "run_number": 1},  # First Sunday in summer
    "Full Moon Hash": {"initial_date": datetime(2024, 1, 25), "run_number": 63}  # Reference date for calculation
}

# Hashcash and schedule rules for each kennel
# 1) FIX: Added "NO-NO-DUH" rules
kennel_rules = {
    "Dallas Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$10.00 - Pay Online: Paypal $10", "day": "Saturday"},
    "Ft Worth Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$7.00 cash - Paypal $7 - Pay pal (FWH3) or Zelle 817-689-9363 - BYOB pre-lube beer", "day": "Saturday"},
    "Dallas Urban Hash": {"frequency": "weekly", "time": "6:30 PM", "hashcash": "", "day": "Wednesday"},
    "NO-NO-DUH": {"frequency": "monthly", "time": "7:00 PM", "hashcash": "$7.00", "day": "Monday"}, # NEW HASH RULES
    # "NODUH Hash": {"frequency": "bi-weekly", "time": "7:00 PM", "hashcash": "$7.00", "day": "Monday"}, # OLD HASH REMOVED
    "YAKH3": {"frequency": "summer-sundays", "time": "12:00 PM", "hashcash": "", "day": "Sunday"},  # Summer months only
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
    # The file names for full moon icons are in the format "Calendar Icons-MM.png"
    return f"Calendar Icons-{str(month).zfill(2)}.png"

# Storing the icon information for each kennel
# 1) FIX: Added "NO-NO-DUH" icon and removed the old "NODUH Hash"
kennel_icons = {
    "Dallas Urban Hash": "DUMB.png",
    "NO-NO-DUH": "nonoduh.png", # Using the old NODUH icon file name
    # "NODUH Hash": "NoDHHH2.png", # OLD HASH REMOVED
    "Dallas Hash": "dallas.png",
    "Ft Worth Hash": "ftworth.png",
    # Use the function for the Full Moon Hash icon
    "Full Moon Hash": get_full_moon_icon
}

# Helper function to get the day of the week for a specific date
def get_day_of_week(year, month, day):
    # Returns 0 for Monday, 6 for Sunday
    return date(year, month, day).weekday()

# =========================================================================
# Full Moon Date Calculation
# =========================================================================
def calculate_full_moons_for_year(target_year):
    """
    Calculates approximate full moon dates for the target year using the mean synodic period.
    The starting date is taken from the Full Moon Hash initial_date in kennel_specs.
    """
    # Reference full moon: Jan 25, 2024 (12:00 PM) from kennel_specs
    REF_DATE = datetime(2024, 1, 25, 12)
    SYNODIC_MONTH = 29.530588  # Mean synodic month in days

    # 1. Determine the approximate number of lunations (N) from the REF_DATE to Jan 1 of the target year.
    start_of_target_year = datetime(target_year, 1, 1)
    days_to_target = (start_of_target_year - REF_DATE).total_seconds() / (60*60*24)
    approx_lunations = round(days_to_target / SYNODIC_MONTH)

    # 2. Calculate the estimated first full moon time *near* Jan 1 of the target year.
    current_fm_time = REF_DATE + timedelta(days=approx_lunations * SYNODIC_MONTH)

    # 3. Step backward one lunation to ensure we start *before* the first one in the target year.
    while current_fm_time.year >= target_year:
        current_fm_time -= timedelta(days=SYNODIC_MONTH)

    # 4. Step forward, collecting all full moon dates that fall in the target year.
    fm_dates_set = set()

    for i in range(15): # 15 iterations covers 12-13 moons plus a buffer
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
    # The 'summer-sundays' logic relies on a 4-week cycle starting from initial_date
    elif frequency == "summer-sundays":
        delta = timedelta(weeks=4)
    elif frequency == "full-moon":
        return None
    else:
        return None

    next_event = start_date
    while next_event.date() < current_date.date():
        next_event += delta
        
    # Check for the correct day of the week as well (important for non-weekly)
    kennel_day_name = kennel_rules[kennel]["day"]
    kennel_day_of_week = DAY_MAP.get(kennel_day_name)
    
    # If the current next_event date's day of week does not match the kennel's required day,
    # and the frequency is bi-weekly/seasonal, we might need a tighter check.
    # However, for bi-weekly/weekly, the initial date *should* be the correct day, 
    # and adding weeks preserves the day of week. We rely on the caller's logic 
    # (checking `current_day_of_week == kennel_day_of_week`) to be correct.
    # We only need to ensure `next_event` is *the* event date that matches the cycle.
    
    # If the calculated date is in the future, step back one cycle if needed to find the closest one
    if next_event.date() > current_date.date():
        next_event -= delta

    # If even after stepping back, the date is still before the current date, step forward once to get the match.
    if next_event.date() < current_date.date():
        next_event += delta
        
    return next_event if next_event.date() == current_date.date() else None

# Function to generate TSV event data for each month based on the rules
# (No changes needed here, as the logic for NO-NO-DUH is covered by the dict updates)
def generate_tsv_events(month, year, kennel_run_numbers):
    events = []
    start_of_month = datetime(year, month, 1)
    end_of_month = datetime(year, month, calendar.monthrange(year, month)[1])

    # Get current time in UTC for update timestamp
    current_utc_time = datetime.now(timezone.utc).strftime('%m/%d/%Y %H:%M UTC')

    # Iterate through each kennel to generate events for the month
    for kennel, spec in kennel_specs.items():
        start_date = spec["initial_date"]
        run_number = kennel_run_numbers[kennel]
        rule = kennel_rules[kennel]

        # --- Full Moon Hash Logic ---
        if rule["frequency"] == "full-moon":
            full_moon_dates = calculate_full_moons_for_year(year)

            for fm_month, fm_day in full_moon_dates:
                if fm_month == month:
                    # Setting a default time of 7 PM for the event object
                    next_event = datetime(year, fm_month, fm_day, 19, 0, 0)

                    if start_of_month.date() <= next_event.date() <= end_of_month.date():
                        # Determine if it's an evening run (twilight field)
                        is_twilight = "T" # Default to Twilight for evening/full moon hashes
                        
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
                            "twilight": is_twilight,
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

            # Add explicit day-of-week and seasonal checks for robustness
            kennel_day_name = rule["day"]
            kennel_day_of_week = DAY_MAP.get(kennel_day_name)
            current_day_of_week = current_date.weekday()

            # 1. Skip if the kennel is seasonal (summer-sundays) and it's out of season
            if rule["frequency"] == "summer-sundays" and current_date.month not in [6, 7, 8]:
                current_date += timedelta(days=1)
                continue

            # 2. Skip if the current date is NOT the day of the week specified in the rule
            # Python weekday() is 0=Mon to 6=Sun. DAY_MAP is 0=Mon to 6=Sun.
            if current_day_of_week != kennel_day_of_week:
                current_date += timedelta(days=1)
                continue

            # 3. Determine if the event day aligns with the frequency and initial date
            # Calculate the expected next event date for this day of the week
            expected_event_date_dt = calculate_next_event(kennel, start_date, current_date, rule["frequency"])

            if expected_event_date_dt and expected_event_date_dt.date() == current_date.date():
                # This date is a match for the kennel's schedule
                
                # Determine run time for the event object (defaulting to start of day for now)
                time_str = rule["time"]
                
                # Determine if it's an evening run (twilight field)
                is_twilight = "T" if "PM" in time_str else ""

                event = {
                    "day": current_date.day,
                    "kennel": kennel,
                    "title": "",
                    "run": run_number,
                    "hares": "",
                    "time": time_str,
                    "start": "",
                    "map": "",
                    "hashcash": rule["hashcash"],
                    "turds": "Yes", # Default assumption
                    "tweet": "",
                    "twilight": is_twilight,
                    "date": datetime(year, month, current_date.day),
                    "desc": "",
                    "update": current_date.strftime("%m/%d/%Y %H:%M")
                }
                events.append(event)
                run_number += 1
                
                # Advance date based on frequency to avoid multiple checks on the same event
                if rule["frequency"] == "weekly":
                    current_date += timedelta(weeks=1)
                elif rule["frequency"] == "bi-weekly":
                    current_date += timedelta(weeks=2)
                elif rule["frequency"] == "summer-sundays":
                    current_date += timedelta(weeks=4)
                else:
                    current_date += timedelta(days=1) # Fallback
            else:
                # If it's the correct day of the week but not the correct week for bi-weekly/seasonal,
                # just advance by one day to check the next day
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
        # Determine the icon for the kennel
        icon_source = kennel_icons.get(event['kennel'], "")
        # If the icon source is the function (for Full Moon Hash), call it with the month
        if callable(icon_source):
            icon = icon_source(event['date'].month)
        else:
            icon = icon_source

        # Use the correct date format for TSV
        date_str = event['date'].strftime('%A, %B %d, %Y')
        # BUMPED REVISION TO 1.7 (for NO-NO-DUH change)
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
    # This remains the same as it correctly generates the PHP calls for a single month's grid
    first_day_of_week = date(year, month, 1).weekday()
    num_days = calendar.monthrange(year, month)[1]

    # Adjust for PHP table generation: first_day_of_week needs to be 0=Sunday, 6=Saturday
    php_first_day_of_week = (first_day_of_week + 1) % 7

    html_rows = ""
    day_count = 1
    
    # Start the first week row
    html_rows += "\t\t\t\t\t<tr>\n"

    # Fill in empty cells before the first day of the month
    for i in range(php_first_day_of_week):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'

    # Fill in the days of the month
    while day_count <= num_days:
        
        # Check if a new row is needed (it's Sunday, which is index 0 in the PHP output's table columns)
        if (php_first_day_of_week + day_count - 1) % 7 == 0 and day_count > 1:
            html_rows += '\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n'

        # Determine the unique ID for the day (Month - 1 + Day) for the highlighting script
        js_id = f"j{month-1}{day_count}"
        
        # Using a generic class here. The actual color will be applied by the PHP script.
        day_class = "day" 

        # Build the HTML for the day cell
        html_rows += f'\t\t\t\t\t\t<td class="{day_class}">\n'
        html_rows += f'\t\t\t\t\t\t\t<table class="inner" id="{js_id}">\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += f'\t\t\t\t\t\t\t\t\t<td class="dom">{day_count}</td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t\t<td class="event">\n'
        # PHP call to fill in events
        html_rows += f'\t\t\t\t\t\t\t\t\t\t<?php fillIn({month}, {day_count}, {year}); ?>\n'
        html_rows += '\t\t\t\t\t\t\t\t\t</td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t</table>\n'
        html_rows += '\t\t\t\t\t\t</td>\n'
        
        day_count += 1

    # Fill in remaining empty cells at the end of the last week
    last_day_of_week = (php_first_day_of_week + num_days - 1) % 7
    for i in range(last_day_of_week, 6):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'

    # End the last week row
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

# 3) FIX: New function to generate the single, contiguous year grid for planning.php
def generate_year_grid_for_planning(year):
    """Generates the full-year daily grid for the planning.php file."""
    
    first_day_of_year = date(year, 1, 1)
    # Python weekday(): 0=Mon, 6=Sun. PHP calendar table: 0=Sun, 6=Sat.
    # Conversion: (Python_weekday + 1) % 7
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
        
        # 3. Check if a new row is needed (it's Sunday, which is index 0 in the PHP output's table columns)
        if day_counter % 7 == 0 and current_date != start_date:
            html_rows += '\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n'
            
        # The color class will be dynamically applied by big.php. Use a default.
        # Using a default class of 'day' or 'red'/'blue' based on the old good sample. 
        # I'll use a placeholder 'day' which big.php can override.
        day_class = "day" 
        
        # Determine the day text: MonthName DayOfMonth (only for the first day of the month) or just DayOfMonth
        dom_text = str(current_date.day)
        if current_date.day == 1:
            dom_text = f"{MONTH_NAMES[current_date.month]} {current_date.day}"

        # Inner table structure based on the known_good_2019_planning.php (with three rows)
        html_rows += f'\t\t\t\t\t\t<td class="{day_class}">\n'
        html_rows += '\t\t\t\t\t\t\t<table class="inner">\n' 
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += f'\t\t\t\t\t\t\t\t\t<td class="dom">{dom_text}</td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        # PHP call to fill in events
        html_rows += f'\t\t\t\t\t\t\t\t<td class="event"> <?php fillIn({current_date.month}, {current_date.day}, {year}); ?></td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        # Extra row from the good planning file sample
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t\t<td class="info"></td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t</table>\n'
        html_rows += '\t\t\t\t\t\t</td>\n'
        
        current_date += timedelta(days=1)
        day_counter += 1

    # 4. Fill in remaining empty cells at the end of the last week
    last_day_of_week = (day_counter - 1) % 7 # The last day's position (0-6)
    if last_day_of_week != 6: # If the last day wasn't Saturday
        for i in range(last_day_of_week, 6):
            html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'
            
    # End the last week row
    html_rows += '\t\t\t\t\t</tr>\n'
    
    return html_rows

# 3) FIX: Updated generate_planning_php to use the new grid function
def generate_planning_php(year, kennel_run_numbers):
    php_content = HTML_HEAD_PLANNING.format(year=year)
    
    # NEW: Generate the full year grid
    php_content += generate_year_grid_for_planning(year)
    
    php_content += HTML_FOOTER_PLANNING

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

    tsv_content = generate_tsv_events(month, year, kennel_run_numbers)
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
    os.makedirs(os.path.dirname(tsv_file_path), exist_ok=True)
    with open(tsv_file_path, 'w') as tsv_file:
        tsv_file.write(tsv_content)

    # Generate PHP file content
    month_name = MONTH_NAMES[month]
    year_short = year % 100
    
    moon_name = MOON_NAMES.get(month, month_name) # Fallback to month name if not found
    image_file = f"month-{str(month).zfill(2)}.png"
    
    php_head = HTML_HEAD_MONTH.format(
        month_name=month_name,
        month=month, 
        year=year,
        year_short=year_short,
        image_file=image_file, # UPDATED IMAGE FILE
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