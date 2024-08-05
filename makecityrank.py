import os
import pandas as pd
import matplotlib.pyplot as plt
from collections import Counter
from fuzzywuzzy import process
import re

# List of 117 DFW cities for reference
city_list = ["Addison", "Allen", "Alvarado", "Anna", "Argyle", "Arlington", "Aubrey", "Azle", "Balch Springs", 
              "Bardwell", "Bedford", "Benbrook", "Blue Ridge", "Bonham", "Boyd", "Bridgeport", "Burleson", 
              "Caddo Mills", "Carrollton", "Cedar Hill", "Celina", "Chandler", "Cleburne", "Colleyville", 
              "Coppell", "Copper Canyon", "Corinth", "Corsicana", "Crandall", "Crowley", "Dallas", "Decatur", 
              "Denton", "DeSoto", "Duncanville", "Edgewood", "Euless", "Everman", "Fairview", "Farmers Branch", 
              "Ferris", "Flower Mound", "Forney", "Fort Worth", "Frisco", "Garland", "Glen Rose", "Godley", 
              "Granbury", "Grand Prairie", "Grapevine", "Greenville", "Gun Barrel City", "Haltom City", "Haslet", 
              "Hickory Creek", "Highland Village", "Hurst", "Irving", "Joshua", "Kaufman", "Keller", "Kennedale", 
              "Krum", "Lancaster", "Lavon", "Lewisville", "Little Elm", "Mabank", "Mansfield", "McKinney", 
              "Melissa", "Mesquite", "Midlothian", "Muenster", "Murphy", "Nevada", "North Richland Hills", 
              "Oak Point", "Ovilla", "Palmer", "Parker", "Pilot Point", "Plano", "Prosper", "Red Oak", "Richardson", 
              "Roanoke", "Rockwall", "Rowlett", "Royse City", "Sachse", "Saginaw", "Sanger", "Seagoville", 
              "Southlake", "Springtown", "Sunnyvale", "Terrell", "The Colony", "Trophy Club", "Venus", "Watauga", 
              "Waxahachie", "Weatherford", "Westlake", "Westworth Village", "White Settlement", "Wylie"]

