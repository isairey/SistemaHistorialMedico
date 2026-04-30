<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Si es cuidador, obtener pacientes vinculados
$pacientes = [];
if ($user_type === 'cuidador') {
    $pacientes = getPacientesVinculados($user_id);
}

// Función para generar horarios automáticamente desde las instrucciones
function generarHorariosDesdeInstrucciones($instrucciones) {
    $horarios = [];
    $texto = strtolower($instrucciones);
    
    if (preg_match('/(\d+)\s*(hora|hr|h)/', $texto, $matches)) {
        $intervalo_horas = intval($matches[1]);
        $hora_base = '08:00';
        
        switch($intervalo_horas) {
            case 24: $horarios[] = $hora_base; break;
            case 12: $horarios = ['08:00', '20:00']; break;
            case 8: $horarios = ['08:00', '16:00', '00:00']; break;
            case 6: $horarios = ['08:00', '14:00', '20:00', '02:00']; break;
            case 4: $horarios = ['08:00', '12:00', '16:00', '20:00', '00:00', '04:00']; break;
            default:
                $horas_por_dia = 24 / $intervalo_horas;
                for ($i = 0; $i < $horas_por_dia; $i++) {
                    $hora = $i * $intervalo_horas;
                    $horarios[] = sprintf('%02d:00', $hora);
                }
                break;
        }
    }
    elseif (preg_match_all('/(\d{1,2}):?(\d{2})?\s*(am|pm)?/', $texto, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $hora = intval($match[1]);
            $minutos = isset($match[2]) ? intval($match[2]) : 0;
            $periodo = isset($match[3]) ? $match[3] : '';
            
            if ($periodo === 'pm' && $hora < 12) $hora += 12;
            elseif ($periodo === 'am' && $hora === 12) $hora = 0;
            
            $horarios[] = sprintf('%02d:%02d', $hora, $minutos);
        }
    }
    elseif (strpos($texto, 'mañana') !== false && strpos($texto, 'noche') !== false) {
        $horarios = ['08:00', '20:00'];
    }
    elseif (strpos($texto, 'desayuno') !== false && strpos($texto, 'cena') !== false) {
        $horarios = ['08:00', '20:00'];
    }
    elseif (strpos($texto, 'desayuno') !== false && strpos($texto, 'almuerzo') !== false && strpos($texto, 'cena') !== false) {
        $horarios = ['08:00', '13:00', '20:00'];
    }
    elseif (strpos($texto, 'una vez') !== false || strpos($texto, 'al día') !== false) {
        $horarios = ['08:00'];
    }
    
    $horarios = array_unique($horarios);
    sort($horarios);
    return array_slice($horarios, 0, 6);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $dosis = $_POST['dosis'];
    $instrucciones = $_POST['instrucciones'];
    
    // Determinar para quién es el medicamento
    if ($user_type === 'cuidador') {
        $id_paciente = $_POST['id_paciente'];
        
        // Verificar que el cuidador tiene permisos sobre este paciente
        if (!cuidadorTieneAccesoPaciente($user_id, $id_paciente)) {
            $error = "No tienes permisos para agregar medicamentos a este paciente";
        }
    } else {
        // Si es paciente, el medicamento es para sí mismo
        $id_paciente = $user_id;
    }
    
    if (!isset($error)) {
        // Determinar horarios
        if (isset($_POST['usar_horarios_auto']) && $_POST['usar_horarios_auto'] === 'si' && !empty($instrucciones)) {
            $horarios = generarHorariosDesdeInstrucciones($instrucciones);
        } else {
            $horarios = $_POST['horario'] ?? [];
        }
        
        try {
            // Insertar medicamento - importante: id_usuario es el PACIENTE, agregado_por es quien lo agregó
            $stmt = $pdo->prepare("INSERT INTO medicamentos (id_usuario, nombre_medicamento, dosis, instrucciones, agregado_por) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_paciente, $nombre, $dosis, $instrucciones, $user_id]);
            $medicamento_id = $pdo->lastInsertId();
            
            // Insertar horarios
            foreach ($horarios as $hora) {
                if (!empty($hora)) {
                    $stmt = $pdo->prepare("INSERT INTO horarios (id_medicamento, hora, frecuencia) VALUES (?, ?, 'diario')");
                    $stmt->execute([$medicamento_id, $hora]);
                }
            }
            
            redirectWithMessage('medications.php', 'success', 'Medicamento agregado correctamente');
        } catch (PDOException $e) {
            $error = "Error al guardar el medicamento: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Medicamento - MediRecord</title>
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
            <h2>
                <?php 
                if ($user_type === 'cuidador') {
                    echo 'Añadir medicamento para paciente';
                } else {
                    echo 'Añadir nuevo medicamento';
                }
                ?>
            </h2>
            
            <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="medicationForm">
                <!-- Selector de paciente para cuidadores -->
                <?php if ($user_type === 'cuidador'): ?>
                <div class="input-group">
                    <label for="id_paciente">Seleccionar paciente:</label>
                    <select id="id_paciente" name="id_paciente" required>
                        <option value="">-- Seleccione un paciente --</option>
                        <?php foreach ($pacientes as $paciente): ?>
                        <option value="<?php echo $paciente['id_usuario']; ?>">
                            <?php echo htmlspecialchars($paciente['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($pacientes)): ?>
                    <small class="help-text warning">
                        ⚠️ No tienes pacientes vinculados. <a href="caregivers.php">Vincula un paciente primero</a>.
                    </small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="input-group">
                    <label for="nombre">Nombre del medicamento:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="input-group">
                    <label for="dosis">Dosis:</label>
                    <input type="text" id="dosis" name="dosis" required placeholder="Ej: 1 tableta">
                </div>
                
                <div class="input-group">
                    <label for="instrucciones">Instrucciones:</label>
                    <textarea id="instrucciones" name="instrucciones" placeholder="Ej: Tomar 1 tableta cada 8 horas por 3 días, o Tomar con el desayuno y la cena"></textarea>
                    <small class="help-text">
                        💡 <strong>Sugerencias:</strong> 
                        "cada 8 horas", "mañana y noche", "con el desayuno", "9:00 AM y 9:00 PM", "cada 12 horas"
                    </small>
                </div>
                
                <div class="input-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="usar_horarios_auto" name="usar_horarios_auto" value="si" checked>
                        <label for="usar_horarios_auto">Generar horarios automáticamente desde las instrucciones</label>
                    </div>
                </div>
                
                <div class="input-group" id="seccionHorariosManual" style="display: none;">
                    <label>Horarios (modo manual):</label>
                    <div id="horarios">
                        <div class="horario-input">
                            <input type="time" name="horario[]">
                            <button type="button" class="remove-time" onclick="removeTime(this)">Eliminar</button>
                        </div>
                    </div>
                    <button type="button" class="btn-secondary" onclick="addTime()">Añadir otro horario</button>
                </div>
                
                <div class="input-group" id="previewHorarios">
                    <label>Horarios generados automáticamente:</label>
                    <div id="horariosPreview" class="horarios-preview">
                        <p class="preview-placeholder">Los horarios aparecerán aquí según las instrucciones...</p>
                    </div>
                </div>
                
                <button type="submit" class="btn" <?php echo ($user_type === 'cuidador' && empty($pacientes)) ? 'disabled' : ''; ?>>
                    💾 
                    <?php 
                    if ($user_type === 'cuidador') {
                        echo 'Agregar medicamento al paciente';
                    } else {
                        echo 'Guardar medicamento';
                    }
                    ?>
                </button>
                
                <?php if ($user_type === 'cuidador' && empty($pacientes)): ?>
                <p class="warning-text">
                    ⚠️ Debes tener al menos un paciente vinculado para agregar medicamentos.
                    <a href="caregivers.php">Gestionar pacientes</a>
                </p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // Elementos del DOM
        const instruccionesInput = document.getElementById('instrucciones');
        const horariosPreview = document.getElementById('horariosPreview');
        const usarHorariosAuto = document.getElementById('usar_horarios_auto');
        const seccionHorariosManual = document.getElementById('seccionHorariosManual');
        const previewHorarios = document.getElementById('previewHorarios');
        const idPacienteSelect = document.getElementById('id_paciente');
        
        // Función para analizar instrucciones y generar horarios
        function analizarInstrucciones(texto) {
            const horarios = [];
            texto = texto.toLowerCase();
            
            const intervaloMatch = texto.match(/(\d+)\s*(hora|hr|h)/);
            if (intervaloMatch) {
                const intervalo = parseInt(intervaloMatch[1]);
                switch(intervalo) {
                    case 24: horarios.push('08:00'); break;
                    case 12: horarios.push('08:00', '20:00'); break;
                    case 8: horarios.push('08:00', '16:00', '00:00'); break;
                    case 6: horarios.push('08:00', '14:00', '20:00', '02:00'); break;
                    case 4: horarios.push('08:00', '12:00', '16:00', '20:00', '00:00', '04:00'); break;
                    default:
                        const horasPorDia = 24 / intervalo;
                        for (let i = 0; i < horasPorDia; i++) {
                            const hora = i * intervalo;
                            horarios.push(`${hora.toString().padStart(2, '0')}:00`);
                        }
                }
            }
            
            const horariosEspecificos = texto.matchAll(/(\d{1,2}):?(\d{2})?\s*(am|pm)?/g);
            for (const match of horariosEspecificos) {
                let hora = parseInt(match[1]);
                const minutos = match[2] ? parseInt(match[2]) : 0;
                const periodo = match[3];
                
                if (periodo === 'pm' && hora < 12) hora += 12;
                if (periodo === 'am' && hora === 12) hora = 0;
                
                horarios.push(`${hora.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`);
            }
            
            if (texto.includes('mañana') && texto.includes('noche')) {
                horarios.push('08:00', '20:00');
            } else if (texto.includes('desayuno') && texto.includes('cena')) {
                horarios.push('08:00', '20:00');
            } else if (texto.includes('desayuno') && texto.includes('almuerzo') && texto.includes('cena')) {
                horarios.push('08:00', '13:00', '20:00');
            } else if (texto.includes('una vez') || texto.includes('al día')) {
                horarios.push('08:00');
            } else if (texto.includes('cada día') || texto.includes('diario')) {
                horarios.push('08:00');
            }
            
            const horariosUnicos = [...new Set(horarios)].sort();
            return horariosUnicos.slice(0, 6);
        }
        
        function actualizarPreviewHorarios() {
            const instrucciones = instruccionesInput.value.trim();
            
            if (!instrucciones) {
                horariosPreview.innerHTML = '<p class="preview-placeholder">Los horarios aparecerán aquí según las instrucciones...</p>';
                return;
            }
            
            const horariosGenerados = analizarInstrucciones(instrucciones);
            
            if (horariosGenerados.length === 0) {
                horariosPreview.innerHTML = '<p class="preview-warning">⚠️ No se pudieron generar horarios automáticamente. Use el modo manual.</p>';
                return;
            }
            
            let html = '<div class="horarios-lista">';
            horariosGenerados.forEach(horario => {
                const hora12 = convertirHora12Horas(horario);
                html += `
                    <div class="horario-preview-item">
                        <span class="hora-24">${horario}</span>
                        <span class="hora-12">(${hora12})</span>
                    </div>
                `;
            });
            html += '</div>';
            
            horariosPreview.innerHTML = html;
        }
        
        function convertirHora12Horas(hora24) {
            const [horas, minutos] = hora24.split(':');
            let hora = parseInt(horas);
            const periodo = hora >= 12 ? 'PM' : 'AM';
            
            if (hora > 12) hora -= 12;
            if (hora === 0) hora = 12;
            
            return `${hora}:${minutos} ${periodo}`;
        }
        
        function toggleModoHorarios() {
            if (usarHorariosAuto.checked) {
                seccionHorariosManual.style.display = 'none';
                previewHorarios.style.display = 'block';
                actualizarPreviewHorarios();
            } else {
                seccionHorariosManual.style.display = 'block';
                previewHorarios.style.display = 'none';
            }
        }
        
        // Event listeners
        instruccionesInput.addEventListener('input', actualizarPreviewHorarios);
        usarHorariosAuto.addEventListener('change', toggleModoHorarios);
        
        // Inicializar
        toggleModoHorarios();
        
        // Funciones para horarios manuales
        function addTime() {
            const container = document.getElementById('horarios');
            const div = document.createElement('div');
            div.className = 'horario-input';
            div.innerHTML = `
                <input type="time" name="horario[]">
                <button type="button" class="remove-time" onclick="removeTime(this)">Eliminar</button>
            `;
            container.appendChild(div);
        }
        
        function removeTime(button) {
            if (document.querySelectorAll('.horario-input').length > 1) {
                button.parentElement.remove();
            }
        }
        
        // Validación del formulario
        document.getElementById('medicationForm').addEventListener('submit', function(e) {
            <?php if ($user_type === 'cuidador'): ?>
            if (!idPacienteSelect.value) {
                e.preventDefault();
                alert('Por favor, seleccione un paciente.');
                return;
            }
            <?php endif; ?>
            
            const instrucciones = instruccionesInput.value.trim();
            const usarAuto = usarHorariosAuto.checked;
            
            if (!instrucciones) {
                e.preventDefault();
                alert('Por favor, ingrese las instrucciones del medicamento.');
                return;
            }
            
            if (usarAuto) {
                const horariosGenerados = analizarInstrucciones(instrucciones);
                if (horariosGenerados.length === 0) {
                    e.preventDefault();
                    alert('No se pudieron generar horarios automáticamente. Por favor, use el modo manual o revise las instrucciones.');
                    return;
                }
            }
        });
    </script>
</body>
</html>