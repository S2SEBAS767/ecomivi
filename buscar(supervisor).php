<!DOCTYPE html> 
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ECOMOVI - BUSCAR USUARIO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo(buscar).css">
   <script>
        function abrirModal(id) {
            document.getElementById(id).style.display = 'block';
        }

        function cerrarModal(id) {
            document.getElementById(id).style.display = 'none';
        }
    </script>
</head>
<link rel="icon" href="logo blanco.png" type="icon">

<body>
    <header>
        <nav>
            <img src="logo blanco.png" alt="Título" width="300px">
            <h1>BUSQUEDA DE USUARIO</h1>
            <ul>
                <li><a href="supervisor(PRINCIPAL).html">INICIO</a></li>
            </ul>
        </nav>
    </header>

    <div>
        <form method="POST" action="" id="search-form">
            <input type="text" name="identificacion" id="search-input" 
                   placeholder="Escribe el número de Identificación..." required
                   value="<?php echo isset($_POST['identificacion']) ? $_POST['identificacion'] : ''; ?>">
            <!-- Add a search button -->
            <button type="submit" class="search-button">Buscar</button>
        </form>
    </div>
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["identificacion"])) {
        $servidor = "localhost";
        $usuario = "root";
        $contrasena = "";
        $baseDatos = "ecomovi";

        $conn = new mysqli($servidor, $usuario, $contrasena, $baseDatos);

        if ($conn->connect_error) {
            die("<p>Error de conexión: " . $conn->connect_error . "</p>");
        }

        $identificacion = $conn->real_escape_string($_POST["identificacion"]);

        $sql = "SELECT nom_usu, apell_usu, num_doc_usu FROM usuarios WHERE num_doc_usu = '$identificacion'";
        $resultado = $conn->query($sql);

        if ($resultado->num_rows > 0) {
            $usuario = $resultado->fetch_assoc();

            echo "<div class='usuario-info'>";
            echo "<h2>Nombre del usuario: " . $usuario['nom_usu'] . " " . $usuario['apell_usu'] . "</h2>";
            echo "<p>CC: " . $usuario['num_doc_usu'] . "</p>";
            echo "<button class='btn-ver-vehiculos' onclick=\"abrirModal('vehiculoModal')\">Ver Vehículos</button>";
            echo "</div>";
         
            $sqlVehiculos = "SELECT plac_veh, mar_veh, model_veh, foto_soat, foto_tecno FROM vehiculos";
$resultVehiculos = $conn->query($sqlVehiculos);


        } else {
            echo "<p class='usuario-no-registrado'>⚠ EL USUARIO NO ESTÁ REGISTRADO</p>";
        }
    }
    ?>
<div id="vehiculoModal" class="modal" style="display: none;">
    <div class="modal-content">
        <!-- X de cierre -->
        <span class="close-modal" onclick="cerrarModal('vehiculoModal')">&times;</span>
        <h2>Vehículos Registrados</h2>
        <?php
        if (isset($resultVehiculos) && $resultVehiculos->num_rows > 0) {
            $cantidadVehiculos = $resultVehiculos->num_rows;
            echo "<div class='vehiculo-container'>";
            
            while ($vehiculo = $resultVehiculos->fetch_assoc()) {
                echo "<div class='vehiculo-card'>";
                echo "<h3>Placa: " . $vehiculo['plac_veh'] . "</h3>";
                echo "<p><strong>Marca:</strong> " . $vehiculo['mar_veh'] . "</p>";
                echo "<p><strong>Modelo:</strong> " . $vehiculo['model_veh'] . "</p>";
                
                echo "<button class='btn-documentos' onclick=\"abrirModal('docModal_" . $vehiculo['plac_veh'] . "')\">Documentos</button>";
                echo "<button class='btn-documentos' onclick=\"abrirModal('kmModal_" . $vehiculo['plac_veh'] . "')\">Ver Kilometraje</button>";

                echo "</div>";
            }
            echo "<p class='notificacion-verde'><strong>$cantidadVehiculos</strong> vehículo(s) están registrados</p>";
            echo "</div>";
        } else {
            echo "<p>No hay vehículos registrados.</p>";
        }
        ?>
    </div>
