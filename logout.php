<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
authLogout();
flash('success', 'Sesión cerrada correctamente.');
redirect('login.php');
