<?php
namespace app\model;

use think\Model;

class User extends Model
{
    protected $name = 'user';
    protected $autoWriteTimestamp = true;
    
    protected $schema = [
        'id'          => 'int',
        'username'    => 'string',
        'email'       => 'string',
        'password'    => 'string',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];
}