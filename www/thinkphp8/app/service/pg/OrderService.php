<?php
declare(strict_types=1);

namespace app\service\pg;

use app\model\pg\Order;
use app\model\pg\OrderItem;
use app\model\pg\OrderLog;
use app\model\pg\Cart;
use app\model\pg\GoodsSku;
use app\model\pg\UserAddress;
use app\exception\BusinessException;
use think\facade\Log;
use think\facade\Db;

/**
 * 订单服务类
 */
class OrderService
{
    /**
     * 获取订单列表
     *
     * @param int $userId 用户ID
     * @param int $status 订单状态，-1表示全部
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function getOrderList(int $userId, int $status = -1, int $page = 1, int $limit = 10)
    {
        // 构建查询条件
        $where = ['user_id' => $userId];

        // 订单状态
        if ($status >= 0) {
            $where['status'] = $status;
        }

        // 查询订单列表
        $query = Order::where($where);

        // 查询订单总数
        $total = $query->count();

        // 分页查询
        $orders = $query->with(['orderItems'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 返回结果
        return [
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page,
            'last_page'    => ceil($total / $limit),
            'data'         => $orders
        ];
    }

    /**
     * 获取订单详情
     *
     * @param int $userId 用户ID
     * @param int $orderId 订单ID
     * @return Order
     * @throws BusinessException
     */
    public function getOrderDetail(int $userId, int $orderId)
    {
        // 查询订单
        $order = Order::with(['orderItems', 'orderLogs'])
            ->where('user_id', $userId)
            ->find($orderId);

        if (!$order) {
            throw new BusinessException('订单不存在');
        }

        return $order;
    }

    /**
     * 创建订单
     *
     * @param int $userId 用户ID
     * @param int $addressId 地址ID
     * @param string $note 订单备注
     * @return Order
     * @throws BusinessException
     */
    public function createOrder(int $userId, int $addressId, string $note = '')
    {
        // 获取用户地址
        $address = UserAddress::where('id', $addressId)
            ->where('user_id', $userId)
            ->find();

        if (!$address) {
            throw new BusinessException('收货地址不存在');
        }

        // 获取购物车已选商品
        $cartItems = Cart::getSelectedItems($userId);

        if ($cartItems->isEmpty()) {
            throw new BusinessException('购物车中没有选中的商品');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 创建订单
            $order              = new Order();
            $order->order_no    = Order::generateOrderNo();
            $order->user_id     = $userId;
            $order->status      = Order::STATUS_PENDING;
            $order->pay_status  = Order::PAY_STATUS_UNPAID;
            $order->ship_status = Order::SHIP_STATUS_UNSHIPPED;

            // 设置收货信息
            $order->receiver_name     = $address['name'];
            $order->receiver_mobile   = $address['mobile'];
            $order->receiver_province = $address['province'];
            $order->receiver_city     = $address['city'];
            $order->receiver_district = $address['district'];
            $order->receiver_address  = $address['detail'];
            $order->note              = $note;

            // 初始化金额
            $order->total_amount    = 0;
            $order->pay_amount      = 0;
            $order->discount_amount = 0;
            $order->freight_amount  = 0;

            // 保存订单
            $order->save();

            // 创建订单商品
            $totalAmount = 0;
            foreach ($cartItems as $item) {
                // 获取SKU
                $sku = $item->sku;
                if (!$sku || !$sku->status) {
                    throw new BusinessException('商品已下架');
                }

                // 获取商品
                $goods = $sku->goods;
                if (!$goods || !$goods->on_sale) {
                    throw new BusinessException('商品已下架');
                }

                // 检查库存
                if ($sku->stock < $item->quantity) {
                    throw new BusinessException("商品 [{$goods->name}] 库存不足");
                }

                // 减少库存
                $sku->decreaseStock($item->quantity);

                // 创建订单商品
                $orderItem               = new OrderItem();
                $orderItem->order_id     = $order->id;
                $orderItem->order_no     = $order->order_no;
                $orderItem->goods_id     = $goods->id;
                $orderItem->sku_id       = $sku->id;
                $orderItem->goods_name   = $goods->name;
                $orderItem->sku_name     = $sku->name;
                $orderItem->image        = $sku->image ?: $goods->cover;
                $orderItem->specs        = $sku->specs;
                $orderItem->price        = $sku->price;
                $orderItem->quantity     = $item->quantity;
                $orderItem->total_amount = $sku->price * $item->quantity;
                $orderItem->save();

                // 累加总金额
                $totalAmount += $orderItem->total_amount;
            }

            // 更新订单金额
            $order->total_amount = $totalAmount;
            $order->pay_amount   = $totalAmount + $order->freight_amount - $order->discount_amount;
            $order->save();

            // 记录订单日志
            OrderLog::record(
                $order->id,
                $order->order_no,
                $userId,
                '创建订单',
                '用户创建订单',
                OrderLog::TYPE_USER
            );

            // 清空购物车已选商品
            foreach ($cartItems as $item) {
                $item->delete();
            }

            // 提交事务
            Db::commit();

            Log::info('创建订单成功 {user_id} {order_id} {order_no}', [
                'user_id'  => $userId,
                'order_id' => $order->id,
                'order_no' => $order->order_no
            ]);

            return $order;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            Log::error('创建订单异常 {error} {user_id} {address_id}', [
                'error'      => $e->getMessage(),
                'user_id'    => $userId,
                'address_id' => $addressId
            ]);

            throw new BusinessException('创建订单失败：' . $e->getMessage());
        }
    }

