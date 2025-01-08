import os
import pandas as pd
import re
from datetime import datetime

# Directory containing the .txt files
directory = '.'

# Initialize an empty list to collect event data
event_data = []

# Keywords and regex patterns for birthdays and anniversaries
birthday_patterns = re.compile(r'\b(birthday|b[-\s]*day|beerthday|anniversary|analversary|birth)\b', re.IGNORECASE)

# Debug log to collect parsing issues
debug_log = []

# Iterate over all .txt files in the directory
for file_name in os.listdir(directory):
    if file_name.endswith('.txt'):
        file_path = os.path.join(directory, file_name)
        # Load TSV file
        try:
            df = pd.read_csv(file_path, sep='\t', dtype=str).fillna('')
            # Extract relevant columns: 'TITLE', 'DATE', 'DESC', and 'HARES'
            for index, row in df.iterrows():
                try:
                    # Normalize title and description
                    event_title = re.sub(r'\s+', ' ', re.sub(r'[^\w\s]', '', row.get('TITLE', '').lower().strip()))
                    event_desc = re.sub(r'\s+', ' ', re.sub(r'[^\w\s]', '', row.get('DESC', '').lower().strip()))
                    event_date = datetime.strptime(str(row['DATE']), '%A, %B %d, %Y').date()
                    event_hare = row.get('HARES', '').strip()

                    # Check if either the event title or description matches birthday patterns
                    if birthday_patterns.search(event_title) or birthday_patterns.search(event_desc):
                        event_data.append((event_title, event_date, event_desc, event_hare))
                except (KeyError, ValueError, AttributeError) as e:
                    debug_log.append((file_name, index, str(e)))
        except Exception as e:
            print(f"Error reading {file_path}: {e}")

# Convert the collected data to a DataFrame
event_df = pd.DataFrame(event_data, columns=['Event Title', 'Date', 'Description', 'Hares'])

# Print results
print("Detected Birthdays or Anniversaries with Descriptions and Hares:")
print(event_df)

# Save results to a CSV file
event_df.to_csv('all_birthdays_with_hares.csv', index=False)

# Save debug log to a file for inspection
with open('debug_log.txt', 'w') as log_file:
    for entry in debug_log:
        log_file.write(f"{entry}\n")
