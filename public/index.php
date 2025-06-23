<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Civi\Lughauth\Shared\Infrastructure\Micro;
use Civi\Lughauth\Bootstrap\Plugin\AccessPlugin;
use Civi\Lughauth\Bootstrap\Plugin\MultiTenantPlugin;


// // Crea la app Slim
$micro = new Micro(new ContainerBuilder());
$micro->register(new AccessPlugin() );
$micro->register(new MultiTenantPlugin() );
$micro->run();
