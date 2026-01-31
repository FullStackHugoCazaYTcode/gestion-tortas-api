<?php
require_once 'config.php';
setCorsHeaders();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'registro': registrarUsuario(); break;
    case 'login': loginUsuario(); break;
    case 'lista': listarUsuarios(); break;
    case 'actualizarPerfil': actualizarPerfil(); break;
    case 'cambiarPassword': cambiarPassword(); break;
    case 'cambiarRol': cambiarRol(); break;
    case 'cambiarEstado': cambiarEstado(); break;
    case 'eliminar': eliminarUsuario(); break;
    default: jsonResponse(['error' => 'Acción no válida'], 400);
}

function registrarUsuario() {
    $data = getJsonInput();
    if (empty($data['nombre']) || empty($data['email']) || empty($data['password'])) {
        jsonResponse(['error' => 'Todos los campos son requeridos'], 400);
    }
    
    $conn = getConnection();
    try {
        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Este correo ya está registrado'], 400);
        }
        
        // Verificar si es el primer usuario (será admin)
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM usuarios");
        $stmt->execute();
        $count = $stmt->fetch();
        $rol = $count['total'] == 0 ? 'admin' : 'empleado';
        
        // Crear usuario
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$data['nombre'], $data['email'], $passwordHash, $rol]);
        
        $userId = $conn->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'usuario' => [
                'id' => (int)$userId,
                'nombre' => $data['nombre'],
                'email' => $data['email'],
                'rol' => $rol,
                'activo' => 1
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
    }
}

function loginUsuario() {
    $data = getJsonInput();
    if (empty($data['email']) || empty($data['password'])) {
        jsonResponse(['error' => 'Email y contraseña son requeridos'], 400);
    }
    
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$data['email']]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            jsonResponse(['error' => 'Credenciales incorrectas'], 401);
        }
        
        if (!password_verify($data['password'], $usuario['password'])) {
            jsonResponse(['error' => 'Credenciales incorrectas'], 401);
        }
        
        // Verificar si está activo
        if (isset($usuario['activo']) && $usuario['activo'] == 0) {
            jsonResponse(['error' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'], 403);
        }
        
        jsonResponse([
            'success' => true,
            'usuario' => [
                'id' => (int)$usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'rol' => $usuario['rol'],
                'activo' => (int)($usuario['activo'] ?? 1)
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al iniciar sesión'], 500);
    }
}

function listarUsuarios() {
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("SELECT id, nombre, email, rol, activo, created_at FROM usuarios ORDER BY created_at DESC");
        $stmt->execute();
        $usuarios = $stmt->fetchAll();
        foreach ($usuarios as &$u) {
            $u['id'] = (int)$u['id'];
            $u['activo'] = (int)($u['activo'] ?? 1);
        }
        jsonResponse(['success' => true, 'usuarios' => $usuarios]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener usuarios'], 500);
    }
}

function actualizarPerfil() {
    $data = getJsonInput();
    
    if (empty($data['usuario_id'])) {
        jsonResponse(['error' => 'ID de usuario requerido'], 400);
    }
    
    $usuarioId = (int)$data['usuario_id'];
    $conn = getConnection();
    
    try {
        // Construir la consulta de actualización
        $campos = [];
        $valores = [];
        
        if (isset($data['nombre']) && !empty($data['nombre'])) {
            $campos[] = "nombre = ?";
            $valores[] = $data['nombre'];
        }
        
        if (empty($campos)) {
            jsonResponse(['error' => 'No hay datos para actualizar'], 400);
        }
        
        $valores[] = $usuarioId;
        $sql = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($valores);
        
        // Obtener usuario actualizado
        $stmt = $conn->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'usuario' => [
                'id' => (int)$usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'rol' => $usuario['rol']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al actualizar perfil'], 500);
    }
}

function cambiarPassword() {
    $data = getJsonInput();
    
    if (empty($data['usuario_id']) || empty($data['password_actual']) || empty($data['password_nueva'])) {
        jsonResponse(['error' => 'Todos los campos son requeridos'], 400);
    }
    
    $usuarioId = (int)$data['usuario_id'];
    $conn = getConnection();
    
    try {
        // Verificar contraseña actual
        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            jsonResponse(['error' => 'Usuario no encontrado'], 404);
        }
        
        if (!password_verify($data['password_actual'], $usuario['password'])) {
            jsonResponse(['error' => 'La contraseña actual es incorrecta'], 400);
        }
        
        // Actualizar contraseña
        $passwordHash = password_hash($data['password_nueva'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $usuarioId]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al cambiar contraseña'], 500);
    }
}

// ==========================================
// NUEVAS FUNCIONES: GESTIÓN DE EMPLEADOS
// ==========================================

function cambiarRol() {
    $data = getJsonInput();
    
    if (empty($data['usuario_id']) || empty($data['rol'])) {
        jsonResponse(['error' => 'ID de usuario y rol son requeridos'], 400);
    }
    
    $usuarioId = (int)$data['usuario_id'];
    $rol = $data['rol'];
    
    // Validar rol
    if (!in_array($rol, ['admin', 'empleado'])) {
        jsonResponse(['error' => 'Rol no válido'], 400);
    }
    
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        $stmt->execute([$rol, $usuarioId]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Usuario no encontrado'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Rol actualizado correctamente',
            'rol' => $rol
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al cambiar rol'], 500);
    }
}

function cambiarEstado() {
    $data = getJsonInput();
    
    if (empty($data['usuario_id']) || !isset($data['activo'])) {
        jsonResponse(['error' => 'ID de usuario y estado son requeridos'], 400);
    }
    
    $usuarioId = (int)$data['usuario_id'];
    $activo = (int)$data['activo'];
    
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
        $stmt->execute([$activo, $usuarioId]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Usuario no encontrado'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'message' => $activo ? 'Usuario activado' : 'Usuario desactivado',
            'activo' => $activo
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al cambiar estado'], 500);
    }
}

function eliminarUsuario() {
    $data = getJsonInput();
    
    if (empty($data['usuario_id'])) {
        jsonResponse(['error' => 'ID de usuario requerido'], 400);
    }
    
    $usuarioId = (int)$data['usuario_id'];
    $conn = getConnection();
    
    try {
        // Eliminar participaciones en ventas
        $stmt = $conn->prepare("DELETE FROM venta_participantes WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        
        // Eliminar usuario
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Usuario no encontrado'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Usuario eliminado correctamente'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al eliminar usuario: ' . $e->getMessage()], 500);
    }
}
?>
