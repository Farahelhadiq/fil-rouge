<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id_professeur'])) {
    header("Location: login_professeur.php");
    exit();
}

$pdo = connectDB();
$id_professeur = $_SESSION['id_professeur'];
$errorMessage = '';
$successMessage = '';

$stmt = $pdo->prepare("SELECT id_groupe FROM professeurs_groupes WHERE id_professeur = ? ORDER BY annee_scolaire DESC LIMIT 1");
$stmt->execute([$id_professeur]);
$groupe = $stmt->fetch();
$id_groupe = $groupe ? $groupe['id_groupe'] : null;

$enfants = [];
if ($id_groupe) {

    $stmt = $pdo->prepare("
        SELECT e.* 
        FROM enfants e
        INNER JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant
        WHERE eg.id_groupe = ? AND eg.annee_scolaire = (
            SELECT MAX(annee_scolaire) FROM Enfant_groupe WHERE id_groupe = ?
        )
    ");
    $stmt->execute([$id_groupe, $id_groupe]);
    $enfants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        foreach ($enfants as $enfant) {
            $id_enfant = $enfant['id_enfant'];
            $is_absent = isset($_POST['absents'][$id_enfant]);
            $heure_debut = $_POST['heure_debut'][$id_enfant] ?? null;
            $heure_fin = $_POST['heure_fin'][$id_enfant] ?? null;
            $justification = $_POST['justification'][$id_enfant] ?? null;

            if ($is_absent) {
                if (empty($heure_debut) || empty($heure_fin)) {
                    throw new Exception("Les heures de début et de fin sont obligatoires pour une absence.");
                }

                $check = $pdo->prepare("SELECT * FROM absences WHERE id_enfant = ? AND date_ = CURDATE()");
                $check->execute([$id_enfant]);
                if ($check->rowCount() > 0) {
                  
                    $update = $pdo->prepare("UPDATE absences SET heure_debut = ?, heure_fin = ?, justification = ? WHERE id_enfant = ? AND date_ = CURDATE()");
                    $update->execute([$heure_debut, $heure_fin, $justification, $id_enfant]);
                } else {
    
                    $insert = $pdo->prepare("INSERT INTO absences (date_, heure_debut, heure_fin, justification, id_enfant) VALUES (CURDATE(), ?, ?, ?, ?)");
                    $insert->execute([$heure_debut, $heure_fin, $justification, $id_enfant]);
                }
            } else {
            
                $delete = $pdo->prepare("DELETE FROM absences WHERE id_enfant = ? AND date_ = CURDATE()");
                $delete->execute([$id_enfant]);
            }
        }
        $pdo->commit();
        $successMessage = "Absences mises à jour avec succès.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Erreur lors de la mise à jour des absences : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Marquer absence</title>
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

        .heure-actuelle {
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .heure-actuelle span {
            font-weight: 600;
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

        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        input[type="time"] {
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
            max-width: 150px;
        }

        textarea {
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
            min-height: 60px;
            resize: vertical;
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
            margin-top: 16px;
        }

        button:hover {
            background: #2563eb;
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

            table, thead, tbody, th, td, tr {
                display: block;
            }

            thead {
                display: none;
            }

            tr {
                margin-bottom: 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 12px;
            }

            td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none;
                padding: 8px 12px;
                position: relative;
                text-align: right;
            }

            td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                font-size: 12px;
                position: absolute;
                left: 12px;
            }

            td input[type="time"], td textarea {
                max-width: 100%;
            }

            td input[type="checkbox"] {
                margin-left: auto;
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
                <h1 class="page-title">Marquer absence</h1>
                <p class="page-subtitle">Enregistrez les absences des enfants pour aujourd'hui</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="notification error"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($successMessage)): ?>
                <div class="notification success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Marquer absence</h2>
                        <span class="card-icon"><i class="fa-solid fa-calendar-xmark"></i></span>
                    </div>

                    <form method="post" action="">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Absent</th>
                                    <th>Heure début</th>
                                    <th>Heure fin</th>
                                    <th>Justification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enfants as $enfant): 
                                   
                                    $stmt = $pdo->prepare("SELECT * FROM absences WHERE id_enfant = ? AND date_ = CURDATE()");
                                    $stmt->execute([$enfant['id_enfant']]);
                                    $absence = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <tr>
                                    <td data-label="Nom"><?= htmlspecialchars($enfant['nom']) ?></td>
                                    <td data-label="Prénom"><?= htmlspecialchars($enfant['prenom']) ?></td>
                                    <td data-label="Absent" style="text-align:center;">
                                        <input type="checkbox" name="absents[<?= $enfant['id_enfant'] ?>]" id="absent_<?= $enfant['id_enfant'] ?>" <?= $absence ? 'checked' : '' ?> />
                                    </td>
                                    <td data-label="Heure début">
                                        <input type="time" name="heure_debut[<?= $enfant['id_enfant'] ?>]" value="<?= htmlspecialchars($absence['heure_debut'] ?? '') ?>" />
                                    </td>
                                    <td data-label="Heure fin">
                                        <input type="time" name="heure_fin[<?= $enfant['id_enfant'] ?>]" value="<?= htmlspecialchars($absence['heure_fin'] ?? '') ?>" />
                                    </td>
                                    <td data-label="Justification">
                                        <textarea name="justification[<?= $enfant['id_enfant'] ?>]" placeholder="Justification..."><?= htmlspecialchars($absence['justification'] ?? '') ?></textarea>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="submit">Enregistrer les absences</button>
                    </form>
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

        function updateTime() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            if (hours < 9 || hours >= 17) {
                hours = 9;
                minutes = 0;
                seconds = 0;
            }

            const pad = n => (n < 10 ? '0' + n : n);
            const formattedTime = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);

            document.getElementById('heure_actuelle').textContent = formattedTime;
        }

        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>