<?php

//https://blog.csdn.net/qq_38287952/article/details/83104718
//闭包函数
//$closure = function ($name) {
//    return sprintf('Hello %s', $name);
//};
//
//echo $closure('Yee Jason');

/*$numberPlusOne = array_map(function ($number) {
    return $number + 1;
}, [1, 2, 3]);

print_r($numberPlusOne);*/

// 使用use关键字，把多个参数传入闭包时，需要还是用，号分隔开。具名函数enclosePerson() 有个名为$name的参数，这个函数返回一个闭包对象，而且这个闭包对象封装了 $name参数,即便返回的闭包对象跳出了 enclosePerson() 函数的作用域，它也会记住$name参数的值，因为$name变量仍在闭包中

/*function enclosePerson($name)
{
    return function ($doCommand) use ($name) {
        return sprintf('%s, %s', $name, $doCommand);
    };
}

$clay = enclosePerson('Clay');

echo $clay('get me sweet tea!');*/

//class APP
//{
//    protected $routes = array();
//    protected $responseStatus = '200 ok';
//    protected $responseContentType = 'text/html';
//    protected $responseBody = 'hello world';
//
//    public function addRoute($routePath, $routeCallback)
//    {
//        $this->routes[$routePath] = $routeCallback->bindTo($this, __CLASS__);
//    }
//
//    public function dispatch($currentPath)
//    {
//        foreach ($this->routes as $routePath => $callback) {
//            if ($routePath == $currentPath) {
//                $callback();
//            }
//        }
//
//        header('HTTP/1.1' . $this->responseStatus);
//        header('Content-type' . $this->responseContentType);
//        header('Content-length' . $this->responseBody);
//
//        echo $this->responseBody;
//    }
//}

/*我们要特别注意addRoute方法，这个方法的参数分别是一个路由路径和路由回调，dispatch() 方法的参数是当前的HTTP请求的路径，它会调用匹配的路由回调，我们把路由绑定到当前的App实例上，这么做就能再回调函数中处理App实例的状态 。 */

//$app = new App();
//$app->addRoute('/users/josh', function () {
//    $this->responseContentType = 'application/json; charset=utf8';
//    $this->responseBody        = '{"name" : "yee Jason"}';
//});
////print_r($app);
//$app->dispatch('/users/josh');

/* 示例三：匿名函数作为回调函数参数传入 */
//function callback($callback)
//{
//    $callback();
//}
//
//function test()
//{
//    // 闭包测试函数
//    echo '这里是闭包测试函数体';
//}
//
//callback('test');

/* 示例三修改：匿名函数作为参数传入，并且携带参数 */
/*$content = '这里是闭包函数的输出内容';
$a = function ($callback) use ($content) {
    $callback($content);
};

function callback($content)
{
    // 闭包函数
    echo $content;
}

$a('callback');*/

/*$message = 'hello';

//// 没有 "use"
//$example = function () {
//    var_dump($message);
//};
//echo $example();

// 继承 $message
$example = function () use ($message) {
    var_dump($message);
};
echo $example();

// Inherited variable's value is from when the function
// is defined, not when called
$message = 'world';
echo $example();

// Reset message
$message = 'hello';

// Inherit by-reference
$example = function () use (&$message) {
    var_dump($message);
};
echo $example();

// The changed value in the parent scope
// is reflected inside the function call
$message = 'world';
echo $example();

// Closures can also accept regular arguments
$example = function ($arg) use ($message) {
    var_dump($arg . ' ' . $message);
};
$example("hello");*/

//在第三个echo的时候，程序先改变了$message的值，但是输出仍然为 "hello"，而非"word"。
//由此可见use的参数继承的值是在匿名函数创建的时候就不会再更改了。除非在参数前加 & ，但这样的话跟直接传参没有多大的区别，那么use也没有意义了。

//function closureFunc1(){
//    $func = function(){
//        echo "hello";
//    };
//    $func();
//}
//closureFunc1();
//输出: hello

//function closureFunc2(){
//    $num = 1;
//    $func = function(){
//        echo $num;
//    };
//    $func();
//}
//closureFunc2();

//上面的函数运行后，会报Notice错误，说明我们不能在匿名函数中这样使用局部变量，这时候就要引用一个php的关键字 use， 代码如下
//function closureFunc2()
//{
//    $num  = 1;
//    $func = function () use ($num) {
//        echo $num;
//    };
//    $func();
//}
//
//closureFunc2();

//function closureFunc3()
//{
//    $num  = 1;
//    $func = function () use ($num) {
//        echo $num;
//    };
//    return $func;
//}
//
//$func = closureFunc3(); //函数返回匿名函数
//$func(); //然后我们在用$func() 调用

//function closureFunc4()
//{
//    $num  = 1;
//    $func = function ($str) use ($num) {
//        echo $num;
//        echo "\n";
//        echo $str;
//    };
//    return $func;
//}
//
//$func = closureFunc4();
//$func("hello, closure4");
//输出:
//1
//hello, closure4

//function closureFunc5(){
//    $num = 1;
//    $func = function() use($num) {
//        echo "\n";
//        $num++;
//        echo $num;
//    };
//    echo "\n";
//    echo $num;
//    return $func;
//}
//$func = closureFunc5();
//$func();
//$func();
//$func();

//function closureFunc5(){
//    $num = 2;
//    $func = function() use(&$num) {
//        echo "\n";
//        $num++;
//        echo $num;
//    };
//    echo "\n";
//    echo $num;
//    return $func;
//}
//$func = closureFunc5();
//$func();
//$func();
//$func();
//输出:
// 2
// 3
// 4
// 5
//把匿名函数当作参数传递
//function callFunc($func)
//{
//    $func("argv");
//}
//
//callFunc(function ($str) {
//    echo $str;
//});
//输出：
// argv

