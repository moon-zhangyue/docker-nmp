<?php

/*
 * 多维数组排序
 * */

//$a = array(
//    array('key1' => 940, 'key2' => 'aaa'),
//    array('key1' => 23, 'key2' => 'this'),
//    array('key1' => 894, 'key2' => 'dhis'),
//);
//自定义排序
//function asc_num($x, $y)
//{
//    if ($x['key1'] > $y['key1']) {
//        return true;
//    } elseif ($x['key1'] > $y['key1']) {
//        return false;
//    } else {
//        return 0;
//    }
//}

//usort($a, 'asc_num');


//function asc_string($x, $y)
//{
//    return strcasecmp($x['key2'], $y['key2']);
//}
//
//usort($a, 'asc_string');
//
//var_dump($a);

//$students = array(
//    256 => array('name' => 'Jon', 'grade' => 98.5),
//    2   => array('name' => 'Tom', 'grade' => 85.1),
//    9   => array('name' => 'Steve', 'grade' => 94.0),
//    364 => array('name' => 'Bob', 'grade' => 85.1),
//    68  => array('name' => 'Vance', 'grade' => 74.6),
//);
////按名字排序
//function name_sort($x, $y)
//{
//    return strcasecmp($x['name'], $y['name']);
//}

//按成绩排序
//function grade_sort($x, $y)
//{
//    return ($x['grade'] < $y['grade']);
//}

//uasort($students, 'name_sort'); //uasort使用用户自定义的比较函数对数组按 '键值' 进行排序
//请使用 uksort() 函数对数组按 '键名' 进行排序，该函数使用用户自定义的比较函数进行排序。
//var_dump($students);

//usort($students, 'name_sort');//使用用户自定义的比较函数对数组进行排序。键重写
//var_dump($students);

//uasort($students, 'grade_sort');
//var_dump($students);

//统计递归次数 使用静态变量
//$student = array(
//    256 => array('name' => 'Jon', 'grade' => 98.5),
//    2   => array('name' => 'Tom', 'grade' => 85.1),
//    9   => array('name' => 'Steve', 'grade' => 94.0),
//    364 => array('name' => 'Bob', 'grade' => 85.1),
//    68  => array('name' => 'Vance', 'grade' => 74.6),
//);
////按名字排序
//function name_sort($x, $y)
//{
//    static $count = 1;
//    echo "<p>Interation $count : {$x['name']} vs {$y['name']} .</p>\n";
//    $count++;
//    return strcasecmp($x['name'], $y['name']);
//}
//
//uasort($student, 'name_sort');
//var_dump($student);

//$b = 20;
//$c = 40;
//$a = $b > $c;
//echo $a;
//var_dump($a);

//$a = floatval('5.497％');
//$b = floatval('100%');
//var_dump($a);
//var_dump($b);
//if ($a > $b) {
//    echo 'a';
//}
//var_dump(strrev(0110));
//var_dump(0110);
//var_dump(0110 == strrev(0110));

/*$gen = (function () {
    yield 1;
    yield 2;

    return 3;
})();

foreach ($gen as $val) {
    echo $val, PHP_EOL;
}

echo $gen->getReturn(), PHP_EOL;*/
# output
//1
//2
//3

/*class Database
{
    private $instance;

    private function __construct()
    {
        // Do nothing.
    }

    private function __clone()
    {
        // Do nothing.
    }

    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

$a = Database::getInstance();
$b = Database::getInstance();*/
// true  var_dump($a === $b);


/*
观察者接口
*/

