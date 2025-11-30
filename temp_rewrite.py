from pathlib import Path
path=Path('resources/views/rooms/show.blade.php')
text=path.read_text(encoding='utf-8')
reaction_emojis = ['\U0001F44D','\u2764\ufe0f','\U0001F602','\U0001F62E','\U0001F44F','\U0001F525']

# Replace first (server-rendered) message-actions block
start = text.find('<div class="message-actions">')
if start != -1:
    end_marker = '\n                                    </div>\n                                </li>'
    end = text.find(end_marker, start)
    if end != -1:
        end += len(end_marker)
        server_block = f'''                                    <div class="message-actions">
                                        <button
                                            type="button"
                                            class="msg-action"
                                            data-reply-id="{{ $message->id }}"
                                            data-reply-author="{{ e($authorName) }}"
                                            data-reply-text="{{ e(\\Illuminate\\Support\\Str::limit($message->content, 500)) }}"
                                        >
                                            <i data-lucide="corner-up-right"></i>
                                            <span>Reply</span>
                                        </button>
                                        <div class="message-reactions" data-reactions data-message-id="{{ $message->id }}">
                                            @foreach(['{"','".join(reaction_emojis)}'] as $emoji)
                                                <button class="reaction-pill" type="button" data-emoji="{{ $emoji }}" hidden>
                                                    <span class="reaction-emoji">{{ $emoji }}</span>
                                                    <span class="reaction-count">0</span>
                                                </button>
                                            @endforeach
                                        </div>
                                        <button type="button" class="reaction-add" data-reaction-trigger>
                                            <i data-lucide="smile"></i>
                                            <span>Add reaction</span>
                                        </button>
                                    </div>
                                    </div>
                                </li>'''
        text = text[:start] + server_block + text[end:]

# Replace second (JS template) message-actions block
second_start = text.find('<div class="message-actions">', start + 1)
if second_start != -1:
    end_marker_js = '</div>`;'
    end_js = text.find(end_marker_js, second_start)
    if end_js != -1:
        end_js += len(end_marker_js)
        inner = "".join([
            f"                                                <button class=\\\"reaction-pill\\\" type=\\\"button\\\" data-emoji=\\\"{emo}\\\" hidden>\\n                                                    <span class=\\\"reaction-emoji\\\">{emo}</span>\\n                                                    <span class=\\\"reaction-count\\\">0</span>\\n                                                </button>\\n"
            for emo in reaction_emojis
        ])
        js_block = f'''                                    <div class="message-actions">
                                        <button type="button" class="msg-action">
                                            <i data-lucide="corner-up-right"></i>
                                            <span>Reply</span>
                                        </button>
                                        <div class="message-reactions" data-reactions data-message-id="${{e.id}}">
{inner}                                        </div>
                                        <button type="button" class="reaction-add" data-reaction-trigger>
                                            <i data-lucide="smile"></i>
                                            <span>Add reaction</span>
                                        </button>
                                    </div>`;'''
        text = text[:second_start] + js_block + text[end_js:]

path.write_text(text, encoding='utf-8')
