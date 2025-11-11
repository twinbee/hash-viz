import os
import pandas as pd
import matplotlib.pyplot as plt
from collections import defaultdict
import fuzzy
from unidecode import unidecode
import sys
import io
import argparse

# --- Constants for Data Reading ---
# NOTE: Based on your file content, the DATE column is now the 14th column (index 13)
# and the HARE column is the 6th column (index 5).
DATE_COLUMN_INDEX = 13 
HARE_COLUMN_INDEX = 5

# --- Name Aliases and Ignores (Original Code) ---
NAME_ALIASES = {
    'BDB': 'Blondyke Bar',
    'Blondyke Bar': 'Blondyke Bar',
    'Worst Lesbian Ever': 'Blondyke Bar',
    'Best Lesbian Ever': 'Blondyke Bar',
    'MBFJ': 'My Boyfriend Joe',
    'MBJ': 'My Boyfriend Joe',
    'My Boyfriend Joe': 'My Boyfriend Joe',
    'Just Bee': 'Likes It In the Kitchen',
    'Likes It In the Kitchen': 'Likes It In the Kitchen',
    'Just Joel': 'Martha F. Stewart',
    'Martha F. Stewart': 'Martha F. Stewart',
    'Just Ben': 'Ben Dover My Panties R Showin',
    'Ben Dover My Panties R Showin': 'Ben Dover My Panties R Showin',
    'Soap': 'Son Of A Peach',
    'SOAP': 'Son Of A Peach',
    'S.O.A.P.': 'Son Of A Peach',
    'Son Of A Peach': 'Son Of A Peach',
    'Mystery Hare': 'Mr E Hare',
    'Mystery Hares': 'Mr E Hare',
    'Mr E Hare': 'Mr E Hare',
    'Foreplay': '4Play',
    '4Play': '4Play',
    '3 Strokes': '3 Strokes',
    '3 Strokes': '3-Strokes',
    'Three Strokes': '3 Strokes',
    'Three Strokes and Yer Done': '3 Strokes',
    '3-Strokes and Yer Done': '3 Strokes',
    'WDT': 'Wrong Dong Thong',
    'Wrong Dong Thong': 'Wrong Dong Thong',
    'MFS': 'Martha F Stewart',
    'Pits and Slits': 'Pits and Slits',
    'Double Dribble': 'Double Dribble',
    'Deflated': 'Deflated',
    'Dead Head': 'Deadhead',
    'Deadhead': 'Deadhead',
    'Dot dot dot': 'Dot, dot, dot',
    'Dot': 'Dot, dot, dot',
    'Dot, dot, dot': 'Dot, dot, dot',
    'Dude': 'Dude Where\'s My White Girl',
    'Dude Where\'s My White Girl': 'Dude Where\'s My White Girl',
    'Kneel and Bob': 'Kneel and Bob',
    "Fruity Pepples": 'Fruity Pebbles',
    'Fruity Pebbles': "Fruity Pebbles",
}

IGNORE_HARES = {'Team C U  Next Tue', 'Mr E Hare', '', ' ', 'mystery co-hare', 'Mystery Co-Hare', 'Team H<3<', 'mystery hare', 'Mr. E Hare', 'open', 'OPEN', 'HARE NEEDED', 'CANCELED', 'CANCELLED' }

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# --- Preprocessing Functions (Original Code) ---

def preprocess_name(name):
    name = name.replace('amp;', '')
    name = name.replace('sup>', '').replace('<sup/>', '')
    name = name.replace('<br/>', ',').replace('<br />', ',')
    name = name.replace('/', ',')
    return name

def apply_aliases(name):
    normalized_name = NAME_ALIASES.get(name, name)
    return normalized_name

# --- File Reading with Corrected Indexing and Date Filtering ---

