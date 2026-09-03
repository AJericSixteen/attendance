<div class="chatbot-widget" id="chatbotWidget">
  <button type="button" class="chatbot-toggle" id="chatbotToggle" aria-label="Open help chat">
    <i class="bi bi-chat-dots-fill"></i>
  </button>

  <div class="chatbot-panel d-none" id="chatbotPanel">
    <div class="chatbot-header">
      <div>
        <div class="chatbot-title">Help Assistant</div>
        <div class="chatbot-subtitle">Having an issue? Ask here.</div>
      </div>
      <button type="button" class="chatbot-close" id="chatbotClose" aria-label="Close chat">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="chatbot-messages" id="chatbotMessages">
      <div class="chatbot-msg chatbot-msg--bot">Hi! I'm here to help if you run into any issues with the attendance system. What's going on?</div>
    </div>
    <form class="chatbot-input-row" id="chatbotForm">
      <input type="text" id="chatbotInput" placeholder="Type your question..." autocomplete="off" maxlength="2000" required>
      <button type="submit" class="chatbot-send" aria-label="Send">
        <i class="bi bi-send-fill"></i>
      </button>
    </form>
  </div>
</div>
