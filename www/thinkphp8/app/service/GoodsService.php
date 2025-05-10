<?php
declare(strict_types=1);

namespace app\service;

use app\model\GoodsSpu;
use app\model\GoodsSku;
use app\model\GoodsAttribute;
use think\facade\Cache;
use think\facade\Queue;
use think\facade\Db;
use think\facade\Log;
use think\Exception;
use think\facade\Redis;

class GoodsService
{
    /**
     * 缓存前缀
     */
    private const CACHE_PREFIX = 'goods:';
    
    /**
     * 缓存时间 (秒)
     */
    private const CACHE_TIME = 3600; // 1小时
    
    /**
     * 锁超时时间 (秒)
     */
    private const LOCK_TIMEOUT = 10;
    
    /**
     * 锁等待重试次数
     */
    private const LOCK_RETRY = 3;
    
    /**
     * 锁等待重试间隔 (毫秒)
     */
    private const LOCK_RETRY_DELAY = 100;
    
    /**
     * 库存不足时的错误码
     */
    private const ERR_STOCK_INSUFFICIENT = 1001;
    
    /**
     * 获取商品列表
     * 
     * @param array $params 查询参数
     * @param bool $useCache 是否使用缓存
     * @return array
     */
    public function getList(array $params = [], bool $useCache = true): array
    {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $categoryId = $params['category_id'] ?? null;
        $keyword = $params['keyword'] ?? '';
        
        // 生成缓存键
        $cacheKey = self::CACHE_PREFIX . "list:{$page}:{$limit}:" . md5(json_encode($params));
        
        // 从Redis尝试获取缓存数据
        if ($useCache) {
            $cacheData = Cache::store('redis')->get($cacheKey);
            if ($cacheData !== null) {
                // 如果存在有效缓存，直接返回
                return $cacheData;
            }
        }
        
        // 分布式锁，防止缓存击穿
        $lockKey = "lock:" . $cacheKey;
        $lockValue = uniqid();
        $gotLock = false;
        
        try {
            // 尝试获取锁，避免缓存击穿引起的并发DB查询
            $gotLock = $this->acquireLock($lockKey, $lockValue, 5);
            
            // 获取锁后二次检查缓存
            if ($gotLock && $useCache) {
                $cacheData = Cache::store('redis')->get($cacheKey);
                if ($cacheData !== null) {
                    return $cacheData;
                }
            }
            
            // 构建查询条件
            $query = GoodsSpu::with(['skus', 'attributes']);
            
            if (!empty($categoryId)) {
                $query = $query->where('category_id', $categoryId);
            }
            
            if (!empty($keyword)) {
                $query = $query->where('name', 'like', "%{$keyword}%");
            }
            
            // 执行分页查询
            $goods = $query->order('id', 'desc')
                ->page($page, $limit)
                ->select()
                ->toArray();
                
            $total = $query->count();
            
            $result = [
                'list' => $goods,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'timestamp' => time() // 添加时间戳帮助判断缓存新鲜度
            ];
            
            // 设置缓存，有效期1小时
            if ($useCache) {
                $this->setCache($cacheKey, $result);
                
                // 设置一个额外的映射索引帮助批量更新
                $this->addListToIndex($cacheKey, $goods);
            }
            
            return $result;
        } finally {
            // 释放分布式锁
            if ($gotLock) {
                $this->releaseLock($lockKey, $lockValue);
            }
        }
    }
    
