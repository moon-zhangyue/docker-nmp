<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

//用户注册
Route::post('register', 'user/register');

// 用户Elasticsearch相关路由
Route::post('user/search', 'user/search'); // 用户搜索接口
Route::post('user/searchbyage', 'user/searchByAge'); // 按年龄范围搜索用户
Route::post('user/aggregate/country', 'user/aggregateByCountry'); // 按国家聚合用户数量
Route::post('user/bulk-index', 'user/bulkIndexUsers'); // 批量索引用户数据
Route::post('user/searchwithhighlight', 'user/searchWithHighlight'); // 带高亮的搜索功能
Route::post('user/searchfuzzy', 'user/fuzzySearch'); // 模糊搜索功能
Route::post('user/index', 'user/indexUser'); // 创建或更新用户索引
Route::post('user/import-to-es', 'user/importUsersToEs'); // 导入数据库用户数据到Elasticsearch

// 队列相关路由
Route::post('queue/push', 'Queue/push');
Route::get('queue/status', 'Queue/status');
Route::post('queue/clear', 'Queue/clear');

//think-queue队列
Route::post('user/redis_queue', 'user/redis_queue'); //redis队列
Route::post('user/kafka_queue', 'user/kafka_queue'); //kafka队列（User控制器）
Route::post('KafkaQueue/push', 'KafkaQueue/push'); //kafka队列

//监控指标
Route::get('metrics/prometheus', 'metrics/prometheus'); //Prometheus指标
Route::get('metrics/index', 'metrics/index');
Route::get('metrics/health', 'metrics/health'); //健康检查接口
Route::post('metrics/reset', 'metrics/reset'); //重置监控指标

// 测试路由
Route::get('test', 'Test/index');

// 队列测试路由
Route::post('queue/test', 'QueueTest/push');
Route::post('queue/test/delay', 'QueueTest/pushDelay');


// 红包
Route::get('redpacket/index', 'RedPacketController/index');
Route::post('redpacket/create', 'RedPacketController/create');
Route::post('redpacket/grab', 'RedPacketController/grab');

// 停车场管理
Route::get('parking/lot', 'ParkingLotController/index');
Route::get('parking/lot/:id', 'ParkingLotController/detail');
Route::post('parking/lot', 'ParkingLotController/add');
Route::put('parking/lot/:id', 'ParkingLotController/update');
Route::delete('parking/lot/:id', 'ParkingLotController/delete');
Route::post('parking/lot/update-spaces', 'ParkingLotController/updateOccupiedSpaces');
Route::get('parking/lot/status', 'ParkingLotController/status');

// 停车记录管理
Route::get('parking/record', 'ParkingRecordController/index');
Route::get('parking/record/:id', 'ParkingRecordController/detail');
Route::get('parking/record/plate/:plateNumber', 'ParkingRecordController/getByPlateNumber');
Route::post('parking/record/pay', 'ParkingRecordController/pay');
Route::post('parking/record', 'ParkingRecordController/add');

// 车辆管理
Route::get('vehicle', 'VehicleController/index');
Route::get('vehicle/:plateNumber', 'VehicleController/detail');
Route::post('vehicle', 'VehicleController/add');
Route::put('vehicle/:plateNumber', 'VehicleController/update');
Route::delete('vehicle/:plateNumber', 'VehicleController/delete');
Route::post('vehicle/monthly-pass', 'VehicleController/addMonthlyPass');
Route::put('vehicle/monthly-pass/:id', 'VehicleController/updateMonthlyPass');
Route::delete('vehicle/monthly-pass/:id', 'VehicleController/deleteMonthlyPass');

// 闸机管理
Route::get('gate/device', 'GateController/deviceList');
Route::get('gate/device/:id', 'GateController/deviceDetail');
Route::post('gate/device', 'GateController/addDevice');
Route::put('gate/device/:id', 'GateController/updateDevice');
Route::delete('gate/device/:id', 'GateController/deleteDevice');
Route::post('gate/entry', 'GateController/vehicleEntry');
Route::post('gate/exit', 'GateController/vehicleExit');
Route::post('gate/open', 'GateController/openGate');

// 停车费规则管理
Route::get('parking/fee-rule', 'ParkingFeeRuleController/index');
Route::get('parking/fee-rule/:id', 'ParkingFeeRuleController/detail');
Route::post('parking/fee-rule', 'ParkingFeeRuleController/add');
Route::put('parking/fee-rule/:id', 'ParkingFeeRuleController/update');
Route::delete('parking/fee-rule/:id', 'ParkingFeeRuleController/delete');
Route::post('parking/fee-rule/calculate', 'ParkingFeeRuleController/calculateFee');


// 停车场监控指标管理路由
Route::group('parking/metrics', function () {
    // 记录数据的接口
    Route::post('occupancy', 'ParkingMetricsController/recordOccupancyRate'); // 记录车位占用率
    Route::post('device-status', 'ParkingMetricsController/recordDeviceStatus'); // 记录设备状态
    Route::post('plate-recognition', 'ParkingMetricsController/recordPlateRecognition'); // 记录车牌识别性能
    Route::post('gate-operation', 'ParkingMetricsController/recordGateOperation'); // 记录闸机操作日志
    Route::post('peak-hours', 'ParkingMetricsController/analyzePeakHours'); // 分析高峰期数据
    Route::post('duration-stats', 'ParkingMetricsController/generateParkingDurationStats'); // 生成停车时长统计

    // 查询数据的接口
    Route::get('peak-analysis', 'ParkingMetricsController/getPeakHoursAnalysis'); // 获取高峰期分析
    Route::get('occupancy-history', 'ParkingMetricsController/getOccupancyRateHistory'); // 获取车位占用率历史
    Route::get('device-history', 'ParkingMetricsController/getDeviceStatusHistory'); // 获取设备状态历史

    // 诊断接口
    Route::get('diagnose', 'ParkingMetricsController/diagnoseInfluxDB'); // 诊断InfluxDB连接和配置问题
});
