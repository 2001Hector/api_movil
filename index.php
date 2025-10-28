<?php
// index.php - API CRUD para Catálogo de Ramos y Pedidos
require_once 'db.php';

// CORS COMPLETO para Expo/React Native
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function
function sendResponse($success, $data = null, $error = null, $code = null) {
    http_response_code($code ?: ($success ? 200 : 500));
    echo json_encode([
        'ok' => $success,
        'data' => $data,
        'error' => $error
    ]);
    exit;
}

// Obtener método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Obtener datos del body para POST/PUT
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = [];
}

// También obtener datos de $_POST para compatibilidad
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

try {
    $pdo = Database::getInstance();
    
    // Obtener la ruta solicitada
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    
    // Remover /api/ si existe
    $path = str_replace('/api', '', $path);
    
    // Health check
    if ($path == '/health' || $path == '/') {
        $stmt = $pdo->query("SELECT 1 AS ok");
        sendResponse(true, ['status' => 'API funcionando', 'timestamp' => date('Y-m-d H:i:s')]);
    }
    
    // ========== CRUD PARA RAMOS ==========
    
    // GET todos los ramos
    if ($path == '/ramos' && $method == 'GET') {
        $stmt = $pdo->query("SELECT * FROM catalogo_ramos ORDER BY id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse(true, $rows);
    }
    
    // GET ramo por ID
    if (preg_match('/^\/ramos\/(\d+)$/', $path, $matches) && $method == 'GET') {
        $id = $matches[1];
        $stmt = $pdo->prepare("SELECT * FROM catalogo_ramos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            sendResponse(true, $row);
        } else {
            sendResponse(false, null, "Ramo no encontrado", 404);
        }
    }
    
    // POST - Crear nuevo ramo
    if ($path == '/ramos' && $method == 'POST') {
        error_log("📥 POST /ramos recibido: " . json_encode($input));
        
        $required = ['titulo', 'valor', 'categoria'];
        $missing = array_diff($required, array_keys($input));
        
        if (!empty($missing)) {
            sendResponse(false, null, "Faltan campos requeridos: " . implode(', ', $missing), 400);
        }
        
        $sql = "INSERT INTO catalogo_ramos (titulo, valor, categoria, description, imagen) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $titulo = trim($input['titulo']);
        $valor = floatval($input['valor']);
        $categoria = trim($input['categoria']);
        $description = isset($input['description']) ? trim($input['description']) : '';
        $imagen = isset($input['imagen']) ? trim($input['imagen']) : '';
        
        try {
            $stmt->execute([
                $titulo,
                $valor,
                $categoria,
                $description,
                $imagen
            ]);
            
            $nuevoId = $pdo->lastInsertId();
            error_log("✅ Ramo creado exitosamente - ID: $nuevoId");
            sendResponse(true, ['id' => $nuevoId, 'message' => 'Ramo creado exitosamente']);
            
        } catch (PDOException $e) {
            error_log("❌ Error al crear ramo: " . $e->getMessage());
            sendResponse(false, null, "Error al crear ramo: " . $e->getMessage(), 500);
        }
    }
    
    // PUT - Actualizar ramo
    if (preg_match('/^\/ramos\/(\d+)$/', $path, $matches) && $method == 'PUT') {
        $id = $matches[1];
        error_log("📥 PUT /ramos/$id recibido: " . json_encode($input));
        
        // Verificar si existe
        $checkStmt = $pdo->prepare("SELECT id FROM catalogo_ramos WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if (!$checkStmt->fetch()) {
            sendResponse(false, null, "Ramo no encontrado", 404);
        }
        
        $allowedFields = ['titulo', 'valor', 'categoria', 'description', 'imagen'];
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                if ($field === 'valor') {
                    $params[] = floatval($input[$field]);
                } else {
                    $params[] = trim($input[$field]);
                }
            }
        }
        
        if (empty($updateFields)) {
            sendResponse(false, null, "No hay campos para actualizar", 400);
        }
        
        $params[] = $id;
        $sql = "UPDATE catalogo_ramos SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute($params);
            error_log("✅ Ramo actualizado exitosamente - ID: $id");
            sendResponse(true, ['message' => 'Ramo actualizado exitosamente']);
            
        } catch (PDOException $e) {
            error_log("❌ Error al actualizar ramo: " . $e->getMessage());
            sendResponse(false, null, "Error al actualizar ramo: " . $e->getMessage(), 500);
        }
    }
    
    // DELETE - Eliminar ramo
    if (preg_match('/^\/ramos\/(\d+)$/', $path, $matches) && $method == 'DELETE') {
        $id = $matches[1];
        
        // Verificar si existe
        $checkStmt = $pdo->prepare("SELECT id FROM catalogo_ramos WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if (!$checkStmt->fetch()) {
            sendResponse(false, null, "Ramo no encontrado", 404);
        }
        
        $stmt = $pdo->prepare("DELETE FROM catalogo_ramos WHERE id = ?");
        
        try {
            $stmt->execute([$id]);
            sendResponse(true, ['message' => 'Ramo eliminado exitosamente']);
            
        } catch (PDOException $e) {
            sendResponse(false, null, "Error al eliminar ramo: " . $e->getMessage(), 500);
        }
    }
    
    // ========== CRUD PARA PEDIDOS ==========
    
    // GET todos los pedidos
    if ($path == '/pedidos' && $method == 'GET') {
        $stmt = $pdo->query("SELECT * FROM pedido ORDER BY id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse(true, $rows);
    }
    
    // GET pedido por ID
    if (preg_match('/^\/pedidos\/(\d+)$/', $path, $matches) && $method == 'GET') {
        $id = $matches[1];
        $stmt = $pdo->prepare("SELECT * FROM pedido WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            sendResponse(true, $row);
        } else {
            sendResponse(false, null, "Pedido no encontrado", 404);
        }
    }
    
    // POST - Crear nuevo pedido
    if ($path == '/pedidos' && $method == 'POST') {
        error_log("📥 POST /pedidos recibido: " . json_encode($input));
        
        $required = ['nombre_cliente', 'direccion', 'fecha_entrega', 'valor_ramo'];
        $missing = array_diff($required, array_keys($input));
        
        if (!empty($missing)) {
            sendResponse(false, null, "Faltan campos requeridos: " . implode(', ', $missing), 400);
        }
        
        $sql = "INSERT INTO pedido (nombre_cliente, direccion, fecha_entrega, valor_ramo, nombre_ramo, celular, descripcion, estado, cantidad_pagada) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute([
                trim($input['nombre_cliente']),
                trim($input['direccion']),
                trim($input['fecha_entrega']),
                floatval($input['valor_ramo']),
                isset($input['nombre_ramo']) ? trim($input['nombre_ramo']) : '',
                isset($input['celular']) ? trim($input['celular']) : '',
                isset($input['descripcion']) ? trim($input['descripcion']) : '',
                isset($input['estado']) ? trim($input['estado']) : 'En proceso',
                isset($input['cantidad_pagada']) ? floatval($input['cantidad_pagada']) : 0.00
            ]);
            
            $nuevoId = $pdo->lastInsertId();
            error_log("✅ Pedido creado exitosamente - ID: $nuevoId");
            sendResponse(true, ['id' => $nuevoId, 'message' => 'Pedido creado exitosamente']);
            
        } catch (PDOException $e) {
            error_log("❌ Error al crear pedido: " . $e->getMessage());
            sendResponse(false, null, "Error al crear pedido: " . $e->getMessage(), 500);
        }
    }
    
    // PUT - Actualizar pedido
    if (preg_match('/^\/pedidos\/(\d+)$/', $path, $matches) && $method == 'PUT') {
        $id = $matches[1];
        error_log("📥 PUT /pedidos/$id recibido: " . json_encode($input));
        
        // Verificar si existe
        $checkStmt = $pdo->prepare("SELECT id FROM pedido WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if (!$checkStmt->fetch()) {
            sendResponse(false, null, "Pedido no encontrado", 404);
        }
        
        $allowedFields = ['nombre_cliente', 'direccion', 'fecha_entrega', 'valor_ramo', 'nombre_ramo', 'celular', 'descripcion', 'estado', 'cantidad_pagada'];
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                if ($field === 'valor_ramo' || $field === 'cantidad_pagada') {
                    $params[] = floatval($input[$field]);
                } else {
                    $params[] = trim($input[$field]);
                }
            }
        }
        
        if (empty($updateFields)) {
            sendResponse(false, null, "No hay campos para actualizar", 400);
        }
        
        $params[] = $id;
        $sql = "UPDATE pedido SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute($params);
            error_log("✅ Pedido actualizado exitosamente - ID: $id");
            sendResponse(true, ['message' => 'Pedido actualizado exitosamente']);
            
        } catch (PDOException $e) {
            error_log("❌ Error al actualizar pedido: " . $e->getMessage());
            sendResponse(false, null, "Error al actualizar pedido: " . $e->getMessage(), 500);
        }
    }
    
    // DELETE - Eliminar pedido
    if (preg_match('/^\/pedidos\/(\d+)$/', $path, $matches) && $method == 'DELETE') {
        $id = $matches[1];
        
        // Verificar si existe
        $checkStmt = $pdo->prepare("SELECT id FROM pedido WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if (!$checkStmt->fetch()) {
            sendResponse(false, null, "Pedido no encontrado", 404);
        }
        
        $stmt = $pdo->prepare("DELETE FROM pedido WHERE id = ?");
        
        try {
            $stmt->execute([$id]);
            sendResponse(true, ['message' => 'Pedido eliminado exitosamente']);
            
        } catch (PDOException $e) {
            sendResponse(false, null, "Error al eliminar pedido: " . $e->getMessage(), 500);
        }
    }
    
    // Si no coincide ninguna ruta
    sendResponse(false, null, "Ruta no encontrada: $path", 404);
    
} catch (PDOException $e) {
    error_log("❌ Error de base de datos: " . $e->getMessage());
    sendResponse(false, null, "Error de base de datos: " . $e->getMessage());
} catch (Exception $e) {
    error_log("❌ Error interno: " . $e->getMessage());
    sendResponse(false, null, "Error interno: " . $e->getMessage());
}
?>