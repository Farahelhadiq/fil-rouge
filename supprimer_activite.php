<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

try {
    $pdo = connectDB();

    // Vérifier les paramètres GET
    if (!isset($_GET['id_activite'], $_GET['date'], $_GET['id_groupe'])) {
        $missing = [];
        if (!isset($_GET['id_activite'])) $missing[] = 'id_activite';
        if (!isset($_GET['date'])) $missing[] = 'date';
        if (!isset($_GET['id_groupe'])) $missing[] = 'id_groupe';
        throw new Exception("Paramètres manquants : " . implode(', ', $missing));
    }

    // Valider les paramètres
    $id_activite = filter_input(INPUT_GET, 'id_activite', FILTER_VALIDATE_INT);
    $id_groupe = filter_input(INPUT_GET, 'id_groupe', FILTER_VALIDATE_INT);
    $date_activite = urldecode($_GET['date']);

    if ($id_activite === false || $id_groupe === false) {
        throw new Exception("Identifiants invalides.");
    }

    // Normaliser la date
    try {
        $date_activite_obj = new DateTime($date_activite);
        $date_activite_formatted = $date_activite_obj->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        throw new Exception("Format de date invalide : " . $e->getMessage());
    }

    // Vérifier si l'enregistrement existe
    $stmtCheckExists = $pdo->prepare("
        SELECT COUNT(*) 
        FROM groupes_activites 
        WHERE id_activite = :id_activite AND id_groupe = :id_groupe AND date_d_activite = :date_d_activite
    ");
    $stmtCheckExists->execute([
        ':id_activite' => $id_activite,
        ':id_groupe' => $id_groupe,
        ':date_d_activite' => $date_activite_formatted
    ]);
    if ($stmtCheckExists->fetchColumn() == 0) {
        throw new Exception("L'activité spécifiée n'existe pas.");
    }

    $pdo->beginTransaction();

    // Supprimer l'association groupes_activites
    $stmtDeleteAssoc = $pdo->prepare("
        DELETE FROM groupes_activites 
        WHERE id_activite = :id_activite AND date_d_activite = :date_d_activite AND id_groupe = :id_groupe
    ");
    $stmtDeleteAssoc->execute([
        ':id_activite' => $id_activite,
        ':date_d_activite' => $date_activite_formatted,
        ':id_groupe' => $id_groupe
    ]);

    // Vérifier s'il reste d'autres associations pour cette activité
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM groupes_activites WHERE id_activite = :id_activite");
    $stmtCheck->execute([':id_activite' => $id_activite]);
    $count = $stmtCheck->fetchColumn();

    // Si aucune association restante, supprimer l'activité elle-même
    if ($count == 0) {
        $stmtDeleteAct = $pdo->prepare("DELETE FROM activite WHERE id_activite = :id_activite");
        $stmtDeleteAct->execute([':id_activite' => $id_activite]);
    }

    $pdo->commit();

    // Redirection avec message de succès
    header('Location: gestion_planning.php?msg=activite_supprimee');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Journaliser l'erreur pour le débogage
    error_log("Erreur dans supprimer_activite.php : " . $e->getMessage(), 3, 'errors.log');
    // Afficher un message utilisateur-friendly
    die("Erreur lors de la suppression : " . htmlspecialchars($e->getMessage()));
}