/* 声明一个简单的容器类 */
//class Container
//{
//    private $diList = array();
//
//    /* 核心方法之一，用于绑定服务
//    * @param string $className 类名称
//    * @param mixed $concrete 依赖在容器中的存储方式，可以是类名字符串，数组，一个实例化对象，或者是一个匿名函数
//    */
//    public function set($className, $concrete)
//    {
//        $this->diList[$className] = $concrete;
//    }
//
//    /*
//    * 核心方法之二，用于获取服务对象
//    * @param string $className 将要获取的依赖的名称
//    * @return object 返回一个依赖的实例化对象
//    */
//    public function get($className)
//    {
//        if (isset($this->diList[$className])) {
//            return $this->diList[$className];
//        }
//        return null;
//    }
//}
//
///* 数据库连接类 */
//class Connection
//{
//    public function __construct($dbParams)
//    {
//        // connect the database...
//    }
//
//    public function someDbTask()
//    {
//        // code...
//    }
//}
//
///* 会话控制类 */
//class Session
//{
//    public function openSession()
//    {
//        session_start();
//    }
//    // code...
//}
//
//$container = new Container();
//
//// 使用容器注册数据库连接服务
//$container->set('db', function () {
//    return new Connection(array(
//        "host"     => "localhost",
//        "username" => "root",
//        "password" => "123456",
//        "dbname"   => "miaosha"
//    ));
//});
//
//// 使用容器注册会话控制服务
//$container->set('session', function () {
//    return new Session();
//});
//
//// 获取之前注册到容器中的服务，并进行业务的处理
//$container->get('db')->someDbTask();
//$container->get('session')->openSession();
/*以上代码是对容器的使用方法，其中注册了 db 和 session 两个服务，这里使用匿名函数作为依赖的存储方式，在调用 $container->set() 方法进行注册服务时实际上并没有进行实例化，而是在调用 $container->get() 方法获取依赖的时候才执行匿名函数，并将实例化对象返回，这样实现了按需实例化，不用则不实例化，提高了程序的运行效率。*/

//实现一个容器类
//class Container
//{
//    public $bindings;
//
//    public function bind($abstract, $concrete)
//    {
//        $this->bindings[$abstract] = $concrete;
//    }
//
//    public function make($abstract, $parameters = [])
//    {
//        return call_user_func_array($this->bindings[$abstract], $parameters);
//    }
//}
//
////服务注册（绑定）
//$container = new Container();
//
//$container->bind('db', function ($arg1, $arg2) {
//    return new DB($arg1, $arg2);
//});
//
//$container->bind('session', function ($arg1, $arg2) {
//    return new Session($arg1, $arg2);
//});
//
//$container->bind('fs', function ($arg1, $arg2) {
//    return new FileSystem($arg1, $arg2);
//});
//
////容器依赖
//class Writer
//{
//    protected $_db;
//    protected $_filesystem;
//    protected $_session;
//    protected $container;
//
//    public function write(Container $container)
//    {
//        $this->_db         = $container->make('db', [1, 2]);
//        $this->_filesystem = $container->make('session', [3, 4]);
//        $this->_session    = $container->make('fs', [5, 6]);
//    }
//}
//
//$writer = new Writer($container);

/**
 * Class MyCloneable.
 */
//class MyCloneable
//{
//    public $objectA;
//    public $objectB;
//
//    function __clone()
//    {
//        echo "MyCloneable::__clone\n";
//        // 强制复制一份this->objectA， 否则仍然指向同一个对象
//        $this->objectA = clone $this->objectA;
//    }
//}
//
///**
// * Class SubObject
// */
//class SubObject
//{
//    /**
//     * 静态计数器，对象每被实例化或clone一次，则+1
//     * @var int
//     */
//    public static $counter = 0;
//
//    public $instance;
//
//    public function __construct()
//    {
//        echo "SubObject::__construct\n\n";
//        $this->instance = ++ self::$counter;
//    }
//
//    public function __clone()
//    {
//        echo "SubObject::__clone\n\n";
//        $this->instance = ++ self::$counter;
//    }
//}
//parent this self
class Base
{
    public function __construct()
    {
        echo 'Base constructor!', PHP_EOL;
    }

    public static function getSelf()
    {
        return new self();
    }

    public static function getInstance()
    {
        return new static();
    }

    public function selfFoo()
    {
        return self::foo();
    }

    public function staticFoo()
    {
        return static::foo();
    }

    public function thisFoo()
    {
        return $this->foo();
    }

    public function foo()
    {
        echo 'Base Foo!', PHP_EOL;
    }
}

class Child extends Base
{
    public function __construct()
    {
        echo 'Child constructor!', PHP_EOL;
    }

    public function foo()
    {
        echo 'Child Foo!', PHP_EOL;
    }
}

//在函数引用上，	self 与	static 的区别是：对于静态成员函数，self 指向代码当前类，static 指向调用类；对于非静态成员函数，self 抑制多态，指向当前类的成员函数，static 等同于this,动态指向调用类的函数。
//$base  = Child::getSelf();
//$child = Child::getInstance();
//
//$child->selfFoo();
//$child->staticFoo();
//$child->thisFoo();

// $name = '一标段';

// $num_arr = ['总承包部' => '总承包部', '一标段' => 'A1', '二标段' => 'A2', '三标段' => 'A3', '四标段' => 'A4', '五标段' => 'A5', '六标段' => 'A6', '七标段' => 'A7', '八标段' => 'A8', '九标段' => 'A9'];

// $bd = $num_arr[$name];

//var_dump($bd);

// eval(var_dump('ddddd'));

var_dump(gettype(null));
