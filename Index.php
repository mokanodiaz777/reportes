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
require_once 'vendor/autoload.php';

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

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


if (isset($_POST['eliminar_reporte'])) {
    $id_reporte_eliminar = $_POST['id_reporte_eliminar'];

    if ($id_reporte_eliminar) {
        $conn = getDatabaseConnection();

        // Iniciar transacción
        $conn->begin_transaction();

        try {
            // Eliminar las secciones relacionadas
            $delete_secciones_sql = "DELETE FROM secciones WHERE reporte_id = ?";
            $stmt_delete_secciones = $conn->prepare($delete_secciones_sql);
            $stmt_delete_secciones->bind_param("i", $id_reporte_eliminar);
            $stmt_delete_secciones->execute();
            $stmt_delete_secciones->close();

            // Eliminar el reporte
            $delete_reporte_sql = "DELETE FROM reportes_2024 WHERE id = ?";
            $stmt_delete_reporte = $conn->prepare($delete_reporte_sql);
            $stmt_delete_reporte->bind_param("i", $id_reporte_eliminar);
            $stmt_delete_reporte->execute();
            $stmt_delete_reporte->close();

            // Confirmar transacción
            $conn->commit();

            // Redirigir a "reporte_borrado.html" después de eliminar exitosamente
            header("Location: reporte_borrado.html");
            exit();

            // Establecer mensaje de éxito
            $mensaje_eliminar_reporte = "<p style='color: green; text-align: center;'>Reporte eliminado exitosamente.</p>";
        } catch (Exception $e) {
            // Revertir transacción
            $conn->rollback();

            // Establecer mensaje de error
            $mensaje_eliminar_reporte = "<p style='color: red; text-align: center;'>Error al eliminar el reporte: " . $e->getMessage() . "</p>";
        } finally {
            $conn->close();
        }
    }
}

