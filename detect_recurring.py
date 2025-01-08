import os
import pandas as pd
from datetime import datetime, timedelta
from fuzzywuzzy import fuzz
from fuzzywuzzy import process

# Directory containing the .txt files
directory = '.'

# Initialize an empty list to collect event data
event_data = []

# Iterate over all .txt files in the directory
for file_name in os.listdir(directory):
    if file_name.endswith('.txt'):
        file_path = os.path.join(directory, file_name)
        # Load TSV file
        try:
            df = pd.read_csv(file_path, sep='\t')
            # Extract relevant columns: 'TITLE' and 'DATE'
            for index, row in df.iterrows():
                try:
                    event_title = row['TITLE'].strip()
                    event_date = datetime.strptime(row['DATE'], '%A, %B %d, %Y').date()
                    event_data.append((event_title, event_date))
                except (KeyError, ValueError):
                    continue  # Skip rows with missing or malformed data
        except Exception as e:
            print(f"Error reading {file_path}: {e}")

# Convert the collected data to a DataFrame
event_df = pd.DataFrame(event_data, columns=['Event Title', 'Date'])

# Define function to find similar events using fuzzy matching
def find_similar_events(event, event_list, threshold=80):
    similar_events = []
    for e in event_list:
        if fuzz.ratio(event, e) >= threshold:
            similar_events.append(e)
    return similar_events

# Group events by approximate title using fuzzy matching
unique_titles = list(event_df['Event Title'].unique())
event_clusters = {}

for title in unique_titles:
    if title not in event_clusters:
        similar_titles = find_similar_events(title, unique_titles)
        for sim_title in similar_titles:
            event_clusters[sim_title] = title

# Replace event titles with clustered titles
event_df['Clustered Title'] = event_df['Event Title'].apply(lambda x: event_clusters[x])

# Allow for ±14 days flexibility in date matching
event_df['Date Range Start'] = event_df['Date'] - timedelta(days=14)
event_df['Date Range End'] = event_df['Date'] + timedelta(days=14)

# Detect recurring events by checking overlap in date ranges for the same clustered title
recurring_events = []
for title, group in event_df.groupby('Clustered Title'):
    sorted_group = group.sort_values(by='Date')
    previous_row = None
    for _, row in sorted_group.iterrows():
        if previous_row is not None:
            if row['Date Range Start'] <= previous_row['Date Range End']:
                recurring_events.append((title, previous_row['Date'], row['Date']))
        previous_row = row

# Convert recurring events to DataFrame and display results
recurring_events_df = pd.DataFrame(recurring_events, columns=['Event Title', 'Date 1', 'Date 2'])

print("Potential Recurring Annual Events:")
print(recurring_events_df)

# Optionally, save to a CSV file
recurring_events_df.to_csv('potential_recurring_events.csv', index=False)
