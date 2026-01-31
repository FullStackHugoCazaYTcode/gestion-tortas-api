<?php
require_once 'config.php';
setCorsHeaders();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'semanal': reporteSemanal(); break;
    case 'porEmpleado': reportePorEmpleado(); break;
    case 'estadisticas': estadisticas(); break;
    default: jsonResponse(['error' => 'Acción no válida'], 400);
}

function reporteSemanal() {
    $fechaInicio = $_GET['fecha_inicio'] ?? '';
    $fechaFin = $_GET['fecha_fin'] ?? '';
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(precio), 0) as total_ventas, COUNT(*) as num_ventas FROM ventas WHERE fecha_entrega >= ? AND fecha_entrega <= ?");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $ventas = $stmt->fetch();
        
        $stmt = $conn->prepare("SELECT COALESCE(SUM(g.monto), 0) as total_gastos FROM gastos g JOIN ventas v ON g.venta_id = v.id WHERE v.fecha_entrega >= ? AND v.fecha_entrega <= ?");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $gastos = $stmt->fetch();
        
        jsonResponse(['success' => true, 'reporte' => [
            'total_ventas' => floatval($ventas['total_ventas']),
            'total_gastos' => floatval($gastos['total_gastos']),
            'num_ventas' => (int)$ventas['num_ventas']
        ]]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al generar reporte'], 500);
    }
}

function reportePorEmpleado() {
    $fechaInicio = $_GET['fecha_inicio'] ?? '';
    $fechaFin = $_GET['fecha_fin'] ?? '';
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT u.id, u.nombre, COUNT(v.id) as num_ventas, COALESCE(SUM(v.precio), 0) as total_ventas FROM usuarios u LEFT JOIN ventas v ON v.creado_por = u.id AND v.fecha_entrega >= ? AND v.fecha_entrega <= ? GROUP BY u.id ORDER BY total_ventas DESC");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $empleados = $stmt->fetchAll();
        foreach ($empleados as &$e) { $e['id'] = (int)$e['id']; $e['num_ventas'] = (int)$e['num_ventas']; $e['total_ventas'] = floatval($e['total_ventas']); }
        jsonResponse(['success' => true, 'empleados' => $empleados]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al generar reporte'], 500);
    }
}

function estadisticas() {
    $conn = getConnection();
    $hoy = date('Y-m-d');
    $inicioSemana = date('Y-m-d', strtotime('monday this week'));
    $finSemana = date('Y-m-d', strtotime('sunday this week'));
    
    try {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(precio), 0) as total FROM ventas WHERE fecha_entrega >= ? AND fecha_entrega <= ?");
        $stmt->execute([$inicioSemana, $finSemana]);
        $ventas = $stmt->fetch();
        
        $stmt = $conn->prepare("SELECT COALESCE(SUM(g.monto), 0) as total FROM gastos g JOIN ventas v ON g.venta_id = v.id WHERE v.fecha_entrega >= ? AND v.fecha_entrega <= ?");
        $stmt->execute([$inicioSemana, $finSemana]);
        $gastos = $stmt->fetch();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas WHERE fecha_entrega >= ? AND fecha_entrega <= ?");
        $stmt->execute([$inicioSemana, $finSemana]);
        $numVentas = $stmt->fetch();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos WHERE fecha >= ?");
        $stmt->execute([$hoy]);
        $pendientes = $stmt->fetch();
        
        jsonResponse(['success' => true, 'estadisticas' => [
            'ganancias' => floatval($ventas['total']) - floatval($gastos['total']),
            'ventas' => (int)$numVentas['total'],
            'pendientes' => (int)$pendientes['total']
        ]]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener estadísticas'], 500);
    }
}
?>
