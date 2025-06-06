<?php

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\ProductService;
use think\facade\Log;
use think\Request;
use think\App;
use think\Response;
use think\exception\ValidateException;


class ProductController extends BaseController
{
    protected $productService;


    public function __construct(App $app, ProductService $productService)
    {
        parent::__construct($app);
        $this->productService = $productService;
    }

    /**
     * Add a new product.
     * Demonstrates dynamic schema by accepting various product structures.
     * Example POST data:
     * {"name": "Laptop", "price": 1200, "brand": "XYZ", "features": ["16GB RAM", "512GB SSD"]}
     * {"name": "T-Shirt", "price": 25, "color": "Blue", "size": "M"}
     */
    public function add(Request $request)
    {
        $data = $request->post();
        if (empty($data)) {
            Log::warning('[ProductController] Add: Empty request data.');
            return json(['status' => 'error', 'message' => 'No data provided'], 400);
        }

        Log::info('[ProductController] Add: Received request to add product. Data: ' . json_encode($data));
        $result = $this->productService->addProduct($data);

        if ($result) {
            Log::info('[ProductController] Add: Product added successfully. ID: ' . $result);
            return json(['status' => 'success', 'message' => 'Product added successfully', 'id' => $result]);
        } else {
            Log::error('[ProductController] Add: Failed to add product.');
            return json(['status' => 'error', 'message' => 'Failed to add product'], 500);
        }
    }

    /**
     * Get all products.
     */
    public function list()
    {
        Log::info('[ProductController] List: Received request to list all products.');
        $products = $this->productService->getAllProducts();
        Log::info('[ProductController] List: Responding with ' . count($products) . ' products.');
        return json(['status' => 'success', 'data' => $products]);
    }

    /**
     * Get a specific product by ID.
     * @param string $id
     */
    public function get(string $id)
    {
        if (empty($id)) {
            Log::warning('[ProductController] Get: Empty product ID.');
            return json(['status' => 'error', 'message' => 'Product ID cannot be empty'], 400);
        }
        Log::info('[ProductController] Get: Received request to get product by ID: ' . $id);
        $product = $this->productService->findProductById($id);

        if ($product) {
            Log::info('[ProductController] Get: Product found. ID: ' . $id);
            return json(['status' => 'success', 'data' => $product]);
        } else {
            Log::warning('[ProductController] Get: Product not found. ID: ' . $id);
            return json(['status' => 'error', 'message' => 'Product not found'], 404);
        }
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
