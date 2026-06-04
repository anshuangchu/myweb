<!-- AI 对话助手浮窗 -->
<div id="aiChatWidget" class="ai-chat-widget">
    <button id="aiChatToggle" class="ai-chat-toggle" onclick="toggleChat()" title="AI 助手">
        <span id="chatToggleIcon">💬</span>
    </button>

    <div id="aiChatPanel" class="ai-chat-panel" style="display:none">
        <div class="ai-chat-header">
            <span>🤖 AI 助手</span>
            <button class="ai-chat-close" onclick="toggleChat()">✕</button>
        </div>
        <div class="ai-chat-body" id="aiChatBody">
            <div class="ai-chat-msg ai-chat-msg-bot">
                <div class="ai-chat-msg-content">你好！我是基于本站内容的 AI 助手，可以回答关于文章、资料等方面的问题。请问有什么可以帮助你的？</div>
            </div>
        </div>
        <div id="aiChatLoading" class="ai-chat-loading" style="display:none">
            <div class="ai-chat-dots"><span></span><span></span><span></span></div>
            <span>AI 思考中…</span>
        </div>
        <div class="ai-chat-input-wrap">
            <input type="text" id="aiChatInput" class="ai-chat-input" placeholder="输入问题…" onkeydown="if(event.key==='Enter')sendChat()">
            <button class="ai-chat-send" onclick="sendChat()">发送</button>
        </div>
        <div class="ai-chat-footer">
            🤖 基于 DeepSeek · 内容来自本站
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>initAiChatDrag());</script>
