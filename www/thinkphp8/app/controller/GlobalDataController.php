<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\GlobalDataService;
use think\facade\Log;
use think\Response;
use think\exception\ValidateException;

class GlobalDataController extends BaseController
{
    protected $globalDataService;
    
    public function __construct(GlobalDataService $globalDataService)
    {
        $this->globalDataService = $globalDataService;
    }
    
    /**
     * 保存全球数据
     * 
     * @return Response
     */
    public function save(): Response
    {
        try {
            // 获取POST数据
            $data = $this->request->post();
            
            // 保存数据
            $result = $this->globalDataService->saveData($data);
            
            return json(['code' => 200, 'message' => '保存成功', 'data' => $result]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('保存全球数据接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 更新全球数据
     * 
     * @param string $id 数据ID
     * @return Response
     */
    public function update(string $id): Response
    {
        try {
            // 获取PUT数据
            $data = $this->request->put();
            
            // 更新数据
            $result = $this->globalDataService->updateData($id, $data);
            
            return json(['code' => 200, 'message' => '更新成功', 'data' => $result]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新全球数据接口异常', ['id' => $id, 'message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 根据区域查询数据
     * 
     * @param string $region 区域编码
     * @return Response
     */
    public function region(string $region): Response
    {
        try {
            // 验证参数
            if (empty($region)) {
                return json(['code' => 400, 'message' => '区域编码不能为空']);
            }
            
            // 获取查询参数
            $conditions = [];
            $fieldParams = $this->request->get();
            
            // 移除分页参数
            unset($fieldParams['page']);
            unset($fieldParams['limit']);
            
            // 添加查询条件
            foreach ($fieldParams as $field => $value) {
                if (!empty($value)) {
                    $conditions[$field] = $value;
                }
            }
            
            // 分页参数
            $page = intval($this->request->param('page', 1));
            $limit = intval($this->request->param('limit', 20));
            
            // 查询数据
            $data = $this->globalDataService->getDataByRegion($region, $conditions, $page, $limit);
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('根据区域查询数据接口异常', ['region' => $region, 'message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取全球区域统计
     * 
     * @return Response
     */
    public function stats(): Response
    {
        try {
            // 获取过滤条件
            $conditions = [];
            $fieldParams = $this->request->get();
            
            // 添加过滤条件
            foreach ($fieldParams as $field => $value) {
                if (!empty($value)) {
                    $conditions[$field] = $value;
                }
            }
            
            // 获取统计
            $stats = $this->globalDataService->getRegionStats($conditions);
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $stats]);
        } catch (\Exception $e) {
            Log::error('获取全球区域统计接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 多区域数据对比
     * 
     * @return Response
     */
    public function compare(): Response
    {
        try {
            // 获取请求参数
            $regions = $this->request->param('regions');
            $metric = $this->request->param('metric');
            
            // 验证参数
            if (empty($regions) || empty($metric)) {
                return json(['code' => 400, 'message' => '区域列表和对比指标不能为空']);
            }
            
            // 解析区域列表
            $regionList = explode(',', $regions);
            
            // 执行对比
            $result = $this->globalDataService->compareRegions($regionList, $metric);
            
            return json(['code' => 200, 'message' => '对比成功', 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('多区域数据对比接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取热门区域
     * 
     * @return Response
     */
    public function hotRegions(): Response
    {
        try {
            // 获取请求参数
            $limit = intval($this->request->param('limit', 10));
            
            // 获取热门区域
            $hotRegions = $this->globalDataService->getHotRegions($limit);
            
            return json(['code' => 200, 'message' => '查询成功', 'data' => $hotRegions]);
        } catch (\Exception $e) {
            Log::error('获取热门区域接口异常', ['message' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '服务器错误：' . $e->getMessage()]);
        }
    }
} 