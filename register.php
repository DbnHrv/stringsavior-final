<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // collect & sanitize
    $firstname   = trim($_POST['firstname'] ?? '');
    $middlename  = trim($_POST['middlename'] ?? '');
    $lastname    = trim($_POST['lastname'] ?? '');
    $age         = intval($_POST['age'] ?? 0);
    $gender      = trim($_POST['gender'] ?? '');
    $birthdate   = trim($_POST['birthdate'] ?? '');
    $street      = trim($_POST['street'] ?? '');
    $barangay    = trim($_POST['barangay'] ?? '');
    $municipality= trim($_POST['municipality'] ?? ''); // maps to city
    $province    = trim($_POST['province'] ?? '');
    $contact     = trim($_POST['contact'] ?? '');
    $email       = strtolower(trim($_POST['email'] ?? ''));
    $password    = $_POST['password'] ?? '';
    $type        = trim($_POST['type'] ?? '');
    $terms       = isset($_POST['terms']);

    // server-side validation
    if ($firstname === '' || $lastname === '' || $email === '' || $password === '' || $type === '' || !$terms) {
        $error = 'Required fields missing or terms not accepted.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($age <= 0) {
        $error = 'Invalid age.';
    } else {
        // prepare values
        $createdAt = date('Y-m-d H:i:s');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // insert into DB (stringsavior_1.user)
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                // duplicate check
                $stmt = $pdo->prepare('SELECT user_id FROM `stringsavior_1`.`user` WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'An account with that email already exists.';
                } else {
                    $ins = $pdo->prepare('
                        INSERT INTO `stringsavior_1`.`user`
                          (first_name, middle_name, last_name, age, gender, birthdate, street, barangay, city, province, contact, email, password, user_type, created_at)
                        VALUES
                          (:first_name, :middle_name, :last_name, :age, :gender, :birthdate, :street, :barangay, :city, :province, :contact, :email, :password, :user_type, :created_at)
                    ');
                    $ins->execute([
                        ':first_name' => $firstname,
                        ':middle_name'=> $middlename,
                        ':last_name'  => $lastname,
                        ':age'        => $age,
                        ':gender'     => $gender,
                        ':birthdate'  => $birthdate,
                        ':street'     => $street,
                        ':barangay'   => $barangay,
                        ':city'       => $municipality,
                        ':province'   => $province,
                        ':contact'    => $contact,
                        ':email'      => $email,
                        ':password'   => $passwordHash,
                        ':user_type'  => $type,
                        ':created_at' => $createdAt
                    ]);
                    $success = 'Registration successful. You may now login.';
                    header('Location: login.php');
                    exit;
                }
            } elseif (isset($conn) && ($conn instanceof mysqli || (is_resource($conn) && get_resource_type($conn) === 'mysql link'))) {
                // mysqli path
                $emailEsc = $conn->real_escape_string($email);
                $res = $conn->query("SELECT user_id FROM `stringsavior_1`.`user` WHERE email = '$emailEsc' LIMIT 1");
                if ($res && $res->num_rows) {
                    $error = 'An account with that email already exists.';
                } else {
                    $stmt = $conn->prepare('
                        INSERT INTO `stringsavior_1`.`user`
                        (first_name, middle_name, last_name, age, gender, birthdate, street, barangay, city, province, contact, email, password, user_type, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if (!$stmt) throw new Exception('DB prepare failed: ' . $conn->error);
                    $types = 'sssisssssssssss'; // 15 params: s s s i s s s s s s s s s s s
                    $params = [
                        $firstname, $middlename, $lastname, $age, $gender, $birthdate,
                        $street, $barangay, $municipality, $province, $contact,
                        $email, $passwordHash, $type, $createdAt
                    ];
                    $stmt->bind_param($types, ...$params);
                    if (!$stmt->execute()) throw new Exception('DB execute failed: ' . $stmt->error);
                    $stmt->close();
                    header('Location: login.php');
                    exit;
                }
            } else {
                $error = 'Database connection not found. Ensure db.php provides $pdo or $conn.';
            }
        } catch (Exception $ex) {
            $error = 'Registration failed: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registration</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="body">
  <div class="container">
    <div class="form">
      <h2 class="form-title">Registration Form</h2>

      <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="register.php" novalidate>
        <div class="input-field">
          <input type="text" id="firstname" name="firstname" required value="<?= isset($firstname)?htmlspecialchars($firstname):'' ?>">
          <label for="firstname">First Name</label>
        </div>

        <div class="input-field">
          <input type="text" id="middlename" name="middlename" value="<?= isset($middlename)?htmlspecialchars($middlename):'' ?>">
          <label for="middlename">Middle Name</label>
        </div>

        <div class="input-field">
          <input type="text" id="lastname" name="lastname" required value="<?= isset($lastname)?htmlspecialchars($lastname):'' ?>">
          <label for="lastname">Last Name</label>
        </div>

        <div class="input-field">
          <input type="number" id="age" name="age" min="1" required value="<?= isset($age)?intval($age):'' ?>">
          <label for="age">Age</label>
        </div>

        <div class="input-field">
          <select id="gender" name="gender" required>
            <option value="" disabled <?= empty($gender) ? 'selected':'' ?>>Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Others">Others</option>
          </select>
        </div>

        <div class="input-field">
          <input type="date" id="birthdate" name="birthdate" required value="<?= isset($birthdate)?htmlspecialchars($birthdate):'' ?>"/>
          <label for="birthdate">Birthdate</label>
        </div>

        <div class="input-field address-field">
          <div class="address-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px;">
            <select id="street" name="street" required>
              <option value="" disabled <?= empty($street)?'selected':'' ?>>Select Street</option>
              <option value="Purok 1">Purok 1</option>
              <option value="Purok 2">Purok 2</option>
              <option value="Purok 3">Purok 3</option>
              <option value="Purok 4">Purok 4</option>
              <option value="Purok 5">Purok 5</option>
              <option value="Purok 6">Purok 6</option>
              <option value="Purok 7">Purok 7</option>
              <option value="Purok 8">Purok 8</option>
              <option value="Purok 9">Purok 9</option>
              <option value="Purok 10">Purok 10</option>
              <option value="Purok 11">Purok 11</option>
              <option value="Purok 12">Purok 12</option>
              <option value="Zone 1">Zone 1</option>
              <option value="Zone 2">Zone 2</option>
              <option value="Zone 3">Zone 3</option>
              <option value="Zone 4">Zone 4</option>
              <option value="Zone 5">Zone 5</option>
              <option value="Zone 6">Zone 6</option>
              <option value="Zone 7">Zone 7</option>
              <option value="Zone 8">Zone 8</option>
              <option value="Zone 9">Zone 9</option>
              <option value="Zone 10">Zone 10</option>
              <option value="Zone 11">Zone 11</option>
              <option value="Zone 12">Zone 12</option>

            </select>

            <select id="barangay" name="barangay" required>
              <option value="" disabled <?= empty($barangay)?'selected':'' ?>>Select Barangay</option>
              <option value="Dumoy">Dumoy</option>
              <option value="Bajada">Bajada</option>
              <option value="Agdao">Agdao</option>
              <option value="Talomo">Talomo</option>
              <option value="Toril">Toril</option>
              <option value="Baliok">Baliok</option>
              <option value="Catalunan Grande">Catalunan Grande</option>
              <option value="Buhangin">Buhangin</option>
              <option value="Lanang">Lanang</option>
              <option value="Matina">Matina</option>
              <option value="Bunawan">Bunawan</option>
              <option value="Calinan">Calinan</option>
              <option value="Marilog">Marilog</option>
              <option value="Mintal">Mintal</option>
              <option value="Mulig">Mulig</option>
            </select>

            <select id="municipality" name="municipality" required>
              <option value="" disabled <?= empty($municipality)?'selected':'' ?>>Select Municipality / City</option>
              <option value="Davao City">Davao City</option>
              <option value="Digos City">Digos City</option>
              <option value="Malita">Malita</option>
              <option value="Nabunturan">Nabunturan</option>
              <option value="Mati">Mati</option>
              <option value="Tagum City">Tagum City</option>
            </select>

            <select id="province" name="province" required>
              <option value="" disabled <?= empty($province)?'selected':'' ?>>Select Province</option>
              <option value="Davao Del Sur">Davao Del Sur</option>
              <option value="Davao De Oro">Davao De Oro</option>
              <option value="Davao Oriental">Davao Oriental</option>
              <option value="Davao Del Norte">Davao Del Norte</option>
              <option value="Davao Occidental">Davao Occidental</option>
            </select>

          </div>
        </div>

        <div class="input-field">
          <input type="text" id="combinedAddress" name="address" disabled>
          <label for="address">Address</label>
        </div>

        <div class="input-field">
          <input type="text" id="contact" name="contact" required value="<?= isset($contact)?htmlspecialchars($contact):'' ?>">
          <label for="contact">Contact Number</label>
        </div>

        <div class="input-field">
          <input type="email" id="email" name="email" required value="<?= isset($email)?htmlspecialchars($email):'' ?>">
          <label for="email">Email</label>
        </div>

        <div class="input-field">
          <input type="password" id="password" name="password" required>
          <label for="password">Password</label>
        </div>

        <!-- Role Selection -->
         <div class="input-field">
          <label style="margin-bottom:8px;display:block; "></label>
          <div id="roleSelection" style="display:flex;gap:16px;justify-content:center;margin-bottom:8px;">
            <div class="role-card" data-role="Supplier" tabindex="0"
              style="flex:1;cursor:pointer;padding:16px;border:2px solid #ccc;border-radius:8px;text-align:center;transition:0.2s;">
              <i class="fas fa-tools" style="font-size:2.2em;"></i>
              <span class="visually-hidden"> Supplier</span>
            </div>
            <div class="role-card" data-role="Store Owner" tabindex="0"
              style="flex:1;cursor:pointer;padding:16px;border:2px solid #ccc;border-radius:8px;text-align:center;transition:0.2s;">
              <i class="fas fa-store" style="font-size:2.2em;"></i>
              <span class="visually-hidden"> Music Store Owner</span>
            </div>
          </div>
          <input type="hidden" id="type" name="type" required>
          <div id="roleError" style="color:red;display:none;font-size:0.95em;">Please select a role.</div>
        </div>

        <div class="terms-container" style="margin:8px 0;">
          <input type="checkbox" id="terms" name="terms" required <?= isset($terms) && $terms ? 'checked':'' ?> />
          <label for="terms">I accept terms and conditions</label>
        </div>

        <button type="submit" class="btn">Register</button>
        <p class="register-link">Already have an account? <a href="login.php">Login now</a></p>
      </form>
    </div>
  </div>

  <script>
    // address combined preview
    (function(){
      const fields = ['street','barangay','municipality','province'];
      function updateAddress(){
        const parts = fields.map(id => (document.getElementById(id)||{value:''}).value.trim()).filter(Boolean);
        document.getElementById('combinedAddress').value = parts.join(', ');
      }
      fields.forEach(id => document.getElementById(id)?.addEventListener('input', updateAddress));
      updateAddress();
    })();

    // role selection (client-side only; server validates type)
    (function(){
      const roleCards = document.querySelectorAll('.role-card');
      const typeInput = document.getElementById('type');
      const roleError = document.getElementById('roleError');
      roleCards.forEach(card=>{
        card.addEventListener('click', ()=> {
          roleCards.forEach(c => c.style.borderColor = '#ccc');
          card.style.borderColor = '#e76d0f';
          typeInput.value = card.getAttribute('data-role');
          roleError.style.display = 'none';
        });
        card.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' ') this.click(); });
      });
    })();
  </script>
</body>
</html>