    /**
     * 取消订单
     *
     * @param int $userId 用户ID
     * @param int $orderId 订单ID
     * @param string $reason 取消原因
     * @return Order
     * @throws BusinessException
     */
    public function cancelOrder(int $userId, int $orderId, string $reason = '')
    {
        // 查询订单
        $order = Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->find();

        if (!$order) {
            throw new BusinessException('订单不存在');
        }

        // 检查订单状态
        if ($order->status != Order::STATUS_PENDING) {
            throw new BusinessException('订单状态不允许取消');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $order->status        = Order::STATUS_CANCELLED;
            $order->cancel_time   = date('Y-m-d H:i:s');
            $order->cancel_reason = $reason;
            $order->save();

            // 恢复库存
            $orderItems = OrderItem::where('order_id', $orderId)->select();
            foreach ($orderItems as $item) {
                $sku = GoodsSku::find($item->sku_id);
                if ($sku) {
                    $sku->increaseStock($item->quantity);
                }
            }

            // 记录订单日志
            OrderLog::record(
                $order->id,
                $order->order_no,
                $userId,
                '取消订单',
                '用户取消订单，原因：' . ($reason ?: '未提供原因'),
                OrderLog::TYPE_USER
            );

            // 提交事务
            Db::commit();

            Log::info('取消订单成功 {user_id} {order_id} {order_no} {reason}', [
                'user_id'  => $userId,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'reason'   => $reason
            ]);

            return $order;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            Log::error('取消订单异常 {error} {user_id} {order_id}', [
                'error'    => $e->getMessage(),
                'user_id'  => $userId,
                'order_id' => $orderId
            ]);

            throw new BusinessException('取消订单失败：' . $e->getMessage());
        }
    }

    /**
     * 支付订单
     *
     * @param int $userId 用户ID
     * @param int $orderId 订单ID
     * @param int $payMethod 支付方式
     * @return Order
     * @throws BusinessException
     */
    public function payOrder(int $userId, int $orderId, int $payMethod)
    {
        // 查询订单
        $order = Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->find();

        if (!$order) {
            throw new BusinessException('订单不存在');
        }

        // 检查订单状态
        if ($order->status != Order::STATUS_PENDING) {
            throw new BusinessException('订单状态不允许支付');
        }

        // 检查支付状态
        if ($order->pay_status == Order::PAY_STATUS_PAID) {
            throw new BusinessException('订单已支付');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $order->status     = Order::STATUS_PROCESSING;
            $order->pay_status = Order::PAY_STATUS_PAID;
            $order->pay_method = $payMethod;
            $order->pay_time   = date('Y-m-d H:i:s');
            $order->save();

            // 记录订单日志
            OrderLog::record(
                $order->id,
                $order->order_no,
                $userId,
                '支付订单',
                '用户支付订单，支付方式：' . $order->getPayMethodTextAttr(null, ['pay_method' => $payMethod]),
                OrderLog::TYPE_USER
            );

            // 提交事务
            Db::commit();

            Log::info('支付订单成功 {user_id} {order_id} {order_no} {pay_method}', [
                'user_id'    => $userId,
                'order_id'   => $order->id,
                'order_no'   => $order->order_no,
                'pay_method' => $payMethod
            ]);

            return $order;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            Log::error('支付订单异常 {error} {user_id} {order_id}', [
                'error'    => $e->getMessage(),
                'user_id'  => $userId,
                'order_id' => $orderId
            ]);

            throw new BusinessException('支付订单失败：' . $e->getMessage());
        }
    }

