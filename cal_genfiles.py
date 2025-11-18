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


def load_existing_tsv(tsv_file_path):
    """
    Loads an existing TSV file and returns:
    - A dictionary mapping (day, kennel) to the full line
    - The header line
    - All lines in order
    """
    if not os.path.exists(tsv_file_path):
        return {}, None, []
    
    try:
        with open(tsv_file_path, 'r', encoding='utf-8', errors='replace') as f:
            lines = f.readlines()
        
        if not lines:
            return {}, None, []
        
        header = lines[0].strip()
        existing_events = {}
        
        for line in lines[1:]:
            parts = line.strip().split('\t')
            if len(parts) >= 2:  # At least day and kennel
                day = parts[0]
                kennel = parts[1]
                existing_events[(day, kennel)] = line.strip()
        
        return existing_events, header, lines
    except Exception as e:
        print(f"Warning: Could not read {tsv_file_path}: {e}")
        return {}, None, []


def show_diff(existing_line, new_line, day, kennel, month, year):
    """Displays the differences between existing and new event lines."""
    existing_parts = existing_line.split('\t')
    new_parts = new_line.split('\t')
    
    header = ["day", "kennel", "icon", "title", "run", "hares", "time", "start", 
              "map", "hashcash", "turds", "tweet", "twilight", "date", "desc", "update"]
    
    # Check if there are any differences (excluding the 'update' field which is index 15)
    has_real_diff = False
    for i, field_name in enumerate(header):
        if field_name == "update":  # Skip the update field
            continue
        existing_val = existing_parts[i] if i < len(existing_parts) else ""
        new_val = new_parts[i] if i < len(new_parts) else ""
        if existing_val != new_val:
            has_real_diff = True
            break
    
    # Only display diff if there are real differences (not just update field)
    if not has_real_diff:
        return False
    
    print(f"\n{'='*80}")
    print(f"DIFF DETECTED: {kennel} on day {day} ({month}/{day}/{year})")
    print(f"{'='*80}")
    
    max_field_len = max(len(h) for h in header)
    
    for i, field_name in enumerate(header):
        if field_name == "update":  # Skip displaying the update field
            continue
        existing_val = existing_parts[i] if i < len(existing_parts) else ""
        new_val = new_parts[i] if i < len(new_parts) else ""
        
        if existing_val != new_val:
            print(f"  {field_name.ljust(max_field_len)} | EXISTING: {existing_val}")
            print(f"  {' ' * max_field_len} | NEW:      {new_val}")
    
    print(f"{'='*80}\n")
    return True


