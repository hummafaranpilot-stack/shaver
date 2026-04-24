<?php
require_once __DIR__ . '/config.php';
$pdo = getDB();

try {
    $pdo->exec("ALTER TABLE shaving_sessions ADD COLUMN cb_path_find VARCHAR(255) DEFAULT NULL AFTER replace_sub_id");
    echo "Added cb_path_find column<br>";
} catch (Exception $e) {
    echo "cb_path_find: " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("ALTER TABLE shaving_sessions ADD COLUMN cb_path_replace VARCHAR(255) DEFAULT NULL AFTER cb_path_find");
    echo "Added cb_path_replace column<br>";
} catch (Exception $e) {
    echo "cb_path_replace: " . $e->getMessage() . "<br>";
}

echo "<br><strong>Done!</strong> You can delete this file now.";
