<?php
// setup_database.php - VERSIÓN COMPATIBLE
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Database - MediRecord</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; }
        .success { color: green; padding: 10px; background: #e8f5e8; border: 1px solid green; }
        .error { color: red; padding: 10px; background: #ffe8e8; border: 1px solid red; }
        .warning { color: orange; padding: 10px; background: #fff8e8; border: 1px solid orange; }
        .sql { background: #f5f5f5; padding: 10px; border-left: 4px solid #ccc; font-family: monospace; }
    </style>
</head>
<body>";

echo "<h1>🧰 Configuración de Base de Datos MediRecord</h1>";

// Verificar conexión
try {
    $pdo->query("SELECT 1");
    echo "<div class='success'>✅ Conexión a Railway MySQL establecida</div>";
} catch (Exception $e) {
    die("<div class='error'>
        <h2>❌ Error de conexión</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Verifica las variables MYSQL_* en Railway Dashboard → Variables</p>
    </div>");
}

// SQL simplificado para compatibilidad
$sql_commands = [
    // Tabla usuarios
    "CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        tipo ENUM('paciente','cuidador') NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        telefono VARCHAR(20),
        whatsapp_token VARCHAR(100),
        telefono_verificado BOOLEAN DEFAULT 0
    );",
    
    // Tabla medicamentos
    "CREATE TABLE IF NOT EXISTS medicamentos (
        id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nombre_medicamento VARCHAR(100) NOT NULL,
        dosis VARCHAR(50) NOT NULL,
        instrucciones TEXT,
        agregado_por INT,
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
        FOREIGN KEY (agregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
    );",
    
    // Tabla horarios
    "CREATE TABLE IF NOT EXISTS horarios (
        id_horario INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento INT NOT NULL,
        hora TIME NOT NULL,
        frecuencia ENUM('diario','lunes-viernes','personalizado') DEFAULT 'diario',
        activo BOOLEAN DEFAULT 1,
        ultimo_recordatorio DATETIME,
        ultima_alerta DATETIME,
        FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento) ON DELETE CASCADE
    );",
    
    // Tabla historial_tomas
    "CREATE TABLE IF NOT EXISTS historial_tomas (
        id_registro INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        fecha_hora_toma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('tomado','omitido','pospuesto') NOT NULL,
        FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE
    );",
    
    // Tabla vinculaciones
    "CREATE TABLE IF NOT EXISTS vinculaciones (
        id_vinculacion INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_cuidador INT NOT NULL,
        confirmado BOOLEAN DEFAULT 0,
        fecha_vinculacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
        FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    );",
    
    // Tabla recordatorios_whatsapp
    "CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
        id_recordatorio INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('enviado','entregado','leido','confirmado') DEFAULT 'enviado',
        token_confirmacion VARCHAR(100) NOT NULL,
        FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    );"
];

echo "<h2>📊 Creando tablas...</h2>";

foreach ($sql_commands as $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✅ Tabla creada exitosamente</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Datos de prueba simples
try {
    // Usuario admin
    $hashed_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO usuarios (nombre, email, password, tipo) VALUES 
               ('Admin Demo', 'admin@medirecord.com', '$hashed_pass', 'paciente')");
    
    echo "<div class='success'>✅ Usuario de prueba creado</div>";
    
} catch (Exception $e) {
    echo "<div class='warning'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>
    <h2>🎉 ¡CONFIGURACIÓN COMPLETADA!</h2>
    <p>Base de datos creada en Railway MySQL.</p>
    <p><strong>Credenciales:</strong> admin@medirecord.com / admin123</p>
    <p><a href='index.php' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Ir al sistema</a></p>
</div>";

echo "</body></html>";
?>
