<?php
require_once __DIR__ . '/config/helpers.php';
redirect(current_user() ? 'dashboard.php' : 'login.php');