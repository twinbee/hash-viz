#!/bin/bash

# --- Configuration ---
FTP_HOST="ftp.kattare.com"
FTP_USER="username"
FTP_PASS="password"
REMOTE_DIR="dfwhhh_org"

# --- Directory Setup ---
# Main local directory where the final zip will be stored
BACKUP_DIR="$HOME/dfwhhh_bak" 
# Temporary directory for clean downloads
TEMP_DIR="$BACKUP_DIR/temp_download"
START_DIR=$(pwd) # Store the initial working directory

# --- Naming Convention ---
DATE_STRING=$(date +%Y-%m-%d)
ZIP_FILENAME="backup_${DATE_STRING}.zip"

# --- Execution ---

# 1. Create the local backup directory structure
mkdir -p "$TEMP_DIR"

echo "Connecting to $FTP_HOST and downloading files from /$REMOTE_DIR..."

# 2. Change into the temporary directory for the lftp operation
cd "$TEMP_DIR" || { echo "ERROR: Could not change to $TEMP_DIR. Exiting."; exit 1; }

# 3. Use lftp with the 'mirror' command for recursive download
lftp -c "open -u $FTP_USER,$FTP_PASS $FTP_HOST; \
         set ftp:ssl-allow no; \
         cd $REMOTE_DIR; \
         mirror -e .; \
         bye"

# Check the exit status of lftp
if [ $? -eq 0 ]; then
  echo "Download complete (including subdirectories). Zipping files..."

  # 4. Zip up the downloaded contents from the current directory ($TEMP_DIR)
  # FIX: Removed the '-j' flag to preserve directory paths and prevent duplicate file name errors.
  # The zip file will contain the 'android/', 'calendar/', etc. structure.
  zip -r "$BACKUP_DIR/$ZIP_FILENAME" .
  
  # 5. Clean up the temporary directory after zipping
  echo "Cleaning up temporary files..."
  # Change back to the starting directory 
  cd "$START_DIR" 
  rm -rf "$TEMP_DIR"

  echo "Backup successfully created: $BACKUP_DIR/$ZIP_FILENAME"
else
  echo "ERROR: lftp failed to download files. Check network connectivity or credentials."
  # Ensure clean up of the failed attempt's temporary folder
  cd "$START_DIR" 
  rm -rf "$TEMP_DIR"
fi

exit 0