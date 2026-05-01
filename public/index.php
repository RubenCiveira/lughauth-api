<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Civi\Lughauth\Bootstrap\Micro;
use Civi\Lughauth\Bootstrap\Plugin\AccessPlugin;
use Civi\Lughauth\Bootstrap\Plugin\MultiTenantPlugin;
use Civi\Lughauth\Bootstrap\Plugin\DocumentPlugin;
use Civi\Lughauth\Bootstrap\Plugin\NotificationPlugin;

$micro = new Micro(new ContainerBuilder());
$micro->register(new AccessPlugin());
$micro->register(new MultiTenantPlugin());
$micro->register(new DocumentPlugin());
$micro->register(new NotificationPlugin());
$micro->run();
