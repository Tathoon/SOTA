<?php
// SOTA Fashion - Page d'accueil
require_once 'includes/session.php';

// Rediriger vers login si non connecté, sinon vers dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>