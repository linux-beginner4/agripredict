<?php
require_once __DIR__ . '/includes/functions.php';
redirect(est_connecte() ? 'dashboard.php' : 'login.php');
