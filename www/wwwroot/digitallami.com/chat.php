<?php
/**
 * chat.php – Chat launcher / redirect page
 */
$title = 'Chat';
require_once __DIR__ . '/includes/user_header.php';
?>

<style>
.ms-chat-card {
    background: var(--ms-white);
    border-radius: 16px;
    box-shadow: var(--ms-shadow);
    padding: 48px 32px;
    text-align: center;
    max-width: 520px;
    margin: 40px auto;
}
.ms-chat-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, var(--ms-primary) 0%, var(--ms-primary-dark) 100%);
    color: #fff; font-size: 2rem;
    display: inline-flex; align-items: center; justify-content: center;
    margin-bottom: 20px;
}
.ms-chat-card h2 { font-weight: 700; font-size: 1.5rem; margin-bottom: 10px; }
.ms-chat-card p { color: var(--ms-text-muted); font-size: 0.95rem; margin-bottom: 24px; }
.ms-btn-chat {
    background: var(--ms-primary); color: #fff; border: none;
    padding: 12px 32px; border-radius: 10px; font-size: 1rem;
    font-weight: 600; text-decoration: none; display: inline-flex;
    align-items: center; gap: 8px; transition: background 0.2s;
}
.ms-btn-chat:hover { background: var(--ms-primary-dark); color: #fff; }
.ms-chat-embed { width: 100%; height: 600px; border: none; border-radius: 12px; box-shadow: var(--ms-shadow); margin-top: 24px; }
</style>

<div class="ms-chat-card">
    <div class="ms-chat-icon"><i class="fas fa-comments"></i></div>
    <h2>Chat with Matches</h2>
    <p>Connect and chat with your matched profiles in real time. Start a conversation to know them better!</p>
    <a href="/chat/index.html" target="_blank" rel="noopener" class="ms-btn-chat">
        <i class="fas fa-external-link-alt"></i> Open Chat
    </a>
</div>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
