# cal_genfiles.py

import os
import calendar
from datetime import datetime, timedelta, timezone

from cal_constants import (
    KENNEL_SPECS, KENNEL_RULES, KENNEL_ICONS, DAY_MAP, MONTH_NAMES, 
    HTML_HEAD_PLANNING, HTML_FOOTER_PLANNING, HTML_HEAD_MONTH, HTML_FOOTER_MONTH
)
from cal_calc import (
    calculate_full_moons_for_year, calculate_sunset_time_dallas, 
    calculate_next_event, get_special_dates_for_year
)


def generate_tsv_events(month, year, kennel_run_numbers):
    events = []
    start_of_month = datetime(year, month, 1)
    end_of_month = datetime(year, month, calendar.monthrange(year, month)[1])

    current_utc_time = datetime.now(timezone.utc).strftime('%m/%d/%Y %H:%M UTC')
    temp_run_numbers = kennel_run_numbers.copy() 

    for kennel, spec in KENNEL_SPECS.items():
        start_date = spec["initial_date"]
        run_number = temp_run_numbers[kennel]
        rule = KENNEL_RULES[kennel]

        # --- Full Moon Hash Logic ---
        if rule["frequency"] == "full-moon":
            full_moon_dates = calculate_full_moons_for_year(year)

            for fm_month, fm_day in full_moon_dates:
                if fm_month == month:
                    next_event = datetime(year, fm_month, fm_day, 19, 0, 0)
                    if start_of_month.date() <= next_event.date() <= end_of_month.date():
                        sunset_time_str = calculate_sunset_time_dallas(next_event) 
                        event = {
                            "day": next_event.day, "kennel": kennel, "title": "",
                            "run": run_number, "hares": "", "time": rule["time"],
                            "start": "", "map": "", "hashcash": rule["hashcash"],
                            "turds": "", "tweet": "", "twilight": sunset_time_str, 
                            "date": next_event, "desc": "", 
                            "update": next_event.strftime("%m/%d/%Y %H:%M")
                        }
                        events.append(event)
                        run_number += 1
            temp_run_numbers[kennel] = run_number
            continue
            
        # --- 7-ELEVEn Hash Logic (fixed-dates) ---
        if rule["frequency"] == "fixed-dates":
            # The fixed dates are 7/11 and 11/7 in the target year
            fixed_dates_in_year = [datetime(year, 7, 11), datetime(year, 11, 7)]
            
            # Filter for events that are in the current month AND on or after the kennel's start_date
            fixed_events_in_month = []
            for d in fixed_dates_in_year:
                if start_of_month.date() <= d.date() <= end_of_month.date():
                    # Only include runs that are on or after the initial start date to manage run numbering continuity
                    if d.date() >= start_date.date():
                        fixed_events_in_month.append(d)
            
            for next_event in fixed_events_in_month:
                sunset_time_str = calculate_sunset_time_dallas(next_event) 
                
                event = {
                    "day": next_event.day, "kennel": kennel, "title": "",
                    "run": run_number, "hares": "", "time": rule["time"],
                    "start": "", "map": "", "hashcash": rule["hashcash"],
                    "turds": "", "tweet": "", "twilight": sunset_time_str, 
                    "date": next_event, "desc": "", 
                    "update": next_event.strftime("%m/%d/%Y %H:%M")
                }
                events.append(event)
                run_number += 1
            
            temp_run_numbers[kennel] = run_number
            continue

        # --- Regular Event Logic ---
        current_date = start_of_month
        kennel_day_name = rule["day"]
        kennel_day_of_week = DAY_MAP.get(kennel_day_name)
        
        # Move current_date to the first correct weekday in the month for iteration
        days_to_add = (kennel_day_of_week - current_date.weekday() + 7) % 7
        current_date += timedelta(days=days_to_add)

        while current_date <= end_of_month:
            # Check if this date is on or after the kennel's initial date
            if current_date.date() < start_date.date():
                # Skip dates before the kennel's initial date for this year
                if rule["frequency"] == "weekly":
                    current_date += timedelta(weeks=1)
                else:
                    current_date += timedelta(days=7)
                continue
                
            expected_event_date_dt = calculate_next_event(kennel, start_date, current_date, rule["frequency"])

            if expected_event_date_dt and expected_event_date_dt.date() == current_date.date():
                time_str = rule["time"]
                sunset_time_str = calculate_sunset_time_dallas(datetime(year, month, current_date.day))

                event = {
                    "day": current_date.day, "kennel": kennel, "title": "",
                    "run": run_number, "hares": "", "time": time_str,
                    "start": "", "map": "", "hashcash": rule["hashcash"],
                    "turds": "", "tweet": "", "twilight": sunset_time_str, 
                    "date": datetime(year, month, current_date.day), "desc": "", 
                    "update": current_date.strftime("%m/%d/%Y %H:%M")
                }
                events.append(event)
                run_number += 1
                
                # Jump forward by the event interval
                if rule["frequency"] == "weekly":
                    current_date += timedelta(weeks=1)
                elif rule["frequency"] == "bi-weekly":
                    current_date += timedelta(weeks=2)
                elif rule["frequency"] in ["monthly", "summer-sundays"]:
                    current_date += timedelta(weeks=4)
                continue

            # Default to moving to the next relevant day
            if rule["frequency"] == "weekly":
                current_date += timedelta(weeks=1)
            else:
                # This ensures bi-weekly/4-weekly is checked correctly on the day itself
                current_date += timedelta(days=7) if current_date.weekday() == kennel_day_of_week else timedelta(days=1)
            

        temp_run_numbers[kennel] = run_number

    events.sort(key=lambda x: x["date"])

    rows = []
    header = ["day", "kennel", "icon", "title", "run", "hares", "time", "start", "map", "hashcash", "turds", "tweet", "twilight", "date", "desc", "update"]
    rows.append("\t".join(header))

    for event in events:
        icon_source = KENNEL_ICONS.get(event['kennel'], "")
        icon = icon_source(event['date'].month) if callable(icon_source) else icon_source
        date_str = event['date'].strftime('%A, %B %d, %Y')
        update_info = f"(calgen 1.7) {current_utc_time}"
        
        row = [
            str(event['day']), event['kennel'], icon, event['title'], str(event['run']),
            event['hares'], event['time'], event['start'], event['map'],
            event['hashcash'], event['turds'], event['tweet'], event['twilight'], 
            date_str, event['desc'], update_info
        ]
        rows.append("\t".join(row))

    return "\n".join(rows), temp_run_numbers

