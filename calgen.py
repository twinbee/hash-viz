import os
import calendar
from datetime import datetime, timedelta, date 
import argparse
from datetime import timezone
import sys 

# --- Configuration Blocks (Initial defaults - will be updated by CLI arguments) ---
kennel_specs = {
    "Dallas Hash": {"initial_date": datetime(2024, 1, 6, 14, 0), "run_number": 1151},
    "Ft Worth Hash": {"initial_date": datetime(2024, 1, 13, 14, 0), "run_number": 999},
    "Dallas Urban Hash": {"initial_date": datetime(2024, 1, 3, 18, 30), "run_number": 731},
    "NODUH Hash": {"initial_date": datetime(2024, 1, 8, 19, 0), "run_number": 319},
    "YAKH3": {"initial_date": datetime(2024, 6, 2, 12, 0), "run_number": 1},
    "Full Moon Hash": {"initial_date": datetime(2024, 1, 25, 0, 0), "run_number": 63} 
}

kennel_rules = {
    "Dallas Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$10.00 - Pay Online: Paypal $10", "day": "Saturday"},
    "Ft Worth Hash": {"frequency": "bi-weekly", "time": "2:00 PM", "hashcash": "$7.00 cash - Paypal $7 - Pay pal (FWH3) or Zelle 817-689-9363 - BYOB pre-lube beer", "day": "Saturday"},
    "Dallas Urban Hash": {"frequency": "weekly", "time": "6:30 PM", "hashcash": "", "day": "Wednesday"},
    "NODUH Hash": {"frequency": "bi-weekly", "time": "7:00 PM", "hashcash": "$7.00", "day": "Monday"},
    "YAKH3": {"frequency": "summer-sundays", "time": "12:00 PM", "hashcash": "", "day": "Sunday"},
    "Full Moon Hash": {"frequency": "full-moon", "time": "varies", "hashcash": "", "day": "full-moon"}
}

kennel_icons = {
    "Dallas Urban Hash": "DUMB.png",
    "NODUH Hash": "NoDHHH2.png",
    "Dallas Hash": "dallas.png",
    "Ft Worth Hash": "ftworth.png",
    "Full Moon Hash": "fullmoon.png"
}
# ---------------------------------------------------------------------------------


# Function to calculate next event based on frequency
def calculate_next_event(kennel, start_date, current_date, frequency):
    """Calculates the next event date for a given kennel."""
    delta = None
    if frequency == "weekly":
        delta = timedelta(weeks=1)
    elif frequency == "bi-weekly":
        delta = timedelta(weeks=2)
    elif frequency == "summer-sundays":
        if current_date.month in [6, 7, 8] and current_date.weekday() == 6: # 6 is Sunday
            # Use the start_date to determine *which* Sundays to run
            if current_date.date() >= start_date.date():
                # Check if it's on the correct weekly cycle from the start
                days_diff = (current_date.date() - start_date.date()).days
                if days_diff % 7 == 0: # It's on the right cycle
                     return current_date
            return None
        return None
    elif frequency == "full-moon":
        # Placeholder for full-moon logic. 
        return None

    if delta:
        next_event = start_date
        # Keep advancing the start_date until it's on or after the current_date
        while next_event.date() < current_date.date():
            next_event += delta
        
        # If the next event falls exactly on the current day, return it.
        if next_event.date() == current_date.date():
             return next_event
        
    return None

# Function to generate TSV event data for each month based on the rules
def generate_tsv_events(month, year, kennel_run_numbers, specs):
    events = []
    start_of_month = datetime(year, month, 1, 0, 0)
    days_in_month = calendar.monthrange(year, month)[1]
    
    # Create a list of all days in the month
    all_dates = [datetime(year, month, day, 0, 0) for day in range(1, days_in_month + 1)]

    for kennel, spec in specs.items():
        start_date = spec["initial_date"]
        run_number = kennel_run_numbers[kennel]
        rule = kennel_rules[kennel]
        
        for current_date_iterator in all_dates:
            # We must pass the time component from the original start_date
            event_time = datetime(
                current_date_iterator.year, 
                current_date_iterator.month, 
                current_date_iterator.day, 
                start_date.hour, 
                start_date.minute
            )
            
            next_event = calculate_next_event(kennel, start_date, event_time, rule["frequency"])
            
            if (next_event and next_event.date() == current_date_iterator.date() and 
                not any(e['date'].date() == next_event.date() and e['kennel'] == kennel for e in events)):

                # Calculate the run number for this specific event date
                current_run_number = 0
                if rule["frequency"] in ["weekly", "bi-weekly"]:
                    # Calculate weeks (or bi-weeks) passed since the initial date
                    time_diff = next_event.date() - start_date.date()
                    if rule["frequency"] == "weekly":
                        weeks_passed = time_diff.days // 7
                    else: # bi-weekly
                        weeks_passed = time_diff.days // 14
                    current_run_number = spec["run_number"] + weeks_passed
                
                elif rule["frequency"] == "summer-sundays":
                    # This is a simple counter just for this kennel for this year
                    current_run_number = run_number
                    run_number += 1 # Advance for the next event found
                
                else:
                    current_run_number = run_number # Fallback

                event = {
                    "day": next_event.day,
                    "kennel": kennel,
                    "title": "",
                    "run": current_run_number, # Use the calculated run number
                    "hares": "TBD",
                    "time": rule["time"],
                    "start": f"Location TBD for {kennel}",
                    "map": "",
                    "hashcash": rule["hashcash"],
                    "turds": "Yes" if kennel != "YAKH3" else "No",
                    "tweet": "",
                    "twilight": "",
                    "date": next_event,
                    "desc": f"Event for {kennel} Run #{current_run_number}",
                    "update": next_event.strftime("%m/%d/%Y %H:%M")
                }
                events.append(event)
                
    
    # Update run numbers for the next month based on events found
    for event in events:
        if event['run'] > 0: # Only update if a valid run number was calculated
            # Set the next run number to be one greater than the last one found this month
            kennel_run_numbers[event['kennel']] = max(kennel_run_numbers[event['kennel']], event['run'] + 1)
    
    events.sort(key=lambda x: x['date'])
    return events

