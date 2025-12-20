<?php
// post.php - Hash Write-Up Generator

$error = '';
$success = '';
$writeup_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $run_number = isset($_POST['run_number']) ? trim($_POST['run_number']) : '';
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';
    $hares = isset($_POST['hares']) ? trim($_POST['hares']) : '';
    $author = isset($_POST['author']) ? trim($_POST['author']) : '';
    $staff = isset($_POST['staff']) ? trim($_POST['staff']) : 'Staff Writer';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $image_filename = '';
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_file = $_FILES['image'];
        $file_size = $upload_file['size'];
        $file_tmp = $upload_file['tmp_name'];
        $file_type = $upload_file['type'];
        
        // Check file size (500KB max)
        if ($file_size > 512000) {
            $error = 'Image file size must be less than 500KB.';
        } else {
            // Check if it's an image
            $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
            if (!in_array($file_type, $allowed_types)) {
                $error = 'Only JPG, PNG, and GIF images are allowed.';
            } else {
                // Generate filename based on date
                $timestamp_for_file = strtotime($date);
                $ext = pathinfo($upload_file['name'], PATHINFO_EXTENSION);
                $image_filename = date('Y-m-d', $timestamp_for_file) . '-image.' . $ext;
                
                // Move uploaded file
                if (!move_uploaded_file($file_tmp, $image_filename)) {
                    $error = 'Failed to upload image.';
                    $image_filename = '';
                }
            }
        }
    }
    
    // Strip magic quotes if enabled (PHP 5.2 issue)
    if (get_magic_quotes_gpc()) {
        $run_number = stripslashes($run_number);
        $title = stripslashes($title);
        $date = stripslashes($date);
        $hares = stripslashes($hares);
        $author = stripslashes($author);
        $staff = stripslashes($staff);
        $content = stripslashes($content);
    }
    
    // Fix character encoding issues - normalize all apostrophes and quotes FIRST
    // Convert UTF-8 smart quotes/apostrophes to regular ASCII
    // Left single quote ('), right single quote ('), acute accent (´), backtick (`)
    $run_number = str_replace(chr(226).chr(128).chr(152), "'", $run_number); // '
    $run_number = str_replace(chr(226).chr(128).chr(153), "'", $run_number); // '
    $run_number = str_replace(chr(194).chr(180), "'", $run_number); // ´
    $run_number = str_replace('`', "'", $run_number);
    
    $title = str_replace(chr(226).chr(128).chr(152), "'", $title);
    $title = str_replace(chr(226).chr(128).chr(153), "'", $title);
    $title = str_replace(chr(194).chr(180), "'", $title);
    $title = str_replace('`', "'", $title);
    
    $hares = str_replace(chr(226).chr(128).chr(152), "'", $hares);
    $hares = str_replace(chr(226).chr(128).chr(153), "'", $hares);
    $hares = str_replace(chr(194).chr(180), "'", $hares);
    $hares = str_replace('`', "'", $hares);
    
    $author = str_replace(chr(226).chr(128).chr(152), "'", $author);
    $author = str_replace(chr(226).chr(128).chr(153), "'", $author);
    $author = str_replace(chr(194).chr(180), "'", $author);
    $author = str_replace('`', "'", $author);
    
    $staff = str_replace(chr(226).chr(128).chr(152), "'", $staff);
    $staff = str_replace(chr(226).chr(128).chr(153), "'", $staff);
    $staff = str_replace(chr(194).chr(180), "'", $staff);
    $staff = str_replace('`', "'", $staff);
    
    $content = str_replace(chr(226).chr(128).chr(152), "'", $content);
    $content = str_replace(chr(226).chr(128).chr(153), "'", $content);
    $content = str_replace(chr(194).chr(180), "'", $content);
    $content = str_replace('`', "'", $content);
    
    // Left double quote ("), right double quote (")
    $run_number = str_replace(chr(226).chr(128).chr(156), '"', $run_number);
    $run_number = str_replace(chr(226).chr(128).chr(157), '"', $run_number);
    
    $title = str_replace(chr(226).chr(128).chr(156), '"', $title);
    $title = str_replace(chr(226).chr(128).chr(157), '"', $title);
    
    $hares = str_replace(chr(226).chr(128).chr(156), '"', $hares);
    $hares = str_replace(chr(226).chr(128).chr(157), '"', $hares);
    
    $author = str_replace(chr(226).chr(128).chr(156), '"', $author);
    $author = str_replace(chr(226).chr(128).chr(157), '"', $author);
    
    $staff = str_replace(chr(226).chr(128).chr(156), '"', $staff);
    $staff = str_replace(chr(226).chr(128).chr(157), '"', $staff);
    
    $content = str_replace(chr(226).chr(128).chr(156), '"', $content);
    $content = str_replace(chr(226).chr(128).chr(157), '"', $content);
    
    // Validate inputs
    if (empty($run_number) || empty($title) || empty($date) || empty($content)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Parse date and create filename
        $timestamp = strtotime($date);
        if (!$timestamp) {
            $error = 'Invalid date format. Use YYYY-MM-DD.';
        } else {
            $filename = date('Y-m-d', $timestamp) . '.html';
            
            // Process content - convert newlines to <P> tags
            $content = trim($content);
            
            // Fix bullet points - normalize various bullet characters
            $content = str_replace(chr(226).chr(128).chr(162), '•', $content); // bullet
            $content = str_replace('�', '•', $content); // corrupted bullet character
            $content = preg_replace('/[��]/', '•', $content); // other bullet variants
            
            // Protect bullet lines - mark them specially so they don't get merged
            $content = preg_replace('/^•\s+/m', '<BULLET>', $content);
            
            // First, protect intentional section headers (all caps or title case lines under 70 chars)
            $content = preg_replace('/\n\s*([A-Z][A-Za-z\s:,\-]{10,70})\s*\n/', "\n\n<SECTION>$1</SECTION>\n\n", $content);
            
            // Add paragraph break after lines ending with punctuation followed by a new line starting with capital
            $content = preg_replace('/([.!?])\s*\n\s*([A-Z])/', "$1\n\n$2", $content);
            
            // Replace multiple newlines with paragraph breaks
            $content = preg_replace('/\n\s*\n+/', '<P>', $content);
            
            // Restore section markers
            $content = str_replace('<SECTION>', '', $content);
            $content = str_replace('</SECTION>', '', $content);
            
            // NOW handle bullets - each <BULLET> becomes a line break + bullet
            $content = str_replace('<BULLET>', '<BR>&bull; ', $content);
            
            // Remove single newlines (keep text flowing) - but preserve our <BR> tags
            $content = str_replace("\n", ' ', $content);
            
            // Split content into two roughly equal columns
            $paragraphs = explode('<P>', $content);
            
            // Remove empty paragraphs
            $paragraphs = array_filter($paragraphs, 'strlen');
            $paragraphs = array_values($paragraphs); // Re-index
            
            // Split roughly in half by paragraph count (better than character count)
            $total_paras = count($paragraphs);
            $split_point = ceil($total_paras / 2);
            
            // Build left and right columns
            $left_content = '';
            $right_content = '';
            
            for ($i = 0; $i < count($paragraphs); $i++) {
                $para = trim($paragraphs[$i]);
                if (empty($para)) continue;
                
                // Add drop cap to first letter of each paragraph
                $first_char = substr($para, 0, 1);
                $rest = substr($para, 1);
                $para_with_dropcap = '<span class="dropcap">' . $first_char . '</span>' . $rest;
                
                if ($i < $split_point) {
                    $left_content .= $para_with_dropcap . '<P>' . "\n";
                } else {
                    $right_content .= $para_with_dropcap . '<P>' . "\n";
                }
            }
            
            // Clean up trailing <P> tags
            $left_content = rtrim($left_content, '<P>' . "\n");
            $right_content = rtrim($right_content, '<P>' . "\n");
            
            // Add image to right column if uploaded
            $image_html = '';
            if ($image_filename) {
                $image_html = '<P><img src="' . htmlspecialchars($image_filename, ENT_COMPAT, 'UTF-8') . '" alt="Hash Photo" style="max-width: 100%; height: auto; margin-top: 10px;" /></P>';
            }
            
            // Format the date for display
            $display_date = date('F j, Y', $timestamp);
            
            // Create the HTML content
            $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<title>Hash Writup for ' . $display_date . '</title>

<link rel="shortcut icon" href="../favicon.ico" />

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

<link href="nustyle.css" rel="stylesheet" type="text/css" />
</head>


<body>
<div id="container">
  <div id="header">
	  <img src="TrashBanner.jpg" width="780" height="200" alt="Hash Trash" />
  </div> <!-- header -->
  
  
  <div id="mainContent">
  
    
    <div class="lcolumn">
      <h2>Dallas Run № ' . htmlspecialchars($run_number, ENT_COMPAT, 'UTF-8') . '</h2>
      <h2>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h2>
      
	  <h3>' . $display_date . '<br />
      Hares: ' . htmlspecialchars($hares, ENT_COMPAT, 'UTF-8') . '<br />
      </h3>
      <hr />
      <h4>' . htmlspecialchars($author, ENT_COMPAT, 'UTF-8') . '<br />
      <span id="staff">' . htmlspecialchars($staff, ENT_COMPAT, 'UTF-8') . '</span>
      </h4>      <p class="noindent">
      
      <!-- LEFT COLUMN TEXT STARTS HERE -->	  	
' . $left_content . '
      <!-- LEFT COLUMN TEXT ENDS HERE -->
      
</div> <!-- lcolumn -->
    
    <div class="rcolumn">   
    
      
      <p class="noindent">
      <!-- RIGHT COLUMN TEXT STARTS HERE -->
' . $right_content . '
      <!-- RIGHT COLUMN TEXT ENDS HERE -->
' . $image_html . '
   </div>  <!-- rcolumn -->
        
    <div class="column">
      <hr />
    </div> <!-- column -->
  
	</div> <!-- mainContent -->
  
</div> <!-- container -->

</body>
</html>';
            
            // Save the file
            $result = file_put_contents($filename, $html);
            
            if ($result !== false) {
                $success = "Write-up saved successfully!";
                $writeup_url = $filename;
            } else {
                $error = "Error saving file. Check directory permissions.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hash Write-Up Generator</title>
    <script>
    function extractMetadata() {
        var content = document.getElementById('content').value;
        var lines = content.split('\n');
        
        // Try to extract from first line if it has format "Write-up... | Date"
        var firstLine = lines[0] || '';
        if (firstLine.match(/\|/)) {
            var parts = firstLine.split('|');
            if (parts.length >= 2) {
                var datePart = parts[parts.length - 1].trim();
                var dateMatch = datePart.match(/(\w+)\s+(\d+),?\s+(\d{4})/);
                if (dateMatch) {
                    var month = dateMatch[1];
                    var day = dateMatch[2];
                    var year = dateMatch[3];
                    
                    var months = {Jan:1,Feb:2,Mar:3,Apr:4,May:5,Jun:6,Jul:7,Aug:8,Sep:9,Oct:10,Nov:11,Dec:12,
                                  January:1,February:2,March:3,April:4,May:5,June:6,July:7,August:8,September:9,October:10,November:11,December:12};
                    var monthNum = months[month] || months[month.substring(0,3)];
                    if (monthNum) {
                        var dateStr = year + '-' + pad(monthNum) + '-' + pad(day);
                        document.getElementById('date').value = dateStr;
                    }
                }
                
                // Try to extract kennel and run from first part
                var titlePart = parts[0];
                var kennelMatch = titlePart.match(/(Dallas Urban Hash|Dallas Hash|Ft\.?\s*Worth Hash|Full Moon Hash|DUH3?|FWHHH|FMH3?|DFWHHH)\s*(?:Run)?\s*[#№]?\s*(\d+)/i);
                if (kennelMatch) {
                    var kennel = kennelMatch[1];
                    var runNum = kennelMatch[2];
                    
                    // Normalize kennel names
                    if (kennel.match(/Dallas Urban|DUH/i)) {
                        document.getElementById('run_number').value = 'Dallas Urban Hash Run #' + runNum;
                    } else if (kennel.match(/Full Moon|FMH/i)) {
                        document.getElementById('run_number').value = 'Full Moon Hash Run #' + runNum;
                    } else if (kennel.match(/Ft\.?\s*Worth|FWHHH/i)) {
                        document.getElementById('run_number').value = 'Ft Worth Hash Run #' + runNum;
                    } else if (kennel.match(/Dallas.*Hash|DFWHHH/i) && !kennel.match(/Urban/i)) {
                        document.getElementById('run_number').value = 'Dallas Hash Run #' + runNum;
                    } else {
                        document.getElementById('run_number').value = kennel + ' Run #' + runNum;
                    }
                }
            }
            // Remove first line from content
            lines.shift();
            content = lines.join('\n');
        }
        
        // Look for "Run #" or "Run №" in content if not found yet
        if (!document.getElementById('run_number').value) {
            var runMatch = content.match(/(Dallas Urban Hash|Dallas Hash|Ft\.?\s*Worth Hash|Full Moon Hash|DUH3?|FWHHH|FMH3?|DFWHHH)?\s*(?:Run|Hash)?\s*[#№]\s*(\d+)/i);
            if (runMatch) {
                var kennel = runMatch[1] || 'Dallas Hash';
                var runNum = runMatch[2];
                
                // Normalize kennel names
                if (kennel.match(/Dallas Urban|DUH/i)) {
                    document.getElementById('run_number').value = 'Dallas Urban Hash Run #' + runNum;
                } else if (kennel.match(/Full Moon|FMH/i)) {
                    document.getElementById('run_number').value = 'Full Moon Hash Run #' + runNum;
                } else if (kennel.match(/Ft\.?\s*Worth|FWHHH/i)) {
                    document.getElementById('run_number').value = 'Ft Worth Hash Run #' + runNum;
                } else {
                    document.getElementById('run_number').value = 'Dallas Hash Run #' + runNum;
                }
            }
        }
        
        // Look for "Hares:" or "Hare:"
        var hareMatch = content.match(/Hares?:\s*([^\n]+)/i);
        if (hareMatch) {
            var hares = hareMatch[1].trim();
            // Remove trailing periods
            hares = hares.replace(/\.\s*$/, '');
            document.getElementById('hares').value = hares;
            
            // Remove the hare line from content
            content = content.replace(/\n?\s*Hares?:\s*[^\n]+\n?/gi, '\n');
        }
        
        // Look for "By:" to extract author
        var authorMatch = content.match(/By:\s*([^\n]+)/i);
        if (authorMatch) {
            var author = authorMatch[1].trim();
            document.getElementById('author').value = author;
            // Remove the by line
            content = content.replace(/\n?\s*By:\s*[^\n]+\n?/gi, '\n');
        }
        
        // Try to extract title - look for "Title:" line
        var titleMatch = content.match(/Title:\s*([^\n]+)/i);
        if (titleMatch) {
            var title = titleMatch[1].trim();
            document.getElementById('title').value = title;
            // Remove the title line
            content = content.replace(/\n?\s*Title:\s*[^\n]+\n?/gi, '\n');
        } else {
            // Look for text after "Write-up" 
            titleMatch = content.match(/Write-up[^|]*?(?:for|:)\s*([^\n|]+)/i);
            if (titleMatch) {
                var title = titleMatch[1].trim();
                // Clean up the title
                title = title.replace(/^(?:Dallas Urban Hash|Dallas Hash|Ft\.?\s*Worth Hash|Full Moon Hash|DUH3?|FWHHH|FMH3?|DFWHHH)\s*(?:Run)?\s*[#№]?\s*\d+[:\s-]*/i, '');
                title = title.replace(/\s*\|\s*\w+\s+\d+,?\s+\d{4}\s*$/, '');
                if (title.length > 0 && title.length < 100) {
                    document.getElementById('title').value = title;
                }
            }
        }
        
        // Clean up content - remove metadata lines
        content = content.replace(/^Write-up.*?\n/i, '');
        content = content.replace(/Hares?:\s*[^\n]+\n?/gi, '');
        content = content.replace(/Title:\s*[^\n]+\n?/gi, '');
        content = content.replace(/By:\s*[^\n]+\n?/gi, '');
        content = content.trim();
        
        document.getElementById('content').value = content;
        
        alert('Metadata extracted! Review the fields and adjust as needed.');
    }
    
    function pad(n) {
        return n < 10 ? '0' + n : n;
    }
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"],
        input[type="date"],
        input[type="file"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        input[type="file"] {
            padding: 5px;
        }
        textarea {
            min-height: 400px;
            font-family: 'Courier New', monospace;
            line-height: 1.6;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        button {
            margin-top: 20px;
            padding: 12px 30px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        .success a {
            color: #1b5e20;
            font-weight: bold;
            text-decoration: none;
            margin-left: 10px;
            padding: 5px 15px;
            background-color: #c8e6c9;
            border-radius: 3px;
            display: inline-block;
            margin-top: 10px;
        }
        .success a:hover {
            background-color: #a5d6a7;
        }
        .help-text {
            font-size: 12px;
            color: #777;
            font-style: italic;
            margin-top: 5px;
        }
        .required {
            color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hash Write-Up Generator</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br>
                <a href="<?php echo htmlspecialchars($writeup_url); ?>" target="_blank">View Write-Up &rarr;</a>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-row">
                <div>
                    <label for="run_number">Run Number <span class="required">*</span></label>
                    <input type="text" id="run_number" name="run_number" required 
                           value="<?php echo htmlspecialchars(isset($_POST['run_number']) ? $_POST['run_number'] : ''); ?>" 
                           placeholder="Dallas Urban Hash Run #825">
                    <div class="help-text">Include kennel name (e.g., "Dallas Urban Hash Run #825", "Full Moon Hash Run #42")</div>
                </div>
                
                <div>
                    <label for="date">Date <span class="required">*</span></label>
                    <input type="date" id="date" name="date" required 
                           value="<?php echo htmlspecialchars(isset($_POST['date']) ? $_POST['date'] : date('Y-m-d')); ?>">
                    <div class="help-text">File will be saved as YYYY-MM-DD.html</div>
                </div>
            </div>
            
            <label for="title">Write-Up Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" required 
                   value="<?php echo htmlspecialchars(isset($_POST['title']) ? $_POST['title'] : ''); ?>" 
                   placeholder="Flour, Frostbite, and Fluffy: A Trail to Regret">
            
            <label for="hares">Hares <span class="required">*</span></label>
            <input type="text" id="hares" name="hares" required 
                   value="<?php echo htmlspecialchars(isset($_POST['hares']) ? $_POST['hares'] : ''); ?>" 
                   placeholder="Too Many">
            
            <div class="form-row">
                <div>
                    <label for="author">Author Name <span class="required">*</span></label>
                    <input type="text" id="author" name="author" required 
                           value="<?php echo htmlspecialchars(isset($_POST['author']) ? $_POST['author'] : ''); ?>" 
                           placeholder="Lumber Jackoff and Principal's Bitch">
                </div>
                
                <div>
                    <label for="staff">Staff Title</label>
                    <input type="text" id="staff" name="staff" 
                           value="<?php echo htmlspecialchars(isset($_POST['staff']) ? $_POST['staff'] : 'Staff Writer'); ?>" 
                           placeholder="Staff Writer">
                </div>
            </div>
            
            <label for="image">Image (Optional)</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif">
            <div class="help-text">Upload an image to appear at the bottom of the right column. Max size: 500KB. Formats: JPG, PNG, GIF</div>
            
            <label for="content">Write-Up Content <span class="required">*</span></label>
            <div class="help-text">Paste or write your content. Use double line breaks for new paragraphs. The first letter will automatically be styled as a drop cap.</div>
            <textarea id="content" name="content" required placeholder="They say hashers are never late, they arrive precisely when the beer calls them...

The trail, from what I've been told, was a relentless 5-mile odyssey through icy winds and shattered hopes..."><?php echo htmlspecialchars(isset($_POST['content']) ? $_POST['content'] : ''); ?></textarea>
            
            <button type="button" onclick="extractMetadata()" style="background-color: #FF9800; margin-top: 10px;">Extract Metadata ↑</button>
            <div class="help-text">Click this button after pasting to auto-fill Run Number, Date, Hares, and Title from the content.</div>
            
            <button type="submit">Generate Write-Up</button>
        </form>
    </div>
</body>
</html>