import os
import sys
import pandas as pd
import matplotlib.pyplot as plt
from collections import defaultdict
import re

# Ensure UTF-8 encoding for output
#sys.stdout = sys.stdout.reconfigure(encoding='utf-8')

def read_calendar_updates(directory):
    """Read all TSV files in a directory and count updates by person."""
    update_counts = defaultdict(int)
    for filename in os.listdir(directory):
        if filename.endswith('.txt'):
            filepath = os.path.join(directory, filename)
            try:
                print(f"Reading file: {filepath}")
                # Load the TSV file
                df = pd.read_csv(filepath, sep='\t', header=0, encoding='utf-8')
                print(f"File loaded successfully. Columns: {list(df.columns)}")

                # Find the 'UPDATE' column dynamically
                update_col = None
                for col in df.columns:
                    if 'UPDATE' in col.upper():
                        update_col = col
                        break

                if update_col:
                    print(f"Processing '{update_col}' column...")
                    # Extract update information from the identified 'UPDATE' column
                    for idx, update_entry in enumerate(df[update_col]):
                        if pd.isna(update_entry):
                            print(f"Row {idx}: '{update_col}' column is empty or NaN in file {filename}.")
                            continue

                        # Parse updater information
                        match = re.search(r'\((.*?)\)', str(update_entry))  # Updated regex
                        if match:
                            updater = match.group(1).strip()
                            update_counts[updater] += 1
                            print(f"File {filename}, Row {idx}: Found updater '{updater}'")
                        else:
                            print(f"File {filename}, Row {idx}: No updater found in '{update_entry}'")
                else:
                    print(f"Warning: 'UPDATE' column not found in file: {filename}")

            except Exception as e:
                print(f"Error reading file {filepath}: {e}")

    return update_counts

def plot_update_counts(update_counts):
    """Generate a bar chart for the update counts."""
    sorted_counts = sorted(update_counts.items(), key=lambda x: x[1], reverse=True)[:50]
    if sorted_counts:
        updaters, counts = zip(*sorted_counts)
        plt.figure(figsize=(12, 10))
        plt.barh(updaters, counts, color='skyblue')
        plt.xlabel('Update Count')
        plt.ylabel('Updater Names')
        plt.title('Top 50 Calendar Updaters')
        plt.gca().invert_yaxis()
        plt.tight_layout()
        plt.show()
    else:
        print("No updates found to plot.")

def main(directory):
    print(f"Starting processing for directory: {directory}")
    update_counts = read_calendar_updates(directory)
    if not update_counts:
        print("No updates found in the files.")
        return

    print("\nSummary of Updates:")
    for updater, count in sorted(update_counts.items(), key=lambda x: x[1], reverse=True):
        print(f"{updater}: {count} updates")

    plot_update_counts(update_counts)

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print("Usage: python chart_updates.py <directory_path>")
        sys.exit(1)
    directory = sys.argv[1]
    if not os.path.isdir(directory):
        print(f"Error: {directory} is not a valid directory.")
        sys.exit(1)
    main(directory)
