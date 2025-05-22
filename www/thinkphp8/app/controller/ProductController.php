<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\ProductService;
use think\facade\Log;
use think\Response;
use think\exception\ValidateException;

class ProductController extends BaseController
{
    protected $productService;
    
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    
    /**
     * 创建产品
     * 
     * @return Response
     */
    public function create(): Response
    {
        try {
            // 获取POST数据
            $data = $this->request->post();
            
            // 创建产品
            $product = $this->productService->create($data);
            
            return json(['code' => 200, 'message' => '创建成功', 'data' => $product]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 更新产品
     * 
     * @param string $id 产品ID
     * @return Response
     */
    public function update(string $id): Response
    {
        try {
            // 获取PUT数据
            $data = $this->request->put();
            
            // 更新产品
            $result = $this->productService->update($id, $data);
            
            return json(['code' => 200, 'message' => '更新成功', 'data' => $result]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 查询产品
     * 
     * @return Response
     */
    public function search(): Response
    {
        try {
            // 获取查询参数
            $params = $this->request->get();
            
            // 查询产品
            $products = $this->productService->search($params);
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $products]);
        } catch (\Exception $e) {
            Log::error('查询产品接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
} 