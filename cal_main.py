# cal_main.py

import argparse
from cal_genfiles import generate_files_for_year

if __name__ == "__main__":
    # Argument parser to get year input from the command line
    parser = argparse.ArgumentParser(description="Generate calendar and android files for the given year.")
    parser.add_argument("year", type=int, help="The year for which to generate the files (e.g., 2038).")
    parser.add_argument("--clobber", action="store_true", 
                        help="Overwrite existing events instead of preserving them (default: preserve existing events)")
    args = parser.parse_args()
    
    generate_files_for_year(args.year, clobber=args.clobber)