# Example ZIP code to city mapping
zip_to_city = {
    "75001": "Addison", "75006": "Carrollton", "75007": "Carrollton", "75009": "Celina",
    "75010": "Carrollton", "75011": "Carrollton", "75013": "Allen", "75014": "Irving",
    "75015": "Irving", "75016": "Irving", "75017": "Irving", "75019": "Coppell",
    "75020": "Denison", "75021": "Denison", "75022": "Flower Mound", "75023": "Plano",
    "75024": "Plano", "75025": "Plano", "75026": "Plano", "75027": "Flower Mound",
    "75028": "Flower Mound", "75029": "Flower Mound", "75030": "Rowlett", "75032": "Rockwall",
    "75034": "Frisco", "75035": "Frisco", "75036": "Frisco", "75038": "Irving",
    "75039": "Irving", "75040": "Garland", "75041": "Garland", "75042": "Garland",
    "75043": "Garland", "75044": "Garland", "75045": "Garland", "75046": "Garland",
    "75047": "Garland", "75048": "Sachse", "75049": "Garland", "75050": "Grand Prairie",
    "75051": "Grand Prairie", "75052": "Grand Prairie", "75053": "Grand Prairie",
    "75054": "Grand Prairie", "75056": "The Colony", "75057": "Lewisville", "75058": "Gunter",
    "75060": "Irving", "75061": "Irving", "75062": "Irving", "75063": "Irving",
    "75065": "Lake Dallas", "75067": "Lewisville", "75068": "Little Elm", "75069": "McKinney",
    "75070": "McKinney", "75071": "McKinney", "75072": "McKinney", "75074": "Plano",
    "75075": "Plano", "75076": "Pottsboro", "75077": "Lewisville", "75078": "Prosper",
    "75080": "Richardson", "75081": "Richardson", "75082": "Richardson", "75083": "Richardson",
    "75085": "Richardson", "75086": "Plano", "75087": "Rockwall", "75088": "Rowlett",
    "75089": "Rowlett", "75090": "Sherman", "75091": "Sherman", "75092": "Sherman",
    "75093": "Plano", "75094": "Plano", "75097": "Weston", "75098": "Wylie",
    "75099": "Coppell", "75101": "Avalon", "75102": "Barry", "75103": "Canton",
    "75104": "Cedar Hill", "75105": "Corsicana", "75106": "Cedar Hill", "75109": "Corsicana",
    "75110": "Corsicana", "75114": "Crandall", "75115": "Desoto", "75116": "Duncanville",
    "75117": "Edgewood", "75118": "Eustace", "75119": "Ennis", "75120": "Ennis",
    "75121": "Eustace", "75123": "Desoto", "75124": "Gun Barrel City", "75125": "Ferris",
    "75126": "Forney", "75127": "Fruitvale", "75132": "Fate", "75134": "Lancaster",
    "75135": "Caddo Mills", "75137": "Duncanville", "75138": "Duncanville", "75140": "Grand Saline",
    "75141": "Hutchins", "75142": "Kaufman", "75143": "Kemp", "75144": "Kerens",
    "75146": "Lancaster", "75147": "Mabank", "75148": "Mabank", "75149": "Mesquite",
    "75150": "Mesquite", "75151": "Corsicana", "75152": "Palmer", "75153": "Mabank",
    "75154": "Red Oak", "75155": "Rice", "75156": "Mabank", "75157": "Scurry",
    "75158": "Seagoville", "75159": "Seagoville", "75160": "Terrell", "75161": "Terrell",
    "75163": "Trinidad", "75164": "Royse City", "75165": "Waxahachie", "75166": "Lavon",
    "75167": "Waxahachie", "75168": "Waxahachie", "75169": "Wills Point", "75172": "Wilmer",
    "75173": "Nevada", "75180": "Balch Springs", "75181": "Mesquite", "75182": "Sunnyvale",
    "75185": "Mesquite", "75187": "Mesquite", "75189": "Royse City", "75201": "Dallas",
    "75202": "Dallas", "75203": "Dallas", "75204": "Dallas", "75205": "Dallas",
    "75206": "Dallas", "75207": "Dallas", "75208": "Dallas", "75209": "Dallas",
    "75210": "Dallas", "75211": "Dallas", "75212": "Dallas", "75214": "Dallas",
    "75215": "Dallas", "75216": "Dallas", "75217": "Dallas", "75218": "Dallas",
    "75219": "Dallas", "75220": "Dallas", "75221": "Dallas", "75222": "Dallas",
    "75223": "Dallas", "75224": "Dallas", "75225": "Dallas", "75226": "Dallas",
    "75227": "Dallas", "75228": "Dallas", "75229": "Dallas", "75230": "Dallas",
    "75231": "Dallas", "75232": "Dallas", "75233": "Dallas", "75234": "Dallas",
    "75235": "Dallas", "75236": "Dallas", "75237": "Dallas", "75238": "Dallas",
    "75240": "Dallas", "75241": "Dallas", "75242": "Dallas", "75243": "Dallas",
    "75244": "Dallas", "75245": "Dallas", "75246": "Dallas", "75247": "Dallas",
    "75248": "Dallas", "75249": "Dallas", "75250": "Dallas", "75251": "Dallas",
    "75252": "Dallas", "75253": "Dallas", "75254": "Dallas", "75260": "Dallas",
    "75261": "Dallas", "75262": "Dallas", "75263": "Dallas", "75264": "Dallas",
    "75265": "Dallas", "75266": "Dallas", "75267": "Dallas", "75270": "Dallas",
    "75275": "Dallas", "75277": "Dallas", "75283": "Dallas", "75284": "Dallas",
    "75285": "Dallas", "75287": "Dallas", "75301": "Dallas", "75303": "Dallas",
    "75312": "Dallas", "75313": "Dallas", "75315": "Dallas", "75320": "Dallas",
    "75323": "Dallas", "75326": "Dallas", "75336": "Dallas", "75339": "Dallas",
    "75340": "Dallas", "75342": "Dallas", "75354": "Dallas", "75355": "Dallas",
    "75356": "Dallas", "75357": "Dallas", "75358": "Dallas", "75359": "Dallas",
    "75360": "Dallas", "75367": "Dallas", "75370": "Dallas", "75371": "Dallas",
    "75372": "Dallas", "75374": "Dallas", "75376": "Dallas", "75378": "Dallas",
    "75379": "Dallas", "75380": "Dallas", "75381": "Dallas", "75382": "Dallas",
    "75389": "Dallas", "75390": "Dallas", "75391": "Dallas", "75392": "Dallas",
    "75393": "Dallas", "75394": "Dallas", "75395": "Dallas", "75397": "Dallas",
    "75398": "Dallas", 
}

def process_files(directory):
    city_counts = {}
    
    for filename in os.listdir(directory):
        if filename.endswith(".txt"):
            file_path = os.path.join(directory, filename)
            try:
                df = pd.read_csv(file_path, sep='\t', header=None)
                
                for index, row in df.iterrows():
                    address = row[7] if len(row) > 7 else ''
                    if isinstance(address, str):  # Ensure address is a string
                        address = re.sub(r'<br\s*/?>', ' ', address)  # Replace HTML-like break tags with space
                        city_name = extract_city_from_address(address)
                        if not city_name:
                            zip_code = extract_zip_from_address(address)
                            city_name = zip_to_city.get(zip_code, '')
                        if city_name:
                            city_counts[city_name] = city_counts.get(city_name, 0) + 1
                        else:
                            print(f"No match found for address: {address.encode('utf-8', 'replace').decode('utf-8')}")
            except Exception as e:
                print(f"Error processing file {filename}: {e}")
    
    return city_counts

def extract_city_from_address(address):
    city_name = ''
    for city in city_list:
        if fuzz.partial_ratio(address, city) > 80:
            city_name = city
            break
    return city_name

def extract_zip_from_address(address):
    match = re.search(r'\b\d{5}\b', address)
    return match.group(0) if match else ''

def main():
    directory = '.'
    city_counts = process_files(directory)
    
    # Sort and plot the results
    sorted_city_counts = dict(sorted(city_counts.items(), key=lambda item: item[1], reverse=True))
    plt.bar(sorted_city_counts.keys(), sorted_city_counts.values())
    plt.xlabel('City')
    plt.ylabel('Count')
    plt.title('City Counts')
    plt.xticks(rotation=90)
    plt.tight_layout()
    plt.show()

if __name__ == "__main__":
    main()