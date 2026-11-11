<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class CloseExpiredOrders extends Command
{
    protected $signature = 'orders:close-expired {--minutes=15 : 未支付超时时间（分钟）}';

    protected $description = '扫描超时未支付订单，事务内关单并回滚库存（含秒杀 Redis 预扣）';

    public function handle(OrderService $orderService): int
    {
        $minutes = max(0, (int) $this->option('minutes'));

        [$scanned, $closed] = $orderService->closeExpired($minutes);

        $this->info("扫描超时订单 {$scanned} 个，实际关闭 {$closed} 个");

        return self::SUCCESS;
    }
}
