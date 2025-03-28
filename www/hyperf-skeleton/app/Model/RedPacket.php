<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class RedPacket extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected ?string $table = 'red_packets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [
        'packet_no',
        'user_id',
        'total_amount',
        'total_num',
        'remaining_num',
        'remaining_amount',
        'status',
        'type',
        'blessing',
        'expired_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected array $casts = [
        'total_amount'     => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'status'           => 'integer',
        'type'             => 'integer',
        'expired_at'       => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /**
     * 获取红包发送者
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取红包领取记录
     */
    public function records()
    {
        return $this->hasMany(RedPacketRecord::class, 'packet_id', 'id');
    }
}