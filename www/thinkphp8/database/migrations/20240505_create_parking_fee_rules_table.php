<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateParkingFeeRulesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('parking_fee_rule');
        $table->addColumn('name', 'string', ['limit' => 50, 'comment' => '规则名称'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '规则类型：1按小时收费，2按次收费，3阶梯收费'])
            ->addColumn('fee_per_hour', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '每小时费用'])
            ->addColumn('fixed_fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '固定费用'])
            ->addColumn('tiered_rules', 'text', ['null' => true, 'comment' => '阶梯收费规则（JSON格式）'])
            ->addColumn('free_minutes', 'integer', ['default' => 0, 'comment' => '免费时长（分钟）'])
            ->addColumn('max_fee_per_day', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '每日最高收费'])
            ->addColumn('start_time', 'time', ['null' => true, 'comment' => '规则生效开始时间'])
            ->addColumn('end_time', 'time', ['null' => true, 'comment' => '规则生效结束时间'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：0禁用，1启用'])
            ->addColumn('remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '备注'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['status'])
            ->create();
    }
}