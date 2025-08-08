<?php require_once 'config.php'; require_login();

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) $tables[] = $row[0];
$res->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Tables • Store Admin</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <a class="button table-link" href="home.php">← Home</a>
      <div class="h1">Tables in <?=h($DB_NAME)?></div>
    </div>

    <div class="card">
      <ul class="table-list">
        <?php foreach($tables as $t): ?>
          <li><a href="crud.php?table=<?=urlencode($t)?>"><?=h($t)?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</body>
</html>
