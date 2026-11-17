<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * 意图识别服务
 * 识别意图：refund(退款) / order_query(查询订单) / tech_support(技术支持) / other(其他)
 * 采用关键词加权打分，返回意图与置信度；演示模式下足以稳定工作。
 */
class IntentService
{
    public const INTENTS = [
        'refund'       => '退款',
        'order_query'  => '查询订单',
        'tech_support' => '技术支持',
        'other'        => '其他',
    ];

    /** 关键词权重表 */
    private const RULES = [
        'refund' => [
            '退款' => 3, '退货' => 3, '退钱' => 3, '申请退款' => 4, '退款到账' => 4,
            '不想要了' => 2, '退' => 1, '取消订单' => 2, '退回' => 2, '返款' => 2,
        ],
        'order_query' => [
            '订单' => 2, '查订单' => 4, '订单状态' => 4, '物流' => 3, '发货' => 3,
            '快递' => 2, '到哪了' => 3, '什么时候发货' => 4, '运单' => 2, '我的订单' => 4,
            '签收' => 2, '配送' => 2,
        ],
        'tech_support' => [
            '闪退' => 4, '打不开' => 3, '崩溃' => 3, '无法登录' => 4, '登录失败' => 4,
            '报错' => 3, '故障' => 3, '不能用' => 3, 'bug' => 3, '卡顿' => 2,
            '忘记密码' => 3, '重置密码' => 3, '异常' => 2, '技术' => 2, '黑屏' => 3,
            '闪退打不开' => 4,
        ],
    ];

    /** 转人工触发词 */
    private const HUMAN_WORDS = ['转人工', '人工客服', '人工', '真人', '客服电话', '投诉', '找个人'];

    /**
     * 识别意图
     * @return array{intent:string, label:string, confidence:float, scores:array, want_human:bool}
     */
    public static function detect(string $text): array
    {
        $text = mb_strtolower(trim($text));
        $scores = ['refund' => 0.0, 'order_query' => 0.0, 'tech_support' => 0.0, 'other' => 0.0];

        foreach (self::RULES as $intent => $words) {
            foreach ($words as $word => $weight) {
                if (mb_strpos($text, mb_strtolower($word)) !== false) {
                    $scores[$intent] += $weight;
                }
            }
        }

        arsort($scores);
        $topIntent = array_key_first($scores);
        $topScore = $scores[$topIntent];

        // 无命中 -> other
        if ($topScore <= 0) {
            $topIntent = 'other';
            $confidence = 0.35;
        } else {
            $total = array_sum($scores);
            $confidence = $total > 0 ? min(0.98, 0.45 + 0.5 * ($topScore / $total)) : 0.4;
            if ($topScore < 2) {
                $topIntent = 'other';
                $confidence = 0.4;
            }
        }

        $wantHuman = false;
        foreach (self::HUMAN_WORDS as $w) {
            if (mb_strpos($text, $w) !== false) { $wantHuman = true; break; }
        }

        return [
            'intent'     => $topIntent,
            'label'      => self::INTENTS[$topIntent] ?? '其他',
            'confidence' => round($confidence, 2),
            'scores'     => $scores,
            'want_human' => $wantHuman,
        ];
    }

    public static function label(string $intent): string
    {
        return self::INTENTS[$intent] ?? '其他';
    }
}
