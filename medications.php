<?php
include 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_type = $_SESSION['user_type'];

// Obtener medicamentos usando la función unificada
$medications = getUserMedications($user_id, $user_type);

// Agrupar por medicamento para mostrar mejor
$meds_grouped = [];
foreach ($medications as $med) {
    $med_id = $med['id_medicamento'];
    if (!isset($meds_grouped[$med_id])) {
        $meds_grouped[$med_id] = [
            'id_medicamento' => $med['id_medicamento'],
            'nombre_medicamento' => $med['nombre_medicamento'],
            'dosis' => $med['dosis'],
            'instrucciones' => $med['instrucciones'],
            'agregado_por_nombre' => $med['agregado_por_nombre'],
            'paciente_nombre' => $med['paciente_nombre'] ?? null, // Solo para cuidadores
            'horarios' => []
        ];
    }
    if ($med['hora']) {
        $meds_grouped[$med_id]['horarios'][] = [
            'id_horario' => $med['id_horario'],
            'hora' => $med['hora'],
            'frecuencia' => $med['frecuencia'],
            'activo' => $med['activo']
        ];
    }
}

// Procesar eliminación de medicamento
if (isset($_GET['delete_med'])) {
    $med_id = $_GET['delete_med'];
    
    // Verificar permisos según el tipo de usuario
    if ($user_type === 'paciente') {
        // Paciente solo puede eliminar sus propios medicamentos
        $stmt = $pdo->prepare("SELECT id_usuario FROM medicamentos WHERE id_medicamento = ?");
        $stmt->execute([$med_id]);
        $med_owner = $stmt->fetch();
        
        if ($med_owner && $med_owner['id_usuario'] == $user_id) {
            try {
                // Eliminar horarios primero (por la foreign key)
                $stmt = $pdo->prepare("DELETE FROM horarios WHERE id_medicamento = ?");
                $stmt->execute([$med_id]);
                
                // Eliminar medicamento
                $stmt = $pdo->prepare("DELETE FROM medicamentos WHERE id_medicamento = ?");
                $stmt->execute([$med_id]);
                
                redirectWithMessage('medications.php', 'success', 'Medicamento eliminado correctamente');
            } catch (PDOException $e) {
                redirectWithMessage('medications.php', 'error', 'Error al eliminar el medicamento');
            }
        } else {
            redirectWithMessage('medications.php', 'error', 'No tienes permisos para eliminar este medicamento');
        }
    } else {
        // Cuidador puede eliminar medicamentos de sus pacientes
        $stmt = $pdo->prepare("
            SELECT m.id_medicamento 
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE m.id_medicamento = ? AND v.confirmado = 1
        ");
        $stmt->execute([$user_id, $med_id]);
        
        if ($stmt->fetch()) {
            try {
                // Eliminar horarios primero
                $stmt = $pdo->prepare("DELETE FROM horarios WHERE id_medicamento = ?");
                $stmt->execute([$med_id]);
                
                // Eliminar medicamento
                $stmt = $pdo->prepare("DELETE FROM medicamentos WHERE id_medicamento = ?");
                $stmt->execute([$med_id]);
                
                redirectWithMessage('medications.php', 'success', 'Medicamento eliminado correctamente');
            } catch (PDOException $e) {
                redirectWithMessage('medications.php', 'error', 'Error al eliminar el medicamento');
            }
        } else {
            redirectWithMessage('medications.php', 'error', 'No tienes permisos para eliminar este medicamento');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Mis Medicamentos</title>
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
            <h2>
                <?php 
                if ($user_type === 'cuidador') {
                    echo 'Medicamentos de Mis Pacientes';
                } else {
                    echo 'Mis Medicamentos';
                }
                ?>
            </h2>

            <?php displayFlashMessage(); ?>

            <div class="action-buttons" style="margin-bottom: 20px;">
                <a href="add_medication.php" class="btn">➕ Añadir Nuevo Medicamento</a>
            </div>

            <?php if (empty($meds_grouped)): ?>
                <div class="no-data">
                    <p>
                        <?php 
                        if ($user_type === 'cuidador') {
                            echo 'No hay medicamentos registrados para tus pacientes.';
                        } else {
                            echo 'No tienes medicamentos registrados.';
                        }
                        ?>
                    </p>
                    <a href="add_medication.php" class="btn">
                        <?php 
                        if ($user_type === 'cuidador') {
                            echo 'Agregar medicamento a un paciente';
                        } else {
                            echo 'Agregar tu primer medicamento';
                        }
                        ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="medications-grid">
                    <?php foreach ($meds_grouped as $med): ?>
                    <div class="medication-card">
                        <div class="med-header">
                            <div class="med-title-section">
                                <h3><?php echo htmlspecialchars($med['nombre_medicamento']); ?></h3>
                                
                                <!-- Información del paciente (solo para cuidadores) -->
                                <?php if ($user_type === 'cuidador' && isset($med['paciente_nombre'])): ?>
                                <div class="patient-info">
                                    <strong>Paciente:</strong> <?php echo htmlspecialchars($med['paciente_nombre']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Información de quién agregó el medicamento -->
                                <?php if ($med['agregado_por_nombre']): ?>
                                <div class="added-by-info">
                                    <small>Agregado por: <?php echo htmlspecialchars($med['agregado_por_nombre']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="med-actions">
                                <a href="edit_medication.php?id=<?php echo $med['id_medicamento']; ?>" class="btn-small">✏️ Editar</a>
                                <a href="?delete_med=<?php echo $med['id_medicamento']; ?>" class="btn-small btn-danger" onclick="return confirm('¿Estás seguro de eliminar este medicamento?')">🗑️ Eliminar</a>
                            </div>
                        </div>
                        
                        <div class="med-body">
                            <p><strong>Dosis:</strong> <?php echo htmlspecialchars($med['dosis']); ?></p>
                            
                            <?php if ($med['instrucciones']): ?>
                                <p><strong>Instrucciones:</strong> <?php echo htmlspecialchars($med['instrucciones']); ?></p>
                            <?php endif; ?>
                            
                            <div class="horarios-section">
                                <h4>Horarios:</h4>
                                <?php if (empty($med['horarios'])): ?>
                                    <p class="no-horarios">No hay horarios configurados</p>
                                <?php else: ?>
                                    <ul class="horarios-list">
                                        <?php foreach ($med['horarios'] as $horario): ?>
                                            <li>
                                                <span class="hora">🕐 <?php echo date('h:i A', strtotime($horario['hora'])); ?></span>
                                                <span class="frecuencia">(<?php echo $horario['frecuencia']; ?>)</span>
                                                <span class="estado <?php echo $horario['activo'] ? 'activo' : 'inactivo'; ?>">
                                                    <?php echo $horario['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>