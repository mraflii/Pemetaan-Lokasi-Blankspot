    <?php
    include "config/db.php";
    session_start();

    // Redirect jika sudah login
    if (isset($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit();
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        
        // Cari user di database - SESUAIKAN DENGAN STRUKTUR TABEL
        $query = $conn->prepare("SELECT * FROM users WHERE username = ? AND status_aktif = 1");
        $query->bind_param("s", $username);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Set session - SESUAIKAN DENGAN NAMA KOLOM
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['peran'] = $user['peran']; // 'peran' bukan 'role'
                
                // Update last login - SESUAIKAN DENGAN NAMA KOLOM
                $update_query = $conn->prepare("UPDATE users SET login_terakhir = NOW() WHERE id = ?");
                $update_query->bind_param("i", $user['id']);
                $update_query->execute();
                $update_query->close();
                
                // Redirect ke dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Username atau password salah!";
            }
        } else {
            $error = "Username atau password salah!";
        }
        
        $query->close();
    }
    ?>


    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Blankspot Maps</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
        <link rel="stylesheet" href="css/login.css">
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1><i class="fas fa-map-marked-alt"></i> Blankspot Maps</h1>
                <p>Sistem Pemetaan Terintegrasi</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" placeholder="Masukkan username" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-toggle">
                            <input type="password" class="form-control" name="password" id="password" placeholder="Masukkan password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

            </div>
        </div>

        <script>
            function togglePassword() {
                const passwordInput = document.getElementById('password');
                const toggleIcon = document.querySelector('.toggle-password i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.className = 'fas fa-eye-slash';
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.className = 'fas fa-eye';
                }
            }

            // Enter key submit
            document.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.querySelector('form').submit();
                }
            });
        </script>
    </body>
    </html>