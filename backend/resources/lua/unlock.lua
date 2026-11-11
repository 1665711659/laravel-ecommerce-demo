-- 缓存重建锁释放：必须“校验持有者再删除”
-- 场景：线程 A 建缓存耗时超过锁 TTL，锁自动过期后被 B 拿到；
-- 此时 A 直接 DEL 会把 B 的锁删掉。用 token 比对可避免误删。
-- KEYS[1] = 锁 key
-- ARGV[1] = 加锁时写入的 token
if redis.call('GET', KEYS[1]) == ARGV[1] then
    return redis.call('DEL', KEYS[1])
end
return 0
