-- 令牌桶限流（Redis 实现，时间取 Redis TIME，避免多台应用服务器时钟不一致）
-- KEYS[1] = demo:seckill:rate:{seckillId}
-- ARGV[1] = capacity 桶容量（瞬时最大并发）
-- ARGV[2] = rate     每秒补充令牌数
-- 返回：1=拿到令牌；0=被限流
local key = KEYS[1]
local capacity = tonumber(ARGV[1])
local rate = tonumber(ARGV[2])

local now = redis.call('TIME')
local nowTs = tonumber(now[1]) + tonumber(now[2]) / 1000000

local bucket = redis.call('HMGET', key, 'tokens', 'ts')
local tokens = tonumber(bucket[1])
local lastTs = tonumber(bucket[2])

if tokens == nil then
    tokens = capacity
    lastTs = nowTs
end

-- 按流逝时间补充令牌，最多补满 capacity
tokens = math.min(capacity, tokens + (nowTs - lastTs) * rate)

if tokens < 1 then
    redis.call('HMSET', key, 'tokens', tokens, 'ts', nowTs)
    redis.call('EXPIRE', key, 2)
    return 0
end

tokens = tokens - 1
redis.call('HMSET', key, 'tokens', tokens, 'ts', nowTs)
redis.call('EXPIRE', key, 2)
return 1