def read_files_from_directory(directory, date_range=None):
    all_records = []
    
    # Parse date_range: "YYYY-MM to YYYY-MM"
    start_date = None
    end_date = None
    if date_range:
        try:
            start_str, end_str = date_range.split(' to ')
            start_date = pd.to_datetime(start_str + '-01')
            end_date_temp = pd.to_datetime(end_str + '-01') + pd.DateOffset(months=1) - pd.DateOffset(days=1)
            end_date = end_date_temp
            print(f"Filtering dates from {start_date.strftime('%Y-%m-%d')} to {end_date.strftime('%Y-%m-%d')}")
        except ValueError:
            print(f"Error: Invalid date range format. Use 'YYYY-MM to YYYY-MM'. Ignoring date filter.")
            date_range = None

    for filename in os.listdir(directory):
        if filename.endswith('.txt'):
            filepath = os.path.join(directory, filename)
            print(f"Processing file: {filepath}")
            try:
                # Read with a general header=None to get all columns
                # NOTE: The date column is now index 13
                df = pd.read_csv(filepath, sep='\t', header=None, encoding='utf-8', skiprows=1)
                
                # Check for required columns (need to check up to the max index used)
                required_cols = max(DATE_COLUMN_INDEX, HARE_COLUMN_INDEX) + 1
                if df.shape[1] >= required_cols:
                    
                    # Select the two columns we need: Date (13) and Hare Names (5)
                    df = df[[DATE_COLUMN_INDEX, HARE_COLUMN_INDEX]].dropna(subset=[HARE_COLUMN_INDEX])
                    
                    if not df.empty:
                        # Convert date column (13) to datetime objects
                        # The dates in column 13 are strings like "Saturday, May 3, 2025"
                        df['date_dt'] = pd.to_datetime(df[DATE_COLUMN_INDEX], errors='coerce')
                        df = df.dropna(subset=['date_dt']) # Drop rows where date conversion failed
                        
                        # Apply date filter
                        if date_range and start_date and end_date:
                            df_filtered = df[(df['date_dt'] >= start_date) & (df['date_dt'] <= end_date)]
                        else:
                            df_filtered = df
                        
                        # Extract only the hare names (column 5)
                        hare_names = df_filtered[HARE_COLUMN_INDEX].tolist()
                        all_records.extend(hare_names)
                    else:
                        print(f"Info: {filepath} has no valid hare data.")
                else:
                    print(f"Warning: {filepath} does not have enough columns (expected at least {required_cols}).")
            except UnicodeDecodeError as e:
                print(f"Unicode error in {filepath}: {e}")
            except Exception as e:
                print(f"Error reading {filepath}: {e}")

    print(f"Total hare names after filtering: {len(all_records)}")
    return all_records

# --- Normalization and Plotting Functions (Original Code) ---

def normalize_hare_names(hare_names):
    soundex = fuzzy.Soundex(4)
    normalized_names = defaultdict(int)
    original_names = defaultdict(list)
    all_name_matches = defaultdict(lambda: defaultdict(int))
    hare_string_matches = defaultdict(list)

    for entry in hare_names:
        try:
            original_entry = entry
            entry = unidecode(str(entry))
            entry = preprocess_name(entry)
            if entry in IGNORE_HARES or 'admin' in entry.lower():
                continue

            if entry in NAME_ALIASES:
                entries = [entry]
            else:
                entries = [name.strip() for name in entry.replace('and', ',').replace('&', ',').split(',')]

            for name in entries:
                try:
                    name = unidecode(name)
                    name = apply_aliases(name)
                    soundex_code = soundex(name)
                    if name not in IGNORE_HARES:
                        normalized_names[soundex_code] += 1
                        original_names[soundex_code].append(name)
                        all_name_matches[soundex_code][name] += 1
                        hare_string_matches[name].append(original_entry)
                except UnicodeEncodeError as e:
                    print(f"Unicode encoding error for Soundex entry '{name}': {e}")
                except Exception as e:
                    print(f"Error processing Soundex entry '{name}': {e}")
        except UnicodeEncodeError as e:
            print(f"Unicode encoding error for entry '{entry}': {e}")
        except Exception as e:
            print(f"Error processing entry '{entry}': {e}")

    most_frequent_names = {code: max(set(names), key=names.count) for code, names in original_names.items()}

    print(f"Total unique Soundex codes: {len(most_frequent_names)}")

    return normalized_names, most_frequent_names, all_name_matches, hare_string_matches


