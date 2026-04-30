<?php
// simulacion.php - Sistema COMPLETO de simulación para MediRecord
session_start();

// Simular base de datos en sesión
if (!isset($_SESSION['base_datos'])) {
    $_SESSION['base_datos'] = [
        'pacientes' => [
            1 => [
                'id' => 1,
                'nombre' => 'Ana García',
                'telefono' => '+5215512345678',
                'medicamentos' => [
                    [
                        'nombre' => 'Paracetamol',
                        'dosis' => '500mg',
                        'hora' => '10:00',
                        'instrucciones' => 'Tomar después del desayuno'
                    ],
                    [
                        'nombre' => 'Loratadina',
                        'dosis' => '10mg',
                        'hora' => '20:00',
                        'instrucciones' => 'Antes de dormir'
                    ]
                ]
            ],
            2 => [
                'id' => 2,
                'nombre' => 'Carlos López',
                'telefono' => '+5215598765432',
                'medicamentos' => [
                    [
                        'nombre' => 'Metformina',
                        'dosis' => '850mg',
                        'hora' => '08:00',
                        'instrucciones' => 'Con el desayuno'
                    ]
                ]
            ]
        ],
        'recordatorios' => [],
        'confirmaciones' => []
    ];
}

// Manejar AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $accion = $_POST['accion'] ?? '';
    $respuesta = [];
    
    switch ($accion) {
        case 'ejecutar_cron':
            $respuesta = ejecutarCronjob();
            break;
            
        case 'enviar_recordatorio':
            $id_paciente = $_POST['id_paciente'] ?? 1;
            $respuesta = enviarRecordatorio($id_paciente);
            break;
            
        case 'confirmar_toma':
            $id_recordatorio = $_POST['id_recordatorio'] ?? 0;
            $respuesta = confirmarToma($id_recordatorio);
            break;
            
        case 'ver_estadisticas':
            $respuesta = obtenerEstadisticas();
            break;
            
        case 'reiniciar_sistema':
            session_destroy();
            $respuesta = ['success' => true, 'message' => 'Sistema reiniciado'];
            break;
            
        default:
            $respuesta = ['success' => false, 'message' => 'Acción no válida'];
    }
    
    echo json_encode($respuesta);
    exit;
}

// Función para ejecutar cronjob simulado
function ejecutarCronjob() {
    $hora_actual = date('H:i');
    $recordatorios_enviados = [];
    
    foreach ($_SESSION['base_datos']['pacientes'] as $paciente) {
        foreach ($paciente['medicamentos'] as $medicamento) {
            if ($medicamento['hora'] === $hora_actual) {
                // Verificar si ya se envió hoy
                $ya_enviado = false;
                foreach ($_SESSION['base_datos']['recordatorios'] as $recordatorio) {
                    if ($recordatorio['id_paciente'] === $paciente['id'] && 
                        $recordatorio['medicamento'] === $medicamento['nombre'] &&
                        date('Y-m-d', strtotime($recordatorio['timestamp'])) === date('Y-m-d')) {
                        $ya_enviado = true;
                        break;
                    }
                }
                
                if (!$ya_enviado) {
                    $id_recordatorio = count($_SESSION['base_datos']['recordatorios']) + 1;
                    $recordatorio = [
                        'id' => $id_recordatorio,
                        'id_paciente' => $paciente['id'],
                        'paciente' => $paciente['nombre'],
                        'telefono' => $paciente['telefono'],
                        'medicamento' => $medicamento['nombre'],
                        'dosis' => $medicamento['dosis'],
                        'hora' => $medicamento['hora'],
                        'instrucciones' => $medicamento['instrucciones'],
                        'timestamp' => date('Y-m-d H:i:s'),
                        'estado' => 'enviado',
                        'confirmado' => false
                    ];
                    
                    $_SESSION['base_datos']['recordatorios'][] = $recordatorio;
                    $recordatorios_enviados[] = $recordatorio;
                }
            }
        }
    }
    
    return [
        'success' => true,
        'message' => count($recordatorios_enviados) > 0 ? 
                    'Cronjob ejecutado: ' . count($recordatorios_enviados) . ' recordatorio(s) enviado(s)' :
                    'Cronjob ejecutado: No hay medicamentos programados para esta hora',
        'recordatorios' => $recordatorios_enviados,
        'hora' => $hora_actual
    ];
}

