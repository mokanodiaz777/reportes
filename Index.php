<?php

// Encabezados de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:;");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
header("Expect-CT: enforce, max-age=86400, report-uri='https://yourdomain.com/report'");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once('tcpdf/tcpdf.php');

function getDatabaseConnection()
{
    $servername = "localhost";
    $username = "u664402082_reportes_new";
    $password = "9a$*exgnJ'Ddn8]";
    $dbname = "u664402082_reportes_new";
    $attempts = 10;
    $conn = null;

    while ($attempts > 0) {
        try {
            $conn = new mysqli($servername, $username, $password, $dbname);

            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            } else {
                return $conn;
            }
        } catch (Exception $e) {
            error_log("Connection attempt failed. Attempts remaining: " . $attempts . ". Error: " . $e->getMessage());
            $attempts--;
            sleep(2);
        }
    }

    if ($conn === null || $conn->connect_error) {
        die("Conexión fallida después de varios intentos: " . ($conn ? $conn->connect_error : "No se pudo establecer una conexión"));
    }
}

$conn = getDatabaseConnection();


function checkAndReconnect(&$conn)
{
    if ($conn->ping() === false || $conn->connect_error) {
        error_log("Reconectando a la base de datos...");
        $conn->close();

        $servername = "localhost";
        $username = "u664402082_reportes_new";
        $password = "9a$*exgnJ'Ddn8]";
        $dbname = "u664402082_reportes_new";

        $attempts = 10;
        while ($attempts > 0) {
            try {
                $conn = new mysqli($servername, $username, $password, $dbname);

                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                } else {
                    return;
                }
            } catch (Exception $e) {
                error_log("Reconnection attempt failed. Attempts remaining: " . $attempts . ". Error: " . $e->getMessage());
                $attempts--;
                sleep(2);
            }
        }

        if ($conn === null || $conn->connect_error) {
            throw new Exception("Conexión fallida después de varios intentos: " . ($conn ? $conn->connect_error : "No se pudo establecer una conexión"));
        }
    }
}

// Configuraciones de PHP
ini_set('memory_limit', '2048M'); // O el tamaño que consideres necesario
ini_set('max_execution_time', 900); // O el tiempo que consideres necesario en segundos

// Asegurarse de que el directorio de subidas exista
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}



function compressImage($source, $destination, $quality = 80, $maxWidth = 800, $maxHeight = 600)
{
    $info = getimagesize($source);

    if ($info === false) return false; // Archivo no es una imagen válida

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false; // Formato no soportado
    }

    // Obtener dimensiones originales
    $origWidth = imagesx($image);
    $origHeight = imagesy($image);

    // Calcular nuevas dimensiones manteniendo la proporción
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1); // No aumentar el tamaño

    $newWidth = (int)($origWidth * $ratio);
    $newHeight = (int)($origHeight * $ratio);

    // Crear una nueva imagen con las dimensiones calculadas
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Manejar transparencias para PNG y GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }

    // Redimensionar la imagen
    imagecopyresampled(
        $newImage,
        $image,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $origWidth,
        $origHeight
    );

    // Guardar la imagen optimizada
    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($newImage, $destination, $quality);
            break;
        case 'image/png':
            // La calidad en PNG va de 0 (sin compresión) a 9
            $pngQuality = round((80 - $quality) / 5);
            $result = imagepng($newImage, $destination, $pngQuality);
            break;
        case 'image/gif':
            $result = imagegif($newImage, $destination);
            break;
        default:
            return false;
    }

    // Liberar memoria
    imagedestroy($image);
    imagedestroy($newImage);

    return $result;
}



// Guardar nueva dirección
if (isset($_POST['guardar_direccion'])) {
    $nueva_direccion = $_POST['nueva_direccion'];
    $sql = "INSERT INTO direcciones (direccion) VALUES (?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $nueva_direccion);

        // Llamar a checkAndReconnect antes de ejecutar la consulta
        checkAndReconnect($conn);

        if ($stmt->execute()) {
            echo json_encode(array('status' => 'success', 'direccion' => $nueva_direccion));
        } else {
            echo json_encode(array('status' => 'error', 'message' => $stmt->error));
        }
        $stmt->close();
    } else {
        echo json_encode(array('status' => 'error', 'message' => $conn->error));
    }
    exit();
}

// Consulta de direcciones
checkAndReconnect($conn);
$direcciones_result = $conn->query("SELECT * FROM direcciones");

$direcciones = [];
while ($row = $direcciones_result->fetch_assoc()) {
    $direcciones[] = $row['direccion'];
}


// Cargar reporte para editar
if (isset($_POST['cargar_reporte'])) {
    $id_reporte = $_POST['id_reporte'];
    $sql = "SELECT * FROM reportes_2024 WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id_reporte);
        $stmt->execute();
        $reporte = $stmt->get_result()->fetch_assoc();

        // Cargar las secciones del reporte desde la tabla 'secciones'
        $secciones_sql = "SELECT * FROM secciones WHERE reporte_id = ?";
        $stmt_secciones = $conn->prepare($secciones_sql);
        $stmt_secciones->bind_param("i", $id_reporte);
        $stmt_secciones->execute();
        $secciones = $stmt_secciones->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(array('status' => 'success', 'reporte' => $reporte, 'secciones' => $secciones));
        $stmt_secciones->close();
        $stmt->close();
    } else {
        echo json_encode(array('status' => 'error', 'message' => $conn->error));
    }
    exit();
}

checkAndReconnect($conn);

// Procesar la solicitud de carga de imagen
if (isset($_GET['action']) && $_GET['action'] == 'upload_image') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
        $file = $_FILES['image'];
        $filePath = 'uploads/' . basename($file['name']);

        // Asegurarse de que el directorio de subidas exista
        if (!is_dir('uploads/')) {
            mkdir('uploads/', 0755, true);
        }

        // Mover el archivo a la carpeta de subidas
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            checkAndReconnect($conn); // Asegurar la conexión antes de ejecutar la consulta
            $stmt = $conn->prepare("INSERT INTO imagenes (ruta, campo) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("ss", $filePath, $_POST['fieldName']);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'filePath' => $filePath]);
                } else {
                    echo json_encode(['success' => false, 'error' => $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al mover el archivo.']);
        }
    }
    exit;
}


