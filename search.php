<?php
// search.php - Search hash events across all kennel files
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$data_dir = '../android/';

/**
 * Clean HTML tags from text, replacing br and p tags with spaces
 */
function clean_html($text) {
    if (empty($text)) return $text;
    
    // Replace <br> and <p> tags with spaces
    $text = preg_replace('/<br\s*\/?>/i', ' ', $text);
    $text = preg_replace('/<\/?p\s*\/?>/i', ' ', $text);
    
    // Strip all remaining HTML tags
    $text = strip_tags($text);
    
    // Remove any remaining < or > characters
    $text = str_replace(array('<', '>'), '', $text);
    
    // Clean up whitespace (collapse multiple spaces into one)
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Parse a hash event file and return array of events
 */
function parse_event_file($filepath) {
    if (!file_exists($filepath)) {
        return array();
    }
    
    $content = file_get_contents($filepath);
    if ($content === false) {
        return array();
    }
    
    $events = array();
    $lines = explode("\n", $content);
    
    // Skip header line
    $first_line = true;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Skip header row
        if ($first_line) {
            $first_line = false;
            continue;
        }
        
        // Tab-separated values
        $parts = explode("\t", $line);
        if (count($parts) < 6) continue;
        
        // Build date from DAY field (column 0) and DATE field (column 13)
        // Extract the day number from column 0
        $day_num = isset($parts[0]) ? trim($parts[0]) : '';
        
        // Get the filename to extract year and month
        $filename = basename($filepath);
        preg_match('/(\d{4})-(\d{2})\.txt/', $filename, $matches);
        if (count($matches) < 3) continue;
        
        $year = $matches[1];
        $month = $matches[2];
        
        // Build the date string
        if (!empty($day_num) && is_numeric($day_num)) {
            $date = $year . '-' . $month . '-' . str_pad($day_num, 2, '0', STR_PAD_LEFT);
        } else {
            // Skip events with invalid day numbers
            continue;
        }
        
        $event = array(
            'date' => $date,
            'kennel' => isset($parts[1]) ? $parts[1] : '',
            'run_number' => isset($parts[4]) ? $parts[4] : '',
            'hare' => isset($parts[5]) ? $parts[5] : '',
            'title' => isset($parts[3]) ? $parts[3] : '',
            'time' => isset($parts[6]) ? $parts[6] : '',
            'location' => isset($parts[7]) ? $parts[7] : '',
            'hashcash' => isset($parts[9]) ? $parts[9] : '',
            'details' => isset($parts[14]) ? $parts[14] : '',
            'special' => isset($parts[10]) ? $parts[10] : ''
        );
        
        $events[] = $event;
    }
    
    return $events;
}

/**
 * Search HTML files for legacy events (2005-2012)
 * Returns array of results with snippet and link
 */
