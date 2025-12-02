async function sendMessage() {
    const message = userInput.value.trim();
    if (!message) return;

    appendUserMessage(message);

    try {
        const response = await fetch("/medace/config/chatbot_integration.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ message })
        });

        if (!response.ok) {
            appendBotMessage("Server error: " + response.status);
            return;
        }

        const result = await response.json();

        if (result.error) {
            appendBotMessage("Error: " + result.error);
            return;
        }

        // ✔ FIXED: PHP returns { reply: "text" }
        const aiMessage = result.reply || "No response.";
        appendBotMessage(aiMessage);

    } catch (error) {
        appendBotMessage("Network error: " + error.message);
    }
}
