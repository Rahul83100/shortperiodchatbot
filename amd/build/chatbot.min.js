define(['jquery'], function ($) {
    return {
        init: function (courseId, canUpload) {
            // Use provided courseId, default to 0
            courseId = courseId || 0;
            canUpload = canUpload || false;
            console.log('Chatbot DEBUG: courseId=', courseId, 'canUpload=', canUpload, 'typeof canUpload=', typeof canUpload);

            // 1. Create and Inject HTML if not exists
            if (document.getElementById('chatbot-container')) {
                return; // Already injected
            }

            const uploadHtml = canUpload ? '<label for="chatbot-upload-file" id="chatbot-upload-btn" title="Upload Course Material">📁</label><input type="file" id="chatbot-upload-file" style="display:none" accept=".txt">' : '';

            const html = `
                <button id="chatbot-open-btn">🤖</button>
                <div id="chatbot-container">
                    <div id="chatbot-header">
                        <span>AI Study Assistant</span>
                        <span id="chatbot-close">×</span>
                    </div>
                    <div id="chatbot-messages"></div>
                    <div id="chatbot-input-container">
                        ${uploadHtml}
                        <textarea id="chatbot-input" placeholder="Ask your question…"></textarea>
                        <button id="chatbot-send">Send</button>
                    </div>
                </div>
            `;

            // Append to body
            const div = document.createElement('div');
            div.innerHTML = html;
            document.body.appendChild(div);

            // 2. DOM Elements
            const openBtn = document.getElementById("chatbot-open-btn");
            const chatBox = document.getElementById("chatbot-container");
            const closeBtn = document.getElementById("chatbot-close");
            const sendBtn = document.getElementById("chatbot-send");
            const input = document.getElementById("chatbot-input");
            const messages = document.getElementById("chatbot-messages");
            const uploadInput = document.getElementById("chatbot-upload-file");

            // 3. Event Listeners
            openBtn.onclick = () => chatBox.style.display = "flex";
            closeBtn.onclick = () => chatBox.style.display = "none";

            // Helper to add message
            function addMessage(text, sender) {
                const msg = document.createElement("div");
                msg.className = sender === "user" ? "user-msg" : "bot-msg";
                msg.innerHTML = text.replace(/\n/g, "<br>");
                messages.appendChild(msg);
                messages.scrollTop = messages.scrollHeight;
            }

            // Upload Logic
            if (uploadInput) {
                uploadInput.onchange = async () => {
                    const file = uploadInput.files[0];
                    if (!file) return;

                    if (!confirm(`Upload "${file.name}" to train the bot for this course?`)) {
                        uploadInput.value = ""; // Reset
                        return;
                    }

                    addMessage(`Uploading ${file.name}...`, "user");

                    const formData = new FormData();
                    formData.append("index_id", courseId);
                    formData.append("file", file);

                    try {
                        const res = await fetch("http://127.0.0.1:8001/api/upload", {
                            method: "POST",
                            body: formData
                        });

                        if (!res.ok) throw new Error("Upload failed");

                        const data = await res.json();
                        addMessage("✅ Context updated. You can now ask questions.", "bot");
                    } catch (e) {
                        console.error(e);
                        addMessage("❌ Error uploading file.", "bot");
                    }

                    uploadInput.value = ""; // Reset
                };
            }

            // Send Logic
            const sendMessage = async () => {
                const q = input.value.trim();
                if (!q) return;

                addMessage(q, "user");
                input.value = "";

                const loadingMsg = document.createElement("div");
                loadingMsg.className = "bot-msg";
                loadingMsg.innerText = "Typing...";
                messages.appendChild(loadingMsg);
                messages.scrollTop = messages.scrollHeight;

                try {
                    // Call Local Backend
                    const res = await fetch("http://127.0.0.1:8001/api/chat", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            question: q,
                            index_id: courseId // Dynamic Course ID
                        })
                    });

                    if (!res.ok) throw new Error("Server error");

                    const data = await res.json();

                    loadingMsg.remove();
                    addMessage(data.answer || "No response received.", "bot");

                } catch (e) {
                    console.error(e);
                    loadingMsg.remove();
                    addMessage("❌ Error connecting to bot backend. Is it running on port 8000?", "bot");
                }
            };

            sendBtn.onclick = sendMessage;

            // Allow Enter key to send (prevent default new line)
            input.onkeydown = (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            };
        }
    };
});
