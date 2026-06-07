<?php

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Vynútime SQLite pred načítaním Symfony env, aby sme prepísali Docker DATABASE_URL
$testDb = dirname(__DIR__) . '/var/test.db';
putenv("DATABASE_URL=sqlite:///$testDb");
$_ENV['DATABASE_URL'] = "sqlite:///$testDb";
$_SERVER['DATABASE_URL'] = "sqlite:///$testDb";

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Vytvor čistú testovú databázu pred každým spustením testov
$kernel = new \App\Kernel('test', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$schemaTool = new SchemaTool($em);
$schemaTool->dropDatabase();
$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

$kernel->shutdown();
