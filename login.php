<?php
/**
 * login.php — sign in with email + password
 * Shows a success banner when arriving from signup.php?registered=1
 */
session_start();
require __DIR__ . '/db.php';

$errors = [];               // field_name => message
$formError = '';            // whole-form error (bad credentials / too many attempts)
$old = ['email' => ''];
$justRegistered = isset($_GET['registered']);

function tooManyAttempts(PDO $pdo, string $identifier): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS attempts FROM login_attempts
         WHERE identifier = :identifier AND success = 0
         AND created_at > (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt->execute(['identifier' => $identifier]);
    return (int) $stmt->fetch()['attempts'] >= 5;
}

function recordAttempt(PDO $pdo, string $identifier, bool $success): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (:identifier, :ip, :success)'
    );
    $stmt->execute([
        'identifier' => $identifier,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
        'success'    => $success ? 1 : 0,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old['email'] = $email;

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if (empty($errors) && tooManyAttempts($pdo, $email)) {
        $formError = 'Too many failed attempts. Try again in 15 minutes.';
    }

    if (empty($errors) && $formError === '') {
        $stmt = $pdo->prepare('SELECT id, password_hash, role, onboarding_completed FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $formError = 'Incorrect email or password.';
            recordAttempt($pdo, $email, false);
        } else {
            recordAttempt($pdo, $email, true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];

            $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
                ->execute(['id' => $user['id']]);

            header('Location: ' . ($user['onboarding_completed'] ? 'dashboard.php' : 'onboarding.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HireSwipe — Sign in</title>
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
          <p class="kicker">Welcome back</p>
          <h1>Sign in to HireSwipe</h1>
        </div>
      </div>

      <div class="tabs">
        <span class="tab" aria-current="true">Sign in</span>
        <a class="tab" href="signup.php">Sign up</a>
      </div>

      <form action="login.php" method="POST" novalidate>

        <?php if ($justRegistered && empty($errors) && $formError === ''): ?>
          <div class="server-msg success show">Account created successfully. Please sign in.</div>
        <?php endif; ?>

        <?php if ($formError !== ''): ?>
          <div class="server-msg error show"><?= htmlspecialchars($formError) ?></div>
        <?php endif; ?>

        <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="you@example.com"
                 value="<?= htmlspecialchars($old['email']) ?>" required autocomplete="username">
          <?php if (isset($errors['email'])): ?>
            <span class="hint error show"><?= htmlspecialchars($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" placeholder="Enter your password"
                   required minlength="8" autocomplete="current-password">
            <button type="button" class="toggle-visibility" data-target="password">Show</button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <span class="hint error show"><?= htmlspecialchars($errors['password']) ?></span>
          <?php endif; ?>
        </div>

        <div class="row-between">
          <span></span>
          <a class="link" href="forgot-password.php">Forgot password?</a>
        </div>

        <button type="submit" class="submit">Sign in</button>

        <p class="switch-line">New to HireSwipe? <a class="link" href="signup.php">Create an account</a></p>
      </form>
    </div>
  </div>

  <script src="auth.js"></script>
</body>
</html>
