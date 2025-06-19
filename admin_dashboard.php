<?php
session_start();
require_once 'config.php';

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

// Fonction pour supprimer un enfant et ses absences associées
function supprimerEnfant($id_enfant) {
    if (!is_numeric($id_enfant)) {
        throw new InvalidArgumentException("ID enfant invalide.");
    }
    $pdo = connectDB();

    $pdo->beginTransaction();

    try {
        $stmtAbsences = $pdo->prepare("DELETE FROM absences WHERE id_enfant = ?");
        $stmtAbsences->execute([$id_enfant]);

        $stmtEnfantGroupe = $pdo->prepare("DELETE FROM Enfant_groupe WHERE id_enfant = ?");
        $stmtEnfantGroupe->execute([$id_enfant]);

        $stmt = $pdo->prepare("DELETE FROM enfants WHERE id_enfant = ?");
        $stmt->execute([$id_enfant]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new RuntimeException("Erreur lors de la suppression : " . $e->getMessage());
    }
}

// Fonction pour supprimer un professeur et ses associations
function supprimerProfesseur($id_professeur) {
    if (!is_numeric($id_professeur)) {
        throw new InvalidArgumentException("ID professeur invalide.");
    }
    $pdo = connectDB();

    $pdo->beginTransaction();

    try {
        // Supprimer les associations dans professeurs_groupes
        $stmtProfGroupe = $pdo->prepare("DELETE FROM professeurs_groupes WHERE id_professeur = ?");
        $stmtProfGroupe->execute([$id_professeur]);

        // Supprimer le professeur
        $stmt = $pdo->prepare("DELETE FROM professeur WHERE id_professeur = ?");
        $stmt->execute([$id_professeur]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new RuntimeException("Erreur lors de la suppression : " . $e->getMessage());
    }
}

// Suppression enfant
if (isset($_GET['delete_enfant_id'])) {
    $idToDelete = (int)$_GET['delete_enfant_id'];
    try {
        supprimerEnfant($idToDelete);
        header("Location: admin_dashboard.php?msg=child_deleted");
        exit;
    } catch (Exception $e) {
        $errorDelete = $e->getMessage();
    }
}

// Suppression professeur
if (isset($_GET['delete_prof_id'])) {
    $idToDelete = (int)$_GET['delete_prof_id'];
    try {
        supprimerProfesseur($idToDelete);
        header("Location: admin_dashboard.php?msg=prof_deleted");
        exit;
    } catch (Exception $e) {
        $errorDelete = $e->getMessage();
    }
}

try {
    $pdo = connectDB();

    // Enfants avec groupe (année scolaire courante)
    $sqlEnfants = "
        SELECT e.id_enfant, e.nom, e.prenom, e.genre, e.photo, e.date_naissance,
               g.nom_groupe,
               p.nom AS nom_parent, p.prenom AS prenom_parent
        FROM enfants e
        LEFT JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant AND eg.annee_scolaire = YEAR(CURDATE())
        LEFT JOIN groupes g ON eg.id_groupe = g.id_groupe
        LEFT JOIN parent p ON e.id_parent = p.id_parent
        ORDER BY e.nom, e.prenom
    ";
    $stmtEnfants = $pdo->query($sqlEnfants);
    $enfants = $stmtEnfants->fetchAll(PDO::FETCH_ASSOC);

    // Absences
    $sqlAbsences = "
        SELECT a.id_absence, a.date_, a.heure_debut, a.heure_fin, a.justification,
               e.nom AS nom_enfant, e.prenom AS prenom_enfant
        FROM absences a
        LEFT JOIN enfants e ON a.id_enfant = e.id_enfant
        ORDER BY a.date_ DESC, e.nom, e.prenom
    ";
    $stmtAbsences = $pdo->query($sqlAbsences);
    $absences = $stmtAbsences->fetchAll(PDO::FETCH_ASSOC);

    // Professeurs
    $sqlProfesseurs = "SELECT * FROM professeur";
    $stmtProfesseurs = $pdo->query($sqlProfesseurs);
    $professeurs = $stmtProfesseurs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord Administrateur</title>
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

        button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            margin-bottom: 6px;
            margin-top: 6px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .edit-btn {
            background: #3b82f6;
            color: white;
        }

        .edit-btn:hover {
            background: #2563eb;
        }

        .delete-btn {
            background: #f87171;
            color: white;
        }

        .delete-btn:hover {
            background: #dc2626;
        }

        .notification {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .notification.success {
            background: #e6fffa;
            color: #1e293b;
            border: 1px solid #99f6e4;
        }

        .notification.error {
            background: #fee2e2;
            color: #1e293b;
            border: 1px solid #f87171;
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
                <a href="admin_dashboard.php" class="nav-item active">
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
                <h1 class="page-title">Bienvenue, <?= htmlspecialchars($_SESSION['admin_prenom']) ?></h1>
                <p class="page-subtitle">Gérez les enfants, professeurs et planning</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'child_deleted'): ?>
                    <div class="notification success">Enfant supprimé avec succès.</div>
                <?php elseif ($_GET['msg'] === 'prof_deleted'): ?>
                    <div class="notification success">Professeur supprimé avec succès.</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($errorDelete)): ?>
                <div class="notification error">Erreur lors de la suppression : <?= htmlspecialchars($errorDelete) ?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profils des enfants</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Genre</th>
                                <th>Date de naissance</th>
                                <th>Groupe</th>
                                <th>Parent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enfants)): ?>
                                <tr><td colspan="8">Aucun enfant trouvé.</td></tr>
                            <?php else: ?>
                                <?php foreach ($enfants as $enfant): ?>
                                    <tr>
                                        <td>
                                            <?php if ($enfant['photo']): ?>
                                                <img src="<?= htmlspecialchars($enfant['photo']) ?>" alt="Photo de <?= htmlspecialchars($enfant['prenom']) ?>" width="60">
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($enfant['nom']) ?></td>
                                        <td><?= htmlspecialchars($enfant['prenom']) ?></td>
                                        <td><?= htmlspecialchars($enfant['genre']) ?></td>
                                        <td><?= htmlspecialchars($enfant['date_naissance']) ?></td>
                                        <td><?= htmlspecialchars($enfant['nom_groupe'] ?? 'Non défini') ?></td>
                                        <td><?= htmlspecialchars(trim($enfant['prenom_parent'] . ' ' . $enfant['nom_parent'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="modifier_enfant.php?id=<?= $enfant['id_enfant'] ?>">
                                                    <button class="edit-btn"><i class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <a href="admin_dashboard.php?delete_enfant_id=<?= $enfant['id_enfant'] ?>" onclick="return confirm('Confirmer la suppression ?');">
                                                    <button class="delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Absences des enfants</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heure début</th>
                                <th>Heure fin</th>
                                <th>Enfant</th>
                                <th>Justification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($absences)): ?>
                                <tr><td colspan="5">Aucune absence enregistrée.</td></tr>
                            <?php else: ?>
                                <?php foreach ($absences as $absence): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($absence['date_']) ?></td>
                                        <td><?= htmlspecialchars($absence['heure_debut']) ?></td>
                                        <td><?= htmlspecialchars($absence['heure_fin']) ?></td>
                                        <td><?= htmlspecialchars($absence['prenom_enfant'] . ' ' . $absence['nom_enfant']) ?></td>
                                        <td><?= htmlspecialchars($absence['justification']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Gestion des professeurs</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($professeurs)): ?>
                                <tr><td colspan="4">Aucun professeur trouvé.</td></tr>
                            <?php else: ?>
                                <?php foreach ($professeurs as $prof): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($prof['nom']) ?></td>
                                        <td><?= htmlspecialchars($prof['prenom']) ?></td>
                                        <td><?= htmlspecialchars($prof['email']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="modifier_professeur.php?id=<?= $prof['id_professeur'] ?>">
                                                    <button class="edit-btn"><i class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <a href="admin_dashboard.php?delete_prof_id=<?= $prof['id_professeur'] ?>" onclick="return confirm('Supprimer ce professeur ?');">
                                                    <button class="delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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