// Función para generar el PPTX
function generarPPTX($reporte, $secciones)
{
    // Crear una nueva presentación
    $ppt = new PhpPresentation();

    // Crear la primera diapositiva (portada)
    $slide = $ppt->getActiveSlide();

    // Establecer la imagen de fondo para la portada
    $slide->createDrawingShape()
        ->setName('Portada Background')
        ->setDescription('Portada Fondo')
        ->setPath('images/portada-full3.jpg') // Imagen de fondo
        ->setHeight(720) // Aseguramos que se ajuste al tamaño predeterminado
        ->setWidth(960)
        ->setOffsetX(0)
        ->setOffsetY(0);

    // Título de la portada
    $shapeTitulo = $slide->createRichTextShape()
        ->setHeight(100)
        ->setWidth(600)
        ->setOffsetX(200)
        ->setOffsetY(377); // Ajusta la posición vertical del título
    $shapeTitulo->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $textRunTitulo = $shapeTitulo->createTextRun($reporte['nombre']);

    // Estilos del título
    $textRunTitulo->getFont()->setBold(true)
        ->setSize(22) // Ajuste del tamaño de la fuente
        ->setColor(new Color('FF333333')) // Color de la fuente (negro)
        ->setName('Arial'); // Establecer la fuente a Arial

    // Fecha en la portada
    $shapeFecha = $slide->createRichTextShape()
        ->setHeight(30) // Ajustar la altura de la fecha
        ->setWidth(600) // Ajustar el ancho de la fecha
        ->setOffsetX(438) // Ajustar posición horizontal
        ->setOffsetY(418); // Ajustar posición vertical de la fecha

    $textRunFecha = $shapeFecha->createTextRun('Fecha: ' . $reporte['fecha']);

    // Estilos de la fecha
    $textRunFecha->getFont()->setSize(10) // Ajuste del tamaño de la fuente
        ->setColor(new Color('FF555555')) // Color gris
        ->setName('Arial'); // Establecer la fuente a Arial

    // Agregar cada sección como una diapositiva
    foreach ($secciones as $seccion) {
        $slide = $ppt->createSlide();

        // Establecer la imagen de fondo para las diapositivas
        $slide->createDrawingShape()
            ->setName('Slide Background')
            ->setDescription('Fondo Slide')
            ->setPath('images/fondo-full3.jpg') // Imagen de fondo
            ->setHeight(720) // Aseguramos que se ajuste al tamaño predeterminado
            ->setWidth(960)
            ->setOffsetX(0)
            ->setOffsetY(0);

        // Título de la sección
        $shape = $slide->createRichTextShape()
            ->setHeight(70) // Ajuste en altura
            ->setWidth(700) // Ajuste en ancho
            ->setOffsetX(50)
            ->setOffsetY(40); // Ajuste en posición vertical
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $textRun = $shape->createTextRun($seccion['seccion']);

        // Estilo del título con fuente personalizada
        $textRun->getFont()->setBold(true) // Negrita
            ->setSize(22) // Tamaño de fuente
            ->setColor(new Color('FF333333')) // Color (Rojo)
            ->setName('Arial'); // Fuente personalizada (por ejemplo, Arial)

        // Descripción de la sección
        $shape = $slide->createRichTextShape()
            ->setHeight(100) // Ajusta la altura de la descripción
            ->setWidth(700)  // Ajuste en ancho
            ->setOffsetX(50)
            ->setOffsetY(117); // Ajuste en la posición vertical de la descripción
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $textRun = $shape->createTextRun($seccion['descripcion']);
        $textRun->getFont()->setSize(8)
            ->setColor(new Color('FF555555'))
            ->setName('Arial'); // Fuente personalizada


        // Dirección de la sección
        if (!empty($seccion['direccion'])) {
            // Convertir la dirección a mayúsculas
            $direccionMayusculas = strtoupper($seccion['direccion']);

            // Agregar la dirección de la sección en mayúsculas
            $shape = $slide->createRichTextShape()
                ->setHeight(60) // Ajusta la altura de la dirección
                ->setWidth(700) // Ajuste en ancho
                ->setOffsetX(50)
                ->setOffsetY(80); // Ajuste en la posición vertical de la dirección
            $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $textRun = $shape->createTextRun('' . $direccionMayusculas);
            $textRun->getFont()->setSize(10)
                ->setColor(new Color('FF555555'))
                ->setName('Arial'); // Fuente personalizada
        }

        // Añadir imágenes
        $x = 50;
        $y = 200; // Ajuste en la posición vertical de las imágenes
        $imageHeight = 250; // Ajuste en la altura de las imágenes

        // Si solo hay una imagen
        if (!empty($seccion['foto1']) && empty($seccion['foto2'])) {
            $fixedHeight = 400; // Altura fija de 700px
            // Obtener las dimensiones de la imagen para calcular la proporción
            list($originalWidth, $originalHeight) = getimagesize($seccion['foto1']); // Obtener dimensiones originales de la imagen

            // Calcular el ancho proporcional manteniendo la relación de aspecto
            $imageWidth = ($originalWidth / $originalHeight) * $fixedHeight;

            // Control de la posición
            $x = isset($seccion['pos_x']) ? $seccion['pos_x'] : (960 - $imageWidth) / 2; // Centrar horizontalmente si no se indica
            $y = isset($seccion['pos_y']) ? $seccion['pos_y'] : 180; // Control de la posición vertical, por defecto en 180px

            // Añadir la imagen con la altura fija y el ancho proporcional
            $slide->createDrawingShape()
                ->setName('Foto 1')
                ->setDescription('Foto 1')
                ->setPath($seccion['foto1'])
                ->setHeight($fixedHeight) // Altura fija
                ->setWidth($imageWidth)  // Ancho proporcional
                ->setOffsetX($x)         // Posición horizontal
                ->setOffsetY($y);        // Posición vertical
        }

        // Si hay dos imágenes
        if (!empty($seccion['foto1']) && !empty($seccion['foto2'])) {
            $totalSpace = 860 - 20; // Ancho total disponible (con espacio entre imágenes)
            $imageWidth = ($totalSpace / 2); // Ajustar ancho para que ambas imágenes quepan
            $imageHeight = (720 / 960) * $imageWidth; // Mantener proporción de altura

            // Imagen 1
            $slide->createDrawingShape()
                ->setName('Foto 1')
                ->setDescription('Foto 1')
                ->setPath($seccion['foto1'])
                ->setHeight($imageHeight)
                ->setWidth($imageWidth)
                ->setOffsetX($x) // Alineación de la primera imagen
                ->setOffsetY($y);

            // Imagen 2
            $slide->createDrawingShape()
                ->setName('Foto 2')
                ->setDescription('Foto 2')
                ->setPath($seccion['foto2'])
                ->setHeight($imageHeight)
                ->setWidth($imageWidth)
                ->setOffsetX($x + $imageWidth + 20) // Espacio de 30px entre las imágenes
                ->setOffsetY($y);
        }
    }

    // Crear el nombre del archivo basado en el nombre del reporte
    $fileName = 'reporte_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $reporte['nombre']) . '.pptx';

    // Configurar encabezados HTTP para la descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Crear el objeto de escritura de PowerPoint
    $oWriter = IOFactory::createWriter($ppt, 'PowerPoint2007');

    // Generar y enviar el contenido del archivo directamente al navegador
    ob_start();
    $oWriter->save('php://output');
    ob_end_flush();

    // Finalizar el script para evitar que se envíen datos adicionales
    exit();
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
    $pdf->Image('images/portada-full2.jpg', 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0, false, false, false);

    // Establecer márgenes para el contenido
    $pdf->SetMargins(15, 0, 0); // Restablecer márgenes
    $pdf->SetAutoPageBreak(TRUE, 0); // Establecer un salto de página automático con un margen inferior

    // Ajustar posición del contenido
    $pdf->SetY(115); // Ajusta la posición vertical del contenido

    // Agregar el título y fecha sobre la imagen de fondo
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->Cell(0, 10, $reporte['nombre'], 0, 1, 'C');
    $pdf->SetTextColor(58, 58, 58);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6.5, 'Fecha: ' . $reporte['fecha'], 0, 1, 'C');

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
        $pdf->Image('images/fondo-full2.jpg', 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0, false, false, false);

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
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->SetY(21);
            $pdf->Cell(0, 10, '' . $seccion['direccion'], 0, 1);
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

