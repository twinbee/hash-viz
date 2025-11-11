#!/bin/bash

# --- Configuration ---
FTP_HOST="ftp.kattare.com"
FTP_USER="username"
FTP_PASS="password"
REMOTE_ROOT="dfwhhh_org"

# --- Dynamic Path & Directory Setup ---
CURRENT_YEAR=$(date +%Y)
CALENDAR_DIR="calendar/$CURRENT_YEAR"
ANDROID_DIR="android"

# Main local directory where the final zip will be stored
BACKUP_DIR="$HOME/dfwhhh_bak" 
TEMP_DIR="$BACKUP_DIR/temp_download"
START_DIR=$(pwd) # Store the initial working directory

# --- Naming Convention ---
DATE_STRING=$(date +%Y-%m-%d)
# Name the zip file to indicate it's a selective backup
ZIP_FILENAME="selective_backup_${DATE_STRING}.zip"

# --- Execution ---

# 1. Create the local backup directory structure
mkdir -p "$TEMP_DIR"

echo "Starting selective download from $FTP_HOST/$REMOTE_ROOT..."

# 2. Change into the temporary directory
cd "$TEMP_DIR" || { echo "ERROR: Could not change to $TEMP_DIR. Exiting."; exit 1; }

# 3. Use lftp to download the two specific directories
lftp -c "open -u $FTP_USER,$FTP_PASS $FTP_HOST; \
         set ftp:ssl-allow no; \
         cd $REMOTE_ROOT; \
         # Mirror the 'android' folder
         mirror --no-empty-dirs $ANDROID_DIR; \
         # Mirror the 'calendar/YYYY' folder
         mirror --no-empty-dirs $CALENDAR_DIR; \
         bye"

# Check the exit status of lftp
if [ $? -eq 0 ]; then
  echo "Download complete. Zipping selected folders ($ANDROID_DIR and $CALENDAR_DIR)..."
  
  # 4. Zip up the downloaded contents from the current directory ($TEMP_DIR)
  # -r: recurse into subdirectories
  # -j is NOT used here, as we need to preserve the relative paths (android/ and calendar/...)
  zip -r "$BACKUP_DIR/$ZIP_FILENAME" .
  
  # 5. Clean up the temporary directory after zipping
  echo "Cleaning up temporary files..."
  # Change back to the starting directory
  cd "$START_DIR" 
  rm -rf "$TEMP_DIR"

  echo "Selective backup successfully created: $BACKUP_DIR/$ZIP_FILENAME"
else
  echo "ERROR: lftp failed to download the selected directories. Check network connectivity or folder names."
  # Ensure clean up of the failed attempt's temporary folder
  cd "$START_DIR" 
  rm -rf "$TEMP_DIR"
fi

exit 0