// Guardar reporte
if (isset($_POST['guardar_reporte'])) {
    $nombre = $_POST['nombre'];
    $fecha = $_POST['fecha'];
    $id_reporte = $_POST['id_reporte']; // ID del reporte a editar, si está presente

    // Guardar datos de las secciones
    $id_secciones = $_POST['id_seccion']; // Nueva variable para los ids de las secciones
    $secciones = $_POST['seccion'];
    $descripciones = $_POST['descripcion'];
    $direcciones = $_POST['direccion'];

    // Definir el tamaño máximo permitido (5MB)
    $maxFileSize = 12 * 1920 * 1024; // 5MB en bytes

    // Comenzar transacción
    $conn->begin_transaction();

    try {
        // Si es un reporte nuevo, insertarlo
        if (!$id_reporte) {
            $insert_reporte_sql = "INSERT INTO reportes_2024 (nombre, fecha) VALUES (?, ?)";
            $stmt_reporte = $conn->prepare($insert_reporte_sql);
            $stmt_reporte->bind_param("ss", $nombre, $fecha);
            $stmt_reporte->execute();
            $id_reporte = $conn->insert_id;  // Obtener el ID del nuevo reporte
            $stmt_reporte->close();
        } else {
            // Actualizar el nombre y fecha del reporte existente si es necesario
            $update_reporte_sql = "UPDATE reportes_2024 SET nombre = ?, fecha = ? WHERE id = ?";
            $stmt_update_reporte = $conn->prepare($update_reporte_sql);
            $stmt_update_reporte->bind_param("ssi", $nombre, $fecha, $id_reporte);
            $stmt_update_reporte->execute();
            $stmt_update_reporte->close();
        }

        // Guardar secciones
        $total_secciones = count($secciones);
        $batchSize = 2; // Define el tamaño del lote
        $imagesToProcess = [];

        for ($i = 0; $i < $total_secciones; $i++) {
            $id_seccion = isset($id_secciones[$i]) ? $id_secciones[$i] : null; // ID de la sección
            $seccion = $secciones[$i];
            $descripcion = $descripciones[$i];
            $direccion = $direcciones[$i];

            // Inicializar las rutas de las fotos con valores existentes
            $foto1_path = '';
            $foto2_path = '';

            if ($id_seccion) {
                // Obtener las rutas actuales de las fotos desde la base de datos
                $select_fotos_sql = "SELECT foto1, foto2 FROM secciones WHERE id = ?";
                $stmt_select_fotos = $conn->prepare($select_fotos_sql);
                $stmt_select_fotos->bind_param("i", $id_seccion);
                $stmt_select_fotos->execute();
                $result_fotos = $stmt_select_fotos->get_result()->fetch_assoc();
                $foto1_path = $result_fotos['foto1'];
                $foto2_path = $result_fotos['foto2'];
                $stmt_select_fotos->close();
            }

            // Agregar las imágenes al array para procesar más tarde
            $imagesToProcess[] = [
                'id' => $id_seccion,
                'seccion' => $seccion,
                'descripcion' => $descripcion,
                'direccion' => $direccion,
                'foto1' => $_FILES['foto1']['name'][$i],
                'foto1_tmp' => $_FILES['foto1']['tmp_name'][$i],
                'foto2' => $_FILES['foto2']['name'][$i],
                'foto2_tmp' => $_FILES['foto2']['tmp_name'][$i],
                'foto1_path' => $foto1_path,
                'foto2_path' => $foto2_path,
                'index' => $i // Para llevar el seguimiento de la posición
            ];

            // Si alcanzamos el tamaño del lote o es la última sección, procesar las imágenes
            if (count($imagesToProcess) >= $batchSize || $i == $total_secciones - 1) {
                // Procesar imágenes
                foreach ($imagesToProcess as $imageData) {
                    // Procesar foto1
                    $foto1_path = handleImageUpload($imageData['foto1'], $imageData['foto1_tmp'], $imageData['foto1_path'], $imageData['seccion'], $maxFileSize);

                    // Procesar foto2
                    $foto2_path = handleImageUpload($imageData['foto2'], $imageData['foto2_tmp'], $imageData['foto2_path'], $imageData['seccion'], $maxFileSize);

                    if ($imageData['id']) {
                        // Actualizar la sección existente
                        $update_seccion_sql = "UPDATE secciones SET seccion = ?, descripcion = ?, direccion = ?, foto1 = ?, foto2 = ? WHERE id = ?";
                        $stmt_update_seccion = $conn->prepare($update_seccion_sql);
                        $stmt_update_seccion->bind_param("sssssi", $imageData['seccion'], $imageData['descripcion'], $imageData['direccion'], $foto1_path, $foto2_path, $imageData['id']);
                        if (!$stmt_update_seccion->execute()) {
                            throw new Exception("Error al actualizar la sección {$imageData['seccion']}: " . $conn->error);
                        }
                        $stmt_update_seccion->close();
                    } else {
                        // Insertar una nueva sección
                        $insert_seccion_sql = "INSERT INTO secciones (reporte_id, seccion, descripcion, direccion, foto1, foto2) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt_insert_seccion = $conn->prepare($insert_seccion_sql);
                        $stmt_insert_seccion->bind_param("isssss", $id_reporte, $imageData['seccion'], $imageData['descripcion'], $imageData['direccion'], $foto1_path, $foto2_path);
                        if (!$stmt_insert_seccion->execute()) {
                            throw new Exception("Error al insertar la sección {$imageData['seccion']}: " . $conn->error);
                        }
                        $stmt_insert_seccion->close();
                    }
                }

                // Limpiar el array de imágenes para el siguiente lote
                $imagesToProcess = [];
            }
        }

        // Confirmar la transacción
        $conn->commit();

        // Si el reporte se guardó exitosamente, envía una respuesta JSON
        echo json_encode(['status' => 'success']);
        exit();
    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}


// Función para manejar la carga de imágenes
function handleImageUpload($imageName, $tempPath, $existingPath, $section, $maxFileSize)
{
    global $conn; // Asegúrate de tener acceso a la conexión dentro de la función

    if (!empty($imageName)) {
        // Verificar el tamaño del archivo
        if (filesize($tempPath) > $maxFileSize) {
            throw new Exception("La foto de la sección $section excede el tamaño máximo permitido de 5MB.");
        }

        $check_image = getimagesize($tempPath);
        if ($check_image !== false) {
            // Eliminar la foto anterior si existe
            if (file_exists($existingPath)) {
                unlink($existingPath);
            }

            // Renombrar la foto de manera única
            $unique_name = hash('sha256', uniqid('', true)) . '_' . basename($imageName);
            $newPath = 'uploads/' . $unique_name;

            // Asegurarse de que el directorio de subidas exista
            if (!is_dir('uploads/')) {
                mkdir('uploads/', 0755, true);
            }

            // Mover el archivo subido a una ubicación temporal
            if (!move_uploaded_file($tempPath, $newPath)) {
                throw new Exception("Error al subir la foto de la sección $section.");
            }

            // Comprimir y redimensionar la imagen
            $compressionResult = compressImage($newPath, $newPath, 65, 1920, 1080);
            if (!$compressionResult) {
                throw new Exception("Error al comprimir la foto de la sección $section.");
            }

            return $newPath; // Retornar la nueva ruta
        } else {
            throw new Exception("El archivo de foto de la sección $section no es una imagen válida.");
        }
    }

    return $existingPath; // Retornar la ruta existente si no hay nueva imagen
}



// Cargar todos los reportes para mostrar en un select

checkAndReconnect($conn);

// Ejecutar la consulta y almacenar el resultado en $reportes
$sql = "SELECT * FROM reportes_2024 ORDER BY fecha DESC, created_at DESC";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->execute();
    $reportes = $stmt->get_result();

    // Aquí puedes procesar los resultados si es necesario
    // while ($row = $reportes->fetch_assoc()) {
    //     // Haz algo con cada $row
    // }

    $stmt->close();
} else {
    echo "Error en la preparación de la consulta: " . $conn->error;
}


// Generador de PDF

