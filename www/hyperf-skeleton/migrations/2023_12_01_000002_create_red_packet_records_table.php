<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateRedPacketRecordsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('red_packet_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('packet_no', 32)->comment('红包编号');
            $table->unsignedBigInteger('packet_id')->comment('红包ID');
            $table->unsignedBigInteger('user_id')->comment('抢红包用户ID');
            $table->decimal('amount', 10, 2)->comment('抢到的红包金额');
            $table->tinyInteger('status')->default(1)->comment('状态：1-已领取，0-已退回');
            $table->timestamps();
            $table->index('packet_no');
            $table->index('packet_id');
            $table->index('user_id');

            // 设置表属性
            $table->engine    = 'InnoDB';
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('red_packet_records');
    }
}