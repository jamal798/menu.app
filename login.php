<?php
session_start();
require_once 'config.php';

// If user is already logged in, redirect to admin panel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username !== '' && $password !== '') {
        $stmt = $conn->prepare('SELECT password FROM admins WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($hashedPassword);
            $stmt->fetch();
            // Verify the provided password with the hash stored in the DB
            if ($hashedPassword && password_verify($password, $hashedPassword)) {

                // Success: set session and redirect
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                header('Location: admin.php');
                exit;
            }
        }
        $error = 'Invalid username or password';
        $stmt->close();
    } else {
        $error = 'Please enter username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main>
    <div class="login-container">
      <h2>Admin Login</h2>
      <form method="post">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter username" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter password" required>
        <button type="submit" class="btn">Login</button>
      </form>
      <?php if ($error): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
