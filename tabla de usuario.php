<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$conexion = new mysqli("localhost", "root", "", "ecomovi");
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}

// ======= EVITAR DUPLICADOS AL RECIBIR INFORMACIÓN =======
// Remove the entire POST handling section:
// From:
// Modify the verification query to check for canjeos within the last 12 hours
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $placa = $_POST["devolver_plac_veh"] ?? $_POST["redimir_plac_veh"];
    $puntos = $_POST["devolver_puntos"] ?? $_POST["redimir_puntos"];
    $accion = $_POST["accion"];
    
    // Check for redemptions in the last 12 hours
    $consulta_existente = $conexion->prepare("
        SELECT * FROM canjeos 
        WHERE plac_veh = ? 
        AND fecha >= DATE_SUB(NOW(), INTERVAL 12 HOUR) 
        LIMIT 1
    ");
    $consulta_existente->bind_param("s", $placa);
    $consulta_existente->execute();
    $resultado_existente = $consulta_existente->get_result();
    
    if ($resultado_existente->num_rows > 0) {
        echo "Debes esperar 12 horas desde tu última recompensa para poder canjear otra.";
        exit();
    }
    $consulta_existente->close();

    // Verificar si ya existe un canjeo registrado para esta placa
    $consulta_existente = $conexion->prepare("SELECT * FROM canjeos WHERE plac_veh = ? LIMIT 1");
    $consulta_existente->bind_param("s", $placa);
    $consulta_existente->execute();
    $resultado_existente = $consulta_existente->get_result();
    
    if ($resultado_existente->num_rows > 0) {
        // Si ya existe un canjeo registrado, mostrar un mensaje y no hacer nada
        echo "Este vehículo ya tiene un canjeo registrado.";
        exit(); // Detener el proceso si el vehículo ya tiene un canjeo.
    }
    $consulta_existente->close();

    // Proceder según la acción seleccionada
    if ($accion === "devolver" || $accion === "redimir") {
        // Verificar si el vehículo existe en la tabla de vehículos
        $consulta = $conexion->prepare("SELECT plac_veh FROM vehiculos WHERE plac_veh = ?");
        $consulta->bind_param("s", $placa);
        $consulta->execute();
        $consulta->bind_result($placa_veh);
        $consulta->fetch();
        $consulta->close();

        if ($accion === "devolver") {
            $stmt = $conexion->prepare("UPDATE vehiculos SET puntos_totales = puntos_totales + ? WHERE plac_veh = ?");
        } elseif ($accion === "redimir") {
            $stmt = $conexion->prepare("UPDATE vehiculos SET puntos_totales = puntos_totales - ? WHERE plac_veh = ?");
        }

        if ($stmt) {
            $stmt->bind_param("is", $puntos, $placa);
            if ($stmt->execute()) {
                // Eliminar el registro de canjeo después de realizar la acción
                $conexion->query("DELETE FROM canjeos WHERE plac_veh = '$placa' LIMIT 1");

                // Enviar correo con la información sobre la acción realizada
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'sebastianleong15@gmail.com';
                    $mail->Password = 'qcsd lfqg cpbg oxud';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;

                    $mail->setFrom('sebastianleon123@outlook.com', 'EcoMovi');
                    $mail->addAddress('sebastianleong15@gmail.com', 'Usuario');

                    $mail->isHTML(true);
                    $mail->Subject = ($accion === 'devolver') ? 'Puntos regresados' : 'Recompensa redimida';

                    $mensaje = ($accion === 'devolver') 
                        ? "Los puntos del vehículo con la placa <strong style='color:#e67e22;'>$placa</strong> han sido <strong>regresados correctamente</strong> a tu cuenta."
                        : "Has redimido exitosamente tu recompensa. El vehículo con la placa <strong style='color:#27ae60;'>$placa</strong> ha utilizado sus puntos.";

                    $mail->Body = "<div style='background-color:#ffffff; padding:40px; font-family:Arial,sans-serif; color:#2c3e50; max-width:600px; margin:auto; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.1);'>
                        <div style='text-align:center;'>
                            <img src='https://i.ibb.co/SXzV5TBX/logo-blanco.png' alt='EcoMovi Logo' style='width:140px; margin-bottom:20px;'>
                            <h2 style='color:#27ae60; margin-bottom:10px;'>🌿 Notificación de EcoMovi</h2>
                            <p style='font-size:17px; color:#444; line-height:1.6;'>$mensaje</p>
                            <p style='font-size:15px; color:#555; margin-top:30px;'>Gracias por confiar en nosotros y ser parte de una movilidad más ecológica.</p>
                            <hr style='margin:40px 0; border:none; border-top:1px solid #eee;'>
                            <div style='text-align:center; margin-top:20px;'>
                                <p style='font-size:14px; color:#888;'>Síguenos en nuestras redes sociales</p>
                                <a href='https://facebook.com' style='margin:0 10px;'><img src='https://cdn-icons-png.flaticon.com/24/145/145802.png'></a>
                                <a href='https://instagram.com' style='margin:0 10px;'><img src='https://cdn-icons-png.flaticon.com/24/2111/2111463.png'></a>
                                <a href='https://wa.me/573001234567' style='margin:0 10px;'><img src='https://cdn-icons-png.flaticon.com/24/733/733585.png'></a>
                            </div>
                            <p style='text-align:center; font-size:12px; color:#aaa; margin-top:30px;'>© 2025 EcoMovi - Todos los derechos reservados</p>
                        </div>
                    </div>";
                    $mail->AltBody = strip_tags($mensaje);
                    $mail->send();
                } catch (Exception $e) {
                    echo "Error al enviar el correo: {$mail->ErrorInfo}";
                }
            }
            $stmt->close();
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ======= CONSULTA PARA MOSTRAR CANJEOS =======
$query = "
    SELECT c.fecha, v.plac_veh, r.nom_reco AS recompensa, r.puntos
    FROM canjeos c
    JOIN vehiculos v ON c.plac_veh = v.plac_veh
    JOIN recompensa r ON c.nom_reco = r.nom_reco
";

// Modified search condition to only search in plac_veh
if (isset($_GET['search_placa']) && !empty($_GET['search_placa'])) {
    $search_placa = $conexion->real_escape_string($_GET['search_placa']);
    $query .= " WHERE v.plac_veh LIKE '%$search_placa%'";
}

$query .= " ORDER BY c.fecha DESC";
$resultado = $conexion->query($query);

if (!$resultado) {
    die("Error en la consulta: " . $conexion->error);
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ECOMOVI - Tabla de Canjeos</title>
    <link rel="stylesheet" href="css/tabla-usuario.css">
    <link rel="icon" href="logo blanco.png" type="image/png">
</head>
<body>
<header>
    <nav>
        <h2 class="titulo-recompensas">TABLA DE RECOMPENSAS</h2>
        <a href="paginaadministrador.html">Inicio</a>
    </nav>
</header>

<div class="container">
    <form method="GET" class="search-form" style="margin-bottom: 20px;">
        <input type="text" name="search_placa" placeholder="Buscar por placa..." 
               style="padding: 8px; width: 200px; margin-right: 10px;">
        <button type="submit" style="padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px;">
            Buscar
        </button>
        <?php if (isset($_GET['search_placa']) && $resultado->num_rows === 0): ?>
            <span style="color: #dc3545; margin-left: 15px;">No se encontraron resultados para la placa "<?php echo htmlspecialchars($_GET['search_placa']); ?>"</span>
        <?php endif; ?>
    </form>

    <table border="1">
        <thead>
            <tr>
                <th>Placa</th>
                <th>Recompensa</th>
                <th>Puntos</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($resultado->num_rows > 0) {
                while ($fila = $resultado->fetch_assoc()) {
                    echo "<tr>
                        <td>" . htmlspecialchars($fila['plac_veh']) . "</td>
                        <td>" . htmlspecialchars($fila['recompensa']) . "</td>
                        <td>" . $fila['puntos'] . "</td>
                    </tr>";
                }
            }
            ?>
        </tbody>
    </table>

</div>
<script src="js/tabla-usuario.js"></script>
</body>
</html>
