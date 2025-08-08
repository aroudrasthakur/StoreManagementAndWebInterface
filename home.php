<?php require_once 'config.php'; require_login(); ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Home • Store Admin</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <h1>Welcome, <?=h($_SESSION['user'])?></h1>
  <p>
    <a class="button" href="tables.php">View Tables</a>
    <a class="button" href="crud.php?table=vendor">Manage Vendors</a>
    <a class="button" href="crud.php?table=item">Manage Items</a>
    <a class="button" href="crud.php?table=vendor_item">Vendor↔Item Links</a>
    <a class="button" href="store_items.php">Store Inventory</a>
    <a class="button" href="stores.php">Stores</a>
  </p>
  <p><a class="button" href="logout.php">Logout</a></p>
</body>
</html>
