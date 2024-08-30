// script.js
function sendMessage() {
    const messages = document.getElementById('messages');
    const input = document.getElementById('messageInput');
    
    // Create message container
    const messageContainer = document.createElement('div');
    messageContainer.className = 'message user';

    // Create message bubble
    const messageBubble = document.createElement('div');
    messageBubble.className = 'bubble';
    messageBubble.textContent = input.value;

    // Create loader
    const loader = document.createElement('div');
    loader.className = 'loader';
    messageContainer.appendChild(messageBubble);
    messageContainer.appendChild(loader);

    // Append message to messages area
    messages.appendChild(messageContainer);
    input.value = ''; // Clear input

    // Simulate message sending delay
    setTimeout(() => {
        loader.remove(); // Remove loader
    }, 2000); // 2-second delay
}
