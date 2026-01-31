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
    case 'actualizar': actualizarVenta(); break;
    case 'cambiarEstado': cambiarEstadoVenta(); break;
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
        
        $stmt = $conn->prepare("INSERT INTO ventas (codigo, producto, cliente, fecha_entrega, precio, notas, creado_por, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')");
        $stmt->execute([$codigo, $data['producto'], $data['cliente'], $data['fecha_entrega'], $data['precio'], $data['notas'] ?? '', $data['usuario_id']]);
        $ventaId = $conn->lastInsertId();
        
        $stmt = $conn->prepare("INSERT INTO venta_participantes (venta_id, usuario_id) VALUES (?, ?)");
        $stmt->execute([$ventaId, $data['usuario_id']]);
        
        jsonResponse(['success' => true, 'venta' => ['id' => (int)$ventaId, 'codigo' => $codigo, 'producto' => $data['producto'], 'cliente' => $data['cliente'], 'fecha_entrega' => $data['fecha_entrega'], 'precio' => floatval($data['precio']), 'estado' => 'pendiente']]);
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
        foreach ($ventas as &$v) { 
            $v['id'] = (int)$v['id']; 
            $v['precio'] = floatval($v['precio']); 
            $v['total_gastos'] = floatval($v['total_gastos']);
            $v['estado'] = $v['estado'] ?? 'pendiente';
        }
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
        $venta['id'] = (int)$venta['id']; 
        $venta['precio'] = floatval($venta['precio']);
        $venta['estado'] = $venta['estado'] ?? 'pendiente';
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
        $venta['id'] = (int)$venta['id']; 
        $venta['precio'] = floatval($venta['precio']);
        $venta['estado'] = $venta['estado'] ?? 'pendiente';
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
        $venta['id'] = (int)$venta['id']; 
        $venta['precio'] = floatval($venta['precio']);
        $venta['estado'] = $venta['estado'] ?? 'pendiente';
        jsonResponse(['success' => true, 'venta' => $venta]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al unirse'], 500);
    }
}

function misVentas() {
    $usuarioId = $_GET['usuario_id'] ?? 0;
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT v.*, u.nombre as creador_nombre, COALESCE((SELECT SUM(monto) FROM gastos WHERE venta_id = v.id), 0) as total_gastos FROM ventas v JOIN venta_participantes vp ON v.id = vp.venta_id LEFT JOIN usuarios u ON v.creado_por = u.id WHERE vp.usuario_id = ? ORDER BY v.id DESC");
        $stmt->execute([$usuarioId]);
        $ventas = $stmt->fetchAll();
        foreach ($ventas as &$v) { 
            $v['id'] = (int)$v['id']; 
            $v['precio'] = floatval($v['precio']); 
            $v['total_gastos'] = floatval($v['total_gastos']);
            $v['estado'] = $v['estado'] ?? 'pendiente';
        }
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

// ==========================================
// NUEVAS FUNCIONES: EDITAR VENTAS
// ==========================================

function actualizarVenta() {
    $data = getJsonInput();
    
    if (empty($data['venta_id'])) {
        jsonResponse(['error' => 'ID de venta requerido'], 400);
    }
    
    $ventaId = (int)$data['venta_id'];
    $conn = getConnection();
    
    try {
        // Verificar que la venta existe
        $stmt = $conn->prepare("SELECT id FROM ventas WHERE id = ?");
        $stmt->execute([$ventaId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Venta no encontrada'], 404);
        }
        
        // Construir la consulta de actualización
        $campos = [];
        $valores = [];
        
        if (isset($data['producto'])) {
            $campos[] = "producto = ?";
            $valores[] = $data['producto'];
        }
        if (isset($data['cliente'])) {
            $campos[] = "cliente = ?";
            $valores[] = $data['cliente'];
        }
        if (isset($data['fecha_entrega'])) {
            $campos[] = "fecha_entrega = ?";
            $valores[] = $data['fecha_entrega'];
        }
        if (isset($data['precio'])) {
            $campos[] = "precio = ?";
            $valores[] = floatval($data['precio']);
        }
        if (isset($data['notas'])) {
            $campos[] = "notas = ?";
            $valores[] = $data['notas'];
        }
        if (isset($data['estado'])) {
            $campos[] = "estado = ?";
            $valores[] = $data['estado'];
        }
        
        if (empty($campos)) {
            jsonResponse(['error' => 'No hay datos para actualizar'], 400);
        }
        
        $valores[] = $ventaId;
        $sql = "UPDATE ventas SET " . implode(", ", $campos) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($valores);
        
        // Obtener venta actualizada
        $stmt = $conn->prepare("SELECT v.*, u.nombre as creador_nombre FROM ventas v LEFT JOIN usuarios u ON v.creado_por = u.id WHERE v.id = ?");
        $stmt->execute([$ventaId]);
        $venta = $stmt->fetch();
        $venta['id'] = (int)$venta['id'];
        $venta['precio'] = floatval($venta['precio']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Venta actualizada correctamente',
            'venta' => $venta
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al actualizar venta: ' . $e->getMessage()], 500);
    }
}

function cambiarEstadoVenta() {
    $data = getJsonInput();
    
    if (empty($data['venta_id']) || empty($data['estado'])) {
        jsonResponse(['error' => 'ID de venta y estado son requeridos'], 400);
    }
    
    $ventaId = (int)$data['venta_id'];
    $estado = $data['estado'];
    
    // Validar estado
    $estadosValidos = ['pendiente', 'realizado', 'cancelado'];
    if (!in_array($estado, $estadosValidos)) {
        jsonResponse(['error' => 'Estado no válido'], 400);
    }
    
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE ventas SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $ventaId]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Venta no encontrada'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'estado' => $estado
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al cambiar estado'], 500);
    }
}
?>
