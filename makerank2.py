import os
import sys
import io
import pandas as pd
import matplotlib.pyplot as plt
from collections import defaultdict
import fuzzy
from unidecode import unidecode

# Ensure UTF-8 encoding for output
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

NAME_ALIASES = {
    'BDB': 'Blondyke Bar',
    'Blondyke Bar': 'Blondyke Bar',
    'Worst Lesbian Ever': 'Blondyke Bar',
    'Best Lesbian Ever': 'Blondyke Bar',
    'MBFJ': 'My Boyfriend Joe',
    'MBJ': 'My Boyfriend Joe',
    'My Boyfriend Joe': 'My Boyfriend Joe',
    'Just Bee': 'Likes It In the Kitchen',
    'Likes It In the Kitchen': 'Likes It In the Kitchen'
}

IGNORE_HARES = {'Team C U  Next Tue', 'Mr E Hare', '', ' ', 'mystery co-hare', 'Mystery Co-Hare',
                'Team H<3<', 'mystery hare', 'Mr. E Hare', 'open', 'OPEN', 'HARE NEEDED', 'CANCELED', 'CANCELLED'}

def preprocess_name(name):
    name = name.replace('amp;', '').replace('sup>', '').replace('<sup/>', '')
    name = name.replace('<br/>', ',').replace('<br />', ',')
    name = name.replace('/', ',')
    name = name.replace('Just ', '')
    return name

def read_files_from_directory(directory):
    events = []
    for filename in os.listdir(directory):
        if filename.endswith('.txt'):
            filepath = os.path.join(directory, filename)
            print(f"Processing file: {filepath}")
            try:
                df = pd.read_csv(filepath, sep='\t', header=0, encoding='utf-8')
                if 'TITLE' in df.columns and 'HARES' in df.columns:
                    events.extend(df[['TITLE', 'HARES']].dropna().to_dict('records'))
                else:
                    print(f"Warning: {filepath} is missing required columns.")
            except Exception as e:
                print(f"Error reading {filepath}: {e}")
    print(f"Total events read: {len(events)}")
    return events

def apply_aliases(name):
    return NAME_ALIASES.get(name, name)

def normalize_hare_names(events):
    soundex = fuzzy.Soundex(4)
    normalized_groups = defaultdict(list)

    for event in events:
        title = event['TITLE']
        hare_string = event['HARES']
        hare_string = unidecode(str(hare_string))
        hare_string = preprocess_name(hare_string)
        if hare_string in IGNORE_HARES:
            continue

        hares = [apply_aliases(h.strip()) for h in hare_string.split(',')]

        for hare in hares:
            soundex_code = soundex(hare)
            if hare not in IGNORE_HARES:
                normalized_groups[soundex_code].append(hare)
                print(f"Debug: Hare '{hare}' matched under Soundex code '{soundex_code}' for event '{title}'")

    return normalized_groups

def plot_hare_groups(normalized_groups):
    hare_counts = defaultdict(int)
    for soundex_code, hares in normalized_groups.items():
        unique_hares = set(hares)
        for hare in unique_hares:
            hare_counts[hare] += len(hares)

    sorted_counts = sorted(hare_counts.items(), key=lambda x: x[1], reverse=True)[:10]
    if sorted_counts:
        hares, counts = zip(*sorted_counts)
        plt.figure(figsize=(10, 6))
        plt.barh(hares, counts, color='skyblue')
        plt.xlabel('Count')
        plt.ylabel('Hare Names')
        plt.title('Top 10 Hares by Count')
        plt.gca().invert_yaxis()
        plt.show()

def main(directory):
    events = read_files_from_directory(directory)
    if not events:
        print("No events found.")
        return
    normalized_groups = normalize_hare_names(events)

    print("\nSummary of Hares Grouped by Soundex Code:")
    for soundex_code, hares in normalized_groups.items():
        print(f"Soundex Code: {soundex_code}")
        print(f"  Hares: {', '.join(set(hares))}")

    plot_hare_groups(normalized_groups)

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print("Usage: python script.py <directory_path>")
        sys.exit(1)
    directory = sys.argv[1]
    main(directory)
