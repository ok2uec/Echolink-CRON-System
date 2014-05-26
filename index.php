<?php

use EcholinkSys\System;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Slim\Middleware\SessionCookie;
use Slim\Slim;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

require './vendor/autoload.php';

define("masterPassword", "heslotvoje", true);

define("host", "localhost", true);
define("username", "name", true);
define("password", "pass", true);
define("database", "dbname", true);



$app = new Slim(array(
    'templates.path' => './twig',
    'mode' => 'development'
        ));


$app->setName('Echolink CRON System');

$app->add(new SessionCookie(array(
    'expires' => '20 minutes',
    'path' => '/',
    'domain' => null,
    'secure' => false,
    'httponly' => false,
    'name' => 'slim_session',
    'secret' => 'CHANGE_ME',
    'cipher' => MCRYPT_RIJNDAEL_256,
    'cipher_mode' => MCRYPT_MODE_CBC
)));



$app->container->singleton('log', function () {
    $log = new Logger('Echolink CRON System');
    $log->pushHandler(new StreamHandler('./logs/app.log', Logger::DEBUG));
    return $log;
});

// Prepare view
$app->view(new Twig());
$app->view->parserOptions = array(
    'charset' => 'utf-8',
    'cache' => realpath('./templates/cache'),
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
$app->view->parserExtensions = array(new TwigExtension());


// dashboard
$app->get('/', function () use ($app) {

    $app->log->info("Echolink CRON System - '/' route");

    $echolinksys = new System("mysql:host=" . host . ";dbname=" . database, username, password);

    $dataHistory = $echolinksys->getHistoryLog();
    $dataEcholinkRep = $echolinksys->getRepeaterInArray();

    $app->render('index.twig', array(
        'dataHistory' => $dataHistory,
        'dataEcholinkRepeater' => $dataEcholinkRep
    ));
})->name('list');

// updating data
$app->get('/check', function () use ($app) {

    $app->log->info("Echolink CRON System - '/check' route");

    $echolinksys = new System("mysql:host=" . host . ";dbname=" . database, username, password);

    $echolinksys->dataFromTheServer();
    $app->log->info("Echolink CRON System - Update was performed from a remote server echolink.org");
 

    foreach ($echolinksys->messageEmail as $email) { 
        //set template for email!
        $body = "Byl změněn stav převaděče.\r\n" .
                "Call: " . $email["callname"] . "\r\n" .
                "Nyní stav: " . ($email["newStatus"]==1?"Online":"Offline") . "\r\n" .
                "Datum poslední změny: " . $email["oldCheckDate"] . "\r\n" .
                "Datum nynější změny: " . $email["checkDate"] . "\r\n";

        $echolinksys->mail($email["email"], "Echolink", $body, $from_name = "EcholinkSyS");
        $echolinksys->addHistoryLog("E-mail send to " . $email["callname"]);
        $app->log->info("Echolink CRON System - Email send to " . $email["callname"]);
    }

    $url = $app->urlFor('list', array());
    $app->redirect($url);
})->name('check');

// updating data
$app->get('/cron', function () use ($app) {

    $app->log->info("Echolink CRON System - '/cron' route");

    $echolinksys = new System("mysql:host=" . host . ";dbname=" . database, username, password);

    $echolinksys->dataFromTheServer();
    $app->log->info("Echolink CRON System - CRON -> Update was performed from a remote server echolink.org");

    $dataArray = array('time' => time(), 'message' => "Run finish");

    header("Content-Type: application/json");
    echo json_encode($dataArray);
    exit;
});


// add new repeater to list
$app->post('/add', function () use ($app) {

    $app->log->info("Echolink CRON System - '/add' route");

    $req = $app->request();

    if (masterPassword == $req->post('masterpassword')) {
        $echolinksys = new System("mysql:host=" . host . ";dbname=" . database, username, password);
        $status = $echolinksys->addRepeater($req->post('callname'), $req->post('email'));
        if ($status == true) {
            $app->log->info("Echolink CRON System - add new Repeater to DB");
            $app->flashNow('info', 'Add repeater!');
        } else {
            $app->log->info("Echolink CRON System - is exist Repeater to DB");
            $app->flashNow('info', 'Fail adding repeater!');
        }
    }

    $url = $app->urlFor('list', array());
    $app->redirect($url);
})->name('add');

// delete repeater
$app->post('/delete', function () use ($app) {

    $app->log->info("Echolink CRON System - '/delete' route");

    $req = $app->request();

    if (masterPassword == $req->post('masterpassword')) {
        $echolinksys = new System("mysql:host=" . host . ";dbname=" . database, username, password);
        $status = $echolinksys->removeRepeater($req->post('callname'));
        if ($status == true) {
            $app->log->info("Echolink CRON System - remove Repeater from DB");
            $app->flashNow('info', 'Delete repeater!');
        } else {
            $app->log->info("Echolink CRON System - fail removed repeater from DB");
            $app->flashNow('info', 'Fail removed repeater!');
        }
    }

    $url = $app->urlFor('list', array());
    $app->redirect($url);
})->name('delete');

$app->get('/api/:callname', function ($callname) use ($app) {
    $app->log->info("Echolink CRON System - '/api/" . $callname . "' route");

    $echolinksys = new System("mysql:host=" . host . ";dbname=" . database, username, password);
    $dataRepeater = $echolinksys->getRepeater($callname);
    $dataArray = array('callname' => $dataRepeater['callname'], 'checkDate' => $dataRepeater['checkDate'], 'status' => (boolean) $dataRepeater['status']);

    header("Content-Type: application/json");
    echo json_encode($dataArray);
    exit;
})->name('getApi');

// Run app
$app->run();
