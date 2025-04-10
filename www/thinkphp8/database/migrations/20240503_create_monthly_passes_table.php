<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateMonthlyPassesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('monthly_pass');
        $table->addColumn('plate_number', 'string', ['limit' => 20, 'comment' => '车牌号码'])
            ->addColumn('start_date', 'date', ['comment' => '开始日期'])
            ->addColumn('end_date', 'date', ['comment' => '结束日期'])
            ->addColumn('fee', 'decimal', ['precision' => 10, 'scale' => 2, 'comment' => '月租费用'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：0无效，1有效'])
            ->addColumn('payment_method', 'string', ['limit' => 20, 'null' => true, 'comment' => '支付方式'])
            ->addColumn('payment_time', 'timestamp', ['null' => true, 'comment' => '支付时间'])
            ->addColumn('remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '备注'])
            ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addIndex(['plate_number'], ['unique' => true])
            ->create();
    }
}