    /**
     * 获取商品详情
     * 
     * @param int $id 商品ID
     * @param bool $useCache 是否使用缓存
     * @return array|null
     */
    public function getDetail(int $id, bool $useCache = true): ?array
    {
        // 生成缓存键
        $cacheKey = self::CACHE_PREFIX . "detail:{$id}";
        
        // 从Redis尝试获取缓存数据
        if ($useCache) {
            $cacheData = Cache::store('redis')->get($cacheKey);
            if ($cacheData !== null) {
                // 如果存在有效缓存，直接返回
                return $cacheData;
            }
        }
        
        // 分布式锁，防止缓存击穿
        $lockKey = "lock:" . $cacheKey;
        $lockValue = uniqid();
        $gotLock = false;
        
        try {
            // 尝试获取锁，避免缓存击穿引起的并发DB查询
            $gotLock = $this->acquireLock($lockKey, $lockValue, 5);
            
            // 获取锁后二次检查缓存
            if ($gotLock && $useCache) {
                $cacheData = Cache::store('redis')->get($cacheKey);
                if ($cacheData !== null) {
                    return $cacheData;
                }
            }
            
            // 查询商品详情，包括SKU和属性
            $goods = GoodsSpu::with(['skus', 'attributes'])
                ->find($id);
                
            if (!$goods) {
                return null;
            }
            
            $result = $goods->toArray();
            $result['timestamp'] = time(); // 添加时间戳帮助判断缓存新鲜度
            
            // 设置缓存，有效期1小时
            if ($useCache) {
                $this->setCache($cacheKey, $result);
                
                // 设置sku缓存，便于精确清理和单独获取
                if (!empty($result['skus'])) {
                    foreach ($result['skus'] as $sku) {
                        $skuCacheKey = self::CACHE_PREFIX . "sku:{$sku['id']}";
                        $this->setCache($skuCacheKey, $sku);
                    }
                }
            }
            
            return $result;
        } finally {
            // 释放分布式锁
            if ($gotLock) {
                $this->releaseLock($lockKey, $lockValue);
            }
        }
    }
    
    /**
     * 批量预加载商品详情到缓存
     * 
     * @param array $ids 商品ID列表
     * @return bool
     */
    public function preloadGoodsCache(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }
        
        $redis = Cache::store('redis')->handler();
        $pipeline = $redis->pipeline();
        
        // 批量查询商品详情
        $goodsList = GoodsSpu::with(['skus', 'attributes'])
            ->whereIn('id', $ids)
            ->select()
            ->toArray();
            
        if (empty($goodsList)) {
            return false;
        }
        
        foreach ($goodsList as $goods) {
            $cacheKey = self::CACHE_PREFIX . "detail:{$goods['id']}";
            $goods['timestamp'] = time();
            
            // 设置商品详情缓存
            $pipeline->set($cacheKey, serialize($goods), ['EX' => self::CACHE_TIME]);
            
            // 为每个sku设置缓存
            if (!empty($goods['skus'])) {
                foreach ($goods['skus'] as $sku) {
                    // SKU详情缓存
                    $skuCacheKey = self::CACHE_PREFIX . "sku:{$sku['id']}";
                    $pipeline->set($skuCacheKey, serialize($sku), ['EX' => self::CACHE_TIME]);
                    
                    // 库存缓存
                    $stockCacheKey = self::CACHE_PREFIX . "stock:{$sku['id']}";
                    $pipeline->set($stockCacheKey, $sku['stock'], ['EX' => self::CACHE_TIME]);
                }
            }
        }
        
