<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\logic\GoodsLogic;
use app\validate\GoodsValidate;
use think\facade\Log;
use think\facade\Request;
use think\Response;
use think\exception\ValidateException;

class GoodsController extends BaseController
{
    /**
     * 商品逻辑类实例
     * 
     * @var GoodsLogic
     */
    protected GoodsLogic $goodsLogic;
    
    /**
     * 控制器构造函数
     */
    public function __construct()
    {
        $this->goodsLogic = new GoodsLogic();
    }
    
    /**
     * 获取商品列表
     * 
     * @return Response
     */
    public function index(): Response
    {
        try {
            $params = Request::get();
            
            // 转换分页参数
            $params['page'] = isset($params['page']) ? (int)$params['page'] : 1;
            $params['limit'] = isset($params['limit']) ? (int)$params['limit'] : 10;
            
            // 转换布尔值
            $params['with_promotion'] = isset($params['with_promotion']) && 
                ($params['with_promotion'] === 'true' || $params['with_promotion'] === '1' || $params['with_promotion'] === true);
            
            $result = $this->goodsLogic->getGoodsList($params);
            
            return json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取商品列表失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => '获取商品列表失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取商品详情
     * 
     * @param int $id 商品ID
     * @return Response
     */
    public function read(int $id): Response
    {
        try {
            $goods = $this->goodsLogic->getGoodsDetail($id);
            
            if (!$goods) {
                return json([
                    'code' => 404,
                    'msg' => '商品不存在'
                ]);
            }
            
            return json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $goods
            ]);
        } catch (\Exception $e) {
            Log::error('获取商品详情失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => '获取商品详情失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 创建商品
     * 
     * @return Response
     */
    public function save(): Response
    {
        try {
            $data = Request::post();
            
            // 验证数据
            try {
                validate(GoodsValidate::class)->check($data);
            } catch (ValidateException $e) {
                return json([
                    'code' => 400,
                    'msg' => $e->getMessage()
                ]);
            }
            
            // 创建商品
            $goodsId = $this->goodsLogic->createGoods($data);
            
            return json([
                'code' => 201,
                'msg' => '创建成功',
                'data' => [
                    'id' => $goodsId
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('创建商品失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => '创建商品失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 更新商品
     * 
     * @param int $id 商品ID
     * @return Response
     */
    public function update(int $id): Response
    {
        try {
            $data = Request::put();
            
            // 验证数据
            try {
                validate(GoodsValidate::class)->scene('update')->check($data);
            } catch (ValidateException $e) {
                return json([
                    'code' => 400,
                    'msg' => $e->getMessage()
                ]);
            }
            
            // 更新商品
            $result = $this->goodsLogic->updateGoods($id, $data);
            
            if (!$result) {
                return json([
                    'code' => 404,
                    'msg' => '商品不存在或更新失败'
                ]);
            }
            
            return json([
                'code' => 200,
                'msg' => '更新成功'
            ]);
        } catch (\Exception $e) {
            Log::error('更新商品失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => '更新商品失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 删除商品
     * 
     * @param int $id 商品ID
     * @return Response
     */
    public function delete(int $id): Response
    {
        try {
            $result = $this->goodsLogic->deleteGoods($id);
            
            if (!$result) {
                return json([
                    'code' => 404,
                    'msg' => '商品不存在或删除失败'
                ]);
            }
            
            return json([
                'code' => 200,
                'msg' => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除商品失败: ' . $e->getMessage());
            
            return json([
                'code' => 500,
                'msg' => '删除商品失败: ' . $e->getMessage()
            ]);
        }
    }
} 