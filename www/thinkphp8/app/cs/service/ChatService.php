<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * 对话编排服务（系统中枢）
 * 负责：会话管理、多轮上下文、消息持久化、意图识别、FAQ检索、LLM回复、转人工、满意度
 */
class ChatService
{
    /**
     * 处理一条用户消息，返回助手回复
     * @return array 回复负载
     */
    public static function handle(string $sessionId, string $question, array $extra = []): array
    {
        $question = trim($question);
        if ($question === '') {
            return ['error' => '消息内容不能为空'];
        }

        // 1. 确保会话存在
        self::ensureConversation($sessionId, $extra);

        // 2. 意图识别
        $intent = IntentService::detect($question);

        // 3. 读取多轮上下文（Redis）；若上下文已过期则从 MySQL 历史重建，保证"继续聊天"连贯
        $history = self::getContext($sessionId);
        if (empty($history)) {
            $past = self::messages($sessionId);
            foreach ($past as $m) {
                if ($m['role'] === 'user' || $m['role'] === 'assistant') {
                    $history[] = ['role' => $m['role'], 'content' => $m['content']];
                }
            }
            $history = array_slice($history, -10);
            foreach ($history as $h) {
                self::appendContext($sessionId, $h['role'], $h['content']);
            }
        }

        // 4. 保存用户消息
        self::saveMessage($sessionId, 'user', $question, $intent['intent'], 'user');
        self::appendContext($sessionId, 'user', $question);

        // 5. 转人工判定
        $humanReason = self::decideHuman($question, $intent, $history);
        if ($humanReason !== null) {
            $ticket = self::transferToHuman($sessionId, $question, $intent['intent'], $humanReason);
            $reply = "我已为您转接人工客服，工单号：{$ticket['ticket_no']}。人工客服会在稍后为您服务（服务时间 9:00-21:00），请稍候。";
            self::saveMessage($sessionId, 'system', $reply, $intent['intent'], 'agent');
            self::appendContext($sessionId, 'assistant', $reply);
            return [
                'reply'       => $reply,
                'intent'      => $intent['intent'],
                'intent_label'=> $intent['label'],
                'confidence'  => $intent['confidence'],
                'source'      => 'agent',
                'transferred' => true,
                'ticket_no'   => $ticket['ticket_no'],
            ];
        }

        // 6. FAQ 检索
        $faq = FaqService::search($question, $intent['intent']);

        // 7. 生成回复：FAQ 命中直接返回，否则交给 LLM
        if ($faq && $faq['score'] >= 3.0) {
            $reply = $faq['answer'];
            $source = 'faq';
            $model = 'faq-kb';
            $matchedFaq = $faq['question'];
        } else {
            $result = LlmService::reply($question, $history, [
                'intent' => $intent,
                'faq'    => $faq,
            ]);
            $reply = $result['content'];
            $source = $result['source'];
            $model = $result['model'];
            $matchedFaq = $faq['question'] ?? null;
        }

        // 8. 持久化助手回复 + 更新上下文
        self::saveMessage($sessionId, 'assistant', $reply, $intent['intent'], $source);
        self::appendContext($sessionId, 'assistant', $reply);
        Store::execute("UPDATE cs_conversation SET last_intent=?, updated_at=NOW() WHERE session_id=?", [$intent['intent'], $sessionId]);

        // 9. 统计计数（Redis）
        Kv::incr('stat:messages');
        Kv::incr('stat:intent:' . $intent['intent']);
        Kv::incr('stat:source:' . $source);

        return [
            'reply'        => $reply,
            'intent'       => $intent['intent'],
            'intent_label' => $intent['label'],
            'confidence'   => $intent['confidence'],
            'source'       => $source,
            'model'        => $model,
            'matched_faq'  => $matchedFaq,
            'transferred'  => false,
        ];
    }

    /** 转人工判定 */
    private static function decideHuman(string $question, array $intent, array $history): ?string
    {
        if ($intent['want_human']) {
            return '用户主动要求转人工';
        }
        // 连续负面/无解决：最近用户消息里反复表达不满
        $negWords = ['没用', '没解决', '不对', '答非所问', '听不懂', '垃圾', '没帮助', '还是不行'];
        foreach ($negWords as $w) {
            if (mb_strpos($question, $w) !== false) {
                return '用户表达问题未解决';
            }
        }
        // 连续多轮低置信度且仍为 other
        if ($intent['intent'] === 'other' && count($history) >= 4) {
            return '多轮对话未能识别有效意图';
        }
        return null;
    }

    /** 创建人工工单 */
    private static function transferToHuman(string $sessionId, string $question, string $intent, string $reason): array
    {
        $ticketNo = 'TK' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        Store::insert(
            "INSERT INTO cs_ticket (ticket_no, session_id, user_question, reason, intent, status) VALUES (?,?,?,?,?,0)",
            [$ticketNo, $sessionId, $question, $reason, $intent]
        );
        Store::execute("UPDATE cs_conversation SET status=2, transferred=1, updated_at=NOW() WHERE session_id=?", [$sessionId]);
        Kv::incr('stat:transferred');
        return ['ticket_no' => $ticketNo];
    }

