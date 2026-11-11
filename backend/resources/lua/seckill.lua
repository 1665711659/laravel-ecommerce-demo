-- 秒杀预扣库存脚本（Redis 单线程执行，整段原子）
-- KEYS[1] = demo:seckill:stock:{seckillId}
-- KEYS[2] = demo:seckill:users:{seckillId}
-- ARGV[1] = userId
-- ARGV[2] = 活动结束时间（unix 秒），用于给一人一单 Set 设置过期时间，避免 key 永久堆积
--
-- 返回值：1=抢购成功；2=重复购买；3=售罄；-1=库存未初始化（由 PHP 侧初始化后重试）
local stockRaw = redis.call('GET', KEYS[1])
if stockRaw == false then
    return -1
end

local stock = tonumber(stockRaw)

-- 先判重再判库存：重复用户直接返回 2，不消耗库存
if redis.call('SISMEMBER', KEYS[2], ARGV[1]) == 1 then
    return 2
end

if stock <= 0 then
    return 3
end

-- 扣减 + 打一人一单标记，在同一个脚本里完成，避免“扣了库存但没记用户”的中间态
redis.call('DECR', KEYS[1])
redis.call('SADD', KEYS[2], ARGV[1])

local now = tonumber(redis.call('TIME')[1])
local ttl = tonumber(ARGV[2]) - now
if ttl > 0 then
    redis.call('EXPIRE', KEYS[2], ttl)
end

return 1
