-- 秒杀本地事务失败后的 Redis 补偿：回滚预扣库存 + 移除一人一单标记
-- KEYS[1] = demo:seckill:stock:{seckillId}
-- KEYS[2] = demo:seckill:users:{seckillId}
-- ARGV[1] = userId
redis.call('INCR', KEYS[1])
redis.call('SREM', KEYS[2], ARGV[1])
return 1
