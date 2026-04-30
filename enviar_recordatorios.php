<?php
require_once "config.php";
date_default_timezone_set('America/Mexico_City');

// Configuración de WhatsApp desde variables de entorno
$token = getenv('WHATSAPP_TOKEN') ?: "TU_TOKEN_DE_ACCESO_REAL";
$phone_id = getenv('WHATSAPP_PHONE_ID') ?: "TU_PHONE_NUMBER_ID_REAL";
$log_file = "cron.log";

// Resto del archivo SE MANTIENE IGUAL
// Solo cambiaste las 3 líneas de arriba


try {
    // Consulta optimizada usando PDO (compatible con tu config.php)
    $hora_actual = date("H:i");
    
    $sql = "
        SELECT h.id_horario, h.hora, m.nombre_medicamento, m.dosis, m.instrucciones,
               u.id_usuario, u.nombre, u.telefono
        FROM horarios h
        INNER JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
        INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
        WHERE h.activo = 1 
        AND TIME(h.hora) = ?
        AND NOT EXISTS (
            SELECT 1 FROM recordatorios_whatsapp rw 
            WHERE rw.id_horario = h.id_horario 
            AND DATE(rw.fecha_envio) = CURDATE()
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hora_actual]);
    $recordatorios = $stmt->fetchAll();

    $enviados = 0;

    foreach ($recordatorios as $row) {
        // Formatear número
        $telefono_formateado = formatearNumero($row['telefono']);
        
        // Crear mensaje
        $mensaje = "MediRecord: RECORDATORIO\n" .
                   "Hola {$row['nombre']}, es hora de tu medicamento.\n\n" .
                   "💊 Medicamento: {$row['nombre_medicamento']}\n" .
                   "📦 Dosis: {$row['dosis']}\n" .
                   "📝 Instrucciones: {$row['instrucciones']}\n" .
                   "🕒 Hora programada: {$row['hora']}\n\n" .
                   "Por favor, responda *SI* cuando haya tomado su medicamento.";

        $token_confirmacion = bin2hex(random_bytes(16));

        // Registrar en recordatorios_whatsapp
        $insert = $pdo->prepare("
            INSERT INTO recordatorios_whatsapp 
            (id_horario, id_usuario, mensaje, token_confirmacion, fecha_envio, estado) 
            VALUES (?, ?, ?, ?, NOW(), 'enviado')
        ");
        
        if ($insert->execute([$row['id_horario'], $row['id_usuario'], $mensaje, $token_confirmacion])) {
            // Enviar WhatsApp
            if (enviarWhatsApp($telefono_formateado, $mensaje, $token, $phone_id)) {
                $enviados++;
                
                // Registrar estado inicial en historial_tomas
                $insert_toma = $pdo->prepare("
                    INSERT INTO historial_tomas 
                    (id_horario, fecha_hora_toma, estado) 
                    VALUES (?, NOW(), 'omitido')
                ");
                $insert_toma->execute([$row['id_horario']]);
                
                file_put_contents($log_file, "[".date("Y-m-d H:i:s")."] ✅ Enviado a: {$telefono_formateado}\n", FILE_APPEND);
            } else {
                file_put_contents($log_file, "[".date("Y-m-d H:i:s")."] ❌ Error enviando a: {$telefono_formateado}\n", FILE_APPEND);
            }
        }
    }

    if ($enviados > 0) {
        file_put_contents($log_file, "[".date("Y-m-d H:i:s")."] 📊 RESUMEN: $enviados recordatorios enviados\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "[".date("Y-m-d H:i:s")."] 🔍 No hay recordatorios para enviar a las $hora_actual\n", FILE_APPEND);
    }

} catch (Exception $e) {
    file_put_contents($log_file, "[".date("Y-m-d H:i:s")."] ❌ ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

function enviarWhatsApp($numero, $mensaje, $token, $phone_id) {
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
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log detallado de la respuesta
    $log_file = "cron.log";
    if ($http_code === 200) {
        file_put_contents($log_file, "[".date("Y-m-d H:i:s")."] ✅ WhatsApp enviado correctamente a $numero\n", FILE_APPEND);
        return true;
    } else {
        file_put_contents($log_file, "[".date("Y-m-d H:i:s")."] ❌ Error $http_code enviando WhatsApp a $numero: $response\n", FILE_APPEND);
        return false;
    }
}

function formatearNumero($numero) {
    $numero = preg_replace('/\D/', '', $numero);
    
    if (substr($numero, 0, 1) === '0') {
        $numero = '52' . substr($numero, 1);
    }
    
    if (substr($numero, 0, 2) !== '52') {
        $numero = '52' . $numero;
    }
    
    return $numero;
}
?>