/*interface InterfaceObserver
{
    function onListen($sender, $args);

    function getObserverName();
}

// 可被观察者接口
interface InterfaceObservable
{
    function addObserver($observer);

    function removeObserver($observer_name);
}

// 观察者抽象类
abstract class Observer implements InterfaceObserver
{
    protected $observer_name;

    function getObserverName()
    {
        return $this->observer_name;
    }

    function onListen($sender, $args)
    {

    }
}

// 可被观察类
abstract class Observable implements InterfaceObservable
{
    protected $observers = array();

    public function addObserver($observer)
    {
        if ($observer instanceof InterfaceObserver) { //判断对象是否属于某个类
            $this->observers[] = $observer;
        }
    }

    public function removeObserver($observer_name)
    {
        foreach ($this->observers as $index => $observer) {
            if ($observer->getObserverName() === $observer_name) {
                array_splice($this->observers, $index, 1);
                return;
            }
        }
    }
}

// 模拟一个可以被观察的类
class A extends Observable
{
    public function addListener($listener)
    {
        foreach ($this->observers as $observer) {
            $observer->onListen($this, $listener);
        }
    }
}

// 模拟一个观察者类
class B extends Observer
{
    protected $observer_name = 'B';

    public function onListen($sender, $args)
    {
        var_dump($sender);
        echo "<br>";
        var_dump($args);
        echo "<br>";
    }
}

// 模拟另外一个观察者类
class C extends Observer
{
    protected $observer_name = 'C';

    public function onListen($sender, $args)
    {
        var_dump($sender);
        echo "<br>";
        var_dump($args);
        echo "<br>";
    }
}

$a = new A();
// 注入观察者
$a->addObserver(new B());
$a->addObserver(new C());

// 可以看到观察到的信息
$a->addListener('D');

// 移除观察者
$a->removeObserver('B');*/

// 打印的信息：
// object(A)#1 (1) { ["observers":protected]=> array(2) { [0]=> object(B)#2 (1) { ["observer_name":protected]=> string(1) "B" } [1]=> object(C)#3 (1) { ["observer_name":protected]=> string(1) "C" } } }
// string(1) "D"
// object(A)#1 (1) { ["observers":protected]=> array(2) { [0]=> object(B)#2 (1) { ["observer_name":protected]=> string(1) "B" } [1]=> object(C)#3 (1) { ["observer_name":protected]=> string(1) "C" } } }
// string(1) "D"

//单例模式
/*class Singleton
{
    private static $instance = null;

    public static function getInstance()
    {
        if(self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct(){}
    private function __clone(){}
    private function __wakeup(){}
}*/

/*class Database
{
    // 声明$instance为私有静态类型，用于保存当前类实例化后的对象
    private static $instance = null;
    // 数据库连接句柄
    private $db = null;

    // 构造方法声明为私有方法，禁止外部程序使用new实例化，只能在内部new
    private function __construct($config = array())
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s', $config['db_host'], $config['db_name']);
        $this->db = new PDO($dsn, $config['db_user'], $config['db_pass']);
    }

    // 这是获取当前类对象的唯一方式
    public static function getInstance($config = array())
    {
        // 检查对象是否已经存在，不存在则实例化后保存到$instance属性
        if(self::$instance == null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    // 获取数据库句柄方法
    public function db()
    {
        return $this->db;
    }

    // 声明成私有方法，禁止克隆对象
    private function __clone(){}
    // 声明成私有方法，禁止重建对象
    private function __wakeup(){}
}
//再通过getInstance()方法使用类对象，

$config = array(
    'db_name' => 'test',
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '123456'
);

$db1 = Database::getInstance($config);
var_dump($db1);
$db2 = Database::getInstance($config);
var_dump($db2);
$db3 = Database::getInstance($config);
var_dump($db3);*/
/*输出信息如下：
object(Database)[1]
  private 'db' => object(PDO)[2]
object(Database)[1]
  private 'db' => object(PDO)[2]
object(Database)[1]
  private 'db' => object(PDO)[2]*/


/*$datetime = new DateTime();
var_dump($datetime->format('Y-m-d H:i:s'));

$datetime = new DateTime('2018-06-13');
var_dump($datetime);

$datetime = DateTime::createFromFormat('Ymd', '20180618');
var_dump($datetime->format('Y-m-d'));*/


