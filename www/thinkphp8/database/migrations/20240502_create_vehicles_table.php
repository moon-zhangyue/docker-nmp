<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateVehiclesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('vehicle');
        $table->addColumn('plate_number', 'string', ['limit' => 20, 'comment' => '车牌号码'])
            ->addColumn('owner_name', 'string', ['limit' => 50, 'null' => true, 'comment' => '车主姓名'])
            ->addColumn('owner_phone', 'string', ['limit' => 20, 'null' => true, 'comment' => '车主电话'])
            ->addColumn('vehicle_brand', 'string', ['limit' => 50, 'null' => true, 'comment' => '车辆品牌'])
            ->addColumn('vehicle_color', 'string', ['limit' => 20, 'null' => true, 'comment' => '车辆颜色'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '车辆类型：1普通，2月租，3VIP，4黑名单'])
            ->addColumn('remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '备注'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['plate_number'], ['unique' => true])
            ->create();
    }
}