<?php
require_once 'db.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; 
}
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } 

?>