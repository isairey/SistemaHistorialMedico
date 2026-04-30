<?php
// Configuración para Railway
if (getenv('RAILWAY_ENVIRONMENT')) {
    $port = getenv('PORT') ?: 3000;
    
    // Si es el archivo principal, iniciar servidor
    if (php_sapi_name() === 'cli-server') {
        // Ya estamos en el servidor CLI de PHP
    }
}
// ... resto del código

session_start();
include 'config.php';

$error = '';

// Procesar inicio de sesión
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Verificar contraseña (en producción usar password_verify())
        if ($password === 'password123') { // Contraseña simple para testing
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_type'] = $user['tipo'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Contraseña incorrecta';
        }
    } else {
        $error = 'Usuario no encontrado';
    }
}

// Procesar registro
if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['new_email'];
    $password = $_POST['new_password'];
    $user_type = $_POST['user_type'];
    
    try {
        // Verificar si el email ya existe
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'El email ya está registrado';
        } else {
            // Insertar nuevo usuario (en producción hashear la contraseña)
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, tipo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $user_type]);
            
            // Iniciar sesión automáticamente después del registro
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_type'] = $user_type;
            header('Location: dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = 'Error al registrar usuario: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Iniciar Sesión</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h1>MediRecord</h1>
            <p class="tagline">Recordatorio de Medicamentos para Adultos Mayores</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group">
                    <label for="email">Correo electrónico:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                </div>
                
                <div class="input-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Ingresa tu contraseña">
                </div>
                
                <button type="submit" name="login" class="btn">Iniciar Sesión</button>
            </form>
            
            <p class="register-link">¿No tienes cuenta? <a href="#" onclick="showRegister()">Regístrate aquí</a></p>
            
            <div id="register-form" style="display: none;">
                <h2>Registro</h2>
                <form method="POST" action="">
                    <div class="input-group">
                        <label for="name">Nombre completo:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="new_email">Correo electrónico:</label>
                        <input type="email" id="new_email" name="new_email" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="new_password">Contraseña:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="user_type">Tipo de usuario:</label>
                        <select id="user_type" name="user_type">
                            <option value="paciente">Paciente</option>
                            <option value="cuidador">Cuidador</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="register" class="btn">Registrarse</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showRegister() {
            document.getElementById('register-form').style.display = 'block';
        }
        
        // Mostrar formulario de registro si hay error en registro
        <?php if (isset($_POST['register'])): ?>
            document.getElementById('register-form').style.display = 'block';
        <?php endif; ?>
    </script>
</body>

</html>  
