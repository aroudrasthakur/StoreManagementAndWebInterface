<?php
require_once 'config.php';
require_login();

// exact table names
$tableStore = 'store';
$tableStock = 'store_item';
$tableItem  = 'item';

// selected store id (if any)
$selectedSid = isset($_GET['sId']) ? intval($_GET['sId']) : null;

// Helper
function full_addr($row) {
  $parts = array_filter([
    $row['Street'] ?? null,
    ($row['City'] ?? null),
    isset($row['StateAb']) && isset($row['ZipCode']) ? ($row['StateAb'].' '.$row['ZipCode']) : null
  ]);
  return implode(', ', $parts);
}

// 1) Fetch all stores (for list view)
$stores = [];
$res = $conn->query("SELECT sId, Sname, Street, City, StateAb, ZipCode FROM `$tableStore` ORDER BY Sname");
while ($r = $res->fetch_assoc()) $stores[] = $r;
$res->close();

// 2) If a store is selected, fetch its items with price and count
$store = null;
$items = [];
if ($selectedSid !== null) {
  // store info
  $stmt = $conn->prepare("SELECT sId, Sname, Street, City, StateAb, ZipCode FROM `$tableStore` WHERE sId = ?");
  $stmt->bind_param("i", $selectedSid);
  $stmt->execute();
  $store = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // items at this store
  $stmt = $conn->prepare("
    SELECT i.iId, i.Iname, i.Sprice, i.Category, si.Scount
    FROM `$tableStock` si
    JOIN `$tableItem` i ON i.iId = si.iId
    WHERE si.sId = ?
    ORDER BY i.Iname
  ");
  $stmt->bind_param("i", $selectedSid);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $items[] = $row;
  $stmt->close();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Stores</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <p><a class="button" href="home.php">← Home</a></p>

  <?php if ($selectedSid === null): ?>
    <h1>Stores</h1>
    <div class="grid stores">
      <?php foreach ($stores as $s): ?>
        <div class="card">
          <div style="font-size:18px;font-weight:700;">
            <?=h($s['Sname'])?> <span class="muted">(#<?=h($s['sId'])?>)</span>
          </div>
            <div class="muted"><?=h(full_addr($s))?></div>
          <p style="margin-top:10px">
            <a class="button" href="stores.php?sId=<?=h($s['sId'])?>">View Items</a>
          </p>
        </div>
      <?php endforeach; ?>
      <?php if (empty($stores)): ?>
        <div class="muted">No stores found.</div>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <?php if (!$store): ?>
      <p class="muted">Store not found.</p>
      <p><a class="button" href="stores.php">← Back to Stores</a></p>
    <?php else: ?>
      <h1><?=h($store['Sname'])?></h1>
      <div class="muted"><?=h(full_addr($store))?></div>
      <p style="margin-top:10px"><a class="button" href="stores.php">← Back to Stores</a></p>

      <h2 style="margin-top:18px">Items Available</h2>
      <table>
        <tr>
          <th>iId</th>
          <th>Item</th>
          <th>Price (Sprice)</th>
          <th>Category</th>
          <th>Count (Scount)</th>
        </tr>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?=h($it['iId'])?></td>
            <td><?=h($it['Iname'])?></td>
            <td><?=h($it['Sprice'])?></td>
            <td><?=h($it['Category'])?></td>
            <td><?=h($it['Scount'])?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
          <tr><td colspan="5" class="muted">No items found for this store.</td></tr>
        <?php endif; ?>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