def merge_tsv_events(month, year, kennel_run_numbers, clobber=False):
    """
    Generates events for the month and merges with existing TSV file.
    Only adds events that don't already exist (based on day + kennel).
    Shows diffs for events that do exist but have changed.
    
    Args:
        month: Month number (1-12)
        year: Year
        kennel_run_numbers: Dictionary of current run numbers for each kennel
        clobber: If True, overwrite existing events instead of preserving them
    """
    events = []
    start_of_month = datetime(year, month, 1)
    end_of_month = datetime(year, month, calendar.monthrange(year, month)[1])

    current_utc_time = datetime.now(timezone.utc).strftime('%m/%d/%Y %H:%M UTC')
    temp_run_numbers = kennel_run_numbers.copy()
    
    # Track which kennels generated events
    kennels_with_events = set()

    for kennel, spec in KENNEL_SPECS.items():
        start_date = spec["initial_date"]
        run_number = temp_run_numbers[kennel]
        rule = KENNEL_RULES[kennel]
        
        # Automatically adjust initial_date to the first matching day of week for regular events
        if rule["frequency"] in ["weekly", "bi-weekly", "monthly"]:
            expected_day = DAY_MAP.get(rule["day"])
            actual_day = start_date.weekday()
            if expected_day != actual_day:
                days_to_add = (expected_day - actual_day + 7) % 7
                if days_to_add == 0:
                    days_to_add = 7
                adjusted_date = start_date + timedelta(days=days_to_add)
                
                day_names = {v: k for k, v in DAY_MAP.items()}
                print(f"INFO: {kennel} initial_date {start_date.strftime('%Y-%m-%d')} ({day_names[actual_day]}) "
                      f"adjusted to {adjusted_date.strftime('%Y-%m-%d')} ({rule['day']}) to match schedule.")
                start_date = adjusted_date

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
                        kennels_with_events.add(kennel)
            temp_run_numbers[kennel] = run_number
            continue
        
        # --- YAKH3 Logic ---
        if rule["frequency"] == "yakh3-summer":
            april_first = datetime(year, 4, 1)
            days_to_sunday = (6 - april_first.weekday()) % 7
            first_sunday_april = april_first + timedelta(days=days_to_sunday)
            third_sunday_april = first_sunday_april + timedelta(weeks=2)
            
            sept_first = datetime(year, 9, 1)
            days_to_sunday = (6 - sept_first.weekday()) % 7
            first_sunday_sept = sept_first + timedelta(days=days_to_sunday)
            third_sunday_sept = first_sunday_sept + timedelta(weeks=2)
            
            current_event_date = third_sunday_april
            while current_event_date <= third_sunday_sept:
                if start_of_month.date() <= current_event_date.date() <= end_of_month.date():
                    if current_event_date.date() >= start_date.date():
                        sunset_time_str = calculate_sunset_time_dallas(current_event_date)
                        
                        event = {
                            "day": current_event_date.day, "kennel": kennel, "title": "",
                            "run": run_number, "hares": "", "time": rule["time"],
                            "start": "", "map": "", "hashcash": rule["hashcash"],
                            "turds": "", "tweet": "", "twilight": sunset_time_str, 
                            "date": current_event_date, "desc": "", 
                            "update": current_event_date.strftime("%m/%d/%Y %H:%M")
                        }
                        events.append(event)
                        run_number += 1
                        kennels_with_events.add(kennel)
                
                current_event_date += timedelta(weeks=2)
            
            temp_run_numbers[kennel] = run_number
            continue
        
        # --- YakH3-HH Logic ---
        if rule["frequency"] == "yakh3hh-summer":
            june_5 = datetime(year, 6, 5)
            sept_4 = datetime(year, 9, 4)
            
            current_event_date = june_5
            days_to_friday = (4 - current_event_date.weekday()) % 7
            if days_to_friday > 0:
                current_event_date += timedelta(days=days_to_friday)
            
            while current_event_date <= sept_4:
                if start_of_month.date() <= current_event_date.date() <= end_of_month.date():
                    if current_event_date.date() >= start_date.date():
                        sunset_time_str = calculate_sunset_time_dallas(current_event_date)
                        
                        event = {
                            "day": current_event_date.day, "kennel": kennel, "title": "",
                            "run": run_number, "hares": "", "time": rule["time"],
                            "start": "", "map": "", "hashcash": rule["hashcash"],
                            "turds": "", "tweet": "", "twilight": sunset_time_str, 
                            "date": current_event_date, "desc": "", 
                            "update": current_event_date.strftime("%m/%d/%Y %H:%M")
                        }
                        events.append(event)
                        run_number += 1
                        kennels_with_events.add(kennel)
                
                current_event_date += timedelta(weeks=4)
            
            temp_run_numbers[kennel] = run_number
            continue
        
        # --- Grapevine Quarterly Hash Logic ---
        if rule["frequency"] == "gqh-quarterly":
            quarterly_dates = [
                datetime(year, 3, 13),
                datetime(year, 6, 13),
                datetime(year, 9, 13),
                datetime(year, 12, 13)
            ]
            
            for quarterly_date in quarterly_dates:
                if start_of_month.date() <= quarterly_date.date() <= end_of_month.date():
                    if quarterly_date.date() >= start_date.date():
                        sunset_time_str = calculate_sunset_time_dallas(quarterly_date)
                        
                        event = {
                            "day": quarterly_date.day, "kennel": kennel, "title": "",
                            "run": run_number, "hares": "", "time": rule["time"],
                            "start": "", "map": "", "hashcash": rule["hashcash"],
                            "turds": "", "tweet": "", "twilight": sunset_time_str, 
                            "date": quarterly_date, "desc": "", 
                            "update": quarterly_date.strftime("%m/%d/%Y %H:%M")
                        }
                        events.append(event)
                        run_number += 1
                        kennels_with_events.add(kennel)
            
            temp_run_numbers[kennel] = run_number
            continue
            
        # --- 7-ELEVEn Hash Logic ---
        if rule["frequency"] == "fixed-dates":
            fixed_dates_in_year = [datetime(year, 7, 11), datetime(year, 11, 7)]
            
            fixed_events_in_month = []
            for d in fixed_dates_in_year:
                if start_of_month.date() <= d.date() <= end_of_month.date():
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
                kennels_with_events.add(kennel)
            
            temp_run_numbers[kennel] = run_number
            continue

        # --- Regular Event Logic ---
        current_date = start_of_month
        kennel_day_name = rule["day"]
        kennel_day_of_week = DAY_MAP.get(kennel_day_name)
        
        days_to_add = (kennel_day_of_week - current_date.weekday() + 7) % 7
        current_date += timedelta(days=days_to_add)

        while current_date <= end_of_month:
            if current_date.date() < start_date.date():
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
                kennels_with_events.add(kennel)
                
                if rule["frequency"] == "weekly":
                    current_date += timedelta(weeks=1)
                elif rule["frequency"] == "bi-weekly":
                    current_date += timedelta(weeks=2)
                elif rule["frequency"] in ["monthly", "summer-sundays"]:
                    current_date += timedelta(weeks=4)
                continue

            if rule["frequency"] == "weekly":
                current_date += timedelta(weeks=1)
            else:
                current_date += timedelta(days=7) if current_date.weekday() == kennel_day_of_week else timedelta(days=1)

        temp_run_numbers[kennel] = run_number

    events.sort(key=lambda x: x["date"])

    # Load existing TSV file
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
    existing_events, existing_header, existing_lines = load_existing_tsv(tsv_file_path)
    
    # Prepare new events as lines
    header = ["day", "kennel", "icon", "title", "run", "hares", "time", "start", "map", 
              "hashcash", "turds", "tweet", "twilight", "date", "desc", "update"]
    
    # Collect all events to write (both existing and new)
    all_events_dict = {}  # Key: (day, kennel), Value: line
    added_events = []  # List of (day, kennel, run, time) for added events
    updated_events = []  # List of events that were clobbered
    added_count = 0
    updated_count = 0
    diff_count = 0
    
    # First, add all existing events to the dictionary
    for key, line in existing_events.items():
        all_events_dict[key] = line
    
    # Now process new events
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
        new_line = "\t".join(row)
        
        key = (str(event['day']), event['kennel'])
        
        if key in existing_events:
            # Event exists - check if different (excluding update field)
            existing_line = existing_events[key]
            has_diff = show_diff(existing_line, new_line, event['day'], event['kennel'], month, year)
            
            if clobber:
                # Overwrite the existing event with new data
                all_events_dict[key] = new_line
                updated_events.append((event['day'], event['kennel'], event['run'], event['time'], date_str))
                updated_count += 1
            else:
                # Keep the existing line (don't overwrite)
                if has_diff:
                    diff_count += 1
        else:
            # New event - add it
            all_events_dict[key] = new_line
            added_events.append((event['day'], event['kennel'], event['run'], event['time'], date_str))
            added_count += 1
    
    # Sort all events by day (first element of key tuple, converted to int)
    sorted_keys = sorted(all_events_dict.keys(), key=lambda k: int(k[0]))
    
    # Write merged TSV file
    os.makedirs(os.path.dirname(tsv_file_path), exist_ok=True)
    with open(tsv_file_path, 'w', encoding='utf-8') as tsv_file:
        # Write header
        if existing_header:
            tsv_file.write(existing_header + "\n")
        else:
            tsv_file.write("\t".join(header) + "\n")
        
        # Write all events in sorted order by day
        for key in sorted_keys:
            tsv_file.write(all_events_dict[key] + "\n")
    
    if added_count > 0:
        print(f"✓ Added {added_count} new event(s) to {tsv_file_path}")
        for day, kennel, run, time, date_str in added_events:
            print(f"  • Day {day}: {kennel} #{run} at {time} ({date_str})")
    if updated_count > 0:
        print(f"✓ Updated {updated_count} existing event(s) in {tsv_file_path} (--clobber mode)")
        for day, kennel, run, time, date_str in updated_events:
            print(f"  • Day {day}: {kennel} #{run} at {time} ({date_str})")
    if diff_count > 0:
        print(f"⚠ Found {diff_count} existing event(s) with differences in {tsv_file_path}")
    if added_count == 0 and diff_count == 0 and updated_count == 0:
        print(f"✓ No changes needed for {tsv_file_path}")

    return temp_run_numbers