/*class Cart
{
    const PRICE_BUTTER = 1.00;
    const PRICE_MILK   = 3.00;
    const PRICE_EGGS   = 6.95;

    protected $products = array();

    public function add($product, $quantity)
    {
        $this->products[$product] = $quantity;
    }

    public function getQuantity($product)
    {
        return isset($this->products[$product]) ? $this->products[$product] : FALSE;
    }

    public function getTotal($tax)
    {
        $total = 0.00;

        $callback = function ($quantity, $product) use ($tax, &$total) {
            $pricePerItem = constant(__CLASS__ . "::PRICE_" .
                strtoupper($product));
            var_dump($quantity);
            var_dump($pricePerItem);

            $total += ($pricePerItem * $quantity) * ($tax + 1.0);
        };
        var_dump($this->products);
        array_walk($this->products, $callback);
        return round($total, 2);
    }
}

$my_cart = new Cart();

// 往购物车里添加条目
$my_cart->add('butter', 1);
$my_cart->add('milk', 3);
$my_cart->add('eggs', 6);

// 打出出总价格，其中有 5% 的销售税.
print $my_cart->getTotal(0.05) . "\n";
// 最后结果是 54.29*/

/*$arr = ['a', 'b', 1, 2, 3];

$new_arr = array_filter($arr, function ($val) {
    var_dump($val);
    return is_numeric($val);
});

var_dump($new_arr);
//返回结果
//array (size=3)
//  2 => int 1
//  3 => int 2
//  4 => int 3*/

/*$arr1 = [1, 2, 3, 4, 5];
$arr2 = [6, 7, 8, 9, 10];

//函数写前面，数组参数写后面
$new_arr = array_map(function ($val1, $val2) {
    return $val1 + $val2;
}, $arr1, $arr2);

var_dump($new_arr);*/
//返回结果
//array (size=5)
//  0 => int 7
//  1 => int 9
//  2 => int 11
//  3 => int 13
//  4 => int 15

//将数组中的元素用于某种操作
/*$arr = ['a', 'b', 'c'];
array_walk($arr, function ($val, $key) {
    echo "{$key} is {$val} <br/>";
});
//返回结果
//0 is a
//1 is b
//2 is c

//改变数组中的值，传参的时候使用引用
array_walk($arr, function (&$val, $key) {
    $val .= $val;
});
var_dump($arr);*/
//array (size=3)
//  0 => string 'aa' (length=2)
//  1 => string 'bb' (length=2)
//  2 => string 'cc' (length=2)

/*
 * 异同点
array_filter() 重点在于过滤（而不是新增）某个元素，当你处理到一个元素时，返回过滤后的数组
array_map() 重点在于遍历一个数组或多个数组的元素，返回一个新的数组
array_walk() 重点在于遍历数组进行某种操作

array_filter() 和 array_walk()对一个数组进行操作，数组参数在前，函数参数在后
array_map() 可以处理多个数组，因此函数参数在前，数组参数在后，可以根据实际情况放入多个数组参数
 * */

//var_dump($GLOBALS);

//带有StdClass的示例
/*$json        = '{ "foo": "bar", "number": 42 }';
$stdInstance = json_decode($json);

echo $stdInstance -> foo . PHP_EOL; //"bar"
echo $stdInstance -> number . PHP_EOL; //42

//Example with associative array
$array = json_decode($json, true);

echo $array['foo'] . PHP_EOL; //"bar"
echo $array['number'] . PHP_EOL; //42*/
//
//$obj = (object)array('qualitypoint', 'technologies', 'India');
//var_dump($obj);
//print_r($obj);

/*function has_string_keys(array $array)
{
    return count(array_filter(array_keys($array), 'is_string')) > 0;
}*/

/*$a      = new stdClass();
$a->foo = "bar";
$b      = clone $a;
var_dump($a === $b);
var_dump($a);
var_dump($b);*/

// $arr = [
//     [
//         "date" => "aaaa",
//         "sunrise" => "06:17"
//     ],
//     [
//         "date" => "bbbbb",
//         "sunrise" => "06:17"
//     ]
// ];
// var_dump(json_encode(json_encode($arr)));
// $a = '[{\"date\":\"aaaa\",\"sunrise\":\"06:17\"},{\"date\":\"bbbbb\",\"sunrise\":\"06:17\"}]';
// var_dump(json_decode(stripslashes($a),true));

echo "<pre>";
print_r(opcache_get_status()['jit']);