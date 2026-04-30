<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $horario_id = $_POST['horario_id'];
    $estado = $_POST['estado'] ?? 'tomado';
    
    try {
        // Verificar permisos según el tipo de usuario
        if ($user_type === 'paciente') {
            $stmt = $pdo->prepare("
                SELECT h.id_horario 
                FROM horarios h
                JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
                WHERE h.id_horario = ? AND m.id_usuario = ?
            ");
            $stmt->execute([$horario_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT h.id_horario 
                FROM horarios h
                JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
                JOIN vinculaciones v ON m.id_usuario = v.id_paciente
                WHERE h.id_horario = ? AND v.id_cuidador = ? AND v.confirmado = 1
            ");
            $stmt->execute([$horario_id, $user_id]);
        }
        
        if ($stmt->fetch()) {
            // Registrar la toma en el historial
            $historial_id = recordMedicationTaken($horario_id, $estado);
            
            if ($historial_id) {
                // Obtener información para notificaciones WhatsApp
                $stmt = $pdo->prepare("
                    SELECT m.nombre_medicamento, m.dosis, h.hora, u.nombre as paciente_nombre,
                           m.id_usuario as paciente_id, u.telefono as paciente_telefono
                    FROM horarios h
                    JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
                    JOIN usuarios u ON m.id_usuario = u.id_usuario
                    WHERE h.id_horario = ?
                ");
                $stmt->execute([$horario_id]);
                $medicamento_info = $stmt->fetch();
                
                // Notificar a cuidadores vía WhatsApp si es un paciente
                if ($user_type === 'paciente') {
                    notificarConfirmacionCuidador($user_id, $medicamento_info);
                    
                    // También enviar confirmación al propio paciente
                    if (!empty($medicamento_info['paciente_telefono'])) {
                        $mensaje = "Confirmación: Has registrado la toma de " . 
                                  $medicamento_info['nombre_medicamento'] . " correctamente.";
                        enviarWhatsApp($medicamento_info['paciente_telefono'], $mensaje, 'confirmacion');
                    }
                }
                
                redirectWithMessage('dashboard.php', 'success', 'Toma registrada correctamente y notificaciones enviadas');
            } else {
                redirectWithMessage('dashboard.php', 'error', 'Error al registrar la toma');
            }
        } else {
            redirectWithMessage('dashboard.php', 'error', 'No tienes permisos para confirmar esta toma');
        }
    } catch (PDOException $e) {
        redirectWithMessage('dashboard.php', 'error', 'Error: ' . $e->getMessage());
    }
} else {
    redirectWithMessage('dashboard.php', 'error', 'Método no permitido');
}
?>