<?php
require_once __DIR__ . '/config.php';
$pdo = getDB();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS vsl_shuffle (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_id INT NOT NULL,
        page_url VARCHAR(500) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_domain_page (domain_id, page_url(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

echo "vsl_shuffle table created.<br>";

// Add config columns if missing
$cols = $pdo->query("SHOW COLUMNS FROM vsl_shuffle")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('page_title', $cols)) {
    $pdo->exec("ALTER TABLE vsl_shuffle ADD COLUMN page_title VARCHAR(255) DEFAULT '' AFTER page_url");
    echo "Added page_title column.<br>";
}
if (!in_array('has_video', $cols)) {
    $pdo->exec("ALTER TABLE vsl_shuffle ADD COLUMN has_video TINYINT(1) NOT NULL DEFAULT 0 AFTER page_title");
    echo "Added has_video column.<br>";
}
if (!in_array('video_source', $cols)) {
    $pdo->exec("ALTER TABLE vsl_shuffle ADD COLUMN video_source VARCHAR(50) DEFAULT '' AFTER has_video");
    echo "Added video_source column.<br>";
}

if (!in_array('embed_type', $cols)) {
    $pdo->exec("ALTER TABLE vsl_shuffle ADD COLUMN embed_type VARCHAR(20) DEFAULT '' AFTER video_source");
    echo "Added embed_type column.<br>";
}
if (!in_array('embed_code', $cols)) {
    $pdo->exec("ALTER TABLE vsl_shuffle ADD COLUMN embed_code TEXT DEFAULT NULL AFTER embed_type");
    echo "Added embed_code column.<br>";
}
if (!in_array('vidalytics_email', $cols)) {
    $pdo->exec("ALTER TABLE vsl_shuffle ADD COLUMN vidalytics_email VARCHAR(255) DEFAULT '' AFTER video_source");
    echo "Added vidalytics_email column.<br>";
}

if (!in_array('confirmed', $cols)) {
    $pdo->exec("ALTER TABLE vsl_shuffle ADD COLUMN confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER vidalytics_email");
    echo "Added confirmed column.<br>";
}

echo "Done.";
