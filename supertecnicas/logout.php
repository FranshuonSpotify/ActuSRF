<?php
session_start();
unset($_SESSION['st_equipo_id']);
session_destroy();
header('Location: index.php');
exit;
