<?php
// config.php - MediRecord - Configuración FINAL CORREGIDA para Railway y Local

// =============================================================================
// CONFIGURACIÓN INICIAL
// =============================================================================

// Verificar si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detectar entorno
define('IS_RAILWAY', getenv('RAILWAY_ENVIRONMENT') !== false);
define('IS_LOCAL', !IS_RAILWAY);

// Configuración de errores
if (IS_LOCAL) {
    // Desarrollo: mostrar errores
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Producción: ocultar errores
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// =============================================================================
// CONFIGURACIÓN DE BASE DE DATOS - CORREGIDA PARA RAILWAY
// =============================================================================

// Railway usa MYSQLHOST, MYSQLUSER, etc. NO DB_HOST, DB_USER
if (IS_RAILWAY && getenv('MYSQLHOST')) {
    // ✅ CONFIGURACIÓN RAILWAY (Producción)
    $host = getenv('MYSQLHOST');
    $port = getenv('MYSQLPORT') ?: '3306';
    $dbname = getenv('MYSQLDATABASE');
    $username = getenv('MYSQLUSER');
    $password = getenv('MYSQLPASSWORD');
    
    // Log para debugging
    error_log("✅ Config Railway: host=$host, db=$dbname, user=$username");
    
} else {
    // ✅ CONFIGURACIÓN LOCAL (Desarrollo)
    $host = 'localhost';
    $port = '3306';
    $dbname = 'medirecord_db';
    $username = 'root';
    $password = '';
    
    error_log("✅ Config Local: host=$host, db=$dbname");
}

// Conexión a la base de datos - VERSIÓN CORREGIDA
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // Opciones de PDO - VERSIÓN COMPATIBLE
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    // Solo agregar MYSQL_ATTR_INIT_COMMAND si está disponible
    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
    }
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Ejecutar SET NAMES manualmente si no se pudo en options
    if (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $pdo->exec("SET NAMES utf8mb4");
    }
    
} catch (PDOException $e) {
    // Manejo de errores amigable
    if (IS_RAILWAY) {
        $error_html = "
        <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 50px auto; border: 2px solid #e74c3c; border-radius: 10px; background: #fff5f5;'>
            <h2 style='color: #c0392b;'>🚨 ERROR DE CONEXIÓN A BASE DE DATOS</h2>
            
            <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <h3>🔍 Diagnóstico:</h3>
                <ul>
                    <li><strong>Entorno:</strong> " . (IS_RAILWAY ? 'Railway' : 'Local') . "</li>
                    <li><strong>Host intentado:</strong> $host</li>
                    <li><strong>Base de datos:</strong> $dbname</li>
                    <li><strong>Usuario:</strong> $username</li>
                    <li><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</li>
                </ul>
            </div>
            
            <div style='background: #e8f4fc; padding: 20px; border-radius: 5px;'>
                <h3>🔧 Solución para Railway:</h3>
                <ol>
                    <li>Ve a <strong>Railway Dashboard</strong> → tu proyecto</li>
                    <li>Haz clic en <strong>Variables</strong></li>
                    <li><strong>ELIMINA</strong> estas variables si existen:
                        <ul>
                            <li><code>DB_HOST</code></li>
                            <li><code>DB_NAME</code></li>
                            <li><code>DB_USER</code></li>
                            <li><code>DB_PASS</code></li>
                        </ul>
                    </li>
                    <li><strong>VERIFICA</strong> que tengas estas variables (Railway las crea automáticamente):
                        <ul>
                            <li><code>MYSQLHOST</code></li>
                            <li><code>MYSQLDATABASE</code></li>
                            <li><code>MYSQLUSER</code></li>
                            <li><code>MYSQLPASSWORD</code></li>
                        </ul>
                    </li>
                    <li>Si no tienes las variables MYSQL_*, añade una base de datos:
                        <ul>
                            <li>En Railway Dashboard, haz clic en <strong>New</strong></li>
                            <li>Selecciona <strong>Database</strong> → <strong>MySQL</strong></li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>
        ";
        die($error_html);
    } else {
        die("
        <div style='font-family: Arial; padding: 20px;'>
            <h2>Error de conexión local</h2>
            <p>Verifica que XAMPP/WAMP esté corriendo.</p>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>DSN:</strong> $dsn</p>
        </div>
        ");
    }
}

// =============================================================================
// CONFIGURACIÓN DE WHATSAPP
// =============================================================================
$whatsapp_config = [
    'api_url' => 'https://graph.facebook.com/v20.0/',
    'message_prefix' => 'MediRecord:',
    'enable_whatsapp' => true,
    'timezone' => 'America/Mexico_City',
    'recordatorio_minutos_antes' => 15,
    'max_intentos' => 3,
    'token' => getenv('WHATSAPP_TOKEN') ?: '',
    'phone_id' => getenv('WHATSAPP_PHONE_ID') ?: ''
];

// =============================================================================
// CONFIGURACIÓN DEL SITIO
// =============================================================================
$site_config = [
    'name' => 'MediRecord',
    'version' => '2.0',
    'description' => 'Sistema de recordatorio de medicamentos',
    'admin_email' => 'admin@medirecord.com',
    'url' => IS_RAILWAY ? ('https://' . (getenv('RAILWAY_STATIC_URL') ?: getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'tu-app.railway.app')) : 'http://localhost',
    'environment' => IS_RAILWAY ? 'production' : 'development'
];

// =============================================================================
// FUNCIONES DE AUTENTICACIÓN (TODAS TUS FUNCIONES ORIGINALES)
// =============================================================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

function getCurrentUser() {
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

function isPaciente() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'paciente';
}

function isCuidador() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'cuidador';
}

