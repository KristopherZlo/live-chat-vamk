<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$response = $app->handleRequest(\Illuminate\Http\Request::create('/', 'GET'));
var_dump($response->getStatusCode());
echo "\n--- BODY ---\n";
echo substr($response->getContent(), 0, 200), "\n";
