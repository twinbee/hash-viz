import os
import calendar
from datetime import datetime, timedelta
import argparse
from datetime import datetime, timezone
from datetime import date # Added for get_day_of_week helper

# Specification block for initial date and run numbers for each kennel
kennel_specs = {
    "Dallas Hash": {"initial_date": datetime(2024, 1, 6), "run_number": 1151},
    "Ft Worth Hash": {"initial_date": datetime(2024, 1, 13), "run_number": 999},
    "Dallas Urban Hash": {"initial_date": datetime(2024, 1, 3), "run_number": 731},
    "NODUH Hash": {"initial_date": datetime(2024, 1, 8), "run_number": 319},
    "YAKH3": {"initial_date": datetime(2024, 6, 2), "run_number": 1},  # First Sunday in summer
    "Full Moon Hash": {"initial_date": datetime(2024, 1, 25), "run_number": 63}  # Reference date for calculation
}

# Hashcash and schedule rules for each kennel
kennel_rules = {
    "Dallas Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$10.00 - Pay Online: Paypal $10", "day": "Saturday"},
    "Ft Worth Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$7.00 cash - Paypal $7 - Pay pal (FWH3) or Zelle 817-689-9363 - BYOB pre-lube beer", "day": "Saturday"},
    "Dallas Urban Hash": {"frequency": "weekly", "time": "6:30 PM", "hashcash": "", "day": "Wednesday"},
    "NODUH Hash": {"frequency": "bi-weekly", "time": "7:00 PM", "hashcash": "$7.00", "day": "Monday"},
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
    return f"Calendar Icons-{str(month).zfill(2)}.png"