# --- PHP/HTML Grid Generation Functions ---

def generate_event_rows(month, year):
    """Generates the HTML table rows for a single month's calendar grid."""
    first_day_of_week = datetime(year, month, 1).weekday()
    num_days = calendar.monthrange(year, month)[1]
    php_first_day_of_week = (first_day_of_week + 1) % 7 # 0=Sun, 6=Sat
    
    # Get special dates for this year
    special_dates = get_special_dates_for_year(year)

    html_rows = "\t\t\t\t\t<tr>\n"

    for i in range(php_first_day_of_week):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'

    day_count = 1
    while day_count <= num_days:
        if (php_first_day_of_week + day_count - 1) % 7 == 0 and day_count > 1:
            html_rows += '\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n'

        js_id = f"j{month-1}{day_count}"
        
        # Check if this date is a special date
        date_key = (month, day_count)
        special_info = special_dates.get(date_key)
        
        # The outer td always has class="day"
        html_rows += f'\t\t\t\t\t\t<td class="day">\n'
        html_rows += f'\t\t\t\t\t\t\t<table class="inner" id="{js_id}">\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        
        # Only the dom td gets the holiday/blue_bar class and tag
        if special_info:
            info_text = special_info[0]
            dom_class = special_info[1]  # "holiday" or "blue_bar"
            html_rows += f'\t\t\t\t\t\t\t\t\t<td class="{dom_class}"><span class="tag">{info_text}</span>{day_count}</td>\n'
        else:
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


