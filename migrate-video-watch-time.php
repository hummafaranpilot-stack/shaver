<?php
require_once __DIR__ . '/config.php';
$pdo = getDB();

$cols = $pdo->query("SHOW COLUMNS FROM affiliate_traffic")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('video_watch_time', $cols)) {
    $pdo->exec("ALTER TABLE affiliate_traffic ADD COLUMN video_watch_time INT DEFAULT NULL AFTER video_plays");
    echo "Added video_watch_time column to affiliate_traffic.<br>";
} else {
    echo "video_watch_time column already exists.<br>";
}

if (!in_array('magical_revealed', $cols)) {
    $pdo->exec("ALTER TABLE affiliate_traffic ADD COLUMN magical_revealed TINYINT(1) DEFAULT 0 AFTER video_watch_time");
    echo "Added magical_revealed column.<br>";
} else {
    echo "magical_revealed column already exists.<br>";
}

echo "Done.";
