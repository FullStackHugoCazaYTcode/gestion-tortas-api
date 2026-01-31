<?php
require_once 'config.php';
setCorsHeaders();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'crear': crearPedido(); break;
    case 'porFecha': pedidosPorFecha(); break;
    case 'porMes': pedidosPorMes(); break;
    case 'eliminar': eliminarPedido(); break;
    default: jsonResponse(['error' => 'Acción no válida'], 400);
}

function crearPedido() {
    $data = getJsonInput();
    if (empty($data['titulo']) || empty($data['fecha']) || empty($data['hora']) || empty($data['usuario_id'])) {
        jsonResponse(['error' => 'Faltan campos requeridos'], 400);
    }
    
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("INSERT INTO pedidos (titulo, descripcion, fecha, hora, creado_por) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$data['titulo'], $data['descripcion'] ?? '', $data['fecha'], $data['hora'], $data['usuario_id']]);
        $pedidoId = $conn->lastInsertId();
        jsonResponse(['success' => true, 'pedido' => ['id' => (int)$pedidoId, 'titulo' => $data['titulo'], 'fecha' => $data['fecha'], 'hora' => $data['hora']]]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al crear pedido'], 500);
    }
}

function pedidosPorFecha() {
    $fecha = $_GET['fecha'] ?? '';
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT p.*, u.nombre as creador_nombre FROM pedidos p LEFT JOIN usuarios u ON p.creado_por = u.id WHERE p.fecha = ? ORDER BY p.hora");
        $stmt->execute([$fecha]);
        $pedidos = $stmt->fetchAll();
        foreach ($pedidos as &$p) $p['id'] = (int)$p['id'];
        jsonResponse(['success' => true, 'pedidos' => $pedidos]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener pedidos'], 500);
    }
}

function pedidosPorMes() {
    $año = $_GET['año'] ?? date('Y');
    $mes = str_pad($_GET['mes'] ?? date('m'), 2, '0', STR_PAD_LEFT);
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT fecha, COUNT(*) as cantidad FROM pedidos WHERE fecha LIKE ? GROUP BY fecha");
        $stmt->execute(["$año-$mes%"]);
        $pedidos = $stmt->fetchAll();
        foreach ($pedidos as &$p) $p['cantidad'] = (int)$p['cantidad'];
        jsonResponse(['success' => true, 'pedidos' => $pedidos]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener pedidos'], 500);
    }
}

function eliminarPedido() {
    $data = getJsonInput();
    $conn = getConnection();
    try {
        $conn->prepare("DELETE FROM pedidos WHERE id = ?")->execute([$data['pedido_id']]);
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al eliminar'], 500);
    }
}
?>
