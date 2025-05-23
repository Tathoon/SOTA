<?php
session_start();

if (empty($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fashion Chic - Dashboard qhdaizhfazdfhazhedaa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?> !</h1>
        <p>Votre email : <b><?php echo htmlspecialchars($_SESSION['mail'] ?? 'inconnu'); ?></b></p>
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">Déconnexion</button>
        </form>
    </div>
</body>
</html>
