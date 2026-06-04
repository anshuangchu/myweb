<?php
/**
 * MimoService — 小米 MiMo 模型服务类
 *
 * MiMo API 兼容 OpenAI Chat Completions 格式。
 * 使用前需先加载 mimo_config.php。
 */
class MimoService
{
    private string $apiKey;
    private string $defaultModel;
    private string $baseUrl;

    /** 可用模型列表 */
    public const MODELS = [
        'mimo-v2.5'      => '原生全模态(推荐)',
        'mimo-v2.5-pro'  => '最强旗舰',
        'mimo-v2-pro'    => '1M 上下文 旗舰文本',
        'mimo-v2-omni'   => '多模态(图文音视频)',
    ];

    public function __construct()
    {
        $configDir = __DIR__ . '/../../myweb-config';
        if (!file_exists($configDir . '/mimo_config.php')) {
            $configDir = __DIR__ . '/../myweb-config';
        }
        if (!file_exists($configDir . '/mimo_config.php')) {
            $configDir = __DIR__ . '/../../../myweb-config';
        }
        require_once $configDir . '/mimo_config.php';

        if (!defined('MIMO_API_KEY')) {
            throw new RuntimeException('MiMo API Key 未配置');
        }
        $this->apiKey        = MIMO_API_KEY;
        $this->defaultModel  = defined('MIMO_MODEL') ? MIMO_MODEL : 'mimo-v2.5';
        $this->baseUrl       = defined('MIMO_BASE_URL') ? MIMO_BASE_URL : 'https://api.xiaomimimo.com/v1';
    }

    /**
     * 基础对话方法（支持工具调用）
     * @param array $messages 消息数组
     * @param string $model 模型名称
     * @param int $maxTokens 最大输出 token
     * @param float $temperature 温度
     * @param array|null $tools 工具定义数组（可选）
     * @return array|string 不传工具时返回文本；传工具时返回完整 response 数组
     */
    public function chat(array $messages, string $model = '', int $maxTokens = 2048, float $temperature = 0.7, ?array $tools = null): array|string
    {
        $model = $model ?: $this->defaultModel;

        $bodyArr = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ];
        if ($tools !== null) {
            $bodyArr['tools'] = $tools;
        }

        $body = json_encode($bodyArr, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('MiMo API 请求失败: ' . $error);
        }
        $data = json_decode($response, true);
        if ($httpCode !== 200 || !$data) {
            $errMsg = $data['error']['message'] ?? ($data['error']['msg'] ?? "HTTP {$httpCode}");
            throw new RuntimeException('MiMo 服务错误: ' . $errMsg);
        }

        // 如果传了 tools，返回完整响应以便处理 tool_calls
        if ($tools !== null) {
            return $data;
        }
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    /** 获取默认模型 */
    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }
}
