<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

try {
    $pdo = connectDB();
    $erreur = "";
    $success = "";
    $groupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe")->fetchAll(PDO::FETCH_ASSOC);
    $activites = $pdo->query("SELECT id_activite, nom_activite FROM activite ORDER BY nom_activite")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_groupe = filter_input(INPUT_POST, 'id_groupe', FILTER_VALIDATE_INT);
        $id_activite = filter_input(INPUT_POST, 'id_activite', FILTER_VALIDATE_INT);
        $date_d_activite = trim($_POST['date_d_activite']);
        if (!$id_groupe || !$id_activite || !$date_d_activite) {
            $erreur = "Tous les champs sont obligatoires.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/", $date_d_activite)) {
            $erreur = "Format de date invalide. Utilisez le format AAAA-MM-JJ HH:MM.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM groupes WHERE id_groupe = ?");
            $stmt->execute([$id_groupe]);
            if ($stmt->fetchColumn() == 0) {
                $erreur = "Le groupe sélectionné n'existe pas.";
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM activite WHERE id_activite = ?");
                $stmt->execute([$id_activite]);
                if ($stmt->fetchColumn() == 0) {
                    $erreur = "L'activité sélectionnée n'existe pas.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO groupes_activites (id_groupe, id_activite, date_d_activite) 
                                           VALUES (:id_groupe, :id_activite, :date_d_activite)");
                    try {
                        $stmt->execute([
                            'id_groupe' => $id_groupe,
                            'id_activite' => $id_activite,
                            'date_d_activite' => $date_d_activite
                        ]);
                        $success = "Activité ajoutée avec succès.";
                        $_POST = [];
                    } catch (PDOException $e) {
                        if ($e->getCode() == '23000') {
                            $erreur = "Cette combinaison de groupe, activité et date existe déjà.";
                        } else {
                            $erreur = "Erreur lors de l'ajout : " . htmlspecialchars($e->getMessage());
                        }
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    $erreur = "Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une activité</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            font-size: 14px;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #357ABD 0%, #4A90E2 100%);
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transform: translateX(0);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            position: relative;
        }

        .toggle-btn {
            position: absolute;
            top: 24px;
            right: 20px;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .toggle-btn.hidden {
            transform: rotate(180deg);
        }

        .show-sidebar-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: none;
            border: none;
            color: #4A90E2;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 999;
            display: none;
            transition: opacity 0.3s ease;
        }

        .show-sidebar-btn.visible {
            display: block;
            opacity: 1;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .user-details p {
            font-size: 12px;
            opacity: 0.8;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            text-decoration: none;
            color: rgba(255,255,255,0.8);
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: white;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .nav-text {
            font-size: 14px;
            font-weight: 500;
        }

        .logout-section {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 0 20px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: background 0.2s ease;
            font-size: 14px;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .page-header {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 16px;
            color: #64748b;
            font-weight: 400;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            max-width: 600px;
            margin: 0 auto;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
        }

        .card-icon {
            font-size: 20px;
            color: #3b82f6;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            color: #1e293b;
            background: #fff;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        button[type="submit"] {
            background: #3b82f6;
            color: white;
        }

        button[type="submit"]:hover {
            background: #2563eb;
        }

        button[type="button"] {
            background: #e2e8f0;
            color: #1e293b;
        }

        button[type="button"]:hover {
            background: #cbd5e1;
        }

        .notification {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .notification.error {
            background: #fee2e2;
            color: #1e293b;
            border: 1px solid #f87171;
        }

        .notification.success {
            background: #e6fffa;
            color: #1e293b;
            border: 1px solid #99f6e4;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.visible {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.full-width {
                margin-left: 0;
            }

            .show-sidebar-btn {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 24px;
            }

            .card {
                padding: 16px;
            }

            .form-actions {
                flex-direction: column;
            }

            button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Tableau de bord Admin</div>
                <button class="toggle-btn" onclick="toggleSidebar()">←</button>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_prenom'] ?? 'A', 0, 1)) ?></div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['admin_prenom'] ?? 'Admin') ?> <?= htmlspecialchars($_SESSION['admin_nom'] ?? '') ?></h4>
                    <p>Administrateur</p>
                </div>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="ajouter_enfant.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-regular fa-user"></i></span>
                    <span class="nav-text">Ajouter un enfant</span>
                </a>
                <a href="ajouter_professeur.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="nav-text">Ajouter un professeur</span>
                </a>
                <a href="gestion_planning.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-regular fa-clock"></i></span>
                    <span class="nav-text">Gérer le planning</span>
                </a>
                <a href="ajouter_activite.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-plus"></i></span>
                    <span class="nav-text">Ajouter une activité</span>
                </a>
            </div>
            <div class="logout-section">
                <a href="logout.php" class="logout-btn">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
        <button class="show-sidebar-btn" onclick="toggleSidebar()">☰</button>
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Ajouter une activité</h1>
                <p class="page-subtitle">Planifiez une nouvelle activité pour un groupe</p>
            </div>

            <?php if ($erreur): ?>
                <div class="notification error"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="notification success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formulaire d'ajout</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="id_groupe">Groupe *</label>
                        <select name="id_groupe" id="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $groupe): ?>
                                <option value="<?= $groupe['id_groupe'] ?>" <?= (isset($_POST['id_groupe']) && $_POST['id_groupe'] == $groupe['id_groupe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($groupe['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_activite">Activité *</label>
                        <select name="id_activite" id="id_activite" required>
                            <option value="">-- Choisir une activité --</option>
                            <?php foreach ($activites as $activite): ?>
                                <option value="<?= $activite['id_activite'] ?>" <?= (isset($_POST['id_activite']) && $_POST['id_activite'] == $activite['id_activite']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($activite['nom_activite']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_d_activite">Date de l'activité *</label>
                        <input type="datetime-local" name="date_d_activite" id="date_d_activite" required value="<?= htmlspecialchars($_POST['date_d_activite'] ?? '') ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit">Ajouter</button>
                        <a href="gestion_planning.php"><button type="button">Annuler</button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const toggleBtn = document.querySelector('.toggle-btn');
            const showSidebarBtn = document.querySelector('.show-sidebar-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('full-width');
            toggleBtn.classList.toggle('hidden');
            showSidebarBtn.classList.toggle('visible');
        }
    </script>
</body>
</html>