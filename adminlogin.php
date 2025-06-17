<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (!empty($email) && !empty($mot_de_passe)) {
        try {
            $pdo = connectDB();

            // Récupérer l'utilisateur par email
            $stmt = $pdo->prepare("SELECT * FROM directeur WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification SHA-256 du mot de passe
            $hashed = hash('sha256', $mot_de_passe);
            if ($admin && $hashed === $admin['mot_de_passe']) {
                $_SESSION['admin_id'] = $admin['id_directeur'];
                $_SESSION['admin_nom'] = $admin['nom'];
                $_SESSION['admin_prenom'] = $admin['prenom'];
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $error = "❌ Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "❗ Erreur de base de données : " . $e->getMessage();
        }
    } else {
        $error = "❗ Veuillez remplir tous les champs.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 3rem;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-self: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #2E5F8F;
        }

        .logo img {
            height: 50px;
            margin-right: 27px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            align-items: center;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            background-color: #f0f8ff;
            color: #4A90E2;
        }

        .dropbtn {
            background-color: #4A90E2;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .dropbtn:hover {
            background-color: #357ABD;
            transform: translateY(-1px);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 45px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            min-width: 180px;
            overflow: hidden;
        }

        .dropdown-content a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .dropdown-content a:hover {
            background-color: #f8fafc;
            color: #4A90E2;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: #666;
            font-weight: 500;
        }

        nav a:hover {
            color: #2563eb;
        }

        /* Main Content */
        .main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 440px;
            text-align: center;
        }

        /* Title */
        .title {
            font-size: 2rem;
            font-weight: 600;
            color: #202124;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #5f6368;
            font-size: 1rem;
            margin-bottom: 2.5rem;
            line-height: 1.4;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #4285f4;
            box-shadow: 0 0 0 2px rgba(66, 133, 244, 0.2);
        }

        .form-input::placeholder {
            color: #9aa0a6;
        }

        /* Password field with eye icon */
        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #5f6368;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 4px;
        }

        .password-toggle:hover {
            color: #202124;
        }

        /* Remember me and forgot password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-container input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #4285f4;
        }

        .checkbox-container label {
            color: #5f6368;
            cursor: pointer;
        }

        .forgot-password {
            color: #4285f4;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        /* Login button */
        .login-button {
            width: 100%;
            padding: 0.875rem;
            background-color: #4285f4;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .login-button:hover {
            background-color: #3367d6;
        }

        .login-button:active {
            background-color: #2851a3;
        }

        .footer {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c4a6b 50%, #1a2d42 100%);
            color: #ffffff;
            padding: 4rem 0 0;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
            gap: 3rem;
        }

        .footer-section h3 {
            color: #4A90E2;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        /* Section Logo et Description */
        .footer-brand .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .footer-description {
            color: #b8c5d1;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Section Contact */
        .footer-contact {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .contact-itemf{
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            color: #b8c5d1;
            font-size: 0.95rem;
        }

        .contact-itemf svg {
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* Section Liens rapides */
        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .footer-links a {
            color: #b8c5d1;
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 0.95rem;
        }

        .footer-links a:hover {
            color: #4A90E2;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #b8c5d1;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: #4A90E2;
            color: white;
            transform: translateY(-2px);
        }

        /* Copyright */
        .footer-bottom {
            margin-top: 3rem;
            padding: 2rem 0;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .footer-bottom p {
            color: #8a9ba8;
            font-size: 0.9rem;
        }

        /* Message styles */
        .message {
            margin-top: 1rem;
            padding: 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .nav {
                gap: 1.5rem;
            }

            .login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
                box-shadow: none;
                border: 1px solid #dadce0;
            }

            .title {
                font-size: 1.75rem;
            }

            .main {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .nav {
                flex-direction: column;
                gap: 0.75rem;
                align-items: center;
            }

            .login-container {
                padding: 1.5rem 1rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="main">
        <div class="login-container">
            <form method="POST" action="">
                <h2 class="title">Connexion </h2>
                
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Email" required>
                </div>
                
                <div class="form-group">
                    <div class="password-field">
                        <input type="password" name="mot_de_passe" id="password" class="form-input" placeholder="Mot de passe" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">👁</button>
                    </div>
                </div>
                
                <button type="submit" class="login-button">Se connecter</button>
                
                <?php if (!empty($error)): ?>
                    <p class="message"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleButton.textContent = '👁';
            }
        }
    </script>
</body>
</html>
