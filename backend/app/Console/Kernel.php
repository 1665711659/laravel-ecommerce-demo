<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 每分钟扫描超时未支付订单（默认 15 分钟），事务内关单 + 回滚库存 + 释放秒杀 Redis 预扣
        // 生产环境建议改用延迟队列（RabbitMQ 死信队列 / Kafka 定时消息），避免扫表
        $schedule->command('orders:close-expired --minutes=15')
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
