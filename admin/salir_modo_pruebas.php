<?php
require_once __DIR__ . '/../includes/auth.php';
requiereRol(['ADMINISTRADOR']);
unset($_SESSION['club_actuando_id']);
header('Location: ' . BASE_URL . '/admin/index.php');
exit;
