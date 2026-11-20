<?php
/* =========================================================
   LOTO AGUINALDO - Página de promoción
   Sin JavaScript: el último número ganador se lee directo de la base
   de datos y el carrusel de ganadores funciona solo con CSS.
   ========================================================= */

// ---------- CONFIGURACIÓN EDITABLE ----------
$reglamentoUrl = '/ImagesSV/documentos/Términos y condiciones Loto Aguinaldo 2026.pdf'; // PDF del reglamento
$imgDir        = '/ImagesSV';
$ganadoresDir  = $imgDir . '/ganadores-aguinaldo';                    // Carpeta con las fotos de los ganadores

// Fotos fijas (opcional). Si la carpeta de arriba no tiene fotos, se usan estas.
// Pueden ser rutas del sitio o URLs completas (por ejemplo, de Azure Blob).
$ganadoresManual = [
    // '/ImagesSV/ganadores-aguinaldo/ganador1.jpg',
    // 'https://tucuenta.blob.core.windows.net/imagenes/ganador2.jpg',
];
$PAIS          = 'EL SALVADOR';   // País de la promoción ('' = no filtrar por país)

if (!function_exists('la_e')) {
    function la_e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

// ---------- ÚLTIMO NÚMERO GANADOR (misma tabla que La Diaria) ----------
$digitos = ['0', '0', '0', '0', '0'];
$numero  = '00000';
try {
    require_once __DIR__ . '/config/connection.php';
    if (!empty($conn)) {
        $sql = "
            SELECT TOP 1 *
            FROM numeros_ganadores_sorteos_prod
            WHERE REPLACE(UPPER(LTRIM(RTRIM(game_name))) COLLATE Latin1_General_CI_AI, ' ', '') = 'LOTOAGUINALDO'
        ";
        $params = [];
        if ($PAIS !== '') {
            $sql .= " AND REPLACE(UPPER(LTRIM(RTRIM(pais))) COLLATE Latin1_General_CI_AI, ' ', '') = ?";
            $params[] = str_replace(' ', '', strtoupper($PAIS));
        }
        $sql .= " ORDER BY draw_date DESC, draw_time DESC, draw_number DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // par1 o, si viene vacío, el primer valor de result_raw
            $base = $row['par1'] ?? null;
            if (($base === null || trim((string) $base) === '') && !empty($row['result_raw'])) {
                $partes = array_map('trim', explode(',', (string) $row['result_raw']));
                $base = $partes[0] ?? '0';
            }
            $base = preg_replace('/\D/', '', (string) $base);
            if ($base !== '') {
                $numero  = str_pad(substr($base, -5), 5, '0', STR_PAD_LEFT);   // 5 dígitos
                $digitos = str_split($numero);
            }
        }
    }
} catch (Throwable $e) {
    error_log(__FILE__ . ': ' . $e->getMessage());
}

// ---------- FOTOS DE GANADORES (más recientes primero) ----------
// Busca la carpeta junto a esta página y, si no, en la raíz del sitio
$carpetaFisica = null;
foreach ([__DIR__ . $ganadoresDir, rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . $ganadoresDir] as $c) {
    if (is_dir($c)) { $carpetaFisica = $c; break; }
}
$ganadores = [];
if ($carpetaFisica) {
    foreach (scandir($carpetaFisica) ?: [] as $f) {
        if (preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)) {
            $ganadores[] = $carpetaFisica . '/' . $f;
        }
    }
    usort($ganadores, fn($a, $b) => filemtime($b) <=> filemtime($a));   // más recientes primero
    $ganadores = array_map(fn($f) => $ganadoresDir . '/' . rawurlencode(basename($f)), $ganadores);
}
if (!$ganadores) {
    $ganadores = array_values(array_filter($ganadoresManual));
}

