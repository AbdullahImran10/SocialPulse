<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

// Only Admin and Social Media Manager can import posts
if (!in_array(getUserRole(), ['Admin', 'Social Media Manager'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: import.php');
    exit;
}

// Verify CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid request. Please try again.');
}

// Validate selected IDs
$brandId = $_POST['brand_id'] ?? '';
$accountId = $_POST['account_id'] ?? '';

if (
    !ctype_digit((string) $brandId) ||
    !ctype_digit((string) $accountId)
) {
    exit('Invalid brand or account selected.');
}

$brandId = (int) $brandId;
$accountId = (int) $accountId;

// Check whether the user can access this brand
if (getUserRole() === 'Admin') {
    $brandStmt = $pdo->prepare(
        "SELECT brand_id FROM brands WHERE brand_id = ?"
    );

    $brandStmt->execute([$brandId]);
} else {
    $brandStmt = $pdo->prepare(
        "SELECT brand_id
         FROM brand_members
         WHERE brand_id = ? AND user_id = ?"
    );

    $brandStmt->execute([$brandId, getUserId()]);
}

if (!$brandStmt->fetch()) {
    exit('You do not have permission to import data for this brand.');
}

// Verify that the account belongs to the selected brand
$accountStmt = $pdo->prepare(
    "SELECT account_id
     FROM social_accounts
     WHERE account_id = ? AND brand_id = ?"
);

$accountStmt->execute([$accountId, $brandId]);

if (!$accountStmt->fetch()) {
    exit('Invalid social media account selected.');
}

// Validate uploaded file
if (
    !isset($_FILES['csv_file']) ||
    $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK
) {
    exit('Please upload a valid CSV file.');
}

$file = $_FILES['csv_file'];

// Maximum file size: 2 MB
if ($file['size'] > 2 * 1024 * 1024) {
    exit('File size must not exceed 2 MB.');
}

// Check file extension
$extension = strtolower(
    pathinfo($file['name'], PATHINFO_EXTENSION)
);

if ($extension !== 'csv') {
    exit('Only CSV files are allowed.');
}

// Open CSV file
$handle = fopen($file['tmp_name'], 'r');

if ($handle === false) {
    exit('Unable to read the uploaded file.');
}

// Expected CSV columns
$requiredHeaders = [
    'post_url',
    'published_at',
    'caption',
    'post_type',
    'views',
    'likes',
    'comments',
    'shares',
    'reach'
];

// Read header row
$headers = fgetcsv($handle, 0, ',', '"', '');

if ($headers === false) {
    fclose($handle);
    exit('The CSV file is empty.');
}

// Remove UTF-8 BOM if present
$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

$headers = array_map(
    fn($header) => strtolower(trim($header)),
    $headers
);

// Check that all required columns exist
if (
    count($headers) !== count(array_unique($headers)) ||
    array_diff($requiredHeaders, $headers)
) {
    fclose($handle);

    exit(
        'Invalid CSV format. Please use the required columns: ' .
        implode(', ', $requiredHeaders)
    );
}

// Map column names to their positions
$columnPositions = array_flip($headers);

$allowedPostTypes = [
    'Image',
    'Video',
    'Reel',
    'Story',
    'Carousel'
];

// MySQL BIGINT UNSIGNED maximum
$maxBigInt = '18446744073709551615';

// Validate unsigned BIGINT values without converting them to float
function validMetric($value, $maxBigInt)
{
    $value = trim($value);

    if ($value === '' || !ctype_digit($value)) {
        return false;
    }

    $normalized = ltrim($value, '0');

    if ($normalized === '') {
        $normalized = '0';
    }

    if (strlen($normalized) > strlen($maxBigInt)) {
        return false;
    }

    if (
        strlen($normalized) === strlen($maxBigInt) &&
        strcmp($normalized, $maxBigInt) > 0
    ) {
        return false;
    }

    return $normalized;
}

// Validate and normalize published date
function validPublishedDate($value)
{
    $value = trim($value);

    $formats = [
        'Y-m-d',
        'Y-m-d H:i:s'
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);

        $errors = DateTime::getLastErrors();

        if (
            $date !== false &&
            ($errors === false ||
                ($errors['warning_count'] === 0 &&
                 $errors['error_count'] === 0)) &&
            $date->format($format) === $value
        ) {
            return $format === 'Y-m-d'
                ? $date->format('Y-m-d 00:00:00')
                : $date->format('Y-m-d H:i:s');
        }
    }

    return false;
}