def generate_tsv_events(month, year, kennel_run_numbers, clobber=False):
    """Legacy function - now redirects to merge_tsv_events"""
    return "", merge_tsv_events(month, year, kennel_run_numbers, clobber)


def find_recommended_start_date(kennel, start_date):
    """
    Finds the recommended start date for a kennel based on its rules.
    Returns the closest date on or after start_date that matches the kennel's day of week.
    """
    rule = KENNEL_RULES[kennel]
    
    # Special frequencies don't have a specific day
    if rule["frequency"] in ["full-moon", "fixed-dates", "yakh3-summer", "yakh3hh-summer", "gqh-quarterly"]:
        return None
    
    kennel_day_name = rule["day"]
    kennel_day_of_week = DAY_MAP.get(kennel_day_name)
    
    if kennel_day_of_week is None:
        return None
    
    # Find the next occurrence of the target day of week
    current_day_of_week = start_date.weekday()
    days_to_add = (kennel_day_of_week - current_day_of_week + 7) % 7
    
    if days_to_add == 0:
        # Already on the correct day
        return start_date
    else:
        # Move to the next occurrence of the correct day
        return start_date + timedelta(days=days_to_add)


def check_kennels_without_events(year):
    """
    Checks if any kennels in KENNEL_SPECS did not generate any events for the entire year.
    Returns a dictionary of kennels without events and their recommended start dates.
    """
    kennels_without_events = {}
    
    for kennel, spec in KENNEL_SPECS.items():
        # Check if this kennel generated any events across all months
        has_events = False
        
        for month in range(1, 13):
            tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"
            if os.path.exists(tsv_file_path):
                try:
                    with open(tsv_file_path, 'r', encoding='utf-8', errors='replace') as f:
                        lines = f.readlines()
                    
                    for line in lines[1:]:  # Skip header
                        parts = line.strip().split('\t')
                        if len(parts) >= 2 and parts[1] == kennel:
                            has_events = True
                            break
                    
                    if has_events:
                        break
                except Exception:
                    continue
        
        if not has_events:
            start_date = spec["initial_date"]
            recommended_date = find_recommended_start_date(kennel, start_date)
            kennels_without_events[kennel] = {
                "current_start_date": start_date,
                "recommended_start_date": recommended_date
            }
    
    return kennels_without_events