if (isset($_POST['generar_pptx'])) {
    $id_reporte = $_POST['id_reporte'];

    // Cargar el reporte y las secciones de la base de datos
    $sql = "SELECT * FROM reportes_2024 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_reporte);
    $stmt->execute();
    $reporte = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $secciones_sql = "SELECT * FROM secciones WHERE reporte_id = ?";
    $stmt_secciones = $conn->prepare($secciones_sql);
    $stmt_secciones->bind_param("i", $id_reporte);
    $stmt_secciones->execute();
    $secciones = $stmt_secciones->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_secciones->close();

    // Generar el PPTX
    $pptxFile = generarPPTX($reporte, $secciones);

    // Descargar el PPTX
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($pptxFile) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($pptxFile));
    readfile($pptxFile);

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
        // Redirigir a "direccion_borrada.html" después de eliminar exitosamente
        header("Location: direccion_borrada.html");
        exit();

        // Eliminar el mensaje de éxito ya que la redirección se encargará de mostrar la nueva página
        // echo "<p>Dirección eliminada correctamente.</p>";
    } else {
        echo "<p>Error al eliminar la dirección: " . $stmt->error . "</p>"; // Añadí la devolución del error para mayor claridad
    }

    $stmt->close();
    $conn->close();
}

error_reporting(0);
$fecha = $_POST["fecha"];
echo $fecha . "<br>";


