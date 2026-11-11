# 压测与验证工具

## 1. PHP 并发脚本（推荐，开箱即用）

`seckill_stress.php` 用 `curl_multi` 批量登录 + 并发抢购，并自动校验库存一致性。

```bash
# 1) 准备活动与测试用户（在后端目录执行）
php artisan migrate:fresh --seed
php artisan demo:make-users 150

# 2) 并发压测（单机单实例）
php tools/seckill_stress.php --users=150 --concurrency=50

# 3) Windows 下 artisan serve 是单进程，建议多开几个实例再轮询压测
#    php artisan serve --port=8001
#    php artisan serve --port=8002
php tools/seckill_stress.php --users=150 --concurrency=50 \
  --base=http://127.0.0.1:8000,http://127.0.0.1:8001,http://127.0.0.1:8002 \
  --db-port=3306
```

参数说明：

| 参数 | 默认值 | 说明 |
| --- | --- | --- |
| `--base` | `http://127.0.0.1:8000` | 支持逗号分隔多个实例，按用户轮询 |
| `--seckill` | `1` | 秒杀活动 ID |
| `--users` | `100` | 登录并参与抢购的用户数（手机号 13900000001 起） |
| `--concurrency` | `50` | 单批并发数 |
| `--password` | `123456` | 测试用户统一密码 |
| `--db-host/--db-port/--db-name/--db-user/--db-pass` | 127.0.0.1/3306/laravel_ecommerce/root/空 | 用于结果校验 |

脚本退出码：`0` 一致；`1` 检测到超卖或订单数不一致。

已实测结果（150 用户抢 100 件库存）：

```
登录成功: 150/150
结果: 成功 100 | 售罄 50 | 重复 0 | 限流 0 | 其他 0
唯一订单号: 100
DB 剩余库存: 0 | 库存减少: 100 | 本轮新增订单: 100
一致性校验: ✅ 通过（无超卖、无重复订单）
```

> Windows 下 Laravel `artisan serve` 单进程处理请求，吞吐数字不代表生产性能；
> 该脚本验证的是**并发下的业务正确性**（不超卖、不重复下单）。

## 2. ab（ApacheBench）

准备一个已登录用户的 token 与请求体 `buy.json`（内容 `{}`）：

```bash
ab -n 1000 -c 100 \
  -p buy.json -T application/json \
  -H "Authorization: Bearer <token>" \
  http://127.0.0.1:8000/api/seckill/1/buy
```

注意：

- 同一个 token 对应同一个用户，受“一人一单”限制，只有第一次返回成功，其余返回 3004；
  验证超卖需要用 `demo:make-users` 生成多用户，并给每个用户准备 token（推荐用 PHP 脚本）。
- ab 不会解析业务 code，需要结合响应内容/数据库统计成功数。

## 3. JMeter

1. 新建 Thread Group：Threads = 150，Ramp-up = 1，Loop = 1。
2. 添加 HTTP Request：
   - Protocol: http，Host: 127.0.0.1，Port: 8000
   - Method: POST，Path: `/api/seckill/1/buy`
   - HTTP Header Manager：`Authorization: Bearer ${token}`、`Content-Type: application/json`
3. 用 CSV Data Set Config 读取 150 个 token（可由登录接口批量获取）。
4. 添加 View Results Tree / Summary Report 观察响应码。
5. 结束后执行校验 SQL：

```sql
SELECT stock FROM seckill WHERE id = 1;
SELECT COUNT(*) FROM orders WHERE seckill_id = 1;
```

库存必须 `>= 0`，订单数必须等于 100 - 剩余库存。

## 4. MeterSphere

- 接口自动化：导入 OpenAPI（`http://127.0.0.1:8000/docs`），生成秒杀接口用例。
- 性能测试：场景中添加 150 个并发线程，CSV 参数化 token，断言 HTTP 200 且响应 JSON `code` ∈ {0, 3003, 3004, 3005}。
- 压测后运行上面的 SQL 做结果校验。

## 5. 手工验证清单

```bash
# 超时关单：把 0 分钟作为超时阈值，立即关闭所有待支付订单并回滚库存
php artisan orders:close-expired --minutes=0

# 秒杀库存（Redis 与 DB 应一致）
redis-cli GET demo:seckill:stock:1
mysql -uroot -e "SELECT stock FROM laravel_ecommerce.seckill WHERE id=1;"

# 一人一单标记
redis-cli SISMEMBER demo:seckill:users:1 <userId>

# 下单幂等：同一个 X-Request-Id 连发两次，订单号应相同
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Authorization: Bearer <token>" \
  -H "X-Request-Id: fixed-demo-token" \
  -H "Content-Type: application/json" \
  -d '{"cart_ids":[1]}'
```