function generarPDF($reporte, $secciones)
{
    // Crear un nuevo objeto TCPDF con orientación horizontal (L)
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Establecer información del documento
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Autor');
    $pdf->SetTitle('Reporte Fotográfico');
    $pdf->SetSubject('Generación de Reportes');
    $pdf->SetKeywords('TCPDF, PDF, reportes, ejemplo');

    // Establecer márgenes iniciales (temporales)
    $pdf->SetMargins(0, 0, 0); // Eliminar márgenes para las imágenes de fondo
    $pdf->SetAutoPageBreak(TRUE, 0); // Desactivar saltos automáticos de página

    // Agregar la primera página para el encabezado
    $pdf->AddPage(); // Añade una nueva página

    // Agregar la imagen de fondo para la portada
    $pdf->Image('portada-full2.jpg', 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0, false, false, false);

    // Establecer márgenes para el contenido
    $pdf->SetMargins(15, 0, 0); // Restablecer márgenes
    $pdf->SetAutoPageBreak(TRUE, 0); // Establecer un salto de página automático con un margen inferior

    // Ajustar posición del contenido
    $pdf->SetY(114); // Ajusta la posición vertical del contenido

    // Agregar el título y fecha sobre la imagen de fondo
    $pdf->SetFont('helvetica', 'B', 26);
    $pdf->Cell(0, 10, $reporte['nombre'], 0, 1, 'C');
    $pdf->SetTextColor(58, 58, 58);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 5, 'Fecha: ' . $reporte['fecha'], 0, 1, 'C');

    $pdf->Ln(10);

    // ** Aquí comienza la personalización del botón invisible en la portada **

    // Ajustamos la ubicación para el área del "botón invisible"
    $xPos = 0; // Coordenada X donde se coloca el "botón invisible"
    $yPos = 0; // Coordenada Y (puedes ajustarlo para que esté en el lugar adecuado)
    $width = 10; // Ancho del área del "botón"
    $height = 10; // Alto del área del "botón"
    // Agregar el área clickeable, el botón invisible (sin texto)
    $pdf->Link($xPos, $yPos, $width, $height, 'https://smreportes.com');

    // ** Fin de la personalización del botón invisible **

    // Agregar las secciones al PDF
    foreach ($secciones as $seccion) {
        // Agregar una nueva página para cada sección
        $pdf->AddPage(); // Añade una nueva página para la sección

        // Agregar la imagen de fondo para las secciones
        $pdf->Image('fondo-full2.jpg', 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0, false, false, false);

        // Establecer márgenes para el contenido
        $pdf->SetMargins(15, 0, 0); // Mantener márgenes
        $pdf->SetAutoPageBreak(TRUE, 0); // Establecer un salto de página automático con un margen inferior

        // Ajustar posición del contenido
        $pdf->SetY(20); // Ajusta la posición vertical del contenido

        // Agregar título de la sección
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetY(15);
        $pdf->Cell(0, 10, $seccion['seccion'], 0, 1);

        // Agregar dirección de la sección
        if (!empty($seccion['direccion'])) {
            $pdf->SetTextColor(92, 92, 92);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetY(21);
            $pdf->Cell(0, 10, 'Dirección: ' . $seccion['direccion'], 0, 1);
        }

        // Agregar descripción de la sección, alineada a la izquierda
        $pdf->SetTextColor(92, 92, 92);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetY(29);
        $pdf->MultiCell(0, 10, $seccion['descripcion'], 0, 'L');

        // Inicializa posición para las imágenes
        $x = 15; // Posición horizontal inicial para la imagen (margen izquierdo)
        $y = $pdf->GetY() + 5; // Posición vertical (5 unidades después del texto)

        // Contadores para las imágenes
        $fotoCount = 0; // Contador de fotos

        // Verifica si hay imágenes y cuenta
        if (!empty($seccion['foto1'])) {
            $fotoCount++;
        }
        if (!empty($seccion['foto2'])) {
            $fotoCount++;
        }

        // Agregar imágenes según la cantidad de fotos
        if ($fotoCount === 1) {
            // Solo hay una foto
            $alturaFoto = 125; // Altura fija para una foto
            // Se obtiene el ancho manteniendo la proporción
            list($anchoFotoReal, $alturaReal) = getimagesize($seccion['foto1']);
            $anchoFoto = ($alturaFoto / $alturaReal) * $anchoFotoReal;

            // Centrar la imagen
            $x = ($pdf->getPageWidth() - 15 - $anchoFoto) / 2; // Centrar la imagen en la página considerando el margen

            // Ajustar la posición vertical, por ejemplo, bajar 10 unidades
            $y = $pdf->GetY() + 10; // Baja la posición en 10 unidades

            // Agregar la única imagen
            $pdf->Image($seccion['foto1'], $x, $y, $anchoFoto, $alturaFoto, '', '', '', false, 300, '', false, false, 0, false, false, false);
        } elseif ($fotoCount === 2) {
            // Hay dos fotos
            $alturaFoto = 97; // Altura fija para dos fotos
            $anchoTotal = $pdf->getPageWidth() - 30; // Ancho total disponible menos márgenes (15 izquierda + 15 derecha)
            $anchoFoto = ($anchoTotal - 5) / 2; // Ancho para cada foto ajustado a la página (10 es el espacio entre fotos)

            // Ajustar la posición vertical, por ejemplo, bajar 10 unidades
            $y = $pdf->GetY() + 25; // Baja la posición en 10 unidades

            // Agregar la primera imagen
            if (!empty($seccion['foto1'])) {
                // Obtener dimensiones de la primera imagen
                list($anchoReal1, $alturaReal1) = getimagesize($seccion['foto1']);
                // Calcular el ancho manteniendo la proporción
                $anchoFoto1 = ($alturaFoto / $alturaReal1) * $anchoReal1;
                $pdf->Image($seccion['foto1'], $x, $y, $anchoFoto1, $alturaFoto, '', '', '', false, 300, '', false, false, 0, false, false, false);
                $x += $anchoFoto1 + 10; // Mover a la derecha para la segunda imagen (10 es el espacio entre fotos)
            }
            // Agregar la segunda imagen
            if (!empty($seccion['foto2'])) {
                // Obtener dimensiones de la segunda imagen
                list($anchoReal2, $alturaReal2) = getimagesize($seccion['foto2']);
                // Calcular el ancho manteniendo la proporción
                $anchoFoto2 = ($alturaFoto / $alturaReal2) * $anchoReal2;
                $pdf->Image($seccion['foto2'], $x, $y, $anchoFoto2, $alturaFoto, '', '', '', false, 300, '', false, false, 0, false, false, false);
            }
        }

        $pdf->Ln(5); // Salto de línea entre secciones
    }

    // Cerrar y generar el PDF
    $pdf->Output('reporte.pdf', 'I'); // 'I' para enviar al navegador, 'D' para descargar
}

if (isset($_POST['generar_pdf'])) {
    $id_reporte = $_POST['id_reporte'];

    // Cargar el reporte y secciones de la base de datos
    $sql = "SELECT * FROM reportes_2024 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_reporte);
    $stmt->execute();
    $reporte = $stmt->get_result()->fetch_assoc();
    $stmt->close(); // Cerrar el statement después de usarlo

    $secciones_sql = "SELECT * FROM secciones WHERE reporte_id = ?";
    $stmt_secciones = $conn->prepare($secciones_sql);
    $stmt_secciones->bind_param("i", $id_reporte);
    $stmt_secciones->execute();
    $secciones = $stmt_secciones->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_secciones->close(); // Cerrar el statement después de usarlo

    // Generar el PDF
    generarPDF($reporte, $secciones);
    exit();
}


// Verificar si se ha enviado una solicitud de eliminación
if (isset($_POST['delete_address']) && isset($_POST['address_id'])) {
    $addressId = intval($_POST['address_id']);
    $conn = getDatabaseConnection();

    // Eliminar la dirección seleccionada
    $stmt = $conn->prepare("DELETE FROM direcciones WHERE id = ?");
    $stmt->bind_param("i", $addressId);

    if ($stmt->execute()) {
        echo "<p>Dirección eliminada correctamente.</p>";
    } else {
        echo "<p>Error al eliminar la dirección: " . $stmt->error . "</p>"; // Añadí la devolución del error para mayor claridad
    }

    $stmt->close();
    $conn->close();
}


