# hash-viz
Tools for visualizing data mined from the website dfwhhh.org, for Hash House Harriers. (If you don't know what that is, go to https://www.hashhouseharriers.com/what-is-hashing/ )

## Requirements

Python, some programs may require Python 2.7 due to use of older libraries. Others, Python 3.x may be ok.

Inputs, use the *.txt files from the DFW HHH website scraped from the public folder called 'android'. These represent all of the dates on the calendar of that website, from June 2013 until the present. The data is in tab-separated value text files and is loose with allowed characters and typos. Each file contains a header describing the columns. Each file represents 1 month of events, and they have names like '2024-07.txt' . An example file is included.

## Usage

To run any of these, prepare a directory of data files for only what you are interested in visualizing. For example, you can drop in only data from 2021. Then **python <nameOfProgram.py> <directoryOfData>**  to run 

### makerank.py

This program is designed to rank hares by how many trails they have laid. It uses the soundex algorithm to group hares by similar name (so Eeyeeyeey and I-I-I and III would group into the most common spelling, for example). The output is a pandas graph to the local display showing Top 10, Top 50, ranked by total number of trails laid. The graph can be saved as an image. The program also generates a report of all fuzzy name matches it generated (in case you want to investigate further, if the numbers seem wrong). There is also a manual mapping array in the code which can be edited, for hares that have multiple names.

Usage: python makerank.py <data directory containing 'android' *.txt files>

### makemap.py

This program generates an HTML map of the locations found in the data files. The map is zoomable / interactive. Each dot on the map is color-coded by kennel, and can be clicked on for more information about that hash run. An example map.html is provided. The latitude / longitude lookup method for the locations is first via associated map URL. If the program fails to find the location that way, it falls back to using the Nominatim geocode API from OpenStreetMaps.org. 

**Note: The program should be compliant to their Terms of Use but I have had issues with my IP address getting blocked from OSM for making too many requests, so be aware of this possibility.**

Usage: python makerank.py <data directory containing 'android' *.txt files>

### makecityrank.py

Similar to makerank.py, this program generates a bar chart of the number of hash runs that have been in each zip code, from the data. It also tries to map zip codes to city names and generates another associated bar chart.

Usage: python makecityrank.py <data directory containing 'android' *.txt files>

### 