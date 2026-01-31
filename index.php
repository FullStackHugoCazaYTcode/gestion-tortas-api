<?php
// Redirigir a la API
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => 'GestionTortas API is running']);
?>