function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $alert_class = 'alert-' . $type;
        $icon = '';
        
        switch ($type) {
            case 'success': $icon = '✅'; break;
            case 'error': $icon = '❌'; break;
            case 'warning': $icon = '⚠️'; break;
            default: $icon = 'ℹ️';
        }
        
        echo "<div class='alert $alert_class'>$icon $message</div>";
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// =============================================================================
// FUNCIONES DE MEDICAMENTOS (TODAS TUS FUNCIONES ORIGINALES)
// =============================================================================

function getUserMedications($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'paciente';
    }
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT m.*, h.hora, h.frecuencia, h.activo, h.id_horario,
                   u.nombre as agregado_por_nombre 
            FROM medicamentos m 
            LEFT JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            LEFT JOIN usuarios u ON m.agregado_por = u.id_usuario
            WHERE m.id_usuario = ? 
            ORDER BY m.nombre_medicamento, h.hora
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.*, h.hora, h.frecuencia, h.activo, h.id_horario,
                   p.nombre as paciente_nombre, 
                   u.nombre as agregado_por_nombre
            FROM medicamentos m
            JOIN usuarios p ON m.id_usuario = p.id_usuario
            LEFT JOIN usuarios u ON m.agregado_por = u.id_usuario
            LEFT JOIN horarios h ON m.id_medicamento = h.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
            ORDER BY p.nombre, m.nombre_medicamento, h.hora
        ");
        $stmt->execute([$user_id]);
    }
    
    return $stmt->fetchAll();
}

function getNextMedication($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'paciente';
    }
    
    $current_time = date('H:i');
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT m.nombre_medicamento, m.dosis, h.hora, h.id_horario
            FROM medicamentos m 
            JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            WHERE m.id_usuario = ? AND h.hora >= ? AND h.activo = 1
            ORDER BY h.hora ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $current_time]);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.nombre_medicamento, m.dosis, h.hora, h.id_horario, p.nombre as paciente_nombre
            FROM medicamentos m 
            JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            JOIN usuarios p ON m.id_usuario = p.id_usuario
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE h.hora >= ? AND h.activo = 1 AND v.confirmado = 1
            ORDER BY h.hora ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $current_time]);
    }
    
    return $stmt->fetch();
}

function recordMedicationTaken($horario_id, $estado = 'tomado') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO historial_tomas (id_horario, estado) VALUES (?, ?)");
        $stmt->execute([$horario_id, $estado]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error registrando toma: " . $e->getMessage());
        return false;
    }
}

function getUserStats($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'paciente';
    }
    
    $stats = [
        'total_medicamentos' => 0,
        'total_tomas' => 0,
        'tomas_hoy' => 0,
        'tomas_pendientes' => 0
    ];
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM medicamentos WHERE id_usuario = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_medicamentos'] = $result['total'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? AND ht.estado = 'tomado'
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_tomas'] = $result['total'];
        
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? AND ht.estado = 'tomado' AND DATE(ht.fecha_hora_toma) = ?
        ");
        $stmt->execute([$user_id, $today]);
        $result = $stmt->fetch();
        $stats['tomas_hoy'] = $result['total'];
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id_medicamento) as total_medicamentos
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_medicamentos'] = $result['total_medicamentos'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE ht.estado = 'tomado' AND v.confirmado = 1
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_tomas'] = $result['total'];
        
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE ht.estado = 'tomado' AND DATE(ht.fecha_hora_toma) = ? AND v.confirmado = 1
        ");
        $stmt->execute([$user_id, $today]);
        $result = $stmt->fetch();
        $stats['tomas_hoy'] = $result['total'];
    }
    
    return $stats;
}

