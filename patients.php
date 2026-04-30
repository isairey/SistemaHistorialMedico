<?php
include 'config.php';
requireAuth();

if (!isCuidador()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener pacientes vinculados
$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.email, v.confirmado, v.id_vinculacion
    FROM vinculaciones v
    JOIN usuarios u ON v.id_paciente = u.id_usuario
    WHERE v.id_cuidador = ?
");
$stmt->execute([$user_id]);
$patients = $stmt->fetchAll();

// Procesar confirmación de vinculación
if (isset($_GET['confirm'])) {
    $id_vinculacion = $_GET['confirm'];
    // Verificar que la vinculación pertenece al cuidador
    $stmt = $pdo->prepare("UPDATE vinculaciones SET confirmado = 1 WHERE id_vinculacion = ? AND id_cuidador = ?");
    $stmt->execute([$id_vinculacion, $user_id]);
    $success = 'Vinculación confirmada.';
    // Recargar la lista
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, v.confirmado, v.id_vinculacion
        FROM vinculaciones v
        JOIN usuarios u ON v.id_paciente = u.id_usuario
        WHERE v.id_cuidador = ?
    ");
    $stmt->execute([$user_id]);
    $patients = $stmt->fetchAll();
}

// Procesar eliminación de vinculación
if (isset($_GET['delete'])) {
    $id_vinculacion = $_GET['delete'];
    // Verificar que la vinculación pertenece al cuidador
    $stmt = $pdo->prepare("DELETE FROM vinculaciones WHERE id_vinculacion = ? AND id_cuidador = ?");
    $stmt->execute([$id_vinculacion, $user_id]);
    $success = 'Vinculación eliminada.';
    // Recargar la lista
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, v.confirmado, v.id_vinculacion
        FROM vinculaciones v
        JOIN usuarios u ON v.id_paciente = u.id_usuario
        WHERE v.id_cuidador = ?
    ");
    $stmt->execute([$user_id]);
    $patients = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Mis Pacientes</title>
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
            <h2>Mis Pacientes</h2>

            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="patients-list">
                <?php if (empty($patients)): ?>
                    <p>No tienes pacientes vinculados.</p>
                <?php else: ?>
                    <div class="patient-cards">
                        <?php foreach ($patients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-info">
                                <h4><?php echo htmlspecialchars($patient['nombre']); ?></h4>
                                <p><?php echo htmlspecialchars($patient['email']); ?></p>
                                <p>Estado: 
                                    <?php if ($patient['confirmado']): ?>
                                        <span class="confirmed">✅ Confirmado</span>
                                    <?php else: ?>
                                        <span class="pending">⏳ Pendiente</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="patient-actions">
                                <?php if (!$patient['confirmado']): ?>
                                    <a href="?confirm=<?php echo $patient['id_vinculacion']; ?>" class="btn-small">Confirmar</a>
                                <?php endif; ?>
                                <a href="?delete=<?php echo $patient['id_vinculacion']; ?>" class="btn-small btn-danger" onclick="return confirm('¿Estás seguro de eliminar este paciente?')">Eliminar</a>
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