function search_html_events($search_query, $base_dir = '../calendar/') {
    $results = array();
    $search_lower = strtolower($search_query);
    
    // Search years 2005-2012
    for ($year = 2005; $year <= 2012; $year++) {
        $year_dir = $base_dir . $year . '/';
        if (!is_dir($year_dir)) continue;
        
        // Get all .html and .htm files
        $html_files = array_merge(
            glob($year_dir . '*.html'),
            glob($year_dir . '*.htm')
        );
        
        foreach ($html_files as $file) {
            $filename = basename($file);
            
            // Skip files that start with $
            if (substr($filename, 0, 1) === '$') {
                continue;
            }
            
            $content = file_get_contents($file);
            if ($content === false) continue;
            
            // Replace <br> and <p> tags with spaces before stripping all tags
            $content_cleaned = preg_replace('/<br\s*\/?>/i', ' ', $content);
            $content_cleaned = preg_replace('/<\/?p\s*\/?>/i', ' ', $content_cleaned);
            
            // Strip ALL HTML tags for searching
            $text_content = strip_tags($content_cleaned);
            
            // Remove any remaining < or > characters that might have escaped
            $text_content = str_replace(array('<', '>'), '', $text_content);
            
            $text_lower = strtolower($text_content);
            
            // Check if search query is in the content
            if (strpos($text_lower, $search_lower) !== false) {
                // Extract a snippet around the match
                $pos = strpos($text_lower, $search_lower);
                $snippet_start = max(0, $pos - 100);
                $snippet_length = 200 + strlen($search_query);
                $snippet = substr($text_content, $snippet_start, $snippet_length);
                
                // Clean up whitespace (collapse multiple spaces into one)
                $snippet = preg_replace('/\s+/', ' ', $snippet);
                $snippet = trim($snippet);
                
                // Add ellipsis if we're not at the start/end
                if ($snippet_start > 0) {
                    $snippet = '...' . $snippet;
                }
                if ($snippet_start + $snippet_length < strlen($text_content)) {
                    $snippet = $snippet . '...';
                }
                
                // Try to extract date and kennel from title/h1 tags
                $date = '';
                $date_sortable = '';
                $kennel = '';
                if (preg_match('/<title>(.*?)<\/title>/i', $content, $title_match)) {
                    $title = $title_match[1];
                    // Extract date if present (MM/DD/YYYY format)
                    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $title, $date_match)) {
                        $date = $date_match[0];
                        // Convert to sortable format YYYY-MM-DD
                        $date_sortable = sprintf('%04d-%02d-%02d', $date_match[3], $date_match[1], $date_match[2]);
                    }
                    // Extract kennel name
                    if (preg_match('/(.*?)\s+for\s+/i', $title, $kennel_match)) {
                        $kennel = trim($kennel_match[1]);
                    }
                }
                
                // If no date from title, try h2 tag
                if (empty($date) && preg_match('/<h2>(.*?)<\/h2>/i', $content, $h2_match)) {
                    $h2_text = strip_tags($h2_match[1]);
                    if (preg_match('/(\w+day,\s+\w+\s+\d{1,2}.*?(\d{4}))/i', $h2_text, $date_match)) {
                        $date = $date_match[1];
                        // Try to convert to sortable format
                        $timestamp = strtotime($date_match[1]);
                        if ($timestamp !== false) {
                            $date_sortable = date('Y-m-d', $timestamp);
                        }
                    }
                }
                
                // If still no sortable date, try to extract from filename (e.g., "02-19.html" -> 2011-02-19)
                if (empty($date_sortable) && preg_match('/^(\d{1,2})-(\d{1,2})\.html?$/i', $filename, $file_match)) {
                    $date_sortable = sprintf('%04d-%02d-%02d', $year, $file_match[1], $file_match[2]);
                    if (empty($date)) {
                        $date = $file_match[1] . '/' . $file_match[2] . '/' . $year;
                    }
                }
                
                $results[] = array(
                    'type' => 'html',
                    'file' => $filename,
                    'year' => $year,
                    'date' => $date,
                    'date_sortable' => $date_sortable,
                    'kennel' => $kennel,
                    'snippet' => $snippet,
                    'url' => $year . '/' . $filename
                );
            }
        }
    }
    
    return $results;
}
function search_events($search_query, $data_dir) {
    $all_events = array();
    
    // Get all .txt files in the android directory
    $files = glob($data_dir . '*.txt');
    
    foreach ($files as $file) {
        $events = parse_event_file($file);
        $all_events = array_merge($all_events, $events);
    }
    
    // Filter events based on search query
    $search_lower = strtolower($search_query);
    $matching_events = array();
    
    foreach ($all_events as $event) {
        // Search in all fields - create searchable string from entire event
        $searchable = strtolower(implode(' ', $event));
        
        if (strpos($searchable, $search_lower) !== false) {
            $matching_events[] = $event;
        }
    }
    
    return $matching_events;
}

/**
 * Comparison function for sorting past events (descending)
 */
function compare_past_events($a, $b) {
    return strcmp($b['date'], $a['date']);
}

/**
 * Comparison function for sorting future events (ascending)
 */
function compare_future_events($a, $b) {
    return strcmp($a['date'], $b['date']);
}

/**
 * Split events into past and future based on current date
 * Also adds event_number for each event based on occurrence order on that date
 */
function split_events_by_date($events) {
    $today = date('Y-m-d');
    $past = array();
    $future = array();
    
    // First, sort all events by date to determine event numbers
    $events_by_date = array();
    foreach ($events as $event) {
        $date = $event['date'];
        if (!isset($events_by_date[$date])) {
            $events_by_date[$date] = array();
        }
        $events_by_date[$date][] = $event;
    }
    
    // Assign event numbers and split into past/future
    foreach ($events_by_date as $date => $date_events) {
        $event_number = 1;
        foreach ($date_events as $event) {
            $event['event_number'] = $event_number;
            
            if ($event['date'] < $today) {
                $past[] = $event;
            } else {
                $future[] = $event;
            }
            $event_number++;
        }
    }
    
    // Sort past events descending (most recent first)
    usort($past, 'compare_past_events');
    
    // Sort future events ascending (soonest first)
    usort($future, 'compare_future_events');
    
    return array('past' => $past, 'future' => $future);
}