        $pipeline->execute();
        return true;
    }
    
    /**
     * 创建商品
     * 
     * @param array $data 商品数据
     * @return int 新商品ID
     * @throws \Exception
     */
    public function create(array $data): int
    {
        // 开启事务
        Db::startTrans();
        try {
            // 创建SPU
            $spu = new GoodsSpu;
            $spu->name = $data['name'];
            $spu->description = $data['description'] ?? '';
            $spu->category_id = $data['category_id'] ?? 0;
            $spu->brand_id = $data['brand_id'] ?? 0;
            $spu->status = $data['status'] ?? 1;
            $spu->save();
            
            // 创建SKU
            $skuIds = [];
            if (!empty($data['skus'])) {
                foreach ($data['skus'] as $skuData) {
                    $sku = new GoodsSku;
                    $sku->spu_id = $spu->id;
                    $sku->price = $skuData['price'];
                    $sku->stock = $skuData['stock'];
                    $sku->attributes = $skuData['attributes'] ?? [];
                    $sku->status = $skuData['status'] ?? 1;
                    $sku->save();
                    
                    $skuIds[] = $sku->id;
                    
                    // 设置库存缓存
                    $stockCacheKey = self::CACHE_PREFIX . "stock:{$sku->id}";
                    $this->setCache($stockCacheKey, $sku->stock, self::CACHE_TIME);
                }
            }
            
            // 创建属性
            if (!empty($data['attributes'])) {
                foreach ($data['attributes'] as $attrData) {
                    $attr = new GoodsAttribute;
                    $attr->spu_id = $spu->id;
                    $attr->name = $attrData['name'];
                    $attr->value = $attrData['value'];
                    $attr->save();
                }
            }
            
            Db::commit();
            
            // 异步清理相关缓存，刷新列表
            $this->asyncCleanListCache();
            
            // 预热新商品缓存
            $this->asyncPreloadGoodsCache($spu->id);
            
            return $spu->id;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("创建商品失败: " . $e->getMessage());
            throw new Exception("创建商品失败: " . $e->getMessage());
        }
    }
    
    /**
     * 更新商品
     * 
     * @param int $id 商品ID
     * @param array $data 商品数据
     * @return bool
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 更新SPU
            $spu = GoodsSpu::find($id);
            if (!$spu) {
                throw new Exception("商品不存在");
            }
            
            // 记录更新前的SKU IDs 用于后续缓存清理
            $oldSkuIds = $spu->skus()->column('id');
            $newSkuIds = [];
            
            // 更新SPU基本信息
            if (isset($data['name'])) {
                $spu->name = $data['name'];
            }
            
            if (isset($data['description'])) {
                $spu->description = $data['description'];
            }
            
            if (isset($data['category_id'])) {
                $spu->category_id = $data['category_id'];
            }
            
            if (isset($data['brand_id'])) {
                $spu->brand_id = $data['brand_id'];
            }
            
            if (isset($data['status'])) {
                $spu->status = $data['status'];
            }
            
            $spu->save();
            
            // 更新SKU
            if (!empty($data['skus'])) {
                // 获取当前SKU ID列表
                $currentSkuIds = $spu->skus()->column('id');
                
                foreach ($data['skus'] as $skuData) {
                    if (isset($skuData['id']) && in_array($skuData['id'], $currentSkuIds)) {
                        // 更新现有SKU
                        $sku = GoodsSku::find($skuData['id']);
                        
                        if (isset($skuData['price'])) {
                            $sku->price = $skuData['price'];
                        }
                        
                        if (isset($skuData['attributes'])) {
                            $sku->attributes = $skuData['attributes'];
                        }
                        
                        if (isset($skuData['status'])) {
                            $sku->status = $skuData['status'];
                        }
                        
                        // 库存特殊处理，不通过这里更新
                        if (isset($skuData['stock']) && $skuData['stock'] != $sku->stock) {
                            $stockDiff = $skuData['stock'] - $sku->stock;
                            $this->updateStock($sku->id, $stockDiff);
                        }
                        
                        $sku->save();
                        $newSkuIds[] = $sku->id;
                    } else {
                        // 创建新SKU
                        $sku = new GoodsSku;
                        $sku->spu_id = $spu->id;
                        $sku->price = $skuData['price'];
                        $sku->stock = $skuData['stock'] ?? 0;
                        $sku->attributes = $skuData['attributes'] ?? [];
                        $sku->status = $skuData['status'] ?? 1;
                        $sku->save();
                        
                        $newSkuIds[] = $sku->id;
                        
                        // 设置库存缓存
                        $stockCacheKey = self::CACHE_PREFIX . "stock:{$sku->id}";
                        $this->setCache($stockCacheKey, $sku->stock, self::CACHE_TIME);
                    }
                }
                
                // 删除不在新数据中的SKU
                foreach ($currentSkuIds as $skuId) {
                    if (!in_array($skuId, $newSkuIds)) {
                        // 清理相关缓存
                        $this->deleteSkuCache($skuId);
                        
                        // 删除SKU
                        GoodsSku::destroy($skuId);
                    }
                }
            }
            
            // 更新属性
            if (!empty($data['attributes'])) {
                // 获取当前属性ID列表
                $currentAttrIds = $spu->attributes()->column('id');
                $newAttrIds = [];
                
                foreach ($data['attributes'] as $attrData) {
                    if (isset($attrData['id']) && in_array($attrData['id'], $currentAttrIds)) {
                        // 更新现有属性
                        $attr = GoodsAttribute::find($attrData['id']);
                        if (isset($attrData['name'])) {
                            $attr->name = $attrData['name'];
                        }
                        if (isset($attrData['value'])) {
                            $attr->value = $attrData['value'];
                        }
                        $attr->save();
                        $newAttrIds[] = $attr->id;
                    } else {
                        // 创建新属性
                        $attr = new GoodsAttribute;
                        $attr->spu_id = $spu->id;
                        $attr->name = $attrData['name'];
                        $attr->value = $attrData['value'];
                        $attr->save();
                        $newAttrIds[] = $attr->id;
                    }
                }
                
                // 删除不在新数据中的属性
                foreach ($currentAttrIds as $attrId) {
                    if (!in_array($attrId, $newAttrIds)) {
                        GoodsAttribute::destroy($attrId);
                    }
                }
            }
            
            Db::commit();
            
            // 合并新旧SKU IDs，确保所有相关缓存都被清理
            $allSkuIds = array_unique(array_merge($oldSkuIds, $newSkuIds));
            
            // 异步清理相关缓存
            // 1. 清理商品详情缓存
            $this->asyncClearCache($id, $allSkuIds);
            
            // 2. 清理列表缓存
            $this->asyncCleanListCache();
            
            // 3. 预热更新后的商品缓存
            $this->asyncPreloadGoodsCache($id);
            
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("更新商品失败: " . $e->getMessage());
            throw new Exception("更新商品失败: " . $e->getMessage());
        }
    }
    
    /**
     * 删除商品
     * 
     * @param int $id 商品ID
     * @return bool
     * @throws \Exception
     */
    public function delete(int $id): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            $spu = GoodsSpu::find($id);
            if (!$spu) {
                throw new Exception("商品不存在");
            }
            
            // 获取关联的SKU IDs 用于后续缓存清理
            $skuIds = $spu->skus()->column('id');
            
            // 删除相关的SKU和属性会通过模型关联自动处理
            $spu->delete();
            
            Db::commit();
            
            // 异步清理相关缓存
            $this->asyncClearCache($id, $skuIds);
            
            // 清理列表缓存
            $this->asyncCleanListCache();
            
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("删除商品失败: " . $e->getMessage());
            throw new Exception("删除商品失败: " . $e->getMessage());
        }
    }
    
    /**
     * 删除SKU缓存
     * 
     * @param int $skuId SKU ID
     * @return void
     */
    private function deleteSkuCache(int $skuId): void
    {
        $redis = Cache::store('redis')->handler();
        $pipeline = $redis->pipeline();
        
        // 删除SKU详情缓存
        $skuCacheKey = self::CACHE_PREFIX . "sku:{$skuId}";
        $pipeline->del($skuCacheKey);
        
        // 删除库存缓存
        $stockCacheKey = self::CACHE_PREFIX . "stock:{$skuId}";
        $pipeline->del($stockCacheKey);
        
        // 删除锁定库存缓存
        $lockedStockCacheKey = self::CACHE_PREFIX . "locked_stock:{$skuId}";
        $pipeline->del($lockedStockCacheKey);
        
        $pipeline->execute();
    }
    
    /**
     * 更新商品库存
     * 
     * @param int $skuId SKU ID
     * @param int $quantity 数量（正数增加，负数减少）
     * @param bool $lockStock 是否锁定库存而非实际减少
     * @return bool
     * @throws Exception
     */
    public function updateStock(int $skuId, int $quantity, bool $lockStock = false): bool
    {
        // 库存不变直接返回
        if ($quantity === 0) {
            return true;
        }
        
        // 分布式锁键
        $lockKey = self::CACHE_PREFIX . "stock_lock:{$skuId}";
        $lockValue = uniqid(mt_rand(10000, 99999) . '_', true);
        
        // 获取Redis锁，防止并发修改库存
        if (!$this->acquireLock($lockKey, $lockValue, self::LOCK_TIMEOUT, 5, 200)) {
            Log::warning("GoodsService.updateStock: 获取库存锁失败，SKU ID: {$skuId}");
            throw new Exception("商品库存操作频繁，请稍后再试", 10002);
        }
        
        try {
            $redis = Cache::store('redis')->handler();
            $stockCacheKey = self::CACHE_PREFIX . "stock:{$skuId}";
            
            // 先查询Redis缓存
            $redisStock = $redis->get($stockCacheKey);
            $redisStockExists = ($redisStock !== false);
            
            // 获取SKU
            $sku = GoodsSku::find($skuId);
            if (!$sku) {
                throw new Exception("SKU不存在", 10003);
            }
            
            // 如果Redis没有库存缓存，使用数据库库存
            if (!$redisStockExists) {
                $redisStock = $sku->stock;
                $redis->set($stockCacheKey, $redisStock, ['EX' => self::CACHE_TIME]);
            }
            
            // 检查库存是否足够（减库存时）
            if ($quantity < 0 && $redisStock < abs($quantity)) {
                throw new Exception("商品库存不足", self::ERR_STOCK_INSUFFICIENT);
            }
            
            // 开启事务
            Db::startTrans();
            
            try {
                // 更新Redis库存（先更新Redis，再更新DB）
                $newStock = $redisStock + $quantity;
                $redis->set($stockCacheKey, $newStock, ['EX' => self::CACHE_TIME]);
                
                if ($lockStock) {
                    // 锁定库存（为订单预留）
                    if ($quantity < 0) {
                        // 减少实际库存，同时增加锁定库存
                        $sku->stock = $sku->stock + $quantity;
                        $sku->locked_stock = ($sku->locked_stock ?? 0) + abs($quantity);
                        
                        // 更新锁定库存缓存
                        $lockedStockCacheKey = self::CACHE_PREFIX . "locked_stock:{$skuId}";
                        $lockedStock = $redis->get($lockedStockCacheKey) ?: 0;
                        $redis->set($lockedStockCacheKey, $lockedStock + abs($quantity), ['EX' => self::CACHE_TIME]);
                    } else {
                        // 解锁库存（取消订单时）
                        // 增加实际库存，减少锁定库存
                        $sku->stock = $sku->stock + $quantity;
                        $lockedStock = $sku->locked_stock ?? 0;
                        $sku->locked_stock = max(0, $lockedStock - $quantity);
                        
                        // 更新锁定库存缓存
                        $lockedStockCacheKey = self::CACHE_PREFIX . "locked_stock:{$skuId}";
                        $currentLockedStock = (int)$redis->get($lockedStockCacheKey) ?: 0;
                        $newLockedStock = max(0, $currentLockedStock - $quantity);
                        $redis->set($lockedStockCacheKey, $newLockedStock, ['EX' => self::CACHE_TIME]);
                    }
                } else {
                    // 直接增减库存
                    $sku->stock = $sku->stock + $quantity;
                }
                
                // 更新数据库
                $sku->save();
                
                Db::commit();
                
                // 异步清理详情缓存
                $this->asyncClearSkuCache($skuId, $sku->spu_id);
                
                return true;
            } catch (\Exception $e) {
                Db::rollback();
                
                // 回滚Redis缓存
                if ($redisStockExists) {
                    $redis->set($stockCacheKey, $redisStock, ['EX' => self::CACHE_TIME]);
                } else {
                    $redis->del($stockCacheKey);
                }
                
                throw $e;
            }
        } finally {
            // 释放Redis锁
            $this->releaseLock($lockKey, $lockValue);
        }
    }
    
    /**
     * 批量更新库存
     * 
     * @param array $items 库存更新项 [['sku_id' => 1, 'quantity' => -2], ...]
     * @param bool $lockStock 是否锁定库存
     * @return array 更新结果 ['success' => [], 'failed' => []]
     */
    public function batchUpdateStock(array $items, bool $lockStock = false): array
    {
        if (empty($items)) {
            return ['success' => [], 'failed' => []];
        }
        
        $result = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($items as $item) {
            try {
                $skuId = $item['sku_id'] ?? 0;
                $quantity = $item['quantity'] ?? 0;
                
                if ($skuId <= 0 || $quantity === 0) {
                    continue;
                }
                
                $success = $this->updateStock($skuId, $quantity, $lockStock);
                
                if ($success) {
                    $result['success'][] = [
                        'sku_id' => $skuId,
                        'quantity' => $quantity
                    ];
                }
            } catch (\Exception $e) {
                $result['failed'][] = [
                    'sku_id' => $skuId ?? 0,
                    'quantity' => $quantity ?? 0,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ];
                
                Log::error("GoodsService.batchUpdateStock 异常: SKU ID: {$skuId}, 数量: {$quantity}, 错误: " . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * 批量获取商品库存
     * 
     * @param array $skuIds SKU ID数组
     * @return array
     */
    public function getStockBatch(array $skuIds): array
    {
        if (empty($skuIds)) {
            return [];
        }
        
        $result = [];
        $missingSkuIds = [];
        $redis = Cache::store('redis')->handler();
        $pipeline = $redis->pipeline();
        
        // 首先尝试从Redis批量获取
        foreach ($skuIds as $skuId) {
            $stockCacheKey = self::CACHE_PREFIX . "stock:{$skuId}";
            $pipeline->get($stockCacheKey);
        }
        
        $stocks = $pipeline->execute();
        
        // 处理结果，标记缺失的SKU
        foreach ($skuIds as $index => $skuId) {
            if (isset($stocks[$index]) && $stocks[$index] !== false) {
                $result[$skuId] = (int)$stocks[$index];
            } else {
                $missingSkuIds[] = $skuId;
            }
        }
        
        // 对于缓存中不存在的SKU，从数据库查询
        if (!empty($missingSkuIds)) {
            $dbStocks = GoodsSku::whereIn('id', $missingSkuIds)->column('stock', 'id');
            
            // 更新Redis缓存
            if (!empty($dbStocks)) {
                $pipeline = $redis->pipeline();
                
                foreach ($dbStocks as $skuId => $stock) {
                    $stockCacheKey = self::CACHE_PREFIX . "stock:{$skuId}";
                    $pipeline->set($stockCacheKey, $stock, ['EX' => self::CACHE_TIME]);
                    $result[$skuId] = (int)$stock;
                }
                
                $pipeline->execute();
            }
        }
        
        return $result;
    }
    
    /**
     * 获取商品SKU详情
     * 
     * @param int $skuId SKU ID
     * @param bool $useCache 是否使用缓存
     * @return array|null
     */
    public function getSkuDetail(int $skuId, bool $useCache = true): ?array
    {
        // 生成缓存键
        $skuCacheKey = self::CACHE_PREFIX . "sku:{$skuId}";
        
        // 从Redis尝试获取缓存数据
        if ($useCache) {
            $cacheData = $this->getCache($skuCacheKey);
            if ($cacheData !== null) {
                return $cacheData;
            }
        }
        
        // 分布式锁，防止缓存击穿
        $lockKey = "lock:" . $skuCacheKey;
        $lockValue = uniqid();
        $gotLock = false;
        
        try {
            // 尝试获取锁
            $gotLock = $this->acquireLock($lockKey, $lockValue, 5);
            
            // 获取锁后二次检查缓存
            if ($gotLock && $useCache) {
                $cacheData = $this->getCache($skuCacheKey);
                if ($cacheData !== null) {
                    return $cacheData;
                }
            }
            
            // 查询SKU详情
            $sku = GoodsSku::with('spu')->find($skuId);
            
            if (!$sku) {
                return null;
            }
            
            $result = $sku->toArray();
            
            // 获取库存
            $stockCacheKey = self::CACHE_PREFIX . "stock:{$skuId}";
            $stock = Cache::store('redis')->get($stockCacheKey);
            if ($stock !== null) {
                $result['stock_cache'] = (int)$stock;
            }
            
            // 设置缓存
            if ($useCache) {
                $this->setCache($skuCacheKey, $result, self::CACHE_TIME);
            }
            
            return $result;
        } finally {
            // 释放分布式锁
            if ($gotLock) {
                $this->releaseLock($lockKey, $lockValue);
            }
        }
    }
    
    /**
     * 获取分布式锁
     * 
     * @param string $key 锁键
     * @param string $value 锁值（用于释放锁时校验）
     * @param int $timeout 超时时间（秒）
     * @param int $retryCount 重试次数
     * @param int $retryDelay 重试延迟（毫秒）
     * @return bool
     */
    private function acquireLock(
        string $key, 
        string $value, 
        int $timeout = self::LOCK_TIMEOUT, 
        int $retryCount = self::LOCK_RETRY, 
        int $retryDelay = self::LOCK_RETRY_DELAY
    ): bool {
        $redis = Cache::store('redis')->handler();
        
        for ($i = 0; $i <= $retryCount; $i++) {
            // 尝试获取锁，NX表示不存在时才设置，PX表示过期时间（毫秒）
            $result = $redis->set($key, $value, ['NX', 'EX' => $timeout]);
            
            if ($result) {
                return true;
            }
            
            // 如果获取失败并且还有重试次数，则稍等一会再试
            if ($i < $retryCount) {
                usleep($retryDelay * 1000); // 微秒
            }
        }
        
        return false;
    }
    
    /**
     * 释放分布式锁
     * 
     * @param string $key 锁键
     * @param string $value 锁值（用于校验）
     * @return bool
     */
    private function releaseLock(string $key, string $value): bool
    {
        $redis = Cache::store('redis')->handler();
        
        // 使用Lua脚本保证原子性操作
        $script = <<<LUA
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;
        
        $result = $redis->eval($script, [$key, $value], 1);
        return (bool)$result;
    }
    
    /**
     * 设置缓存
     * 
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $time 缓存时间（秒）
     * @return bool
     */
    private function setCache(string $key, $value, int $time = self::CACHE_TIME): bool
    {
        return Cache::store('redis')->set($key, $value, $time);
    }
    
    /**
     * 获取缓存
     * 
     * @param string $key 缓存键
     * @return mixed
     */
    private function getCache(string $key)
    {
        return Cache::store('redis')->get($key);
    }
    
    /**
     * 删除缓存
     * 
     * @param string $key 缓存键
     * @return bool
     */
    private function deleteCache(string $key): bool
    {
        return Cache::store('redis')->delete($key);
    }
    
    /**
     * 批量删除缓存
     * 
     * @param array $keys 缓存键数组
     * @return int 删除的数量
     */
    private function deleteCacheBatch(array $keys): int
    {
        if (empty($keys)) {
            return 0;
        }
        
        $redis = Cache::store('redis')->handler();
        return $redis->del($keys);
    }
    
    /**
     * 将列表缓存键添加到索引中
     * 
     * @param string $cacheKey 缓存键
     * @param array $goods 商品数据
     * @return void
     */
    private function addListToIndex(string $cacheKey, array $goods): void
    {
        // 如果列表中有商品，记录到映射表里，方便批量更新
        if (empty($goods)) {
            return;
        }
        
        $redis = Cache::store('redis')->handler();
        $pipeline = $redis->pipeline();
        
        // 记录这个列表包含哪些商品ID
        $indexKey = self::CACHE_PREFIX . "list_index";
        $pipeline->hSet($indexKey, $cacheKey, json_encode(array_column($goods, 'id')));
        $pipeline->expire($indexKey, self::CACHE_TIME * 2);
        
        // 记录每个商品ID被哪些列表使用
        foreach ($goods as $item) {
            $goodsListKey = self::CACHE_PREFIX . "goods_lists:{$item['id']}";
            $pipeline->sAdd($goodsListKey, $cacheKey);
            $pipeline->expire($goodsListKey, self::CACHE_TIME);
        }
        
        $pipeline->execute();
    }
    
    /**
     * 异步清理商品缓存
     * 
     * @param int $spuId 商品ID
     * @param array $skuIds SKU ID数组
     * @return void
     */
    private function asyncClearCache(int $spuId, array $skuIds = []): void
    {
        // 使用队列任务异步清理缓存
        Queue::push('app\job\ClearGoodsCacheJob', [
            'spu_id' => $spuId,
            'sku_ids' => $skuIds
        ]);
    }
    
    /**
     * 异步清理SKU缓存
     * 
     * @param int $skuId SKU ID
     * @param int $spuId 商品ID
     * @return void
     */
    private function asyncClearSkuCache(int $skuId, int $spuId): void
    {
        // 使用队列任务异步清理缓存
        Queue::push('app\job\ClearGoodsCacheJob', [
            'spu_id' => $spuId,
            'sku_ids' => [$skuId],
            'only_sku' => true
        ]);
    }
    
    /**
     * 异步清理列表缓存
     * 
     * @return void
     */
    private function asyncCleanListCache(): void
    {
        // 使用队列任务异步清理列表缓存
        Queue::push('app\job\ClearGoodsListCacheJob', []);
    }
    
    /**
     * 异步预热商品缓存
     * 
     * @param int $spuId 商品ID
     * @return void
     */
    private function asyncPreloadGoodsCache(int $spuId): void
    {
        // 使用队列任务异步预热缓存
        Queue::push('app\job\PreloadGoodsCacheJob', [
            'spu_id' => $spuId
        ]);
    }
} 