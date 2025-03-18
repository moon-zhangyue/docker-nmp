<?php

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker('http://0.0.0.0:8080');

$worker->onMessage = function (TcpConnection $connection, Request $request) {
    $connection->send(json_encode($request->get()));
};

// 运行worker
Worker::runAll();