// =============================================================================
// FUNCIONES DE VINCULACIÓN (TODAS TUS FUNCIONES ORIGINALES)
// =============================================================================

function getPacientesVinculados($cuidador_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, v.confirmado
        FROM vinculaciones v
        JOIN usuarios u ON v.id_paciente = u.id_usuario
        WHERE v.id_cuidador = ? AND v.confirmado = 1
        ORDER BY u.nombre
    ");
    $stmt->execute([$cuidador_id]);
    return $stmt->fetchAll();
}

function cuidadorTieneAccesoPaciente($cuidador_id, $paciente_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id_vinculacion 
        FROM vinculaciones 
        WHERE id_cuidador = ? AND id_paciente = ? AND confirmado = 1
    ");
    $stmt->execute([$cuidador_id, $paciente_id]);
    return $stmt->fetch() !== false;
}

function verificarPermisoMedicamento($user_id, $medicamento_id) {
    global $pdo;
    $user_type = $_SESSION['user_type'];

    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("SELECT id_medicamento FROM medicamentos WHERE id_medicamento = ? AND id_usuario = ?");
        $stmt->execute([$medicamento_id, $user_id]);
        return $stmt->fetch() !== false;
    } else {
        $stmt = $pdo->prepare("
            SELECT m.id_medicamento 
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente
            WHERE m.id_medicamento = ? AND v.id_cuidador = ? AND v.confirmado = 1
        ");
        $stmt->execute([$medicamento_id, $user_id]);
        return $stmt->fetch() !== false;
    }
}

function obtenerMedicamentoPorId($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetch();
}

function obtenerHorariosMedicamento($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id_medicamento = ? ORDER BY hora");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetchAll();
}

// =============================================================================
// FUNCIONES DE WHATSAPP (TODAS TUS FUNCIONES ORIGINALES)
// =============================================================================

function enviarWhatsApp($telefono, $mensaje, $tipo = 'recordatorio', $id_horario = null, $id_usuario = null) {
    global $pdo, $whatsapp_config;
    
    if (!$whatsapp_config['enable_whatsapp'] || empty($telefono)) {
        return false;
    }
    
    try {
        $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
        
        if (!preg_match('/^\+/', $telefono_limpio) && strlen($telefono_limpio) == 10) {
            $telefono_limpio = '52' . $telefono_limpio;
        }
        
        $mensaje_completo = $whatsapp_config['message_prefix'] . " " . $mensaje;
        $token_confirmacion = bin2hex(random_bytes(16));
        
        $stmt = $pdo->prepare("
            INSERT INTO recordatorios_whatsapp 
            (id_horario, id_usuario, mensaje, estado, token_confirmacion) 
            VALUES (?, ?, ?, 'enviado', ?)
        ");
        $stmt->execute([$id_horario, $id_usuario, $mensaje_completo, $token_confirmacion]);
        $log_id = $pdo->lastInsertId();
        
        // Simulación de envío
        error_log("WhatsApp $tipo enviado a $telefono_limpio");
        
        return [
            'success' => true,
            'log_id' => $log_id,
            'token' => $token_confirmacion
        ];
        
    } catch (Exception $e) {
        error_log("Error enviando WhatsApp: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function notificarConfirmacionCuidador($paciente_id, $medicamento_info) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT c.nombre, c.telefono, c.id_usuario
        FROM vinculaciones v
        JOIN usuarios c ON v.id_cuidador = c.id_usuario
        WHERE v.id_paciente = ? AND v.confirmado = 1
    ");
    $stmt->execute([$paciente_id]);
    $cuidadores = $stmt->fetchAll();
    
    foreach ($cuidadores as $cuidador) {
        if (!empty($cuidador['telefono'])) {
            $mensaje = $medicamento_info['paciente_nombre'] . " ha confirmado la toma de " . 
                      $medicamento_info['nombre_medicamento'] . " - " . 
                      $medicamento_info['dosis'] . " a las " . date('H:i');
            
            enviarWhatsApp(
                $cuidador['telefono'], 
                $mensaje, 
                'confirmacion',
                null,
                $cuidador['id_usuario']
            );
        }
    }
}

// =============================================================================
// INICIALIZACIÓN
// =============================================================================

function inicializarDirectorios() {
    $directorios = ['logs', 'temp', 'uploads'];
    foreach ($directorios as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

inicializarDirectorios();
?>
