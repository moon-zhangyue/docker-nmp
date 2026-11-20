<?php
$serv = new Swoole\Server("0.0.0.0", 9501, SWOOLE_BASE);
$serv->set(
    [
        'worker_num' => 1,
    ]
);

$serv->on('pipeMessage', function ($serv, $src_worker_id, $data) {
    echo "#{$serv->worker_id} message from #$src_worker_id: $data\n";
    sleep(10);//不接收sendMessage发来的数据，缓冲区将很快写满
});

$serv->on('receive', function (swoole_server $serv, $fd, $reactor_id, $data) {

});

//情况1：同步IO(默认行为)
// $userProcess = new Swoole\Process(function ($worker) use ($serv) {
//     while (1) {
//         var_dump($serv->sendMessage("big string", 0));//默认情况下，缓存区写满后，此处会阻塞
//     }
// }, false);

//情况2：通过enable_coroutine参数开启UserProcess进程的协程支持，为了防止其他协程得不到 EventLoop 的调度，
//Swoole会把sendMessage转换成异步IO
// $enable_coroutine = true;
// $userProcess      = new Swoole\Process(function ($worker) use ($serv) {
//     while (1) {
//         var_dump($serv->sendMessage("big string", 0));//缓存区写满后，不会阻塞进程,会报错
//     }
// }, false, 1, $enable_coroutine);

//情况3：在UserProcess进程里面如果设置了异步回调(例如设置定时器、Swoole\Event::add等)，
//为了防止其他回调函数得不到 EventLoop 的调度，Swoole会把sendMessage转换成异步IO
$userProcess = new Swoole\Process(function ($worker) use ($serv) {
    swoole_timer_tick(2000, function ($interval) use ($worker, $serv) {
        echo "timer\n";
    });
    while (1) {
        var_dump($serv->sendMessage("big string", 0));//缓存区写满后，不会阻塞进程,会报错
    }
}, false);

$serv->addProcess($userProcess);

$serv->start();
