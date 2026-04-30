<?php
require_once "config.php";

// Token de verificación del webhook (debes usar el mismo en Meta Developers)
$verify_token = "MEDIRECORD_WEBHOOK_TOKEN";

// 1. Verificación del webhook (cuando Meta lo configura)
if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === $verify_token) {
    echo $_GET['hub_challenge'];
    exit;
}

// 2. Procesar mensajes entrantes
$input = json_decode(file_get_contents('php://input'), true);
file_put_contents("webhook.log", date("Y-m-d H:i:s") . " - " . json_encode($input) . "\n", FILE_APPEND);

if (isset($input['entry'][0]['changes'][0]['value']['messages'][0])) {
    $message = $input['entry'][0]['changes'][0]['value']['messages'][0];
    $from = $message['from']; // Número que envía el mensaje
    $text = strtolower(trim($message['text']['body']));
    
    // Buscar si es una confirmación
    if (preg_match('/^(si|sí|yes|ok|listo|tomado)$/', $text)) {
        
        // Buscar el recordatorio pendiente más reciente para este número
        $sql = "
            SELECT rw.id_recordatorio, rw.id_horario, u.nombre as paciente
            FROM recordatorios_whatsapp rw
            INNER JOIN usuarios u ON rw.id_usuario = u.id_usuario
            WHERE u.telefono = ? 
            AND rw.estado = 'enviado'
            AND DATE(rw.fecha_envio) = CURDATE()
            ORDER BY rw.fecha_envio DESC 
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$from]);
        $row = $stmt->fetch();
        
        if ($row) {
            try {
                // 1. Actualizar recordatorios_whatsapp
                $update = $pdo->prepare("
                    UPDATE recordatorios_whatsapp 
                    SET estado = 'confirmado' 
                    WHERE id_recordatorio = ?
                ");
                $update->execute([$row['id_recordatorio']]);
                
                // 2. Actualizar historial_tomas a 'tomado'
                $update_toma = $pdo->prepare("
                    UPDATE historial_tomas 
                    SET estado = 'tomado', fecha_hora_toma = NOW() 
                    WHERE id_horario = ? 
                    AND DATE(fecha_hora_toma) = CURDATE()
                    AND estado = 'omitido'
                    ORDER BY id_registro DESC 
                    LIMIT 1
                ");
                $update_toma->execute([$row['id_horario']]);
                
                // 3. Enviar mensaje de agradecimiento
                enviarMensajeAgradecimiento($from, $row['paciente']);
                
                file_put_contents("webhook.log", date("Y-m-d H:i:s") . " - ✅ Confirmación procesada: " . $row['id_horario'] . " - Paciente: " . $row['paciente'] . "\n", FILE_APPEND);
                
            } catch (Exception $e) {
                file_put_contents("webhook.log", date("Y-m-d H:i:s") . " - ❌ Error procesando confirmación: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        } else {
            file_put_contents("webhook.log", date("Y-m-d H:i:s") . " - ⚠️ No se encontró recordatorio pendiente para: $from\n", FILE_APPEND);
        }
    } else {
        file_put_contents("webhook.log", date("Y-m-d H:i:s") . " - ❓ Mensaje no reconocido de $from: $text\n", FILE_APPEND);
    }
}

function enviarMensajeAgradecimiento($numero, $paciente) {
    // Configuración de WhatsApp - ACTUALIZAR CON TUS DATOS REALES
    $token = "TU_TOKEN_DE_ACCESO_REAL";
    $phone_id = "TU_PHONE_NUMBER_ID_REAL";
    
    $mensaje = "✅ Gracias {$paciente} por confirmar que tomó su medicamento. ¡Que tenga un buen día!";
    
    $url = "https://graph.facebook.com/v20.0/$phone_id/messages";
    $data = [
        "messaging_product" => "whatsapp",
        "to" => $numero,
        "type" => "text",
        "text" => ["body" => $mensaje]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        file_put_contents("webhook.log", date("Y-m-d H:i:s") . " - ✅ Mensaje de agradecimiento enviado a: $numero\n", FILE_APPEND);
    } else {
        file_put_contents("webhook.log", date("Y-m-d H:i:s") . " - ❌ Error $http_code enviando agradecimiento a: $numero\n", FILE_APPEND);
    }
}

// Respuesta obligatoria para Meta
http_response_code(200);
echo json_encode(['status' => 'success']);
?>