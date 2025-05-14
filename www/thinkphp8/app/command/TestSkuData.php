<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

class TestSkuData extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('test:sku-data')
            ->setDescription('测试SKU数据处理');
    }

    protected function execute(Input $input, Output $output)
    {
        // 测试不同格式的SKU数据
        $this->testSkuDataProcessing($output);
    }

    /**
     * 测试SKU数据处理
     *
     * @param Output $output 输出对象
     */
    protected function testSkuDataProcessing(Output $output)
    {
        // 测试用例1: 正常的JSON字符串
        $jsonString = json_encode([
            [
                'price' => '99.99',
                'stock' => '100',
                'status' => '1'
            ],
            [
                'price' => '199.99',
                'stock' => '50',
                'status' => '1'
            ]
        ]);

        $output->writeln("<info>测试用例1: 正常的JSON字符串</info>");
        $output->writeln("原始数据: " . $jsonString);

        // 模拟处理
        $result1 = $this->processSkusData($jsonString);
        $output->writeln("处理后: " . json_encode($result1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $output->writeln("价格类型: " . gettype($result1[0]['price']));
        $output->writeln("价格值: " . $result1[0]['price']);
        $output->writeln("");

        // 测试用例2: 带有空字符串价格的数据
        $emptyPriceData = [
            [
                'price' => '',
                'stock' => '100',
                'status' => '1'
            ]
        ];

        $output->writeln("<info>测试用例2: 带有空字符串价格的数据</info>");
        $output->writeln("原始数据: " . json_encode($emptyPriceData));

        // 模拟处理
        $result2 = $this->processSkusData($emptyPriceData);
        $output->writeln("处理后: " . json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $output->writeln("价格类型: " . gettype($result2[0]['price']));
        $output->writeln("价格值: " . $result2[0]['price']);
        $output->writeln("");

        // 测试用例3: 带有非数字价格的数据
        $nonNumericPriceData = [
            [
                'price' => 'abc',
                'stock' => '100',
                'status' => '1'
            ]
        ];

        $output->writeln("<info>测试用例3: 带有非数字价格的数据</info>");
        $output->writeln("原始数据: " . json_encode($nonNumericPriceData));

        // 模拟处理
        $result3 = $this->processSkusData($nonNumericPriceData);
        $output->writeln("处理后: " . json_encode($result3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $output->writeln("价格类型: " . gettype($result3[0]['price']));
        $output->writeln("价格值: " . $result3[0]['price']);
        $output->writeln("");

        // 测试用例4: 验证处理后的数据
        $output->writeln("<info>测试用例4: 验证处理后的数据</info>");

        foreach ($result1 as $index => $sku) {
            $isValid = $this->validateSku($sku);
            $output->writeln("SKU {$index} 验证结果: " . ($isValid ? '通过' : '失败'));
        }

        foreach ($result2 as $index => $sku) {
            $isValid = $this->validateSku($sku);
            $output->writeln("SKU {$index} (空价格) 验证结果: " . ($isValid ? '通过' : '失败'));
        }

        foreach ($result3 as $index => $sku) {
            $isValid = $this->validateSku($sku);
            $output->writeln("SKU {$index} (非数字价格) 验证结果: " . ($isValid ? '通过' : '失败'));
        }
    }

    /**
     * 处理SKUs数据，确保格式正确
     *
     * @param mixed $skus 原始SKUs数据
     * @return array 处理后的SKUs数组
     */
    protected function processSkusData($skus): array
    {
        // 如果skus是字符串，尝试解析为JSON
        if (is_string($skus)) {
            $decodedSkus = json_decode($skus, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $skus = $decodedSkus;
            } else {
                Log::warning('SKUs JSON解析失败: ' . json_last_error_msg());
                return []; // 返回空数组，让验证器处理错误
            }
        }

        // 确保skus是数组
        if (!is_array($skus)) {
            Log::warning('SKUs不是有效的数组格式');
            return []; // 返回空数组，让验证器处理错误
        }

        // 处理每个SKU
        foreach ($skus as &$sku) {
            // 确保数值字段是正确的类型
            if (isset($sku['price'])) {
                $sku['price'] = (float)$sku['price'];
            }

            if (isset($sku['stock'])) {
                $sku['stock'] = (int)$sku['stock'];
            }

            if (isset($sku['status'])) {
                $sku['status'] = (int)$sku['status'];
            }

            if (isset($sku['id'])) {
                $sku['id'] = (int)$sku['id'];
            }
        }

        return $skus;
    }

    /**
     * 验证SKU数据
     *
     * @param array $sku SKU数据
     * @return bool 是否有效
     */
    protected function validateSku(array $sku): bool
    {
        if (!isset($sku['price']) || !is_numeric($sku['price']) || $sku['price'] < 0) {
            return false;
        }

        if (!isset($sku['stock']) || !is_numeric($sku['stock']) || $sku['stock'] < 0) {
            return false;
        }

        return true;
    }
}
