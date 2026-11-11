<?php

/**
 * 秒杀并发验证脚本（纯 PHP + curl_multi，无需 JMeter 即可跑）
 *
 * 用法：
 *   php tools/seckill_stress.php --users=150 --concurrency=50
 *   php tools/seckill_stress.php --users=200 --concurrency=100 \
 *       --base=http://127.0.0.1:8000,http://127.0.0.1:8001
 *
 * 前置：
 *   1. 后端已启动，且执行过 php artisan demo:make-users 150
 *   2. 秒杀活动 id=1 进行中（Seeder 默认生成）
 *
 * 校验：
 *   - 成功抢购数 == 库存减少量（Redis 预扣 -> DB 扣减一致）
 *   - DB 剩余库存 >= 0（绝不超卖）
 *   - DB 秒杀订单数 == 成功数（一人一单，无重复订单）
 */

$options = getopt('', [
    'base::', 'seckill::', 'users::', 'concurrency::', 'password::',
    'db-host::', 'db-port::', 'db-name::', 'db-user::', 'db-pass::',
]);

$bases = array_values(array_filter(array_map('trim', explode(',', $options['base'] ?? 'http://127.0.0.1:8000'))));
$seckillId = (int) ($options['seckill'] ?? 1);
$userCount = max(1, (int) ($options['users'] ?? 100));
$concurrency = max(1, (int) ($options['concurrency'] ?? 50));
$password = $options['password'] ?? '123456';

$dbConfig = [
    'host' => $options['db-host'] ?? '127.0.0.1',
    'port' => $options['db-port'] ?? '3306',
    'name' => $options['db-name'] ?? 'laravel_ecommerce',
    'user' => $options['db-user'] ?? 'root',
    'pass' => $options['db-pass'] ?? '',
];

function multiRequest(array $requests, int $concurrency): array
{
    $results = [];
    $batches = array_chunk($requests, $concurrency, true);

    foreach ($batches as $batch) {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($batch as $key => $request) {
            $ch = curl_init($request['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $request['method'] ?? 'GET',
                CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $request['headers'] ?? []),
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            if (isset($request['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request['body'], JSON_UNESCAPED_UNICODE));
            }
            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $results[$key] = [
                'http' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'json' => json_decode((string) $body, true),
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);
    }

    return $results;
}

function pdo(array $config): ?PDO
{
    try {
        return new PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
            $config['user'],
            $config['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable $e) {
        echo "⚠️  MySQL 连接失败，跳过数据库一致性校验：{$e->getMessage()}\n";
        return null;
    }
}

function baseUrl(array $bases, int $index): string
{
    return $bases[$index % count($bases)];
}

$pdo = pdo($dbConfig);
$initialStock = null;
$initialOrders = 0;

if ($pdo) {
    $stmt = $pdo->prepare('SELECT stock FROM seckill WHERE id = ?');
    $stmt->execute([$seckillId]);
    $initialStock = $stmt->fetchColumn();
    if ($initialStock === false) {
        fwrite(STDERR, "秒杀活动 {$seckillId} 不存在，请先执行 migrate --seed\n");
        exit(1);
    }
    $initialStock = (int) $initialStock;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE seckill_id = ?');
    $stmt->execute([$seckillId]);
    $initialOrders = (int) $stmt->fetchColumn();
}

echo "== 秒杀压测开始 ==\n";
echo '服务地址: '.implode(', ', $bases)."\n";
echo "活动 ID: {$seckillId} | 用户数: {$userCount} | 单批并发: {$concurrency} | 初始库存: "
    .($initialStock ?? '未知')."\n\n";

// 1) 批量登录拿 token（手机号规则与 demo:make-users 一致：139 + 8 位序号）
$loginRequests = [];
for ($i = 1; $i <= $userCount; $i++) {
    $phone = '139'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);
    $loginRequests[$phone] = [
        'url' => baseUrl($bases, $i).'/api/auth/login',
        'method' => 'POST',
        'body' => ['phone' => $phone, 'password' => $password],
    ];
}

$loginResults = multiRequest($loginRequests, $concurrency);
$tokens = [];
foreach ($loginResults as $phone => $result) {
    if (($result['json']['code'] ?? -1) === 0 && ! empty($result['json']['data']['token'])) {
        $tokens[$phone] = $result['json']['data']['token'];
    }
}
echo '登录成功: '.count($tokens).'/'.$userCount."\n";

if (! $tokens) {
    fwrite(STDERR, "没有可用 token，请先执行：php artisan demo:make-users {$userCount}\n");
    exit(1);
}

// 2) 并发抢购
$buyRequests = [];
$index = 0;
foreach ($tokens as $phone => $token) {
    $buyRequests[$phone] = [
        'url' => baseUrl($bases, $index++)."/api/seckill/{$seckillId}/buy",
        'method' => 'POST',
        'headers' => ["Authorization: Bearer {$token}"],
        'body' => [],
    ];
}

$start = microtime(true);
$buyResults = multiRequest($buyRequests, $concurrency);
$elapsed = microtime(true) - $start;

$stats = ['success' => 0, 'sold_out' => 0, 'duplicate' => 0, 'limited' => 0, 'other' => 0];
$orderNos = [];

foreach ($buyResults as $phone => $result) {
    $code = $result['json']['code'] ?? null;

    if ($code === 0) {
        $stats['success']++;
        $orderNos[$result['json']['data']['order_no'] ?? uniqid()] = true;
    } elseif ($code === 3003) {
        $stats['sold_out']++;
    } elseif ($code === 3004) {
        $stats['duplicate']++;
    } elseif ($code === 3005 || $result['http'] === 429) {
        $stats['limited']++;
    } else {
        $stats['other']++;
    }
}

printf(
    "结果: 成功 %d | 售罄 %d | 重复 %d | 限流 %d | 其他 %d\n",
    $stats['success'],
    $stats['sold_out'],
    $stats['duplicate'],
    $stats['limited'],
    $stats['other']
);
printf("唯一订单号: %d | 耗时: %.2fs | 吞吐: %.1f req/s\n\n", count($orderNos), $elapsed, count($buyResults) / max($elapsed, 0.001));

if (! $pdo) {
    echo "未连接数据库，无法校验库存一致性。\n";
    exit(0);
}

$stmt = $pdo->prepare('SELECT stock FROM seckill WHERE id = ?');
$stmt->execute([$seckillId]);
$remainingStock = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE seckill_id = ?');
$stmt->execute([$seckillId]);
$totalOrders = (int) $stmt->fetchColumn();
$newOrders = $totalOrders - $initialOrders;

$sold = $initialStock - $remainingStock;
$ok = $remainingStock >= 0
    && $sold === $stats['success']
    && $newOrders === $stats['success']
    && count($orderNos) === $stats['success'];

echo "DB 剩余库存: {$remainingStock} | 库存减少: {$sold} | 本轮新增订单: {$newOrders}\n";
echo "一致性校验: ".($ok ? '✅ 通过（无超卖、无重复订单）' : '❌ 失败，请检查 Redis/MySQL 与并发逻辑')."\n";

exit($ok ? 0 : 1);
