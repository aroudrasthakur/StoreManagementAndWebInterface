<?php
require_once 'config.php'; require_login();

$table = $_GET['table'] ?? '';
if ($table === '') { header("Location: tables.php"); exit; }

// --- Get columns (ordered) and PK columns
$cols = [];
$pk   = [];
$qCols = $conn->prepare("
  SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, EXTRA
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
  ORDER BY ORDINAL_POSITION
");
$qCols->bind_param("ss", $DB_NAME, $table);
$qCols->execute();
$rCols = $qCols->get_result();
while ($c = $rCols->fetch_assoc()) {
  $cols[] = $c;
  if ($c['COLUMN_KEY'] === 'PRI') $pk[] = $c['COLUMN_NAME'];
}
$qCols->close();

if (empty($cols)) die("Table not found.");

// Helpers
function col_names($cols){ return array_map(fn($c)=>$c['COLUMN_NAME'],$cols); }
$colNames = col_names($cols);

// CRUD handlers
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Build arrays
  $data = [];
  foreach ($colNames as $cname) {
    // keep posted value or null; don't block empty string (let SQL coerce)
    if (isset($_POST[$cname])) $data[$cname] = $_POST[$cname];
  }

  if ($action === 'add') {
    // skip AUTO_INCREMENT cols
    $insertCols = [];
    $place = [];
    $values = [];
    foreach ($cols as $c) {
      if (stripos($c['EXTRA'], 'auto_increment') !== false) continue;
      $insertCols[] = $c['COLUMN_NAME'];
      $place[] = '?';
      $values[] = $data[$c['COLUMN_NAME']] ?? null;
    }
    if (!empty($insertCols)) {
      $sql = "INSERT INTO `$table` (".implode(',', array_map(fn($x)=>"`$x`",$insertCols)).") VALUES (".implode(',', $place).")";
      $stmt = $conn->prepare($sql);
      $types = str_repeat('s', count($values));
      $stmt->bind_param($types, ...$values);
      $ok = $stmt->execute();
      $notice = $ok ? "Row added." : "Add failed: ".$stmt->error;
      $stmt->close();
    }
  }

  if ($action === 'update' && !empty($pk)) {
    // SET for non-PK cols
    $setCols = [];
    $values = [];
    foreach ($cols as $c) {
      $name = $c['COLUMN_NAME'];
      if (in_array($name, $pk, true)) continue;
      if (!array_key_exists($name,$data)) continue;
      $setCols[] = "`$name` = ?";
      $values[] = $data[$name];
    }
    // WHERE using PKs (values pass as _pk_<name>)
    $where = [];
    foreach ($pk as $k) {
      $where[] = "`$k` = ?";
      $values[] = $_POST["_pk_$k"] ?? null;
    }
    if (!empty($setCols) && !empty($where)) {
      $sql = "UPDATE `$table` SET ".implode(',',$setCols)." WHERE ".implode(' AND ',$where);
      $stmt = $conn->prepare($sql);
      $types = str_repeat('s', count($values));
      $stmt->bind_param($types, ...$values);
      $ok = $stmt->execute();
      $notice = $ok ? "Row updated." : "Update failed: ".$stmt->error;
      $stmt->close();
    }
  }

  if ($action === 'delete' && !empty($pk)) {
    $where = [];
    $values = [];
    foreach ($pk as $k) {
      $where[] = "`$k` = ?";
      $values[] = $_POST["_pk_$k"] ?? null;
    }
    if (!empty($where)) {
      $sql = "DELETE FROM `$table` WHERE ".implode(' AND ', $where);
      $stmt = $conn->prepare($sql);
      $types = str_repeat('s', count($values));
      $stmt->bind_param($types, ...$values);
      $ok = $stmt->execute();
      $notice = $ok ? "Row deleted." : "Delete failed: ".$stmt->error;
      $stmt->close();
    }
  }
}

// Fetch rows (you can remove LIMIT if you want all)
$rows = [];
$rs = $conn->query("SELECT * FROM `$table` LIMIT 200");
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$rs->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>CRUD • <?=h($table)?></title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="topbar">
    <a class="button" href="home.php">← Home</a>
    <span style="margin-left:10px"><b>Table:</b> <?=h($table)?></span>
  </div>

  <?php if($notice): ?><div class="notice"><?=h($notice)?></div><?php endif; ?>

  <!-- ADD FORM -->
  <details open>
    <summary><b>Add New Row</b></summary>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <table>
        <tr>
          <?php foreach($cols as $c): ?>
            <?php if (stripos($c['EXTRA'],'auto_increment')!==false) continue; ?>
            <th><?=h($c['COLUMN_NAME'])?></th>
          <?php endforeach; ?>
        </tr>
        <tr>
          <?php foreach($cols as $c): if (stripos($c['EXTRA'],'auto_increment')!==false) continue; ?>
            <td>
              <input name="<?=h($c['COLUMN_NAME'])?>" type="text" placeholder="<?=h($c['DATA_TYPE'])?>">
            </td>
          <?php endforeach; ?>
        </tr>
      </table>
      <p><button type="submit">Add</button></p>
    </form>
  </details>

  <!-- ROWS + UPDATE/DELETE -->
  <h3 style="margin-top:24px">Rows (showing up to 200)</h3>
  <table>
    <tr>
      <?php foreach($colNames as $c): ?><th><?=h($c)?></th><?php endforeach; ?>
      <th>Actions</th>
    </tr>
    <?php foreach($rows as $row): ?>
      <tr>
        <form method="post">
          <input type="hidden" name="action" value="update">
          <?php foreach($pk as $k): ?>
            <input type="hidden" name="_pk_<?=h($k)?>" value="<?=h($row[$k])?>">
          <?php endforeach; ?>
          <?php foreach($colNames as $c): ?>
            <td><input name="<?=h($c)?>" value="<?=h($row[$c])?>" type="text"></td>
          <?php endforeach; ?>
          <td>
            <button type="submit">Update</button>
        </form>
        <form method="post" class="inline" onsubmit="return confirm('Delete this row?');">
          <input type="hidden" name="action" value="delete">
          <?php foreach($pk as $k): ?>
            <input type="hidden" name="_pk_<?=h($k)?>" value="<?=h($row[$k])?>">
          <?php endforeach; ?>
          <button type="submit">Delete</button>
        </form>
          </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <p style="color:#666;font-size:12px;margin-top:10px">
    • Updates target columns except primary keys.<br>
    • Deletes/Updates use primary key(s). If a table has no PK, this page won’t know how to target a row—add a PK if needed.<br>
    • Types are bound as strings; MySQL will coerce (fine for admin).
  </p>
</body>
</html>
