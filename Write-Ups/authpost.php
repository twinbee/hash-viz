<?php
// authpost.php - Authorize hash write-up posts

// Comparison function for sorting by timestamp descending
function sort_by_timestamp($a, $b) {
    if ($a['timestamp'] == $b['timestamp']) {
        return 0;
    }
    return ($a['timestamp'] > $b['timestamp']) ? -1 : 1;
}

$message = '';
$error = '';

// Handle authorization/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    
    if ($action === 'delete' && !empty($filename)) {
        // Delete the file
        if (file_exists($filename)) {
            if (unlink($filename)) {
                $message = 'Write-up "' . $filename . '" has been permanently deleted.';
            } else {
                $error = 'Could not delete file. Check permissions.';
            }
        } else {
            $error = 'File not found.';
        }
    } elseif ($action === 'authorize' && !empty($filename) && !empty($title) && !empty($date)) {
        // Read current index.html
        $index_content = file_get_contents('index.html');
        
        if ($index_content === false) {
            $error = 'Could not read index.html';
        } else {
            // Parse the date
            $timestamp = strtotime($date);
            if (!$timestamp) {
                $error = 'Invalid date format';
            } else {
                // Format date for display
                $month = date('M', $timestamp);
                $day = date('j', $timestamp);
                $year = date('Y', $timestamp);
                
                // Create the new entry line
                $new_entry = '<p><a href="' . htmlspecialchars($filename) . '"><em>' 
                    . $month . '&nbsp;' . $day . '&nbsp;' . $year 
                    . '</em>&nbsp;&nbsp;' . htmlspecialchars($title) . '</a></p>';
                
                // Find the insertion point (after the menu div closing)
                $menu_end = strpos($index_content, '</div> <!-- menu -->');
                
                if ($menu_end === false) {
                    $error = 'Could not find insertion point in index.html';
                } else {
                    // Extract all existing entries
                    $entries = array();
                    
                    // Pattern to match entry lines
                    if (preg_match_all('/<p><a href="([^"]+)"><em>([^<]+)<\/em>([^<]+)<\/a><\/p>/', $index_content, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $match) {
                            $entry_file = $match[1];
                            $entry_date_str = strip_tags($match[2]);
                            $entry_title = strip_tags($match[3]);
                            
                            // Parse the date - handle &nbsp; and various formats
                            $entry_date_str = str_replace('&nbsp;', ' ', html_entity_decode($entry_date_str));
                            // Remove non-breaking spaces and other unicode spaces
                            $entry_date_str = preg_replace('/\s+/', ' ', $entry_date_str);
                            $entry_date_str = trim($entry_date_str);
                            
                            $entry_timestamp = strtotime($entry_date_str);
                            
                            // If strtotime fails, try to extract from filename
                            if (!$entry_timestamp && preg_match('/(\d{4})-(\d{2})-(\d{2})/', $entry_file, $date_parts)) {
                                $entry_timestamp = strtotime($date_parts[1] . '-' . $date_parts[2] . '-' . $date_parts[3]);
                            }
                            
                            if ($entry_timestamp) {
                                $entries[] = array(
                                    'timestamp' => $entry_timestamp,
                                    'line' => $match[0],
                                    'filename' => $entry_file
                                );
                            }
                        }
                    }
                    
                    // Check if this file is already in the index
                    $already_exists = false;
                    foreach ($entries as $entry) {
                        if ($entry['filename'] === $filename) {
                            $already_exists = true;
                            break;
                        }
                    }
                    
                    if ($already_exists) {
                        $message = 'This write-up is already in the index.';
                    } else {
                        // Add the new entry
                        $entries[] = array(
                            'timestamp' => $timestamp,
                            'line' => $new_entry,
                            'filename' => $filename
                        );
                        
                        // Sort by date descending (newest first)
                        usort($entries, 'sort_by_timestamp');
                        
                        // Rebuild the entries section
                        $new_entries_html = "\n\n";
                        foreach ($entries as $entry) {
                            $new_entries_html .= $entry['line'] . "\n";
                        }
                        $new_entries_html .= "</div>\n\n</div>\n</body>\n</html>";
                        
                        // Find where to cut off old entries (everything after menu to </div></div></body></html>)
                        $menu_end_full = strpos($index_content, '</div> <!-- menu -->');
                        $before_entries = substr($index_content, 0, $menu_end_full + strlen('</div> <!-- menu -->'));
                        
                        // Combine
                        $new_index = $before_entries . $new_entries_html;
                        
                        // Save
                        $result = file_put_contents('index.html', $new_index);
                        
                        if ($result !== false) {
                            $message = 'Write-up authorized and added to index.html!';
                        } else {
                            $error = 'Could not write to index.html. Check permissions.';
                        }
                    }
                }
            }
        }
    }
}

// Scan directory for dated HTML/PHP files
$files = glob('*.{html,php}', GLOB_BRACE);
$dated_files = array();