?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta name="robots" content="noindex">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link id="themeStylesheet" rel="stylesheet" href="dark4.css"> <!-- Hoja de estilo por defecto -->
    <title>Generador de Reportes</title>
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .flatpickr-calendar {
            background-color: #1b1d24;
            color: #fff;
            border-radius: 4px;
            border: 0px;

        }

        .flatpickr-day.inRange,
        .flatpickr-day.prevMonthDay.inRange,
        .flatpickr-day.nextMonthDay.inRange,
        .flatpickr-day.today.inRange,
        .flatpickr-day.prevMonthDay.today.inRange,
        .flatpickr-day.nextMonthDay.today.inRange,
        .flatpickr-day:hover,
        .flatpickr-day.prevMonthDay:hover,
        .flatpickr-day.nextMonthDay:hover,
        .flatpickr-day:focus,
        .flatpickr-day.prevMonthDay:focus,
        .flatpickr-day.nextMonthDay:focus {
            cursor: pointer;
            outline: 0;
            background: #393E5E;
            border: 0px;
            color: #fff;
        }

        .flatpickr-day {
            background: none;
            border: 1px solid transparent;
            border-radius: 4px;
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
            color: #fff !important;
            cursor: pointer;
            font-weight: 400;
            width: 14.2857143%;
            -webkit-flex-basis: 14.2857143%;
            -ms-flex-preferred-size: 14.2857143%;
            flex-basis: 14.2857143%;
            max-width: 39px;
            height: 39px;
            line-height: 39px;
            margin: 0;
            display: inline-block;
            position: relative;
            -webkit-box-pack: center;
            -webkit-justify-content: center;
            -ms-flex-pack: center;
            justify-content: center;
            text-align: center;
            font-family: Poppins !important;
            font-weight: normal;
            font-size: 90%;
        }

        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange,
        .flatpickr-day.selected.inRange,
        .flatpickr-day.startRange.inRange,
        .flatpickr-day.endRange.inRange,
        .flatpickr-day.selected:focus,
        .flatpickr-day.startRange:focus,
        .flatpickr-day.endRange:focus,
        .flatpickr-day.selected:hover,
        .flatpickr-day.startRange:hover,
        .flatpickr-day.endRange:hover,
        .flatpickr-day.selected.prevMonthDay,
        .flatpickr-day.startRange.prevMonthDay,
        .flatpickr-day.endRange.prevMonthDay,
        .flatpickr-day.selected.nextMonthDay,
        .flatpickr-day.startRange.nextMonthDay,
        .flatpickr-day.endRange.nextMonthDay {
            background: #333645;
            -webkit-box-shadow: none;
            box-shadow: none;
            color: #fff;
            border: none;
            font-family: Poppins !important;
            font-weight: normal;
            font-size: 90%;
        }

        .flatpickr-day.flatpickr-disabled,
        .flatpickr-day.flatpickr-disabled:hover,
        .flatpickr-day.prevMonthDay,
        .flatpickr-day.nextMonthDay,
        .flatpickr-day.notAllowed,
        .flatpickr-day.notAllowed.prevMonthDay,
        .flatpickr-day.notAllowed.nextMonthDay {
            color: rgba(134, 133, 133, 0.3) !important;
            background: transparent;
            border-color: transparent;
            cursor: default;
        }

        .flatpickr-months .flatpickr-prev-month,
        .flatpickr-months .flatpickr-next-month {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            text-decoration: none;
            cursor: pointer;
            position: absolute;
            top: 0;
            height: 34px;
            padding: 10px;
            z-index: 3;
            color: rgba(255, 251, 251, 0.9);
            fill: rgba(255, 255, 255, 0.9);
        }

        span.flatpickr-weekday {
            cursor: default;
            font-size: 90%;
            background: transparent;
            color: rgb(64, 140, 164);
            line-height: 1;
            margin: 0;
            text-align: center;
            display: block;
            -webkit-box-flex: 1;
            -webkit-flex: 1;
            -ms-flex: 1;
            flex: 1;
            font-weight: normal;
        }

        .flatpickr-current-month .flatpickr-monthDropdown-months {
            appearance: menulist;
            background: transparent;
            border: none;
            border-radius: 0;
            box-sizing: border-box;
            cursor: pointer;
            font-size: inherit;
            height: auto;
            line-height: inherit;
            margin: -1px 0 0 0;
            outline: none;
            padding: 0 0 0 .5ch;
            position: relative;
            vertical-align: initial;
            -webkit-box-sizing: border-box;
            -webkit-appearance: menulist;
            -moz-appearance: menulist;
            width: auto;
            color: #fff;
            font-weight: normal;
            font-size: 14px;
        }

        .flatpickr-current-month input.cur-year {
            background: transparent;
            -webkit-box-sizing: border-box;
            box-sizing: border-box;

            cursor: text;
            padding: 0 0 0 .5ch;
            margin: 0;
            display: inline-block;

            font-family: inherit;
            font-weight: normal;
            line-height: inherit;
            height: auto;
            border: 0;
            border-radius: 0;
            vertical-align: initial;
            -webkit-appearance: textfield;
            -moz-appearance: textfield;
            appearance: textfield;
            font-size: 14px;
            color: #fff;
        }

        .flatpickr-monthDropdown-months {
            background-color: #1b1d24 !important;
        }

        .flatpickr-calendar {
            border: none !important;
            box-shadow: none !important;
        }

        .flatpickr-calendar {
            margin-top: 5px;

        }

        .flatpickr-calendar::before,
        .flatpickr-calendar::after {
            display: none !important;

        }

        .flatpickr-calendar {
            padding: 3px;

            box-sizing: border-box;
            transform-origin: top left;

        }


        /* Estilo del switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            transform: rotate(180deg);
        }

        /* Ocultar checkbox */
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        /* Slider del switch */
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #797979;
            transition: 0.4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #393E5E;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }



        .hidden {
            display: none;
        }

        .preview-container {
            /* border: 1px solid #ccc; */
            max-height: 150px;
            overflow-y: auto;
            background: #1b1d24;
            position: absolute;
            z-index: 1000;
            width: calc(50% - 20px);
            margin-top: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            font-size: 12px;
            border-radius: 4px;
        }

        .preview-item {
            padding: 8px 0 8px 12px;
            cursor: pointer;
        }

        .preview-item:hover {
            background-color: #36518c;
        }


        .toggle-btn {
            background: linear-gradient(45deg, #585858, #7a7a7a, #999999);
            /* Gradiente de tonos grises */
            background-size: auto;
            background-size: 200% 200%;
            color: #fff;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 0px 0px 4px 4px;
            position: relative;
            transition: background-position 2s ease;
            margin-bottom: 20px;
        }

        .toggle-btn:hover {
            background-position: 100% 100%;
            /* Cambio de posición al hacer hover */
        }

        .direccion {
            padding: 0px 0px 10px 20px;
            border-radius: 4px;
            margin-top: 20px;
        }

        .direccion-contenido {
            max-height: 0;
            overflow: hidden;
            transition: max-height 1s ease;
            /* Transición más lenta y suave */
        }

        .direccion-contenido.visible {
            max-height: 1000px;
            /* Ajusta según el contenido */
        }
        
        #form-eliminar-reporte {
	margin-bottom: 20px;
}
    </style>

