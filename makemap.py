import os
import re
import csv
import argparse
import requests
from geopy.geocoders import Nominatim
from geopy.exc import GeocoderTimedOut
import folium
from geopy.distance import geodesic
import time
import json
from fuzzywuzzy import process
import sys

# Dallas, TX coordinates
DALLAS_COORDINATES = (32.7767, -96.7970)
MAX_DISTANCE_MILES = 100
CACHE_FILE = 'geocode_cache.json'

KENNEL_COLORS = {
    "NODUH Hash": "orange",
    "Happy Hour": "green",
    "Bike Hash": "yellow",
    "Fort Worth Hash": "red",
    "Dallas Hash": "blue",
    "Dallas Urban Hash": "purple",
    "Full Moon": "black",
    "Grapevine Quarterly": "violet"
}

def load_cache():
    if os.path.exists(CACHE_FILE):
        with open(CACHE_FILE, 'r') as cache_file:
            return json.load(cache_file)
    return {}

def save_cache(cache):
    with open(CACHE_FILE, 'w') as cache_file:
        json.dump(cache, cache_file)

def extract_addresses(text):
    addresses = []
    map_links = []
    reader = csv.reader(text.splitlines(), delimiter='\t')
    for row in reader:
        if len(row) > 8:
            address = row[7]
            map_link = row[8]
            address = re.sub(r'\bStart\b', '', address, flags=re.IGNORECASE).strip()
            address = address.replace('<br>', ' ').replace('<br />', ' ')
            address = re.sub(r'[^\x00-\x7F]+', '', address)
            addresses.append((row[1], row[3], row[13], address, row[5]))  # (kennel, title, date, address, run)
            map_links.append(map_link)
    return addresses, map_links

def clean_address(address):
    match = re.search(r'\d', address)
    if match:
        address = address[match.start():]
    
    match = re.search(r'\b7\d{4}\b', address)
    if match:
        zipcode_start = match.start()
        address = address[:zipcode_start + 5]
    
    return address

def expand_url(short_url):
    try:
        response = requests.head(short_url, allow_redirects=True, headers={"User-Agent": "Hash House Harriers calendar map"})
        return response.url
    except requests.RequestException as e:
        print(f"Error expanding URL {short_url}: {e}")
        return None

def extract_coords_from_url(url):
    # Handle @lat,lon format
    match = re.search(r'@(-?\d+\.\d+),(-?\d+\.\d+)', url)
    if match:
        return (float(match.group(1)), float(match.group(2)))
    
    # Handle ll=lat,lon format
    match = re.search(r'll=(-?\d+\.\d+),(-?\d+\.\d+)', url)
    if match:
        return (float(match.group(1)), float(match.group(2)))
    
    # Handle maps?q=lat,+lon format
    match = re.search(r'q=(-?\d+\.\d+),(-?\d+\.\d+)', url)
    if match:
        return (float(match.group(1)), float(match.group(2)))
    
    # Handle maps/search/lat,+lon format
    match = re.search(r'search/(-?\d+\.\d+),(-?\d+\.\d+)', url)
    if match:
        return (float(match.group(1)), float(match.group(2)))
    
    return None

def geocode_address(geolocator, address, cache, attempt=1):
    if address in cache:
        return cache[address]
    try:
        location = geolocator.geocode(address, timeout=3)
        if location:
            distance = geodesic((location.latitude, location.longitude), DALLAS_COORDINATES).miles
            if distance > MAX_DISTANCE_MILES:
                if attempt == 1:
                    address = address.replace(", Dallas/Fort Worth, TX", ", Dallas, TX")
                    return geocode_address(geolocator, address, cache, attempt=2)
                elif attempt == 2:
                    address = address.replace(", Dallas/Fort Worth, TX", ", Fort Worth, TX")
                    return geocode_address(geolocator, address, cache, attempt=3)
            cache[address] = (location.latitude, location.longitude)
            save_cache(cache)
            return (location.latitude, location.longitude)
        else:
            cleaned_address = clean_address(address)
            if cleaned_address != address:
                return geocode_address(geolocator, cleaned_address, cache, attempt=4)
    except GeocoderTimedOut:
        time.sleep(1)  # Wait a bit before retrying
        return geocode_address(geolocator, address, cache, attempt)
    except Exception as e:
        print(f"Error geocoding address {address}: {e}")
    return None

