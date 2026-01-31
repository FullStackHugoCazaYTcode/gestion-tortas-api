<?php
require_once 'config.php';
setCorsHeaders();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'agregar': agregarGasto(); break;
    case 'listar': listarGastos(); break;
    case 'eliminar': eliminarGasto(); break;
    default: jsonResponse(['error' => 'Acción no válida'], 400);
}

function agregarGasto() {
    $data = getJsonInput();
    if (empty($data['venta_id']) || empty($data['descripcion']) || !isset($data['monto']) || empty($data['usuario_id'])) {
        jsonResponse(['error' => 'Faltan campos requeridos'], 400);
    }
    
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("INSERT INTO gastos (venta_id, descripcion, monto, registrado_por) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['venta_id'], $data['descripcion'], $data['monto'], $data['usuario_id']]);
        $gastoId = $conn->lastInsertId();
        jsonResponse(['success' => true, 'gasto' => ['id' => (int)$gastoId, 'venta_id' => (int)$data['venta_id'], 'descripcion' => $data['descripcion'], 'monto' => floatval($data['monto'])]]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al agregar gasto'], 500);
    }
}

function listarGastos() {
    $ventaId = $_GET['venta_id'] ?? 0;
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT g.*, u.nombre as registrado_por_nombre FROM gastos g LEFT JOIN usuarios u ON g.registrado_por = u.id WHERE g.venta_id = ? ORDER BY g.id DESC");
        $stmt->execute([$ventaId]);
        $gastos = $stmt->fetchAll();
        foreach ($gastos as &$g) { $g['id'] = (int)$g['id']; $g['monto'] = floatval($g['monto']); }
        jsonResponse(['success' => true, 'gastos' => $gastos]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener gastos'], 500);
    }
}

function eliminarGasto() {
    $data = getJsonInput();
    $conn = getConnection();
    try {
        $conn->prepare("DELETE FROM gastos WHERE id = ?")->execute([$data['gasto_id']]);
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al eliminar'], 500);
    }
}
?>
