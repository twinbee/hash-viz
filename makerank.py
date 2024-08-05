import os
import pandas as pd
import matplotlib.pyplot as plt
from collections import defaultdict
import fuzzy
from unidecode import unidecode

# Define name aliases
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
    'Three Strokes': '3 Strokes',
    'WDT': 'Wrong Dong Thong',
    'Wrong Dong Thong': 'Wrong Dong Thong',
    'MFS': 'Martha F Stewart',
}

IGNORE_HARES = {'Team C U  Next Tue', 'Yer Done', 'Mr E Hare', '', ' '}

def preprocess_name(name):
    # Remove unwanted substrings and preprocess the name
    name = name.replace('amp;', '')  # Remove amp;
    name = name.replace('sup>', '').replace('<sup/>', '')  
    name = name.replace('<br/>', ',').replace('<br />', ',')  # Treat <br/> and <br /> as comma
    name = name.replace('/', ',')  # Treat '/' as comma
    name = name.replace('Just ', '')  # Remove 'just '
    return name

def read_files_from_directory(directory):
    hare_names = []
    for filename in os.listdir(directory):
        if filename.endswith('.txt'):
            filepath = os.path.join(directory, filename)
            print(f"Processing file: {filepath}")  # Verbose output
            try:
                df = pd.read_csv(filepath, sep='\t', header=None, encoding='utf-8', skiprows=1)
                if df.shape[1] > 5:
                    hare_names.extend(df[5].dropna())
                else:
                    print(f"Warning: {filepath} does not have enough columns.")
            except UnicodeDecodeError as e:
                print(f"Unicode error in {filepath}: {e}")
            except Exception as e:
                print(f"Error reading {filepath}: {e}")
    print(f"Total hare names collected: {len(hare_names)}")  # Debugging
    return hare_names

def apply_aliases(name):
    # Apply name aliases
    normalized_name = NAME_ALIASES.get(name, name)
    return normalized_name

def normalize_hare_names(hare_names):
    soundex = fuzzy.Soundex(4)  # Create a Soundex object with a specific code length
    normalized_names = defaultdict(int)
    original_names = defaultdict(list)
    all_name_matches = defaultdict(lambda: defaultdict(int))

    for entry in hare_names:
        try:
            # Convert entry to ASCII and preprocess the name
            entry = unidecode(str(entry))
            entry = preprocess_name(entry)
            if entry in IGNORE_HARES:
                continue
            # Split by comma, "and", "/", "amp;", or "&", and normalize each hare name
            entries = [name.strip() for name in entry.replace('and', ',').replace('&', ',').split(',')]
            for name in entries:
                try:
                    # Convert name to ASCII and apply aliases
                    name = unidecode(name)
                    name = apply_aliases(name)
                    # Use Soundex to match similar names
                    soundex_code = soundex(name)
                    if name not in IGNORE_HARES:
                        normalized_names[soundex_code] += 1
                        original_names[soundex_code].append(name)
                        all_name_matches[soundex_code][name] += 1
                except UnicodeEncodeError as e:
                    print(f"Unicode encoding error for Soundex entry '{name}': {e}")
                except Exception as e:
                    print(f"Error processing Soundex entry '{name}': {e}")
        except UnicodeEncodeError as e:
            print(f"Unicode encoding error for entry '{entry}': {e}")
        except Exception as e:
            print(f"Error processing entry '{entry}': {e}")
    
    # Choose the most frequent original name for each Soundex code
    most_frequent_names = {code: max(set(names), key=names.count) for code, names in original_names.items()}
    
    print(f"Total unique Soundex codes: {len(most_frequent_names)}")  # Debugging
    
    return normalized_names, most_frequent_names, all_name_matches

def plot_hare_names(normalized_names, most_frequent_names, all_name_matches):
    # Configure matplotlib to handle Unicode
    plt.rcParams['axes.unicode_minus'] = False
    plt.rcParams['font.sans-serif'] = ['Arial', 'sans-serif']
    plt.rcParams['font.family'] = 'sans-serif'
    
    name_count = defaultdict(int)
    for soundex_code in normalized_names:
        name_count[most_frequent_names.get(soundex_code, soundex_code)] += normalized_names[soundex_code]

    sorted_names = sorted(name_count.items(), key=lambda x: x[1], reverse=True)

    # Plot top 10
    top_10 = sorted_names[:10]
    names_10, counts_10 = zip(*top_10)

    plt.figure(figsize=(10, 6))
    plt.barh(names_10, counts_10, color='skyblue')
    plt.xlabel('Count')
    plt.ylabel('Hare Names')
    plt.title('Top 10 Hare Names')
    plt.gca().invert_yaxis()
    plt.show()

    # Plot top 50
    top_50 = sorted_names[:50]
    names_50, counts_50 = zip(*top_50)

    plt.figure(figsize=(12, 8))
    plt.barh(names_50, counts_50, color='skyblue')
    plt.xlabel('Count')
    plt.ylabel('Hare Names')
    plt.title('Top 50 Hare Names')
    plt.gca().invert_yaxis()
    plt.show()

    # Print the top 100 hares and their counts
    print("\nTop 100 Hare Names:")
    for name, count in sorted(name_count.items(), key=lambda x: x[1], reverse=True)[:200]:
        print(f"{name}: {count}")

    # Print all near names for each hare name
    print("\nAll Near Names for Each Hare Name:")
    for soundex_code, name_counts in all_name_matches.items():
        main_name = most_frequent_names.get(soundex_code, soundex_code)
        sorted_near_names = sorted(name_counts.items(), key=lambda x: x[1], reverse=True)
        print(f"{main_name}:")
        for name, count in sorted_near_names:
            if name != main_name:
                print(f"  - {name} (Count: {count})")

def main(directory):
    hare_names = read_files_from_directory(directory)
    if not hare_names:
        print("No hare names found.")
        return
    normalized_names, most_frequent_names, all_name_matches = normalize_hare_names(hare_names)
    plot_hare_names(normalized_names, most_frequent_names, all_name_matches)

if __name__ == '__main__':
    directory = input("Enter the directory path: ")
    # Ensure the directory path is handled as Unicode
    directory = str(directory)
    main(directory)