# --- PHP/HTML Grid Generation Functions ---

def generate_event_rows(month, year):
    """Generates the HTML table rows for a single month's calendar grid."""
    first_day_of_week = datetime(year, month, 1).weekday()
    num_days = calendar.monthrange(year, month)[1]
    php_first_day_of_week = (first_day_of_week + 1) % 7
    
    special_dates = get_special_dates_for_year(year)

    html_rows = "\t\t\t\t\t<tr>\n"

    for i in range(php_first_day_of_week):
        html_rows += '\t\t\t\t\t\t<td class="empty"></td>\n'

    day_count = 1
    while day_count <= num_days:
        if (php_first_day_of_week + day_count - 1) % 7 == 0 and day_count > 1:
            html_rows += '\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n'

        js_id = f"j{month-1}{day_count}"
        
        date_key = (month, day_count)
        special_info = special_dates.get(date_key)
        
        html_rows += f'\t\t\t\t\t\t<td class="day">\n'
        html_rows += f'\t\t\t\t\t\t\t<table class="inner" id="{js_id}">\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        
        if special_info:
            info_text = special_info[0]
            dom_class = special_info[1]
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
    events_by_date = {}
    
    for month in range(1, 13):
        tsv_file_path = f"android/{previous_year}-{str(month).zfill(2)}.txt"
        if not os.path.exists(tsv_file_path):
            continue
            
        try:
            with open(tsv_file_path, 'r', encoding='utf-8', errors='replace') as f:
                lines = f.readlines()
                
            for line in lines[1:]:
                parts = line.strip().split('\t')
                if len(parts) >= 6:
                    day = int(parts[0])
                    title = parts[3]
                    hares = parts[5]
                    
                    if title:
                        date_key = (month, day)
                        if date_key not in events_by_date:
                            events_by_date[date_key] = []
                        events_by_date[date_key].append((title, hares))
        except Exception as e:
            print(f"Warning: Could not read {tsv_file_path}: {e}")
            continue
    
    return events_by_date