# Storing the icon information for each kennel
kennel_icons = {
    "Dallas Urban Hash": "DUMB.png",
    "NODUH Hash": "NoDHHH2.png",
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
    while next_event < current_date:
        next_event += delta
    return next_event

# Function to generate TSV event data for each month based on the rules
def generate_tsv_events(month, year, kennel_run_numbers):
    events = []
    start_of_month = datetime(year, month, 1)
    end_of_month = datetime(year, month, calendar.monthrange(year, month)[1])

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
                        event = {
                            "day": next_event.day,
                            "kennel": kennel,
                            "title": "Full Moon Hash",
                            "run": run_number,
                            "hares": "TBD",
                            "time": "7:00 PM (time may vary)",
                            "start": f"Location TBD for {kennel}",
                            "map": "",
                            "hashcash": rule["hashcash"],
                            "turds": "Yes",
                            "tweet": "",
                            "twilight": "",
                            "date": next_event,
                            "desc": f"Full Moon Hash Run #{run_number}",
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

            # 1. Skip if the kennel is seasonal and it's out of season
            if rule["frequency"] == "summer-sundays" and current_date.month not in [6, 7, 8]:
                current_date += timedelta(days=1)
                continue

            # 2. Skip if the current date is NOT the day of the week specified in the rule
            if kennel_day_of_week is not None and current_date.weekday() != kennel_day_of_week:
                current_date += timedelta(days=1)
                continue
            # End checks

            # If we reach here, the date is the correct day of the week AND in season (if applicable).
            next_event = calculate_next_event(kennel, start_date, current_date, rule["frequency"])

            # Check if the calculated next event falls exactly on the current date
            if next_event and next_event.date() == current_date.date() and not any(e['date'].date() == next_event.date() and e['kennel'] == kennel for e in events):
                event = {
                    "day": next_event.day,
                    "kennel": kennel,
                    "title": "",
                    "run": run_number,
                    "hares": "TBD",
                    "time": rule["time"],
                    "start": f"Location TBD for {kennel}",
                    "map": "",
                    "hashcash": rule["hashcash"],
                    "turds": "Yes" if kennel != "YAKH3" else "No",
                    "tweet": "",
                    "twilight": "",
                    "date": next_event.replace(hour=0, minute=0, second=0, microsecond=0), # Keep only the date part
                    "desc": f"Event for {kennel}",
                    "update": next_event.strftime("%m/%d/%Y %H:%M")
                }
                events.append(event)
                run_number += 1  # Increment the run number after each event

            # Increment the current date after processing for this kennel
            current_date += timedelta(days=1)

        # Update the global run number for this kennel
        kennel_run_numbers[kennel] = run_number

    # Sort events by date
    events.sort(key=lambda x: x['date'])
    return events

# Function to generate TSV format content (Android .txt file)
def generate_tsv_file(month, year, events):
    header = "DAY\tKENNEL\tICON\tTITLE\tRUN\tHARES\tTIME\tSTART\tMAP\tHASHCASH\tTURDS\tTWEET\tTWILIGHT\tDATE\tDESC\tUPDATE"
    rows = [header]

    # Capture the current UTC time for the "UPDATE" column
    current_utc_time = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S %Z")

    for event in events:
        # Retrieve the correct icon for the kennel
        icon_source = kennel_icons.get(event['kennel'], "")
        
        # If the icon source is the function (for Full Moon Hash), call it with the month
        if callable(icon_source):
            icon = icon_source(event['date'].month)
        else:
            icon = icon_source

        # Use the correct date format for TSV
        date_str = event['date'].strftime('%A, %B %d, %Y')
        # BUMPED REVISION TO 1.6
        update_info = f"(calgen 1.6) {current_utc_time}"

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
    # Returns 0 for Monday, 6 for Sunday
    first_day_of_week = date(year, month, 1).weekday()
    num_days = calendar.monthrange(year, month)[1]

    # Adjust for PHP/HTML calendar start (Sunday = first column)
    # Python weekday (0=Mon, 6=Sun) -> HTML column index (0=Sun, 6=Sat)
    start_offset = (first_day_of_week + 1) % 7

    html = ""
    current_day = 1
    day_of_week = 0 # 0 for Sunday column, 6 for Saturday column

    # First row: starting blanks
    html += "\t\t\t\t\t<tr >\n"
    for i in range(start_offset):
        html += "\t\t\t\t\t\t<td class=\"empty\"></td>\n"
        day_of_week += 1

    # Loop through all days of the month
    while current_day <= num_days:
        if day_of_week == 7:
            html += "\t\t\t\t\t</tr>\n"
            html += "\t\t\t\t\t<tr >\n"
            day_of_week = 0

        # Build the PHP call string
        php_call = f"<?php fillIn({month}, {current_day}, {year}); ?>"

        # Use month-1 for the JavaScript ID as d.getMonth() returns 0-11
        js_id_month = month - 1

        # Generate the table cell HTML for the day
        cell_html = f"""\t\t\t\t\t\t<td class=\"day\">\n\t\t\t\t\t\t\t<table class=\"inner\" id=\"j{js_id_month}{current_day}\">\n\t\t\t\t\t\t\t\t<tr>\n\t\t\t\t\t\t\t\t\t<td class=\"dom\">{current_day}</td>\n\t\t\t\t\t\t\t\t</tr>\n\t\t\t\t\t\t\t\t<tr>\n\t\t\t\t\t\t\t\t\t<td class=\"event\">\n\t\t\t\t\t\t\t\t\t\t{php_call}\n\t\t\t\t\t\t\t\t\t</td>\n\t\t\t\t\t\t\t\t</tr>\n\t\t\t\t\t\t\t</table>\n\t\t\t\t\t\t</td>\n"""

        html += cell_html

        current_day += 1
        day_of_week += 1

    # Last row: ending blanks
    while day_of_week < 7:
        html += "\t\t\t\t\t\t<td class=\"empty\"></td>\n"
        day_of_week += 1

    html += "\t\t\t\t\t</tr>\n"

    return html

# Function to generate a PHP calendar file template for a specific month and year
def generate_php_file(month, year, previous_month_link, next_month_link):
    month_name = calendar.month_name[month]
    month_image = f"month-{str(month).zfill(2)}.png"
    year_suffix = str(year)[-2:]

    # JS/CSS logic, including the today highlight and nav window
    js_logic = f"""
<script language="JavaScript">
// script to highlight todays date via style override
var d = new Date();
var id = "j" + d.getMonth() + d.getDate();
      if (d.getYear() % 100 == {year_suffix}) document.write('<style type="text/css" media="screen"></style>');

			// script to open navagation window
			function openNav() {{
			window.open("/calendar/Nav/index.html", "nav", "width=320, height=1040, top=0, left=0");
			}}
		</script>
"""

    nav_footer = """
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
		</div> </td>
		</tr>
"""

    php_content = f"""<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
<title>{month_name}, {year} Hash Events</title>
<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />
{js_logic}
<?php
	$year={year};
	$month={month};
	include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
	<area shape="rect" coords="0,0,150,91" href="{previous_month_link}" alt="Previous Month" />
	<area shape="rect" coords="957,0,807,91" href="{next_month_link}" alt="Next Month" />
</map>
<div class=container>
	<table class="overall"  border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td>
				<table class="banner" border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><img src="{month_image}" alt="{month_name}, {year}"  border="0" usemap="#Map"/></td>
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
{generate_event_rows(month, year)}
				</table>
			</td>
		</tr>
{nav_footer}
	</table>
</div>
</body>
</html>
"""
    return php_content


# =========================================================================
# REWRITTEN FUNCTION: generate_planning_php (REVISION 1.6)
# Fixes the layout to match the known_good_2019_planning.php (single year-long table)
# =========================================================================

# Helper function for a single day cell in the yearly planning view
def generate_yearly_day_cell(month_name, day_of_month, month_num, year):
    # Heuristic for color coding (alternating month colors: Odd=blue, Even=red)
    day_class = "blue" if month_num % 2 != 0 else "red"
    php_call = f"<?php fillIn({month_num}, {day_of_month}, {year}); ?>"
    
    # Structure matches the layout of the old generator for a single day cell
    return f"""\t\t\t\t\t\t<td class="{day_class}">
\t\t\t\t\t\t\t<table class="inner">
\t\t\t\t\t\t\t\t<tr>
\t\t\t\t\t\t\t\t\t<td class="dom">{month_name}  {day_of_month}</td>
\t\t\t\t\t\t\t\t</tr>
\t\t\t\t\t\t\t\t<tr>
\t\t\t\t\t\t\t\t<td class="event"> {php_call}</td>
\t\t\t\t\t\t\t\t</tr>
\t\t\t\t\t\t\t\t<tr>
\t\t\t\t\t\t\t\t\t<td class="info"></td>
\t\t\t\t\t\t\t\t</tr>
\t\t\t\t\t\t\t</table>
\t\t\t\t\t\t</td>
"""

# NEW FUNCTION: Generate the full-year planning.php file
def generate_planning_php(year):
    
    # 1. Start the main HTML template (copied from known_good_2019_planning.php)
    html_content = f"""<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtmltransitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
\t<meta http-equiv="pragma" content="no-cache" />
\t<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
\t<meta http-equiv="content-type" content="text/html;charset=utf-8" />
\t
\t<title>{year} Planning Calendar</title>
\t<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />
\t
\t
\t<?php
\t$year={year};
\tinclude 'big.php';
\t?>
\t
</head>
\t
<body>
\t
<div class=container>
\t<table class="overall"  border="0" cellspacing="0" cellpadding="0">
\t\t<tr>
\t\t\t<td>
\t\t\t\t<table class="banner" border="0" cellspacing="0" cellpadding="0">
\t\t\t\t\t<tr>
\t\t\t\t\t\t<td><img src="planning.png" alt=""  border="0" usemap="#Map"/></td>
\t\t\t\t\t</tr>
\t\t\t\t</table>
\t\t\t</td>
\t\t</tr>
\t\t<tr>
\t\t\t<td>
\t\t\t\t<table class="main"  border="0" cellspacing="0" cellpadding="0">
\t\t\t\t\t<tr >
\t\t\t\t\t\t<th>Sunday</th>
\t\t\t\t\t\t<th>Monday</th>
\t\t\t\t\t\t<th>Tuesday</th>
\t\t\t\t\t\t<th>Wednesday</th>
\t\t\t\t\t\t<th>Thursday</th>
\t\t\t\t\t\t<th>Friday</th>
\t\t\t\t\t\t<th>Saturday</th>
\t\t\t\t\t</tr>
"""
    
    # 2. Sequential Day Generation Logic
    
    start_date = datetime(year, 1, 1)
    # Python weekday: (date.weekday() + 1) % 7 gives 0=Sunday for use in HTML table (0=Sun, 6=Sat)
    day_of_week_index = (start_date.weekday() + 1) % 7 
    
    num_days_in_year = 366 if calendar.isleap(year) else 365
    current_date = start_date
    html_rows = ""

    # Start the first row
    html_rows += "\t\t\t\t\t<tr>\n"
    
    # Add initial empty cells until the first day of the year (Jan 1)
    for _ in range(day_of_week_index):
        html_rows += "\t\t\t\t\t\t<td class=\"empty\"></td>\n"

    # Iterate through all days in the year
    for day_of_year in range(num_days_in_year):
        
        # Check if the current date is Sunday (Python's weekday: 6) and it's not the very first day
        if current_date.weekday() == 6 and day_of_year > 0: 
            html_rows += "\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n"
            
        # Generate the cell
        html_rows += generate_yearly_day_cell(
            MONTH_NAMES[current_date.month],
            current_date.day,
            current_date.month,
            year
        )
        
        # Advance to the next day
        current_date += timedelta(days=1)
        
    # Close the last row
    
    # Get the day index for the day *after* the last day in the year (0=Sun, 6=Sat)
    last_day_of_week_index = (current_date.weekday() + 1) % 7
    
    if last_day_of_week_index != 0:
        # Number of empty cells needed to complete the row
        empty_cells_needed = 7 - last_day_of_week_index
        for _ in range(empty_cells_needed):
            html_rows += "\t\t\t\t\t\t<td class=\"empty\"></td>\n"

    html_rows += "\t\t\t\t\t</tr>\n" # Close the last row
    
    html_content += html_rows

    # 3. End the HTML template
    html_content += f"""\t\t\t\t</table>
\t\t\t</td>
\t\t</tr>
\t</table>
</div>
</body>
</html>
"""

    file_path = f"calendar/planning.php"
    os.makedirs(os.path.dirname(file_path), exist_ok=True)
    with open(file_path, 'w') as f:
        f.write(html_content)
        
    return file_path


# Function to generate both TSV and PHP files for a month
def generate_files_for_month(month, year, kennel_run_numbers):
    # Generate previous and next month links for PHP
    previous_month = (month - 1) if month > 1 else 12
    previous_year = year if month > 1 else year - 1
    next_month = (month + 1) if month < 12 else 1
    next_year = year if month < 12 else year + 1

    # --- FIX: ADD $ PREFIX AND USE HYPHEN IN FILENAMES/LINKS (from previous turn) ---
    
    # Navigation links with the '$' prefix and HYPHEN
    previous_month_link = f"../{previous_year}/${str(previous_month).zfill(2)}-{previous_year}.php"
    next_month_link = f"../{next_year}/${str(next_month).zfill(2)}-{next_year}.php"

    # kennel_run_numbers is updated by generate_tsv_events to maintain run numbers across months
    events = generate_tsv_events(month, year, kennel_run_numbers)

    # Generate TSV (Android) file
    tsv_content = generate_tsv_file(month, year, events)
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
    os.makedirs(os.path.dirname(tsv_file_path), exist_ok=True)
    with open(tsv_file_path, 'w') as tsv_file:
        tsv_file.write(tsv_content)

    # Generate PHP file with the '$' prefix and HYPHEN
    php_content = generate_php_file(month, year, previous_month_link, next_month_link)
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
        php_file_path, tsv_file_path = generate_files_for_month(month, year, kennel_run_numbers)

    # Generate the yearly planning file after all monthly events have been calculated
    print(f"Generating full-year planning file...")
    planning_file_path = generate_planning_php(year)
    print(f"Generated: {planning_file_path}")


if __name__ == "__main__":
    # Argument parser to get year input from the command line
    parser = argparse.ArgumentParser(description="Generate calendar and android files for the given year.")
    parser.add_argument("year", type=int, help="The year for which to generate the files (e.g., 2038).")
    args = parser.parse_args()

    # Generate files for the input year
    if args.year < 2000 or args.year > 2100:
        print("Error: Please specify a year between 2000 and 2100.")
    else:
        generate_files_for_year(args.year)
        print(f"Successfully generated all files for {args.year}.")