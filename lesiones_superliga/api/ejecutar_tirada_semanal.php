<?php
require_once __DIR__ . '/helpers.php';
jsonResponse(['success' => false, 'error' => 'deprecated_endpoint', 'message' => 'Usa commit_resultado.php desde la interfaz v5.2'], 410);
