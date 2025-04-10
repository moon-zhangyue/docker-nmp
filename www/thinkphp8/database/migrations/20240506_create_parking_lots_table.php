<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateParkingLotsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('parking_lot');
        $table->addColumn('name', 'string', ['limit' => 100, 'comment' => '停车场名称'])
            ->addColumn('address', 'string', ['limit' => 255, 'null' => true, 'comment' => '停车场地址'])
            ->addColumn('total_spaces', 'integer', ['default' => 0, 'comment' => '总车位数'])
            ->addColumn('occupied_spaces', 'integer', ['default' => 0, 'comment' => '已占用车位数'])
            ->addColumn('business_hours_start', 'time', ['null' => true, 'comment' => '营业开始时间'])
            ->addColumn('business_hours_end', 'time', ['null' => true, 'comment' => '营业结束时间'])
            ->addColumn('contact_person', 'string', ['limit' => 50, 'null' => true, 'comment' => '联系人'])
            ->addColumn('contact_phone', 'string', ['limit' => 20, 'null' => true, 'comment' => '联系电话'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：0关闭，1开放'])
            ->addColumn('remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '备注'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }
}