def load_previous_year_events(year):
    """Loads all events from the previous year's android TSV files."""
    previous_year = year - 1
    events_by_date = {}  # Key: (month, day), Value: list of (title, hares) tuples
    
    for month in range(1, 13):
        tsv_file_path = f"android/{previous_year}-{str(month).zfill(2)}.txt"
        if not os.path.exists(tsv_file_path):
            continue
            
        try:
            with open(tsv_file_path, 'r', encoding='utf-8', errors='replace') as f:
                lines = f.readlines()
                
            # Skip header line
            for line in lines[1:]:
                parts = line.strip().split('\t')
                if len(parts) >= 6:  # Ensure we have at least day, kennel, icon, title, run, hares
                    day = int(parts[0])
                    title = parts[3]  # title field
                    hares = parts[5]  # hares field
                    
                    if title:  # Only add if title is not empty
                        date_key = (month, day)
                        if date_key not in events_by_date:
                            events_by_date[date_key] = []
                        events_by_date[date_key].append((title, hares))
        except Exception as e:
            print(f"Warning: Could not read {tsv_file_path}: {e}")
            continue
    
    return events_by_date


def generate_year_grid_for_planning(year):
    """Generates the full-year daily grid for the planning.php file, marking holidays in red/blue."""
    special_dates = get_special_dates_for_year(year) 
    previous_year_events = load_previous_year_events(year)
    
    first_day_of_year = datetime(year, 1, 1).date()
    php_first_day_of_week = (first_day_of_year.weekday() + 1) % 7 # 0=Sun, 6=Sat
    
    start_date = datetime(year, 1, 1).date()
    end_date = datetime(year, 12, 31).date()
    
    html_rows = '\t\t\t\t\t<tr>\n'
    
    for i in range(php_first_day_of_week):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'
        
    day_counter = php_first_day_of_week
    current_date = start_date
    
    while current_date <= end_date:
        if day_counter % 7 == 0 and current_date != start_date:
            html_rows += '\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n'
            
        date_key = (current_date.month, current_date.day)
        special_info = special_dates.get(date_key)
        prev_year_events = previous_year_events.get(date_key, [])
        
        dom_text = str(current_date.day)
        if current_date.day == 1:
            dom_text = f"{MONTH_NAMES[current_date.month]} {current_date.day}"

        # The outer td always has class="day"
        html_rows += '\t\t\t\t\t\t<td class="day">\n'
        html_rows += '\t\t\t\t\t\t\t<table class="inner">\n' 
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        
        # Only the dom td gets the holiday/blue_bar class and tag
        if special_info:
            info_text = special_info[0]
            dom_class = special_info[1]  # "holiday" or "blue_bar"
            html_rows += f'\t\t\t\t\t\t\t\t\t<td class="{dom_class}"><span class="tag">{info_text}</span>{dom_text}</td>\n'
        else:
            html_rows += f'\t\t\t\t\t\t\t\t\t<td class="dom">{dom_text}</td>\n'

        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += f'\t\t\t\t\t\t\t\t<td class="event"> <?php fillIn({current_date.month}, {current_date.day}, {year}); ?></td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        
        # Add previous year events as small red text at the bottom in a separate row
        if prev_year_events:
            html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
            html_rows += '\t\t\t\t\t\t\t\t<td class="event" style="font-size: 8px; color: #cc0000; padding-top: 2px;">'
            for i, (title, hares) in enumerate(prev_year_events):
                if i > 0:
                    html_rows += '<br/>'
                # Format: "2025 - Title - Hares" or "2025 - Title" if no hares
                if hares:
                    html_rows += f'{year - 1} - {title} - {hares}'
                else:
                    html_rows += f'{year - 1} - {title}'
            html_rows += '</td>\n'
            html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        
        html_rows += '\t\t\t\t\t\t\t</table>\n'
        html_rows += '\t\t\t\t\t\t</td>\n'
        
        current_date += timedelta(days=1)
        day_counter += 1

    last_day_of_week = (day_counter - 1) % 7 
    if last_day_of_week != 6: 
        for i in range(last_day_of_week, 6):
            html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'
            
    html_rows += '\t\t\t\t\t</tr>\n'
    
    return html_rows