</div>

    <?php
    if (isset($resultVehiculos) && $resultVehiculos->num_rows > 0) {
        $resultVehiculos->data_seek(0);
        while ($vehiculo = $resultVehiculos->fetch_assoc()) {
            echo "<div id='docModal_" . $vehiculo['plac_veh'] . "' class='content_documentos' style='display: none;'>";
            echo "<div class='modal-documentos'>";
            echo "<span class='close' onclick=\"cerrarModal('docModal_" . $vehiculo['plac_veh'] . "')\">&times;</span>";
            echo "<h2 class=''>Documentos de " . $vehiculo['plac_veh'] . "</h2>";

            echo "<div class='documentos-container'>";

            // Mostrar la imagen del SOAT si está disponible
            echo "<div class='documento-item'><p>SOAT:</p>";
            if (isset($vehiculo['foto_soat']) && !empty($vehiculo['foto_soat'])) {
                echo "<img src='" . $vehiculo['foto_soat'] . "' alt='SOAT' width='200px'>";
            } else {
                echo "<p class='not-available'>⚠️ SOAT no disponible</p>";
            }
            echo "</div>";

            echo "<div class='documento-item'><p>TECNOMECANICA:</p>";
            if (isset($vehiculo['foto_tecno']) && !empty($vehiculo['foto_tecno'])) {
                echo "<img src='" . $vehiculo['foto_tecno'] . "' alt='TECNOMECANICA' width='200px'>";
            } else {
                echo "<p class='not-available'>⚠️ TECNOMECANICA no disponible</p>";
            }
            echo "</div>";
         

            echo "</div>"; // Cierra el contenedor de documentos

            echo "</div></div>"; // Cierra el modal
        }
    }
?>
<?php
if (isset($resultVehiculos)) {
    $resultVehiculos->data_seek(0);

    while ($vehiculo = $resultVehiculos->fetch_assoc()) {
        $placa = $vehiculo['plac_veh'];

        // Consulta historial de movilidad
        $sqlMovilidad = "SELECT fecha_inicial, hora_inicial, fecha_final, hora_final, foto_inicial, foto_final 
                         FROM movilidad 
                         WHERE plac_veh = '$placa' 
                         ORDER BY fecha_inicial DESC";
        $resultMovilidad = $conn->query($sqlMovilidad);

        // Modal de kilometraje
        echo "<div id='kmModal_$placa' class='modal-km-container' style='display: none;'>
        <div class='modal-km-content'>
            <span class='close' onclick=\"cerrarModal('kmModal_$placa')\">&times;</span>
            <h2 class='modal-km-title'>🚗Historial de Kilometraje - Vehículo $placa</h2>";

        if ($resultMovilidad && $resultMovilidad->num_rows > 0) {
            echo "<div class='movilidad-registros'>";
            while ($mov = $resultMovilidad->fetch_assoc()) {
                echo "<div class='registro-movilidad'>";
                echo "<p><strong>📅 Fecha:</strong> {$mov['fecha_inicial']}</p>";

                echo "<div class='registro-fotos'>";

                // Foto inicial
                echo "<div class='foto-bloque'>
                        <p><strong>🕒 Hora Inicial:</strong> {$mov['hora_inicial']}</p>";
                if (!empty($mov['foto_inicial'])) {
                    // Asegurarse de que la ruta de la imagen sea correcta
                    $foto_inicial = 'uploads/' . basename($mov['foto_inicial']);
                    echo "<a href='$foto_inicial' target='_blank'>
                            <img src='$foto_inicial' alt='Foto Inicial' width='200'>
                          </a>";
                } else {
                    echo "<p>Sin foto inicial</p>";
                }
                echo "</div>";

                // Foto final
                echo "<div class='foto-bloque'>
                        <p><strong>🕓 Hora Final:</strong> {$mov['hora_final']}</p>";
                if (!empty($mov['foto_final'])) {
                    // Asegurarse de que la ruta de la imagen sea correcta
                    $foto_final = 'uploads/' . basename($mov['foto_final']);
                    echo "<a href='$foto_final' target='_blank'>
                            <img src='$foto_final' alt='Foto Final' width='200'>
                          </a>";
                } else {
                    echo "<p>Sin foto final</p>";
                }
                echo "</div>";

                echo "</div>"; // cierre registro-fotos
                echo "</div>"; // cierre registro-movilidad
            }
            echo "</div>";
        } else {
            echo "<p>No hay registros de kilometraje para este vehículo.</p>";
        }

        echo "</div></div>"; // Cierre modal
    }
}
?>

</body>
</html>