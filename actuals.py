import os
import csv
import sys
from datetime import datetime

# Ensure the script uses UTF-8 encoding for output
sys.stdout.reconfigure(encoding='utf-8')

def is_event_exempt(description):
    """
    Check if the event should not cause the RUN number to increment.
    
    Args:
        description (str): The description of the event.
        
    Returns:
        bool: True if the event is "Cancelled," "Drinking practice," or "happy hour"; False otherwise.
    """
    description_lower = description.lower()
    return ("cancelled" in description_lower or
            "drinking practice" in description_lower or
            "happy hour" in description_lower)

def process_file(filepath, kennels_data):
    """
    Process a single file to determine the actual RUN numbers for each kennel.

    Args:
        filepath (str): The path to the file to process.
        kennels_data (dict): Dictionary to store actual RUN data for each kennel.
    """
    with open(filepath, 'r', encoding='utf-8', errors='replace') as file:
        reader = csv.reader(file, delimiter='\t')
        header = next(reader)  # Ignore the header row

        for row in reader:
            if len(row) < 15:
                print(f'Skipping row with insufficient columns: {row}')
                continue

            try:
                date_str = row[13]
                row_date = datetime.strptime(date_str, '%A, %B %d, %Y')
            except ValueError:
                print(f"Skipping row with invalid date: {row}")
                continue

            kennel = row[1]
            
            # Attempt to parse the RUN number, handle cases where it's missing or invalid
            try:
                run_number = int(row[4])  # RUN is in the 5th column (index 4)
            except ValueError:
                print(f"[WARNING] Skipping row with invalid RUN number: {row}")
                continue

            description = row[14] if len(row) > 14 else ""  # Description is in the 15th column (index 14)

            # Initialize kennel data if not already present
            if kennel not in kennels_data:
                kennels_data[kennel] = {
                    'last_run_number': run_number,
                    'errors': 0,
                    'cancelled_or_practice': 0,
                    'rows': [],
                    'expected_run_number': run_number
                }

            kennel_data = kennels_data[kennel]

            # Check for "Cancelled," "Drinking practice," or "happy hour"
            if is_event_exempt(description):
                kennel_data['cancelled_or_practice'] += 1
                print(f"[DEBUG] {kennel} - Event exempt from increment: {description} on {date_str}")
            else:
                # Verify if RUN number is correctly incremented
                expected_run_number = kennel_data['expected_run_number'] + 1
                if run_number != expected_run_number:
                    kennel_data['errors'] += 1
                    print(f"[ERROR] {kennel} - Expected RUN {expected_run_number}, found {run_number} on {date_str}")

                # Update the last recorded RUN number and expected RUN number
                kennel_data['last_run_number'] = run_number
                kennel_data['expected_run_number'] = expected_run_number

            # Store the row for final reporting
            kennel_data['rows'].append((row_date, run_number, description))

def generate_report(kennels_data):
    """
    Generate a report of the actual RUN numbers and any discrepancies.

    Args:
        kennels_data (dict): Dictionary containing the actual RUN data for each kennel.
    """
    print("\n=== Actual RUN Numbers Report ===\n")
    for kennel, data in kennels_data.items():
        print(f"Kennel: {kennel}")
        print(f"  Total Entries Processed: {len(data['rows'])}")
        print(f"  Total Exempted Events (Cancelled/Drinking Practice/Happy Hour): {data['cancelled_or_practice']}")
        print(f"  Total Errors (RUN number discrepancies): {data['errors']}")
        print("  Errors Summary:")
        for row_date, run_number, description in sorted(data['rows']):
            if is_event_exempt(description):
                event_status = "Exempted"
            else:
                event_status = "Regular"

            print(f"    Date: {row_date.strftime('%Y-%m-%d')}, RUN: {run_number}, Status: {event_status}, Description: {description}")
        print("\n")

    print("=== Summary of Last Reported and Expected RUN Numbers for Each Kennel ===\n")
    for kennel, data in kennels_data.items():
        print(f"Kennel: {kennel}")
        print(f"  Last Reported RUN Number: {data['last_run_number']}")
        print(f"  Expected/Calculated RUN Number: {data['expected_run_number']}\n")

def process_directory(directory):
    """
    Process all files in the given directory to determine actual RUN numbers.

    Args:
        directory (str): The path to the directory containing the .txt files.
    """
    kennels_data = {}

    for filename in sorted(os.listdir(directory)):
        if filename.endswith('.txt'):
            print(f'Processing file: {filename}')
            filepath = os.path.join(directory, filename)
            process_file(filepath, kennels_data)

    generate_report(kennels_data)

# Prompt user for input
directory = input("Enter the directory path containing the .txt files: ")

# Run the process
process_directory(directory)

print("RUN number analysis completed.")