foreach ($files as $file) {
    // Skip index.html, post.php, authpost.php, and any other system files
    if ($file === 'index.html' || $file === 'post.php' || $file === 'authpost.php') {
        continue;
    }
    
    // Check if filename matches YYYY-MM-DD pattern
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\.(html|php)$/', $file, $matches)) {
        $year = $matches[1];
        $month = $matches[2];
        $day = $matches[3];
        
        // Try to extract title from file
        $content = file_get_contents($file);
        $title = '';
        
        // Look for the second <h2> tag (the title) - allow for nested tags
        if (preg_match_all('/<h2>(.*?)<\/h2>/s', $content, $h2_matches)) {
            if (count($h2_matches[1]) >= 2) {
                // Get second h2, strip all HTML tags, decode entities, clean up whitespace
                $title = $h2_matches[1][1];
                $title = strip_tags($title);
                $title = html_entity_decode($title);
                $title = preg_replace('/\s+/', ' ', $title); // normalize whitespace
                $title = trim($title);
            } elseif (count($h2_matches[1]) >= 1) {
                $title = $h2_matches[1][0];
                $title = strip_tags($title);
                $title = html_entity_decode($title);
                $title = preg_replace('/\s+/', ' ', $title);
                $title = trim($title);
            }
        }
        
        // If no title found or title looks like a date, try the <title> tag
        if ((empty($title) || preg_match('/^\d{4}/', $title)) && preg_match('/<title>([^<]+)<\/title>/', $content, $title_match)) {
            $title = trim(html_entity_decode(strip_tags($title_match[1])));
            // Remove "Hash Writup for " prefix if present
            $title = preg_replace('/^Hash Writ[eu]p for\s*/i', '', $title);
        }
        
        $date_str = $year . '-' . $month . '-' . $day;
        $timestamp = strtotime($date_str);
        
        $dated_files[] = array(
            'filename' => $file,
            'date' => $date_str,
            'timestamp' => $timestamp,
            'title' => $title
        );
    }
}

// Sort by date descending
usort($dated_files, 'sort_by_timestamp');

// Check which files are already in index.html
$index_content = file_get_contents('index.html');
$indexed_files = array();

if (preg_match_all('/<p><a href="([^"]+)">/', $index_content, $matches)) {
    $indexed_files = $matches[1];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Authorize Hash Write-Ups</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
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
        .write-up {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .write-up.indexed {
            background-color: #e8f5e9;
            border-color: #81c784;
        }
        .write-up h3 {
            margin-top: 0;
            color: #333;
        }
        .write-up .filename {
            font-family: 'Courier New', monospace;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .write-up .date {
            color: #555;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .write-up .title {
            color: #1976d2;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .write-up .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .status.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status.indexed {
            background-color: #d4edda;
            color: #155724;
        }
        .button-group {
            margin-top: 15px;
        }
        button {
            padding: 10px 20px;
            margin-right: 10px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-authorize {
            background-color: #4CAF50;
            color: white;
        }
        .btn-authorize:hover {
            background-color: #45a049;
        }
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        .btn-delete:hover {
            background-color: #da190b;
        }
        .btn-view {
            background-color: #2196F3;
            color: white;
        }
        .btn-view:hover {
            background-color: #0b7dda;
        }
        .no-posts {
            text-align: center;
            color: #999;
            padding: 40px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Authorize Hash Write-Ups</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (empty($dated_files)): ?>
            <div class="no-posts">No dated write-up files found in this directory.</div>
        <?php else: ?>
            <?php foreach ($dated_files as $file): ?>
                <?php 
                $is_indexed = in_array($file['filename'], $indexed_files);
                ?>
                <div class="write-up <?php echo $is_indexed ? 'indexed' : ''; ?>">
                    <div class="filename"><?php echo htmlspecialchars($file['filename']); ?></div>
                    <div class="date"><?php echo date('F j, Y', $file['timestamp']); ?></div>
                    <div class="title"><?php echo htmlspecialchars($file['title'] ? $file['title'] : '(No title found)'); ?></div>
                    
                    <span class="status <?php echo $is_indexed ? 'indexed' : 'pending'; ?>">
                        <?php echo $is_indexed ? 'ALREADY INDEXED' : 'PENDING AUTHORIZATION'; ?>
                    </span>
                    
                    <div class="button-group">
                        <a href="<?php echo htmlspecialchars($file['filename']); ?>" target="_blank">
                            <button type="button" class="btn-view">View Write-Up</button>
                        </a>
                        
                        <?php if (!$is_indexed): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="authorize">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['filename']); ?>">
                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($file['title']); ?>">
                                <input type="hidden" name="date" value="<?php echo htmlspecialchars($file['date']); ?>">
                                <button type="submit" class="btn-authorize">Authorize Post</button>
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this write-up? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['filename']); ?>">
                                <button type="submit" class="btn-delete">Delete Post</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>