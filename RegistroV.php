<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$conn = new mysqli("localhost", "root", "", "ecomovi");
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

session_start();
if (!isset($_SESSION['num_doc_usu'])) {
    header('Location: login.php');
    exit();
}

$num_doc_usu = $_SESSION['num_doc_usu'];

$resultVehiculos = $conn->query("SELECT * FROM vehiculos WHERE num_doc_usu = '$num_doc_usu'");

$resultPuntos = $conn->query("
    SELECT plac_veh, COALESCE(SUM(puntos), 0) as total_puntos 
    FROM movilidad 
    WHERE fecha_final IS NOT NULL 
    GROUP BY plac_veh
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehiculo_id'], $_POST['recompensa_id'])) {
    $vehiculosId = $_POST['vehiculo_id'];
    $recompensaId = $_POST['recompensa_id'];

    $stmtPuntos = $conn->prepare("
        SELECT COALESCE(SUM(puntos), 0) as puntos_totales 
        FROM movilidad 
        WHERE plac_veh = ? AND fecha_final IS NOT NULL
    ");
    $stmtPuntos->bind_param("s", $vehiculosId);
    $stmtPuntos->execute();
    $resultadoPuntos = $stmtPuntos->get_result()->fetch_assoc();
    $puntosTotales = $resultadoPuntos['puntos_totales'];

    $stmtRecompensa = $conn->prepare("SELECT puntos, disponible, nom_reco FROM recompensa WHERE nom_reco = ?");
    $stmtRecompensa->bind_param("s", $recompensaId);
    $stmtRecompensa->execute();
    $recompensa = $stmtRecompensa->get_result()->fetch_assoc();

    if ($recompensa && $puntosTotales >= $recompensa['puntos'] && $recompensa['disponible'] > 0) {
        $puntosRestantes = $recompensa['puntos'];

        $stmtGetRecords = $conn->prepare("
            SELECT id_mov, puntos 
            FROM movilidad 
            WHERE plac_veh = ? 
            AND fecha_final IS NOT NULL 
            AND puntos > 0 
            ORDER BY fecha_final DESC
        ");
        $stmtGetRecords->bind_param("s", $vehiculosId);
        $stmtGetRecords->execute();
        $result = $stmtGetRecords->get_result();

        while (($row = $result->fetch_assoc()) && $puntosRestantes > 0) {
            $puntosADescontar = min($row['puntos'], $puntosRestantes);
            $nuevosPuntos = $row['puntos'] - $puntosADescontar;

            $stmtUpdatePuntos = $conn->prepare("UPDATE movilidad SET puntos = ? WHERE id_mov = ?");
            $stmtUpdatePuntos->bind_param("ii", $nuevosPuntos, $row['id_mov']);
            $stmtUpdatePuntos->execute();

            $puntosRestantes -= $puntosADescontar;
        }

        $stmtUpdateRecompensa = $conn->prepare("UPDATE recompensa SET disponible = disponible - 1 WHERE nom_reco = ?");
        $stmtUpdateRecompensa->bind_param("s", $recompensaId);
        $stmtUpdateRecompensa->execute();

        $stmtInsert = $conn->prepare("INSERT INTO canjeos (plac_veh, nom_reco, fecha) VALUES (?, ?, NOW())");
        $stmtInsert->bind_param("ss", $vehiculosId, $recompensaId);
        $stmtInsert->execute();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ingkevinrivera25@gmail.com';
            $mail->Password = 'wttq tooj egmv ergj';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('ingkevinrivera25@gmail.com', 'EcoMovi');
            $mail->addAddress('ingkevinrivera25@gmail.com', 'Usuario');
            $mail->isHTML(true);
            $mail->Subject = '🎉 Redención de puntos confirmada - EcoMovi';
            $mail->Body = '<p>¡Has redimido tu recompensa <b>' . htmlspecialchars($recompensaId) . '</b> exitosamente!</p>';

            $mail->send();
        } catch (Exception $e) {
            $mensajeRedencion .= "\n(No se pudo enviar correo: {$mail->ErrorInfo})";
        }

        $mensajeRedencion = "¡Recompensa redimida con éxito! Código enviado al correo.";
    } else {
        $mensajeRedencion = "No tienes suficientes puntos o la recompensa ya no está disponible.";
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensajeRedencion));
    exit();
}

