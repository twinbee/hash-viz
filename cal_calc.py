# cal_calc.py

import math
from datetime import datetime, timedelta, date

from cal_constants import (
    DAY_MAP, DALLAS_LATITUDE, DALLAS_LONGITUDE, REF_FM_DATE, SYNODIC_MONTH, 
    KENNEL_RULES
)

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
    red_holidays = {
        (1, 1): "New Year's Day", (2, 14): "Valentine's Day", (3, 17): "St. Patrick's Day",
        (5, 5): "Cinco de Mayo", (6, 19): "Juneteenth", (7, 4): "Independence Day",
        (7, 31): "Gispert's Birthday", (10, 31): "Halloween", (11, 11): "Veterans Day",
        (12, 25): "Christmas Day", (12, 31): "New Year's Eve"
    }
    
    for (m, d), name in red_holidays.items():
        special_dates[(m, d)] = (name, "holiday")

    # Floating Red Holidays (MLK, Presidents', Memorial, Labor, Columbus, Thanksgiving)
    mlk = get_day_of_occurrence(year, 1, 0, 3) 
    if mlk: special_dates[(mlk.month, mlk.day)] = ("MLK Jr. Day", "holiday")
    presidents = get_day_of_occurrence(year, 2, 0, 3)
    if presidents: special_dates[(presidents.month, presidents.day)] = ("Presidents' Day", "holiday")
    memorial = get_day_of_occurrence(year, 5, 0, -1)
    if memorial: special_dates[(memorial.month, memorial.day)] = ("Memorial Day", "holiday")
    labor = get_day_of_occurrence(year, 9, 0, 1)
    if labor: special_dates[(labor.month, labor.day)] = ("Labor Day", "holiday")
    columbus = get_day_of_occurrence(year, 10, 0, 2)
    if columbus: special_dates[(columbus.month, columbus.day)] = ("Columbus Day", "holiday")
    thanksgiving = get_day_of_occurrence(year, 11, 3, 4) 
    if thanksgiving: special_dates[(thanksgiving.month, thanksgiving.day)] = ("Thanksgiving Day", "holiday")

    # --- BLUE BAR Holidays (EOP and Hashmas) ---
    july_4th = date(year, 7, 4)
    for i in range(14):
        eop_date = july_4th - timedelta(days=i)
        eop_name = f"EOP #{14 - i}" 
        if (eop_date.month, eop_date.day) not in red_holidays:
            special_dates[(eop_date.month, eop_date.day)] = (eop_name, "blue_bar")

    christmas_day = date(year, 12, 25)
    for i in range(12):
        hashmas_date = christmas_day - timedelta(days=i)
        hashmas_name = f"Hashmas Day {12 - i}"
        if (hashmas_date.month, hashmas_date.day) not in red_holidays:
            special_dates[(hashmas_date.month, hashmas_date.day)] = (hashmas_name, "blue_bar")

    return special_dates

# =========================================================================
# SUNSET CALCULATION FUNCTIONS
# =========================================================================

def is_dst_dallas(date_obj):
    """Checks if a given datetime object is within the US DST period."""
    year = date_obj.year
    # DST Start: Second Sunday in March at 2:00 AM
    march_first = date(year, 3, 1)
    first_sunday_march = march_first + timedelta(days=(6 - march_first.weekday()) % 7) 
    dst_start_date = first_sunday_march + timedelta(weeks=1)
    # DST End: First Sunday in November at 2:00 AM
    november_first = date(year, 11, 1)
    dst_end_date = november_first + timedelta(days=(6 - november_first.weekday()) % 7) 
    event_date = date_obj.date()
    return (event_date >= dst_start_date) and (event_date < dst_end_date)

