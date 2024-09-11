import os
import csv
import re
import matplotlib.pyplot as plt
from datetime import datetime, timedelta

# Function to extract hashcash values
def extract_hashcash(value):
    # Use regex to find the first occurrence of a dollar amount
    match = re.search(r'\$([\d.]+)', value)
    return float(match.group(1)) if match else None

# Function to process a single file
def process_file(filepath, year, month):
    data = []
    with open(filepath, 'r', encoding='utf-8', errors='replace') as file:
        reader = csv.reader(file, delimiter='\t')
        header = next(reader)  # Skip the header
        for row in reader:
            if len(row) < 11:
                # print(f'Skipping row with insufficient columns: {row}')
                continue  # Skip rows that do not have enough columns
            hashcash_value = extract_hashcash(row[9])  # HASHCASH is the 10th column (index 9)
            if hashcash_value is not None:
                if hashcash_value >= 20.0:
                    print("Spensive: " + str(hashcash_value) +  " Hash:" + row[3])
                try:
                    day = int(row[0])  # DAY is the 1st column (index 0)
                    date = datetime(year, month, day)
                    data.append((date, hashcash_value))
                except ValueError:
                    # Print rows where the DAY column does not contain a valid integer
                    print(f'Invalid day value in row: {row}')
                    continue
            else:
                print(f'No valid hashcash value in row: {row}')
                continue
    return data

# Main function to process all files in a directory
def process_directory(directory):
    all_data = []
    for filename in os.listdir(directory):
        if filename.endswith('.txt'):
            print(f'Processing file: {filename}')
            filepath = os.path.join(directory, filename)
            # Extract year and month from the filename
            try:
                year, month = map(int, filename.split('.')[0].split('-'))
                file_data = process_file(filepath, year, month)
                all_data.extend(file_data)
            except ValueError:
                print(f'Invalid filename format: {filename}')
                continue
    return all_data

# Approximate Miller Lite price data (from 2012 to 2024)
miller_lite_prices = {
    datetime(2012, 1, 1): 1.50,
    datetime(2013, 1, 1): 1.55,
    datetime(2014, 1, 1): 1.60,
    datetime(2015, 1, 1): 1.65,
    datetime(2016, 1, 1): 1.70,
    datetime(2017, 1, 1): 1.75,
    datetime(2018, 1, 1): 1.80,
    datetime(2019, 1, 1): 1.85,
    datetime(2020, 1, 1): 1.90,
    datetime(2021, 1, 1): 1.95,
    datetime(2022, 1, 1): 2.00,
    datetime(2023, 1, 1): 2.05,
    datetime(2024, 1, 1): 2.10
}

# Interpolating Miller Lite prices for all dates
def interpolate_miller_lite_prices(start_date, end_date, prices):
    current_date = start_date
    interpolated_prices = {}
    while current_date <= end_date:
        previous_date = max([date for date in prices if date <= current_date])
        interpolated_prices[current_date] = prices[previous_date]
        current_date += timedelta(days=1)
    return interpolated_prices

# Prompt for directory
directory = input("Please enter the directory path containing the .txt files: ")

# Process directory
all_data = process_directory(directory)

# Sorting data by date
all_data.sort(key=lambda x: x[0])

# Debugging output
print(f"Total data points collected: {len(all_data)}")

# Extracting dates and hashcash values
if all_data:
    dates, hashcash_values = zip(*all_data)

    # Generating all dates from the minimum to the maximum date in the data
    min_date, max_date = min(dates), max(dates)
    all_dates = [min_date + timedelta(days=i) for i in range((max_date - min_date).days + 1)]

    # Interpolating Miller Lite prices for the dates in hashcash data
    interpolated_prices = interpolate_miller_lite_prices(min_date, max_date, miller_lite_prices)

    # Extracting the interpolated Miller Lite prices corresponding to the hashcash dates
    miller_lite_values = [interpolated_prices[date] for date in dates]

    # Plotting the data
    plt.figure(figsize=(12, 6))
    plt.plot(dates, hashcash_values, label='Hashcash ($)', marker='o')
    plt.plot(dates, miller_lite_values, label='Miller Lite (24 oz)', marker='x')
    plt.xlabel('Date')
    plt.ylabel('Price ($)')
    plt.title('Hashcash vs. Miller Lite Price Over Time')
    plt.legend()
    plt.grid(True)
    plt.show()
else:
    print("No valid data points found.")
