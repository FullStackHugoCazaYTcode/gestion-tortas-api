<?php
require_once 'config.php';
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'registro':
        if ($method === 'POST') registrarUsuario();
        break;
    case 'login':
        if ($method === 'POST') loginUsuario();
        break;
    case 'lista':
        listarUsuarios();
        break;
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}

function registrarUsuario() {
    $data = getJsonInput();
    
    if (empty($data['nombre']) || empty($data['email']) || empty($data['password'])) {
        jsonResponse(['error' => 'Nombre, email y contraseña son requeridos'], 400);
    }
    
    $nombre = trim($data['nombre']);
    $email = strtolower(trim($data['email']));
    $password = $data['password'];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Email no válido'], 400);
    }
    
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'La contraseña debe tener al menos 6 caracteres'], 400);
    }
    
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'El correo electrónico ya está registrado'], 400);
        }
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM usuarios");
        $stmt->execute();
        $count = $stmt->fetch();
        $rol = ($count['total'] == 0) ? 'admin' : 'empleado';
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, $passwordHash, $rol]);
        
        $userId = $conn->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'usuario' => ['id' => (int)$userId, 'nombre' => $nombre, 'email' => $email, 'rol' => $rol]
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
        $stmt = $conn->prepare("SELECT id, nombre, email, password, rol FROM usuarios WHERE email = ?");
        $stmt->execute([strtolower(trim($data['email']))]);
        $usuario = $stmt->fetch();
        
        if (!$usuario || !password_verify($data['password'], $usuario['password'])) {
            jsonResponse(['error' => 'Correo o contraseña incorrectos'], 401);
        }
        
        unset($usuario['password']);
        $usuario['id'] = (int)$usuario['id'];
        
        jsonResponse(['success' => true, 'usuario' => $usuario]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al iniciar sesión'], 500);
    }
}

function listarUsuarios() {
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("SELECT id, nombre, email, rol, created_at FROM usuarios ORDER BY id DESC");
        $stmt->execute();
        $usuarios = $stmt->fetchAll();
        
        foreach ($usuarios as &$u) $u['id'] = (int)$u['id'];
        
        jsonResponse(['success' => true, 'usuarios' => $usuarios]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Error al obtener usuarios'], 500);
    }
}
?>
