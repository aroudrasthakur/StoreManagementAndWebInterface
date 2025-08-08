<?php require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = $_POST['username'] ?? '';
  $p = $_POST['password'] ?? '';
  if ($u === DUMMY_USER && $p === DUMMY_PASS) {
    $_SESSION['user'] = $u;
    header("Location: home.php");
    exit;
  } else {
    $err = "Invalid username or password";
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Login • Store Admin</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="card login">
    <h2>Store Admin Login</h2>
    <?php if(isset($_GET['msg'])) echo "<div>".h($_GET['msg'])."</div>"; ?>
    <?php if(!empty($err)) echo "<div class='err'>".h($err)."</div>"; ?>
    <form method="post">
      <input name="username" placeholder="Username" required />
      <input name="password" type="password" placeholder="Password" required />
      <button type="submit">Log in</button>
    </form>
    <p style="color:#666;font-size:12px">Dummy creds: <b>admin / password123</b></p>
  </div>
</body>
</html>
