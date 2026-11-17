<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * 管理后台数据服务
 */
class AdminService
{
    /** 校验管理后台账号密码（兼容 password_hash 与 .env 明文默认值） */
    public static function verifyCredential(string $user, string $pass): bool
    {
        $adm = Config::admin();
        if (!hash_equals($adm['user'], $user)) {
            return false;
        }
        $stored = $adm['pass'];
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2')) {
            return password_verify($pass, $stored);
        }
        return hash_equals($stored, $pass);
    }

    /** 总览统计 */
    public static function overview(): array
    {
        $totalSessions = (int) Store::scalar("SELECT COUNT(*) FROM cs_conversation");
        $totalMessages = (int) Store::scalar("SELECT COUNT(*) FROM cs_message");
        $transferred   = (int) Store::scalar("SELECT COUNT(*) FROM cs_conversation WHERE transferred=1");
        $pendingTickets= (int) Store::scalar("SELECT COUNT(*) FROM cs_ticket WHERE status=0");
        $ratingRow     = Store::one("SELECT COUNT(*) AS cnt, AVG(rating) AS avg FROM cs_satisfaction");
        $ratingCount   = (int) ($ratingRow['cnt'] ?? 0);
        $avgRating     = $ratingCount > 0 ? round((float)$ratingRow['avg'], 2) : 0;
        $satisfied     = (int) Store::scalar("SELECT COUNT(*) FROM cs_satisfaction WHERE rating>=4");
        $satisfactionRate = $ratingCount > 0 ? round($satisfied / $ratingCount * 100, 1) : 0;
        $transferRate  = $totalSessions > 0 ? round($transferred / $totalSessions * 100, 1) : 0;

        return [
            'total_sessions'     => $totalSessions,
            'total_messages'     => $totalMessages,
            'transferred'        => $transferred,
            'transfer_rate'      => $transferRate,
            'pending_tickets'    => $pendingTickets,
            'rating_count'       => $ratingCount,
            'avg_rating'         => $avgRating,
            'satisfaction_rate'  => $satisfactionRate,
        ];
    }

    /** 意图分布 */
    public static function intentDistribution(): array
    {
        $rows = Store::query("SELECT last_intent AS intent, COUNT(*) AS cnt FROM cs_conversation GROUP BY last_intent");
        $result = [];
        foreach ($rows as $r) {
            $result[IntentService::label($r['intent'])] = (int)$r['cnt'];
        }
        return $result;
    }

    /** 满意度分布(1-5星) */
    public static function ratingDistribution(): array
    {
        $rows = Store::query("SELECT rating, COUNT(*) AS cnt FROM cs_satisfaction GROUP BY rating ORDER BY rating");
        $map = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
        foreach ($rows as $r) {
            $map[(int)$r['rating']] = (int)$r['cnt'];
        }
        return $map;
    }

    /** 消息来源分布 faq/llm/agent */
    public static function sourceDistribution(): array
    {
        $rows = Store::query("SELECT source, COUNT(*) AS cnt FROM cs_message WHERE role='assistant' GROUP BY source");
        $map = [];
        foreach ($rows as $r) {
            $map[$r['source']] = (int)$r['cnt'];
        }
        return $map;
    }

    /** 近 N 天会话趋势 */
    public static function dailyTrend(int $days = 7): array
    {
        $rows = Store::query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM cs_conversation
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC",
            [$days - 1]
        );
        $map = [];
        foreach ($rows as $r) { $map[$r['d']] = (int)$r['cnt']; }
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['date' => $d, 'count' => $map[$d] ?? 0];
        }
        return $result;
    }

    /** 对话记录列表（分页 + 筛选） */
    public static function conversations(int $page = 1, int $size = 15, string $status = '', string $keyword = ''): array
    {
        $offset = ($page - 1) * $size;
        $where = ['1=1'];
        $params = [];
        if ($status !== '') { $where[] = "c.status=?"; $params[] = (int)$status; }
        if ($keyword !== '') {
            $where[] = "(c.session_id LIKE ? OR c.user_name LIKE ?)";
            $params[] = "%$keyword%"; $params[] = "%$keyword%";
        }
        $w = implode(' AND ', $where);

        $total = (int) Store::scalar("SELECT COUNT(*) FROM cs_conversation c WHERE $w", $params);
        $rows = Store::query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM cs_message m WHERE m.session_id=c.session_id) AS msg_count,
                    (SELECT content FROM cs_message m WHERE m.session_id=c.session_id AND m.role='user' ORDER BY m.id ASC LIMIT 1) AS first_question,
                    (SELECT rating FROM cs_satisfaction s WHERE s.session_id=c.session_id ORDER BY s.id DESC LIMIT 1) AS rating
             FROM cs_conversation c WHERE $w
             ORDER BY c.created_at DESC LIMIT $size OFFSET $offset",
            $params
        );
        foreach ($rows as &$r) {
            $r['status_text'] = [1=>'进行中', 2=>'已转人工', 3=>'已结束'][(int)$r['status']] ?? '未知';
            $r['intent_text'] = IntentService::label($r['last_intent'] ?? 'other');
        }
        return ['total' => $total, 'page' => $page, 'size' => $size, 'list' => $rows];
    }

    /** 会话详情（消息列表 + 工单） */
    public static function conversationDetail(string $sessionId): array
    {
        $conv = Store::one("SELECT * FROM cs_conversation WHERE session_id=?", [$sessionId]);
        if (!$conv) return [];
        $messages = ChatService::messages($sessionId);
        $tickets = Store::query("SELECT * FROM cs_ticket WHERE session_id=? ORDER BY id DESC", [$sessionId]);
        return ['conversation' => $conv, 'messages' => $messages, 'tickets' => $tickets];
    }

    /** 工单列表 */
    public static function tickets(int $page = 1, int $size = 15, string $status = ''): array
    {
        $offset = ($page - 1) * $size;
        $where = $status !== '' ? "WHERE status=" . (int)$status : '';
        $total = (int) Store::scalar("SELECT COUNT(*) FROM cs_ticket $where");
        $rows = Store::query("SELECT * FROM cs_ticket $where ORDER BY created_at DESC LIMIT $size OFFSET $offset");
        foreach ($rows as &$r) {
            $r['status_text'] = [0=>'待处理', 1=>'处理中', 2=>'已完成'][(int)$r['status']] ?? '未知';
            $r['intent_text'] = IntentService::label($r['intent'] ?? 'other');
        }
        return ['total' => $total, 'page' => $page, 'size' => $size, 'list' => $rows];
    }

    /** 处理/回复工单 */
    public static function replyTicket(int $id, string $reply, string $assignee = '客服'): bool
    {
        $affected = Store::execute(
            "UPDATE cs_ticket SET reply=?, status=2, assignee=?, updated_at=NOW() WHERE id=?",
            [$reply, $assignee, $id]
        );
        if ($affected < 1) {
            return false;
        }
        // 将人工回复写入会话
        $ticket = Store::one("SELECT session_id, user_question FROM cs_ticket WHERE id=?", [$id]);
        if ($ticket) {
            Store::execute(
                "INSERT INTO cs_message (session_id, role, content, intent, source) VALUES (?,'agent',?,'other','agent')",
                [$ticket['session_id'], $reply]
            );
        }
        return true;
    }

    /** FAQ 列表 */
    public static function faqs(): array
    {
        return Store::query("SELECT * FROM cs_faq ORDER BY id ASC");
    }
}
