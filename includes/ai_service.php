<?php
/**
 * AiService — 统一 AI 服务类
 *
 * 封装所有 DeepSeek API 调用，提供文章辅助、搜索增强、推荐等功能。
 * 使用前需先加载 ai_config.php（定义 DEEPSEEK_API_KEY / DEEPSEEK_MODEL / DEEPSEEK_BASE_URL）。
 */
class AiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        // 尝试定位 ai_config.php（从 web 根目录外加载）
        $configDir = __DIR__ . '/../../myweb-config';
        if (!file_exists($configDir . '/ai_config.php')) {
            $configDir = __DIR__ . '/../myweb-config';
        }
        // 如果还没找到，回溯一级（从 admin/ 内加载时）
        if (!file_exists($configDir . '/ai_config.php')) {
            $configDir = __DIR__ . '/../../../myweb-config';
        }
        require_once $configDir . '/ai_config.php';

        if (!defined('DEEPSEEK_API_KEY') || !DEEPSEEK_API_KEY) {
            throw new RuntimeException('AI API Key 未配置');
        }
        $this->apiKey  = DEEPSEEK_API_KEY;
        $this->model   = defined('DEEPSEEK_MODEL') ? DEEPSEEK_MODEL : 'deepseek-chat';
        $this->baseUrl = defined('DEEPSEEK_BASE_URL') ? DEEPSEEK_BASE_URL : 'https://api.deepseek.com';
    }

    /**
     * 基础对话方法
     * @param array $messages 消息数组 [['role'=>'system'|'user', 'content'=>'...'], ...]
     * @param int $maxTokens 最大输出 token
     * @param float $temperature 温度 (0-2)
     * @return string AI 返回文本
     */
    public function chat(array $messages, int $maxTokens = 1024, float $temperature = 0.7): string
    {
        $body = json_encode([
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->baseUrl . '/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('API 请求失败: ' . $error);
        }
        $data = json_decode($response, true);
        if ($httpCode !== 200 || !$data) {
            $errMsg = $data['error']['message'] ?? ($data['error']['msg'] ?? "HTTP {$httpCode}");
            throw new RuntimeException('AI 服务错误: ' . $errMsg);
        }
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    // ==================== 文章辅助 ====================

    /** 生成摘要 */
    public function summarize(string $content, string $title = ''): string
    {
        $full = $title ? "标题：{$title}\n\n正文：{$content}" : $content;
        return $this->chat([
            ['role' => 'system', 'content' => '你是专业的文章摘要生成助手，擅长提炼文章要点。'],
            ['role' => 'user',   'content' => "根据以下文章内容，生成一段简洁的摘要（50-100字），直接返回摘要文本：\n\n{$full}"],
        ]);
    }

    /** 润色全文 */
    public function polish(string $content): string
    {
        return $this->chat([
            ['role' => 'system', 'content' => '你是专业文字编辑，擅长润色中文文章。'],
            ['role' => 'user',   'content' => "优化以下内容，修正错别字和语法问题，改善语句通顺度，保持原意：\n\n{$content}"],
        ], 2048);
    }

    /** 续写内容 */
    public function expand(string $content, string $title = '', string $length = 'medium'): string
    {
        $lengthMap = ['short' => '50-100字', 'medium' => '100-200字', 'long' => '200-400字'];
        $desc = $lengthMap[$length] ?? '100-200字';
        $full = $title ? "标题：{$title}\n\n正文：{$content}" : $content;
        return $this->chat([
            ['role' => 'system', 'content' => '你是文章续写专家，能保持文风一致地扩展内容。'],
            ['role' => 'user',   'content' => "续写一段相关文字（{$desc}），保持风格一致：\n\n{$full}"],
        ]);
    }

    /** 全文生成：根据主题生成文章 */
    public function generateArticle(string $topic, string $style = 'general'): array
    {
        $styleMap = [
            'general' => '通用风格，适合大众阅读',
            'formal'  => '正式专业风格',
            'casual'  => '轻松口语化风格',
            'tech'    => '技术教程风格',
        ];
        $styleDesc = $styleMap[$style] ?? '通用风格';

        $result = $this->chat([
            ['role' => 'system', 'content' => "你是专业的内容创作者，擅长撰写{$styleDesc}的文章。请严格按照指定 JSON 格式返回，不要添加 markdown 代码块标记。"],
            ['role' => 'user',   'content' => <<<PROMPT
根据主题「{$topic}」生成一篇完整文章。
风格要求：{$styleDesc}

请严格按照以下 JSON 格式返回，不要包含任何额外文字：
{"title":"文章标题","summary":"文章摘要（50-100字）","content":"文章正文（支持HTML标签，不少于500字）"}
PROMPT
            ],
        ], 2048, 0.8);

        // 尝试提取 JSON（AI 可能返回 markdown 包裹）
        $result = trim($result);
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $result, $m)) {
            $result = $m[1];
        }
        $data = json_decode($result, true);
        if (!$data || !isset($data['title'], $data['content'])) {
            // 解析失败，直接作为内容返回
            return ['title' => $topic, 'summary' => '', 'content' => $result];
        }
        return [
            'title'   => $data['title'] ?? $topic,
            'summary' => $data['summary'] ?? '',
            'content' => $data['content'] ?? '',
        ];
    }

    /** 标题优化：推荐 3-5 个标题 */
    public function suggestTitle(string $content): string
    {
        return $this->chat([
            ['role' => 'system', 'content' => '你是标题创作专家，擅长提炼吸引人的文章标题。'],
            ['role' => 'user',   'content' => "根据以下文章内容，推荐3-5个吸引人的标题，每行一个，直接返回标题列表：\n\n{$content}"],
        ]);
    }

    // ==================== 搜索增强 ====================

    /** 搜索关键词扩展：分析用户自然语言查询，返回扩展后的关键词 */
    public function expandSearchQuery(string $query): array
    {
        $result = $this->chat([
            ['role' => 'system', 'content' => '你擅长理解用户搜索意图，提取核心关键词。'],
            ['role' => 'user',   'content' => "用户搜索「{$query}」。请分析搜索意图，返回3-5个相关的搜索关键词，每行一个。只返回关键词，不要序号和额外说明。"],
        ], 512, 0.3);

        $keywords = explode("\n", trim($result));
        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords, fn($v) => !empty($v));
        // 去重并限制数量
        $keywords = array_unique($keywords);
        $keywords = array_slice(array_values($keywords), 0, 5);

        // 原始查询也加入
        if (!in_array($query, $keywords)) {
            array_unshift($keywords, $query);
        }
        return $keywords;
    }

    /** AI 排序搜索结果 */
    public function rankResults(string $query, array $results): array
    {
        if (empty($results)) return $results;

        $items = [];
        foreach ($results as $i => $r) {
            $items[] = "【{$i}】{$r['title']} — {$r['summary']}";
        }
        $itemsStr = implode("\n", $items);

        $result = $this->chat([
            ['role' => 'system', 'content' => '你是搜索排序专家。根据用户查询，对搜索结果按相关性排序。返回排序后的序号，用逗号分隔。'],
            ['role' => 'user',   'content' => "用户查询：{$query}\n\n搜索结果：\n{$itemsStr}\n\n请按相关性从高到低排列序号（如 3,0,2,1,4）："],
        ], 256, 0.3);

        // 解析序号
        $indices = [];
        preg_match_all('/\d+/', $result, $matches);
        foreach ($matches[0] as $num) {
            $idx = (int)$num;
            if ($idx >= 0 && $idx < count($results) && !in_array($idx, $indices)) {
                $indices[] = $idx;
            }
        }
        // 补全缺失项
        foreach (array_keys($results) as $idx) {
            if (!in_array($idx, $indices)) $indices[] = $idx;
        }

        $sorted = [];
        foreach ($indices as $idx) {
            $sorted[] = $results[$idx];
        }
        return $sorted;
    }

    // ==================== 推荐 ====================

    /** AI 推荐文章排序 */
    public function recommendFromCandidates(string $articleTitle, string $articleSummary, array $candidates, int $limit = 5): array
    {
        if (empty($candidates)) return [];

        $items = [];
        foreach ($candidates as $i => $c) {
            $items[] = "【{$i}】{$c['title']} — {$c['summary']}";
        }
        $itemsStr = implode("\n", $items);

        $result = $this->chat([
            ['role' => 'system', 'content' => '你是内容推荐专家，擅长找出内容最相似的文章。'],
            ['role' => 'user',   'content' => "当前文章：{$articleTitle} — {$articleSummary}\n\n候选文章：\n{$itemsStr}\n\n请选出最相关的{$limit}篇，按相关性排序，返回序号（逗号分隔）。只返回序号，不要解释。"],
        ], 256, 0.3);

        $indices = [];
        preg_match_all('/\d+/', $result, $matches);
        foreach ($matches[0] as $num) {
            $idx = (int)$num;
            if ($idx >= 0 && $idx < count($candidates) && !in_array($idx, $indices)) {
                $indices[] = $idx;
            }
        }

        $sorted = [];
        foreach (array_slice($indices, 0, $limit) as $idx) {
            $sorted[] = $candidates[$idx];
        }
        return $sorted;
    }

    // ==================== 对话 ====================

    /** 基于上下文回答问题 */
    public function chatWithContext(string $question, string $context): string
    {
        return $this->chat([
            ['role' => 'system', 'content' => '你是一个网站智能助手。请基于提供的网站内容回答问题。如果内容不足以回答，诚实地告诉用户你不知道。请用中文回答，简洁准确。'],
            ['role' => 'user',   'content' => "网站内容：\n{$context}\n\n用户问题：{$question}"],
        ], 1024, 0.5);
    }
}
