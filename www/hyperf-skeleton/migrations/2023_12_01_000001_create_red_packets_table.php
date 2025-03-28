<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateRedPacketsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('red_packets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('packet_no', 32)->unique()->comment('红包编号');
            $table->unsignedBigInteger('user_id')->comment('发红包用户ID');
            $table->decimal('total_amount', 10, 2)->comment('红包总金额');
            $table->unsignedInteger('total_num')->comment('红包总数量');
            $table->unsignedInteger('remaining_num')->default(0)->comment('剩余红包数量');
            $table->decimal('remaining_amount', 10, 2)->default(0)->comment('剩余红包金额');
            $table->tinyInteger('status')->default(1)->comment('状态：1-有效，0-无效');
            $table->tinyInteger('type')->default(1)->comment('红包类型：1-普通红包，2-拼手气红包');
            $table->string('blessing', 255)->nullable()->comment('祝福语');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
            $table->timestamps();
            $table->index('user_id');
            $table->index('status');
            $table->index('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('red_packets');
    }
}