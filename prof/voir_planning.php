<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id_professeur'])) {
    header("Location: login_professeur.php");
    exit();
}

$pdo = connectDB();
$id_professeur = $_SESSION['id_professeur'];

$stmtYear = $pdo->prepare("SELECT annee_scolaire FROM professeurs_groupes WHERE id_professeur = ? ORDER BY annee_scolaire DESC LIMIT 1");
$stmtYear->execute([$id_professeur]);
$annee_scolaire = $stmtYear->fetchColumn();

if (!$annee_scolaire) {
    die("Aucune année scolaire trouvée pour ce professeur.");
}

$stmtGroupes = $pdo->prepare("
    SELECT g.id_groupe, g.nom_groupe 
    FROM professeurs_groupes pg 
    JOIN groupes g ON pg.id_groupe = g.id_groupe 
    WHERE pg.id_professeur = ? AND pg.annee_scolaire = ?
");
$stmtGroupes->execute([$id_professeur, $annee_scolaire]);
$groupes = $stmtGroupes->fetchAll();

if (!$groupes) {
    die("Aucun groupe trouvé pour ce professeur cette année scolaire.");
}

function getMondayDate($date) {
    $dt = new DateTime($date);
    $dt->modify('Monday this week');
    return $dt->format('Y-m-d');
}

$monday = getMondayDate(date('Y-m-d'));
$startDate = $monday;
$endDate = (new DateTime($monday))->modify('+6 days')->format('Y-m-d');

$activities = [];

foreach ($groupes as $groupe) {
    $stmtActivities = $pdo->prepare("
        SELECT DATE(ga.date_d_activite) AS date_activite, a.nom_activite, a.description, g.nom_groupe 
        FROM groupes_activites ga 
        JOIN activite a ON ga.id_activite = a.id_activite 
        JOIN groupes g ON ga.id_groupe = g.id_groupe
        WHERE ga.id_groupe = ? AND DATE(ga.date_d_activite) BETWEEN ? AND ?
        ORDER BY ga.date_d_activite
    ");
    $stmtActivities->execute([$groupe['id_groupe'], $startDate, $endDate]);
    $result = $stmtActivities->fetchAll();

    foreach ($result as $act) {
        $activities[$act['date_activite']][] = $act;
    }
}

$days = [];
for ($i = 0; $i < 7; $i++) {
    $dayDate = (new DateTime($monday))->modify("+$i days")->format('Y-m-d');
    $days[$dayDate] = $activities[$dayDate] ?? [];
}

$joursFrancais = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planning Hebdomadaire</title>
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
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
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
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
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
            background: rgba(255, 255, 255, 0.2);
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
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
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
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: background 0.2s ease;
            font-size: 14px;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
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
            margin-left: 42px;
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

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
            margin-left: 32px;
            margin-right: 32px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
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

        .week-info {
            font-size: 14px;
            color: #64748b;
            text-align: center;
            margin-bottom: 24px;
        }

        .day-block {
            margin-bottom: 16px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .day-header {
            background: #f1f5f9;
            padding: 12px 16px;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .activity {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .activity:last-child {
            border-bottom: none;
        }

        .groupe-name {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .activity h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .activity p {
            font-size: 14px;
            color: #475569;
            line-height: 1.4;
        }

        .no-activity {
            padding: 16px;
            font-size: 14px;
            color: #64748b;
            text-align: center;
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

            .dashboard-grid {
                margin-left: 16px;
                margin-right: 16px;
            }

            .day-block {
                margin-bottom: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Espace Professeur</div>
                <button class="toggle-btn" onclick="toggleSidebar()">←</button>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['prenom'], 0, 1)) ?></div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['prenom']) ?> <?= htmlspecialchars($_SESSION['nom']) ?></h4>
                    <p>Professeur</p>
                </div>
            </div>
            <div class="nav-menu">
                <a href="espace_professeur.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="marquer_presences.php" class="nav-item ">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-xmark"></i></span>
                    <span class="nav-text">Marquer absence</span>
                </a>
                <a href="voir_planning.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-regular fa-clock"></i></span>
                    <span class="nav-text">Voir le planning</span>
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
                <h1 class="page-title">Planning Hebdomadaire</h1>
                <p class="page-subtitle">Consultez les activités prévues pour votre groupe</p>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Activités de la semaine</h2>
                       
                    </div>
                    <div class="week-info">
                        Semaine du <?= (new DateTime($monday))->format('d/m/Y') ?> au <?= (new DateTime($monday))->modify('+6 days')->format('d/m/Y') ?>
                    </div>
                    <?php 
                    $i = 0;
                    foreach ($days as $date => $acts): ?>
                        <div class="day-block">
                            <div class="day-header"><?= $joursFrancais[$i++] ?> - <?= (new DateTime($date))->format('d/m/Y') ?></div>
                            <?php if (count($acts) === 0): ?>
                                <p class="no-activity">Aucune activité prévue.</p>
                            <?php else: ?>
                                <?php foreach ($acts as $act): ?>
                                    <div class="activity">
                                        <div class="groupe-name">Groupe : <?= htmlspecialchars($act['nom_groupe']) ?></div>
                                        <h4><?= htmlspecialchars($act['nom_activite']) ?></h4>
                                        <p><?= nl2br(htmlspecialchars($act['description'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
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