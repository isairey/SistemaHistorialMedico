<?php
include 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if (!isset($_GET['id'])) {
    redirectWithMessage('medications.php', 'error', 'ID de medicamento no especificado');
}

$medicamento_id = $_GET['id'];

// Verificar permisos
if (!verificarPermisoMedicamento($user_id, $medicamento_id)) {
    redirectWithMessage('medications.php', 'error', 'No tienes permisos para editar este medicamento');
}

// Obtener datos actuales
$medicamento = obtenerMedicamentoPorId($medicamento_id);
if (!$medicamento) {
    redirectWithMessage('medications.php', 'error', 'Medicamento no encontrado');
}

$horarios = obtenerHorariosMedicamento($medicamento_id);

// Obtener información del paciente (para cuidadores)
$paciente_info = null;
if ($user_type === 'cuidador') {
    $stmt = $pdo->prepare("
        SELECT u.nombre 
        FROM usuarios u 
        JOIN medicamentos m ON u.id_usuario = m.id_usuario 
        WHERE m.id_medicamento = ?
    ");
    $stmt->execute([$medicamento_id]);
    $paciente_info = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $dosis = trim($_POST['dosis']);
    $instrucciones = trim($_POST['instrucciones']);
    $nuevos_horarios = $_POST['horario'] ?? [];

    // Validaciones básicas
    if (empty($nombre) || empty($dosis)) {
        $error = 'El nombre y la dosis son obligatorios';
    } else {
        try {
            // Iniciar transacción para asegurar consistencia
            $pdo->beginTransaction();

            // Actualizar medicamento
            $stmt = $pdo->prepare("UPDATE medicamentos SET nombre_medicamento = ?, dosis = ?, instrucciones = ? WHERE id_medicamento = ?");
            $stmt->execute([$nombre, $dosis, $instrucciones, $medicamento_id]);

            // Eliminar horarios antiguos
            $stmt = $pdo->prepare("DELETE FROM horarios WHERE id_medicamento = ?");
            $stmt->execute([$medicamento_id]);

            // Insertar nuevos horarios
            foreach ($nuevos_horarios as $hora) {
                if (!empty($hora)) {
                    $stmt = $pdo->prepare("INSERT INTO horarios (id_medicamento, hora, frecuencia) VALUES (?, ?, 'diario')");
                    $stmt->execute([$medicamento_id, $hora]);
                }
            }

            $pdo->commit();
            redirectWithMessage('medications.php', 'success', 'Medicamento actualizado correctamente');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al actualizar el medicamento: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Medicamento - MediRecord</title>
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
            <h2>Editar Medicamento</h2>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Información del paciente (solo para cuidadores) -->
            <?php if ($user_type === 'cuidador' && $paciente_info): ?>
                <div class="patient-info-card">
                    <h3>📋 Información del Paciente</h3>
                    <p><strong>Paciente:</strong> <?php echo htmlspecialchars($paciente_info['nombre']); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label for="nombre">Nombre del medicamento:</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($medicamento['nombre_medicamento']); ?>" required>
                </div>

                <div class="input-group">
                    <label for="dosis">Dosis:</label>
                    <input type="text" id="dosis" name="dosis" value="<?php echo htmlspecialchars($medicamento['dosis']); ?>" required placeholder="Ej: 1 tableta">
                </div>

                <div class="input-group">
                    <label for="instrucciones">Instrucciones (opcional):</label>
                    <textarea id="instrucciones" name="instrucciones" placeholder="Ej: Tomar con alimentos, no mezclar con alcohol..."><?php echo htmlspecialchars($medicamento['instrucciones']); ?></textarea>
                </div>

                <div class="input-group">
                    <label>Horarios:</label>
                    <div id="horarios">
                        <?php if (count($horarios) > 0): ?>
                            <?php foreach ($horarios as $index => $horario): ?>
                                <div class="horario-input">
                                    <input type="time" name="horario[]" value="<?php echo htmlspecialchars($horario['hora']); ?>" required>
                                    <button type="button" class="remove-time" onclick="removeTime(this)">🗑️ Eliminar</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="horario-input">
                                <input type="time" name="horario[]" required>
                                <button type="button" class="remove-time" onclick="removeTime(this)">🗑️ Eliminar</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-secondary" onclick="addTime()">➕ Añadir otro horario</button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">💾 Guardar Cambios</button>
                    <a href="medications.php" class="btn btn-secondary">❌ Cancelar</a>
                </div>
            </form>

            <!-- Sección de información adicional -->
            <div class="medication-info">
                <h3>ℹ️ Información del Medicamento</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>ID:</strong> <?php echo $medicamento['id_medicamento']; ?>
                    </div>
                    <div class="info-item">
                        <strong>Agregado por:</strong> 
                        <?php 
                        if ($medicamento['agregado_por']) {
                            $stmt = $pdo->prepare("SELECT nombre FROM usuarios WHERE id_usuario = ?");
                            $stmt->execute([$medicamento['agregado_por']]);
                            $agregador = $stmt->fetch();
                            echo htmlspecialchars($agregador['nombre'] ?? 'Desconocido');
                        } else {
                            echo 'Usuario actual';
                        }
                        ?>
                    </div>
                    <div class="info-item">
                        <strong>Fecha de creación:</strong> 
                        <?php echo date('d/m/Y H:i', strtotime($medicamento['fecha_agregado'] ?? $medicamento['fecha_registro'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addTime() {
            const container = document.getElementById('horarios');
            const timeInputs = container.querySelectorAll('input[type="time"]');
            
            // Máximo 6 horarios
            if (timeInputs.length >= 6) {
                alert('Máximo 6 horarios por medicamento');
                return;
            }
            
            const div = document.createElement('div');
            div.className = 'horario-input';
            div.innerHTML = `
                <input type="time" name="horario[]" required>
                <button type="button" class="remove-time" onclick="removeTime(this)">🗑️ Eliminar</button>
            `;
            container.appendChild(div);
        }

        function removeTime(button) {
            const container = document.getElementById('horarios');
            const timeInputs = container.querySelectorAll('.horario-input');
            
            if (timeInputs.length > 1) {
                button.parentElement.remove();
            } else {
                alert('Debe haber al menos un horario.');
            }
        }

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const dosis = document.getElementById('dosis').value.trim();
            const timeInputs = document.querySelectorAll('input[type="time"]');
            
            let hasValidTime = false;
            timeInputs.forEach(input => {
                if (input.value) hasValidTime = true;
            });
            
            if (!nombre || !dosis) {
                e.preventDefault();
                alert('El nombre y la dosis son obligatorios.');
                return;
            }
            
            if (!hasValidTime) {
                e.preventDefault();
                alert('Debe especificar al menos un horario.');
                return;
            }
        });
    </script>
</body>
</html>