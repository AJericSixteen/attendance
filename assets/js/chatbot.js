(function () {
  const widget = document.getElementById('chatbotWidget');
  if (!widget) return;

  const toggleBtn = document.getElementById('chatbotToggle');
  const closeBtn = document.getElementById('chatbotClose');
  const panel = document.getElementById('chatbotPanel');
  const messagesEl = document.getElementById('chatbotMessages');
  const form = document.getElementById('chatbotForm');
  const input = document.getElementById('chatbotInput');

  const endpoint = window.DIFY_CHAT_ENDPOINT || 'includes/dify_chat.php';

  function appendMessage(text, who) {
    const msg = document.createElement('div');
    msg.className = 'chatbot-msg chatbot-msg--' + who;
    msg.textContent = text;
    messagesEl.appendChild(msg);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return msg;
  }

  function togglePanel(show) {
    panel.classList.toggle('d-none', !show);
    if (show) {
      input.focus();
    }
  }

  toggleBtn.addEventListener('click', () => togglePanel(panel.classList.contains('d-none')));
  closeBtn.addEventListener('click', () => togglePanel(false));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    appendMessage(text, 'user');
    input.value = '';
    input.disabled = true;

    const typingMsg = appendMessage('Typing...', 'bot');
    typingMsg.classList.add('chatbot-msg--typing');

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text }),
      });
      const data = await res.json();

      typingMsg.remove();

      if (!res.ok) {
        appendMessage(data.error || 'Something went wrong. Please try again.', 'bot');
        return;
      }

      appendMessage(data.answer || "Sorry, I don't have an answer for that.", 'bot');
    } catch (err) {
      typingMsg.remove();
      appendMessage('Could not reach the chat service. Check your connection and try again.', 'bot');
    } finally {
      input.disabled = false;
      input.focus();
    }
  });
})();
