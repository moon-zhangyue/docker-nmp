<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * 大模型适配层
 * - demo 模式：内置规则化回复生成器，无需密钥即可完整体验
 * - openai 模式：调用任意 OpenAI 兼容 /chat/completions 接口
 */
class LlmService
{
    /** 系统提示词 */
    private const SYSTEM_PROMPT = "你是一名专业、耐心的智能客服助手「小智」，服务于一家电商平台。\n" .
        "请用简洁、友好、专业的中文回答用户问题。\n" .
        "回答规则：\n" .
        "1. 优先基于提供的知识库内容回答，不要编造不存在的信息。\n" .
        "2. 若知识库无法解决，且问题超出你的能力，请明确告知并建议转人工。\n" .
        "3. 回答控制在150字以内，条理清晰。\n" .
        "4. 不要暴露你是AI模型或任何技术细节。";

    /**
     * 生成回复
     * @param string $question 用户当前问题
     * @param array $history 历史消息 [['role'=>'user/assistant','content'=>...]]
     * @param array $context 附加上下文（意图、FAQ命中）
     * @return array{content:string, source:string, model:string}
     */
    public static function reply(string $question, array $history = [], array $context = []): array
    {
        $cfg = Config::llm();
        if ($cfg['mode'] === 'openai' && $cfg['api_key'] !== '') {
            $content = self::callOpenAI($question, $history, $context, $cfg);
            if ($content !== null) {
                return ['content' => $content, 'source' => 'llm', 'model' => $cfg['model']];
            }
            // 接口失败则降级到演示模式
        }
        return [
            'content' => self::demoReply($question, $context),
            'source'  => 'llm',
            'model'   => 'demo-llm',
        ];
    }

    /** 调用 OpenAI 兼容接口 */
    private static function callOpenAI(string $question, array $history, array $context, array $cfg): ?string
    {
        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

        // 注入知识库上下文
        if (!empty($context['faq'])) {
            $faq = $context['faq'];
            $messages[] = [
                'role' => 'system',
                'content' => "【知识库参考】\n问题：" . $faq['question'] . "\n答案：" . $faq['answer'],
            ];
        }

        // 多轮历史（取最近 N 条）
        $recent = array_slice($history, -$cfg['max_history']);
        foreach ($recent as $h) {
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string)($h['content'] ?? '')];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $payload = json_encode([
            'model'       => $cfg['model'],
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 500,
        ], JSON_UNESCAPED_UNICODE);

        $url = rtrim($cfg['base_url'], '/') . '/chat/completions';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $cfg['timeout'],
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['api_key'],
            ],
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code !== 200) {
            return null;
        }
        $data = json_decode($resp, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    /** 演示模式回复生成器：根据意图与FAQ生成自然答复 */
    private static function demoReply(string $question, array $context): string
    {
        $intent = $context['intent']['intent'] ?? 'other';
        $faq = $context['faq'] ?? null;

        // 有 FAQ 命中：基于 FAQ 答案润色
        if ($faq && !empty($faq['answer'])) {
            return $faq['answer'];
        }

        switch ($intent) {
            case 'refund':
                return "关于退款，一般流程是：在「我的订单」中申请退款并填写原因，审核通过后款项会在1-7个工作日内原路退回。如果您能提供订单号，我可以帮您进一步查询退款进度。";
            case 'order_query':
                return "您可以提供订单号，我来帮您查询订单状态和物流信息。也可以在「我的订单」页面实时查看。请问您的订单号是多少？";
            case 'tech_support':
                return "很抱歉给您带来不便。建议您先尝试：升级到最新版本、清理缓存、重启设备或检查网络。如果问题仍然存在，请描述具体的报错信息，或回复「转人工」由技术专员为您处理。";
            default:
                return "您好，我是智能客服小智。我可以协助您处理退款、查询订单、技术支持等问题。请问您具体遇到了什么情况？如果我的回答无法解决您的问题，可以随时回复「转人工」。";
        }
    }
}
