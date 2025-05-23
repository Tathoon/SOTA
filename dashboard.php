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
    <title>Fashion Chic - Tableau de bord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container {
            background: #fff;
            padding: 2.8rem 2.4rem 2.5rem 2.4rem;
            border-radius: 1.5rem;
            box-shadow: 0 6px 32px rgba(239,35,60,0.09);
            max-width: 480px;
            width: 98vw;
            text-align: center;
            margin: 7vh auto 0 auto;
        }
        .dashboard-container h1 {
            color: #ef233c;
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1.4rem;
            letter-spacing: 1.3px;
        }
        .user-group {
            display: inline-block;
            background: linear-gradient(90deg, #ffb3b3 0%, #ef233c 100%);
            color: #fff;
            font-size: 1.06rem;
            font-weight: 600;
            border-radius: 1rem;
            padding: 0.5rem 1.3rem;
            margin-bottom: 1.2rem;
            margin-top: 0.7rem;
            letter-spacing: 1px;
            box-shadow: 0 1px 10px rgba(239,35,60,0.09);
        }
        .logout-btn {
            margin-top: 2rem;
        }
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 1rem 0.3rem 1.5rem 0.3rem;
            }
            .dashboard-container h1 {
                font-size: 1.25rem;
            }
            .user-group {
                font-size: 0.98rem;
                padding: 0.5rem 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?> !</h1>
        <?php if (!empty($_SESSION['group'])): ?>
            <div class="user-group"><?php echo htmlspecialchars($_SESSION['group']); ?></div>
        <?php else: ?>
            <div class="user-group" style="background: #fff;color:#ef233c;border:1.2px solid #ef233c;">Aucun groupe trouvé</div>
        <?php endif; ?>
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">Déconnexion</button>
        </form>
    </div>
</body>
</html>
