#!/usr/bin/env python3
"""
fixup.py - Batch Event File Editor
Modifies hash event data files by kennel and field
"""

import os
import sys
import glob
from datetime import datetime

# Field names and indices
FIELDS = {
    0: "DAY",
    1: "KENNEL",
    2: "TYPE/ICON",
    3: "TITLE",
    4: "RUN",
    5: "HARES",
    6: "TIME",
    7: "ADDRESS",
    8: "MAPLINK",
    9: "HASHCASH",
    10: "TURDS",
    11: "TWEET",
    12: "TWILIGHT",
    13: "DATE",
    14: "DESC",
    15: "UPDATED"
}

def read_event_files(directory):
    """Read all .txt files in the directory"""
    pattern = os.path.join(directory, "*.txt")
    files = glob.glob(pattern)
    
    if not files:
        print(f"No .txt files found in {directory}")
        return []
    
    print(f"Found {len(files)} event file(s)")
    return sorted(files)

def get_kennels_from_files(files):
    """Extract unique kennel names from all files"""
    kennels = set()
    
    for filepath in files:
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                for i, line in enumerate(f):
                    if i == 0:  # Skip header
                        continue
                    fields = line.strip().split('\t')
                    if len(fields) > 1:
                        kennels.add(fields[1])
        except Exception as e:
            print(f"Error reading {filepath}: {e}")
    
    return sorted(kennels)

def display_menu(items, title):
    """Display a numbered menu and get selection"""
    print(f"\n{title}")
    print("=" * 50)
    for i, item in enumerate(items, 1):
        print(f"{i}. {item}")
    print("0. Cancel")
    
    while True:
        try:
            choice = int(input("\nSelect number: "))
            if choice == 0:
                return None
            if 1 <= choice <= len(items):
                return items[choice - 1]
            print("Invalid selection. Try again.")
        except ValueError:
            print("Please enter a number.")

def preview_changes(files, kennel, field_index, action, new_value):
    """Preview what changes will be made"""
    print("\n" + "=" * 70)
    print("PREVIEW OF CHANGES")
    print("=" * 70)
    
    total_matches = 0
    
    for filepath in files:
        file_matches = 0
        print(f"\nFile: {os.path.basename(filepath)}")
        
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                lines = f.readlines()
                
            for i, line in enumerate(lines):
                if i == 0:  # Skip header
                    continue
                    
                fields = line.strip().split('\t')
                if len(fields) > field_index and fields[1] == kennel:
                    file_matches += 1
                    old_value = fields[field_index]
                    
                    if action == "replace":
                        new_field_value = new_value
                    else:  # append
                        new_field_value = old_value + new_value
                    
                    # Show preview (truncate if too long)
                    old_display = old_value[:60] + "..." if len(old_value) > 60 else old_value
                    new_display = new_field_value[:60] + "..." if len(new_field_value) > 60 else new_field_value
                    
                    print(f"  Day {fields[0]:>2} - OLD: {old_display}")
                    print(f"          NEW: {new_display}")
            
            if file_matches > 0:
                print(f"  ({file_matches} event(s) will be modified)")
                total_matches += file_matches
        
        except Exception as e:
            print(f"  Error: {e}")
    
    print("\n" + "=" * 70)
    print(f"TOTAL: {total_matches} event(s) will be modified")
    print("=" * 70)
    
    return total_matches

def apply_changes(files, kennel, field_index, action, new_value):
    """Apply changes to all matching events"""
    total_modified = 0
    backup_timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    for filepath in files:
        try:
            # Read file
            with open(filepath, 'r', encoding='utf-8') as f:
                lines = f.readlines()
            
            # Create backup
            backup_path = filepath + f".backup_{backup_timestamp}"
            with open(backup_path, 'w', encoding='utf-8') as f:
                f.writelines(lines)
            
            # Modify lines
            modified_count = 0
            new_lines = []
            
            for i, line in enumerate(lines):
                if i == 0:  # Keep header unchanged
                    new_lines.append(line)
                    continue
                
                fields = line.strip().split('\t')
                
                # Check if this event matches our criteria
                if len(fields) > field_index and fields[1] == kennel:
                    if action == "replace":
                        fields[field_index] = new_value
                    else:  # append
                        fields[field_index] = fields[field_index] + new_value
                    
                    modified_count += 1
                    new_lines.append('\t'.join(fields) + '\n')
                else:
                    new_lines.append(line)
            
            # Write modified file
            if modified_count > 0:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.writelines(new_lines)
                print(f"✓ Modified {modified_count} event(s) in {os.path.basename(filepath)}")
                print(f"  Backup: {os.path.basename(backup_path)}")
                total_modified += modified_count
        
        except Exception as e:
            print(f"✗ Error processing {filepath}: {e}")
    
    return total_modified

def main():
    print("=" * 70)
    print("FIXUP.PY - Hash Event Batch Editor")
    print("=" * 70)
    
    # Step 1: Get directory
    if len(sys.argv) > 1:
        directory = sys.argv[1]
    else:
        directory = input("\nEnter directory path containing .txt files: ").strip()
    
    if not os.path.isdir(directory):
        print(f"Error: '{directory}' is not a valid directory")
        return
    
    # Step 2: Read files
    files = read_event_files(directory)
    if not files:
        return
    
    # Step 3: Get available kennels
    kennels = get_kennels_from_files(files)
    if not kennels:
        print("No kennels found in files")
        return
    
    print(f"\nFound {len(kennels)} unique kennel(s)")
    
    # Step 4: Select kennel
    kennel = display_menu(kennels, "SELECT KENNEL")
    if kennel is None:
        print("Cancelled.")
        return
    
    # Step 5: Select field
    field_list = [f"{idx}: {name}" for idx, name in sorted(FIELDS.items())]
    field_choice = display_menu(field_list, "SELECT FIELD TO MODIFY")
    if field_choice is None:
        print("Cancelled.")
        return
    
    field_index = int(field_choice.split(':')[0])
    field_name = FIELDS[field_index]
    
    # Step 6: Select action
    action = display_menu(["Replace (overwrite existing)", "Append (add to end)"], 
                         "SELECT ACTION")
    if action is None:
        print("Cancelled.")
        return
    
    action_type = "replace" if "Replace" in action else "append"
    
    # Step 7: Get new value
    print(f"\nEnter new value to {action_type} for field '{field_name}':")
    new_value = input("> ")
    
    if not new_value and action_type == "replace":
        confirm = input("Warning: Replace with empty string? (yes/no): ")
        if confirm.lower() != "yes":
            print("Cancelled.")
            return
    
    # Step 8: Preview changes
    total = preview_changes(files, kennel, field_index, action_type, new_value)
    
    if total == 0:
        print("\nNo matching events found.")
        return
    
    # Step 9: Confirm
    print(f"\nReady to modify {total} event(s) in {len(files)} file(s)")
    print(f"Kennel: {kennel}")
    print(f"Field: {field_name}")
    print(f"Action: {action_type.upper()}")
    print(f"Value: {new_value if new_value else '(empty)'}")
    
    confirm = input("\nProceed? (yes/no): ")
    if confirm.lower() != "yes":
        print("Cancelled.")
        return
    
    # Step 10: Apply changes
    print("\nApplying changes...")
    modified = apply_changes(files, kennel, field_index, action_type, new_value)
    
    print("\n" + "=" * 70)
    print(f"✓ COMPLETE: Modified {modified} event(s)")
    print(f"✓ Backups created with timestamp: {datetime.now().strftime('%Y%m%d_%H%M%S')}")
    print("=" * 70)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\nCancelled by user.")
        sys.exit(0)