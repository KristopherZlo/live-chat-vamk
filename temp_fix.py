import re, pathlib
path = pathlib.Path('resources/views/rooms/show.blade.php')
text = path.read_text(encoding='utf-8')
server_pattern = re.compile(r'(\s*<div class=\"message-reactions\" data-reactions data-message-id=\"\{\{ \$message->id \}\}\">.*?@endforeach\s+</div>)', re.S)
server_repl = '''
                                        <div class="message-reactions" data-reactions data-message-id="{{ $message->id }}">
                                            @foreach(['??','??','??','??','??','??'] as $emoji)
                                                <button class="reaction-pill" type="button" data-emoji="{{ $emoji }}" hidden>
                                                    <span class="reaction-emoji">{{ $emoji }}</span>
                                                    <span class="reaction-count">0</span>
                                                </button>
                                            @endforeach
                                        </div>
                                        <button type="button" class="reaction-add" data-reaction-trigger>
                                            <i data-lucide="smile"></i>
                                            <span>Add reaction</span>
                                        </button>'''
text, n1 = server_pattern.subn(server_repl, text, count=1)
js_pattern = re.compile(r'(\s*<div class=\"message-reactions\" data-reactions data-message-id=\"\$\{e.id\}\">.*?</div>)', re.S)
js_repl = '''
                                        <div class="message-reactions" data-reactions data-message-id="${e.id}">
                                            ${['??','??','??','??','??','??'].map((emo) => `
                                                <button class="reaction-pill" type="button" data-emoji="${emo}" hidden>
                                                    <span class="reaction-emoji">${emo}</span>
                                                    <span class="reaction-count">0</span>
                                                </button>
                                            `).join('')}
                                        </div>
                                        <button type="button" class="reaction-add" data-reaction-trigger>
                                            <i data-lucide="smile"></i>
                                            <span>Add reaction</span>
                                        </button>'''
text, n2 = js_pattern.subn(js_repl, text, count=1)
path.write_text(text, encoding='utf-8')
print('replaced', n1, n2)