?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta name="robots" content="noindex">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://fonts.googleapis.com/css?family=Roboto' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Generador de Reportes</title>
 <style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: "Roboto", sans-serif;
    background-color: #333645;
    color: #fff;
    padding: 20px;
  }

  .cuerpo {
    max-width: 1080px;
    margin: 0 auto;
    background-color: #1b1b2d;
    padding: 30px;
    border-radius: 6px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
  }

  h1 {
    text-align: center;
    margin-bottom: 30px;
    color: #2c3e50;
  }

  .form-group {
    margin-bottom: 20px;
  }

  label {
    display: block;
    font-weight: normal;
    color: #fff;
    margin-top: 10px;
    margin-bottom: 10px;
    font-size: 14px;
  }

  input[type="text"],
  input[type="date"],
  textarea,
  select {
    font-family: "Roboto", sans-serif;
    padding: 10px 15px;
    border: 1px solid #5b5f6b;
    /* Bordes más sutiles */
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s, box-shadow 0.3s;
    margin-bottom: 10px;
    width: 228px;
    background: #2d2f3a;
    color: #d1d1d1;
    /* Colores de texto más claros */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    /* Sombra sutil */
  }

  input[type="text"]:focus,
  input[type="date"]:focus,
  textarea:focus,
  select:focus {
    border-color: #4c9caf;
    /* Color de borde en foco */
    box-shadow: 0 0 8px rgba(76, 156, 175, 0.15);
    /* Efecto de foco con la mitad del brillo reducido nuevamente */
    outline: none;
    /* Eliminar el borde predeterminado */
  }

  ::placeholder {
    color: #a5a5a6;
  }

  textarea {
    resize: vertical;
    min-height: 30px;
  }

  button {
    font-family: "Roboto", sans-serif;
    padding: 9px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s, transform 0.2s;
  }

  button:hover {
    transform: translateY(0px);
  }

  .eliminar-Seccion,
  .eliminarSeccion {
    background: linear-gradient(135deg, #d9534f, #c9302c);
  border-radius: 0 4px 4px 0;
  color: #ffffff;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: normal;
  cursor: pointer;
  transition: background 0.3s ease, transform 0.2s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-top: 38px;
  }

  .eliminar-Seccion:hover,
  .eliminarSeccion:hover {
    background: linear-gradient(135deg, #c9302c, #d9534f);
  }

  .eliminar-preview {
    background-color: #e6867c;
    color: #ffffff;
    padding: 5px 10px;
    position: relative;
    top: -50px;
    left: 260px;
  }

  .eliminar-preview:hover {
    background-color: #c0392b;
  }

  .eliminarImagen:hover {
    background-color: #c0392b;
  }

  .eliminar-preview {
    border-radius: 4px;
  }

  .preview {
    margin-top: 10px;
    position: relative;
  }

  .preview img {
    max-width: 300px;
    height: auto;
    border-radius: 0px;
    background: #232531;
  }

  @media (min-width: 768px) {
    .form-group {
      display: flex;
      align-items: center;
    }

    .form-group label {
      flex: 1;
      margin-bottom: 0;
    }

    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group textarea,
    .form-group select {
      flex: 3;
    }
  }

  .direccion {
    background-color: #393e5e;
    padding: 20px;
    border-radius: 4px;
    margin-top: 20px;
  }

  .selector_reportes {
    background-color: #393e5e;
    padding: 20px;
    border-radius: 4px;
    margin-top: 0px;
    padding-top: 35px;
    width: 660px;
  }

  .direccion-filter {
    width: 100px !important;
  }

  .eliminar-imagen {
    background-color: #993b3b;
    color: #ffffff;
    border-radius: 6px;
    /* Bordes más redondeados */
    padding: 6px 10px;
    /* Un poco más de padding para mejor apariencia */
    font-size: 14px;
    /* Tamaño de fuente uniforme con otros botones */
    font-family: "Roboto", sans-serif;
    /* Fuente consistente */
    border: none;
    /* Eliminar borde */
    cursor: pointer;
    position: relative;
    top: -50px;
    left: 260px;
    transition: background-color 0.3s ease, box-shadow 0.3s ease;
    /* Suavizar transiciones */
  }

  .eliminar-imagen:hover {
    background-color: #b94c4c;
    /* Color de fondo más claro al pasar el ratón */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    /* Sombra sutil para profundidad */
  }

  .form-seccion {
    margin-top: 30px;
  }

  .btn-generar-pdf {
    background: linear-gradient(45deg, #4c9caf, #3b7a99, #2b5e74) !important;
    background-size: 200% 200% !important;
    color: #ffffff !important;
    border: none !important;
    padding: 9px 20px !important;
    cursor: pointer !important;
    font-size: 14px !important;
    border-radius: 0px !important;
    transition: background-position 0.3s ease !important;
  }

  .btn-generar-pdf:hover {
    animation: fasterGradientAnimation 2s ease infinite !important;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  input[type="file"] {
    padding: 5px 0;
    background: #232531;
    padding: 10px;
    color: #a5a5a6;
    border-radius: 4px;
  }

  .direccion-text {
    width: 600px !important;
  }

  select {
    background-color: #1b1b2d !important;
  }

  .styled-input {
    padding: 10px 12px;
    border-radius: 4px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
    color: #6f6f6e;
  }

  .styled-input:focus {
    border-color: #3498db;
    box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
  }

  .styled-select {
    padding: 10px 12px;
    color: #d1d1d1;
    /* Colores de texto más claros */
    border: 1px solid #5b5f6b;
    /* Bordes alineados con los otros inputs */
    border-radius: 4px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    background-color: #2d2f3a;
    /* Fondo más oscuro */
    width: 400px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    /* Sombra sutil */
  }

  .styled-select:focus {
    border-color: #4c9caf;
    /* Color de borde en foco alineado con los otros inputs */
    box-shadow: 0 0 8px rgba(76, 156, 175, 0.3);
    /* Efecto de foco alineado con los otros inputs */
  }

  .delete-button {
    background: linear-gradient(45deg, #973b42, #7b2d35, #5e2028);
    background-size: 200% 200%;
    color: #fff;
    border: none;
    padding: 10px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: normal;
    transition: background-position 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-family: "Roboto", sans-serif;
    margin-top: -10px;
    margin-left: 3px;
  }

  .delete-button:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  .direccion-filter::placeholder {
    color: #3a3a46;
    font-style: italic;
  }

  .upload-status {
    margin-top: 5px;
    font-size: 0.9em;
  }

  .g_reporte {
    margin-right: 0px !important;
    margin-left: 0px !important;
    height: 35px !important;
    background: linear-gradient(45deg, #3b5998, #2b3f6f, #1f2a4a);
    background-size: 200% 200%;
    color: white;
    transition: background-position 2s ease;
    border-radius: 0 0 0 0;
  }

  .g_reporte:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }
  
  .g2_reporte {
    margin-right: 0px !important;
    margin-left: 0px !important;
    height: 35px !important;
    background: linear-gradient(45deg, #3b5998, #2b3f6f, #1f2a4a);
    background-size: 200% 200%;
    color: white;
    transition: background-position 2s ease;
    border-radius: 4px 0 0 4px;
  }

  .g2_reporte:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  .botones_top {
    margin-bottom: 45px;
  }

  .form-direc {
    font-family: "Roboto", sans-serif;
    margin-bottom: 20px;
  }

  .form-direc label {
    font-size: 16px;
    font-weight: 500;
    color: #d1d1d1;
    display: block;
    margin-bottom: 8px;
  }

  .direccion-filter,
  .direccion-text,
  .form-direc select {
    font-family: "Roboto", sans-serif;
    padding: 10px 15px;
    border: 1px solid #5b5f6b;
    border-radius: 4px;
    font-size: 14px;
    color: #d1d1d1;
    background-color: #2d2f3a;
    transition: border-color 0.3s, box-shadow 0.3s;
    margin-bottom: 10px;
    width: 100%;
  }

  .direccion-filter:focus,
  .direccion-text:focus,
  .form-direc select:focus {
    border-color: #4c9caf;
    box-shadow: 0 0 8px rgba(76, 156, 175, 0.3);
    outline: none;
  }

  .inline-container {
    display: flex;
    gap: 10px;
  }

  .form-direc select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;base64,...");
    /* Agregar ícono de flecha para select */
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 12px;
  }

  .direccion-text {
    display: none;
  }

  .custom-file-input {
    position: relative;
    display: flex;
    align-items: center;
    width: 300px;
    /* Ancho ajustado a 300px */
    border: 1px solid #5b5f6b;
    border-radius: 0px;
    padding: 10px;
    background-color: #2d2f3a;
  }

  .custom-file-input i {
    margin-right: 10px;
    color: #d1d1d1;
  }

  .custom-file-input input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }

  .custom-file-input span {
    font-size: 14px;
    color: #d1d1d1;
    margin-left: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .eliminar-preview,
  .eliminar-imagen {
    background-color: #993b3b;
    color: #ffffff;
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-top: 10px;
    transition: background-color 0.3s ease;
  }

  .eliminar-preview:hover,
  .eliminar-imagen:hover {
    background-color: #b94c4c;
  }

  .form-foto-container {
    display: flex;
    flex-direction: row;
    gap: 20px;
    /* Espacio entre los elementos */
    align-items: flex-start;
    /* Alinear todos los elementos al inicio */
  }

  .form-foto {
    width: 300px;
  }

  .divisor2 hr {
    margin: 20px 0 0;
    border: none;
    border-top: 5px solid #232531;
    opacity: 0.7;
  }

  #addressFilter {
    width: 100px;
  }

  #tituloImagen {
    display: block;
    margin: 0 auto;
    max-width: 300px;
    width: 100%;
    height: auto;
  }

  #search-reportes::placeholder {
    color: #3a3a46;
    font-style: italic;
  }

  #addressFilter::placeholder {
    color: #3a3a46;
    font-style: italic;
  }

  #seccionDestino {
    visibility: hidden;
  }

  #bajarDestino {
    visibility: hidden;
  }

  #botonSubir {
    background: linear-gradient(45deg, #787f99, #5a6173, #3c4351);
    background-size: 200% 200%;
    color: #ffffff;
   border-radius: 0 4px 4px 0 !important;
    width: 95px;
    transition: background-position 0.3s ease;
  }

  #botonSubir:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  #botonBajar {
    background: linear-gradient(45deg, #787f99, #5a6173, #3c4351);
    background-size: 200% 200%;
    color: #ffffff;
    border-radius: 0 4px 4px 0 !important;
    width: 95px;
    transition: background-position 0.3s ease;
  }

  #botonBajar:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  #progress-container {
    width: 100%;
    height: auto;
    border-radius: 0px;
    /* Bordes más redondeados */
    margin-top: 20px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    /* Sombra más suave y definida */
    display: none;
    background-color: #1c1e29;
    /* Fondo más oscuro para el contenedor */
  }

  #progress-bar {
    position: relative;
    width: 0%;
    /* Iniciar en 0% para mostrar progreso */
    height: 100%;
    /* Ocupa todo el alto del contenedor */
    background-color: rgb(137, 168, 68);
    border-radius: 0px;
    /* Bordes redondeados */
    overflow: hidden;
    padding-top: 6px;
    transition: width 0.3s ease;
    /* Animación suave para el progreso */
  }

  #progress-bar span {
    position: absolute;
    top: 50%;
    /* Centrado verticalmente */
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 14px;
    /* Tamaño de fuente más grande */
    font-family: "Roboto", sans-serif;
    font-weight: 500;
    /* Peso de fuente más grande */
    color: #fff;
    /* Color de texto blanco para mejor legibilidad */
    text-align: center;
    white-space: nowrap;
    padding: 0px 10px;
  }

  #select-reportes {
    width: 330px;
  }

  #form-reporte {
    background-color: #333645;
    padding: 20px;
    border-radius: 4px;
    margin-top: 20px;
    padding-top: 45px;
  }

  #fecha {
    width: 228px;
  }

  #botones_pie {
    margin-top: 45px;
    margin-bottom: 20px;
  }

  #nueva-direccion {
    width: 510px;
  }

  #cargarReporte {
    background: linear-gradient(45deg, #8e44ad, #71368a, #56307c);
    background-size: 200% 200%;
    color: #ffffff;
    border: none;
    padding: 9px 20px;
    font-size: 14px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-position 0.3s ease;
    margin-top: -10px;
    margin-left: 9px;
  }

  #cargarReporte:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  #search-reportes {
    width: 100px;
  }

  #nombre {
    width: 228px;
    margin-bottom: 20px;
  }

  #agregarSeccion {
    background: linear-gradient(45deg, #8a2c02, #a34802, #b96002);
    background-size: 200% 200%;
    color: #fff;
    position: relative;
    /*right: 30%;*/
    width: 165px;
    transition: background-position 2s ease;
    border-radius: 4px 0 0 4px;
  }

  #agregarSeccion:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  #guardarDireccion {
    background: linear-gradient(45deg, #787f99, #5a6173, #3c4351);
    background-size: 200% 200%;
    color: #fff;
    margin-top: 10px;
    margin-left: 9px;
    transition: background-position 0.3s ease;
  }

  #guardarDireccion:hover {
    animation: fasterGradientAnimation 2s ease infinite;
  }

  @keyframes fasterGradientAnimation {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }
  
