<?php
include 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];

// Obtener historial de tomas
$stmt = $pdo->prepare("
    SELECT ht.fecha_hora_toma, ht.estado, m.nombre_medicamento, m.dosis, h.hora
    FROM historial_tomas ht
    JOIN horarios h ON ht.id_horario = h.id_horario
    JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
    WHERE m.id_usuario = ?
    ORDER BY ht.fecha_hora_toma DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$historial = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Historial de Tomas</title>
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
            <h2>Historial de Tomas</h2>

            <?php if (empty($historial)): ?>
                <p>No hay registros en el historial.</p>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Medicamento</th>
                            <th>Dosis</th>
                            <th>Hora Programada</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $registro): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($registro['fecha_hora_toma'])); ?></td>
                            <td><?php echo htmlspecialchars($registro['nombre_medicamento']); ?></td>
                            <td><?php echo htmlspecialchars($registro['dosis']); ?></td>
                            <td><?php echo date('h:i A', strtotime($registro['hora'])); ?></td>
                            <td>
                                <span class="estado <?php echo $registro['estado']; ?>">
                                    <?php 
                                    $estados = [
                                        'tomado' => '✅ Tomado',
                                        'omitido' => '❌ Omitido',
                                        'pospuesto' => '⏱ Pospuesto'
                                    ];
                                    echo $estados[$registro['estado']]; 
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>