def generate_planning_php(year, kennel_run_numbers):
    """Generates the full-year planning.php file."""
    php_content = HTML_HEAD_PLANNING.format(year=year)
    php_content += generate_year_grid_for_planning(year)
    php_content += HTML_FOOTER_PLANNING

    planning_file_path = f"calendar/{year}/planning.php"
    os.makedirs(os.path.dirname(planning_file_path), exist_ok=True)
    with open(planning_file_path, 'w', encoding='utf-8') as php_file:
        php_file.write(php_content)
    
    return planning_file_path


def generate_files_for_month(month, year, kennel_run_numbers):
    """Generates the monthly PHP and TSV files."""
    
    # Calculate previous and next month/year
    prev_month, prev_year = (month - 1, year) if month > 1 else (12, year - 1)
    next_month, next_year = (month + 1, year) if month < 12 else (1, year + 1)
    
    # --- Fix for Navigation Links (Next/Previous Buttons) ---
    
    # Filenames, including the unusual '$' prefix
    prev_filename = f"${str(prev_month).zfill(2)}-{prev_year}.php"
    next_filename = f"${str(next_month).zfill(2)}-{next_year}.php"

    # Determine relative path: simple filename if in the same year directory, 
    # or '../{year}/{filename}' if crossing a year boundary.
    if prev_year == year:
        prev_month_link = prev_filename
    else:
        prev_month_link = f"../{prev_year}/{prev_filename}"

    if next_year == year:
        next_month_link = next_filename
    else:
        next_month_link = f"../{next_year}/{next_filename}"
    
    # --- End Fix ---

    # --- Generate TSV File ---
    tsv_content, updated_run_numbers = generate_tsv_events(month, year, kennel_run_numbers)
    
    # Update the passed-in dictionary with the new run numbers
    kennel_run_numbers.update(updated_run_numbers)
    
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
    os.makedirs(os.path.dirname(tsv_file_path), exist_ok=True)
    with open(tsv_file_path, 'w', encoding='utf-8') as tsv_file:
        tsv_file.write(tsv_content)

    # --- Generate PHP File ---
    month_name = MONTH_NAMES[month]
    year_short = year % 100
    
    # Use the banner image format month-01.png, etc.
    image_file = f"month-{str(month).zfill(2)}.png"
    
    php_head = HTML_HEAD_MONTH.format(
        month_name=month_name, month=month, year=year, year_short=year_short,
        image_file=image_file, prev_link=prev_month_link, next_link=next_month_link
    )
    
    php_rows = generate_event_rows(month, year)
    php_content = php_head + php_rows + HTML_FOOTER_MONTH
    
    # Note: The output filename contains the dollar sign to match the legacy file structure
    php_file_path = f"calendar/{year}/${str(month).zfill(2)}-{year}.php"
    os.makedirs(os.path.dirname(php_file_path), exist_ok=True)
    with open(php_file_path, 'w', encoding='utf-8') as php_file:
        php_file.write(php_content)

    return php_file_path, tsv_file_path


def generate_files_for_year(year):
    """Orchestrates the generation of all calendar files for a given year."""
    # Initialize run numbers for each kennel
    kennel_run_numbers = {kennel: spec["run_number"] for kennel, spec in KENNEL_SPECS.items()}

    print(f"Generating monthly files for {year}...")
    for month in range(1, 13):
        # generate_files_for_month updates kennel_run_numbers for the next month
        generate_files_for_month(month, year, kennel_run_numbers)

    print(f"Generating full-year planning file...")
    generate_planning_php(year, kennel_run_numbers)