$validRows = [];
$errors = [];
$lineNumber = 1;
$maximumRows = 1000;

// Read and validate CSV rows
while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {

    $lineNumber++;

    // Skip completely empty rows
    if ($row === [null] || $row === []) {
        continue;
    }

    if (count($validRows) + count($errors) >= $maximumRows) {
        $errors[] = 'Maximum limit of 1000 CSV rows exceeded.';
        break;
    }

    if (count($row) !== count($headers)) {
        $errors[] = "Row $lineNumber: Incorrect number of columns.";
        continue;
    }

    // Get values using their column names
    $data = [];

    foreach ($requiredHeaders as $header) {
        $data[$header] = trim($row[$columnPositions[$header]]);
    }

    // Validate date
    $publishedAt = validPublishedDate($data['published_at']);

    if ($publishedAt === false) {
        $errors[] = "Row $lineNumber: Invalid published_at date.";
        continue;
    }

    // Validate post type
    if (!in_array($data['post_type'], $allowedPostTypes, true)) {
        $errors[] = "Row $lineNumber: Invalid post_type.";
        continue;
    }

    // Validate URL
    $postUrl = $data['post_url'];

    if (
        $postUrl !== '' &&
        (
            strlen($postUrl) > 500 ||
            !filter_var($postUrl, FILTER_VALIDATE_URL) ||
            !in_array(
                strtolower(parse_url($postUrl, PHP_URL_SCHEME) ?? ''),
                ['http', 'https'],
                true
            )
        )
    ) {
        $errors[] = "Row $lineNumber: Invalid post URL.";
        continue;
    }

    // Validate metrics
    $metrics = [];

    $metricColumns = [
        'views',
        'likes',
        'comments',
        'shares',
        'reach'
    ];

    $invalidMetric = false;

    foreach ($metricColumns as $column) {
        $value = validMetric($data[$column], $maxBigInt);

        if ($value === false) {
            $errors[] = "Row $lineNumber: Invalid $column value.";
            $invalidMetric = true;
            break;
        }

        $metrics[$column] = $value;
    }

    if ($invalidMetric) {
        continue;
    }

    $validRows[] = [
        'post_url' => $postUrl,
        'published_at' => $publishedAt,
        'caption' => $data['caption'],
        'post_type' => $data['post_type'],
        'metrics' => $metrics
    ];
}

fclose($handle);

// Insert valid rows
$imported = 0;
$duplicates = 0;

try {

    $pdo->beginTransaction();

    $checkDuplicate = $pdo->prepare(
        "SELECT post_id
         FROM posts
         WHERE account_id = ? AND post_url = ?
         LIMIT 1"
    );

    $insertPost = $pdo->prepare(
        "INSERT INTO posts
            (account_id, caption, post_type, published_at, post_url)
         VALUES (?, ?, ?, ?, ?)"
    );

    $insertMetrics = $pdo->prepare(
        "INSERT INTO post_metrics
            (post_id, views, likes, comments, shares, reach)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($validRows as $index => $post) {

        // Skip an existing post URL
        if ($post['post_url'] !== '') {

            $checkDuplicate->execute([
                $accountId,
                $post['post_url']
            ]);

            if ($checkDuplicate->fetch()) {
                $duplicates++;
                continue;
            }
        }

        // Insert post
        $insertPost->execute([
            $accountId,
            $post['caption'],
            $post['post_type'],
            $post['published_at'],
            $post['post_url'] !== '' ? $post['post_url'] : null
        ]);

        $postId = $pdo->lastInsertId();

        // Insert its metrics
        $insertMetrics->execute([
            $postId,
            $post['metrics']['views'],
            $post['metrics']['likes'],
            $post['metrics']['comments'],
            $post['metrics']['shares'],
            $post['metrics']['reach']
        ]);

        $imported++;
    }

    $pdo->commit();

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('CSV Import Error: ' . $e->getMessage());

    http_response_code(500);
    exit('An error occurred while importing the CSV file. Please try again.');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV Import Results</title>
</head>
<body>

    <h2>CSV Import Results</h2>

    <p>Successfully imported: <?= $imported ?></p>

    <p>Duplicate posts skipped: <?= $duplicates ?></p>

    <p>Invalid rows skipped: <?= count($errors) ?></p>

    <?php if (!empty($errors)): ?>

        <h3>Errors</h3>

        <ul>
            <?php foreach ($errors as $error): ?>
                <li>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

    <a href="import.php">Import Another CSV</a>

</body>
</html>