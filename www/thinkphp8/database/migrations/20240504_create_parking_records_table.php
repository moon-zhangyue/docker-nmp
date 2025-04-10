<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateParkingRecordsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('parking_record');
        $table->addColumn('plate_number', 'string', ['limit' => 20, 'comment' => '车牌号码'])
            ->addColumn('entry_time', 'timestamp', ['null' => true, 'comment' => '进入时间'])
            ->addColumn('exit_time', 'timestamp', ['null' => true, 'comment' => '离开时间'])
            ->addColumn('entry_device_id', 'integer', ['null' => true, 'comment' => '入场设备ID'])
            ->addColumn('exit_device_id', 'integer', ['null' => true, 'comment' => '出场设备ID'])
            ->addColumn('entry_image', 'string', ['limit' => 255, 'null' => true, 'comment' => '入场图片'])
            ->addColumn('exit_image', 'string', ['limit' => 255, 'null' => true, 'comment' => '出场图片'])
            ->addColumn('duration', 'integer', ['default' => 0, 'comment' => '停车时长（分钟）'])
            ->addColumn('fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '停车费用'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：1进场，2出场，3已完成，4异常'])
            ->addColumn('payment_status', 'integer', ['default' => 0, 'comment' => '支付状态：0未支付，1已支付，2免费'])
            ->addColumn('payment_method', 'string', ['limit' => 20, 'null' => true, 'comment' => '支付方式'])
            ->addColumn('payment_time', 'timestamp', ['null' => true, 'comment' => '支付时间'])
            ->addColumn('remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '备注'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['plate_number'])
            ->addIndex(['entry_time'])
            ->addIndex(['exit_time'])
            ->addIndex(['status'])
            ->addIndex(['payment_status'])
            ->create();
    }
}