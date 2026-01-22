<?php
session_start();
require_once 'db.php';

$error = '';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please provide email and password.';
    } else {
        try {
            $user = null;
            // select all columns from user table so we have the full record after login
            $sql = 'SELECT user_id, first_name, middle_name, last_name, age, gender, birthdate, street, barangay, city, province, contact, email, password, user_type, created_at
                    FROM `stringsavior_1`.`user` WHERE email = ? LIMIT 1';

            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } elseif (isset($conn) && ($conn instanceof mysqli)) {
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $user = $res ? $res->fetch_assoc() : null;
                    $stmt->close();
                }
            } else {
                throw new Exception('Database connection not found. Check db.php for $pdo or $conn.');
            }

            if (!$user) {
                $error = 'Invalid credentials.';
            } else {
                // verify hashed password stored in `password` column
                if (!password_verify($password, $user['password'])) {
                    $error = 'Invalid credentials.';
                } else {
                    // remove password before storing session
                    unset($user['password']);

                    // set session values
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_type'] = $user['user_type'] ?? '';
                    $_SESSION['user'] = $user; // full user record (without password)

                    // redirect based on user_type (case-insensitive)
                    if (strcasecmp($_SESSION['user_type'], 'Store Owner') === 0) {
                        header('Location: music_store_owner_home.php');
                        exit;
                    } else {
                        // default landing for other user types
                        header('Location: supplier_home.php');
                        exit;
                    }
                }
            }
        } catch (Exception $ex) {
            // optionally log: error_log($ex->getMessage());
            $error = 'Login failed. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" >
  <meta name="viewport" content="width=device-width, initial-scale=1.0" >
  <title>Login</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="body">
  <div class="grid-container">
  <div class="logo-area">
    <a href="index.html">
    <img src="images/StringSavior Logo.png" alt="Logo" class="logo-img" >
    </a>
  </div>

  <div class="form-area">
    <div class="form">
    <h2 class="form-title">Login</h2>

    <?php if ($error): ?>
      <div class="error" style="color: red; margin-bottom: 1rem;"><?php echo e($error); ?></div>
    <?php endif; ?>

    <!-- post back to this file so PHP can handle redirect -->
    <form method="POST" action="">
      <div class="input-field">
      <input type="email" name="email" required placeholder=" " value="<?php echo e($_POST['email'] ?? ''); ?>" />
      <label for="email">Email</label>
      </div>

      <div class="input-field">
      <input type="password" name="password" required placeholder=" " />
      <label for="password">Password</label>
      </div>

      <button type="submit" class="btn">Login</button>
      <p class="register-link">
      Don't have an account? <a href="register.php">Register now</a>
      </p>
    </form>
    </div>
  </div>
  </div>
</body>
</html>