# Function to generate TSV format content (Android .txt file)
def generate_tsv_file(month, year, events):
    header = "DAY\tKENNEL\tICON\tTITLE\tRUN\tHARES\tTIME\tSTART\tMAP\tHASHCASH\tTURDS\tTWEET\tTWILIGHT\tDATE\tDESC\tUPDATE"
    rows = [header]
    current_utc_time = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S %Z")

    for event in events:
        icon = kennel_icons.get(event['kennel'], "")
        update_info = f"(calgen 1.0) {current_utc_time}"
        row = f"{event['day']}\t{event['kennel']}\t{icon}\t{event['title']}\t{event['run']}\t{event['hares']}\t{event['time']}\t{event['start']}\t{event['map']}\t{event['hashcash']}\t{event['turds']}\t{event['tweet']}\t{event['twilight']}\t{event['date'].strftime('%A, %B %d, %Y')}\t{event['desc']}\t{update_info}"
        rows.append(row)
    
    return "\n".join(rows)

# Helper function to get the day of the week for a specific date
def get_day_of_week(year, month, day):
    # Returns 0 for Monday, 6 for Sunday
    return date(year, month, day).weekday()

# Function to generate event rows for the PHP file
def generate_event_rows(month, year):
    # Weekday: 0=Mon, 1=Tue, ..., 6=Sun
    first_day_of_week = get_day_of_week(year, month, 1)
    
    # Convert to Sunday-based offset (0=Sun, 1=Mon, ...)
    start_offset = (first_day_of_week + 1) % 7 
    
    days_in_month = calendar.monthrange(year, month)[1]
    rows = []
    current_row = '\t\t\t\t\t<tr>\n' # Match known good indentation
    
    # 1. Add empty cells before the first day of the month
    for _ in range(start_offset):
        current_row += '\t\t\t\t\t\t<td class="empty"></td>\n'

    day_counter = 1
    cell_counter = start_offset

    # 2. Fill in the rest of the days
    while day_counter <= days_in_month:
        # Check if a new row needs to start
        if cell_counter % 7 == 0: 
            # End the previous row and start a new one
            current_row += '\t\t\t\t\t</tr>\n\n\t\t\t\t\t<tr>\n' 
        
        # JavaScript ID uses 0-indexed month (e.g., 1 for Feb) and unpadded day
        js_month_id = month - 1 

        current_row += f'''\t\t\t\t\t\t<td class="day">
							<table class="inner" id="j{js_month_id}{day_counter}">
								<tr>
									<td class="dom">{day_counter}</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn({month}, {day_counter}, {year}); ?>
									</td>
								</tr>
							</table>
						</td>\n''' # Match known good indentation

        day_counter += 1
        cell_counter += 1

    # 3. GENERATE BLANKS UNTIL THE LAST ROW IS COMPLETE
    if cell_counter % 7 != 0:
        while cell_counter % 7 != 0:
            current_row += '\t\t\t\t\t\t<td class="empty"></td>\n'
            cell_counter += 1
    
    current_row += '\t\t\t\t\t</tr>\n'
    rows.append(current_row)

    return "".join(rows)


