<?php
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  ini_set('log_errors', 1);
  ini_set('error_log', __DIR__ . '/php_errors.log');

  define('ROOT_PATH', __DIR__);
  session_start();

  $page = $_GET['page'] ?? 'home';

  $allowed = ['home','biblioteca','frases','login','register', 'herramientas', 'cuestionario', 'cuestionario_resultado'];
  if (!in_array($page, $allowed)) { $page = 'home'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cultura de Paz - Para Estudiantes</title>
  <link rel="stylesheet" href="public/css/style.css" />
  <link rel="stylesheet" href="public/css/home.css" />
  <link rel="stylesheet" href="public/css/biblioteca.css" />
  <script src="public/js/auth.js"></script>
  <link rel="stylesheet" href="public/css/assessments.css">
  <script src="public/js/assessments.js" defer></script>
  <link rel="stylesheet" href="public/css/assessments-detail.css">
  <script src="public/js/assessments-detail.js" defer></script>
</head>
<body>
  <div class="layout">
    <?php include ROOT_PATH . '/view/templates/header.html'; ?>
    <main class="content">
      <div class="container">
        <?php include ROOT_PATH . "/view/{$page}.html"; ?>  
      </div>
    </main>
  </div>

  <?php include ROOT_PATH . '/view/templates/footer.html'; ?>
</body>
</html>