def get_kennel_color(kennel):
    kennel_match = process.extractOne(kennel, KENNEL_COLORS.keys())
    if kennel_match and kennel_match[1] >= 80:  # Fuzzy match threshold
        return KENNEL_COLORS[kennel_match[0]]
    return "white"

def create_map(addresses):
    valid_addresses = {addr: coords for addr, coords in addresses.items() if coords}

    if not valid_addresses:
        print("No valid addresses to map.")
        return None

    initial_coords = next(iter(valid_addresses.values()), DALLAS_COORDINATES)
    m = folium.Map(location=initial_coords, zoom_start=10)

    for (kennel, title, date, address, run), coords in valid_addresses.items():
        date_formatted = date.replace("-", "/")
        popup_text = f"{date_formatted}\n{title}\n{kennel} #{run}\n{address}"
        color = get_kennel_color(kennel)
        folium.Marker(location=coords, popup=popup_text, icon=folium.Icon(color=color)).add_to(m)
    
    return valid_addresses, m

def main(directory, kennel_filter=None):
    geolocator = Nominatim(user_agent="Hash House Harriers calendar map", timeout=3)
    addresses = {}
    total_rows_processed = 0
    total_valid_addresses = 0
    total_files_processed = 0
    total_failed_geocodes = 0

    cache = load_cache()

    txt_files = [f for f in os.listdir(directory) if f.endswith('.txt')]
    total_files = len(txt_files)

    for idx, filename in enumerate(txt_files):
        filepath = os.path.join(directory, filename)
        total_files_processed += 1
        try:
            with open(filepath, 'r', encoding='utf-8') as file:
                text = file.read()
        except UnicodeDecodeError:
            print(f"Error decoding file {filepath}, skipping.")
            continue
        except Exception as e:
            print(f"Error reading file {filepath}: {e}, skipping.")
            continue

        found_addresses, found_map_links = extract_addresses(text)
        total_rows_processed += len(found_addresses)
        for (kennel, title, date, address, run), map_link in zip(found_addresses, found_map_links):
            if kennel_filter and kennel_filter.lower() not in kennel.lower():
                continue  # Skip rows that do not match the kennel filter
            
            if address.strip():
                coords = None
                
                if map_link:
                    expanded_url = expand_url(map_link)
                    if expanded_url:
                        coords = extract_coords_from_url(expanded_url)
                    
                if not coords:
                    coords = geocode_address(geolocator, address, cache)
                
                if coords:
                    total_valid_addresses += 1
                else:
                    total_failed_geocodes += 1
                addresses[(kennel, title, date, address, run)] = coords
        
        # Print progress every 10 files
        if (idx + 1) % 10 == 0 or (idx + 1) == total_files:
            progress = (idx + 1) / total_files * 100
            print(f"Processed {idx + 1}/{total_files} files ({progress:.2f}%) - {filename}")
            sys.stdout.flush()

    valid_addresses, map_obj = create_map(addresses)
    if map_obj:
        map_obj.save('map.html')
        print("Map has been created and saved as 'map.html'.")
    else:
        print("No valid addresses to map.")

    print(f"\nTotal files processed: {total_files_processed}")
    print(f"Total rows processed (excluding files): {total_rows_processed - total_files_processed}")
    print(f"Total dots added to map: {total_valid_addresses}")
    print(f"Total addresses that failed to geocode: {total_failed_geocodes}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Process a directory of TSV files containing addresses.")
    parser.add_argument('directory', type=str, help="Path to the directory containing the TSV files.")
    parser.add_argument('--kennel', type=str, help="Filter by specific kennel (case insensitive).", default=None)
    args = parser.parse_args()
    main(args.directory, args.kennel)
