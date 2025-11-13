#!/bin/bash

# --- VARIABLES TO CONFIGURE ---

# The full name you want to appear on all commits
NEW_NAME="kitchen"

# The email address you want to appear on all commits
NEW_EMAIL="likesitinthekitchen@gmail.com"

# --- EXECUTION ---

echo "Starting Git history rewrite..."
echo "All commits will be re-attributed to:"
echo "Name: $NEW_NAME"
echo "Email: $NEW_EMAIL"
echo ""

# The git filter-repo command to unconditionally change all author and committer names/emails
# The --force flag is added because we are rewriting ALL commits, which filter-repo usually warns against.
# Using --name-callback and --email-callback ensures both author and committer are updated.
git filter-repo \
    --force \
    --name-callback 'return b"'$NEW_NAME'"' \
    --email-callback 'return b"'$NEW_EMAIL'"'

# --- CLEANUP AND PUSH ---

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ History rewrite complete."
    echo ""
    echo "The repository history has been locally updated."
    echo "To push this new history to GitHub, you must force-push (USE WITH EXTREME CAUTION!):"
    echo ""
    echo '  git push --force --tags origin "refs/heads/*"'
else
    echo "❌ History rewrite failed. Please check the logs."
fi