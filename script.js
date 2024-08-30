// script.js
function sendMessage() {
    const messages = document.getElementById('messages');
    const input = document.getElementById('messageInput');
    
    // Create message container for user message
    const userMessageContainer = document.createElement('div');
    userMessageContainer.className = 'message user';

    // Create user message bubble
    const userMessageBubble = document.createElement('div');
    userMessageBubble.className = 'bubble';
    userMessageBubble.textContent = input.value;

    // Append user message to messages area
    userMessageContainer.appendChild(userMessageBubble);
    messages.appendChild(userMessageContainer);
    input.value = ''; // Clear input

    // Simulate loading for incoming messages
    setTimeout(() => {
        addIncomingMessage("Thanks for your message!"); // First response
        setTimeout(() => {
            addIncomingMessage("How can I assist you?"); // Second response
            setTimeout(() => {
                addIncomingMessage("Feel free to ask anything!"); // Third response
            }, 1000);
        }, 1000);
    }, 1000); // Simulate delay for user message
}

function addIncomingMessage(text) {
    const messages = document.getElementById('messages');

    // Create message container for incoming message
    const incomingMessageContainer = document.createElement('div');
    incomingMessageContainer.className = 'message';

    // Create incoming message bubble
    const incomingMessageBubble = document.createElement('div');
    incomingMessageBubble.className = 'bubble';
    incomingMessageBubble.textContent = text;

    // Create loader
    const loader = document.createElement('div');
    loader.className = 'loader';
    incomingMessageContainer.appendChild(loader);
    incomingMessageContainer.appendChild(incomingMessageBubble);

    // Append incoming message to messages area
    messages.appendChild(incomingMessageContainer);

    // Simulate loading delay
    setTimeout(() => {
        loader.remove(); // Remove loader
    }, 2000); // 2-second delay for incoming message
}
