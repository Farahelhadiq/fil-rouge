<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id_professeur'])) {
    header("Location: login_professeur.php");
    exit();
}

$id_professeur = $_SESSION['id_professeur'];
$pdo = connectDB();

$stmt = $pdo->prepare("SELECT id_groupe FROM professeurs_groupes WHERE id_professeur = ? ORDER BY annee_scolaire DESC LIMIT 1");
$stmt->execute([$id_professeur]);
$groupe = $stmt->fetch();
$id_groupe = $groupe ? $groupe['id_groupe'] : null;

$nb_total_enfants = 0;
$nb_enfants_presents = 0;
$taux_presence = 0;
$arrivees = [];
$activites_du_jour = [];

if ($id_groupe) {

    $stmt = $pdo->prepare("
        SELECT e.* 
        FROM enfants e
        JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant
        WHERE eg.id_groupe = ?
    ");
    $stmt->execute([$id_groupe]);
    $enfants = $stmt->fetchAll();
    $nb_total_enfants = count($enfants);

    $stmt = $pdo->prepare("SELECT id_enfant FROM absences WHERE date_ = CURDATE()");
    $stmt->execute();
    $absents = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $nb_enfants_presents = $nb_total_enfants - count(array_intersect(array_column($enfants, 'id_enfant'), $absents));
    $taux_presence = $nb_total_enfants > 0 ? round(($nb_enfants_presents / $nb_total_enfants) * 100, 2) : 0;

    $stmt = $pdo->prepare("
        SELECT e.nom, e.prenom, e.photo 
        FROM enfants e
        JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant
        WHERE eg.id_groupe = ?
        AND e.id_enfant NOT IN (
            SELECT id_enfant FROM absences WHERE date_ = CURDATE()
        )
        ORDER BY e.id_enfant DESC LIMIT 5
    ");
    $stmt->execute([$id_groupe]);
    $arrivees = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT a.nom_activite, ga.date_d_activite 
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        WHERE ga.id_groupe = ? AND DATE(ga.date_d_activite) = CURDATE()
    ");
    $stmt->execute([$id_groupe]);
    $activites_du_jour = $stmt->fetchAll();
}

function getStatutActivite($date) {
    $aujourdhui = date('Y-m-d');
    $date_activite = date('Y-m-d', strtotime($date));
    if ($date_activite == $aujourdhui) return "En cours";
    elseif ($date_activite > $aujourdhui) return "À venir";
    else return "Planifiée";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Professeur</title>
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f1f5f9;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }

        td {
            font-size: 14px;
            color: #1e293b;
        }

        tr:hover {
            background: #f8fafc;
        }

        td img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            background: #3b82f6;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            margin-bottom: 6px;
            margin-top: 6px;
        }

        button:hover {
            background: #2563eb;
        }

        .activity-list {
            list-style: none;
            padding: 0;
        }

        .activity-list li {
            padding: 8px 0;
            font-size: 14px;
            color: #1e293b;
        }

        .activity-list li em {
            color: #64748b;
            font-style: normal;
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
                <h1 class="page-title">Bienvenue, <?= htmlspecialchars($_SESSION['prenom']) ?> <?= htmlspecialchars($_SESSION['nom']) ?></h1>
                <p class="page-subtitle">Gérez les présences et le planning de votre groupe</p>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Statistiques du jour</h2>
                        <span class="card-icon"></span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Enfants présents</span>
                            <span class="detail-value"><?= $nb_enfants_presents ?> / <?= $nb_total_enfants ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Taux de présence</span>
                            <span class="detail-value"><?= $taux_presence ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Activités du jour</h2>
                       
                    </div>
                    <?php if ($activites_du_jour): ?>
                        <ul class="activity-list">
                            <?php foreach ($activites_du_jour as $a): ?>
                                <li><?= htmlspecialchars($a['nom_activite']) ?> – <em><?= getStatutActivite($a['date_d_activite']) ?></em></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Aucune activité pour aujourd'hui.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Arrivées récentes</h2>
                        
                    </div>
                    <?php if ($arrivees): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($arrivees as $enfant): ?>
                                    <tr>
                                        <td><img src="<?= htmlspecialchars($enfant['photo']) ?>" alt="Photo de <?= htmlspecialchars($enfant['prenom']) ?>"></td>
                                        <td><?= htmlspecialchars($enfant['nom']) ?></td>
                                        <td><?= htmlspecialchars($enfant['prenom']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Aucune arrivée enregistrée aujourd'hui.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Actions rapides</h2>
                    </div>
                    <button onclick="location.href='marquer_presences.php'">Marquer absence</button>
                    <button onclick="location.href='voir_planning.php'">Voir le planning</button>
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

        function updateHeure() {
            const now = new Date();
            const heure = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const secondes = now.getSeconds().toString().padStart(2, '0');
            const heureStr = `${heure}:${minutes}:${secondes}`;
            document.getElementById('heureActuelleStats').textContent = heureStr;
        }

        setInterval(updateHeure, 1000);
        updateHeure();
    </script>
</body>
</html>