// Función para enviar recordatorio específico
function enviarRecordatorio($id_paciente) {
    $paciente = $_SESSION['base_datos']['pacientes'][$id_paciente] ?? null;
    
    if (!$paciente) {
        return ['success' => false, 'message' => 'Paciente no encontrado'];
    }
    
    $medicamento = $paciente['medicamentos'][0] ?? $paciente['medicamentos'][array_rand($paciente['medicamentos'])];
    
    $id_recordatorio = count($_SESSION['base_datos']['recordatorios']) + 1;
    $recordatorio = [
        'id' => $id_recordatorio,
        'id_paciente' => $paciente['id'],
        'paciente' => $paciente['nombre'],
        'telefono' => $paciente['telefono'],
        'medicamento' => $medicamento['nombre'],
        'dosis' => $medicamento['dosis'],
        'hora' => $medicamento['hora'],
        'instrucciones' => $medicamento['instrucciones'],
        'timestamp' => date('Y-m-d H:i:s'),
        'estado' => 'enviado',
        'confirmado' => false
    ];
    
    $_SESSION['base_datos']['recordatorios'][] = $recordatorio;
    
    return [
        'success' => true,
        'message' => 'Recordatorio enviado a ' . $paciente['nombre'],
        'recordatorio' => $recordatorio
    ];
}

