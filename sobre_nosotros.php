<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ======================================================
   CONEXIÓN A SQL SERVER
====================================================== */

$server   = "srvdbcacdev.database.windows.net";
$database = "dblotocacdev";
$username = "LotoAdmin";
$password = "LotAdmin1.";

try {

    $conn = new PDO(
        "sqlsrv:Server={$server};Database={$database}",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    /* Obtener información de Sobre Nosotros */
    $stmt = $conn->query(
        "SELECT *
         FROM paginaweb_sobre_nosotros
         WHERE id = 1"
    );

    $datos = $stmt->fetch();

    if (!$datos) {
        die("No se encontró el registro de Sobre Nosotros.");
    }

} catch (PDOException $e) {

    die("Error de conexión: " . $e->getMessage());

}


/* ======================================================
   PROCESAR TEST DE JUEGO RESPONSABLE
====================================================== */

$mensajeExito = "";
$tipoMensaje  = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["enviar_test"])
) {

    $JugoParaDeudas = $_POST["JugoParaDeudas"] ?? null;
    $ImpulsoVolverAJugar = $_POST["ImpulsoVolverAJugar"] ?? null;
    $PidioPrestamo = $_POST["PidioPrestamo"] ?? null;
    $Remordimientos = $_POST["Remordimientos"] ?? null;
    $QuedarseSinDinero = $_POST["QuedarseSinDinero"] ?? null;
    $RecuperarPerdidas = $_POST["RecuperarPerdidas"] ?? null;
    $TristezaPorJuego = $_POST["TristezaPorJuego"] ?? null;

    $respuestas = [
        $JugoParaDeudas,
        $ImpulsoVolverAJugar,
        $PidioPrestamo,
        $Remordimientos,
        $QuedarseSinDinero,
        $RecuperarPerdidas,
        $TristezaPorJuego
    ];

    if (in_array(null, $respuestas, true)) {

        $mensajeExito = "Debes responder todas las preguntas.";
        $tipoMensaje = "error";

    } else {

        try {

            $sql = "
                INSERT INTO Test_responsable
                (
                    JugoParaDeudas,
                    ImpulsoVolverAJugar,
                    PidioPrestamo,
                    Remordimientos,
                    QuedarseSinDinero,
                    RecuperarPerdidas,
                    TristezaPorJuego
                )
                VALUES
                (
                    :JugoParaDeudas,
                    :ImpulsoVolverAJugar,
                    :PidioPrestamo,
                    :Remordimientos,
                    :QuedarseSinDinero,
                    :RecuperarPerdidas,
                    :TristezaPorJuego
                )
            ";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                ":JugoParaDeudas" => $JugoParaDeudas,
                ":ImpulsoVolverAJugar" => $ImpulsoVolverAJugar,
                ":PidioPrestamo" => $PidioPrestamo,
                ":Remordimientos" => $Remordimientos,
                ":QuedarseSinDinero" => $QuedarseSinDinero,
                ":RecuperarPerdidas" => $RecuperarPerdidas,
                ":TristezaPorJuego" => $TristezaPorJuego
            ]);

            /*
             * Coloca aquí la URL completa de la Logic App
             * que ya utilizas actualmente.
             */
            $logicAppUrl = "https://prod-29.canadacentral.logic.azure.com:443/workflows/08ef2beb8f3b40f4afd2820e4db42d68/triggers/When_an_HTTP_request_is_received/paths/invoke?api-version=2016-10-01&sp=%2Ftriggers%2FWhen_an_HTTP_request_is_received%2Frun&sv=1.0&sig=dIwHyCaQqyiNPy1taKz3Jt3VHBlb3k12yz49asN1zmc";

            $data = [
                "JugoParaDeudas" => $JugoParaDeudas,
                "ImpulsoVolverAJugar" => $ImpulsoVolverAJugar,
                "PidioPrestamo" => $PidioPrestamo,
                "Remordimientos" => $Remordimientos,
                "QuedarseSinDinero" => $QuedarseSinDinero,
                "RecuperarPerdidas" => $RecuperarPerdidas,
                "TristezaPorJuego" => $TristezaPorJuego
            ];

            if (
                !empty($logicAppUrl) &&
                $logicAppUrl !== "https://prod-29.canadacentral.logic.azure.com:443/workflows/08ef2beb8f3b40f4afd2820e4db42d68/triggers/When_an_HTTP_request_is_received/paths/invoke?api-version=2016-10-01&sp=%2Ftriggers%2FWhen_an_HTTP_request_is_received%2Frun&sv=1.0&sig=dIwHyCaQqyiNPy1taKz3Jt3VHBlb3k12yz49asN1zmc"
            ) {

                $ch = curl_init($logicAppUrl);

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json"
                    ],
                    CURLOPT_POSTFIELDS => json_encode(
                        $data,
                        JSON_UNESCAPED_UNICODE
                    ),
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_TIMEOUT => 30
                ]);

                $respuestaLogicApp = curl_exec($ch);
                $errorCurl = curl_error($ch);
                $codigoHttp = curl_getinfo(
                    $ch,
                    CURLINFO_HTTP_CODE
                );

                curl_close($ch);

                if ($errorCurl) {

                    $mensajeExito =
                        "El test se guardó, pero ocurrió un error al enviar el correo.";

                    $tipoMensaje = "error";

                } elseif (
                    $codigoHttp < 200 ||
                    $codigoHttp >= 300
                ) {

                    $mensajeExito =
                        "El test se guardó, pero la notificación no fue aceptada.";

                    $tipoMensaje = "error";

                } else {

                    $mensajeExito =
                        "Test enviado correctamente y correo enviado.";

                    $tipoMensaje = "exito";

                }

            } else {

                $mensajeExito =
                    "El test se guardó correctamente.";

                $tipoMensaje = "exito";

            }

        } catch (PDOException $e) {

            $mensajeExito =
                "Error al guardar el test: " .
                $e->getMessage();

            $tipoMensaje = "error";

        }

    }

}


