<?php

namespace app\controller\mongo;

use app\BaseController;
use app\service\mongo\ProductService;
use think\facade\Log;
use think\Request;

class ProductController extends BaseController
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
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
}
