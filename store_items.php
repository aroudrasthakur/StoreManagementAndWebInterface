<?php
require_once 'config.php';
require_login();

$tableStore = 'store';
$tableItem  = 'item';
$tableStock = 'store_item';
$tableVend  = 'vendor';
$tableVI    = 'vendor_item';

$notice = '';
$selectedSid = isset($_GET['sId']) ? intval($_GET['sId']) : null;

/* ------------------ POST actions ------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Create a brand-new item (REQUIRES iId), link to a vendor (existing OR new vendor that REQUIRES vId), then add stock for the selected store
  if ($action === 'create_item_vendor_stock') {
    $sId      = intval($_POST['sId']);
    $iIdGiven = isset($_POST['iId']) ? intval($_POST['iId']) : 0; // REQUIRED
    $Iname    = trim($_POST['Iname'] ?? '');
    $Sprice   = $_POST['Sprice'] ?? null;
    $Category = trim($_POST['Category'] ?? '');
    $Scount   = isset($_POST['Scount']) ? intval($_POST['Scount']) : -1;

    // vendor choice: existing vId OR create new vendor inline (REQUIRES vId when creating new)
    $useNewVendor = isset($_POST['create_new_vendor']) && $_POST['create_new_vendor'] === '1';
    $vId = null;

    // Basic validation
    if ($sId <= 0 || $iIdGiven <= 0 || $Iname === '' || $Sprice === null || $Category === '' || $Scount < 0) {
      $notice = "Please fill all item fields correctly (including a positive iId and non-negative stock).";
      $selectedSid = $sId ?: $selectedSid;
    } else {
      $conn->begin_transaction();
      try {
        // Enforce: iId must not exist
        $chk = $conn->prepare("SELECT 1 FROM `$tableItem` WHERE iId = ?");
        $chk->bind_param("i", $iIdGiven);
        $chk->execute();
        $exists = $chk->get_result()->fetch_row();
        $chk->close();
        if ($exists) {
          throw new Exception("Item ID $iIdGiven already exists. Choose a different iId.");
        }

        if ($useNewVendor) {
          // New vendor path: REQUIRE vId_new and ensure it's unique
          $vIdNew  = isset($_POST['vId_new']) ? intval($_POST['vId_new']) : 0;
          $Vname   = trim($_POST['Vname'] ?? '');
          $Street  = trim($_POST['Street'] ?? '');
          $City    = trim($_POST['City'] ?? '');
          $StateAb = trim($_POST['StateAb'] ?? '');
          $ZipCode = trim($_POST['ZipCode'] ?? '');

          if ($vIdNew <= 0 || $Vname === '' || $Street === '' || $City === '' || $StateAb === '' || $ZipCode === '') {
            throw new Exception("All new vendor fields are required, including a positive vId.");
          }

          $chk = $conn->prepare("SELECT 1 FROM `$tableVend` WHERE vId = ?");
          $chk->bind_param("i", $vIdNew);
          $chk->execute();
          $exists = $chk->get_result()->fetch_row();
          $chk->close();
          if ($exists) {
            throw new Exception("Vendor ID $vIdNew already exists. Choose a different vId.");
          }

          $stmt = $conn->prepare("INSERT INTO `$tableVend` (vId, Vname, Street, City, StateAb, ZipCode) VALUES (?,?,?,?,?,?)");
          $stmt->bind_param("isssss", $vIdNew, $Vname, $Street, $City, $StateAb, $ZipCode);
          $stmt->execute();
          $stmt->close();
          $vId = $vIdNew;
        } else {
          // Existing vendor path: REQUIRE vId selection
          $vIdSel = isset($_POST['vId']) ? intval($_POST['vId']) : 0;
          if ($vIdSel <= 0) {
            throw new Exception("Select an existing vendor or choose 'Create new vendor'.");
          }
          // Optionally verify it exists:
          $chk = $conn->prepare("SELECT 1 FROM `$tableVend` WHERE vId = ?");
          $chk->bind_param("i", $vIdSel);
          $chk->execute();
          $exists = $chk->get_result()->fetch_row();
          $chk->close();
          if (!$exists) {
            throw new Exception("Selected vendor (vId=$vIdSel) does not exist.");
          }
          $vId = $vIdSel;
        }

        // Insert item with REQUIRED given iId
        $stmt = $conn->prepare("INSERT INTO `$tableItem` (iId, Iname, Sprice, Category) VALUES (?,?,?,?)");
        $stmt->bind_param("isds", $iIdGiven, $Iname, $Sprice, $Category);
        $stmt->execute();
        $stmt->close();

        // Link vendor <-> item
        $stmt = $conn->prepare("INSERT INTO `$tableVI` (vId, iId) VALUES (?, ?)");
        $stmt->bind_param("ii", $vId, $iIdGiven);
        $stmt->execute();
        $stmt->close();

        // Add stock for this store
        $stmt = $conn->prepare("INSERT INTO `$tableStock` (sId, iId, Scount) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $sId, $iIdGiven, $Scount);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $notice = "Item (iId=$iIdGiven) created, linked to vendor (vId=$vId), and stocked for the store.";
        $selectedSid = $sId;
      } catch (Throwable $e) {
        $conn->rollback();
        $notice = "Failed to create item: ".$e->getMessage();
      }
    }
  }

  // Update stock count for an existing row (store_item)
  if ($action === 'update_count') {
    $sId = intval($_POST['sId']);
    $iId = intval($_POST['iId']);
    $Scount = intval($_POST['Scount']);
    $stmt = $conn->prepare("UPDATE `$tableStock` SET Scount = ? WHERE sId = ? AND iId = ?");
    $stmt->bind_param("iii", $Scount, $sId, $iId);
    $ok = $stmt->execute();
    $stmt->close();
    $notice = $ok ? "Count updated." : "Failed to update count.";
    $selectedSid = $sId;
  }

  // Delete an item globally (from all stores) + clean vendors with no supplies
  if ($action === 'delete_item_everywhere') {
    $sId = intval($_POST['sId']);  // just to keep context for redirect
    $iId = intval($_POST['iId']);

    $conn->begin_transaction();
    try {
      // Capture vendors linked to this item first
      $vendorIds = [];
      $stmt = $conn->prepare("SELECT vId FROM `$tableVI` WHERE iId = ?");
      $stmt->bind_param("i", $iId);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) $vendorIds[] = intval($row['vId']);
      $stmt->close();

      // Remove item from ALL stores
      $stmt = $conn->prepare("DELETE FROM `$tableStock` WHERE iId = ?");
      $stmt->bind_param("i", $iId);
      $stmt->execute();
      $stmt->close();

      // Remove vendor links
      $stmt = $conn->prepare("DELETE FROM `$tableVI` WHERE iId = ?");
      $stmt->bind_param("i", $iId);
      $stmt->execute();
      $stmt->close();

      // Delete the item itself
      $stmt = $conn->prepare("DELETE FROM `$tableItem` WHERE iId = ?");
      $stmt->bind_param("i", $iId);
      $stmt->execute();
      $stmt->close();

      // For each vendor formerly linked to this item, remove vendor if no more supplies
      foreach ($vendorIds as $vid) {
        $stmt = $conn->prepare("SELECT 1 FROM `$tableVI` WHERE vId = ? LIMIT 1");
        $stmt->bind_param("i", $vid);
        $stmt->execute();
        $stillHas = $stmt->get_result()->fetch_row();
        $stmt->close();

        if (!$stillHas) {
          $stmt = $conn->prepare("DELETE FROM `$tableVend` WHERE vId = ?");
          $stmt->bind_param("i", $vid);
          $stmt->execute();
          $stmt->close();
        }
      }

      $conn->commit();
      $notice = "Item removed globally; vendors cleaned up if unused.";
      $selectedSid = $sId;
    } catch (Throwable $e) {
      $conn->rollback();
      $notice = "Failed to delete item: ".$e->getMessage();
    }
  }
}

/* ------------------ Fetch data for UI ------------------ */
// All stores for dropdown
$stores = [];
$res = $conn->query("SELECT sId, Sname FROM `$tableStore` ORDER BY Sname");
while ($r = $res->fetch_assoc()) $stores[] = $r;
$res->close();