</head>

<body>

    <div class="container">
        <div class="center">

            <div class="cuerpo">
                <img src="images/smreportes.png" alt="Generador de Reportes Fotográficos" id="tituloImagen">
                <a id="seccionDestino">subir</a>

                <!-- Contenedor principal -->
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px;">
                    <!-- Contenedor selector_reportes -->
                    <div class="selector_reportes" style="display: flex; align-items: center; gap: 10px;">
                        <!-- Campo de búsqueda -->
                        <div class="buscador" style="display: flex; align-items: center; gap: 5px;">
                            <input type="text" id="search-reportes" placeholder="Filtro" oninput="filtrarReportesEditar()">
                        </div>

                        <!-- Selección de reportes para editar -->
                        <div class="reportes" style="display: flex; align-items: center; gap: 5px;">
                            <select id="select-reportes">
                                <option value="">-- Seleccionar Reporte --</option>
                                <?php while ($row = $reportes->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo htmlspecialchars($row['nombre']); ?> - <?php echo htmlspecialchars($row['fecha']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button id="cargarReporte">
                                <i class="fas fa-file-alt"></i> Cargar Reporte
                            </button>
                        </div>
                    </div>

                    <!-- Contenedor del switch con el label -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label for="themeSwitch" style="font-size: 14px;">Cambiar tema</label>
                        <label class="switch">
                            <input type="checkbox" id="themeSwitch">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Formulario principal -->
                <form action="" method="POST" enctype="multipart/form-data" id="form-reporte">

                    <div style="position: relative;" class="botones_top">
                        <!-- Botón de refrescar fuera del formulario funcionalmente, pero dentro visualmente -->
                        <button class="refrescar" type="button" onclick="location.reload()" style="position: absolute; top: 0; right: 0px;">
                            <i class="fa fa-refresh" aria-hidden="true"></i> Refrescar
                        </button>
                        <button type="submit" name="guardar_reporte" class="g2_reporte">
                            <i class="fas fa-save"></i> Guardar Reporte
                        </button>
                        <button type="submit" name="generar_pdf" class="btn-generar-pdf">
                            <i class="fas fa-file-pdf"></i> Generar PDF
                        </button>

                        <!-- Botón espejo -->
                        <button type="button" class="btn-pptx" id="mirrorButton">
                            <i class="fa fa-file-powerpoint" aria-hidden="true"></i>Generar PPTX
                        </button>

                        <button id="botonBajar"><i class="fas fa-arrow-down"></i> Bajar</button>
                    </div>

                    <div class="line"></div>

                    <input type="hidden" name="id_reporte" id="id_reporte">


                    <div class="container_fecha">
                        <div class="nombre">
                            <label for="nombre">Nombre</label>
                            <input type="text" name="nombre" required id="nombre">
                        </div>

                        <div class="fecha">
                            <label for="fecha">Fecha</label>
                            <input type="text" id="fecha" name="fecha" required>
                        </div>
                    </div>

                    <div class="line"></div>

                    <div id="secciones-container">

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


                        <!-- Botón original -->
                        <form id="originalForm" method="POST" action="index.php">
                            <input type="hidden" name="generar_pptx" value="1">
                            <button type="submit" class="btn-pptx" id="originalButton">
                                <i class="fa fa-file-powerpoint" aria-hidden="true"></i>Generar PPTX
                            </button>
                        </form>

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
                    <!-- Botón para desplegar/ocultar el contenido -->
                    <button type="button" id="toggleDireccion" class="toggle-btn">
                        <i class="fa fa-chevron-down"></i> <!-- Ícono de "desplegar" -->
                        Opciones Adicionales
                    </button>

                    <!-- Contenido que será ocultado/desplegado -->
                    <div id="direccionContenido" class="direccion-contenido">
                        <label for="nueva-direccion">Guardar Nueva Dirección</label>
                        <input type="text" id="nueva-direccion" placeholder="Guardar nueva dirección">
                        <button type="button" id="guardarDireccion"><i class="fas fa-map-marker-alt"></i> Guardar Dirección</button>

                        <label for="borrar-direccion" class="label-eliminar-direccion">Eliminar Dirección</label>
                        <form method="POST" action="" style="display: flex; align-items: center; gap: 10px;">
                            <input type="text" id="addressFilter" placeholder="Filtro" oninput="filterAddresses()" class="styled-input" />

                            <select name="address_id" id="addressSelect" class="styled-select">
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

                        <form id="form-eliminar-reporte" method="POST">
                            <label for="borrar-direccion">Eliminar Reportes</label>
                            <div class="selector-eliminar-reportes" style="display: flex; align-items: center; gap: 10px; margin-top: 20px;">
                                <input type="text" id="search-reporte" placeholder="Filtro" oninput="filtrarReportes()">

                                <div class="reportes" style="display: flex; align-items: center; gap: 5px;">
                                    <select id="select-eliminar-reportes" name="id_reporte_eliminar">
                                        <option value="">-- Seleccionar Reporte para Eliminar --</option>
                                        <?php
                                        $conn = getDatabaseConnection();
                                        $result = $conn->query("SELECT id, nombre, fecha FROM reportes_2024");
                                        while ($row = $result->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $row['id']; ?>" data-nombre="<?php echo htmlspecialchars($row['nombre']); ?>"><?php echo htmlspecialchars($row['nombre']); ?> - <?php echo htmlspecialchars($row['fecha']); ?></option>
                                        <?php
                                        endwhile;
                                        $conn->close();
                                        ?>
                                    </select>
                                    <button type="submit" name="eliminar_reporte" class="btn-eliminar-reporte">
                                        <i class="fa fa-trash" aria-hidden="true"></i> Eliminar Reporte
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </div>

    </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="flatpickr/flatpickr.min.js"></script>
    <script src="flatpickr/src/l10n/es.js"></script>



    <script>
        // Función para alternar la visibilidad con deslizamiento
        document.getElementById('toggleDireccion').addEventListener('click', function() {
            var direccionContenido = document.getElementById('direccionContenido');
            direccionContenido.classList.toggle('visible');
        });
    </script>


    <script>
        const themeSwitch = document.getElementById('themeSwitch');
        const themeStylesheet = document.getElementById('themeStylesheet');
        const clearCacheButton = document.getElementById('clearCacheButton');

        // Verificar si hay un tema guardado en localStorage
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            themeStylesheet.href = savedTheme; // Aplicar el tema guardado
            themeSwitch.checked = savedTheme === 'dark4.css'; // Ajustar posición del switch
        } else {
            // Si no hay tema guardado, establecer un tema por defecto (oscuro)
            const defaultTheme = 'dark4.css';
            themeStylesheet.href = defaultTheme;
            localStorage.setItem('theme', defaultTheme);
            themeSwitch.checked = false; // El switch inicia desmarcado
        }

        // Escuchar el evento de cambio en el switch
        themeSwitch.addEventListener('change', () => {
            const newTheme = themeSwitch.checked ? 'dark4.css' : 'light2.css';
            themeStylesheet.href = newTheme; // Cambiar el tema
            localStorage.setItem('theme', newTheme); // Guardar el tema seleccionado
        });
    </script>

    <script>
        flatpickr("#fecha", {
            dateFormat: "Y-m-d", // Formato de la fecha
            theme: "dark", // Establecer el tema oscuro
            locale: "es"
        });
    </script>

    <script>
        document.getElementById("mirrorButton").addEventListener("click", function() {
            // Crear y configurar un nuevo evento de clic
            var event = new MouseEvent("click", {
                bubbles: true,
                cancelable: true,
                view: window
            });
            // Despachar el evento de clic en el botón original
            document.getElementById("originalButton").dispatchEvent(event);
        });
    </script>

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
                    <option value="Espalda Pantalla LED Gran Formato">Espalda Pantalla LED Gran Formato</option>
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
    <!-- Contenedor dinámico de previsualización -->
    <div class="preview-container"></div>
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
<div class="line"></div>
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
            var formDirec = input.closest('.form-direc');
            var selectDireccion = formDirec.querySelector('select[name="direccion[]"]');
            var filterValue = input.value.toLowerCase();
            var previewContainer = formDirec.querySelector('.preview-container');

            // Crear contenedor si no existe
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.className = 'preview-container hidden'; // Inicia oculto
                formDirec.appendChild(previewContainer);
            }

            // Limpiar previsualización
            previewContainer.innerHTML = '';

            if (filterValue.trim() === '') {
                // Si el filtro está vacío, ocultar el contenedor
                previewContainer.classList.add('hidden');
                return;
            }

            // Mostrar contenedor si hay filtro
            previewContainer.classList.remove('hidden');

            // Iterar sobre las opciones del select para filtrar
            for (var i = 0; i < selectDireccion.options.length; i++) {
                var option = selectDireccion.options[i];
                var textoOpcion = option.text.toLowerCase();

                // Dividir el texto de la opción en palabras y comprobar coincidencias
                var palabras = textoOpcion.split(' ');
                var coincide = palabras.some(function(palabra) {
                    return palabra.startsWith(filterValue);
                });

                // Mostrar u ocultar la opción en el select
                option.style.display = coincide ? '' : 'none';

                // Agregar a la previsualización si coincide
                if (coincide && option.value !== "") {
                    var previewItem = document.createElement('div');
                    previewItem.textContent = option.text;
                    previewItem.className = 'preview-item';
                    previewItem.dataset.value = option.value;

                    // Evento al hacer clic en un elemento de previsualización
                    previewItem.addEventListener('click', function() {
                        input.value = ''; // Limpiar la casilla de filtro
                        selectDireccion.value = this.dataset.value; // Seleccionar en el <select>
                        mostrarDireccion(selectDireccion); // Mostrar en la casilla de texto
                        previewContainer.innerHTML = ''; // Limpiar previsualización
                        previewContainer.classList.add('hidden'); // Ocultar el contenedor
                    });

                    previewContainer.appendChild(previewItem);
                }
            }

            // Si no hay resultados, ocultar el contenedor
            if (previewContainer.innerHTML.trim() === '') {
                previewContainer.classList.add('hidden');
            }
        }

        // Función para mostrar la dirección seleccionada en la casilla de texto
        function mostrarDireccion(selectElement) {
            var formDirec = selectElement.closest('.form-direc');
            var direccionText = formDirec.querySelector('.direccion-text');
            direccionText.value = selectElement.options[selectElement.selectedIndex].text;
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
                <option value="Espalda Pantalla LED Gran Formato" ${seccion.seccion === 'Espalda Pantalla LED Gran Formato' ? 'selected' : ''}>Espalda Pantalla LED Gran Formato</option>
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
    <div class="line"></div>
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

        // Función para filtrar reportes para editar
        function filtrarReportesEditar() {
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


        //Filtro para eliminar reportes
        function filtrarReportes() {
            var input, filter, select, options, i, txtValue;
            input = document.getElementById("search-reporte");
            filter = input.value.toLowerCase();
            select = document.getElementById("select-eliminar-reportes");
            options = select.getElementsByTagName("option");

            for (i = 1; i < options.length; i++) {
                txtValue = options[i].getAttribute("data-nombre");
                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    options[i].style.display = "";
                } else {
                    options[i].style.display = "none";
                }
            }
        }
        
        
        
 

    </script>

</body>

</html>