def plot_hare_names(normalized_names, most_frequent_names, all_name_matches, hare_string_matches, directory, date_range=None):
    # Configure matplotlib to handle Unicode
    plt.rcParams['axes.unicode_minus'] = False
    plt.rcParams['font.sans-serif'] = ['Arial', 'sans-serif']
    plt.rcParams['font.family'] = 'sans-serif'
    
    # Title suffix for plot
    date_suffix = f" ({date_range})" if date_range else ""
    directory_name = os.path.basename(directory)

    name_count = defaultdict(int)
    for soundex_code in normalized_names:
        name_count[most_frequent_names.get(soundex_code, soundex_code)] += normalized_names[soundex_code]

    # Filter out hares with fewer than 2 trails (kept for the original plot/top 100 logic)
    filtered_name_count = {name: count for name, count in name_count.items() if count >= 2}
    
    # Store ALL unique hares for the new total count printout
    all_unique_hare_counts = dict(sorted(name_count.items(), key=lambda x: x[1], reverse=True))

    sorted_names = sorted(filtered_name_count.items(), key=lambda x: x[1], reverse=True)

    # Plot top 10
    top_10 = sorted_names[:10]
    if top_10:
        names_10, counts_10 = zip(*top_10)

        plt.figure(figsize=(10, 6))
        plt.barh(names_10, counts_10, color='skyblue')
        plt.xlabel('Count')
        plt.ylabel('Hare Names')
        plt.title(f'Top 10 Hares ({directory_name}{date_suffix})')
        plt.gca().invert_yaxis()
        plt.show()

    # Plot top 100
    top_50 = sorted_names[:100]
    if top_50:
        names_50, counts_50 = zip(*top_50)

        plt.figure(figsize=(12, 8))
        plt.barh(names_50, counts_50, color='skyblue')
        plt.xlabel('Count')
        plt.ylabel('Hare Names')
        plt.title(f'Top Hares ({directory_name}{date_suffix})')
        plt.gca().invert_yaxis()
        plt.show()

    # Print the total count for every hare name (New Section)
    print("\n\nAll Hare Counts (Total Unique Hares):")
    total_unique_hares = len(all_unique_hare_counts)
    print(f"Total Unique Hares Found: {total_unique_hares}")
    print("---------------------------------------")
    for name, count in all_unique_hare_counts.items():
        print(f"{name}: {count}")
    print("---------------------------------------")

    # Print the top 100 hares and their counts (Existing Section)
    print(f"\nTop 100 Hare Names (Minimum 2 Trails):")
    for name, count in sorted_names[:100]:
        print(f"{name}: {count}")

    # Print all near names for each hare name (Existing Section)
    print("\nAll Near Names for Each Hare Name:")
    for soundex_code, name_counts in all_name_matches.items():
        main_name = most_frequent_names.get(soundex_code, soundex_code)
        sorted_near_names = sorted(name_counts.items(), key=lambda x: x[1], reverse=True)
        # Check if the main name has at least 1 count (or 2 if using the filtered list)
        if main_name in name_count:
            print(f"{main_name} (Total Count: {name_count[main_name]}):")
            for name, count in sorted_near_names:
                if name != main_name:
                    print(f"  - {name} (Count: {count})")

    # Print exact hare strings that matched each hare (Existing Section)
    print("\nExact Hare Strings That Matched Each Hare:")
    for hare, strings in hare_string_matches.items():
        # Only print hares that have a count (i.e., not in IGNORE_HARES)
        if hare in name_count:
            print(f"{hare} (Total Count: {name_count[hare]}):")
            # Print the top 5 unique original strings for brevity
            for s in set(strings):
                print(f"  - {s}")
                
# --- Main Function with Argument Parsing (Original Code) ---

def main(directory, date_range):
    hare_names = read_files_from_directory(directory, date_range)
    if not hare_names:
        print("No hare names found after filtering.")
        return
    normalized_names, most_frequent_names, all_name_matches, hare_string_matches = normalize_hare_names(hare_names)
    plot_hare_names(normalized_names, most_frequent_names, all_name_matches, hare_string_matches, directory, date_range)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Analyze hare names from tab-separated files.")
    parser.add_argument("directory", type=str, help="The path to the directory containing the .txt files.")
    parser.add_argument("--date", type=str, dest="date_range", default=None,
                        help="An optional date range filter in the format 'YYYY-MM to YYYY-MM'.")
    
    args = parser.parse_args()
    
    main(args.directory, args.date_range)