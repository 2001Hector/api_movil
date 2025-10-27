<?php
// index.php - API para Catálogo de Ramos - CON CORS COMPLETO
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
function sendResponse($success, $data = null, $error = null) {
    http_response_code($success ? 200 : 500);
    echo json_encode([
        'ok' => $success,
        'data' => $data,
        'error' => $error
    ]);
    exit;
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
        sendResponse(true, $stmt->fetch());
    }
    
    // GET todos los ramos
    if ($path == '/ramos') {
        $stmt = $pdo->query("SELECT * FROM catalogo_ramos ORDER BY id DESC");
        $rows = $stmt->fetchAll();
        sendResponse(true, $rows);
    }
    
    // GET ramo por ID
    if (preg_match('/^\/ramos\/(\d+)$/', $path, $matches)) {
        $id = $matches[1];
        $stmt = $pdo->prepare("SELECT * FROM catalogo_ramos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        if ($row) {
            sendResponse(true, $row);
        } else {
            sendResponse(false, null, "Ramo no encontrado");
        }
    }
    
    // Si no coincide ninguna ruta
    sendResponse(false, null, "Ruta no encontrada: $path");
    
} catch (PDOException $e) {
    sendResponse(false, null, "Error de base de datos: " . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, null, "Error interno: " . $e->getMessage());
}
?>