def calculate_sunset_time_dallas(date_obj):
    """Calculates the sunset time for Dallas, TX for a given date using a simplified algorithm."""
    # Day of year (1-365/366)
    N = date_obj.timetuple().tm_yday
    
    # Mean anomaly in degrees
    M = (0.9856 * N) - 3.289
    
    # Sun's true longitude
    L = M + (1.916 * math.sin(math.radians(M))) + (0.020 * math.sin(math.radians(2 * M))) + 282.634
    L = L % 360
    
    # Sun's right ascension
    RA = math.degrees(math.atan(0.91764 * math.tan(math.radians(L))))
    RA = RA % 360
    
    # Right ascension needs to be in the same quadrant as L
    L_quadrant = (math.floor(L / 90)) * 90
    RA_quadrant = (math.floor(RA / 90)) * 90
    RA = RA + (L_quadrant - RA_quadrant)
    
    # Convert RA to hours
    RA = RA / 15
    
    # Sun's declination
    sin_dec = 0.39782 * math.sin(math.radians(L))
    cos_dec = math.cos(math.asin(sin_dec))
    
    # Sun's local hour angle for sunset (accounting for atmospheric refraction)
    cos_H = (math.sin(math.radians(-0.833)) - (sin_dec * math.sin(math.radians(DALLAS_LATITUDE)))) / \
            (cos_dec * math.cos(math.radians(DALLAS_LATITUDE)))
    
    # Check if sun rises/sets on this day
    if cos_H > 1 or cos_H < -1:
        return ""
    
    # Hour angle for sunset (in hours)
    H = math.degrees(math.acos(cos_H)) / 15
    
    # Local mean time of sunset
    T = H + RA - (0.06571 * N) - 6.622
    
    # Adjust for longitude (Dallas is at -96.7970)
    UT = T - (DALLAS_LONGITUDE / 15)
    UT = UT % 24
    
    # Convert to local time zone
    # CST is UTC-6, CDT is UTC-5
    if is_dst_dallas(date_obj):
        local_time = UT - 5  # CDT
    else:
        local_time = UT - 6  # CST
    
    # Normalize to 0-24 range
    local_time = local_time % 24
    
    # Convert to hours and minutes
    hour = int(local_time)
    minute = int(round((local_time - hour) * 60))
    
    # Handle minute overflow
    if minute >= 60:
        minute = 0
        hour += 1
    if minute < 0:
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
    current_fm_time = REF_FM_DATE
    start_of_target_year = datetime(target_year, 1, 1)
    days_to_target = (start_of_target_year - REF_FM_DATE).total_seconds() / (60*60*24)
    approx_lunations = round(days_to_target / SYNODIC_MONTH)

    current_fm_time = REF_FM_DATE + timedelta(days=approx_lunations * SYNODIC_MONTH)

    while current_fm_time.year >= target_year:
        current_fm_time -= timedelta(days=SYNODIC_MONTH)

    fm_dates_set = set()

    for i in range(15): 
        current_fm_time += timedelta(days=SYNODIC_MONTH)
        if current_fm_time.year == target_year:
            fm_dates_set.add((current_fm_time.date().month, current_fm_time.date().day))
        elif current_fm_time.year > target_year:
            break

    return sorted(list(fm_dates_set))
# =========================================================================

# Function to calculate the expected event date based on frequency
def calculate_next_event(kennel, start_date, current_date, frequency):
    """
    Calculates the next expected event date for a given kennel, checking if it lands
    on the current_date based on its start_date and frequency.
    """
    if frequency == "weekly":
        delta = timedelta(weeks=1)
    elif frequency == "bi-weekly":
        delta = timedelta(weeks=2)
    elif frequency == "monthly" or frequency == "summer-sundays":
        # Monthly and Summer-Sundays are calculated on a 4-week cycle based on 
        # the initial start date, and must land on the specified weekday.
        delta = timedelta(weeks=4)
        if frequency == "summer-sundays" and current_date.month not in [6, 7, 8]:
             return None
    elif frequency == "full-moon" or frequency == "fixed-dates" or frequency == "yakh3-summer":
        # These frequencies are handled separately in generate_tsv_events
        return None
    else:
        return None

    time_diff = current_date.date() - start_date.date()
    interval_days = delta.total_seconds() / (60*60*24)
    
    # Check if the time difference is a multiple of the interval AND the day of week matches
    if time_diff.days >= 0 and time_diff.days % interval_days == 0:
        kennel_day_name = KENNEL_RULES[kennel]["day"]
        kennel_day_of_week = DAY_MAP.get(kennel_day_name)
        if current_date.weekday() == kennel_day_of_week:
             return current_date
             
    return None