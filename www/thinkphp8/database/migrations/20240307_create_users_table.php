<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUsersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('user');
        $table->addColumn('username', 'string', ['limit' => 50])
              ->addColumn('email', 'string', ['limit' => 100])
              ->addColumn('password', 'string', ['limit' => 255])
              ->addColumn('create_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('update_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['username'], ['unique' => true])
              ->addIndex(['email'], ['unique' => true])
              ->create();
    }
} 