<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
require_once __DIR__ . '/vendor/autoload.php';


/*使用HTTP协议对外提供Web服务*/
// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker("http://0.0.0.0:2345");

// 启动4个进程对外提供服务
$http_worker->count = 4;

// 接收到浏览器发送的数据时回复hello world给浏览器
$http_worker->onMessage = function(TcpConnection $connection, Request $request)
{
    // 向浏览器发送hello world
    $connection->send('hello world!!!这是一个Http协议的Web服务器');
};

/*自定义通讯协议*/
//$json_worker = new Worker('JsonNL://0.0.0.0:1234');
//$json_worker->onMessage = function(TcpConnection $connection, $data) {
//
//    // $data就是客户端传来的数据，数据已经经过JsonNL::decode处理过
//    echo $data;
//
//    // $connection->send的数据会自动调用JsonNL::encode方法打包，然后发往客户端
//    $connection->send(array('code'=>0, 'msg'=>'ok'));
//
//};

// 运行worker
Worker::runAll();