    /**
     * 确认收货
     *
     * @param int $userId 用户ID
     * @param int $orderId 订单ID
     * @return Order
     * @throws BusinessException
     */
    public function confirmOrder(int $userId, int $orderId)
    {
        // 查询订单
        $order = Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->find();

        if (!$order) {
            throw new BusinessException('订单不存在');
        }

        // 检查订单状态
        if ($order->status != Order::STATUS_SHIPPED) {
            throw new BusinessException('订单状态不允许确认收货');
        }

        // 检查发货状态
        if ($order->ship_status != Order::SHIP_STATUS_SHIPPED) {
            throw new BusinessException('订单未发货，不能确认收货');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $order->status       = Order::STATUS_COMPLETED;
            $order->ship_status  = Order::SHIP_STATUS_RECEIVED;
            $order->confirm_time = date('Y-m-d H:i:s');
            $order->save();

            // 记录订单日志
            OrderLog::record(
                $order->id,
                $order->order_no,
                $userId,
                '确认收货',
                '用户确认收货',
                OrderLog::TYPE_USER
            );

            // 提交事务
            Db::commit();

            Log::info('确认收货成功 {user_id} {order_id} {order_no}', [
                'user_id'  => $userId,
                'order_id' => $order->id,
                'order_no' => $order->order_no
            ]);

            return $order;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            Log::error('确认收货异常 {error} {user_id} {order_id}', [
                'error'    => $e->getMessage(),
                'user_id'  => $userId,
                'order_id' => $orderId
            ]);

            throw new BusinessException('确认收货失败：' . $e->getMessage());
        }
    }

    /**
     * 发货
     * 
     * @param int $orderId 订单ID
     * @param string $deliveryCompany 物流公司
     * @param string $deliveryNo 物流单号
     * @return Order
     * @throws BusinessException
     */
    public function shipOrder(int $orderId, string $deliveryCompany, string $deliveryNo)
    {
        // 查询订单
        $order = Order::find($orderId);

        if (!$order) {
            throw new BusinessException('订单不存在');
        }

        // 检查订单状态
        if ($order->status != Order::STATUS_PROCESSING) {
            throw new BusinessException('订单状态不允许发货');
        }

        // 检查支付状态
        if ($order->pay_status != Order::PAY_STATUS_PAID) {
            throw new BusinessException('订单未支付，不能发货');
        }

        // 检查发货状态
        if ($order->ship_status != Order::SHIP_STATUS_UNSHIPPED) {
            throw new BusinessException('订单已发货');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $order->status           = Order::STATUS_SHIPPED;
            $order->ship_status      = Order::SHIP_STATUS_SHIPPED;
            $order->ship_time        = date('Y-m-d H:i:s');
            $order->delivery_company = $deliveryCompany;
            $order->delivery_no      = $deliveryNo;
            $order->save();

            // 记录订单日志
            OrderLog::record(
                $order->id,
                $order->order_no,
                0, // 系统操作
                '订单发货',
                "订单已发货，物流公司：{$deliveryCompany}，物流单号：{$deliveryNo}",
                OrderLog::TYPE_SYSTEM
            );

            // 提交事务
            Db::commit();

            Log::info('订单发货成功 {order_id} {order_no} {delivery_company} {delivery_no}', [
                'order_id'         => $order->id,
                'order_no'         => $order->order_no,
                'delivery_company' => $deliveryCompany,
                'delivery_no'      => $deliveryNo
            ]);

            return $order;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            Log::error('订单发货异常 {error} {order_id}', [
                'error'    => $e->getMessage(),
                'order_id' => $orderId
            ]);

            throw new BusinessException('订单发货失败：' . $e->getMessage());
        }
    }
}