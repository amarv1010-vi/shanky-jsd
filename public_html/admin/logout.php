<?php
require_once __DIR__ . '/auth.php';
admin_logout();
header('Location: index.php');
exit;
