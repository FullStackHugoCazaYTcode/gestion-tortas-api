<?php
require_once 'config.php';
setCorsHeaders();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'crear': crearVenta(); break;
    case 'listar': listarVentas(); break;
    case 'obtener': obtenerVenta(); break;
    case 'porCodigo': obtenerVentaPorCodigo(); break;
    case 'unirse': unirseAVenta(); break;
    case 'misVentas': misVentas(); break;
    case 'eliminar': eliminarVenta(); break;
    default: jsonResponse(['error' => 'Acción no válida'], 400);
}

function generarCodigo() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $codigo = 'VTA-';
    for ($i = 0; $i < 6; $i++) $codigo .= $chars[rand(0, strlen($chars) - 1)];
    return $codigo;
}

function crearVenta() {
    $data = getJsonInput();
    if (empty($data['producto']) || empty($data['cliente']) || empty($data['fecha_entrega']) || !isset($data['precio']) || empty($data['usuario_id'])) {
        jsonResponse(['error' => 'Faltan campos requeridos'], 400);
    }
    
    $conn = getConnection();
    try {
        do {
            $codigo = generarCodigo();
            $stmt = $conn->prepare("SELECT id FROM ventas WHERE codigo = ?");
            $stmt->execute([$codigo]);
        } while ($stmt->fetch());
        
        $stmt = $conn->prepare("INSERT INTO ventas (codigo, producto, cliente, fecha_entrega, precio, notas, creado_por, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'activa')");
        $stmt->execute([$codigo, $data['producto'], $data['cliente'], $data['fecha_entrega'], $data['precio'], $data['notas'] ?? '', $data['usuario_id']]);
        $ventaId = $conn->lastInsertId();
        
        $stmt = $conn->prepare("INSERT INTO venta_participantes (venta_id, usuario_id) VALUES (?, ?)");
        $stmt->execute([$ventaId, $data['usuario_id']]);
        
        jsonResponse(['success' => true, 'venta' => ['id' => (int)$ventaId, 'codigo' => $codigo, 'producto' => $data['producto'], 'cliente' => $data['cliente'], 'fecha_entrega' => $data['fecha_entrega'], 'precio' => floatval($data['precio'])]]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al crear venta: ' . $e->getMessage()], 500);
    }
}

function listarVentas() {
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT v.*, u.nombre as creador_nombre, COALESCE((SELECT SUM(monto) FROM gastos WHERE venta_id = v.id), 0) as total_gastos FROM ventas v LEFT JOIN usuarios u ON v.creado_por = u.id ORDER BY v.id DESC");
        $stmt->execute();
        $ventas = $stmt->fetchAll();
        foreach ($ventas as &$v) { $v['id'] = (int)$v['id']; $v['precio'] = floatval($v['precio']); $v['total_gastos'] = floatval($v['total_gastos']); }
        jsonResponse(['success' => true, 'ventas' => $ventas]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener ventas'], 500);
    }
}

function obtenerVenta() {
    $id = $_GET['id'] ?? 0;
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT v.*, u.nombre as creador_nombre FROM ventas v LEFT JOIN usuarios u ON v.creado_por = u.id WHERE v.id = ?");
        $stmt->execute([$id]);
        $venta = $stmt->fetch();
        if (!$venta) jsonResponse(['error' => 'Venta no encontrada'], 404);
        $venta['id'] = (int)$venta['id']; $venta['precio'] = floatval($venta['precio']);
        jsonResponse(['success' => true, 'venta' => $venta]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener venta'], 500);
    }
}

function obtenerVentaPorCodigo() {
    $codigo = strtoupper($_GET['codigo'] ?? '');
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT v.*, u.nombre as creador_nombre FROM ventas v LEFT JOIN usuarios u ON v.creado_por = u.id WHERE v.codigo = ?");
        $stmt->execute([$codigo]);
        $venta = $stmt->fetch();
        if (!$venta) jsonResponse(['error' => 'Venta no encontrada'], 404);
        $venta['id'] = (int)$venta['id']; $venta['precio'] = floatval($venta['precio']);
        jsonResponse(['success' => true, 'venta' => $venta]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener venta'], 500);
    }
}

function unirseAVenta() {
    $data = getJsonInput();
    if (empty($data['codigo']) || empty($data['usuario_id'])) jsonResponse(['error' => 'Código y usuario requeridos'], 400);
    
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT * FROM ventas WHERE codigo = ?");
        $stmt->execute([strtoupper($data['codigo'])]);
        $venta = $stmt->fetch();
        if (!$venta) jsonResponse(['error' => 'Venta no encontrada'], 404);
        
        $stmt = $conn->prepare("SELECT id FROM venta_participantes WHERE venta_id = ? AND usuario_id = ?");
        $stmt->execute([$venta['id'], $data['usuario_id']]);
        if (!$stmt->fetch()) {
            $stmt = $conn->prepare("INSERT INTO venta_participantes (venta_id, usuario_id) VALUES (?, ?)");
            $stmt->execute([$venta['id'], $data['usuario_id']]);
        }
        $venta['id'] = (int)$venta['id']; $venta['precio'] = floatval($venta['precio']);
        jsonResponse(['success' => true, 'venta' => $venta]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al unirse'], 500);
    }
}

function misVentas() {
    $usuarioId = $_GET['usuario_id'] ?? 0;
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT v.*, u.nombre as creador_nombre, COALESCE((SELECT SUM(monto) FROM gastos WHERE venta_id = v.id), 0) as total_gastos FROM ventas v JOIN venta_participantes vp ON v.id = vp.venta_id LEFT JOIN usuarios u ON v.creado_por = u.id WHERE vp.usuario_id = ? AND v.estado = 'activa' ORDER BY v.id DESC");
        $stmt->execute([$usuarioId]);
        $ventas = $stmt->fetchAll();
        foreach ($ventas as &$v) { $v['id'] = (int)$v['id']; $v['precio'] = floatval($v['precio']); $v['total_gastos'] = floatval($v['total_gastos']); }
        jsonResponse(['success' => true, 'ventas' => $ventas]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener ventas'], 500);
    }
}

function eliminarVenta() {
    $data = getJsonInput();
    $conn = getConnection();
    try {
        $conn->prepare("DELETE FROM gastos WHERE venta_id = ?")->execute([$data['venta_id']]);
        $conn->prepare("DELETE FROM venta_participantes WHERE venta_id = ?")->execute([$data['venta_id']]);
        $conn->prepare("DELETE FROM ventas WHERE id = ?")->execute([$data['venta_id']]);
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al eliminar'], 500);
    }
}
?>
