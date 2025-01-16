import os
import sys
import io
import pandas as pd
import matplotlib.pyplot as plt
from collections import defaultdict
import re

# Ensure UTF-8 encoding for output
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

def read_calendar_updates(directory):
    update_counts = defaultdict(int)

    for filename in os.listdir(directory):
        if filename.endswith('.txt'):
            filepath = os.path.join(directory, filename)
            print(f"Processing file: {filepath}")
            try:
                df = pd.read_csv(filepath, sep='\t', header=0, encoding='utf-8')
                if 'UPDATE' in df.columns:
                    for update_entry in df['UPDATE'].dropna():
                        match = re.search(r'\((.*?)\)$', str(update_entry))
                        if match:
                            updater = match.group(1).strip()
                            update_counts[updater] += 1
                else:
                    print(f"Warning: {filepath} does not contain an 'UPDATE' column.")
            except Exception as e:
                print(f"Error reading {filepath}: {e}")

    return update_counts

def plot_update_counts(update_counts):
    sorted_counts = sorted(update_counts.items(), key=lambda x: x[1], reverse=True)[:10]
    if sorted_counts:
        updaters, counts = zip(*sorted_counts)
        plt.figure(figsize=(10, 6))
        plt.barh(updaters, counts, color='skyblue')
        plt.xlabel('Update Count')
        plt.ylabel('Updater Names')
        plt.title('Top 10 Calendar Updaters')
        plt.gca().invert_yaxis()
        plt.show()
    else:
        print("No updates found to plot.")

def main(directory):
    update_counts = read_calendar_updates(directory)
    print("\nSummary of Updates:")
    for updater, count in sorted(update_counts.items(), key=lambda x: x[1], reverse=True):
        print(f"{updater}: {count} updates")

    plot_update_counts(update_counts)

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print("Usage: python chart_updates.py <directory_path>")
        sys.exit(1)
    directory = sys.argv[1]
    main(directory)
