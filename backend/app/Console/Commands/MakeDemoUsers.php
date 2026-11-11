<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * 压测辅助：批量创建测试用户，便于验证「一人一单 + 防超卖」。
 * 生成规则：手机号 13900000001 起递增，密码统一 123456。
 * 仅用于本地 Demo，生产环境不要提供类似入口。
 */
class MakeDemoUsers extends Command
{
    protected $signature = 'demo:make-users {count=100 : 创建数量} {--start=1 : 手机号起始序号}';

    protected $description = '批量创建压测用户（手机号 139000xxxxx / 密码 123456）';

    public function handle(): int
    {
        $count = max(1, (int) $this->argument('count'));
        $start = max(1, (int) $this->option('start'));
        $created = 0;

        for ($i = $start; $i < $start + $count; $i++) {
            $phone = '139'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);

            $user = User::firstOrCreate(
                ['phone' => $phone],
                ['nickname' => '压测用户'.$i, 'password' => '123456']
            );

            if ($user->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("完成：新增 {$created} 个用户，手机号区间 139".str_pad((string) $start, 8, '0', STR_PAD_LEFT)
            .' ~ 139'.str_pad((string) ($start + $count - 1), 8, '0', STR_PAD_LEFT).'，密码 123456');

        return self::SUCCESS;
    }
}