// Imágenes cargadas desde el módulo administrativo (tienen prioridad sobre la carpeta)
try {
    if (!empty($conn)) {
        $stmtG = $conn->query("
            IF OBJECT_ID('dbo.paginaweb_sv_aguinaldo_ganadores', 'U') IS NOT NULL
                SELECT imagen FROM dbo.paginaweb_sv_aguinaldo_ganadores
                WHERE imagen IS NOT NULL AND LTRIM(RTRIM(imagen)) <> ''
                ORDER BY orden, id
        ");
        $desdeAdmin = $stmtG ? $stmtG->fetchAll(PDO::FETCH_COLUMN) : [];
        if ($desdeAdmin) {
            $ganadores = $desdeAdmin;
        }
    }
} catch (Throwable $e) {
    error_log(__FILE__ . ' (ganadores): ' . $e->getMessage());
}
$totalG = count($ganadores);

// Fotos visibles a la vez según pantalla: 3 = escritorio, 2 = tableta, 1 = celular
$vistas = [3, 2, 1];

/* Destino de las flechas para la foto $i mostrando $v fotos a la vez.
   Al llegar al final vuelve al inicio y viceversa. */
function la_destinos($i, $v, $n) {
    $max  = max(0, $n - $v);
    $e    = min($i, $max);
    $next = ($e + 1 > $max) ? 0 : $e + 1;
    $prev = ($e - 1 < 0) ? $max : $e - 1;
    return [$prev, $next, $max];
}

include __DIR__ . '/includes/header.php';
?>
<style>
/* ================= LOTO AGUINALDO ================= */
.la-page{
  --la-naranja:#f27a0c; --la-naranja-osc:#d9590a; --la-blanco:#fff; --la-ancho:1100px;
  position:relative; overflow:hidden; color:var(--la-blanco); text-align:center;
  font-family:"Montserrat","Poppins",Arial,Helvetica,sans-serif;
  padding:30px 16px 60px;
  background:
    var(--la-fondo,none) center top / cover no-repeat,
    radial-gradient(ellipse at 50% 15%,#ffa62b 0%,var(--la-naranja) 45%,var(--la-naranja-osc) 100%);
}
.la-page > section{max-width:var(--la-ancho); margin:0 auto;}
.la-page img{max-width:100%; height:auto; display:block;}
.la-sr-only{position:absolute!important; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; border:0;}

/* Títulos */
.la-titulo,.la-subtitulo{margin:0 0 22px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; line-height:1.15; color:var(--la-blanco); text-shadow:0 2px 4px rgba(0,0,0,.25);}
.la-titulo{font-size:clamp(22px,3.2vw,34px);}
.la-subtitulo{font-size:clamp(18px,2.4vw,24px); letter-spacing:1.5px;}

/* Hero */
.la-hero{display:flex; flex-direction:column; align-items:center; padding-top:140px;}
.la-logo{width:min(420px,80%); margin:0 auto; filter:drop-shadow(0 6px 10px rgba(0,0,0,.25));}
.la-premio{width:min(300px,62%); margin:-6px auto 0; filter:drop-shadow(0 6px 10px rgba(0,0,0,.25));}

/* Info: boletos / jugá desde $3 / rifa */
.la-info{display:grid; grid-template-columns:repeat(3,1fr); align-items:center; justify-items:center; gap:24px; margin-top:40px!important;}
.la-boletos{width:min(240px,100%);}
.la-htp{width:min(260px,100%); filter:drop-shadow(0 8px 14px rgba(0,0,0,.25));}
.la-rifa{width:min(240px,100%);}

/* Último número ganador */
.la-numero{margin-top:50px!important;}
.la-bolas{display:flex; justify-content:center; gap:14px; flex-wrap:wrap;}
.la-bola{width:54px; height:54px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; color:#222;
  background:radial-gradient(circle at 35% 30%,#fff 0%,#f4f4f4 55%,#d9d9d9 100%);
  box-shadow:0 4px 8px rgba(0,0,0,.25), inset 0 -3px 5px rgba(0,0,0,.12);
  animation:la-pop .45s ease both;}
.la-bola:nth-child(2){animation-delay:.08s}
.la-bola:nth-child(3){animation-delay:.16s}
.la-bola:nth-child(4){animation-delay:.24s}
.la-bola:nth-child(5){animation-delay:.32s}
@keyframes la-pop{from{transform:scale(.4);opacity:0}to{transform:scale(1);opacity:1}}

/* Ganadores: carrusel solo con CSS */
.la-ganadores{margin-top:60px!important;}
.la-carrusel{--la-vis:3; --la-gap:16px; position:relative; display:grid; grid-template-columns:44px 1fr 44px; align-items:center; gap:18px;}
.la-radio{position:absolute; opacity:0; pointer-events:none;}
.la-viewport{grid-column:2; grid-row:1; overflow:hidden; padding:4px 0;}
.la-track{--la-i:0; display:flex; gap:var(--la-gap); transition:transform .45s ease;
  transform:translateX(calc(-1 * min(var(--la-i), max(0, var(--la-n) - var(--la-vis))) * (100% + var(--la-gap)) / var(--la-vis)));}
.la-slide{flex:0 0 calc((100% - (var(--la-vis) - 1) * var(--la-gap)) / var(--la-vis)); margin:0; border-radius:4px; overflow:hidden; background:#fff; box-shadow:0 6px 14px rgba(0,0,0,.25); aspect-ratio:4/3;}
.la-slide img{width:100%; height:100%; object-fit:cover; object-position:center 30%;}
/* Con 1 o 2 fotos: centradas */
.la-pocas .la-track{justify-content:center;}
.la-sin-fotos{margin:0; font-size:18px; font-weight:700;}

/* Flechas: triángulos blancos */
.la-nav{grid-row:1; display:flex; justify-content:center;}
.la-nav-prev{grid-column:1;}
.la-nav-next{grid-column:3;}
.la-nav label{display:none; width:44px; height:64px; cursor:pointer; position:relative; transition:transform .2s ease;}
.la-nav label::before{content:""; position:absolute; top:50%; left:50%; width:0; height:0; border-top:30px solid transparent; border-bottom:30px solid transparent; transform:translate(-50%,-50%); filter:drop-shadow(0 2px 3px rgba(0,0,0,.25));}
.la-nav-prev label::before{border-right:34px solid var(--la-blanco);}
.la-nav-next label::before{border-left:34px solid var(--la-blanco);}
.la-nav label:hover{transform:scale(1.1);}
.la-radio:focus-visible ~ .la-viewport{outline:2px solid #fff; outline-offset:4px;}

/* Fechas de rifa */
.la-fechas{margin-top:50px!important; max-width:820px!important;}
.la-fechas .la-titulo{margin-bottom:14px;}
.la-fechas p{margin:0; font-size:clamp(14px,1.6vw,17px); font-weight:700; line-height:1.5; text-shadow:0 1px 2px rgba(0,0,0,.2);}

/* Reglamento */
.la-reglamento{margin-top:44px!important;}
.la-reglamento .la-titulo{margin-bottom:18px;}
.la-btn{display:inline-block; padding:14px 38px; border:3px solid var(--la-blanco); border-radius:40px; color:var(--la-blanco); font-size:17px; font-weight:700; text-decoration:none; transition:background .2s ease,color .2s ease;}
.la-btn:hover,.la-btn:focus-visible{background:var(--la-blanco); color:var(--la-naranja-osc);}

/* Responsive: qué flechas se muestran según las fotos visibles */
@media (min-width:901px){ .la-nav .la-v2,.la-nav .la-v1{display:none!important;} }
@media (min-width:681px) and (max-width:900px){
  .la-carrusel{--la-vis:2;}
  .la-nav .la-v3,.la-nav .la-v1{display:none!important;}
}
@media (max-width:680px){
  .la-page{padding:20px 12px 44px;}
  .la-hero{padding-top:110px;}
  .la-info{grid-template-columns:1fr; gap:22px; margin-top:28px!important;}
  .la-htp{order:-1;}
  .la-bola{width:46px; height:46px; font-size:20px;}
  .la-bolas{gap:10px;}
  .la-carrusel{--la-vis:1; grid-template-columns:28px 1fr 28px; gap:8px;}
  .la-nav label{width:28px; height:44px;}
  .la-nav label::before{border-top-width:20px; border-bottom-width:20px;}
  .la-nav-prev label::before{border-right-width:22px;}
  .la-nav-next label::before{border-left-width:22px;}
  .la-btn{padding:12px 26px; font-size:15px;}
  .la-nav .la-v3,.la-nav .la-v2{display:none!important;}
}
@media (prefers-reduced-motion:reduce){ .la-bola{animation:none;} .la-track{transition:none;} }

/* Posición del carrusel según la foto seleccionada (generado) */
<?php for ($i = 0; $i < $totalG; $i++): ?>
#la-g<?= $i ?>:checked ~ .la-viewport .la-track{--la-i:<?= $i ?>;}
#la-g<?= $i ?>:checked ~ .la-nav .la-a<?= $i ?>{display:block;}
<?php endfor; ?>
</style>

<main class="la-page" style="--la-fondo:url('<?= la_e($imgDir) ?>/loto-aguinaldo-fondo.png')">

  <!-- HERO -->
  <section class="la-hero">
    <h1 class="la-sr-only">Loto Aguinaldo - $1,000 todos los días</h1>
    <img class="la-logo" src="<?= la_e($imgDir) ?>/logo-loto-aguinaldo.png" alt="Loto Aguinaldo">
    <img class="la-premio" src="<?= la_e($imgDir) ?>/loto-aguinaldo-premio-1000.png" alt="$1,000 todos los días">
  </section>

  <!-- INFO: boletos / jugá desde $3 / datos rifa -->
  <section class="la-info">
    <img class="la-boletos" src="<?= la_e($imgDir) ?>/loto-aguinaldo-boletos.png" alt="Boletos participantes de Loto Aguinaldo" loading="lazy">
    <img class="la-htp" src="<?= la_e($imgDir) ?>/loto-aguinaldo-juga-desde-3.png" alt="Jugá desde $3. Todos los juegos participan: La Diaria, InstaCash, Loto Super Premio y Apostemos" loading="lazy">
    <img class="la-rifa" src="<?= la_e($imgDir) ?>/loto-aguinaldo-datos-rifa.png" alt="Guardá tu boleto. Rifa 9:00 p.m. por Canal 4 y Facebook / YouTube Loto El Salvador" loading="lazy">
  </section>

  <!-- ÚLTIMO NÚMERO GANADOR -->
  <section class="la-numero">
    <h2 class="la-subtitulo">Último número ganador</h2>
    <div class="la-bolas" aria-label="Último número ganador <?= la_e($numero) ?>">
      <?php foreach ($digitos as $d): ?>
        <span class="la-bola"><?= la_e($d) ?></span>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- GANADORES -->
  <section class="la-ganadores">
    <h2 class="la-titulo">Ellos ya ganaron con Loto Aguinaldo</h2>
    <?php if (!$totalG): ?>
      <!-- Sin fotos: carpeta buscada = <?= la_e($carpetaFisica ?: (__DIR__ . $ganadoresDir)) ?> -->
      <p class="la-sin-fotos">Muy pronto publicaremos a nuestros ganadores.</p>
    <?php else: ?>
    <div class="la-carrusel<?= $totalG < 3 ? ' la-pocas' : '' ?>" style="--la-n:<?= $totalG ?>">

      <?php for ($i = 0; $i < $totalG; $i++): ?>
        <input class="la-radio" type="radio" name="la-ganador" id="la-g<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> aria-label="Foto <?= $i + 1 ?> de <?= $totalG ?>">
      <?php endfor; ?>

      <div class="la-nav la-nav-prev">
        <?php for ($i = 0; $i < $totalG; $i++): foreach ($vistas as $v):
          [$prev, , $max] = la_destinos($i, $v, $totalG);
          if ($max === 0) continue; ?>
          <label class="la-a<?= $i ?> la-v<?= $v ?>" for="la-g<?= $prev ?>" title="Anterior"><span class="la-sr-only">Anterior</span></label>
        <?php endforeach; endfor; ?>
      </div>

      <div class="la-viewport">
        <div class="la-track">
          <?php foreach ($ganadores as $i => $foto): ?>
            <figure class="la-slide">
              <img src="<?= la_e($foto) ?>" alt="Ganador de Loto Aguinaldo <?= $i + 1 ?>" loading="lazy">
            </figure>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="la-nav la-nav-next">
        <?php for ($i = 0; $i < $totalG; $i++): foreach ($vistas as $v):
          [, $next, $max] = la_destinos($i, $v, $totalG);
          if ($max === 0) continue; ?>
          <label class="la-a<?= $i ?> la-v<?= $v ?>" for="la-g<?= $next ?>" title="Siguiente"><span class="la-sr-only">Siguiente</span></label>
        <?php endforeach; endfor; ?>
      </div>

    </div>
    <?php endif; ?>
  </section>

  <!-- FECHAS DE RIFA -->
  <section class="la-fechas">
    <h2 class="la-titulo">Fechas de rifa</h2>
    <p>
      Promoción válida del 16 de noviembre de 2026 al 6 de enero de 2027. Rifas de $1,000 todos
      los días a las 9:00 P.M. por Canal 4 o Transmisión en vivo por Facebook y YouTube Live.<br>
      Rifas especiales de $2,000: 1, 24 y 31 de Diciembre de 2026, y 6 de Enero de 2027.<br>
      Seguí la transmisión en vivo por Canal 4, Facebook y YouTube de Loto El Salvador.
    </p>
  </section>

  <!-- REGLAMENTO -->
  <section class="la-reglamento">
    <h2 class="la-titulo">Reglamento de la promoción Loto Aguinaldo</h2>
    <a class="la-btn" href="<?= la_e($reglamentoUrl) ?>" target="_blank" rel="noopener" download>Hacé click aquí para descargar</a>
  </section>

</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
