<?php
// sort_index.php - Re-sort all entries in index.html by date descending

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sort'])) {
    // Read index.html
    $index_content = file_get_contents('index.html');
    
    if ($index_content === false) {
        $error = 'Could not read index.html';
    } else {
        // Extract all entry lines
        $entries = array();
        
        if (preg_match_all('/<p><a href="([^"]+)"><em>([^<]+)<\/em>([^<]+)<\/a><\/p>/', $index_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $entry_file = $match[1];
                $entry_date_str = strip_tags($match[2]);
                
                // Parse the date - handle &nbsp; entities
                $entry_date_str = str_replace('&nbsp;', ' ', html_entity_decode($entry_date_str));
                $entry_timestamp = strtotime($entry_date_str);
                
                if ($entry_timestamp) {
                    $entries[] = array(
                        'timestamp' => $entry_timestamp,
                        'line' => $match[0],
                        'filename' => $entry_file
                    );
                }
            }
        }
        
        if (empty($entries)) {
            $error = 'No entries found to sort';
        } else {
            // Sort by timestamp descending (newest first)
            usort($entries, create_function('$a,$b', 'return $b["timestamp"] - $a["timestamp"];'));
            
            // Rebuild the entries section
            $new_entries_html = "\n\n";
            foreach ($entries as $entry) {
                $new_entries_html .= $entry['line'] . "\n";
            }
            $new_entries_html .= "</div>\n\n</div>\n</body>\n</html>";
            
            // Find the menu end
            $menu_end = strpos($index_content, '</div> <!-- menu -->');
            
            if ($menu_end === false) {
                $error = 'Could not find menu end marker in index.html';
            } else {
                $before_entries = substr($index_content, 0, $menu_end + strlen('</div> <!-- menu -->'));
                
                // Combine
                $new_index = $before_entries . $new_entries_html;
                
                // Backup old index
                copy('index.html', 'index.html.backup.' . date('Y-m-d-His'));
                
                // Save new sorted index
                $result = file_put_contents('index.html', $new_index);
                
                if ($result !== false) {
                    $success = 'Index.html has been sorted! ' . count($entries) . ' entries re-ordered by date (newest first). Backup saved.';
                } else {
                    $error = 'Could not write to index.html. Check permissions.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sort Index.html</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #666;
            padding-bottom: 10px;
        }
        .message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        .info {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        button {
            padding: 15px 30px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background-color: #0b7dda;
        }
        .description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sort Index.html</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="info">
            <strong>What this does:</strong> This utility will re-sort all entries in index.html by date in descending order (newest first). A backup will be created before making changes.
        </div>
        
        <div class="description">
            <p>Current index.html appears to have unsorted entries. Click the button below to sort all write-ups by date.</p>
            <p><strong>Note:</strong> This will create a backup file named <code>index.html.backup.YYYY-MM-DD-HHMMSS</code> before making changes.</p>
        </div>
        
        <form method="POST">
            <button type="submit" name="sort" value="1">Sort Index.html Now</button>
        </form>
    </div>
</body>
</html>