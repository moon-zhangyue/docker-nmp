<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateGateDevicesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('gate_device');
        $table->addColumn('name', 'string', ['limit' => 50, 'comment' => '设备名称'])
            ->addColumn('ip_address', 'string', ['limit' => 50, 'comment' => '设备IP地址'])
            ->addColumn('port', 'integer', ['default' => 8080, 'comment' => '设备端口'])
            ->addColumn('location', 'string', ['limit' => 100, 'comment' => '设备位置'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '设备类型：1入口，2出口，3双向'])
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '设备状态：0离线，1在线'])
            ->addColumn('last_heartbeat', 'timestamp', ['null' => true, 'comment' => '最后心跳时间'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['ip_address'], ['unique' => true])
            ->create();
    }
}