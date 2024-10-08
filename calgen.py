import os
import calendar
from datetime import datetime, timedelta
import argparse
from datetime import datetime, timezone

# Specification block for initial date and run numbers for each kennel
kennel_specs = {
    "Dallas Hash": {"initial_date": datetime(2024, 1, 6), "run_number": 1151},
    "Ft Worth Hash": {"initial_date": datetime(2024, 1, 13), "run_number": 999},
    "Dallas Urban Hash": {"initial_date": datetime(2024, 1, 3), "run_number": 731},
    "NODUH Hash": {"initial_date": datetime(2024, 1, 8), "run_number": 319},
    "YAKH3": {"initial_date": datetime(2024, 6, 2), "run_number": 1},  # First Sunday in summer
    "Full Moon Hash": {"initial_date": datetime(2024, 1, 25), "run_number": 63}  # Placeholder for full moon logic
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

# Function to calculate next event based on frequency
def calculate_next_event(kennel, start_date, current_date, frequency):
    if frequency == "weekly":
        delta = timedelta(weeks=1)
    elif frequency == "bi-weekly":
        delta = timedelta(weeks=2)
    elif frequency == "summer-sundays" and current_date.month in [6, 7, 8]:
        delta = timedelta(weeks=4)  # First Sunday logic
    elif frequency == "full-moon":
        return None  # Placeholder for full-moon logic
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
        run_number = kennel_run_numbers[kennel]  # Continue from the last run number
        rule = kennel_rules[kennel]
        
        current_date = start_of_month
        while current_date <= end_of_month:
            next_event = calculate_next_event(kennel, start_date, current_date, rule["frequency"])
            
            # Ensure we only add one event per date per kennel
            if next_event and start_of_month <= next_event <= end_of_month and not any(e['date'] == next_event and e['kennel'] == kennel for e in events):
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
                    "date": next_event,
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

# Function to generate TSV format content ("android" .txt file)
def generate_tsv_file(month, year, events):
    header = "DAY\tKENNEL\tICON\tTITLE\tRUN\tHARES\tTIME\tSTART\tMAP\tHASHCASH\tTURDS\tTWEET\tTWILIGHT\tDATE\tDESC\tUPDATE"
    rows = [header]

    # Capture the current UTC time for the "UPDATE" column
    current_utc_time = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S %Z")

    for event in events:
        update_info = f"(calgen 1.0) {current_utc_time}"
        row = f"{event['day']}\t{event['kennel']}\t{event.get('icon', '')}\t{event['title']}\t{event['run']}\t{event['hares']}\t{event['time']}\t{event['start']}\t{event['map']}\t{event['hashcash']}\t{event['turds']}\t{event['tweet']}\t{event['twilight']}\t{event['date'].strftime('%A, %B %d, %Y')}\t{event['desc']}\t{update_info}"
        rows.append(row)
    
    return "\n".join(rows)

# Function to generate a PHP calendar file template for a specific month and year
def generate_php_file(month, year, previous_month_link, next_month_link):
    # Define the month name and month image
    month_name = calendar.month_name[month]
    month_image = f"month-{str(month).zfill(2)}.png"
    
    # HTML and PHP content for the month
    php_content = f"""<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<title>{month_name}, {year} Hash Events</title>
<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />
<script language="JavaScript">
var d = new Date();
var id = "j" + d.getMonth() + d.getDate();
if (d.getYear() % 100 == {str(year)[-2:]}) document.write('<style type="text/css" media="screen"><!-- table.inner#' + id + ' {{ background-image: url(throb.gif); }}--> </style>');
</script>
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
    <table class="overall" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="{month_image}" alt="{month_name}, {year}" border="0" usemap="#Map" /></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table class="main" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <th>Sunday</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
                    <!-- Events will go here -->
                    {generate_event_rows(month, year)}
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
"""
    return php_content

# Function to generate event rows for the PHP file
def generate_event_rows(month, year):
    first_day_of_week, days_in_month = calendar.monthrange(year, month)  # 0=Monday, 6=Sunday
    rows = []
    current_row = '<tr>\n'
    
    # Add empty cells before the first day of the month
    for _ in range(first_day_of_week):
        current_row += '\t<td class="empty"></td>\n'

    day_counter = 1

    # Fill in the rest of the days for the current row
    while day_counter <= days_in_month:
        current_row += f'''\t<td class="day">
                            <table class="inner" id="j{month}{str(day_counter).zfill(2)}">
                                <tr>
                                    <td class="dom">{day_counter}</td>
                                </tr>
                                <tr>
                                    <td class="event">
                                        <?php fillIn({month}, {day_counter}, {year}); ?>
                                    </td>
                                </tr>
                            </table>
                        </td>\n'''
        day_counter += 1

        # If the current row is full (7 columns), close it and start a new row
        if (first_day_of_week + day_counter - 1) % 7 == 0 or day_counter > days_in_month:
            current_row += '</tr>\n'
            rows.append(current_row)
            current_row = '<tr>\n'

    # If there are remaining cells in the last row, fill them with empty cells
    if current_row != '<tr>\n':  # Check if the last row has content
        while (first_day_of_week + day_counter - 1) % 7 != 0:
            current_row += '\t<td class="empty"></td>\n'
            day_counter += 1
        current_row += '</tr>\n'
        rows.append(current_row)

    return "".join(rows)

# Function to generate both TSV and PHP files for a month
def generate_files_for_month(month, year, kennel_run_numbers):
    # Generate previous and next month links for PHP
    previous_month = (month - 1) if month > 1 else 12
    previous_year = year if month > 1 else year - 1
    next_month = (month + 1) if month < 12 else 1
    next_year = year if month < 12 else year + 1

    previous_month_link = f"$calendar/{previous_year}/{str(previous_month).zfill(2)}_{previous_year}.php"
    next_month_link = f"$calendar/{next_year}/{str(next_month).zfill(2)}_{next_year}.php"

    events = generate_tsv_events(month, year, kennel_run_numbers)

    # Generate TSV (Android) file
    tsv_content = generate_tsv_file(month, year, events)
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
    os.makedirs(os.path.dirname(tsv_file_path), exist_ok=True)
    with open(tsv_file_path, 'w') as tsv_file:
        tsv_file.write(tsv_content)

    # Generate PHP file
    php_content = generate_php_file(month, year, previous_month_link, next_month_link)
    php_file_path = f"calendar/{year}/$calendar_{str(month).zfill(2)}_{year}.php"
    os.makedirs(os.path.dirname(php_file_path), exist_ok=True)
    with open(php_file_path, 'w') as php_file:
        php_file.write(php_content)

    return php_file_path, tsv_file_path

# Main function to generate files for an entire year
def generate_files_for_year(year):
    # Initialize run numbers for each kennel
    kennel_run_numbers = {kennel: spec["run_number"] for kennel, spec in kennel_specs.items()}

    for month in range(1, 13):
        php_file_path, tsv_file_path = generate_files_for_month(month, year, kennel_run_numbers)
        print(f"Generated: {php_file_path} and {tsv_file_path}")

if __name__ == "__main__":
    # Argument parser to get year input from the command line
    parser = argparse.ArgumentParser(description="Generate calendar and android files for the given year.")
    parser.add_argument("year", type=int, help="The year for which to generate the files (e.g., 2038).")
    args = parser.parse_args()

    # Generate files for the input year
    generate_files_for_year(args.year)