</style>


</head>

<body>

    <div class="container">
        <div class="center">

            <div class="cuerpo">
                <img src="smreportes.png" alt="Generador de Reportes Fotográficos" id="tituloImagen">
                <a id="seccionDestino">subir</a>

                <!-- Campo de búsqueda para reportes -->

                <div class="selector_reportes" style="display: flex; align-items: center; gap: 10px;">
                    <!-- Campo de búsqueda -->
                    <div class="buscador" style="display: flex; align-items: center; gap: 5px;">
                        <input type="text" id="search-reportes" placeholder="Filtro" oninput="filtrarReportes()">
                        </button>
                    </div>

                    <!-- Selección de reportes para editar -->
                    <div class="reportes" style="display: flex; align-items: center; gap: 5px;">
                        <select id="select-reportes">
                            <option value="">-- Seleccionar Reporte --</option>
                            <?php while ($row = $reportes->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nombre']); ?> - <?php echo htmlspecialchars($row['fecha']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button id="cargarReporte">
                            <i class="fas fa-file-alt"></i> Cargar Reporte
                        </button>
                    </div>
                </div>

                <!-- Formulario principal -->
                <form action="" method="POST" enctype="multipart/form-data" id="form-reporte">

                    <div style="position: relative;" class="botones_top">
                        <button type="submit" name="guardar_reporte" class="g2_reporte">
                            <i class="fas fa-save"></i> Guardar Reporte
                        </button>
                        <button type="submit" name="generar_pdf" class="btn-generar-pdf">
                            <i class="fas fa-file-pdf"></i> Generar PDF
                        </button>

                        <button id="botonBajar"><i class="fas fa-arrow-down"></i> Bajar</button>
                    </div>



                    <hr style="margin: 25px 0; border: none; border-top: 2px solid #232531; opacity: 0.7;" />


                    <input type="hidden" name="id_reporte" id="id_reporte">

                    <div class="fecha">

                        <label for="fecha">Fecha</label>
                        <input type="date" name="fecha" required id="fecha">
                    </div>

                    <div class="nombre">
                        <label for="nombre">Nombre</label>
                        <input type="text" name="nombre" required id="nombre">
                    </div>

                    <hr style="margin: 25px 0; border: none; border-top: 2px solid #232531; opacity: 0.7;" />

                    <!-- Contenedor de secciones dinámicas -->

                    <div id="secciones-container">

                        <!-- Secciones se cargarán aquí -->

                    </div>

                    <!-- Contenedor botones_pie con botones alineados a la izquierda y derecha -->
                    <div id="botones_pie" style="display: flex; align-items: center; gap: 4px; width: 100%;">
                        <!-- Botones alineados a la izquierda -->
                        <button type="button" id="agregarSeccion">
                            <i class="fas fa-plus"></i> Agregar Sección
                        </button>
                        <button type="submit" name="guardar_reporte" class="g_reporte">
                            <i class="fas fa-save"></i> Guardar Reporte
                        </button>
                        <button type="submit" name="generar_pdf" class="btn-generar-pdf">
                            <i class="fas fa-file-pdf"></i> Generar PDF
                        </button>
                        <button id="botonSubir">
                            <i class="fas fa-arrow-up"></i> Subir
                        </button>
                        <a id="bajarDestino">bajar</a>
                    </div>

                    <!-- Barra de progreso debajo de los botones -->
                    <div id="progress-container" style="width: 100%; background-color: #ddd; display: none; margin-top: 10px;">
                        <div id="progress-bar" style="width: 0%; height: 30px; background-color: #4CAF50; text-align: center; color: white;">
                            0%
                        </div>
                    </div>

                </form>

                <div class="direccion">
                    <label for="nueva-direccion">Guardar Nueva Dirección</label>
                    <input type="text" id="nueva-direccion" placeholder="Guardar nueva dirección">
                    <button type="button" id="guardarDireccion"><i class="fas fa-map-marker-alt"></i> Guardar Dirección</button>

                    <label for="borrar-direccion">Eliminar Dirección</label>
                    <form method="POST" action="" style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" id="addressFilter" placeholder="Filtro" oninput="filterAddresses()" class="styled-input" />

                        <select name="address_id" id="addressSelect" class="styled-select">
                            <!-- Opción vacía predeterminada -->
                            <option value="" disabled selected>Seleccione una dirección</option>
                            <?php
                            // Consultar todas las direcciones de la base de datos para mostrarlas en el selector
                            $conn = getDatabaseConnection();
                            $result = $conn->query("SELECT id, direccion FROM direcciones");

                            // Generar las opciones del selector con cada dirección
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['direccion']) . "</option>";
                            }
                            $conn->close();
                            ?>
                        </select>

                        <button type="submit" name="delete_address" class="delete-button">
                            <i class="fa fa-trash"></i> <!-- Icono de "papelera" Font Awesome -->
                            Eliminar Dirección
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

    </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Agregar nueva sección
        document.addEventListener('DOMContentLoaded', function() {
            let contadorSecciones = 0;

            document.getElementById('agregarSeccion').addEventListener('click', function() {
                contadorSecciones++;
                var container = document.getElementById('secciones-container');
                var nuevaSeccion = document.createElement('div');
                nuevaSeccion.classList.add('seccion-group');
                nuevaSeccion.innerHTML = `
            <input type="hidden" name="id_seccion[]" value="">
            <div class="form-seccion">
                <label for="seccion">Sección:</label>
                <select class="selector-items" name="seccion[]">
                    <option value="">-- Seleccionar Item --</option>
                    <option value="Mobiliario Urbano">Mobiliario Urbano</option>
                    <option value="Mobiliario Digital">Mobiliario Digital</option>
                    <option value="Vallas">Vallas</option>
                    <option value="Cierre LED">Cierre LED</option>
                    <option value="Backlights">Backlights</option>
                    <option value="MUDGF">MUDGF</option>
                    <option value="Pantalla LED Gran Formato">Pantalla LED Gran Formato</option>
                    <option value="AIPC">AIPC</option>
                </select>
            </div>
            <div class="form-descrip">
                <label for="descripcion">Descripción:</label>
                <textarea name="descripcion[]"></textarea>
            </div>
            <div class="form-direc">
    <label for="direccion">Dirección:</label>
    <div class="inline-container">
        <input type="text" class="direccion-filter" placeholder="Filtro" oninput="filtrarDirecciones(this)">
        <select name="direccion[]" onchange="mostrarDireccion(this)">
            <option value="">-- Seleccionar Dirección --</option>
            <?php foreach ($direcciones as $direccion): ?>
                <option value="<?php echo htmlspecialchars($direccion); ?>"><?php echo htmlspecialchars($direccion); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <input type="text" class="direccion-text" readonly>
</div>

            <div class="form-foto-container">
    <div class="form-foto">
        <label for="foto1">Foto 1:</label>
        <div class="custom-file-input">
            <i class="fa fa-upload" aria-hidden="true"></i>
            <input type="file" name="foto1[]" accept="image/*" id="foto1" onchange="handleFileChange(this, 'file-text-foto1'); previewImage(this); toggleDeleteButton(this, 'eliminarImagen1')">
            <span id="file-text-foto1">Por favor, selecciona un archivo</span>
        </div>
        <div class="preview">
            <button type="button" class="eliminar-preview" style="display: none;">X</button>
        </div>
    </div>
    <div class="form-foto">
        <label for="foto2">Foto 2:</label>
        <div class="custom-file-input">
            <i class="fa fa-upload" aria-hidden="true"></i>
            <input type="file" name="foto2[]" accept="image/*" id="foto2" onchange="handleFileChange(this, 'file-text-foto2'); previewImage(this); toggleDeleteButton(this, 'eliminarImagen2')">
            <span id="file-text-foto2">Por favor, selecciona un archivo</span>
        </div>
        <div class="preview">
            <button type="button" class="eliminar-preview" style="display: none;">X</button>
        </div>
    </div>
   <button type="button" class="eliminar-Seccion"> <i class="fa fa-trash" aria-hidden="true"></i> Eliminar Sección </button>
</div>
<div class="divisor">
<hr style="margin: 25px 0; border: none; border-top: 2px solid #232531; opacity: 0.7;" />
</div>
        `;
                container.appendChild(nuevaSeccion);

                // Mostrar aviso solo cuando el contador es un múltiplo de 5
                if (contadorSecciones > 0 && contadorSecciones % 10 === 0) {
                    alert(`Has agregado ${contadorSecciones} secciones, guarda el reporte para continuar.`);
                }

                // Agregar funcionalidad al botón de eliminar sección
                nuevaSeccion.querySelector('.eliminar-Seccion').addEventListener('click', function() {
                    // Confirmar si desea eliminar la sección
                    var confirmarEliminacion = confirm("¿Estás seguro de que quieres eliminar esta sección?");
                    if (confirmarEliminacion) {
                        eliminarSeccionTemporal(this);
                    }
                });
            });

            // Delegar evento al contenedor de secciones
            document.getElementById('secciones-container').addEventListener('change', function(event) {
                if (event.target.matches('.selector-items')) {
                    var inputSeccion = event.target.closest('.seccion-group').querySelector('input[name="seccion[]"]');
                    inputSeccion.value = event.target.value; // Asigna el valor seleccionado al input "sección"
                }
            });
        });

        // Función para eliminar la sección temporal
        function eliminarSeccionTemporal(button) {
            var seccionGroup = button.closest('.seccion-group'); // Encuentra el contenedor de la sección
            if (seccionGroup) {
                seccionGroup.remove(); // Elimina el contenedor de la sección
            }
        }


        // Función para filtrar direcciones
        function filtrarDirecciones(input) {
            var seccionGroup = input.closest('.seccion-group');
            var selectDireccion = seccionGroup.querySelector('select[name="direccion[]"]');
            var filterValue = input.value.toLowerCase();

            for (var i = 0; i < selectDireccion.options.length; i++) {
                var option = selectDireccion.options[i];
                var textoOpcion = option.text.toLowerCase();

                // Dividir el texto de la opción en palabras y comprobar si alguna palabra empieza con el valor de filtro
                var palabras = textoOpcion.split(' ');
                var coincide = palabras.some(function(palabra) {
                    return palabra.startsWith(filterValue);
                });

                option.style.display = coincide ? '' : 'none';
            }
        }


        // Función para mostrar la dirección seleccionada en la casilla de texto
        function mostrarDireccion(selectElement) {
            var direccionText = selectElement.parentElement.querySelector('.direccion-text');
            direccionText.value = selectElement.value; // Copia el valor del select a la casilla de texto
        }


        document.getElementById('cargarReporte').addEventListener('click', function() {
            var id_reporte = document.getElementById('select-reportes').value;
            if (id_reporte) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true); // Asegúrate de que la URL sea correcta
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            var reporte = response.reporte;
                            document.getElementById('id_reporte').value = id_reporte;
                            document.getElementById('fecha').value = reporte.fecha;
                            document.getElementById('nombre').value = reporte.nombre;

                            // Limpiar secciones anteriores
                            document.getElementById('secciones-container').innerHTML = '';

                            // Cargar secciones
                            response.secciones.forEach(function(seccion) {
                                var seccionDiv = document.createElement('div');
                                seccionDiv.classList.add('seccion-group');

                                seccionDiv.innerHTML = `
        <input type="hidden" name="id_seccion[]" value="${seccion.id}">
        <div class="form-seccion">
            <label for="seccion">Sección:</label>
            <select class="selector-items" name="seccion[]">
                <option value="">-- Seleccionar Item --</option>
                <option value="Mobiliario Urbano" ${seccion.seccion === 'Mobiliario Urbano' ? 'selected' : ''}>Mobiliario Urbano</option>
                <option value="Mobiliario Digital" ${seccion.seccion === 'Mobiliario Digital' ? 'selected' : ''}>Mobiliario Digital</option>
                <option value="Vallas" ${seccion.seccion === 'Vallas' ? 'selected' : ''}>Vallas</option>
                <option value="Cierre LED" ${seccion.seccion === 'Cierre LED' ? 'selected' : ''}>Cierre LED</option>
                <option value="Backlights" ${seccion.seccion === 'Backlights' ? 'selected' : ''}>Backlights</option>
                <option value="MUDGF" ${seccion.seccion === 'MUDGF' ? 'selected' : ''}>MUDGF</option>
                <option value="Pantalla LED Gran Formato" ${seccion.seccion === 'Pantalla LED Gran Formato' ? 'selected' : ''}>Pantalla LED Gran Formato</option>
                <option value="AIPC" ${seccion.seccion === 'AIPC' ? 'selected' : ''}>AIPC</option>
            </select>
        </div>
   <div class="form-descrip">
                                <label for="descripcion">Descripción:</label>
                                <textarea name="descripcion[]">${escapeHtml(seccion.descripcion)}</textarea>
                            </div>
                            <div class="form-direc">
    <label for="direccion">Dirección:</label>
    <div style="display: flex; gap: 10px;">
        <input type="text" class="direccion-filter" placeholder="Filtro" oninput="filtrarDirecciones(this)">
        <select name="direccion[]" onchange="mostrarDireccion(this)">
            <option value="">-- Seleccionar Dirección --</option>
            <?php foreach ($direcciones as $direccion): ?>
                <option value="<?php echo htmlspecialchars($direccion); ?>" ${seccion.direccion === '<?php echo htmlspecialchars($direccion); ?>' ? 'selected' : ''}>
                    <?php echo htmlspecialchars($direccion); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <input type="text" class="direccion-text" value="${escapeHtml(seccion.direccion)}" readonly>
</div>

<div class="form-foto-container">
    <div class="form-foto">
        <label for="foto1">Foto 1:</label>
        <div class="custom-file-input">
            <i class="fa fa-upload" aria-hidden="true"></i>
            <input type="file" name="foto1[]" accept="image/*" onchange="handleFileChange(this, 'file-text-foto1'); previewImage(this)">
            <span id="file-text-foto1">Por favor, selecciona un archivo</span>
        </div>
        <div class="preview">
            ${seccion.foto1 ? `<img src="${escapeHtml(seccion.foto1)}" alt="Imagen 1">
            <button type="button" class="eliminar-imagen" data-id="${seccion.id}" data-foto="foto1">X</button>` : ''}
            <button type="button" class="eliminar-preview" style="display: none;">X</button>
        </div>
    </div>
    <div class="form-foto">
        <label for="foto2">Foto 2:</label>
        <div class="custom-file-input">
            <i class="fa fa-upload" aria-hidden="true"></i>
            <input type="file" name="foto2[]" accept="image/*" onchange="handleFileChange(this, 'file-text-foto2'); previewImage(this)">
            <span id="file-text-foto2">Por favor, selecciona un archivo</span>
        </div>
        <div class="preview">
            ${seccion.foto2 ? `<img src="${escapeHtml(seccion.foto2)}" alt="Imagen 2">
            <button type="button" class="eliminar-imagen" data-id="${seccion.id}" data-foto="foto2">X</button>` : ''}
            <button type="button" class="eliminar-preview" style="display: none;">X</button>
        </div>
    </div>
    <button type="button" class="eliminarSeccion" onclick="eliminarSeccion(${seccion.id}, this)"> <i class="fa fa-trash" aria-hidden="true"></i> Eliminar Sección </button>
</div>

<!-- Agregar divisor después del contenedor -->
<div class="divisor2">
    <hr style="margin: 0 0 0 0; border: none; border-top: 2px solid #232531; opacity: 0.7;" />
</div>


                        `;

                                // Aquí asignamos el valor seleccionado del selector de ítems
                                var selectorItems = seccionDiv.querySelector('.selector-items');
                                selectorItems.value = seccion.seccion; // Asigna el valor de la sección cargada al selector

                                // Agregar funcionalidad al selector de ítems
                                selectorItems.addEventListener('change', function() {
                                    var inputSeccion = seccionDiv.querySelector('input[name="seccion[]"]');
                                    inputSeccion.value = this.value; // Asigna el valor seleccionado al input "sección"
                                });

                                document.getElementById('secciones-container').appendChild(seccionDiv);
                            });
                        } else {
                            alert('Error al cargar el reporte.');
                        }
                    }
                };
                xhr.send('cargar_reporte=true&id_reporte=' + encodeURIComponent(id_reporte));
            } else {
                alert('Por favor, selecciona un reporte para cargar.');
            }
        });


        // Función para eliminar la sección
        function eliminarSeccion(id_seccion, button) {
            if (confirm('¿Estás seguro de que deseas eliminar esta sección?')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'eliminar_seccion.php', true); // Asegúrate de que esta ruta sea correcta
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            // Eliminar la sección del DOM
                            var seccionDiv = button.closest('.seccion-group'); // Aquí usamos closest
                            seccionDiv.remove(); // Eliminar el contenedor
                            alert('Sección eliminada exitosamente.');
                        } else {
                            alert('Error al eliminar la sección: ' + response.message);
                        }
                    } else if (xhr.readyState === 4) {
                        alert('Error en la solicitud al servidor.');
                    }
                };
                xhr.onerror = function() {
                    alert('Error de conexión con el servidor.');
                };
                xhr.send('eliminar_seccion=true&id_seccion=' + encodeURIComponent(id_seccion));
            }
        }


        // Función para escapar caracteres HTML y prevenir XSS
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }


        // Guardar dirección
        document.getElementById('guardarDireccion').addEventListener('click', function() {
            var nueva_direccion = document.getElementById('nueva-direccion').value;
            if (nueva_direccion) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            alert(response.message || 'Dirección guardada con éxito');
                            if (response.status === 'success') {
                                // Agregar nueva opción a todos los selects de direcciones
                                var selectsDireccion = document.querySelectorAll('select[name="direccion[]"]');
                                selectsDireccion.forEach(function(select) {
                                    var option = document.createElement('option');
                                    option.value = response.direccion;
                                    option.text = response.direccion;
                                    select.add(option);
                                });
                                document.getElementById('nueva-direccion').value = ''; // Limpiar el campo
                            }
                        } catch (e) {
                            alert('Error al procesar la respuesta del servidor.');
                        }
                    } else if (xhr.readyState === 4) {
                        alert('Error en la solicitud al servidor.');
                    }
                };
                xhr.onerror = function() {
                    alert('Error de conexión con el servidor.');
                };
                xhr.send('guardar_direccion=true&nueva_direccion=' + encodeURIComponent(nueva_direccion));
            } else {
                alert('Por favor, ingresa una dirección.');
            }
        });

        // Función para filtrar reportes
        function filtrarReportes() {
            var input = document.getElementById('search-reportes');
            var filterValue = input.value.toLowerCase();
            var select = document.getElementById('select-reportes');
            var options = select.options;

            for (var i = 1; i < options.length; i++) { // Comenzar en 1 para omitir la opción vacía
                var optionText = options[i].text.toLowerCase();
                options[i].style.display = optionText.includes(filterValue) ? '' : 'none';
            }
        }


        // Evento para eliminar la imagen cargada desde el servidor
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('eliminar-imagen')) {
                var id_seccion = e.target.getAttribute('data-id');
                var foto = e.target.getAttribute('data-foto');

                if (confirm('¿Estás seguro de que deseas eliminar esta imagen?')) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'eliminar_imagen.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                alert(response.message);
                                if (response.status === 'success') {
                                    // Seleccionar el contenedor .preview correspondiente al botón clickeado
                                    var previewDiv = e.target.closest('.preview');
                                    if (previewDiv) {
                                        previewDiv.innerHTML = ''; // Limpiar la vista previa específica
                                    }
                                }
                            } catch (e) {
                                alert('Error al procesar la respuesta del servidor.');
                            }
                        } else if (xhr.readyState === 4) {
                            alert('Error en la solicitud al servidor.');
                        }
                    };
                    xhr.onerror = function() {
                        alert('Error de conexión con el servidor.');
                    };
                    xhr.send('id_seccion=' + encodeURIComponent(id_seccion) + '&foto=' + encodeURIComponent(foto));
                }
            }
        });


        document.getElementById("botonSubir").addEventListener("click", function(event) {
            event.preventDefault(); // Evita el refresco de la página
            document.getElementById("seccionDestino").scrollIntoView({
                behavior: "smooth"
            });
        });

        document.getElementById("botonBajar").addEventListener("click", function(event) {
            event.preventDefault(); // Evita el refresco de la página
            document.getElementById("bajarDestino").scrollIntoView({
                behavior: "smooth"
            });
        });



        // Capturamos el evento submit para todo el formulario - Barra progreso
        document.getElementById('form-reporte').addEventListener('submit', function(event) {
            const button = event.submitter; // El botón que se presionó

            // Si el botón presionado es el de 'Generar PDF', 'Bajar', 'Subir', 'Agregar Sección' o 'Cargar Reporte', no prevenimos el envío del formulario
            if (button && (
                    button.name === 'generar_pdf' ||
                    button.id === 'botonBajar' ||
                    button.id === 'botonSubir' ||
                    button.id === 'agregarSeccion' ||
                    button.id === 'cargarReporte')) {
                return; // Deja que el formulario se envíe normalmente para estos botones
            }

            // Si el botón presionado es el de 'Guardar Reporte', prevenimos el envío para mostrar la barra de progreso
            if (button && button.name === 'guardar_reporte') {
                event.preventDefault(); // Prevenimos el envío del formulario para mostrar la barra de progreso

                // Mostramos la barra de progreso
                document.getElementById('progress-container').style.display = 'block';

                // Creamos un objeto FormData con el formulario
                let formData = new FormData(this);
                formData.append('guardar_reporte', true);

                // Crear un objeto XMLHttpRequest para enviar el formulario de manera asíncrona
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'index.php', true);

                // Evento para manejar el progreso de la carga
                xhr.upload.addEventListener('progress', function(event) {
                    if (event.lengthComputable) {
                        let progress = Math.round((event.loaded / event.total) * 100);
                        actualizarBarraProgreso(progress);
                    }
                });

                // Manejo de la respuesta cuando se completa la carga
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.status === 'success') {
                                // Completar la barra de progreso y redirigir
                                actualizarBarraProgreso(100);
                                setTimeout(function() {
                                    window.location.href = 'reporte_guardado.html'; // Redirige después de la carga completa
                                }, 500); // Retardo de medio segundo para que la barra llegue al 100%
                            } else {
                                alert('Error al guardar el reporte: ' + data.message);
                            }
                        } catch (e) {
                            alert('Error al procesar la respuesta del servidor.');
                        }
                    } else {
                        alert('Error en la conexión: ' + xhr.statusText);
                    }
                };

                xhr.onerror = function() {
                    alert('Error de conexión.');
                };

                // Enviar la solicitud con los datos del formulario
                xhr.send(formData);
            }
        });

        // Función para actualizar la barra de progreso
        function actualizarBarraProgreso(progress) {
            const progressBar = document.getElementById('progress-bar');
            progressBar.style.width = progress + '%';

            // Si el progreso es 100%, muestra el mensaje de "Procesando"
            if (progress === 100) {
                progressBar.innerText = '100% - Procesando, espere un momento.';
            } else {
                progressBar.innerText = progress + '%';
            }
        }


        // Función para verificar la conexión a Internet
        function checkInternetConnection() {
            const saveButton = document.getElementById('guardar_reporte');
            if (navigator.onLine) {
                saveButton.disabled = false;
            } else {
                saveButton.disabled = true;
                alert('No hay conexión a Internet. Verifica tu conexión.');
            }
        }

        // Llama a checkInternetConnection cuando se carga la página o cambia la conexión
        window.addEventListener('load', checkInternetConnection);
        window.addEventListener('online', checkInternetConnection);
        window.addEventListener('offline', checkInternetConnection);


        // Función para verificar la conexión a la base de datos
        document.getElementById('guardar_reporte').addEventListener('click', function(event) {
            event.preventDefault(); // Detiene temporalmente la acción de guardar

            fetch('check_connection.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Si hay conexión a la base de datos, permite guardar el formulario
                        document.getElementById('form-reporte').submit();
                    } else {
                        alert('No se puede guardar. Verifica tu conexión con la base de datos.');
                    }
                })
                .catch(error => {
                    console.error('Error al verificar la conexión:', error);
                    alert('No se puede guardar. Verifica tu conexión con la base de datos.');
                });
        });


        function handleFileChange(input, textId) {
            const fileName = input.files.length > 0 ? input.files[0].name : 'Por favor, selecciona un archivo';
            document.getElementById(textId).textContent = fileName;
        }

        function previewImage(input) {
            const previewDiv = input.parentElement.nextElementSibling;
            const file = input.files[0];

            // Seleccionar o crear el botón "Eliminar Preview"
            let eliminarPreviewButton = previewDiv.querySelector('.eliminar-preview');
            if (!eliminarPreviewButton) {
                eliminarPreviewButton = document.createElement('button');
                eliminarPreviewButton.type = 'button';
                eliminarPreviewButton.className = 'eliminar-preview';
                eliminarPreviewButton.textContent = 'X';
                eliminarPreviewButton.style.display = 'none'; // Inicialmente oculto
                previewDiv.appendChild(eliminarPreviewButton);
            }

            // Si ya hay una imagen previa cargada (reporte), no mostramos el botón "Eliminar Preview"
            const eliminarImagenButton = previewDiv.querySelector('.eliminar-imagen');
            if (eliminarImagenButton) {
                eliminarPreviewButton.style.display = 'none';
                return; // Salimos porque ya existe una imagen previa
            }

            // Verificar si existe un archivo para cargar
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Verificar si ya existe una imagen preview para evitar duplicados
                    let imgElement = previewDiv.querySelector('img');
                    if (!imgElement) {
                        imgElement = document.createElement('img');
                        previewDiv.insertBefore(imgElement, eliminarPreviewButton);
                    }

                    // Actualizar el src de la imagen
                    imgElement.src = e.target.result;
                    imgElement.alt = 'Imagen Preview';
                    imgElement.style.maxWidth = '300px';
                    imgElement.style.maxHeight = '300px';

                    // Mostrar el botón "Eliminar Preview" solo cuando se cargue una nueva imagen
                    eliminarPreviewButton.style.display = 'inline-block';

                    // Agregar funcionalidad al botón de eliminar preview
                    eliminarPreviewButton.onclick = function() {
                        input.value = ''; // Limpiar el input
                        if (imgElement) {
                            previewDiv.removeChild(imgElement); // Eliminar la imagen del preview
                        }
                        eliminarPreviewButton.style.display = 'none'; // Ocultar el botón
                        document.getElementById(textId).textContent = 'Por favor, selecciona un archivo';
                    };
                };
                reader.readAsDataURL(file);
            }
        }

        function toggleDeleteButton(input, buttonId) {
            const deleteButton = document.getElementById(buttonId);
            if (input.files.length > 0) {
                deleteButton.style.display = 'inline';
            } else {
                deleteButton.style.display = 'none';
            }
        }

        function removeImage(button) {
            const previewDiv = button.parentElement;
            previewDiv.innerHTML = '';
            const fileInput = previewDiv.previousElementSibling.querySelector('input[type="file"]');
            fileInput.value = '';
            const placeholderText = fileInput.nextElementSibling;
            placeholderText.textContent = 'Por favor, selecciona un archivo';
            button.style.display = 'none';
        }

        // Función filtro de direcciones para eliminar
        function filterAddresses() {
            const filter = document.getElementById('addressFilter').value.toLowerCase();
            const select = document.getElementById('addressSelect');
            const options = select.getElementsByTagName('option');

            // Iterar sobre todas las opciones del select
            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const addressText = option.textContent.toLowerCase();

                // Mostrar u ocultar las opciones según el filtro
                if (addressText.indexOf(filter) > -1) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        }


        window.addEventListener('load', function() {
            document.querySelectorAll('.eliminar-imagen').forEach(button => {
                button.style.display = 'block';
            });
        });
        
    </script>

</body>

</html>
