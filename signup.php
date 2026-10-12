<?php
/**
 * signup.php — create an account (email, mobile, password, confirm password)
 * On success: redirects to login.php?registered=1 so the sign-in page can show a success message.
 */
session_start();
require __DIR__ . '/db.php';

$errors = [];              // field_name => message
$old = ['email' => '', 'mobile' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $mobile   = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $old['email']  = $email;
    $old['mobile'] = $mobile;

    // ---- Field-by-field validation ----
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($mobile === '') {
        $errors['mobile'] = 'Mobile number is required.';
    } elseif (!preg_match('/^\+?[0-9\s]{10,15}$/', $mobile)) {
        $errors['mobile'] = 'Enter a valid mobile number (10–15 digits).';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Use at least one letter and one number.';
    }

    if ($confirm === '') {
        $errors['confirm_password'] = 'Re-enter your password.';
    } elseif ($confirm !== $password) {
        $errors['confirm_password'] = "Passwords don't match.";
    }

    // ---- Uniqueness check (only if the basic fields are already valid) ----
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id, email, mobile FROM users WHERE email = :email OR mobile = :mobile LIMIT 1');
        $stmt->execute(['email' => $email, 'mobile' => $mobile]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['email'] === $email) {
                $errors['email'] = 'An account with this email already exists.';
            }
            if ($existing['mobile'] === $mobile) {
                $errors['mobile'] = 'An account with this mobile number already exists.';
            }
        }
    }

    // ---- Create the account ----
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, mobile, password_hash)
             VALUES (:name, :email, :mobile, :password_hash)'
        );
        // name is filled in later during onboarding — use the email prefix as a placeholder
        $stmt->execute([
            'name'          => strstr($email, '@', true),
            'email'         => $email,
            'mobile'        => $mobile,
            'password_hash' => $passwordHash,
        ]);

        // TODO: send an OTP / verification email here before the account is fully active.

        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HireSwipe — Sign up</title>
<link rel="stylesheet" href="auth.css">
</head>
<body>

  <div class="brand"><span class="dot"></span>HireSwipe</div>

  <div class="stage">
    <div class="deck-card back-2"></div>
    <div class="deck-card back-1"></div>

    <div class="card">
      <div class="eyebrow-row">
        <div>
          <p class="kicker">Get started</p>
          <h1>Create your account</h1>
        </div>
      </div>

      <div class="tabs">
        <a class="tab" href="login.php">Sign in</a>
        <span class="tab" aria-current="true">Sign up</span>
      </div>

      <form action="signup.php" method="POST" novalidate id="signupForm">

        <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="you@example.com"
                 value="<?= htmlspecialchars($old['email']) ?>" required autocomplete="email">
          <?php if (isset($errors['email'])): ?>
            <span class="hint error show"><?= htmlspecialchars($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['mobile']) ? 'has-error' : '' ?>">
          <label for="mobile">Mobile number</label>
          <input type="tel" id="mobile" name="mobile" placeholder="+91 98765 43210"
                 value="<?= htmlspecialchars($old['mobile']) ?>"
                 pattern="^\+?[0-9\s]{10,15}$" required autocomplete="tel">
          <?php if (isset($errors['mobile'])): ?>
            <span class="hint error show"><?= htmlspecialchars($errors['mobile']) ?></span>
          <?php else: ?>
            <span class="hint">Include country code, e.g. +91 98765 43210.</span>
          <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" placeholder="At least 8 characters"
                   required minlength="8" autocomplete="new-password">
            <button type="button" class="toggle-visibility" data-target="password">Show</button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <span class="hint error show"><?= htmlspecialchars($errors['password']) ?></span>
          <?php else: ?>
            <span class="hint">At least 8 characters, with a letter and a number.</span>
          <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
          <label for="confirm_password">Confirm password</label>
          <div class="input-wrap">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password"
                   required minlength="8" autocomplete="new-password">
            <button type="button" class="toggle-visibility" data-target="confirm_password">Show</button>
          </div>
          <?php if (isset($errors['confirm_password'])): ?>
            <span class="hint error show"><?= htmlspecialchars($errors['confirm_password']) ?></span>
          <?php endif; ?>
        </div>

        <button type="submit" class="submit">Create account</button>

        <p class="switch-line">Already have an account? <a class="link" href="login.php">Sign in</a></p>
      </form>
    </div>
  </div>

  <script src="auth.js"></script>
</body>
</html>
