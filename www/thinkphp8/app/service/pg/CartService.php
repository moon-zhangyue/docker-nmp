<?php
declare(strict_types=1);

namespace app\service\pg;

use app\model\pg\Cart;
use app\model\pg\GoodsSku;
use app\exception\BusinessException;
use think\facade\Log;
use think\facade\Db;

/**
 * 购物车服务类
 */
class CartService
{
    /**
     * 获取购物车列表
     *
     * @param int $userId 用户ID
     * @return array
     */
    public function getCartList(int $userId)
    {
        // 获取购物车列表
        $cart = Cart::getCartList($userId);
        
        // 处理商品数据
        $total = 0;
        $totalQuantity = 0;
        $items = [];
        
        foreach ($cart as $item) {
            // 获取SKU
            $sku = $item->sku;
            if (!$sku || !$sku->status) {
                // 商品不存在或已下架，自动从购物车移除
                $item->delete();
                continue;
            }
            
            // 获取商品
            $goods = $sku->goods;
            if (!$goods || !$goods->on_sale) {
                // 商品不存在或已下架，自动从购物车移除
                $item->delete();
                continue;
            }
            
            // 计算小计
            $subtotal = $sku->price * $item->quantity;
            
            // 如果选中，计入总价
            if ($item->selected) {
                $total += $subtotal;
                $totalQuantity += $item->quantity;
            }
            
            // 构建商品数据
            $items[] = [
                'id' => $item->id,
                'sku_id' => $item->sku_id,
                'goods_id' => $goods->id,
                'name' => $goods->name,
                'cover' => $goods->cover,
                'sku_name' => $sku->name,
                'image' => $sku->image ?: $goods->cover,
                'price' => $sku->price,
                'specs' => $sku->specs,
                'specs_text' => $sku->specs_text,
                'quantity' => $item->quantity,
                'stock' => $sku->stock,
                'selected' => $item->selected,
                'subtotal' => $subtotal,
            ];
        }
        
        // 返回结果
        return [
            'items' => $items,
            'total' => $total,
            'total_quantity' => $totalQuantity,
        ];
    }
    