# Function to generate a PHP calendar file template for a specific month and year
def generate_php_file(month, year, previous_month_link, next_month_link):
    month_name = calendar.month_name[month]
    month_image = f"month-{str(month).zfill(2)}.png"
    year_suffix = str(year)[-2:]
    
    # --- FIX 1: Corrected JavaScript/CSS (includes throb.gif rule) ---
    # This ensures today's date is highlighted correctly (using the known good code)
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

    # --- FIX 2: Corrected Navigation Footer ---
    nav_footer = """
		<tr id="nav">
			<td>
			<div id="menu">
				<a href="/index.html">home</a>&nbsp;&nbsp;&nbsp;&nbsp;
				calendar&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Our_Idiots/index.html">our idiots</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Write-Ups/index.html">write-ups</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Gallery/index.html">gallery</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Links/index.html">links</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Road_Trip/index.html">road trip</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="planning.php">planning</a>&nbsp;&nbsp;&nbsp;&nbsp;
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
	include 'php.php'; // <<< REVERTED to original 'php.php'
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


# Function to generate both TSV and PHP files for a month
def generate_files_for_month(month, year, kennel_run_numbers, specs):
    previous_month = (month - 1) if month > 1 else 12
    previous_year = year if month > 1 else year - 1
    next_month = (month + 1) if month < 12 else 1
    next_year = year if month < 12 else year + 1

    # Corrected Navigation links (flat file names for same year, relative path for cross-year)
    if previous_year == year:
        # Correct same-year link (e.g., $01-2025.php)
        previous_month_link = f"${str(previous_month).zfill(2)}-{year}.php"
    else:
        # Correct cross-year link (e.g., ../2024/$12-2024.php)
        previous_month_link = f"../{previous_year}/${str(previous_month).zfill(2)}-{previous_year}.php"
        
    if next_year == year:
        next_month_link = f"${str(next_month).zfill(2)}-{year}.php"
    else:
        next_month_link = f"../{next_year}/${str(next_month).zfill(2)}-{next_year}.php"

    events = generate_tsv_events(month, year, kennel_run_numbers, specs)

    # Generate TSV (Android) file
    tsv_content = generate_tsv_file(month, year, events)
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
    os.makedirs(os.path.dirname(tsv_file_path), exist_ok=True)
    with open(tsv_file_path, 'w') as tsv_file:
        tsv_file.write(tsv_content)

    # Generate PHP file (using the requested $MM-YYYY.php file naming)
    php_content = generate_php_file(month, year, previous_month_link, next_month_link)
    php_file_name = f"${str(month).zfill(2)}-{year}.php" 
    php_file_path = f"calendar/{year}/{php_file_name}"
    
    os.makedirs(os.path.dirname(php_file_path), exist_ok=True)
    with open(php_file_path, 'w') as php_file:
        php_file.write(php_content)

    return php_file_path, tsv_file_path

def parse_date_arg(date_string):
    """Custom type function for argparse to parse YYYY-MM-DD or MM/DD/YYYY"""
    try:
        if '-' in date_string:
            return datetime.strptime(date_string, "%Y-%m-%d")
        elif '/' in date_string:
            return datetime.strptime(date_string, "%m/%d/%Y")
        else:
            raise ValueError
    except ValueError:
        raise argparse.ArgumentTypeError("Date must be in YYYY-MM-DD or MM/DD/YYYY format.")

# Main function to generate files for an entire year
def generate_files_for_year(year, specs):
    # Initialize run numbers for each kennel
    kennel_run_numbers = {kennel: spec["run_number"] for kennel, spec in specs.items()}

    for month in range(1, 13):
        php_file_path, tsv_file_path = generate_files_for_month(month, year, kennel_run_numbers, specs)
        print(f"Generated: {php_file_path} and {tsv_file_path}")

if __name__ == "__main__":
    default_year = datetime.now().year + 1
    
    parser = argparse.ArgumentParser(description="Generate calendar and android files for the given year.")
    parser.add_argument("year", 
                        type=int, 
                        nargs='?', 
                        default=default_year,
                        help=f"The year for which to generate the files (e.g., 2038). Defaults to {default_year}.")
    
    parser.add_argument("--dallas_start", 
                        type=parse_date_arg, 
                        help="Override the initial Dallas Hash Saturday run date (e.g., 2025-01-11 or 01/11/2025).")
    
    args = parser.parse_args()

    updated_specs = kennel_specs.copy()
    
    if args.dallas_start:
        dallas_start_date = args.dallas_start.replace(hour=14, minute=0)
        
        if dallas_start_date.weekday() != 5: # 5 is Saturday
            print(f"Error: The provided Dallas start date '{args.dallas_start.strftime('%Y-%m-%d')}' is not a Saturday. Aborting.", file=sys.stderr)
            sys.exit(1)
            
        print(f"Overriding Dallas Hash initial date with: {dallas_start_date.strftime('%Y-%m-%d')}")
        
        updated_specs["Dallas Hash"]["initial_date"] = dallas_start_date
        
        ft_worth_start_date = dallas_start_date + timedelta(days=7)
        updated_specs["Ft Worth Hash"]["initial_date"] = ft_worth_start_date.replace(hour=14, minute=0)
        
        print(f"Setting Fort Worth Hash initial date to opposite Saturday: {ft_worth_start_date.strftime('%Y-%m-%d')}")

    generate_files_for_year(args.year, updated_specs)