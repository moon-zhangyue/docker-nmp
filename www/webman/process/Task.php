<?php
namespace process;

use support\Log;
use Workerman\Crontab\Crontab;

class Task
{
    public function onWorkerStart()
    {
        // 每秒钟执行一次
        // new Crontab('*/1 * * * * *', function () {
        //     Log::info(date('Y-m-d H:i:s') . '每秒钟执行一次');
        // });

        // // 每5秒执行一次
        // new Crontab('*/5 * * * * *', function () {
        //     echo date('Y-m-d H:i:s') . "\n";
        // });

        // // 每分钟执行一次
        // new Crontab('0 */1 * * * *', function () {
        //     Log::info(date('Y-m-d H:i:s') . '每分钟执行一次');
        // });

        // // 每5分钟执行一次
        // new Crontab('0 */5 * * * *', function () {
        //     echo date('Y-m-d H:i:s') . "\n";
        // });

        // // 每分钟的第一秒执行
        // new Crontab('1 * * * * *', function () {
        //     Log::info(date('Y-m-d H:i:s') . '每分钟的第一秒执行');
        // });

        // // 每天的7点50执行，注意这里省略了秒位
        // new Crontab('50 7 * * *', function () {
        //     echo date('Y-m-d H:i:s') . "\n";
        // });
    }
}