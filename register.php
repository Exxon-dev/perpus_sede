<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Proses registrasi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $role = $_POST['role'] ?? 'siswa';

    // Validasi
    $errors = [];

    if (empty($username)) {
        $errors[] = "Username harus diisi";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username minimal 3 karakter";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username hanya boleh berisi huruf, angka, dan underscore";
    }

    if (empty($password)) {
        $errors[] = "Password harus diisi";
    } elseif (strlen($password) < 3) {
        $errors[] = "Password minimal 3 karakter";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Konfirmasi password tidak cocok";
    }

    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap harus diisi";
    }

    // Cek username sudah ada atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username '$username' sudah digunakan. Silakan pilih username lain.";
        }
    }

    // Jika tidak ada error, simpan ke database (LANGSUNG PLAIN TEXT, TIDAK DI-HASH)
    if (empty($errors)) {
        try {
            // SIMPAN PASSWORD LANGSUNG TANPA HASH
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $password, $nama_lengkap, $role]);

            $success = "Pendaftaran berhasil! Silakan login dengan akun Anda.";

            // Reset form
            $_POST = [];
        } catch (PDOException $e) {
            $errors[] = "Gagal mendaftar: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Perpustakaan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .register-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-weight: normal;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }

        input,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #999;
        }

        .btn-login {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: #000000;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: #555;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #555;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }

        .login-link {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
        }

        .login-link a {
            color: #333;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }

        .role-group {
            margin-bottom: 20px;
        }

        .role-group label {
            margin-bottom: 8px;
        }

        .role-options {
            display: flex;
            gap: 20px;
            margin-top: 8px;
        }

        .role-options div {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .role-options input {
            width: auto;
        }

        .role-options label {
            margin: 0;
            cursor: pointer;
        }

        small {
            display: block;
            margin-top: 5px;
            color: #888;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div class="register-container">
        <h2>Daftar Akun Baru</h2>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
            <a href="index.php" class="btn-login">Kembali ke Halaman Login</a>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autocomplete="off"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <small>Minimal 3 karakter, hanya huruf, angka, dan underscore</small>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <small>Minimal 3 karakter (akan disimpan dalam bentuk plain text)</small>
                </div>

                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" required
                        value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>">
                </div>

                <div class="role-group">
                    <label>Daftar Sebagai</label>
                    <div class="role-options">
                        <div>
                            <input type="radio" name="role" value="siswa" id="role_siswa"
                                <?= (!isset($_POST['role']) || $_POST['role'] == 'siswa') ? 'checked' : '' ?>>
                            <label for="role_siswa">Siswa</label>
                        </div>
                    </div>
                </div>

                <button type="submit">Daftar</button>
            </form>

            <hr>

            <div class="login-link">
                Sudah punya akun? <a href="index.php">Login di sini</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>