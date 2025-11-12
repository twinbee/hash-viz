import sys
import os
from datetime import datetime

# --- Helper Functions ---

def parse_date(date_str):
    """
    Parses a date string (e.g., '3/14/2026') and returns a datetime.date object 
    and the expected TSV file path (e.g., 'android/2026-03.txt').
    """
    try:
        # Expects MM/DD/YYYY format
        dt = datetime.strptime(date_str, '%m/%d/%Y')
        year = dt.year
        month = str(dt.month).zfill(2)
        # Construct path relative to script location
        filepath = os.path.join('android', f'{year}-{month}.txt')
        return dt.date(), filepath
    except ValueError:
        print(f"Error: Invalid date format for '{date_str}'. Please use MM/DD/YYYY.")
        sys.exit(1)

def load_events_from_file(filepath):
    """
    Reads a TSV file, returns header, list of event fields (rows), 
    and a mapping of column names to indices.
    """
    if not os.path.exists(filepath):
        print(f"Error: Required file not found: {filepath}. Please ensure the calendar files have been generated.")
        return None, [], {}

    with open(filepath, 'r') as f:
        lines = f.read().splitlines()

    if not lines:
        return None, [], {}

    header = lines[0].split('\t')
    col_indices = {name: i for i, name in enumerate(header)}
    event_data = []

    for line in lines[1:]:
        if not line.strip(): continue
        fields = line.split('\t')
        # Ensure the row has the correct number of columns (matching the header)
        if len(fields) != len(header):
            print(f"Warning: Skipping malformed line in {filepath} (Expected {len(header)} columns, found {len(fields)})")
            continue
        # Store as a mutable list of strings
        event_data.append(fields)

    return lines[0], event_data, col_indices

def find_event(target_date, file_data, col_indices):
    """
    Finds the event fields matching the target_date in the file_data.
    Returns the event's field list and its index within the file_data list.
    """
    date_col_index = col_indices.get('date')

    if date_col_index is None:
        print("Error: 'date' column not found in the file header.")
        return None, None

    for i, fields in enumerate(file_data):
        event_date_str = fields[date_col_index]
        try:
            # Parse the full date string (e.g., 'Saturday, March 14, 2026')
            event_dt = datetime.strptime(event_date_str, '%A, %B %d, %Y').date()
            if event_dt == target_date:
                return fields, i
        except ValueError:
            # Skip lines that don't match the expected date format
            pass 

    return None, None

def format_event_desc(event_fields, col_indices):
    """Creates a concise, descriptive string for the user confirmation."""
    date_str = event_fields[col_indices['date']]
    kennel = event_fields[col_indices['kennel']]
    run = event_fields[col_indices['run']]
    title = event_fields[col_indices['title']]

    # Extract only the date part "Month Day, Year"
    try:
        date_part = datetime.strptime(date_str, '%A, %B %d, %Y').strftime('%B %d, %Y')
    except ValueError:
        date_part = date_str
    
    # Construct the description string as requested by the user's example
    # Example: "March 13 2026, - Dallas Hash #1214 - Dallas Hash Run Hare:"
    description = f"{date_part}, - {kennel} #{run} - {title}"
    if 'hares' in col_indices and event_fields[col_indices['hares']].strip():
         description += f" Hare:{event_fields[col_indices['hares']].strip()}"
    elif 'hares' in col_indices:
        # If 'hares' is a column but empty, just append the colon/label
        description += f" Hare:"


    return description

def save_data(filepath, header, data):
    """Saves the modified data back to the specified TSV file."""
    try:
        print(f"Saving changes to: {filepath}")
        with open(filepath, 'w') as f:
            f.write(header + '\n')
            for fields in data:
                f.write('\t'.join(fields) + '\n')
    except Exception as e:
        print(f"Critical Error saving file {filepath}: {e}")
        sys.exit(1)


# --- Main Logic ---

def switch_events(date1_str, date2_str):
    """Coordinates the loading, verification, swapping, and saving of event data."""
    date1, path1 = parse_date(date1_str)
    date2, path2 = parse_date(date2_str)

    # 1. Load data from required files
    header1, data1, cols1 = load_events_from_file(path1)
    if not data1: return

    path2_is_same = (path1 == path2)
    
    if path2_is_same:
        # If files are the same, reference the same data/headers/columns
        header2, data2, cols2 = header1, data1, cols1
    else:
        header2, data2, cols2 = load_events_from_file(path2)
        if not data2: return
        
    # Consistency check: Ensure both files have the same column structure
    if cols1 != cols2:
        print("Error: The column structure of the two required files is different. Cannot switch.")
        return
    
    col_indices = cols1
    # 'day' is the first column, index 0, which we must skip during the swap
    day_idx = col_indices.get('day', 0) 

    # 2. Locate the two events
    event1_fields, idx1 = find_event(date1, data1, col_indices)
    event2_fields, idx2 = find_event(date2, data2, col_indices)

    if event1_fields is None:
        print(f"Error: Could not find event on {date1_str}.")
        return
    if event2_fields is None:
        print(f"Error: Could not find event on {date2_str}.")
        return

    # 3. Verification and Confirmation
    # We must use the fields *before* the swap to generate the description
    desc1_pre = format_event_desc(event1_fields, col_indices)
    desc2_pre = format_event_desc(event2_fields, col_indices)
    
    print("\n--- Event Switch Confirmation ---")
    # This confirmation matches the user's requested example:
    print(f'You want to switch "{desc1_pre}" with "{desc2_pre}"')
    
    confirmation = input("Do you want to proceed with the swap? (y/N): ").lower()
    if confirmation != 'y':
        print("Switch canceled by user.")
        return

    # 4. Perform the swap (excluding the 'day' column, index 0)
    print("Performing swap...")
    
    # Slice the fields from index 1 onwards (excluding 'day')
    # Note: Using event1_fields[day_idx+1:] creates a copy of the list slice
    data_to_swap_1 = event1_fields[day_idx+1:]
    data_to_swap_2 = event2_fields[day_idx+1:]
    
    # Replace all columns (except 'day') in event 1's slot with event 2's data
    data1[idx1][day_idx+1:] = data_to_swap_2
    
    # Replace all columns (except 'day') in event 2's slot with event 1's data
    data2[idx2][day_idx+1:] = data_to_swap_1

    # 5. Save the modified data back to the original files
    save_data(path1, header1, data1)
    
    if not path2_is_same:
        save_data(path2, header2, data2)
    
    print("\nSuccess: Event data successfully switched.")


def main():
    """Entry point for the script."""
    if len(sys.argv) != 3:
        print(f"Usage: python {sys.argv[0]} <MM/DD/YYYY_Date1> <MM/DD/YYYY_Date2>")
        print("Example: python {sys.argv[0]} 3/14/2026 3/21/2026")
        sys.exit(1)

    date1_str = sys.argv[1]
    date2_str = sys.argv[2]
    
    # Ensure 'android' directory exists, as the script expects to find the .txt files there
    if not os.path.isdir('android'):
        print("Warning: 'android' directory not found. Please ensure your calendar files are generated.")
        # Do not exit here; let the file loading handle the final error if files are missing.

    switch_events(date1_str, date2_str)

if __name__ == '__main__':
    main()