<?php
session_start();
unset($_SESSION['is_admin']);
session_destroy();
header('Location: index.php');
exit;