/**
 * Format date for display
 */
function format_date($date_str) {
    $timestamp = strtotime($date_str);
    return date('D, M j, Y', $timestamp);
}

/**
 * Generate event page URL (relative)
 */
function get_event_url($event, $event_number) {
    // Extract year, month, day from date
    $date_parts = explode('-', $event['date']);
    $year = $date_parts[0];
    $month = (int)$date_parts[1]; // Remove leading zero
    $day = (int)$date_parts[2];   // Remove leading zero
    
    // Format: year/event.php?month=M&day=D&year=YYYY&no=N
    return $year . '/event.php?month=' . $month . '&day=' . $day . '&year=' . $year . '&no=' . $event_number;
}

/**
 * Generate pagination HTML
 */
function generate_pagination($current_page, $total_pages, $search_query, $per_page) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $html .= '<a href="?q=' . urlencode($search_query) . '&page=' . $prev_page . '&per_page=' . urlencode($per_page) . '" class="page-btn">&laquo; Previous</a>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $html .= '<a href="?q=' . urlencode($search_query) . '&page=1&per_page=' . urlencode($per_page) . '" class="page-btn">1</a>';
        if ($start_page > 2) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="page-btn active">' . $i . '</span>';
        } else {
            $html .= '<a href="?q=' . urlencode($search_query) . '&page=' . $i . '&per_page=' . urlencode($per_page) . '" class="page-btn">' . $i . '</a>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
        $html .= '<a href="?q=' . urlencode($search_query) . '&page=' . $total_pages . '&per_page=' . urlencode($per_page) . '" class="page-btn">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $html .= '<a href="?q=' . urlencode($search_query) . '&page=' . $next_page . '&per_page=' . urlencode($per_page) . '" class="page-btn">Next &raquo;</a>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Comparison function for sorting HTML events (descending - most recent first)
 */
function compare_html_events($a, $b) {
    // If both have sortable dates, compare them
    if (!empty($a['date_sortable']) && !empty($b['date_sortable'])) {
        return strcmp($b['date_sortable'], $a['date_sortable']);
    }
    // If only one has a date, put it first
    if (!empty($a['date_sortable'])) return -1;
    if (!empty($b['date_sortable'])) return 1;
    // Otherwise compare by year then filename
    if ($a['year'] != $b['year']) {
        return $b['year'] - $a['year'];
    }
    return strcmp($b['file'], $a['file']);
}

// Handle search
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? $_GET['per_page'] : '20';

// Validate per_page
$valid_per_page = array('20', '50', '100', 'unlimited');
if (!in_array($per_page, $valid_per_page)) {
    $per_page = '20';
}

$results = array();
$html_results = array();
$html_results_paginated = array();
$past_events = array();
$future_events = array();
$past_total = 0;
$future_total = 0;
$html_total = 0;
$past_pages = 1;
$future_pages = 1;
$html_pages = 1;

if (!empty($search_query)) {
    // Search structured data (2012+)
    $results = search_events($search_query, $data_dir);
    $split_results = split_events_by_date($results);
    
    // Search HTML files (2005-2012)
    $html_results = search_html_events($search_query);
    
    // Sort HTML results by date (most recent first)
    usort($html_results, 'compare_html_events');
    
    $past_total = count($split_results['past']);
    $future_total = count($split_results['future']);
    $html_total = count($html_results);
    
    if ($per_page === 'unlimited') {
        $past_events = $split_results['past'];
        $future_events = $split_results['future'];
        $html_results_paginated = $html_results;
        $past_pages = 1;
        $future_pages = 1;
        $html_pages = 1;
    } else {
        $items_per_page = (int)$per_page;
        
        // Calculate pagination for past events
        $past_pages = max(1, ceil($past_total / $items_per_page));
        $past_offset = ($page - 1) * $items_per_page;
        $past_events = array_slice($split_results['past'], $past_offset, $items_per_page);
        
        // Calculate pagination for future events
        $future_pages = max(1, ceil($future_total / $items_per_page));
        $future_offset = ($page - 1) * $items_per_page;
        $future_events = array_slice($split_results['future'], $future_offset, $items_per_page);
        
        // Calculate pagination for HTML results
        $html_pages = max(1, ceil($html_total / $items_per_page));
        $html_offset = ($page - 1) * $items_per_page;
        $html_results_paginated = array_slice($html_results, $html_offset, $items_per_page);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DFW Hash Event Search</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .search-box {
            text-align: center;
            margin: 30px 0;
        }
        .search-box input[type="text"] {
            width: 60%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }
        .search-box button {
            padding: 12px 30px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .search-box button:hover {
            background-color: #45a049;
        }
        .results-section {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .results-section h2 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .event-item {
            padding: 15px;
            margin: 10px 0;
            background-color: #f9f9f9;
            border-left: 4px solid #4CAF50;
            border-radius: 4px;
        }
        .event-item:hover {
            background-color: #f0f0f0;
        }
        .event-date {
            font-weight: bold;
            color: #2196F3;
            font-size: 14px;
        }
        .event-kennel {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        .event-title {
            font-weight: bold;
            color: #666;
            font-size: 15px;
            margin: 5px 0;
        }
        .event-details {
            margin: 5px 0;
            color: #666;
        }
        .event-link {
            display: inline-block;
            margin-top: 8px;
            color: #2196F3;
            text-decoration: none;
            font-size: 14px;
        }
        .event-link:hover {
            text-decoration: underline;
        }
        .no-results {
            text-align: center;
            color: #999;
            padding: 40px;
            font-style: italic;
        }
        .result-count {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .info-message {
            text-align: center;
            color: #666;
            padding: 40px;
            font-style: italic;
        }
        .per-page-control {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .per-page-control label {
            margin-right: 10px;
            font-weight: bold;
            color: #333;
        }
        .per-page-control select {
            padding: 8px 12px;
            font-size: 14px;
            border: 2px solid #ddd;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
        }
        .pagination {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
        }
        .page-btn {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
        }
        .page-btn:hover {
            background-color: #f0f0f0;
        }
        .page-btn.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        .page-ellipsis {
            display: inline-block;
            padding: 8px 12px;
            color: #999;
        }
        .legacy-event {
            background-color: #fffef0;
            border-left-color: #f39c12;
        }
        .event-snippet {
            margin: 8px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #ddd;
            font-size: 14px;
            color: #555;
            font-style: italic;
        }
        .legacy-badge {
            display: inline-block;
            background-color: #f39c12;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <h1>🍺 DFW Hash Event Search</h1>
    
    <div class="search-box">
        <form method="GET" action="">
            <input type="text" name="q" placeholder="Search events (e.g., 'Marine Corps', 'Red Dress', hare name...)" 
                   value="<?php echo htmlspecialchars($search_query); ?>" autofocus>
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if (!empty($search_query)): ?>
    <div class="per-page-control">
        <label for="per_page">Events per page:</label>
        <select id="per_page" name="per_page" onchange="window.location.href='?q=<?php echo urlencode($search_query); ?>&per_page=' + this.value + '&page=1'">
            <option value="20" <?php echo ($per_page === '20') ? 'selected' : ''; ?>>20</option>
            <option value="50" <?php echo ($per_page === '50') ? 'selected' : ''; ?>>50</option>
            <option value="100" <?php echo ($per_page === '100') ? 'selected' : ''; ?>>100</option>
            <option value="unlimited" <?php echo ($per_page === 'unlimited') ? 'selected' : ''; ?>>Unlimited</option>
        </select>
    </div>
    <?php endif; ?>

    <?php if (empty($search_query)): ?>
        <div class="info-message">
            Enter a search term to find hash events. You can search by kennel name, hare name, location, event title, or event details.
        </div>
    <?php elseif (empty($results) && empty($html_results)): ?>
        <div class="no-results">
            No events found matching "<?php echo htmlspecialchars($search_query); ?>"
        </div>
    <?php else: ?>
        
        <?php if (!empty($future_events)): ?>
        <div class="results-section">
            <h2>📅 Upcoming Events</h2>
            <div class="result-count">
                Showing <?php echo count($future_events); ?> of <?php echo $future_total; ?> future event(s)
                <?php if ($per_page !== 'unlimited'): ?> (Page <?php echo $page; ?> of <?php echo $future_pages; ?>)<?php endif; ?>
            </div>
            <?php foreach ($future_events as $event): ?>
                <div class="event-item">
                    <div class="event-date"><?php echo format_date($event['date']); ?></div>
                    <div class="event-kennel">
                        <?php echo htmlspecialchars(clean_html($event['kennel'])); ?> 
                        <?php if (!empty($event['run_number'])): ?>- Run #<?php echo htmlspecialchars($event['run_number']); ?><?php endif; ?>
                    </div>
                    <?php if (!empty($event['title'])): ?>
                    <div class="event-title"><?php echo htmlspecialchars(clean_html($event['title'])); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($event['hare'])): ?>
                    <div class="event-details">
                        <strong>Hare:</strong> <?php echo htmlspecialchars(clean_html($event['hare'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($event['location'])): ?>
                    <div class="event-details">
                        <strong>Location:</strong> <?php echo htmlspecialchars(clean_html($event['location'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($event['details'])): ?>
                    <?php 
                        // Get first line of description
                        $details_clean = clean_html($event['details']);
                        $first_line = strtok($details_clean, "\n");
                        if (strlen($first_line) > 150) {
                            $first_line = substr($first_line, 0, 150) . '...';
                        }
                    ?>
                    <div class="event-details" style="font-style: italic; color: #777;">
                        <?php echo htmlspecialchars($first_line); ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?php echo get_event_url($event, $event["event_number"]); ?>" class="event-link" target="_blank">View Event Page →</a>
                </div>
            <?php endforeach; ?>
            <?php echo generate_pagination($page, $future_pages, $search_query, $per_page); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($past_events)): ?>
        <div class="results-section">
            <h2>📜 Past Events</h2>
            <div class="result-count">
                Showing <?php echo count($past_events); ?> of <?php echo $past_total; ?> past event(s)
                <?php if ($per_page !== 'unlimited'): ?> (Page <?php echo $page; ?> of <?php echo $past_pages; ?>)<?php endif; ?>
            </div>
            <?php foreach ($past_events as $event): ?>
                <div class="event-item">
                    <div class="event-date"><?php echo format_date($event['date']); ?></div>
                    <div class="event-kennel">
                        <?php echo htmlspecialchars(clean_html($event['kennel'])); ?> 
                        <?php if (!empty($event['run_number'])): ?>- Run #<?php echo htmlspecialchars($event['run_number']); ?><?php endif; ?>
                    </div>
                    <?php if (!empty($event['title'])): ?>
                    <div class="event-title"><?php echo htmlspecialchars(clean_html($event['title'])); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($event['hare'])): ?>
                    <div class="event-details">
                        <strong>Hare:</strong> <?php echo htmlspecialchars(clean_html($event['hare'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($event['location'])): ?>
                    <div class="event-details">
                        <strong>Location:</strong> <?php echo htmlspecialchars(clean_html($event['location'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($event['details'])): ?>
                    <?php 
                        // Get first line of description
                        $details_clean = clean_html($event['details']);
                        $first_line = strtok($details_clean, "\n");
                        if (strlen($first_line) > 150) {
                            $first_line = substr($first_line, 0, 150) . '...';
                        }
                    ?>
                    <div class="event-details" style="font-style: italic; color: #777;">
                        <?php echo htmlspecialchars($first_line); ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?php echo get_event_url($event, $event["event_number"]); ?>" class="event-link" target="_blank">View Event Page →</a>
                </div>
            <?php endforeach; ?>
            <?php echo generate_pagination($page, $past_pages, $search_query, $per_page); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($html_results_paginated)): ?>
        <div class="results-section">
            <h2>📂 Unstructured Events (2005-2012)</h2>
            <div class="result-count">
                Showing <?php echo count($html_results_paginated); ?> of <?php echo $html_total; ?> unstructured event(s)
                <?php if ($per_page !== 'unlimited'): ?> (Page <?php echo $page; ?> of <?php echo $html_pages; ?>)<?php endif; ?>
            </div>
            <?php foreach ($html_results_paginated as $event): ?>
                <div class="event-item legacy-event">
                    <div class="event-kennel">
                        <?php if (!empty($event['kennel'])): ?>
                            <?php echo htmlspecialchars($event['kennel']); ?>
                        <?php else: ?>
                            Event from <?php echo $event['year']; ?>
                        <?php endif; ?>
                        <span class="legacy-badge">UNSTRUCTURED</span>
                    </div>
                    <?php if (!empty($event['date'])): ?>
                    <div class="event-date"><?php echo htmlspecialchars($event['date']); ?></div>
                    <?php endif; ?>
                    <div class="event-snippet">
                        <?php echo htmlspecialchars($event['snippet']); ?>
                    </div>
                    <a href="<?php echo htmlspecialchars($event['url']); ?>" class="event-link" target="_blank">View Event Page →</a>
                </div>
            <?php endforeach; ?>
            <?php echo generate_pagination($page, $html_pages, $search_query, $per_page); ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</body>
</html>