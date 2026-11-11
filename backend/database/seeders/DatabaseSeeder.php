<?php

namespace Database\Seeders;

use App\Models\Goods;
use App\Models\Seckill;
use App\Models\User;
use App\Support\RedisKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedGoods();
        $this->seedSeckill();
        $this->resetRedisState();
    }

    private function seedUsers(): void
    {
        // 测试账号：13800138000 / 123456
        User::updateOrCreate(
            ['phone' => '13800138000'],
            ['nickname' => '测试用户', 'password' => Hash::make('123456')]
        );

        User::updateOrCreate(
            ['phone' => '13800138001'],
            ['nickname' => '测试用户2', 'password' => Hash::make('123456')]
        );
    }

    private function seedGoods(): void
    {
        $goods = [
            [1, '无线蓝牙耳机', 'minimalist product photo of white wireless earbuds on light gray background', 299.00, 100, '主动降噪，30 小时续航，面试 Demo 用商品 1'],
            [2, '客制化机械键盘', 'minimalist product photo of mechanical keyboard on white desk', 499.00, 80, '热插拔轴体，RGB 背光'],
            [3, '智能运动手表', 'minimalist product photo of smart watch on light background', 899.00, 50, '血氧/心率监测，5ATM 防水'],
            [4, '便携胶囊咖啡机', 'minimalist product photo of portable coffee maker on white background', 359.00, 60, '一键萃取，办公室神器'],
            [5, '人体工学办公椅', 'product photo of ergonomic office chair in bright studio', 1299.00, 30, '腰托可调，久坐不累'],
            [6, '极简通勤双肩包', 'minimalist product photo of gray backpack on white background', 219.00, 120, '15.6 英寸电脑仓'],
            [7, '头戴式降噪耳机', 'product photo of over-ear headphones on neutral background', 799.00, 40, '混合主动降噪'],
            [8, '桌面显示器支架', 'minimalist product photo of monitor arm on white desk', 199.00, 90, '气压升降，承重 9kg'],
            [9, '桌面氛围灯', 'minimalist product photo of rgb desk lamp on dark desk', 129.00, 200, '支持 App 调色'],
            [10, '不锈钢保温杯', 'minimalist product photo of stainless steel thermos on white background', 89.00, 150, '316 不锈钢，12 小时保温'],
        ];

        foreach ($goods as [$id, $title, $prompt, $price, $stock, $desc]) {
            Goods::updateOrCreate(['id' => $id], [
                'title' => $title,
                'cover' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt='
                    .rawurlencode($prompt).'&image_size=square',
                'price' => $price,
                'stock' => $stock,
                'status' => Goods::STATUS_ON,
                'desc' => $desc,
            ]);
        }
    }

    private function seedSeckill(): void
    {
        // 商品 1 的秒杀活动：正在进行中，方便前端直接演示
        Seckill::updateOrCreate(['id' => 1], [
            'goods_id' => 1,
            'seckill_price' => 9.90,
            'stock' => 100,
            'start_time' => now()->subMinutes(30),
            'end_time' => now()->addHours(2),
            'status' => Seckill::STATUS_ON,
        ]);
    }

    /**
     * 重新 seed 时清理 Redis 中的秒杀库存/一人一单标记与商品缓存，
     * 否则 DB 重置了但 Redis 还是旧库存，演示会出现“DB 有货 Redis 售罄”。
     */
    private function resetRedisState(): void
    {
        Redis::del(
            RedisKey::seckillStock(1),
            RedisKey::seckillUsers(1),
            RedisKey::seckillRate(1),
            RedisKey::goodsList()
        );

        foreach (Redis::keys(RedisKey::PREFIX.'goods:detail:*') as $key) {
            Redis::del($key);
        }
    }
}
