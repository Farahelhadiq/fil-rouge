<?php
session_start();
require_once 'config.php';

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID enfant invalide.");
}

$id_enfant = (int)$_GET['id'];

try {
    $pdo = connectDB();

    // Récupérer groupes pour la liste déroulante
    $stmtGroupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe");
    $groupes = $stmtGroupes->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer parents pour la liste déroulante
    $stmtParents = $pdo->query("SELECT id_parent, nom, prenom FROM parent ORDER BY nom, prenom");
    $parents = $stmtParents->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer l'enfant à modifier
    $stmtEnfant = $pdo->prepare("SELECT * FROM enfants WHERE id_enfant = ?");
    $stmtEnfant->execute([$id_enfant]);
    $enfant = $stmtEnfant->fetch(PDO::FETCH_ASSOC);
    if (!$enfant) {
        die("Enfant non trouvé.");
    }

    // Récupérer le groupe actuel pour l'enfant pour l'année scolaire courante
    $annee_scolaire = date('Y'); // année en cours (exemple simple)
    $stmtGroupeEnfant = $pdo->prepare("SELECT id_groupe FROM Enfant_groupe WHERE id_enfant = ? AND annee_scolaire = ?");
    $stmtGroupeEnfant->execute([$id_enfant, $annee_scolaire]);
    $enfant_groupe = $stmtGroupeEnfant->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur base de données : " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $genre = $_POST['genre'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $photo = trim($_POST['photo'] ?? '');
    $id_groupe = $_POST['id_groupe'] ?? null;
    $id_parent = $_POST['id_parent'] ?? null;

    if ($nom === '' || $prenom === '' || $genre === '' || $date_naissance === '' || !$id_groupe || !$id_parent) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            // Mise à jour de la table enfants (hors groupe)
            $stmtUpdate = $pdo->prepare("
                UPDATE enfants SET nom = ?, prenom = ?, genre = ?, photo = ?, date_naissance = ?, id_parent = ?
                WHERE id_enfant = ?
            ");
            $stmtUpdate->execute([$nom, $prenom, $genre, $photo, $date_naissance, $id_parent, $id_enfant]);

            // Mise à jour ou insertion dans Enfant_groupe pour l'année scolaire courante
            if ($enfant_groupe) {
                // Update si déjà affecté à un groupe cette année
                $stmtUpdateGroupe = $pdo->prepare("
                    UPDATE Enfant_groupe SET id_groupe = ?
                    WHERE id_enfant = ? AND annee_scolaire = ?
                ");
                $stmtUpdateGroupe->execute([$id_groupe, $id_enfant, $annee_scolaire]);
            } else {
                // Insert nouvelle affectation
                $stmtInsertGroupe = $pdo->prepare("
                    INSERT INTO Enfant_groupe (id_enfant, id_groupe, annee_scolaire) VALUES (?, ?, ?)
                ");
                $stmtInsertGroupe->execute([$id_enfant, $id_groupe, $annee_scolaire]);
            }

            header('Location: admin_dashboard.php');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
} else {
    // Pré-remplir les valeurs du formulaire
    $nom = $enfant['nom'];
    $prenom = $enfant['prenom'];
    $genre = $enfant['genre'];
    $date_naissance = $enfant['date_naissance'];
    $photo = $enfant['photo'];
    $id_parent = $enfant['id_parent'];
    $id_groupe = $enfant_groupe['id_groupe'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un enfant</title>
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
            background: white;
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
            font-weight: 700px;
            color: #333e3b;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 16px;
            color: #664748b;
            font-weight: 400px;
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
            color: #333e3b;
        }

        .card-icon {
            font-size: 20px;
            color: #33b82f6;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #664748b;
            text-transform: uppercase;
            font-weight: 600px;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            color: #333e3b;
            background: #fff;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #33b82f6;
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
            font-weight: 600px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        button[type="submit"] {
            background: #33b82f6;
            color: white;
        }

        button[type="submit"]:hover {
            background: #22563eb;
        }

        button[type="button"] {
            background: #e2e8f0;
            color: #333e3b;
        }

        button[type="button"]:hover {
            background: #ccbd5e1;
        }

        .notification {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .notification.error {
            background: #ffee2e2;
            color: #333e3b;
            border: 1px solid #ff87171;
        }

        .notification.success {
            background: #ee6fffa;
            color: #333e3b;
            border: 1px solid #999f6e4;
        }
        .button{
            background: #3b82f6;
            color: white;
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
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_prenom'], 0, 1)) ?></div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['admin_prenom']) ?> <?= htmlspecialchars($_SESSION['admin_nom']) ?></h4>
                    <p>Administrateur</p>
                </div>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="ajouter_enfant.php" class="nav-item">
                    <span class="nav-icon">👶</span>
                    <span class="nav-text">Ajouter un enfant</span>
                </a>
                <a href="ajouter_professeur.php" class="nav-item">
                    <span class="nav-icon">👩‍🏫</span>
                    <span class="nav-text">Ajouter un professeur</span>
                </a>
                <a href="gestion_planning.php" class="nav-item">
                    <span class="nav-icon">🕒</span>
                    <span class="nav-text">Gérer le planning</span>
                </a>
                <a href="ajouter_activite.php" class="nav-item">
                    <span class="nav-icon">➕</span>
                    <span class="nav-text">Ajouter une activité</span>
                </a>
            </div>
            <div class="logout-section">
                <a href="logout.php" class="logout-btn">
                    <span class="nav-icon">🚪</span>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
        <button class="show-sidebar-btn" onclick="toggleSidebar()">☰</button>
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Modifier un enfant</h1>
                <p class="page-subtitle">Mettez à jour les informations de l'enfant</p>
            </div>

            <?php if ($error): ?>
                <div class="notification error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formulaire de modification</h2>
                    <span class="card-icon">👶</span>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" name="nom" id="nom" required value="<?= htmlspecialchars($nom) ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" name="prenom" id="prenom" required value="<?= htmlspecialchars($prenom) ?>">
                    </div>
                    <div class="form-group">
                        <label for="genre">Genre *</label>
                        <select name="genre" id="genre" required>
                            <option value="">-- Choisir --</option>
                            <option value="M" <?= ($genre === 'M') ? 'selected' : '' ?>>Masculin</option>
                            <option value="F" <?= ($genre === 'F') ? 'selected' : '' ?>>Féminin</option>
                            <option value="Autre" <?= ($genre === 'Autre') ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_naissance">Date de naissance *</label>
                        <input type="date" name="date_naissance" id="date_naissance" required value="<?= htmlspecialchars($date_naissance) ?>">
                    </div>
                    <div class="form-group">
                        <label for="photo">Photo (URL)</label>
                        <input type="text" name="photo" id="photo" value="<?= htmlspecialchars($photo) ?>">
                    </div>
                    <div class="form-group">
                        <label for="id_groupe">Groupe *</label>
                        <select name="id_groupe" id="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $g): ?>
                                <option value="<?= $g['id_groupe'] ?>" <?= ($id_groupe == $g['id_groupe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_parent">Parent *</label>
                        <select name="id_parent" id="id_parent" required>
                            <option value="">-- Choisir un parent --</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= $p['id_parent'] ?>" <?= ($id_parent == $p['id_parent']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button">Enregistrer</button>
                        <a href="admin_dashboard.php"><button type="button">Annuler</button></a>
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