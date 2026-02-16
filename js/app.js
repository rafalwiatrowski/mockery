/**
 * Mockery - AI Page Builder
 * Main Application JavaScript with Streaming & Patch Support
 */

class MockeryApp {
    constructor() {
        this.currentPage = null;
        this.currentVersion = null;
        this.pages = [];
        this.chatHistory = [];
        this.isGenerating = false;
        this.pollInterval = null;
        this.pollRate = 500;
        this.usePatching = true; // Enable fast patching mode

        this.init();
    }

    init() {
        this.cacheElements();
        this.bindEvents();
        this.loadPages();
        this.startPolling();
    }

    cacheElements() {
        this.pageSelector = document.getElementById('page-selector');
        this.chatPageSelector = document.getElementById('chat-page-selector');
        this.newPageBtn = document.getElementById('new-page-btn');
        this.emptyNewPageBtn = document.getElementById('empty-new-page-btn');
        this.deletePageBtn = document.getElementById('delete-page-btn');
        this.chatToggle = document.getElementById('chat-toggle');
        this.sendBtn = document.getElementById('send-btn');
        this.createPageBtn = document.getElementById('create-page-btn');
        this.cancelNewPage = document.getElementById('cancel-new-page');
        this.chatIcon = document.getElementById('chat-icon');
        this.closeIcon = document.getElementById('close-icon');
        this.chatPanel = document.getElementById('chat-panel');
        this.chatMessages = document.getElementById('chat-messages');
        this.chatInput = document.getElementById('chat-input');
        this.typingIndicator = document.getElementById('typing-indicator');
        this.previewFrame = document.getElementById('preview-frame');
        this.emptyState = document.getElementById('empty-state');
        this.newPageModal = document.getElementById('new-page-modal');
        this.newPageName = document.getElementById('new-page-name');
        this.versionText = document.getElementById('version-text');
        this.syncIndicator = document.getElementById('sync-indicator');
    }

