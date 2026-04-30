<?php
include 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Procesar actualización del perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    
    try {
        // Verificar si el email ya existe en otro usuario
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->fetch()) {
            $error = 'El email ya está en uso por otro usuario';
        } else {
            // Actualizar perfil con teléfono
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ? WHERE id_usuario = ?");
            $stmt->execute([$name, $email, $telefono, $user_id]);
            
            // Actualizar sesión
            $_SESSION['user_name'] = $name;
            $success = 'Perfil actualizado correctamente';
            
            // Recargar datos del usuario
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    } catch (PDOException $e) {
        $error = 'Error al actualizar perfil: ' . $e->getMessage();
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verificar contraseña actual (en producción usar password_verify())
    if ($current_password !== 'password123') { // Cambiar por verificación real
        $error = 'La contraseña actual es incorrecta';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las nuevas contraseñas no coinciden';
    } elseif (strlen($new_password) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres';
    } else {
        try {
            // Actualizar contraseña (en producción usar password_hash())
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
            $stmt->execute([$new_password, $user_id]);
            $success = 'Contraseña cambiada correctamente';
        } catch (PDOException $e) {
            $error = 'Error al cambiar contraseña: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Mi Perfil</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>MediRecord</h1>
            <div class="user-info">
                <a href="dashboard.php">Inicio</a> | 
                <a href="medications.php">Medicamentos</a> | 
                <a href="logout.php">Cerrar Sesión</a>
            </div>
        </header>
        
        <div class="content">
            <h2>Mi Perfil</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="profile-sections">
                <!-- Sección de información del perfil -->
                <div class="profile-section">
                    <h3>Información Personal</h3>
                    <form method="POST" action="">
                        <div class="input-group">
                            <label for="name">Nombre completo:</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="email">Correo electrónico:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="input-group">
                            <label for="telefono">Teléfono para WhatsApp:</label>
                            <input type="tel" id="telefono" name="telefono" 
                                   value="<?php echo htmlspecialchars($user['telefono'] ?? ''); ?>"
                                   placeholder="+521234567890">
                            <small>Incluye código de país. Ej: +521234567890</small>
                        </div>
                        
                        <div class="input-group">
                            <label>Tipo de usuario:</label>
                            <input type="text" value="<?php echo ucfirst($user['tipo']); ?>" disabled>
                            <small>El tipo de usuario no se puede cambiar</small>
                        </div>
                        
                        <div class="input-group">
                            <label>Fecha de registro:</label>
                            <input type="text" value="<?php echo date('d/m/Y H:i', strtotime($user['fecha_registro'])); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn">Actualizar Perfil</button>
                    </form>
                </div>
                
                <!-- Sección de cambio de contraseña -->
                <div class="profile-section">
                    <h3>Cambiar Contraseña</h3>
                    <form method="POST" action="">
                        <div class="input-group">
                            <label for="current_password">Contraseña actual:</label>
                            <input type="password" id="current_password" name="current_password" required>
                            <small>Para testing usa: password123</small>
                        </div>
                        
                        <div class="input-group">
                            <label for="new_password">Nueva contraseña:</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="confirm_password">Confirmar nueva contraseña:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn">Cambiar Contraseña</button>
                    </form>
                </div>
                
                <!-- Sección de estadísticas (opcional) -->
                <div class="profile-section">
                    <h3>Mis Estadísticas</h3>
                    <?php
                    // Obtener estadísticas del usuario
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as total_medicamentos 
                        FROM medicamentos 
                        WHERE id_usuario = ?
                    ");
                    $stmt->execute([$user_id]);
                    $stats = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as total_tomas 
                        FROM historial_tomas ht
                        JOIN horarios h ON ht.id_horario = h.id_horario
                        JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
                        WHERE m.id_usuario = ? AND ht.estado = 'tomado'
                    ");
                    $stmt->execute([$user_id]);
                    $tomas_stats = $stmt->fetch();
                    ?>
                    
                    <div class="stats">
                        <div class="stat-item">
                            <span class="stat-label">Medicamentos registrados:</span>
                            <span class="stat-value"><?php echo $stats['total_medicamentos']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Tomas registradas:</span>
                            <span class="stat-value"><?php echo $tomas_stats['total_tomas']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>