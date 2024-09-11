import os
import csv
from datetime import datetime

def process_file(filepath, kennel_name, start_date, current_run_number):
    """
    Process a single file to correct RUN numbers for a specified kennel starting from a given date.
    
    Args:
        filepath (str): The path to the file to process.
        kennel_name (str): The name of the kennel to filter on.
        start_date (datetime): The date from which to start correcting RUN numbers.
        current_run_number (int): The current RUN number to start from.
    
    Returns:
        int: The next RUN number after processing the file.
    """
    rows = []

    # Read the file content
    with open(filepath, 'r', encoding='utf-8', errors='replace') as file:
        reader = csv.reader(file, delimiter='\t')
        header = next(reader)  # Store the header
        for row in reader:
            if len(row) < 15:
                print(f'Skipping row with insufficient columns: {row}')
                continue
            rows.append(row)

    # Sort rows by DATE to ensure chronological order
    rows.sort(key=lambda x: datetime.strptime(x[13], '%A, %B %d, %Y'))  # DATE is in the 14th column (index 13)

    today_str = datetime.now().strftime('%Y-%m-%d')  # Get today's date as a string

    for row in rows:
        date_str = row[13]
        kennel = row[1]
        try:
            row_date = datetime.strptime(date_str, '%A, %B %d, %Y')
        except ValueError:
            print(f"Skipping row with invalid date: {row}")
            continue

        # Update RUN number if the row is for the specified kennel and after the start date
        if kennel == kennel_name and row_date >= start_date:
            old_run_number = row[4]  # Store the old RUN number
            row[4] = str(current_run_number)  # RUN is in the 5th column (index 4)

            # Debugging statement for each correction
            print(f"Correcting RUN in file {os.path.basename(filepath)}: Kennel: {kennel}, "
                  f"Date: {date_str}, Old RUN: {old_run_number}, New RUN: {current_run_number}")

            # Update the UPDATE column (15th column, index 14)
            if len(row) > 14:
                update_field = row[14]
                if update_field:
                    update_field += "; "
                else:
                    update_field = ""
                update_field += f"Autocorrected from {old_run_number} to {current_run_number} on {today_str}"
                row[14] = update_field

            current_run_number += 1  # Increment the RUN number for each qualifying row

    # Write the updated content back to the file
    with open(filepath, 'w', newline='', encoding='utf-8') as file:
        writer = csv.writer(file, delimiter='\t')
        writer.writerow(header)  # Write the header back
        writer.writerows(rows)
    
    return current_run_number  # Return the next RUN number to continue for the next file

def correct_runs(directory, kennel_name, correction_start_date, start_run_number):
    """
    Process all files in the given directory to correct RUN numbers for a specified kennel.

    Args:
        directory (str): The path to the directory containing the .txt files.
        kennel_name (str): The name of the kennel to filter on.
        correction_start_date (datetime): The date from which to start correcting RUN numbers.
        start_run_number (int): The initial RUN number to start from.
    """
    current_run_number = start_run_number

    for filename in sorted(os.listdir(directory)):  # Sort files to process them in order
        if filename.endswith('.txt'):
            print(f'Processing file: {filename}')
            filepath = os.path.join(directory, filename)
            current_run_number = process_file(filepath, kennel_name, correction_start_date, current_run_number)

# Prompt user for input
directory = input("Enter the directory path containing the .txt files: ")
kennel_name = input("Enter the kennel name to filter on: ")
start_date_str = input("Enter the start date for correction (format: YYYY-MM-DD): ")
start_run_number = int(input("Enter the initial RUN number to start from: "))

# Convert start date input to a datetime object
correction_start_date = datetime.strptime(start_date_str, '%Y-%m-%d')

# Run the correction process
correct_runs(directory, kennel_name, correction_start_date, start_run_number)

print("RUN correction process completed.")
