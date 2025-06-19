<?php 
session_start(); 
require_once 'config.php';  

$message = '';  

if ($_SERVER["REQUEST_METHOD"] == "POST") {     
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);     
    $mot_de_passe = filter_input(INPUT_POST, 'mot_de_passe', FILTER_SANITIZE_STRING);      
    if (!empty($email) && !empty($mot_de_passe)) {         
        try {
            $pdo = connectDB();         
            $stmt = $pdo->prepare("SELECT * FROM professeur WHERE email = ?");         
            $stmt->execute([$email]);         
            $professeur = $stmt->fetch(PDO::FETCH_ASSOC);          
            
            if ($professeur && password_verify($mot_de_passe, $professeur['mot_de_passe'])) {             
                $_SESSION['id_professeur'] = $professeur['id_professeur'];             
                $_SESSION['nom'] = $professeur['nom'];             
                $_SESSION['prenom'] = $professeur['prenom'];             
                header("Location: espace_professeur.php");             
                exit();         
            } else {             
                $message = "❌ Email ou mot de passe incorrect.";         
            }     
        } catch (PDOException $e) {
            $message = "❌ Erreur de connexion à la base de données.";
        }
    } else {         
        $message = "❗ Veuillez remplir tous les champs.";     
    } 
} 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Professeur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
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

        .hamburger {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 16px;
            cursor: pointer;
        }

        .hamburger span {
            width: 100%;
            height: 2px;
            background-color: #333;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(4px, 4px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
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
            position: absolute;
            top: 45px;
            right: 0;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            min-width: 180px;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: opacity 0.4s ease, transform 0.4s ease;
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

        .dropdown:hover .dropdown-content,
        .dropbtn:focus + .dropdown-content {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .btn-contact {
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .login-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 440px;
            text-align: center;
            position: relative;
        }

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

        .message {
            margin-top: 1rem;
            padding: 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
        }

        .footer {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c4a6b 50%, #1a2d42 100%);
            color: #b8c5d1;
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

        .footer-contact {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .contact-itemf {
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
            color: #b8c5d1;
            transform: translateY(-2px);
        }

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

          .logo1{
  transform: translateX(100%);
}

        @media (max-width: 768px) {
          .logo1{
  transform: translateX(80%);
}

            .hamburger {
                display: flex;
            }

            .nav-links {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 60px;
                left: 0;
                width: 100%;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                padding: 1rem 0;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                z-index: 999;
            }

            .nav-links.active {
                display: flex;
            }

            .nav-links li {
                width: 100%;
                text-align: center;
            }

            .nav-links a {
                display: block;
                padding: 0.75rem 0;
                font-size: 1rem;
            }

            .dropdown {
                width: 100%;
                text-align: center;
            }

            .dropbtn {
                width: 90%;
                margin: 0 auto;
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            .dropdown-content {
                position: static;
                width: 90%;
                margin: 0 auto 0.5rem;
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
                box-shadow: none;
                border-radius: 6px;
                background: #f8fafc;
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

            .footer-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            nav {
                padding: 0.75rem 1rem;
            }

            .logo img {
                height: 32px;
                margin-right: 0.5rem;
            }

            .nav-links a {
                font-size: 0.9rem;
            }

            .dropbtn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .dropdown-content a {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }

            .login-container {
                padding: 1.5rem 1rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .footer {
                padding-top: 2rem;
            }

            .footer-section h3 {
                font-size: 1.1rem;
            }

            .footer-description {
                font-size: 0.85rem;
            }

            .contact-itemf {
                font-size: 0.85rem;
            }

            .footer-links a {
                font-size: 0.85rem;
            }

            .footer-bottom p {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 360px) {
            .footer-section h3 {
                font-size: 1rem;
            }

            .footer-description {
                font-size: 0.8rem;
            }

            .contact-itemf {
                font-size: 0.8rem;
            }

            .footer-links a {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <ul class="nav-links">
            <li><a href="index.html" class="active">Accueil</a></li>
            <li><a href="contact.html">Contact</a></li>
            <li><a href="Enfants.html">Enfants</a></li>
            <li><a href="A propos.html">À propos</a></li>
            <li class="dropdown">
                <button class="dropbtn">Connexion ▾</button>
                <div class="dropdown-content">
                    <a href="loginparent.php">Espace Parents</a>
                    <a href="espace_professeur.php">Espace Personnel</a>
                </div>
            </li>
        </ul>
    </nav>    
    <div class="main">
        <div class="login-container">
            <img src="logo1.png" class="logo logo1" alt="Logo">
            <form method="POST" action="">
                <h1 class="title">Connexion Professeur</h1>
                <p class="subtitle">Accédez à votre espace personnel sur Baraime El Rahma</p>
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <div class="password-field">
                        <input type="password" name="mot_de_passe" id="password" class="form-input" placeholder="Mot de passe" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()"><i class="fa-solid fa-eye-slash"></i></button>
                    </div>
                </div>
                <button type="submit" class="login-button">Se connecter</button>
                <?php if ($message): ?>
                    <p class="message"><?= htmlspecialchars($message) ?></p>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section footer-brand">
                <div class="footer-logo">
                    <h3>Baraime El Rahma</h3>
                </div>
                <p class="footer-description">
                    Un environnement sûr, nurturant et éducatif pour les enfants. Nous nous engageons à favoriser le développement et l'apprentissage des enfants.
                </p>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <div class="footer-contact">
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span>123 Rue de l'Exemple, Ville, Pays</span>
                    </div>
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <span>+212 5XX-XXXXXX</span>
                    </div>
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <span>contact@baraime-elrahma.com</span>
                    </div>
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12,6 12,12 16,14"/>
                        </svg>
                        <div>Lun-Ven: 9h00-17h00</div>
                    </div>
                </div>
            </div>
            <div class="footer-section">
                <h3>Liens rapides</h3>
                <ul class="footer-links">
                    <li><a href="index.html">Accueil</a></li>
                    <li><a href="A propos.html">À propos</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="loginparent.php">Espace Parents</a></li>
                    <li><a href="espace_professeur.php">Espace Personnel</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Restez informés</h3>
                <div class="social-links">
                    <a href="#" aria-label="Facebook">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="Instagram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-container">
                <p>© 2025 Baraime El Rahma. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.classList.remove('fa-eye-slash');
                toggleButton.classList.add('fa-eye');
            } else {
                passwordField.type = 'password';
                toggleButton.classList.remove('fa-eye');
                toggleButton.classList.add('fa-eye-slash');
            }
        }

        document.querySelector('.hamburger').addEventListener('click', function() {
            this.classList.toggle('active');
            document.querySelector('.nav-links').classList.toggle('active');
        });
    </script>
</body>
</html>