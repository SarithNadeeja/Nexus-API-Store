<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::clearAdmin();
redirect('/nexus-admin/login.php?logout=1');
