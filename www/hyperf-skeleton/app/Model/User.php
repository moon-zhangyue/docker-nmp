<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class User extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected ?string $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [
        'name',
        'avatar',
        'balance',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected array $casts = [
        'balance'    => 'decimal:2',
        'status'     => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取用户的红包
     */
    public function redPackets()
    {
        return $this->hasMany(RedPacket::class, 'user_id', 'id');
    }

    /**
     * 获取用户的红包记录
     */
    public function redPacketRecords()
    {
        return $this->hasMany(RedPacketRecord::class, 'user_id', 'id');
    }
}