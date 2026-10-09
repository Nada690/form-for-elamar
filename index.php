<?php
// Forward query string seamlessly to form.php (preserving any ?slug=...)
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: form.php" . $query);
exit;
