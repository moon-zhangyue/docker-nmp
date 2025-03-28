<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\User;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
#[Command]
class SeedCommand extends HyperfCommand
{
    /**
     * 执行的命令行
     *
     * @var string
     */
    protected ?string $name = 'db:seed';

    /**
     * 命令描述
     *
     * @var string
     */
    protected string $description = '填充测试数据';

    public function configure()
    {
        parent::configure();
        $this->setDescription('填充测试数据到数据库');
    }

    /**
     * 执行数据填充
     */
    public function handle()
    {
        $this->seedUsers();
        $this->info('数据填充完成！');
    }

    /**
     * 填充用户数据
     */
    protected function seedUsers()
    {
        $this->info('开始填充用户数据...');

        // 清空用户表
        Db::table('users')->truncate();

        // 创建测试用户
        $users = [
            [
                'name'       => '张三',
                'avatar'     => 'https://via.placeholder.com/100/FF5733/FFFFFF?text=张三',
                'balance'    => 1000.00,
                'status'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name'       => '李四',
                'avatar'     => 'https://via.placeholder.com/100/33FF57/FFFFFF?text=李四',
                'balance'    => 2000.00,
                'status'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name'       => '王五',
                'avatar'     => 'https://via.placeholder.com/100/5733FF/FFFFFF?text=王五',
                'balance'    => 3000.00,
                'status'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name'       => '赵六',
                'avatar'     => 'https://via.placeholder.com/100/FF33A8/FFFFFF?text=赵六',
                'balance'    => 4000.00,
                'status'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name'       => '钱七',
                'avatar'     => 'https://via.placeholder.com/100/33A8FF/FFFFFF?text=钱七',
                'balance'    => 5000.00,
                'status'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }

        $this->info('用户数据填充完成！');
    }
}