$resultRecompensas = $conn->query("SELECT * FROM recompensa");
$recompensas = [];
while ($row = $resultRecompensas->fetch_assoc()) {
    $recompensas[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EcoMovi - Redimir recompensa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        p {
            color: black;
        }

        h5 {
            color: black;
        }

        .text-white {
            color: white;
        }

        .botoncito{
            position: fixed;
            left: 20px;
            top: 20px;
            z-index: 1000;
            padding: 15px 25px;
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            border: none;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-weight: bold;
            font-size: 16px;
            text-decoration: none;
        }

        .botoncito:hover {
            transform: translateY(-3px);
            color: white;
        }

        .card .btn {
            padding: 10px 20px;
            margin: 5px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            width: 80%;
            display: block;
            margin: 10px auto;
        }

        .btn-success {
        background: linear-gradient(45deg, #2ecc71, #27ae60);
        border: none;
        box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .btn-info {
        background: linear-gradient(45deg, #3498db, #2980b9);
        border: none;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        color: white !important;
        }

        .btn-warning {
        background: linear-gradient(45deg, #f1c40f, #f39c12);
        border: none;
        box-shadow: 0 4px 15px rgba(241, 196, 15, 0.3);
        color: white !important;
        }
        
        .btn-delete{
           background: linear-gradient(45deg,rgba(241, 30, 15, 0.93),rgb(243, 70, 18));
        border: none;
        box-shadow: 0 4px 15px rgba(241, 49, 15, 0.3);
        color: white !important;
        }

        .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .card {
    border-radius: 20px;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    border: none;
    padding: 25px 15px;
    background:  #e6f3d9a1 !important;
    transition: all 0.3s ease;
    min-height: 320px;
    display: flex;
    flex-direction: column;
}


        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .card-info {
            margin-bottom: 20px;
          
        }

        .buttons-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
        }

        .card .btn {
            padding: 12px 20px;
            margin: 0;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn i {
            font-size: 1.1em;
        }
    .list-group-item {
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 10px !important;
        border: 1px   ;
        background: rgba(255, 255, 255, 0.274);
        transition: all 0.3s ease;
    }
    .list-group-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .list-group-item img {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .list-group-item .btn {
        padding: 5px 15px;
        border-radius: 20px;
    }
    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
@keyframes brillo {
            0% {
                box-shadow: 0 0 15px 5px #00ff00;
            }

            50% {
                box-shadow: 0 0 35px 15px #00ff00;
            }

            100% {
                box-shadow: 0 0 15px 5px #00ff00;
            }
        }

        .borde-verde {
            border: 3.5px solid #00ff00;
            box-shadow: 0 0 25px 10px #00ff00;
            animation: brillo 1s infinite ease-in-out;
        }

    </style>
</head>
<body>

<a href="iniusu.html" class="botoncito"><i class="fas fa-arrow-left"></i> Regresar</a>
<div class="container mt-5">
    <link rel="stylesheet" href="estilousuario.css">
    <link rel="icon" href="logo blanco.png" type="image/png">
<?php if (isset($_GET['mensaje'])): ?>
    <script>alert("<?= htmlspecialchars($_GET['mensaje']) ?>");</script>
<?php endif; ?>

<div class="container mt-5">
    <h1 class="text-center">Vehículos Registrados</h1>
    <div class="row">
        <?php foreach ($resultVehiculos as $vehiculo): 
            $puntos = 0;
            if ($resultPuntos) {
                $resultPuntos->data_seek(0);
                while ($row = $resultPuntos->fetch_assoc()) {
                    if ($row['plac_veh'] === $vehiculo['plac_veh']) {
                        $puntos = $row['total_puntos'];
                        break;
                    }
                }
            }
        ?>
        <div class="col-md-4 mb-3">
            <div class="card text-center">
                <div class="card-body">
                   <b> <h4 class="card-title"><?= htmlspecialchars($vehiculo['mar_veh']) ?></h></b>
                     <b><p>
                                    Placa : <?= htmlspecialchars($vehiculo['plac_veh']) ?><br>
                                    Tipo : <?= htmlspecialchars($vehiculo['tip_veh']) ?><br>
                                    Puntos Totales : <?= $puntos ?>
                     </p></b>

                    <div class="buttons-container">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#rewardsModal"
                                onclick="showRecompensas('<?= $vehiculo['plac_veh'] ?>')">
                            <i class="fas fa-gift"></i> Recompensa
                        </button>
                        <a href="seguimiento de movilidad.php?id=<?= $vehiculo['plac_veh'] ?>" class="btn btn-info">
                            <i class="fas fa-route"></i> Registrar Movilidad
                        </a>
                        <a href="continuar_formulario.php?plac_veh=<?= $vehiculo['plac_veh'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Completar registro

                       <a href="eliminar_veh.php?plac_veh=<?= $vehiculo['plac_veh'] ?>" 
                            class="btn btn-delete"
                            onclick="return confirm('¿Estás seguro de que deseas eliminar este vehículo?');">
                            <i class="fas fa-edit"></i> Eliminar vehículo
                            </a>

                        
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="rewardsModal" tabindex="-1" aria-labelledby="rewardsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="">
        <div class="modal-header">
          <h5 class="modal-title" id="rewardsModalLabel">Selecciona una recompensa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="vehiculo_id" id="vehiculoId">
          <div class="list-group">
          <?php foreach ($recompensas as $recompensa): ?>
                    <label class="list-group-item d-flex justify-content-between align-items-center">
                      <div class="d-flex align-items-center">
                         <img src="<?= htmlspecialchars($recompensa['imagen_url'] ?: 'placeholder.jpg') ?>"
                          alt="Imagen Recompensa"
                          style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                      <div>
                         <?= htmlspecialchars($recompensa['nom_reco']) ?> - <b>Puntos: <?= $recompensa['puntos'] ?></b>
                      </div>
                      </div>    
                        <input type="radio" name="recompensa_id" value="<?= htmlspecialchars($recompensa['nom_reco']) ?>" required>
                    </label>
             <?php endforeach;?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Canjear</button>
        </div>
        

      </form>
    </div>
  </div>
</div>

<script>
function showRecompensas(placa) {
    document.getElementById('vehiculoId').value = placa;
}
</script>

<style>
    .modal-content {
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .modal-header {
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        background: linear-gradient(45deg, #4CAF50, #45a049);
    }

    .list-group-item {
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 10px !important;
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
    }

    .list-group-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .list-group-item img {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .list-group-item .btn {
        padding: 5px 15px;
        border-radius: 20px;
    }

    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>