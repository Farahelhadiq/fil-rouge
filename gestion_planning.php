<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

try {
    $pdo = connectDB();
    $startDate = new DateTime('monday this week');
    $days = [];
    for ($i = 0; $i < 5; $i++) {
        $days[] = (clone $startDate)->modify("+$i day");
    }
    $sql = "
        SELECT ga.date_d_activite, a.id_activite, a.nom_activite, a.description,
               g.id_groupe, g.nom_groupe
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        JOIN groupes g ON ga.id_groupe = g.id_groupe
        WHERE DATE(ga.date_d_activite) BETWEEN :start_date AND :end_date
        ORDER BY ga.date_d_activite, a.nom_activite
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $days[0]->format('Y-m-d'),
        ':end_date' => $days[4]->format('Y-m-d'),
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activitiesByDate = [];
    foreach ($results as $row) {
        $date = (new DateTime($row['date_d_activite']))->format('Y-m-d');
        if (!isset($activitiesByDate[$date])) {
            $activitiesByDate[$date] = [];
        }
        $activitiesByDate[$date][] = $row;
    }

} catch (PDOException $e) {
    die("Erreur base de données : " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion du planning hebdomadaire</title>
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
            vertical-align: top;
            min-width: 200px;
        }

        .activity-list {
            list-style: none;
            padding: 0;
        }

        .activity-item {
            margin-bottom: 12px;
            padding: 8px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }

        .activity-item:hover {
            background: #f8fafc;
        }

        .activity-title {
            font-weight: 600;
            color: #1e293b;
        }

        .activity-group {
            font-size: 12px;
            color: #64748b;
        }

        .activity-description {
            margin: 8px 0;
            color: #475569;
        }

        .activity-actions {
            display: flex;
            gap: 8px;
        }

        button, .action-button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        button.modifier, .action-button.modifier {
            background: #3b82f6;
            color: white;
        }

        button.modifier:hover, .action-button.modifier:hover {
            background: #2563eb;
        }

        button.supprimer, .action-button.supprimer {
            background: #f87171;
            color: white;
        }

        button.supprimer:hover, .action-button.supprimer:hover {
            background: #dc2626;
        }

        .no-activities {
            color: #64748b;
            font-style: italic;
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

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 24px;
            }

            .card {
                padding: 16px;
            }

            td {
                min-width: 150px;
            }

            .activity-actions {
                flex-direction: column;
                gap: 4px;
            }

            button, .action-button {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
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
                <a href="gestion_planning.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-regular fa-clock"></i></span>
                    <span class="nav-text">Gérer le planning</span>
                </a>
                <a href="ajouter_activite.php" class="nav-item">
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
                <h1 class="page-title">Planning hebdomadaire</h1>
                <p class="page-subtitle">Semaine du <?= htmlspecialchars($days[0]->format('d/m/Y')) ?> au <?= htmlspecialchars($days[4]->format('d/m/Y')) ?></p>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Activités par jour</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($days as $day): ?>
                                <th><?= htmlspecialchars($day->format('l d/m/Y')) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($days as $day): 
                                $dateStr = $day->format('Y-m-d');
                                ?>
                                <td>
                                    <strong><?= htmlspecialchars($day->format('d/m/Y')) ?></strong>
                                    <?php if (isset($activitiesByDate[$dateStr])): ?>
                                        <ul class="activity-list">
                                            <?php foreach ($activitiesByDate[$dateStr] as $activity): ?>
                                                <li class="activity-item">
                                                    <div class="activity-title"><?= htmlspecialchars($activity['nom_activite']) ?></div>
                                                    <div class="activity-group">Groupe: <?= htmlspecialchars($activity['nom_groupe']) ?></div>
                                                    <div class="activity-time">Heure: <?= htmlspecialchars((new DateTime($activity['date_d_activite']))->format('H:i')) ?></div>
                                                    <div class="activity-description"><?= nl2br(htmlspecialchars($activity['description'] ?? 'Aucune description')) ?></div>
                                                    <div class="activity-actions">
                                                        <a href="modifier_activite.php?id_activite=<?= urlencode($activity['id_activite']) ?>&date=<?= urlencode($activity['date_d_activite']) ?>&id_groupe=<?= urlencode($activity['id_groupe']) ?>" 
                                                           class="action-button modifier">Modifier</a>
                                                        <a href="supprimer_activite.php?id_activite=<?= urlencode($activity['id_activite']) ?>&date=<?= urlencode($activity['date_d_activite']) ?>&id_groupe=<?= urlencode($activity['id_groupe']) ?>" 
                                                           onclick="return confirm('Confirmer la suppression de cette activité ?');" 
                                                           class="action-button supprimer">Supprimer</a>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <div class="no-activities">Aucune activité prévue.</div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
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