<?php

use support\Request;
use think\facade\Db;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

//$worker = new Worker('websocket://0.0.0.0:8484');
$worker = new Worker('http://0.0.0.0:9502');

//$worker->onWorkerStart = function ($worker) {
// 将db实例存储在全局变量中(也可以存储在某类的静态成员中)
//    global $db;
//    $db = new \Workerman\MySQL\Connection('127.0.0.1', '3308', 'root', '123456', 'test');
//};
$worker->onMessage = function (TcpConnection $connection, $data) {

    // 执行SQL
//    $all_tables = $db->query('show tables');
//    $connection->send(json_encode($all_tables));

    // 获取所有数据
//    $res = $db->select('id,sex')->from('user')->where('sex= :sex AND id = :id')->bindValues(array('sex' => 'male', 'id' => 1))->query();
    //等价于
//    $db->select('id,sex')->from('user')->where("sex= 'male' AND id = 1")->query();
    //等价于
//    $db->query("SELECT id,sex FROM `user` WHERE sex='male' AND ID = 1");


//    // 获取一行数据
//    $db->select('ID,Sex')->from('Persons')->where('sex= :sex')->bindValues(array('sex' => 'M'))->row();
//    //等价于
//    $db->select('ID,Sex')->from('Persons')->where("sex= 'M' ")->row();
//    //等价于
//    $db->row("SELECT ID,Sex FROM `Persons` WHERE sex='M'");
//
//    // 获取一列数据
//    $db->select('ID')->from('Persons')->where('sex= :sex')->bindValues(array('sex' => 'M'))->column();
//    //等价于
//    $db->select('ID')->from('Persons')->where("sex= 'F' ")->column();
//    //等价于
//    $db->column("SELECT `ID` FROM `Persons` WHERE sex='M'");
//
//    // 获取单个值
//    $db->select('ID')->from('Persons')->where('sex= :sex')->bindValues(array('sex' => 'M'))->single();
//    //等价于
//    $db->select('ID')->from('Persons')->where("sex= 'F' ")->single();
//    //等价于
//    $db->single("SELECT ID FROM `Persons` WHERE sex='M'");
//
//    // 复杂查询
//    $db->select('*')->from('table1')->innerJoin('table2', 'table1.uid = table2.uid')->where('age > :age')->groupBy(array('aid'))->having('foo="foo"')->orderByASC/*orderByDESC*/ (array('did'))
//        ->limit(10)->offset(20)->bindValues(array('age' => 13));
//    // 等价于
//    $db->query('SELECT * FROM `table1` INNER JOIN `table2` ON `table1`.`uid` = `table2`.`uid`
//WHERE age > 13 GROUP BY aid HAVING foo="foo" ORDER BY did LIMIT 10 OFFSET 20');
//
//    // 插入
//    $insert_id = $db->insert('Persons')->cols(array(
//        'Firstname' => 'abc',
//        'Lastname'  => 'efg',
//        'Sex'       => 'M',
//        'Age'       => 13))->query();
//    //等价于
//    $insert_id = $db->query("INSERT INTO `Persons` ( `Firstname`,`Lastname`,`Sex`,`Age`)
//VALUES ( 'abc', 'efg', 'M', 13)");
//
//    // 更新
//    $row_count = $db->update('Persons')->cols(array('sex'))->where('ID=1')
//        ->bindValue('sex', 'F')->query();
//    //等价于
//    $row_count = $db->update('Persons')->cols(array('sex' => 'F'))->where('ID=1')->query();
//    // 等价于
//    $row_count = $db->query("UPDATE `Persons` SET `sex` = 'F' WHERE ID=1");
//
//    // 删除
//    $row_count = $db->delete('Persons')->where('ID=9')->query();
//    // 等价于
//    $row_count = $db->query("DELETE FROM `Persons` WHERE ID=9");

    //使用think-orm
    // 数据库配置信息设置（全局有效）
    Db::setConfig([
        // 默认数据连接标识
        'default'     => 'mysql',
        // 数据库连接信息
        'connections' => [
            'mysql' => [
                // 数据库类型
                'type'     => 'mysql',
                // 主机地址
                'hostname' => '127.0.0.1',
                // 用户名
                'username' => 'root',
                // 数据库名
                'database' => 'test',
                // 数据库密码
                'password' => '123456',
                // 数据库连接端口
                'hostport' => '3308',
                // 数据库编码默认采用utf8
                'charset'  => 'utf8mb4',
                // 数据库表前缀
                'prefix'   => '',
                // 数据库调试模式
                'debug'    => true,
            ],
        ],
    ]);

    $res = Db::table('user')->where('id', '>', 1)->find();

    $connection->send(json_encode($res));
};


// 运行worker
Worker::runAll();