    bindEvents() {
        this.chatToggle.addEventListener('click', () => this.toggleChat());

        this.pageSelector.addEventListener('change', (e) => this.selectPage(e.target.value));
        this.chatPageSelector.addEventListener('change', (e) => {
            this.pageSelector.value = e.target.value;
            this.selectPage(e.target.value);
        });

        this.newPageBtn.addEventListener('click', () => this.showNewPageModal());
        this.emptyNewPageBtn.addEventListener('click', () => this.showNewPageModal());
        this.deletePageBtn.addEventListener('click', () => this.deletePage());

        this.createPageBtn.addEventListener('click', () => this.createPage());
        this.cancelNewPage.addEventListener('click', () => this.hideNewPageModal());
        this.newPageModal.addEventListener('click', (e) => {
            if (e.target === this.newPageModal) this.hideNewPageModal();
        });

        this.chatInput.addEventListener('input', () => this.handleInputChange());
        this.chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        this.sendBtn.addEventListener('click', () => this.sendMessage());

        this.chatInput.addEventListener('input', () => {
            this.chatInput.style.height = 'auto';
            this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px';
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.chatPanel.classList.contains('flex')) {
                this.toggleChat();
            }
        });
    }

    // ==================== Page Management ====================

    async loadPages() {
        try {
            const response = await fetch('api/pages.php');
            const data = await response.json();

            if (data.success) {
                this.pages = data.pages;
                this.updatePageSelectors();

                if (this.pages.length > 0 && !this.currentPage) {
                    this.selectPage(this.pages[0].name);
                }
            }
        } catch (error) {
            console.error('Failed to load pages:', error);
        }
    }

    updatePageSelectors() {
        const options = this.pages.map(p =>
            `<option value="${p.name}">${p.name}</option>`
        ).join('');

        const defaultOption = '<option value="">Wybierz stronę...</option>';
        this.pageSelector.innerHTML = defaultOption + options;
        this.chatPageSelector.innerHTML = defaultOption + options;

        if (this.currentPage) {
            this.pageSelector.value = this.currentPage;
            this.chatPageSelector.value = this.currentPage;
        }
    }

    selectPage(pageName) {
        if (!pageName) {
            this.currentPage = null;
            this.showEmptyState();
            this.deletePageBtn.classList.add('hidden');
            return;
        }

        this.currentPage = pageName;
        this.pageSelector.value = pageName;
        this.chatPageSelector.value = pageName;

        this.hideEmptyState();
        this.deletePageBtn.classList.remove('hidden');
        this.loadPageContent();
    }

    async loadPageContent() {
        if (!this.currentPage) return;

        try {
            const timestamp = Date.now();
            const url = `pages/${this.currentPage}.html?t=${timestamp}`;

            const response = await fetch(url);
            if (response.ok) {
                const html = await response.text();
                this.previewFrame.srcdoc = html;
                this.previewFrame.classList.remove('hidden');

                const version = response.headers.get('Last-Modified') || new Date().toISOString();
                this.updateVersion(version);
            }
        } catch (error) {
            console.error('Failed to load page:', error);
        }
    }

    showEmptyState() {
        this.emptyState.classList.remove('hidden');
        this.previewFrame.classList.add('hidden');
        this.versionText.textContent = '';
        this.syncIndicator.classList.add('hidden');
    }

    hideEmptyState() {
        this.emptyState.classList.add('hidden');
        this.previewFrame.classList.remove('hidden');
    }

    showNewPageModal() {
        this.newPageModal.classList.remove('hidden');
        this.newPageModal.classList.add('flex');
        this.newPageName.value = '';
        this.newPageName.focus();
    }

    hideNewPageModal() {
        this.newPageModal.classList.add('hidden');
        this.newPageModal.classList.remove('flex');
    }

    async createPage() {
        const name = this.newPageName.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-');

        if (!name) {
            alert('Podaj nazwę strony');
            return;
        }

        try {
            const response = await fetch('api/pages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create', name })
            });

            const data = await response.json();

            if (data.success) {
                this.hideNewPageModal();
                await this.loadPages();
                this.selectPage(name);
                this.addMessage('assistant', `Strona "${name}" została utworzona! Opisz mi jak ma wyglądać.`);
            } else {
                alert(data.error || 'Nie udało się utworzyć strony');
            }
        } catch (error) {
            console.error('Failed to create page:', error);
            alert('Błąd podczas tworzenia strony');
        }
    }

    async deletePage() {
        if (!this.currentPage) return;

        if (!confirm(`Czy na pewno chcesz usunąć stronę "${this.currentPage}"?`)) {
            return;
        }

        try {
            const response = await fetch('api/pages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', name: this.currentPage })
            });

            const data = await response.json();

            if (data.success) {
                this.currentPage = null;
                await this.loadPages();
                this.showEmptyState();
            } else {
                alert(data.error || 'Nie udało się usunąć strony');
            }
        } catch (error) {
            console.error('Failed to delete page:', error);
        }
    }

    // ==================== Chat ====================

    toggleChat() {
        const isOpen = this.chatPanel.classList.contains('flex');

        if (isOpen) {
            this.chatPanel.classList.add('hidden');
            this.chatPanel.classList.remove('flex');
            this.chatIcon.classList.remove('hidden');
            this.closeIcon.classList.add('hidden');
        } else {
            this.chatPanel.classList.remove('hidden');
            this.chatPanel.classList.add('flex');
            this.chatIcon.classList.add('hidden');
            this.closeIcon.classList.remove('hidden');
            this.chatInput.focus();
        }
    }

    handleInputChange() {
        const hasText = this.chatInput.value.trim().length > 0;
        const hasPage = !!this.currentPage;
        this.sendBtn.disabled = !hasText || !hasPage || this.isGenerating;
    }

    addMessage(role, content, isStreaming = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex gap-3 message-enter';

        if (role === 'user') {
            messageDiv.innerHTML = `
                <div class="flex-1 bg-fluent-primary-light rounded-lg p-3 text-sm text-fluent-text-primary ml-8">
                    ${this.escapeHtml(content)}
                </div>
                <div class="w-8 h-8 rounded-full bg-fluent-text-secondary flex-shrink-0 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="w-8 h-8 rounded-full bg-fluent-primary flex-shrink-0 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1 bg-fluent-bg-secondary rounded-lg p-3 text-sm text-fluent-text-primary ${isStreaming ? 'streaming-message' : ''}">
                    ${this.escapeHtml(content)}
                </div>
            `;
        }

        this.chatMessages.appendChild(messageDiv);
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;

        if (!isStreaming) {
            this.chatHistory.push({ role, content });
        }

        return messageDiv;
    }

    updateStreamingMessage(messageDiv, content) {
        const contentDiv = messageDiv.querySelector('.bg-fluent-bg-secondary');
        if (contentDiv) {
            contentDiv.innerHTML = this.escapeHtml(content);
        }
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showTypingIndicator() {
        this.typingIndicator.classList.remove('hidden');
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    hideTypingIndicator() {
        this.typingIndicator.classList.add('hidden');
    }

    async sendMessage() {
        const message = this.chatInput.value.trim();
        if (!message || !this.currentPage || this.isGenerating) return;

        this.addMessage('user', message);
        this.chatInput.value = '';
        this.chatInput.style.height = 'auto';
        this.handleInputChange();

        this.isGenerating = true;
        this.showTypingIndicator();
        this.sendBtn.disabled = true;

        if (this.usePatching) {
            await this.sendMessageWithStreaming(message);
        } else {
            await this.sendMessageLegacy(message);
        }
    }

    /**
     * Send message using streaming SSE with patch support
     */
    async sendMessageWithStreaming(message) {
        const statusDiv = this.addMessage('assistant', 'Analizuję...', true);
        let streamingContent = '';

        try {
            const response = await fetch('api/patch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page: this.currentPage,
                    message: message,
                    history: this.chatHistory.slice(-10)
                })
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop(); // Keep incomplete line in buffer

                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        const eventType = line.slice(7);
                        continue;
                    }
                    if (line.startsWith('data: ')) {
                        const data = JSON.parse(line.slice(6));
                        await this.handleSSEEvent(data, statusDiv);
                    }
                }
            }
        } catch (error) {
            console.error('Streaming error:', error);
            this.updateStreamingMessage(statusDiv, 'Przepraszam, wystąpił błąd. Spróbuj ponownie.');
        } finally {
            this.isGenerating = false;
            this.hideTypingIndicator();
            this.handleInputChange();
        }
    }

    /**
     * Handle SSE events from patch.php
     */
    async handleSSEEvent(data, statusDiv) {
        // Status events
        if (data.status) {
            this.updateStreamingMessage(statusDiv, data.message || data.status);
            return;
        }

        // Error events
        if (data.error) {
            this.updateStreamingMessage(statusDiv, `Błąd: ${data.error}`);
            return;
        }

        // Patch events - apply DOM changes
        if (data.patches) {
            await this.applyPatches(data.patches);
            const summary = data.summary || 'Zmiany wprowadzone!';
            this.updateStreamingMessage(statusDiv, summary);
            this.chatHistory.push({ role: 'assistant', content: summary });
            this.flashSyncIndicator();

            if (data.version) {
                this.currentVersion = data.version;
            }
            return;
        }

        // Full HTML update
        if (data.html) {
            this.previewFrame.srcdoc = data.html;
            this.updateStreamingMessage(statusDiv, 'Strona została zaktualizowana!');
            this.chatHistory.push({ role: 'assistant', content: 'Strona została zaktualizowana!' });
            this.flashSyncIndicator();

            if (data.version) {
                this.currentVersion = data.version;
            }
            return;
        }

        // Progress events (streaming chunks)
        if (data.chunk) {
            // Could show streaming text here if needed
            return;
        }

        // Done event
        if (data.success !== undefined) {
            return;
        }
    }

    /**
     * Apply patches to the iframe DOM
     */
    async applyPatches(patches) {
        const iframeDoc = this.previewFrame.contentDocument || this.previewFrame.contentWindow.document;

        for (const patch of patches) {
            try {
                const elements = iframeDoc.querySelectorAll(patch.selector);

                if (elements.length === 0) {
                    console.warn(`Selector not found: ${patch.selector}`);
                    continue;
                }

                for (const element of elements) {
                    switch (patch.action) {
                        case 'replace':
                            element.innerHTML = patch.content;
                            this.highlightElement(element);
                            break;

                        case 'insert':
                            const newEl = document.createElement('div');
                            newEl.innerHTML = patch.content;
                            const insertEl = newEl.firstElementChild || newEl;

                            switch (patch.position) {
                                case 'before':
                                    element.parentNode.insertBefore(insertEl, element);
                                    break;
                                case 'after':
                                    element.parentNode.insertBefore(insertEl, element.nextSibling);
                                    break;
                                case 'prepend':
                                    element.insertBefore(insertEl, element.firstChild);
                                    break;
                                case 'append':
                                default:
                                    element.appendChild(insertEl);
                                    break;
                            }
                            this.highlightElement(insertEl);
                            break;

                        case 'remove':
                            element.style.transition = 'opacity 0.3s, transform 0.3s';
                            element.style.opacity = '0';
                            element.style.transform = 'scale(0.9)';
                            setTimeout(() => element.remove(), 300);
                            break;

                        case 'setAttribute':
                            element.setAttribute(patch.attribute, patch.value);
                            this.highlightElement(element);
                            break;

                        case 'addClass':
                            element.classList.add(patch.class);
                            this.highlightElement(element);
                            break;

                        case 'removeClass':
                            element.classList.remove(patch.class);
                            this.highlightElement(element);
                            break;

                        case 'setStyle':
                            element.style[patch.attribute] = patch.value;
                            this.highlightElement(element);
                            break;
                    }
                }
            } catch (error) {
                console.error(`Failed to apply patch:`, patch, error);
            }
        }

        // Save the updated HTML back to server
        await this.saveCurrentHtml(iframeDoc);
    }

    /**
     * Highlight element after change (visual feedback)
     */
    highlightElement(element) {
        if (!element || !element.style) return;

        const originalTransition = element.style.transition;
        const originalBoxShadow = element.style.boxShadow;

        element.style.transition = 'box-shadow 0.3s ease-out';
        element.style.boxShadow = '0 0 0 3px rgba(0, 120, 212, 0.5)';

        setTimeout(() => {
            element.style.boxShadow = originalBoxShadow;
            setTimeout(() => {
                element.style.transition = originalTransition;
            }, 300);
        }, 500);
    }

    /**
     * Save current iframe HTML back to server
     */
    async saveCurrentHtml(iframeDoc) {
        const html = '<!DOCTYPE html>\n' + iframeDoc.documentElement.outerHTML;

        try {
            await fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page: this.currentPage,
                    html: html
                })
            });
        } catch (error) {
            console.error('Failed to save HTML:', error);
        }
    }

    /**
     * Legacy non-streaming message send
     */
    async sendMessageLegacy(message) {
        try {
            const response = await fetch('api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page: this.currentPage,
                    message: message,
                    history: this.chatHistory.slice(-10)
                })
            });

            const data = await response.json();

            if (data.success) {
                this.addMessage('assistant', data.message || 'Zmiany zostały wprowadzone!');
                await this.loadPageContent();
                this.flashSyncIndicator();
            } else {
                this.addMessage('assistant', `Błąd: ${data.error || 'Nie udało się wygenerować zmian'}`);
            }
        } catch (error) {
            console.error('Generate error:', error);
            this.addMessage('assistant', 'Przepraszam, wystąpił błąd połączenia. Spróbuj ponownie.');
        } finally {
            this.isGenerating = false;
            this.hideTypingIndicator();
            this.handleInputChange();
        }
    }

    // ==================== Version & Polling ====================

    updateVersion(version) {
        const date = new Date(version);
        if (!isNaN(date.getTime())) {
            this.versionText.textContent = `v${date.toLocaleTimeString('pl-PL')}`;
        }
        this.currentVersion = version;
    }

    flashSyncIndicator() {
        this.syncIndicator.classList.remove('hidden');
        setTimeout(() => {
            this.syncIndicator.classList.add('hidden');
        }, 2000);
    }

    startPolling() {
        this.pollInterval = setInterval(() => this.checkForUpdates(), this.pollRate);
    }

    async checkForUpdates() {
        if (!this.currentPage || this.isGenerating) return;

        try {
            const response = await fetch(`api/version.php?page=${this.currentPage}`);
            const data = await response.json();

            if (data.version && data.version !== this.currentVersion) {
                this.currentVersion = data.version;
                await this.loadPageContent();
                this.flashSyncIndicator();
            }
        } catch (error) {
            // Silent fail for polling
        }
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.mockeryApp = new MockeryApp();
});