/* ======================================================
   FUNCIÓN PARA MOSTRAR INFORMACIÓN DE FORMA SEGURA
====================================================== */

function escapar($valor)
{
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Loto - Sobre Nosotros</title>

    <style>

        /* ==================================================
           CONFIGURACIÓN GENERAL
        ================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family:
                "HelveticaRounded LT Std Bd",
                Arial,
                Helvetica,
                sans-serif;
        }

        html {
            width: 100%;
            overflow-x: hidden;
        }

        body {
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
            background: #ffffff;
            color: #333333;
        }

        img {
            max-width: 100%;
        }


       /* ==================================================
   BANNER PRINCIPAL
================================================== */

.banner-principal {
    width: 100%;
    padding: 15px 30px;
    margin: 0;
    background: #ffffff;
    overflow: visible;
}

.banner-principal img {
    display: block;
    width: 100%;
    height: auto;
    max-height: none;
    object-fit: contain;
    object-position: center;
    border-radius: 0;
    margin: 0 auto;
}

/* Evita que el banner quede escondido debajo
   del menú/header del sitio */
.banner-principal {
    position: relative;
    z-index: 1;
}


        /* ==================================================
           QUIÉNES SOMOS
        ================================================== */

        .seccion-quienes {
            width: 100%;
            background: #ffffff;
        }

        .container-quienes {
            width: 100%;
            max-width: 1430px;
            margin: 0 auto;
            padding: 40px;
            background: #ffffff;
        }

        .quienes-text {
            margin-bottom: 40px;
        }

        .quienes-text h2 {
            margin-bottom: 15px;
            color: #ff7b00;
            font-size: 32px;
            font-weight: bold;
        }

        .quienes-text p {
            color: #005bbb;
            font-size: 18px;
            font-weight: 600;
            line-height: 1.6;
        }


        /* ==================================================
           MÉTRICAS
        ================================================== */

        .orange-rect-wrapper {
            position: relative;
            margin-bottom: 30px;
        }

        .orange-rect {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            align-items: center;
            justify-items: center;
            gap: 20px;
            padding: 20px 40px;
            background: #ff7b00;
            border-radius: 10px;
        }

        .metric {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            width: 100%;
            color: #ffffff;
        }

        .metric img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .metric-text {
            flex: 1;
        }

        .metric-text p {
            margin: 0;
            color: #ffffff;
            font-size: 20px;
            font-weight: 600;
            text-align: center;
        }

        .metric-text p strong {
            font-size: 28px;
            font-weight: 900;
        }


        /* ==================================================
           CARDS
        ================================================== */

        .cuadros-extra {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .card-extra {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80px;
            padding: 20px;
            border-radius: 12px;
            color: #ffffff;
            font-size: 18px;
            line-height: 1.3;
            text-align: center;
        }

        .diversion {
            background: #4bb543;
        }

        .confianza {
            background: #3fa1e4;
        }

        .esperanza {
            background: #ff6eb4;
        }

        .solidaridad {
            background: #ffbb00;
        }


        /* ==================================================
           BANNER SECUNDARIO
        ================================================== */

        .banner-secundario {
            width: 100%;
            margin: 0;
            padding: 15px;
            overflow: hidden;
            background: #081b91;
        }

        .banner-secundario img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 520px;
            object-fit: cover;
            object-position: center;
            border-radius: 20px;
        }


        /* ==================================================
           RESPONSABILIDAD
        ================================================== */

        .responsabilidad-section {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px 50px;
            background: #ffffff;
        }

        .responsabilidad-title {
            margin-bottom: 35px;
            color: #ff9900;
            font-size: 32px;
            font-weight: bold;
            text-align: center;
        }

        .container-blanco {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.10);
        }

        .responsabilidad-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .resp-item {
            display: flex;
            align-items: center;
            gap: 25px;
            width: 100%;
            min-height: 120px;
            padding: 25px 30px;
            border-radius: 20px;
            color: #ffffff;
            font-size: 20px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transition:
                transform 0.3s ease,
                box-shadow 0.3s ease;
        }

        .resp-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.20);
        }

        .resp-item:nth-child(even) {
            flex-direction: row-reverse;
        }

        .resp-item p {
            flex: 1;
            margin: 0;
            line-height: 1.4;
            text-align: center;
        }

        .resp-item .icon {
            width: 90px;
            height: 90px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .naranja {
            background:
                linear-gradient(
                    135deg,
                    #ff8c00,
                    #ff5e00
                );
        }

        .azul {
            background:
                linear-gradient(
                    135deg,
                    #4facfe,
                    #00c6ff
                );
        }

        .morado {
            background:
                linear-gradient(
                    135deg,
                    #a18cd1,
                    #f1a6dc
                );
        }

        .verde {
            background:
                linear-gradient(
                    135deg,
                    #0f9b0f,
                    #a8e063
                );
        }

        .rosado {
            background:
                linear-gradient(
                    135deg,
                    #d63384,
                    #ff9ecf
                );
        }


        /* ==================================================
           MISIÓN Y JUEGO DE AZAR
        ================================================== */

        .mision-azar-section {
            width: 100%;
            padding: 20px 20px 60px;
            background: #ffffff;
        }

        .mision-text-center h2 {
            margin-bottom: 15px;
            color: #005bbb;
            font-size: 36px;
            font-weight: bold;
            text-align: center;
        }

        .mision-text-center p {
            margin-bottom: 30px;
            color: #333333;
            font-size: 18px;
            line-height: 1.6;
            text-align: center;
        }

        .container-azar-blanco {
            margin-bottom: 30px;
            padding: 30px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.10);
        }

        .container-azar-blanco h2 {
            margin-bottom: 15px;
            color: #ff9900;
            font-size: 28px;
            font-weight: bold;
        }

        .container-azar-blanco p {
            color: #333333;
            font-size: 16px;
            line-height: 1.6;
        }


        /* ==================================================
           ACORDEONES
        ================================================== */

        .acordeon-container {
            margin-top: 20px;
        }

        .acordeon-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 10px;
            padding: 18px 20px;
            border: none;
            border-radius: 12px;
            outline: none;
            background: #ff9900;
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            text-align: left;
            cursor: pointer;
        }

        .acordeon-btn .circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            margin-left: 15px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #ffffff;
            color: #ff9900;
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .acordeon-btn.active .circle {
            transform: rotate(180deg);
        }

        .acordeon-content {
            display: none;
            margin-bottom: 10px;
            padding: 20px;
            border-radius: 12px;
            background: #fff3e0;
            color: #333333;
            font-size: 16px;
            line-height: 1.6;
        }

        .acordeon-content.activo {
            display: block;
        }


        /* ==================================================
           BOTÓN DEL TEST
        ================================================== */

        .boton-test {
            width: 100%;
        }

        .btn-azul {
            display: block;
            margin: 40px auto 0;
            padding: 15px 25px;
            border: none;
            border-radius: 12px;
            background: #005bbb;
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            line-height: 1.4;
            cursor: pointer;
            transition:
                background-color 0.3s ease,
                transform 0.3s ease;
        }

        .btn-azul:hover {
            background: #003f7f;
            transform: translateY(-2px);
        }


        /* ==================================================
           MODAL DEL TEST
        ================================================== */

        .modal-test {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.65);
        }

        .modal-test.abierto {
            display: flex;
        }

        .modal-contenido {
            position: relative;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            padding: 30px;
            overflow-y: auto;
            border-radius: 20px;
            background: #ffffff;
            animation: aparecer 0.3s ease;
        }

        @keyframes aparecer {
            from {
                opacity: 0;
                transform: scale(0.92);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-contenido h2 {
            margin-bottom: 25px;
            padding: 0 30px;
            color: #005bbb;
            font-size: 26px;
            font-weight: bold;
            text-align: center;
        }

        .cerrar-modal {
            position: absolute;
            top: 12px;
            right: 18px;
            border: none;
            background: transparent;
            color: #333333;
            font-size: 32px;
            line-height: 1;
            cursor: pointer;
        }

        .pregunta {
            margin-bottom: 20px;
            padding: 18px;
            border-radius: 15px;
            background: #f4f7ff;
        }

        .pregunta p {
            margin-bottom: 12px;
            color: #003f7f;
            font-size: 16px;
            font-weight: bold;
            line-height: 1.4;
        }

        .opciones {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .opciones label {
            display: flex;
            align-items: center;
            color: #333333;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .opciones input {
            margin-right: 8px;
            transform: scale(1.25);
        }


        /* ==================================================
           MENSAJES
        ================================================== */

        .alerta {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 420px;
            padding: 15px 25px;
            border-radius: 12px;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            line-height: 1.4;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            animation:
                mostrarAlerta 0.5s forwards,
                ocultarAlerta 0.5s 7s forwards;
        }

        .alerta-exito {
            background: #4bb543;
        }

        .alerta-error {
            background: #d9363e;
        }

        @keyframes mostrarAlerta {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes ocultarAlerta {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }


        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 900px) {

            .container-quienes {
                padding: 30px 20px;
            }

            .container-blanco {
                padding: 30px;
            }

            .mision-text-center h2 {
                font-size: 28px;
            }

            .container-azar-blanco h2 {
                font-size: 24px;
            }

        }

        @media (max-width: 768px) {

           @media (max-width: 768px) {

    .banner-principal {
        width: 100%;
        padding: 8px;
        margin: 0;
    }


    
    .banner-principal img {
        display: block;
        width: 100%;
        height: auto;
        max-height: none;
        object-fit: contain;
        object-position: center;
        border-radius: 0;
    }
}


            .container-quienes {
                width: 100%;
                margin: 0;
                padding: 25px 15px;
            }

            .quienes-text {
                margin-bottom: 25px;
            }

            .quienes-text h2 {
                font-size: 24px;
            }

            .quienes-text p {
                font-size: 14px;
                line-height: 1.6;
            }

            .orange-rect {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px 15px;
            }

            .metric {
                flex-direction: row;
                justify-content: center;
            }

            .metric img {
                width: 55px;
                height: 55px;
            }

            .metric-text p {
                font-size: 14px;
            }

            .cuadros-extra {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .card-extra {
                min-height: 90px;
                padding: 14px 10px;
                font-size: 12px;
            }

            .banner-secundario {
                padding: 10px;
            }

            .banner-secundario img {
                width: 100%;
                height: auto;
                max-height: none;
                object-fit: contain;
                border-radius: 12px;
            }

            .responsabilidad-section {
                padding: 25px 12px 40px;
            }

            .responsabilidad-title {
                margin-bottom: 25px;
                font-size: 23px;
            }

            .container-blanco {
                padding: 20px 15px;
                border-radius: 15px;
            }

            .responsabilidad-container {
                gap: 18px;
            }

            .resp-item,
            .resp-item:nth-child(even) {
                flex-direction: column;
                min-height: unset;
                padding: 20px;
                font-size: 16px;
                text-align: center;
            }

            .resp-item .icon {
                width: 100px;
                height: 100px;
            }

            .mision-azar-section {
                padding: 10px 12px 40px;
            }

            .mision-text-center h2 {
                font-size: 24px;
            }

            .mision-text-center p {
                font-size: 16px;
            }

            .container-azar-blanco {
                padding: 20px;
            }

            .container-azar-blanco h2 {
                font-size: 21px;
            }

            .acordeon-btn {
                padding: 15px;
                font-size: 15px;
            }

            .acordeon-btn .circle {
                width: 26px;
                height: 26px;
                font-size: 13px;
            }

            .modal-contenido {
                padding: 25px 15px;
            }

            .modal-contenido h2 {
                padding: 0 25px;
                font-size: 22px;
            }

            .alerta {
                top: 15px;
                right: 15px;
                left: 15px;
                max-width: none;
            }

        }

        @media (max-width: 480px) {

            .banner-principal {
                padding: 6px;
            }

            .banner-secundario {
                padding: 8px;
            }

            .cuadros-extra {
                grid-template-columns: repeat(2, 1fr);
            }

            .card-extra {
                min-height: 100px;
                font-size: 11px;
            }

            .opciones {
                gap: 20px;
            }

            .btn-azul {
                width: 100%;
                font-size: 15px;
            }

        }

    </style>

</head>

<body>

    <!-- ==================================================
         BANNER 1 NUEVO
    ================================================== -->

    <?php if (!empty($datos["bannerEncabezadoImagen"])): ?>

        <header class="banner-principal">

            <img
                src="<?= escapar(
                    $datos["bannerEncabezadoImagen"]
                ) ?>"
                alt="Banner principal de Sobre Nosotros"
            >

        </header>

    <?php endif; ?>


    <!-- ==================================================
         QUIÉNES SOMOS
    ================================================== -->

    <section class="seccion-quienes">

        <div class="container-quienes">

            <div class="quienes-text">

                <h2>¿Quiénes somos?</h2>

                <p>
                    <?= nl2br(
                        escapar(
                            $datos["quienesSomosTexto"] ?? ""
                        )
                    ) ?>
                </p>

            </div>


            <!-- MÉTRICAS -->

            <div class="orange-rect-wrapper">

                <div class="orange-rect">

                    <div class="metric">

                        <?php if (!empty($datos["metric1Imagen"])): ?>

                            <img
                                src="<?= escapar(
                                    $datos["metric1Imagen"]
                                ) ?>"
                                alt="Ícono de empleos directos"
                            >

                        <?php endif; ?>

                        <div class="metric-text">

                            <p>
                                <?= escapar(
                                    $datos["metric1Texto"] ?? ""
                                ) ?>
                            </p>

                        </div>

                    </div>


                    <div class="metric">

                        <?php if (!empty($datos["metric2Imagen"])): ?>

                            <img
                                src="<?= escapar(
                                    $datos["metric2Imagen"]
                                ) ?>"
                                alt="Ícono de empleos indirectos"
                            >

                        <?php endif; ?>

                        <div class="metric-text">

                            <p>
                                <?= escapar(
                                    $datos["metric2Texto"] ?? ""
                                ) ?>
                            </p>

                        </div>

                    </div>

                </div>

            </div>


            <!-- CARDS -->

            <div class="cuadros-extra">

                <div class="card-extra diversion">
                    <?= escapar(
                        $datos["cardDiversion"] ?? ""
                    ) ?>
                </div>

                <div class="card-extra confianza">
                    <?= escapar(
                        $datos["cardConfianza"] ?? ""
                    ) ?>
                </div>

                <div class="card-extra esperanza">
                    <?= escapar(
                        $datos["cardEsperanza"] ?? ""
                    ) ?>
                </div>

                <div class="card-extra solidaridad">
                    <?= escapar(
                        $datos["cardSolidaridad"] ?? ""
                    ) ?>
                </div>

            </div>

        </div>

    </section>


    <!-- ==================================================
         BANNER 2 ANTERIOR
    ================================================== -->

    <?php if (!empty($datos["bannerImagen"])): ?>

        <section class="banner-secundario">

            <img
                src="<?= escapar(
                    $datos["bannerImagen"]
                ) ?>"
                alt="Banner secundario de Sobre Nosotros"
            >

        </section>

    <?php endif; ?>


    <!-- ==================================================
         CÓMO JUGAR CON RESPONSABILIDAD
    ================================================== -->

    <section class="responsabilidad-section">

        <h2 class="responsabilidad-title">
            ¿CÓMO JUGAR CON RESPONSABILIDAD?
        </h2>

        <div class="container-blanco">

            <div class="responsabilidad-container">


                <!-- RESPONSABILIDAD 1 -->

                <div class="resp-item naranja">

                    <?php if (!empty($datos["resp1Imagen"])): ?>

                        <img
                            src="<?= escapar(
                                $datos["resp1Imagen"]
                            ) ?>"
                            class="icon"
                            alt="Consejo de juego responsable 1"
                        >

                    <?php endif; ?>

                    <p>
                        <?= escapar(
                            $datos["resp1Texto"] ?? ""
                        ) ?>
                    </p>

                </div>


                <!-- RESPONSABILIDAD 2 -->

                <div class="resp-item azul">

                    <?php if (!empty($datos["resp2Imagen"])): ?>

                        <img
                            src="<?= escapar(
                                $datos["resp2Imagen"]
                            ) ?>"
                            class="icon"
                            alt="Consejo de juego responsable 2"
                        >

                    <?php endif; ?>

                    <p>
                        <?= escapar(
                            $datos["resp2Texto"] ?? ""
                        ) ?>
                    </p>

                </div>


                <!-- RESPONSABILIDAD 3 -->

                <div class="resp-item morado">

                    <?php if (!empty($datos["resp3Imagen"])): ?>

                        <img
                            src="<?= escapar(
                                $datos["resp3Imagen"]
                            ) ?>"
                            class="icon"
                            alt="Consejo de juego responsable 3"
                        >

                    <?php endif; ?>

                    <p>
                        <?= escapar(
                            $datos["resp3Texto"] ?? ""
                        ) ?>
                    </p>

                </div>


                <!-- RESPONSABILIDAD 4 -->

                <div class="resp-item verde">

                    <?php if (!empty($datos["resp4Imagen"])): ?>

                        <img
                            src="<?= escapar(
                                $datos["resp4Imagen"]
                            ) ?>"
                            class="icon"
                            alt="Consejo de juego responsable 4"
                        >

                    <?php endif; ?>

                    <p>
                        <?= escapar(
                            $datos["resp4Texto"] ?? ""
                        ) ?>
                    </p>

                </div>


                <!-- RESPONSABILIDAD 5 FIJA -->

                <div class="resp-item rosado">

                    <img
                        src="/ImagesSV/Iconos-05.png"
                        class="icon"
                        alt="Relaciones familiares y sociales"
                    >

                    <p>
                        NO DEJÉS QUE EL JUEGO AFECTE TU RELACIÓN
                        CON TU FAMILIA Y AMIGOS.
                    </p>

                </div>

            </div>

        </div>

    </section>


    <!-- ==================================================
         MISIÓN Y JUEGO RESPONSABLE
    ================================================== -->

    <section class="mision-azar-section">

        <div class="container-blanco">


            <!-- MISIÓN -->

            <div class="mision-text-center">

                <h2>
                    NUESTRA MISIÓN ES QUE JUGUÉS CON RESPONSABILIDAD
                </h2>

                <p>
                    <?= nl2br(
                        escapar(
                            $datos["misionTexto"] ?? ""
                        )
                    ) ?>
                </p>

            </div>


            <!-- QUÉ ES EL JUEGO DE AZAR -->

            <div class="container-azar-blanco">

                <h2>
                    <?= escapar(
                        $datos["juegoAzarTitulo"] ?? ""
                    ) ?>
                </h2>

                <p>
                    <?= nl2br(
                        escapar(
                            $datos["juegoAzarTexto"] ?? ""
                        )
                    ) ?>
                </p>

            </div>


            <!-- ACORDEONES -->

            <div class="acordeon-container">

                <div class="acordeon">


                    <button
                        type="button"
                        class="acordeon-btn"
                    >

                        <span>
                            <?= escapar(
                                $datos["acordeon1Titulo"] ?? ""
                            ) ?>
                        </span>

                        <span class="circle">▼</span>

                    </button>

                    <div class="acordeon-content">

                        <p>
                            <?= nl2br(
                                escapar(
                                    $datos["acordeon1Texto"] ?? ""
                                )
                            ) ?>
                        </p>

                    </div>


                    <button
                        type="button"
                        class="acordeon-btn"
                    >

                        <span>
                            <?= escapar(
                                $datos["acordeon2Titulo"] ?? ""
                            ) ?>
                        </span>

                        <span class="circle">▼</span>

                    </button>

                    <div class="acordeon-content">

                        <p>
                            <?= nl2br(
                                escapar(
                                    $datos["acordeon2Texto"] ?? ""
                                )
                            ) ?>
                        </p>

                    </div>


                    <button
                        type="button"
                        class="acordeon-btn"
                    >

                        <span>
                            <?= escapar(
                                $datos["acordeon3Titulo"] ?? ""
                            ) ?>
                        </span>

                        <span class="circle">▼</span>

                    </button>

                    <div class="acordeon-content">

                        <p>
                            <?= nl2br(
                                escapar(
                                    $datos["acordeon3Texto"] ?? ""
                                )
                            ) ?>
                        </p>

                    </div>

                </div>

            </div>


            <!-- BOTÓN PARA ABRIR TEST -->

            <div class="boton-test">

                <button
                    type="button"
                    class="btn-azul"
                    id="abrirTest"
                >
                    REALIZA UN TEST PARA AUTOEVALUACIÓN
                    <br>
                    SOBRE JUEGO RESPONSABLE
                </button>

            </div>

        </div>

    </section>


    <!-- ==================================================
         MODAL DEL TEST
    ================================================== -->

    <div
        class="modal-test"
        id="modalTest"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloTest"
    >

        <div class="modal-contenido">

            <button
                type="button"
                class="cerrar-modal"
                id="cerrarTest"
                aria-label="Cerrar test"
            >
                &times;
            </button>

            <h2 id="tituloTest">
                Test para autoevaluación sobre juego responsable
            </h2>

            <form method="post" action="">


                <div class="pregunta">

                    <p>
                        ¿Has jugado alguna vez para pagar tus deudas
                        o resolver dificultades financieras?
                    </p>

                    <div class="opciones">

                        <label>
                            <input
                                type="radio"
                                name="JugoParaDeudas"
                                value="Sí"
                                required
                            >
                            Sí
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="JugoParaDeudas"
                                value="No"
                            >
                            No
                        </label>

                    </div>

                </div>


                <div class="pregunta">

                    <p>
                        Después de ganar, ¿sentís impulso de volver
                        a jugar?
                    </p>

                    <div class="opciones">

                        <label>
                            <input
                                type="radio"
                                name="ImpulsoVolverAJugar"
                                value="Sí"
                                required
                            >
                            Sí
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="ImpulsoVolverAJugar"
                                value="No"
                            >
                            No
                        </label>

                    </div>

                </div>


                <div class="pregunta">

                    <p>
                        ¿Has pedido dinero prestado para jugar?
                    </p>

                    <div class="opciones">

                        <label>
                            <input
                                type="radio"
                                name="PidioPrestamo"
                                value="Sí"
                                required
                            >
                            Sí
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="PidioPrestamo"
                                value="No"
                            >
                            No
                        </label>

                    </div>

                </div>


                <div class="pregunta">

                    <p>
                        ¿Has sentido remordimientos después de jugar?
                    </p>

                    <div class="opciones">

                        <label>
                            <input
                                type="radio"
                                name="Remordimientos"
                                value="Sí"
                                required
                            >
                            Sí
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="Remordimientos"
                                value="No"
                            >
                            No
                        </label>

                    </div>

                </div>


                <div class="pregunta">

                    <p>
                        ¿Jugás hasta quedarte sin dinero?
                    </p>

                    <div class="opciones">

                        <label>
                            <input
                                type="radio"
                                name="QuedarseSinDinero"
                                value="Sí"
                                required
                            >
                            Sí
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="QuedarseSinDinero"
                                value="No"
                            >
                            No
                        </label>

                    </div>

                </div>


                <div class="pregunta">

                    <p>
                        Después de perder, ¿sentís que debés recuperar
                        lo perdido?
                    </p>

                    <div class="opciones">

                        <label>
                            <input
                                type="radio"
                                name="RecuperarPerdidas"
                                value="Sí"
                                required
                            >
                            Sí
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="RecuperarPerdidas"
                                value="No"
                            >
                            No
                        </label>

                    </div>

                </div>


                <div class="pregunta">

                    <p>
                        ¿El juego ha causado tristeza en tu vida?
                    </p>

                    <div class="opciones">

                        <label>
                            <input
                                type="radio"
                                name="TristezaPorJuego"
                                value="Sí"
                                required
                            >
                            Sí
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="TristezaPorJuego"
                                value="No"
                            >
                            No
                        </label>

                    </div>

                </div>


                <button
                    type="submit"
                    name="enviar_test"
                    class="btn-azul"
                >
                    Enviar Test
                </button>

            </form>

        </div>

    </div>


    <!-- ==================================================
         MENSAJE DE RESULTADO
    ================================================== -->

    <?php if (!empty($mensajeExito)): ?>

        <div
            class="alerta <?= $tipoMensaje === "exito"
                ? "alerta-exito"
                : "alerta-error" ?>"
            id="alertaResultado"
        >
            <?= escapar($mensajeExito) ?>
        </div>

    <?php endif; ?>


    <!-- ==================================================
         JAVASCRIPT GENERAL
    ================================================== -->

    <script>

        document.addEventListener(
            "DOMContentLoaded",
            function () {

                /* Acordeones */

                const botonesAcordeon =
                    document.querySelectorAll(".acordeon-btn");

                botonesAcordeon.forEach(function (boton) {

                    boton.addEventListener(
                        "click",
                        function () {

                            const contenido =
                                boton.nextElementSibling;

                            boton.classList.toggle("active");

                            if (contenido) {
                                contenido.classList.toggle("activo");
                            }

                        }
                    );

                });


                /* Modal del test */

                const botonAbrir =
                    document.getElementById("abrirTest");

                const modal =
                    document.getElementById("modalTest");

                const botonCerrar =
                    document.getElementById("cerrarTest");

                function abrirModal() {

                    if (!modal) {
                        return;
                    }

                    modal.classList.add("abierto");

                    document.body.style.overflow = "hidden";

                }

                function cerrarModal() {

                    if (!modal) {
                        return;
                    }

                    modal.classList.remove("abierto");

                    document.body.style.overflow = "";

                }

                if (botonAbrir) {

                    botonAbrir.addEventListener(
                        "click",
                        abrirModal
                    );

                }

                if (botonCerrar) {

                    botonCerrar.addEventListener(
                        "click",
                        cerrarModal
                    );

                }

                if (modal) {

                    modal.addEventListener(
                        "click",
                        function (evento) {

                            if (evento.target === modal) {
                                cerrarModal();
                            }

                        }
                    );

                }

                document.addEventListener(
                    "keydown",
                    function (evento) {

                        if (evento.key === "Escape") {
                            cerrarModal();
                        }

                    }
                );


                /* Ocultar alerta */

                const alerta =
                    document.getElementById("alertaResultado");

                if (alerta) {

                    setTimeout(function () {
                        alerta.style.display = "none";
                    }, 7500);

                }

            }
        );


        /*
         * Evita volver a enviar el formulario
         * si el usuario actualiza la página.
         */

        if (window.history.replaceState) {

            window.history.replaceState(
                null,
                document.title,
                window.location.href
            );

        }

    </script>


    <!-- ==================================================
         FRESHCHAT
    ================================================== -->

    <script>

        function initFreshChat() {

            if (
                typeof window.fcWidget === "undefined"
            ) {
                return;
            }

            window.fcWidget.init({
                token:
                    "e25e83b5-ef96-41b9-b3d4-a949ffada641",
                host:
                    "https://loteradehonduras-help.freshchat.com",
                widgetUuid:
                    "7bb0713e-e6f3-41d8-945d-9c6d399b2166",
                locale: "es"
            });

        }

        function initializeFreshChat(documento, idScript) {

            let script;

            if (documento.getElementById(idScript)) {

                initFreshChat();

                return;

            }

            script = documento.createElement("script");

            script.id = idScript;
            script.async = true;

            script.src =
                "https://loteradehonduras-help.freshchat.com/js/widget.js";

            script.onload = initFreshChat;

            documento.head.appendChild(script);

        }

        function iniciarFreshChat() {

            initializeFreshChat(
                document,
                "Freshchat-js-sdk"
            );

        }

        if (window.addEventListener) {

            window.addEventListener(
                "load",
                iniciarFreshChat,
                false
            );

        } else if (window.attachEvent) {

            window.attachEvent(
                "onload",
                iniciarFreshChat
            );

        }

    </script>

</body>

</html>


















