// Función para confirmar toma
function confirmarToma($id_recordatorio) {
    // Buscar el recordatorio
    foreach ($_SESSION['base_datos']['recordatorios'] as &$recordatorio) {
        if ($recordatorio['id'] == $id_recordatorio && !$recordatorio['confirmado']) {
            $recordatorio['confirmado'] = true;
            $recordatorio['hora_confirmacion'] = date('H:i:s');
            $recordatorio['estado'] = 'confirmado';
            
            // Registrar confirmación
            $_SESSION['base_datos']['confirmaciones'][] = [
                'id_recordatorio' => $id_recordatorio,
                'paciente' => $recordatorio['paciente'],
                'medicamento' => $recordatorio['medicamento'],
                'hora_recordatorio' => $recordatorio['hora'],
                'hora_confirmacion' => date('H:i:s'),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            return [
                'success' => true,
                'message' => '✅ ' . $recordatorio['paciente'] . ' confirmó la toma de ' . $recordatorio['medicamento'],
                'confirmacion' => $recordatorio
            ];
        }
    }
    
    return ['success' => false, 'message' => 'Recordatorio no encontrado o ya confirmado'];
}

// Función para obtener estadísticas
function obtenerEstadisticas() {
    $total_pacientes = count($_SESSION['base_datos']['pacientes']);
    $total_recordatorios = count($_SESSION['base_datos']['recordatorios']);
    
    $confirmados = 0;
    foreach ($_SESSION['base_datos']['recordatorios'] as $recordatorio) {
        if ($recordatorio['confirmado']) $confirmados++;
    }
    
    return [
        'success' => true,
        'estadisticas' => [
            'pacientes' => $total_pacientes,
            'recordatorios_hoy' => $total_recordatorios,
            'confirmados_hoy' => $confirmados,
            'pendientes_hoy' => $total_recordatorios - $confirmados,
            'tasa_exito' => $total_recordatorios > 0 ? round(($confirmados / $total_recordatorios) * 100, 1) : 0
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Sistema de Simulación</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .header p {
            color: #7f8c8d;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            background: #25D366;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }

        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h3 i {
            color: #3498db;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
            margin: 5px;
        }

        .btn:hover {
            background: #2980b9;
            transform: scale(1.05);
        }

        .btn-whatsapp {
            background: #25D366;
        }

        .btn-whatsapp:hover {
            background: #1DA851;
        }

        .btn-success {
            background: #2ecc71;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #d35400;
        }

        .log-container {
            background: #2c3e50;
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
            max-height: 300px;
            overflow-y: auto;
        }

        .log-entry {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .log-time {
            color: #3498db;
        }

        .log-success {
            color: #2ecc71;
        }

        .log-warning {
            color: #f39c12;
        }

        .log-error {
            color: #e74c3c;
        }

        .chat-container {
            background: #ece5dd;
            border-radius: 10px;
            padding: 20px;
            height: 400px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            max-width: 70%;
            padding: 12px;
            border-radius: 15px;
            position: relative;
        }

        .message-out {
            background: #dcf8c6;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .message-in {
            background: white;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .message-header {
            font-weight: bold;
            margin-bottom: 5px;
            color: #075e54;
        }

        .message-time {
            font-size: 11px;
            color: #666;
            text-align: right;
            margin-top: 5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-box {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #3498db;
        }

        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .patient-list {
            list-style: none;
        }

        .patient-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .patient-info h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .patient-info p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .medication-tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 5px;
        }

        .control-panel {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        .clock {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            margin: 20px 0;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .control-panel {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-pills"></i> MediRecord System <span class="badge">SIMULACIÓN</span></h1>
            <p>Sistema de recordatorios de medicamentos vía WhatsApp - Modo Presentación</p>
            <div class="clock" id="liveClock">--:--:--</div>
        </div>

        <div class="control-panel">
            <button class="btn" onclick="ejecutarCronjob()">
                <i class="fas fa-play"></i> Ejecutar Cronjob Automático
            </button>
            <button class="btn btn-whatsapp" onclick="enviarRecordatorioManual()">
                <i class="fab fa-whatsapp"></i> Enviar Recordatorio Manual
            </button>
            <button class="btn btn-success" onclick="simularConfirmacion()">
                <i class="fas fa-check"></i> Simular Respuesta "SI"
            </button>
            <button class="btn btn-warning" onclick="reiniciarSistema()">
                <i class="fas fa-redo"></i> Reiniciar Sistema
            </button>
        </div>

        <div class="dashboard">
            <!-- Panel de WhatsApp -->
            <div class="card">
                <h3><i class="fab fa-whatsapp"></i> Simulación de WhatsApp</h3>
                <div class="chat-container" id="whatsappChat">
                    <div class="message message-in">
                        <div class="message-header">MediRecord</div>
                        Sistema de simulación iniciado. Esperando recordatorios...
                        <div class="message-time" id="currentTime"><?php echo date('H:i'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Panel de Control -->
            <div class="card">
                <h3><i class="fas fa-cogs"></i> Control del Sistema</h3>
                <div style="margin-bottom: 20px;">
                    <h4>Pacientes en sistema:</h4>
                    <ul class="patient-list">
                        <?php foreach ($_SESSION['base_datos']['pacientes'] as $paciente): ?>
                        <li class="patient-item">
                            <div class="patient-info">
                                <h4><?php echo $paciente['nombre']; ?></h4>
                                <p><?php echo $paciente['telefono']; ?></p>
                                <div>
                                    <?php foreach ($paciente['medicamentos'] as $med): ?>
                                    <span class="medication-tag"><?php echo $med['nombre']; ?> (<?php echo $med['hora']; ?>)</span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button class="btn" onclick="enviarRecordatorioEspecifico(<?php echo $paciente['id']; ?>)">
                                Enviar
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Panel de Estadísticas -->
            <div class="card">
                <h3><i class="fas fa-chart-bar"></i> Estadísticas en Tiempo Real</h3>
                <div class="stats-grid" id="statsContainer">
                    <!-- Las estadísticas se cargarán por AJAX -->
                </div>
                <button class="btn" onclick="actualizarEstadisticas()" style="margin-top: 15px;">
                    <i class="fas fa-sync"></i> Actualizar Estadísticas
                </button>
            </div>

            <!-- Panel de Logs -->
            <div class="card">
                <h3><i class="fas fa-clipboard-list"></i> Registro de Eventos</h3>
                <div class="log-container" id="logContainer">
                    <div class="log-entry">
                        <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                        <span class="log-success"> Sistema de simulación iniciado correctamente</span>
                    </div>
                </div>
                <button class="btn" onclick="limpiarLogs()" style="margin-top: 15px;">
                    <i class="fas fa-trash"></i> Limpiar Logs
                </button>
            </div>
        </div>

        <!-- Panel de Base de Datos -->
        <div class="card" style="margin-top: 20px;">
            <h3><i class="fas fa-database"></i> Estado de la Base de Datos</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                <div>
                    <h4><i class="fas fa-user-injured"></i> Pacientes</h4>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
                        <?php foreach ($_SESSION['base_datos']['pacientes'] as $paciente): ?>
                        <div style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <strong><?php echo $paciente['nombre']; ?></strong><br>
                            <small><?php echo $paciente['telefono']; ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <h4><i class="fas fa-bell"></i> Recordatorios Hoy</h4>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
                        <?php 
                        $recordatorios_hoy = array_filter($_SESSION['base_datos']['recordatorios'], function($r) {
                            return date('Y-m-d', strtotime($r['timestamp'])) === date('Y-m-d');
                        });
                        
                        if (empty($recordatorios_hoy)) {
                            echo "<em>No hay recordatorios hoy</em>";
                        } else {
                            foreach ($recordatorios_hoy as $recordatorio) {
                                $estado = $recordatorio['confirmado'] ? '✅' : '⏳';
                                echo "<div style='padding: 5px 0; border-bottom: 1px solid #eee;'>
                                    {$estado} {$recordatorio['paciente']} - {$recordatorio['medicamento']}
                                </div>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Reloj en tiempo real
        function actualizarReloj() {
            const ahora = new Date();
            const hora = ahora.getHours().toString().padStart(2, '0');
            const minutos = ahora.getMinutes().toString().padStart(2, '0');
            const segundos = ahora.getSeconds().toString().padStart(2, '0');
            document.getElementById('liveClock').textContent = `${hora}:${minutos}:${segundos}`;
        }
        setInterval(actualizarReloj, 1000);
        actualizarReloj();

        // Función para agregar logs
        function agregarLog(mensaje, tipo = 'success') {
            const logContainer = document.getElementById('logContainer');
            const ahora = new Date();
            const hora = ahora.toLocaleTimeString();
            
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry';
            logEntry.innerHTML = `<span class="log-time">[${hora}]</span> <span class="log-${tipo}">${mensaje}</span>`;
            
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        // Función para mostrar mensaje en WhatsApp
        function mostrarMensajeWhatsApp(mensaje, esRespuesta = false, remitente = 'MediRecord') {
            const chat = document.getElementById('whatsappChat');
            const ahora = new Date();
            const hora = ahora.toLocaleTimeString();
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${esRespuesta ? 'message-out' : 'message-in'}`;
            messageDiv.innerHTML = `
                <div class="message-header">${remitente}</div>
                ${mensaje}
                <div class="message-time">${hora}</div>
            `;
            
            chat.appendChild(messageDiv);
            chat.scrollTop = chat.scrollHeight;
        }

        // Función para actualizar estadísticas
        function actualizarEstadisticas() {
            fetch('simulacion.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=ver_estadisticas'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const stats = data.estadisticas;
                    document.getElementById('statsContainer').innerHTML = `
                        <div class="stat-box">
                            <div class="stat-value">${stats.pacientes}</div>
                            <div class="stat-label">Pacientes</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">${stats.recordatorios_hoy}</div>
                            <div class="stat-label">Recordatorios Hoy</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">${stats.confirmados_hoy}</div>
                            <div class="stat-label">Confirmados</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">${stats.tasa_exito}%</div>
                            <div class="stat-label">Tasa de Éxito</div>
                        </div>
                    `;
                    agregarLog('Estadísticas actualizadas', 'success');
                }
            });
        }

        // Función para ejecutar cronjob automático
        function ejecutarCronjob() {
            agregarLog('Ejecutando cronjob automático...', 'warning');
            
            fetch('simulacion.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=ejecutar_cron'
            })
            .then(r => r.json())
            .then(data => {
                agregarLog(data.message, data.success ? 'success' : 'error');
                
                if (data.success && data.recordatorios && data.recordatorios.length > 0) {
                    data.recordatorios.forEach(recordatorio => {
                        const mensaje = `Hola ${recordatorio.paciente}, es hora de tu medicamento.\n\n💊 ${recordatorio.medicamento}\n📦 ${recordatorio.dosis}\n🕒 ${recordatorio.hora}\n📝 ${recordatorio.instrucciones}\n\nPor favor, responda SI cuando haya tomado su medicamento.`;
                        mostrarMensajeWhatsApp(mensaje, false, 'MediRecord');
                    });
                }
                actualizarEstadisticas();
            });
        }

        // Función para enviar recordatorio manual
        function enviarRecordatorioManual() {
            agregarLog('Enviando recordatorio manual...', 'warning');
            
            fetch('simulacion.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=enviar_recordatorio&id_paciente=1'
            })
            .then(r => r.json())
            .then(data => {
                agregarLog(data.message, data.success ? 'success' : 'error');
                
                if (data.success) {
                    const r = data.recordatorio;
                    const mensaje = `Hola ${r.paciente}, es hora de tu medicamento.\n\n💊 ${r.medicamento}\n📦 ${r.dosis}\n🕒 ${r.hora}\n📝 ${r.instrucciones}\n\nPor favor, responda SI cuando haya tomado su medicamento.`;
                    mostrarMensajeWhatsApp(mensaje, false, 'MediRecord');
                }
                actualizarEstadisticas();
            });
        }

        // Función para enviar recordatorio a paciente específico
        function enviarRecordatorioEspecifico(idPaciente) {
            agregarLog(`Enviando recordatorio a paciente ID: ${idPaciente}`, 'warning');
            
            fetch('simulacion.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `accion=enviar_recordatorio&id_paciente=${idPaciente}`
            })
            .then(r => r.json())
            .then(data => {
                agregarLog(data.message, data.success ? 'success' : 'error');
                
                if (data.success) {
                    const r = data.recordatorio;
                    const mensaje = `Hola ${r.paciente}, es hora de tu medicamento.\n\n💊 ${r.medicamento}\n📦 ${r.dosis}\n🕒 ${r.hora}\n📝 ${r.instrucciones}\n\nPor favor, responda SI cuando haya tomado su medicamento.`;
                    mostrarMensajeWhatsApp(mensaje, false, 'MediRecord');
                }
                actualizarEstadisticas();
            });
        }

        // Función para simular confirmación
        function simularConfirmacion() {
            // Buscar el último recordatorio no confirmado
            fetch('simulacion.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=confirmar_toma&id_recordatorio=1'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    agregarLog(data.message, 'success');
                    
                    // Mostrar respuesta SI del paciente
                    mostrarMensajeWhatsApp('SI', true, 'Paciente');
                    
                    // Mostrar confirmación del sistema
                    setTimeout(() => {
                        mostrarMensajeWhatsApp(`✅ Gracias por confirmar la toma de su medicamento. ¡Que tenga un buen día!`, false, 'MediRecord');
                    }, 1000);
                    
                    actualizarEstadisticas();
                } else {
                    agregarLog('No hay recordatorios pendientes para confirmar', 'warning');
                    mostrarMensajeWhatsApp('No hay recordatorios pendientes. Primero envía un recordatorio.', false, 'Sistema');
                }
            });
        }

        // Función para reiniciar sistema
        function reiniciarSistema() {
            if (confirm('¿Estás seguro de reiniciar el sistema? Se perderán todos los datos de la sesión.')) {
                fetch('simulacion.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'accion=reiniciar_sistema'
                })
                .then(r => r.json())
                .then(data => {
                    location.reload();
                });
            }
        }

        // Función para limpiar logs
        function limpiarLogs() {
            document.getElementById('logContainer').innerHTML = '';
            agregarLog('Logs limpiados', 'warning');
        }

        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            actualizarEstadisticas();
            agregarLog('Sistema de simulación listo para la presentación', 'success');
            
            // Mostrar mensaje de bienvenida
            setTimeout(() => {
                mostrarMensajeWhatsApp('Sistema MediRecord activo. Listo para enviar recordatorios de medicamentos.', false, 'Sistema');
            }, 1000);
        });

        // Teclas rápidas para la presentación
        document.addEventListener('keydown', function(e) {
            // F1 - Ejecutar cronjob
            if (e.key === 'F1' || e.key === 'F2' || e.key === 'F3') {
                e.preventDefault();
                if (e.key === 'F1') ejecutarCronjob();
                if (e.key === 'F2') enviarRecordatorioManual();
                if (e.key === 'F3') simularConfirmacion();
            }
            
            // Espacio - Mostrar ayuda
            if (e.code === 'Space') {
                e.preventDefault();
                alert('Teclas rápidas:\nF1 - Ejecutar Cronjob\nF2 - Enviar Recordatorio\nF3 - Confirmar Toma\n\nPara la presentación, sigue el flujo: 1 → 2 → 3');
            }
        });
    </script>
</body>
</html>
