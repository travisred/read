<?php
// CLI only - fetches feeds, writes daily HTML digest, marks items as read
chdir(__DIR__);
require('config.php');

ini_set('user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36');

$pdo = new PDO("sqlite:" . $sqliteFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$log = fopen(__DIR__ . '/log.txt', 'a');

fwrite($log, date('Y-m-d H:i:s') . ": Daily run starting\n");

// --- Fetch feeds ---
require('feeds.php');

foreach ($feeds as $site => $feed) {
    fwrite($log, date('Y-m-d H:i:s') . ": Fetching $site from $feed\n");
    try {
        $xml = simplexml_load_file($feed);
    } catch (Exception $e) {
        fwrite($log, $e->getMessage() . "\n");
        continue;
    }
    $items = $xml->channel->item;
    if (empty($items)) $items = $xml->entry;
    if (empty($items)) $items = $xml->item;
    if (empty($items)) {
        fwrite($log, date('Y-m-d H:i:s') . ": No items found for $site\n");
        continue;
    }
    fwrite($log, "Processing $site, found " . count($items) . " items\n");

    for ($i = 0; $i < count($items); $i++) {
        $link = $items[$i]->link;
        if (strpos($link, 'http') === false) {
            $link = $items[$i]->link->attributes();
            $link = $link['href'];
        }
        $comments = !empty($items[$i]->comments) ? $items[$i]->comments : '';
        $check = $pdo->prepare("SELECT id FROM rss WHERE title = :title AND site = :site");
        $check->execute([':title' => $items[$i]->title, ':site' => $site]);
        if (empty($check->fetch(PDO::FETCH_ASSOC))) {
            $ins = $pdo->prepare("INSERT INTO rss(title, url, comments, site, is_read, is_starred) VALUES(:title, :url, :comments, :site, 0, 0)");
            $ins->execute([
                ':title' => $items[$i]->title,
                ':url' => $link,
                ':comments' => $comments,
                ':site' => $site,
            ]);
        }
    }
}

// --- Output unread items to daily HTML ---
$date = date('Y-m-d');
$dailyDir = __DIR__ . '/docs';
if (!is_dir($dailyDir)) {
    mkdir($dailyDir, 0775, true);
}
$outFile = $dailyDir . '/' . $date . '.html';
$indexFile = $dailyDir . '/index.html';

$stmt = $pdo->prepare("SELECT * FROM rss WHERE is_read = 0 ORDER BY site, id DESC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ids = array_column($rows, 'id');

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily RSS - ' . $date . '</title>
    <style>
        html { font-size: 20px; width: 100%; }
    </style>
</head>
<body>
<a href="' . htmlspecialchars('/read/docs/' . date('Y-m-d', strtotime('-1 day')) . '.html') . '">Yesterday</a> - <a href="/read/docs">Today</a>
';

$site_last = '';
foreach ($rows as $row) {
    if ($row['site'] !== $site_last) {
        $html .= '<h3>' . htmlspecialchars($row['site']) . '</h3>' . "\n";
        $site_last = $row['site'];
    }
    $html .= '<p><a href="' . htmlspecialchars($row['url']) . '" target="_blank">' . htmlspecialchars($row['title']) . '</a>';
    if (!empty($row['comments'])) {
        $html .= ' - <a href="' . htmlspecialchars($row['comments']) . '" target="_blank">comments</a>';
    }
    $html .= '</p>' . "\n";
}

$html .= '</body>
</html>';

file_put_contents($outFile, $html);
file_put_contents($indexFile, $html);
fwrite($log, date('Y-m-d H:i:s') . ": Wrote " . count($rows) . " items to $outFile\n");

// --- Mark as read ---
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE rss SET is_read = 1 WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    fwrite($log, date('Y-m-d H:i:s') . ": Marked " . count($ids) . " items as read\n");
}

fclose($log);
