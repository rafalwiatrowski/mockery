<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mockery - AI Page Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'fluent': ['"Segoe UI"', 'Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        fluent: {
                            primary: '#0078d4',
                            'primary-dark': '#106ebe',
                            'primary-light': '#deecf9',
                            'bg-primary': '#ffffff',
                            'bg-secondary': '#faf9f8',
                            'bg-tertiary': '#f3f2f1',
                            'border': '#edebe9',
                            'border-dark': '#8a8886',
                            'text-primary': '#323130',
                            'text-secondary': '#605e5c',
                            'text-disabled': '#a19f9d',
                            'success': '#107c10',
                            'warning': '#ffb900',
                            'error': '#d13438',
                            'purple': '#5c2d91',
                        }
                    },
                    boxShadow: {
                        'fluent-2': '0 0 2px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.14)',
                        'fluent-4': '0 0 2px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.14)',
                        'fluent-8': '0 0 2px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.14)',
                        'fluent-16': '0 0 2px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.14)',
                        'fluent-64': '0 0 2px rgba(0,0,0,0.12), 0 32px 64px rgba(0,0,0,0.14)',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.2s ease-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-dot': 'pulseDot 1.5s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        pulseDot: {
                            '0%, 100%': { opacity: '0.4' },
                            '50%': { opacity: '1' },
                        },
                    }
                }
            }
        }
    </script>
    <style>
        * {
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
        }

        /* Fluent scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #c8c6c4;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #a19f9d;
        }

        /* Preview iframe styles */
        #preview-frame {
            border: none;
            width: 100%;
            height: 100%;
        }

        /* Chat bubble animation */
        .chat-bubble {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .chat-bubble:hover {
            transform: scale(1.05);
        }

        /* Typing indicator */
        .typing-dot {
            animation: pulseDot 1.5s ease-in-out infinite;
        }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        /* Message animations */
        .message-enter {
            animation: slideUp 0.3s ease-out;
        }

        /* Acrylic effect for chat panel */
        .acrylic {
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            background-color: rgba(255, 255, 255, 0.85);
        }
    </style>
</head>
<body class="bg-fluent-bg-secondary font-fluent antialiased">
    <!-- Top Bar -->
    <header class="fixed top-0 left-0 right-0 h-12 bg-fluent-bg-primary border-b border-fluent-border z-40 flex items-center px-4 shadow-fluent-2">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded bg-fluent-primary flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                </svg>
            </div>
            <span class="text-fluent-text-primary font-semibold text-lg">Mockery</span>
            <span class="text-fluent-text-secondary text-sm">AI Page Builder</span>
        </div>

        <!-- Page selector -->
        <div class="ml-8 flex items-center gap-2">
            <select id="page-selector" class="h-8 px-3 pr-8 bg-fluent-bg-secondary border border-fluent-border rounded text-sm text-fluent-text-primary focus:outline-none focus:border-fluent-primary cursor-pointer appearance-none" style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%23605e5c%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 8px center; background-size: 16px;">
                <option value="">Wybierz stronę...</option>
            </select>
            <button id="new-page-btn" class="h-8 px-3 bg-fluent-primary hover:bg-fluent-primary-dark text-white text-sm font-medium rounded transition-colors flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nowa strona
            </button>
            <button id="delete-page-btn" class="h-8 px-3 bg-fluent-bg-secondary hover:bg-fluent-error hover:text-white border border-fluent-border text-fluent-text-secondary text-sm font-medium rounded transition-colors hidden">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>

        <!-- Version indicator -->
        <div id="version-indicator" class="ml-auto flex items-center gap-2 text-sm text-fluent-text-secondary">
            <span id="version-text"></span>
            <div id="sync-indicator" class="w-2 h-2 rounded-full bg-fluent-success hidden"></div>
        </div>
    </header>

    <!-- Main Preview Area -->
    <main class="pt-12 h-screen">
        <div id="preview-container" class="w-full h-full bg-white relative">
            <!-- Empty state -->
            <div id="empty-state" class="absolute inset-0 flex flex-col items-center justify-center text-fluent-text-secondary">
                <svg class="w-24 h-24 mb-6 text-fluent-border" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h2 class="text-xl font-semibold text-fluent-text-primary mb-2">Brak aktywnej strony</h2>
                <p class="text-fluent-text-secondary mb-6">Wybierz istniejącą stronę lub stwórz nową</p>
                <button id="empty-new-page-btn" class="px-6 py-2 bg-fluent-primary hover:bg-fluent-primary-dark text-white font-medium rounded transition-colors">
                    Stwórz pierwszą stronę
                </button>
            </div>

            <!-- Preview iframe -->
            <iframe id="preview-frame" class="hidden"></iframe>
        </div>
    </main>

    <!-- Floating Chat Button -->
    <button id="chat-toggle" class="chat-bubble fixed bottom-6 right-6 w-14 h-14 bg-fluent-primary hover:bg-fluent-primary-dark text-white rounded-full shadow-fluent-16 flex items-center justify-center z-50 transition-all">
        <svg id="chat-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <svg id="close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>

    <!-- Chat Panel -->
    <div id="chat-panel" class="fixed bottom-24 right-6 w-96 max-h-[600px] bg-fluent-bg-primary rounded-lg shadow-fluent-64 z-50 hidden flex-col overflow-hidden border border-fluent-border">
        <!-- Chat Header -->
        <div class="px-4 py-3 border-b border-fluent-border bg-fluent-bg-secondary flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-fluent-primary flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="font-semibold text-fluent-text-primary text-sm">Asystent AI</div>
                <div class="text-xs text-fluent-text-secondary">Opisz zmiany na stronie</div>
            </div>
            <div class="ml-auto">
                <select id="chat-page-selector" class="h-7 px-2 bg-fluent-bg-primary border border-fluent-border rounded text-xs text-fluent-text-primary focus:outline-none focus:border-fluent-primary">
                </select>
            </div>
        </div>

        <!-- Chat Messages -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 min-h-[300px] max-h-[400px]">
            <!-- Welcome message -->
            <div class="flex gap-3 message-enter">
                <div class="w-8 h-8 rounded-full bg-fluent-primary flex-shrink-0 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1 bg-fluent-bg-secondary rounded-lg p-3 text-sm text-fluent-text-primary">
                    Cześć! Jestem asystentem AI. Opisz mi jak ma wyglądać Twoja strona, a ja ją dla Ciebie zbuduję. Używam HTML, Tailwind CSS i JavaScript w stylu Fluent Design.
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div id="typing-indicator" class="hidden px-4 pb-2">
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-fluent-primary flex-shrink-0 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="bg-fluent-bg-secondary rounded-lg p-3 flex items-center gap-1">
                    <div class="typing-dot w-2 h-2 rounded-full bg-fluent-text-secondary"></div>
                    <div class="typing-dot w-2 h-2 rounded-full bg-fluent-text-secondary"></div>
                    <div class="typing-dot w-2 h-2 rounded-full bg-fluent-text-secondary"></div>
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="p-4 border-t border-fluent-border bg-fluent-bg-primary">
            <div class="flex gap-2">
                <textarea id="chat-input" placeholder="Opisz zmiany na stronie..." rows="1" class="flex-1 px-3 py-2 bg-fluent-bg-secondary border border-fluent-border rounded-lg text-sm text-fluent-text-primary placeholder-fluent-text-disabled focus:outline-none focus:border-fluent-primary resize-none" style="max-height: 120px;"></textarea>
                <button id="send-btn" class="px-4 py-2 bg-fluent-primary hover:bg-fluent-primary-dark disabled:bg-fluent-border disabled:cursor-not-allowed text-white rounded-lg transition-colors flex items-center justify-center" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- New Page Modal -->
    <div id="new-page-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
        <div class="bg-fluent-bg-primary rounded-lg shadow-fluent-64 w-full max-w-md mx-4 animate-fade-in">
            <div class="px-6 py-4 border-b border-fluent-border">
                <h3 class="text-lg font-semibold text-fluent-text-primary">Nowa strona</h3>
            </div>
            <div class="px-6 py-4">
                <label class="block text-sm font-medium text-fluent-text-primary mb-2">Nazwa strony</label>
                <input id="new-page-name" type="text" placeholder="np. landing-page" class="w-full px-3 py-2 bg-fluent-bg-secondary border border-fluent-border rounded text-sm text-fluent-text-primary placeholder-fluent-text-disabled focus:outline-none focus:border-fluent-primary">
                <p class="mt-2 text-xs text-fluent-text-secondary">Używaj tylko liter, cyfr i myślników</p>
            </div>
            <div class="px-6 py-4 border-t border-fluent-border flex justify-end gap-2">
                <button id="cancel-new-page" class="px-4 py-2 bg-fluent-bg-secondary hover:bg-fluent-bg-tertiary border border-fluent-border text-fluent-text-primary text-sm font-medium rounded transition-colors">
                    Anuluj
                </button>
                <button id="create-page-btn" class="px-4 py-2 bg-fluent-primary hover:bg-fluent-primary-dark text-white text-sm font-medium rounded transition-colors">
                    Stwórz stronę
                </button>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