def generate_year_grid_for_planning(year):
    """Generates the full-year daily grid for the planning.php file."""
    special_dates = get_special_dates_for_year(year) 
    previous_year_events = load_previous_year_events(year)
    
    month_colors = {
        1: "#E6F2FF", 2: "#FFE6F0", 3: "#E6FFE6", 4: "#FFF9E6",
        5: "#F0E6FF", 6: "#FFE6E6", 7: "#E6FFFF", 8: "#FFF0E6",
        9: "#E6F0FF", 10: "#FFE6F9", 11: "#F0FFE6", 12: "#E6E6FF"
    }
    
    first_day_of_year = datetime(year, 1, 1).date()
    php_first_day_of_week = (first_day_of_year.weekday() + 1) % 7
    
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
        
        bg_color = month_colors.get(current_date.month, "#FFFFFF")
        dom_text = f"{MONTH_NAMES[current_date.month]} {current_date.day}"

        html_rows += f'\t\t\t\t\t\t<td class="day" style="background-color: {bg_color};">\n'
        html_rows += '\t\t\t\t\t\t\t<table class="inner">\n' 
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        
        if special_info:
            info_text = special_info[0]
            dom_class = special_info[1]
            html_rows += f'\t\t\t\t\t\t\t\t\t<td class="{dom_class}"><span class="tag">{info_text}</span>{dom_text}</td>\n'
        else:
            html_rows += f'\t\t\t\t\t\t\t\t\t<td class="dom">{dom_text}</td>\n'

        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
        html_rows += f'\t\t\t\t\t\t\t\t<td class="event"> <?php fillIn({current_date.month}, {current_date.day}, {year}); ?></td>\n'
        html_rows += '\t\t\t\t\t\t\t\t</tr>\n'
        
        if prev_year_events:
            html_rows += '\t\t\t\t\t\t\t\t<tr>\n'
            html_rows += '\t\t\t\t\t\t\t\t<td class="event" style="font-size: 8px; color: #cc0000; padding-top: 2px;">'
            for i, (title, hares) in enumerate(prev_year_events):
                if i > 0:
                    html_rows += '<br/>'
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


