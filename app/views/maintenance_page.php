<?php
// Simple Maintenance Page
// This page should have minimal dependencies and avoid including complex headers/footers
// that might rely on database connections or other services that could be down during maintenance.

// Set a 503 Service Unavailable header
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
header('Retry-After: 3600'); // Suggest a retry after 1 hour (optional)

// Fetch site name for display, if available from a simple constant or default
$siteName = defined('SITE_NAME_BASIC') ? SITE_NAME_BASIC : 'Our Website';
if (class_exists('OptionModel') && isset($GLOBALS['pdo'])) { // Check if OptionModel and PDO might be available
    try {
        // This is a soft attempt, if DB is down, it will fail gracefully below.
        $optionModel = new OptionModel($GLOBALS['pdo']);
        $dbSiteName = $optionModel->getOption('site_name');
        if ($dbSiteName) {
            $siteName = $dbSiteName;
        }
    } catch (Exception $e) {
        // Ignore DB connection errors here, use default siteName
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - Under Maintenance</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            background-color: #f8f9fa; 
            color: #343a40; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            text-align: center; 
            padding: 20px;
            box-sizing: border-box;
        }
        .container { 
            background-color: #fff; 
            padding: 30px 40px; 
            border-radius: 8px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
            max-width: 600px;
            width: 100%;
        }
        h1 { 
            color: #007bff; 
            font-size: 2.5em;
            margin-bottom: 0.5em;
        }
        p { 
            font-size: 1.1em; 
            line-height: 1.6; 
            margin-bottom: 1em;
        }
        .icon {
            font-size: 4em;
            color: #ffc107;
            margin-bottom: 0.5em;
        }
        @media (max-width: 600px) {
            h1 { font-size: 2em; }
            p { font-size: 1em; }
            .icon { font-size: 3em; }
            .container { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">&#x26A0;&#xFE0F;</div> {/* Warning symbol emoji */}
        <h1>Under Maintenance</h1>
        <p>
            Our site, <strong><?php echo htmlspecialchars($siteName); ?></strong>, is currently down for scheduled maintenance.
            We are working hard to improve your experience.
        </p>
        <p>
            We expect to be back online shortly. Please check back soon.
        </p>,
        <p>
            Thank you for your patience!
        </p>
    </div>
</body>
</html>
