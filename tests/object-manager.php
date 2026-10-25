<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Watchdog\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', false);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
