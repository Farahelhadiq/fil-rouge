<?php
session_start();
require 'config.php';
if (!isset($_SESSION['id_parent'])) {
    header("Location: loginparent.php");
    exit();
}

$pdo = connectDB();
$id_parent = $_SESSION['id_parent'];
$email = $_SESSION['email'];
$stmt = $pdo->prepare("SELECT nom, prenom FROM parent WHERE id_parent = ?");
$stmt->execute([$id_parent]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);
$nom = $parent['nom'];
$prenom = $parent['prenom'];
$stmt = $pdo->prepare("
    SELECT 
        e.id_enfant,
        e.nom, 
        e.prenom, 
        e.photo, 
        e.date_naissance, 
        g.nom_groupe, 
        g.id_groupe,
        eg.annee_scolaire
    FROM enfants e
    LEFT JOIN Enfant_groupe eg 
        ON e.id_enfant = eg.id_enfant 
        AND eg.annee_scolaire = (
            SELECT MAX(annee_scolaire) 
            FROM Enfant_groupe 
            WHERE id_enfant = e.id_enfant
        )
    LEFT JOIN groupes g 
        ON eg.id_groupe = g.id_groupe
    WHERE e.id_parent = ?
    LIMIT 1
");
$stmt->execute([$id_parent]);
$child = $stmt->fetch(PDO::FETCH_ASSOC);
$educatrice_nom = null;
$educatrice_prenom = null;

if ($child && !empty($child['id_groupe']) && !empty($child['annee_scolaire'])) {
    $stmt = $pdo->prepare("
        SELECT 
            p.nom AS educatrice_nom, 
            p.prenom AS educatrice_prenom
        FROM professeurs_groupes pg 
        JOIN professeur p ON pg.id_professeur = p.id_professeur
        WHERE pg.id_groupe = ? 
        AND pg.annee_scolaire = ?
        LIMIT 1
    ");
    $stmt->execute([$child['id_groupe'], $child['annee_scolaire']]);
    $educatrice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($educatrice) {
        $educatrice_nom = $educatrice['educatrice_nom'];
        $educatrice_prenom = $educatrice['educatrice_prenom'];
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                p.nom AS educatrice_nom, 
                p.prenom AS educatrice_prenom
            FROM professeurs_groupes pg 
            JOIN professeur p ON pg.id_professeur = p.id_professeur
            WHERE pg.id_groupe = ?
            ORDER BY pg.annee_scolaire DESC
            LIMIT 1
        ");
        $stmt->execute([$child['id_groupe']]);
        $educatrice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($educatrice) {
            $educatrice_nom = $educatrice['educatrice_nom'];
            $educatrice_prenom = $educatrice['educatrice_prenom'];
        }
    }
}
if ($child && (empty($child['date_naissance']) || $child['date_naissance'] == '0000-00-00')) {
    $child['date_naissance'] = null;
}
$age = null;
if ($child && !empty($child['date_naissance'])) {
    $dob = new DateTime($child['date_naissance']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}

$next_activity_name = "Aucune activité";
$next_activity_time = "";
if (!empty($child['id_groupe'])) {
    $stmt = $pdo->prepare("
        SELECT a.nom_activite, ga.date_d_activite
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        WHERE ga.id_groupe = ? AND ga.date_d_activite >= CURDATE()
        ORDER BY ga.date_d_activite ASC
        LIMIT 1
    ");
    $stmt->execute([$child['id_groupe']]);
    $next_activity = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($next_activity) {
        $next_activity_name = $next_activity['nom_activite'];
        $next_activity_time = (new DateTime($next_activity['date_d_activite']))->format('H:i');
    }
}

$today_activities = [];
if (!empty($child['id_groupe'])) {
    $stmt = $pdo->prepare("
        SELECT a.nom_activite, ga.date_d_activite
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        WHERE ga.id_groupe = ? AND DATE(ga.date_d_activite) = CURDATE()
        ORDER BY ga.date_d_activite ASC
    ");
    $stmt->execute([$child['id_groupe']]);
    $today_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$absences = [];
if (!empty($child['id_enfant'])) {
    $stmt = $pdo->prepare("
        SELECT date_, justification AS raison, heure_debut, heure_fin
        FROM absences
        WHERE id_enfant = ?
        AND date_ BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
        AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
        ORDER BY date_, heure_debut
    ");
    $stmt->execute([$child['id_enfant']]);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Espace Parent - Tableau de bord</title>
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
            margin-top: 23px;
            margin-bottom: 2px;
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
            padding: 14px;
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

        .child-profile {
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }

        .child-avatar {
            flex-shrink: 0;
        }

        .child-avatar img {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .child-details {
            flex: 1;
        }

        .child-name {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }

        .group-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .educator-card {
            background: linear-gradient(135deg, #e0f2fe, #b3e5fc);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .educator-label {
            font-size: 12px;
            color: #0277bd;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .educator-name {
            font-size: 16px;
            color: #01579b;
            font-weight: 600;
        }

        .activity-card {
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            position: relative;
        }

        .activity-label {
            font-size: 12px;
            color: #7c3aed;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .activity-name {
            font-size: 16px;
            color: #5b21b6;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 14px;
            color: #7c3aed;
            font-weight: 500;
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
            margin-left: 32px;
            margin-right: 32px;
        }

        .events-list, .messages-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .event-item, .message-item {
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fafbfc;
            transition: all 0.2s ease;
        }

        .event-item:hover, .message-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .event-header, .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .event-title, .message-sender {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .event-date, .message-time {
            font-size: 12px;
            color: #64748b;
        }

        .message-preview {
            font-size: 13px;
            color: #475569;
            line-height: 1.4;
        }

        .view-all-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-top: 16px;
            transition: color 0.2s ease;
        }

        .view-all-link:hover {
            color: #2563eb;
        }

        .view-all-link::after {
            content: "→";
            font-size: 16px;
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

            .bottom-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .child-profile {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 24px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <button class="show-sidebar-btn" id="show-sidebar-btn">➔</button>
    <div class="app-container">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1 class="sidebar-title">Espace Parent</h1>
                <button class="toggle-btn" id="toggle-btn">➔</button>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($email, 0, 2)); ?>
                    </div>
                    <div class="user-details">
                        <?php if (!empty($prenom) && !empty($nom)): ?>
                            <h4><?php echo htmlspecialchars($prenom . ' ' . $nom); ?></h4>
                        <?php endif; ?>
                        <p><?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>
            </div>

            <div class="nav-menu">
                <a href="#" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Tableau de bord</span>
                </a>
            </div>

            <div class="logout-section">
                <a href="logout.php" class="logout-btn">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>

        <main class="main-content" id="main-content">
            <div class="page-header">
                <h1 class="page-title">Tableau de bord</h1>
                <p class="page-subtitle">Consultez toutes les informations concernant votre enfant et suivez son parcours éducatif.</p>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profil de l'enfant</h2>
                    </div>
                    
                    <div class="child-profile">
                        <div class="child-avatar">
                            <img src="<?php echo htmlspecialchars($child['photo'] ?: 'https://via.placeholder.com/100x100/4f46e5/ffffff?text=Photo'); ?>" alt="Photo de l'enfant" />
                        </div>
                        
                        <div class="child-details">
                            <h3 class="child-name"><?php echo htmlspecialchars($child['nom'] . ' ' . $child['prenom']); ?></h3>
                            
                            <div class="details-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Âge</span>
                                    <span class="detail-value"><?php echo $age !== null ? $age . ' ans' : 'Non disponible'; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Date de naissance</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($child['date_naissance'] ?: 'Non disponible'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Groupe</span>
                                    <span class="detail-value">
                                        <?php if ($child['nom_groupe']): ?>
                                            <span class="group-badge">
                                                ⭐ <?php echo htmlspecialchars($child['nom_groupe']); ?>
                                            </span>
                                        <?php else: ?>
                                            Non attribué
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="educator-card">
                                <div>
                                    <div class="educator-label">Éducatrice responsable</div>
                                    <div class="educator-name">
                                        <?php 
                                        if ($educatrice_prenom && $educatrice_nom) {
                                            echo htmlspecialchars($educatrice_prenom . ' ' . $educatrice_nom);
                                        } else {
                                            echo 'Non attribuée';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="activity-card">
                                <div class="activity-label">Prochaine activité</div>
                                <div class="activity-name"><?php echo htmlspecialchars($next_activity_name); ?></div>
                                <?php if ($next_activity_time): ?>
                                    <div class="activity-time"><?php echo htmlspecialchars($next_activity_time); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bottom-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Événements à venir</h2>
                    </div>
                    
                    <div class="events-list">
                        <?php if (!empty($today_activities)): ?>
                            <?php foreach ($today_activities as $activity): ?>
                                <div class="event-item">
                                    <div class="event-header">
                                        <span class="event-title"><?php echo htmlspecialchars($activity['nom_activite']); ?></span>
                                        <span class="event-date"><?php echo (new DateTime($activity['date_d_activite']))->format('d M'); ?></span>
                                    </div>
                                    <div class="event-date"><?php echo (new DateTime($activity['date_d_activite']))->format('H:i'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="event-item">
                                <div class="event-title">Aucun événement aujourd'hui</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Absences cette semaine</h2>
                    </div>

                    <div class="card-subtitle">
                        <small>
                            Du <strong><?php echo date('d M Y', strtotime('monday this week')); ?></strong>
                            au <strong><?php echo date('d M Y', strtotime('sunday this week')); ?></strong>
                        </small>
                    </div>

                    <div class="messages-list">
                        <?php if (!empty($absences)): ?>
                            <?php foreach ($absences as $absence): ?>
                                <div class="message-item">
                                    <div class="message-header">
                                        <span class="message-sender">Absence</span>
                                        <span class="message-time"><?php echo (new DateTime($absence['date_']))->format('d M Y'); ?></span>
                                    </div>
                                    <div class="message-preview">
                                        <?php if (!empty($absence['heure_debut']) && !empty($absence['heure_fin'])): ?>
                                            Heure : <?php echo substr($absence['heure_debut'], 0, 5); ?> - <?php echo substr($absence['heure_fin'], 0, 5); ?><br>
                                        <?php else: ?>
                                            Heure : Non spécifiée<br>
                                        <?php endif; ?>
                                        Date : <?php echo (new DateTime($absence['date_']))->format('d M Y'); ?><br>
                                        Raison : <?php echo htmlspecialchars($absence['raison'] ?: 'Aucune raison précisée'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="message-item">
                                <div class="message-title">Aucune absence cette semaine</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggle-btn');
        const showSidebarBtn = document.getElementById('show-sidebar-btn');
        const mainContent = document.getElementById('main-content');

        function toggleSidebar() {
            sidebar.classList.toggle('hidden');
            toggleBtn.classList.toggle('hidden');
            mainContent.classList.toggle('full-width');
            showSidebarBtn.classList.toggle('visible');
        }

        toggleBtn.addEventListener('click', toggleSidebar);
        showSidebarBtn.addEventListener('click', toggleSidebar);

        if (window.innerWidth <= 1024) {
            sidebar.classList.add('hidden');
            mainContent.classList.add('full-width');
            showSidebarBtn.classList.add('visible');
        }
    </script>
</body>
</html>