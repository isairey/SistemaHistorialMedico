<?php
include 'config.php';
requireAuth();

if (!isPaciente()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener cuidadores vinculados
$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.email, v.confirmado, v.id_vinculacion
    FROM vinculaciones v
    JOIN usuarios u ON v.id_cuidador = u.id_usuario
    WHERE v.id_paciente = ?
");
$stmt->execute([$user_id]);
$caregivers = $stmt->fetchAll();

// Procesar solicitud para agregar cuidador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_caregiver'])) {
    $email = $_POST['email'];

    // Verificar que el cuidador existe
    $stmt = $pdo->prepare("SELECT id_usuario, nombre FROM usuarios WHERE email = ? AND tipo = 'cuidador'");
    $stmt->execute([$email]);
    $caregiver = $stmt->fetch();

    if ($caregiver) {
        // Verificar que no esté ya vinculado
        $stmt = $pdo->prepare("SELECT id_vinculacion FROM vinculaciones WHERE id_paciente = ? AND id_cuidador = ?");
        $stmt->execute([$user_id, $caregiver['id_usuario']]);
        if ($stmt->fetch()) {
            $error = 'Este cuidador ya está vinculado.';
        } else {
            // Crear vinculación (inicialmente no confirmada)
            $stmt = $pdo->prepare("INSERT INTO vinculaciones (id_paciente, id_cuidador, confirmado) VALUES (?, ?, 0)");
            $stmt->execute([$user_id, $caregiver['id_usuario']]);
            $success = 'Solicitud enviada al cuidador. Esperando confirmación.';
            // Recargar la lista
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.nombre, u.email, v.confirmado, v.id_vinculacion
                FROM vinculaciones v
                JOIN usuarios u ON v.id_cuidador = u.id_usuario
                WHERE v.id_paciente = ?
            ");
            $stmt->execute([$user_id]);
            $caregivers = $stmt->fetchAll();
        }
    } else {
        $error = 'No se encontró un cuidador con ese email.';
    }
}

// Procesar eliminación de vinculación
if (isset($_GET['delete'])) {
    $id_vinculacion = $_GET['delete'];
    // Verificar que la vinculación pertenece al paciente
    $stmt = $pdo->prepare("DELETE FROM vinculaciones WHERE id_vinculacion = ? AND id_paciente = ?");
    $stmt->execute([$id_vinculacion, $user_id]);
    $success = 'Vinculación eliminada.';
    // Recargar la lista
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, v.confirmado, v.id_vinculacion
        FROM vinculaciones v
        JOIN usuarios u ON v.id_cuidador = u.id_usuario
        WHERE v.id_paciente = ?
    ");
    $stmt->execute([$user_id]);
    $caregivers = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Mis Cuidadores</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>MediRecord</h1>
            <div class="user-info">
                <a href="dashboard.php">Inicio</a> | 
                <a href="profile.php">Perfil</a> | 
                <a href="logout.php">Cerrar Sesión</a>
            </div>
        </header>

        <div class="content">
            <h2>Mis Cuidadores</h2>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="caregiver-section">
                <h3>Agregar Cuidador</h3>
                <form method="POST" action="">
                    <div class="input-group">
                        <label for="email">Email del cuidador:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <button type="submit" name="add_caregiver" class="btn">Enviar Solicitud</button>
                </form>
            </div>

            <div class="caregiver-list">
                <h3>Cuidadores Vinculados</h3>
                <?php if (empty($caregivers)): ?>
                    <p>No tienes cuidadores vinculados.</p>
                <?php else: ?>
                    <div class="caregiver-cards">
                        <?php foreach ($caregivers as $caregiver): ?>
                        <div class="caregiver-card">
                            <div class="caregiver-info">
                                <h4><?php echo htmlspecialchars($caregiver['nombre']); ?></h4>
                                <p><?php echo htmlspecialchars($caregiver['email']); ?></p>
                                <p>Estado: 
                                    <?php if ($caregiver['confirmado']): ?>
                                        <span class="confirmed">✅ Confirmado</span>
                                    <?php else: ?>
                                        <span class="pending">⏳ Pendiente</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="caregiver-actions">
                                <a href="?delete=<?php echo $caregiver['id_vinculacion']; ?>" class="btn-small btn-danger" onclick="return confirm('¿Estás seguro de eliminar este cuidador?')">Eliminar</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>