    /**
     * 添加商品到购物车
     *
     * @param int $userId 用户ID
     * @param int $skuId SKU ID
     * @param int $quantity 数量
     * @return array
     * @throws BusinessException
     */
    public function addToCart(int $userId, int $skuId, int $quantity = 1)
    {
        // 检查商品是否存在
        $sku = GoodsSku::find($skuId);
        if (!$sku) {
            throw new BusinessException('商品不存在');
        }
        
        // 检查商品状态
        if (!$sku->status) {
            throw new BusinessException('商品已下架');
        }
        
        // 检查商品库存
        if ($sku->stock <= 0) {
            throw new BusinessException('商品已售罄');
        }
        
        // 检查商品库存是否充足
        if ($sku->stock < $quantity) {
            throw new BusinessException('商品库存不足');
        }
        
        // 检查商品是否上架
        $goods = $sku->goods;
        if (!$goods || !$goods->on_sale) {
            throw new BusinessException('商品已下架');
        }
        
        // 添加到购物车
        try {
            $cart = Cart::addToCart($userId, $skuId, $quantity);
            
            Log::info('添加商品到购物车', [
                'user_id' => $userId,
                'sku_id' => $skuId,
                'quantity' => $quantity,
                'cart_id' => $cart->id
            ]);
            
            // 返回购物车列表
            return $this->getCartList($userId);
        } catch (\Exception $e) {
            Log::error('添加商品到购物车异常', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'sku_id' => $skuId,
                'quantity' => $quantity
            ]);
            throw new BusinessException('添加商品到购物车失败');
        }
    }
    
    /**
     * 更新购物车商品数量
     *
     * @param int $userId 用户ID
     * @param int $id 购物车ID
     * @param int $quantity 数量
     * @return array
     * @throws BusinessException
     */
    public function updateQuantity(int $userId, int $id, int $quantity)
    {
        // 检查购物车商品是否存在
        $cart = Cart::where('id', $id)->where('user_id', $userId)->find();
        if (!$cart) {
            throw new BusinessException('购物车商品不存在');
        }
        
        // 检查数量是否合法
        if ($quantity <= 0) {
            throw new BusinessException('商品数量必须大于0');
        }
        
        // 检查商品库存
        $sku = GoodsSku::find($cart->sku_id);
        if (!$sku) {
            throw new BusinessException('商品不存在');
        }
        
        // 检查商品库存是否充足
        if ($sku->stock < $quantity) {
            throw new BusinessException('商品库存不足');
        }
        
        // 更新购物车商品数量
        try {
            Cart::updateQuantity($id, $userId, $quantity);
            
            Log::info('更新购物车商品数量', [
                'user_id' => $userId,
                'cart_id' => $id,
                'quantity' => $quantity
            ]);
            
            // 返回购物车列表
            return $this->getCartList($userId);
        } catch (\Exception $e) {
            Log::error('更新购物车商品数量异常', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'cart_id' => $id,
                'quantity' => $quantity
            ]);
            throw new BusinessException('更新购物车商品数量失败');
        }
    }
    
    /**
     * 更新购物车商品选中状态
     *
     * @param int $userId 用户ID
     * @param int $id 购物车ID
     * @param bool $selected 是否选中
     * @return array
     * @throws BusinessException
     */
    public function updateSelected(int $userId, int $id, bool $selected)
    {
        // 检查购物车商品是否存在
        $cart = Cart::where('id', $id)->where('user_id', $userId)->find();
        if (!$cart) {
            throw new BusinessException('购物车商品不存在');
        }
        
        // 更新购物车商品选中状态
        try {
            Cart::updateSelected($id, $userId, $selected);
            
            Log::info('更新购物车商品选中状态', [
                'user_id' => $userId,
                'cart_id' => $id,
                'selected' => $selected
            ]);
            
            // 返回购物车列表
            return $this->getCartList($userId);
        } catch (\Exception $e) {
            Log::error('更新购物车商品选中状态异常', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'cart_id' => $id,
                'selected' => $selected
            ]);
            throw new BusinessException('更新购物车商品选中状态失败');
        }
    }
    
    /**
     * 全选/全不选
     *
     * @param int $userId 用户ID
     * @param bool $selected 是否选中
     * @return array
     * @throws BusinessException
     */
    public function selectAll(int $userId, bool $selected)
    {
        // 更新购物车商品选中状态
        try {
            Cart::selectAll($userId, $selected);
            
            Log::info('购物车全选/全不选', [
                'user_id' => $userId,
                'selected' => $selected
            ]);
            
            // 返回购物车列表
            return $this->getCartList($userId);
        } catch (\Exception $e) {
            Log::error('购物车全选/全不选异常', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'selected' => $selected
            ]);
            throw new BusinessException('购物车全选/全不选失败');
        }
    }
    
    /**
     * 删除购物车商品
     *
     * @param int $userId 用户ID
     * @param int $id 购物车ID
     * @return array
     * @throws BusinessException
     */
    public function removeFromCart(int $userId, int $id)
    {
        // 检查购物车商品是否存在
        $cart = Cart::where('id', $id)->where('user_id', $userId)->find();
        if (!$cart) {
            throw new BusinessException('购物车商品不存在');
        }
        
        // 删除购物车商品
        try {
            Cart::removeFromCart($id, $userId);
            
            Log::info('删除购物车商品', [
                'user_id' => $userId,
                'cart_id' => $id
            ]);
            
            // 返回购物车列表
            return $this->getCartList($userId);
        } catch (\Exception $e) {
            Log::error('删除购物车商品异常', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'cart_id' => $id
            ]);
            throw new BusinessException('删除购物车商品失败');
        }
    }
    
    /**
     * 清空购物车
     *
     * @param int $userId 用户ID
     * @return array
     * @throws BusinessException
     */
    public function clearCart(int $userId)
    {
        // 清空购物车
        try {
            Cart::clearCart($userId);
            
            Log::info('清空购物车', [
                'user_id' => $userId
            ]);
            
            // 返回空购物车
            return [
                'items' => [],
                'total' => 0,
                'total_quantity' => 0,
            ];
        } catch (\Exception $e) {
            Log::error('清空购物车异常', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw new BusinessException('清空购物车失败');
        }
    }
    
    /**
     * 获取购物车商品数量
     *
     * @param int $userId 用户ID
     * @return int
     */
    public function getCartCount(int $userId)
    {
        return Cart::getCartCount($userId);
    }
} 