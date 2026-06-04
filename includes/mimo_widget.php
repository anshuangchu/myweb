<!-- 小米 MiMo 对话浮窗 -->
<div id="mimoWidget" class="mimo-widget" style="display:none">
    <div class="mimo-header">
        <span class="mimo-header-title">🤖 MiMo 对话</span>
        <div class="mimo-header-actions">
            <button class="mimo-btn-sm" onclick="mimoNewConversation()" title="新对话">＋ 新对话</button>
            <button class="mimo-btn-close" onclick="toggleMimo()" title="关闭">✕</button>
        </div>
    </div>

    <div class="mimo-body">
        <!-- 左侧：对话列表 -->
        <div class="mimo-conv-list" id="mimoConvList">
            <div class="mimo-conv-items" id="mimoConvItems"></div>
        </div>

        <!-- 右侧：消息区域 -->
        <div class="mimo-main">
            <div class="mimo-messages" id="mimoMessages">
                <div class="mimo-welcome">
                    <div class="mimo-welcome-icon">🤖</div>
                    <h3>MiMo AI 助手</h3>
                    <p>基于小米 MiMo 模型，支持多轮对话</p>
                    <p class="mimo-welcome-hint">在下方输入问题开始对话</p>
                </div>
            </div>

            <div id="mimoLoading" class="mimo-loading" style="display:none">
                <div class="mimo-dots"><span></span><span></span><span></span></div>
                <span>MiMo 思考中…</span>
            </div>

            <div class="mimo-input-wrap">
                <input type="text" id="mimoInput" class="mimo-input" placeholder="输入问题…" onkeydown="if(event.key==='Enter')mimoSend()">
                <button class="mimo-send" onclick="mimoSend()">发送</button>
            </div>
        </div>
    </div>

    <div class="mimo-footer">
        <span class="mimo-model-label">模型:</span>
        <select id="mimoModelSelect" class="mimo-model-select">
            <option value="mimo-v2.5">mimo-v2.5 (原生全模态 推荐)</option>
            <option value="mimo-v2.5-pro">mimo-v2.5-pro (最强旗舰)</option>
            <option value="mimo-v2-pro">mimo-v2-pro (1M 上下文)</option>
            <option value="mimo-v2-omni">mimo-v2-omni (多模态)</option>
        </select>
        <span class="mimo-footer-powered">小米 MiMo</span>
    </div>
</div>