// Existing vendors for dropdown
$vendors = [];
$res = $conn->query("SELECT vId, Vname FROM `$tableVend` ORDER BY Vname");
while ($r = $res->fetch_assoc()) $vendors[] = $r;
$res->close();

// If a store selected, fetch its inventory + store name
$storeName = null;
$inventory = [];
if ($selectedSid !== null) {
  $stmt = $conn->prepare("SELECT Sname FROM `$tableStore` WHERE sId = ?");
  $stmt->bind_param("i", $selectedSid);
  $stmt->execute();
  $storeName = ($stmt->get_result()->fetch_row())[0] ?? null;
  $stmt->close();

  $stmt = $conn->prepare("
    SELECT si.sId, si.iId, si.Scount, i.Iname, i.Sprice, i.Category
    FROM `$tableStock` si
    JOIN `$tableItem` i ON i.iId = si.iId
    WHERE si.sId = ?
    ORDER BY i.Iname
  ");
  $stmt->bind_param("i", $selectedSid);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $inventory[] = $r;
  $stmt->close();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Store Inventory</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <a class="button" href="home.php">← Home</a>
      <div class="h1">Store Inventory</div>
    </div>

    <?php if ($notice): ?><div class="notice"><?=h($notice)?></div><?php endif; ?>

    <!-- Store selector -->
    <form method="get" class="row">
      <label for="sId"><b>Select Store:</b></label>
      <select name="sId" id="sId" required>
        <option value="">-- choose --</option>
        <?php foreach ($stores as $s): ?>
          <option value="<?=h($s['sId'])?>" <?= $selectedSid===$s['sId']?'selected':''?>>
            <?=h($s['Sname'])?> (#<?=h($s['sId'])?>)
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="primary">Load</button>
    </form>

    <?php if ($selectedSid !== null && $storeName): ?>
      <h2 class="h2" style="margin-top:16px"><?=h($storeName)?> <span class="muted">(#<?=h($selectedSid)?>)</span></h2>

      <!-- CREATE new item (REQUIRES iId) + link vendor (existing OR new that REQUIRES vId) + add stock -->
      <div class="card">
        <details open>
          <summary><b>Create New Item for This Store (and link to vendor)</b></summary>
          <form method="post">
            <input type="hidden" name="action" value="create_item_vendor_stock">
            <input type="hidden" name="sId" value="<?=h($selectedSid)?>">

            <h4>Item</h4>
            <div class="row">
              <label>Item ID (iId)</label>
              <input type="number" name="iId" min="1" required placeholder="e.g., 111">
              <label>Name</label>
              <input type="text" name="Iname" required placeholder="e.g., Item">
              <label>Price</label>
              <input type="number" step="0.01" name="Sprice" required placeholder="e.g., 10.00">
              <label>Category</label>
              <input type="text" name="Category" required placeholder="e.g., Nuts">
              <label>Initial Stock</label>
              <input type="number" name="Scount" min="0" required placeholder="e.g., 0">
            </div>

            <h4 style="margin-top:10px">Vendor</h4>
            <div class="row">
              <label><input type="checkbox" name="create_new_vendor" value="1" id="newvend"> Create new vendor</label>
            </div>

            <div id="existing-vendor" class="row">
              <label>Select Existing</label>
              <select name="vId" required>
                <option value="">-- choose vendor --</option>
                <?php foreach ($vendors as $v): ?>
                  <option value="<?=h($v['vId'])?>"><?=h($v['Vname'])?> (#<?=h($v['vId'])?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div id="new-vendor" class="row" style="display:none">
              <label>Vendor ID (vId)</label><input type="number" name="vId_new" min="1">
              <label>Name</label><input type="text" name="Vname">
              <label>Street</label><input type="text" name="Street">
              <label>City</label><input type="text" name="City">
              <label>State</label><input type="text" name="StateAb" maxlength="2" placeholder="TX">
              <label>Zip</label><input type="text" name="ZipCode">
            </div>

            <p><button type="submit" class="primary">Create & Link</button></p>
          </form>
          <p class="muted">This will insert the item with your <b>exact iId</b>, link to a vendor (existing or with your <b>exact vId</b>), and add stock to the selected store.</p>
        </details>
      </div>

      <!-- Current inventory with inline update/delete -->
      <h3 class="h2" style="margin-top:18px">Current Inventory</h3>
      <div class="card">
        <table>
          <tr>
            <th>iId</th>
            <th>Item</th>
            <th>Price</th>
            <th>Category</th>
            <th>Count (Scount)</th>
            <th>Actions</th>
          </tr>
          <?php foreach ($inventory as $row): ?>
            <tr>
              <td><?=h($row['iId'])?></td>
              <td><?=h($row['Iname'])?></td>
              <td><?=h($row['Sprice'])?></td>
              <td><?=h($row['Category'])?></td>
              <td>
                <form method="post" class="inline">
                  <input type="hidden" name="action" value="update_count">
                  <input type="hidden" name="sId" value="<?=h($row['sId'])?>">
                  <input type="hidden" name="iId" value="<?=h($row['iId'])?>">
                  <input type="number" name="Scount" min="0" value="<?=h($row['Scount'])?>" />
                  <button type="submit">Update</button>
                </form>
              </td>
              <td>
                <form method="post" class="inline" onsubmit="return confirm('This removes the item from ALL stores, deletes the item, and cleans up vendors with no more supplies. Continue?');">
                  <input type="hidden" name="action" value="delete_item_everywhere">
                  <input type="hidden" name="sId" value="<?=h($row['sId'])?>">
                  <input type="hidden" name="iId" value="<?=h($row['iId'])?>">
                  <button type="submit" class="danger">Delete Item Everywhere</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($inventory)): ?>
            <tr><td colspan="6" class="muted">No items linked to this store yet.</td></tr>
          <?php endif; ?>
        </table>
      </div>
    <?php elseif ($selectedSid !== null): ?>
      <div class="notice err">Store not found.</div>
    <?php endif; ?>
  </div>

  <script>
    // Toggle vendor sections + required attributes
    const cb = document.getElementById('newvend');
    const blockNew = document.getElementById('new-vendor');
    const blockExist = document.getElementById('existing-vendor');
    const selExist = blockExist ? blockExist.querySelector('select[name="vId"]') : null;
    const reqNewIds = blockNew ? blockNew.querySelector('input[name="vId_new"]') : null;
    const reqNewFields = blockNew ? blockNew.querySelectorAll('input[name="Vname"], input[name="Street"], input[name="City"], input[name="StateAb"], input[name="ZipCode"]') : [];

    function setRequiredNew(on) {
      if (selExist) selExist.required = !on;
      if (reqNewIds) reqNewIds.required = on;
      reqNewFields.forEach(el => el.required = on);
    }

    if (cb) {
      const toggle = () => {
        const on = cb.checked;
        blockNew.style.display = on ? 'flex' : 'none';
        blockExist.style.display = on ? 'none' : 'flex';
        setRequiredNew(on);
      };
      cb.addEventListener('change', toggle);
      toggle();
    }
  </script>
</body>
</html>