    /** 确保会话存在 */
    private static function ensureConversation(string $sessionId, array $extra): void
    {
        $exists = Store::scalar("SELECT COUNT(*) FROM cs_conversation WHERE session_id=?", [$sessionId]);
        if (!$exists) {
            Store::execute(
                "INSERT INTO cs_conversation (session_id, user_id, user_name, status, last_intent) VALUES (?,?,?,1,'other')",
                [$sessionId, $extra['user_id'] ?? '', $extra['user_name'] ?? '访客']
            );
            Kv::incr('stat:sessions');
        }
    }

    /** 保存消息 */
    private static function saveMessage(string $sessionId, string $role, string $content, string $intent, string $source): void
    {
        Store::execute(
            "INSERT INTO cs_message (session_id, role, content, intent, source) VALUES (?,?,?,?,?)",
            [$sessionId, $role, $content, $intent, $source]
        );
    }

    /** Redis 上下文 key */
    private static function ctxKey(string $sessionId): string
    {
        return 'ctx:' . $sessionId;
    }

    public static function getContext(string $sessionId): array
    {
        $v = Kv::get(self::ctxKey($sessionId));
        return is_array($v) ? $v : [];
    }

    private static function appendContext(string $sessionId, string $role, string $content): void
    {
        $ctx = self::getContext($sessionId);
        $ctx[] = ['role' => $role, 'content' => $content];
        if (count($ctx) > 20) {
            $ctx = array_slice($ctx, -20);
        }
        Kv::set(self::ctxKey($sessionId), $ctx, Config::contextTtl());
    }

    /** 获取某会话的消息历史（MySQL 持久化） */
    public static function messages(string $sessionId): array
    {
        return Store::query(
            "SELECT id, role, content, intent, source, created_at FROM cs_message WHERE session_id=? ORDER BY id ASC",
            [$sessionId]
        );
    }

    /**
     * 提交满意度评价
     * 评价仅作为打分动作，不改变会话生命周期：会话保持进行中、上下文保留，用户可继续聊天与回看记录。
     * 同一会话重复评价采用更新，避免统计虚高。
     */
    public static function rate(string $sessionId, int $rating, string $comment = ''): array
    {
        $rating = max(1, min(5, $rating));
        $exists = Store::scalar("SELECT COUNT(*) FROM cs_satisfaction WHERE session_id=?", [$sessionId]);
        if ($exists) {
            Store::execute(
                "UPDATE cs_satisfaction SET rating=?, comment=?, created_at=NOW() WHERE session_id=?",
                [$rating, mb_substr($comment, 0, 500), $sessionId]
            );
        } else {
            Store::execute(
                "INSERT INTO cs_satisfaction (session_id, rating, comment) VALUES (?,?,?)",
                [$sessionId, $rating, mb_substr($comment, 0, 500)]
            );
            Kv::incr('stat:ratings');
        }
        // 不再关闭会话、不清除上下文，保证评价后可继续聊天与翻看记录
        return ['ok' => true, 'rating' => $rating];
    }

    /** 新建会话 */
    public static function newSession(array $extra = []): array
    {
        $sessionId = 'S' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        self::ensureConversation($sessionId, $extra);
        return ['session_id' => $sessionId];
    }

    /**
     * 按 session_id 列表查询会话摘要（供前端"历史记录"翻看）
     * 只返回确实存在的会话，避免伪造 id 干扰。
     */
    public static function sessionsByIds(array $sessionIds): array
    {
        $sessionIds = array_values(array_filter(array_map('strval', $sessionIds)));
        if (empty($sessionIds)) return [];
        $sessionIds = array_slice($sessionIds, 0, 100);
        $in = implode(',', array_fill(0, count($sessionIds), '?'));
        $rows = Store::query(
            "SELECT c.session_id, c.status, c.last_intent, c.transferred, c.created_at, c.updated_at,
                    (SELECT COUNT(*) FROM cs_message m WHERE m.session_id=c.session_id) AS msg_count,
                    (SELECT content FROM cs_message m WHERE m.session_id=c.session_id AND m.role='user' ORDER BY m.id ASC LIMIT 1) AS first_question,
                    (SELECT rating FROM cs_satisfaction s WHERE s.session_id=c.session_id ORDER BY s.id DESC LIMIT 1) AS rating
             FROM cs_conversation c WHERE c.session_id IN ($in)
             ORDER BY c.updated_at DESC",
            $sessionIds
        );
        foreach ($rows as &$r) {
            $r['status_text']  = [1=>'进行中', 2=>'已转人工', 3=>'已结束'][(int)$r['status']] ?? '未知';
            $r['intent_text']  = IntentService::label($r['last_intent'] ?? 'other');
        }
        return $rows;
    }
}
