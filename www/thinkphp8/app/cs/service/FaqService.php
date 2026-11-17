<?php
declare(strict_types=1);

namespace app\cs\service;

/**
 * FAQ 知识库检索服务
 * - 全量 FAQ 缓存在 Redis（5分钟）
 * - 基于关键词命中 + 字符二元组相似度打分
 */
class FaqService
{
    private const CACHE_KEY = 'faq:all';
    private const CACHE_TTL = 300;

    /** 获取全部启用 FAQ（Redis 缓存） */
    public static function all(): array
    {
        $cached = Kv::get(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }
        $rows = Store::query("SELECT id, question, answer, keywords, category, intent, hit_count FROM cs_faq WHERE status=1");
        if (!empty($rows)) {
            Kv::set(self::CACHE_KEY, $rows, self::CACHE_TTL);
        }
        return $rows;
    }

    /** 清空 FAQ 缓存 */
    public static function clearCache(): void
    {
        Kv::del(self::CACHE_KEY);
    }

    /**
     * 检索最相关的 FAQ
     * @return array|null {id, question, answer, intent, score}
     */
    public static function search(string $question, string $intent = ''): ?array
    {
        $faqs = self::all();
        if (empty($faqs)) return null;

        $q = self::normalize($question);
        $qBigrams = self::bigrams($q);
        $best = null;
        $bestScore = 0.0;

        foreach ($faqs as $faq) {
            $score = 0.0;

            // 1) 关键词命中（权重高）
            $keywords = array_filter(array_map('trim', explode(',', $faq['keywords'])));
            foreach ($keywords as $kw) {
                $kwN = self::normalize($kw);
                if ($kwN !== '' && mb_strpos($q, $kwN) !== false) {
                    $score += 3.0;
                }
            }

            // 2) 问题标题二元组相似度
            $fb = self::bigrams(self::normalize($faq['question']));
            if (!empty($qBigrams) && !empty($fb)) {
                $inter = count(array_intersect($qBigrams, $fb));
                $union = count(array_unique(array_merge($qBigrams, $fb)));
                if ($union > 0) {
                    $score += 2.0 * ($inter / $union);
                }
            }

            // 3) 意图一致加权
            if ($intent !== '' && ($faq['intent'] ?? '') === $intent) {
                $score += 0.6;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        if ($best === null || $bestScore < 2.0) {
            return null;
        }

        // 命中次数 +1：hit_count 不参与检索，无需因此清空全量缓存
        Store::execute("UPDATE cs_faq SET hit_count=hit_count+1 WHERE id=?", [(int)$best['id']]);

        return [
            'id'       => $best['id'],
            'question' => $best['question'],
            'answer'   => $best['answer'],
            'intent'   => $best['intent'],
            'category' => $best['category'],
            'score'    => round($bestScore, 2),
        ];
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[\s，。！？?！,、；;：:]/u', '', $s) ?? $s;
        return $s;
    }

    /** 中文/字符串二元组 */
    private static function bigrams(string $s): array
    {
        $len = mb_strlen($s);
        $grams = [];
        for ($i = 0; $i < $len - 1; $i++) {
            $grams[] = mb_substr($s, $i, 2);
        }
        return array_values(array_unique($grams));
    }
}