def generate_files_for_month(month, year, kennel_run_numbers, clobber=False):
    """Generates the monthly PHP and TSV files."""
    
    prev_month, prev_year = (month - 1, year) if month > 1 else (12, year - 1)
    next_month, next_year = (month + 1, year) if month < 12 else (1, year + 1)
    
    prev_filename = f"${str(prev_month).zfill(2)}-{prev_year}.php"
    next_filename = f"${str(next_month).zfill(2)}-{next_year}.php"

    if prev_year == year:
        prev_month_link = prev_filename
    else:
        prev_month_link = f"../{prev_year}/{prev_filename}"

    if next_year == year:
        next_month_link = next_filename
    else:
        next_month_link = f"../{next_year}/{next_filename}"

    # Generate/merge TSV file
    updated_run_numbers = merge_tsv_events(month, year, kennel_run_numbers, clobber)
    kennel_run_numbers.update(updated_run_numbers)
    
    tsv_file_path = f"android/{year}-{str(month).zfill(2)}.txt"

    # Generate PHP File
    month_name = MONTH_NAMES[month]
    year_short = year % 100
    image_file = f"month-{str(month).zfill(2)}.png"
    
    php_head = HTML_HEAD_MONTH.format(
        month_name=month_name, month=month, year=year, year_short=year_short,
        image_file=image_file, prev_link=prev_month_link, next_link=next_month_link
    )
    
    php_rows = generate_event_rows(month, year)
    php_content = php_head + php_rows + HTML_FOOTER_MONTH
    
    php_file_path = f"calendar/{year}/${str(month).zfill(2)}-{year}.php"
    os.makedirs(os.path.dirname(php_file_path), exist_ok=True)
    with open(php_file_path, 'w', encoding='utf-8') as php_file:
        php_file.write(php_content)

    return php_file_path, tsv_file_path


def generate_files_for_year(year, clobber=False):
    """Orchestrates the generation of all calendar files for a given year."""
    kennel_run_numbers = {kennel: spec["run_number"] for kennel, spec in KENNEL_SPECS.items()}

    print(f"\n{'='*80}")
    print(f"Generating calendar files for {year}")
    if clobber:
        print(f"Mode: CLOBBER (will overwrite existing events)")
    else:
        print(f"Mode: MERGE (will preserve existing events)")
    print(f"{'='*80}\n")
    
    for month in range(1, 13):
        month_name = MONTH_NAMES[month]
        print(f"\n--- Processing {month_name} {year} ---")
        generate_files_for_month(month, year, kennel_run_numbers, clobber)

    print(f"\n--- Generating full-year planning file ---")
    generate_planning_php(year, kennel_run_numbers)
    
    # Check for kennels without events
    kennels_without_events = check_kennels_without_events(year)
    
    if kennels_without_events:
        print(f"\n{'='*80}")
        print(f"⚠ WARNING: The following kennels did not generate any events for {year}:")
        print(f"{'='*80}")
        
        for kennel, dates in kennels_without_events.items():
            current = dates["current_start_date"]
            recommended = dates["recommended_start_date"]
            rule = KENNEL_RULES[kennel]
            
            print(f"\n  Kennel: {kennel}")
            print(f"  Current start date: {current.strftime('%Y-%m-%d')} ({calendar.day_name[current.weekday()]})")
            print(f"  Frequency: {rule['frequency']}")
            
            if recommended:
                print(f"  Expected day: {rule['day']}")
                print(f"  ✓ RECOMMENDED: Change initial_date to {recommended.strftime('%Y-%m-%d')} ({calendar.day_name[recommended.weekday()]})")
            else:
                print(f"  Note: This kennel has special scheduling rules")
        
        print(f"\n{'='*80}\n")
    
    print(f"\n{'='*80}")
    print(f"Calendar generation for {year} complete!")
    print(f"{'='*80}\n")