<x-app-layout page-class="page-presentation">
    <style>
        .page-presentation .wrap { max-width: 1180px; margin: 0 auto; padding: 2.6rem 1.3rem 3.6rem; display: grid; gap: 1.4rem; }
        .page-presentation .panel { border: 1px solid var(--border-subtle); background: var(--bg-elevated); border-radius: 16px; box-shadow: 0 16px 38px rgba(15,23,42,0.08); overflow: hidden; }
        .page-presentation .panel-header { padding: 1.05rem 1.25rem 0.6rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .page-presentation .panel-title { display: flex; align-items: center; gap: 0.55rem; font-weight: 700; letter-spacing: -0.01em; }
        .page-presentation .panel-body { padding: 0.85rem 1.25rem 1.15rem; display: grid; gap: 0.9rem; }
        .page-presentation .hero { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: center; }
        .page-presentation .hero h1 { margin: 0; font-size: clamp(2rem, 4vw, 2.8rem); line-height: 1.1; letter-spacing: -0.02em; }
        .page-presentation .hero p { margin: 0.4rem 0 0.9rem; color: var(--text-secondary); font-size: 1.05rem; line-height: 1.55; }
        .page-presentation .hero-actions { display: flex; gap: 0.55rem; flex-wrap: wrap; }
        .page-presentation .pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.75rem; border: 1px solid var(--border-subtle); border-radius: 999px; background: var(--bg-soft); color: var(--text-secondary); font-size: 0.9rem; }
        .page-presentation .agenda { display: grid; gap: 0.65rem; }
        .page-presentation .agenda-item { display: grid; gap: 0.25rem; padding: 0.65rem 0.75rem; border-radius: 12px; border: 1px solid var(--border-subtle); background: var(--bg-soft); }
        .page-presentation .agenda-item strong { display: flex; gap: 0.5rem; align-items: center; }
        .page-presentation .agenda-item small { color: var(--text-secondary); }
        .page-presentation .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); }
        .page-presentation .snippet-card { border: 1px solid var(--border-subtle); border-radius: 14px; background: var(--bg-soft); display: grid; gap: 0.75rem; padding: 1rem; }
        .page-presentation .snippet-top { display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; }
        .page-presentation .snippet-title { margin: 0; font-weight: 700; letter-spacing: -0.01em; }
        .page-presentation .snippet { border: 1px solid var(--border-subtle); border-radius: 12px; background: var(--bg-elevated); padding: 0.85rem; display: grid; gap: 0.65rem; }
        .page-presentation .room-card { display: grid; gap: 0.35rem; }
        .page-presentation .room-title { font-weight: 700; }
        .page-presentation .room-meta { display: flex; gap: 0.45rem; align-items: center; color: var(--text-secondary); flex-wrap: wrap; }
        .page-presentation .status-pill { padding: 0.25rem 0.55rem; border-radius: 999px; background: var(--accent-soft); color: var(--accent-strong); font-weight: 600; }
        .page-presentation .status-muted { background: var(--border-subtle); color: var(--text-secondary); }
        .page-presentation .form-inline { display: grid; gap: 0.6rem; }
        .page-presentation .form-inline label { display: grid; gap: 0.3rem; font-size: 0.9rem; color: var(--text-secondary); }
        .page-presentation .form-inline input,
        .page-presentation .form-inline textarea { border: 1px solid var(--border-subtle); border-radius: 10px; padding: 0.55rem 0.7rem; background: var(--bg); color: var(--text-primary); }
        .page-presentation .message { border: 1px solid var(--border-subtle); border-radius: 10px; padding: 0.8rem; display: grid; gap: 0.5rem; background: var(--bg-soft); }
        .page-presentation .message-header { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
        .page-presentation .message-meta { display: flex; gap: 0.35rem; align-items: center; color: var(--text-secondary); }
        .page-presentation .reactions { display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .page-presentation .reaction-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.55rem; border: 1px solid var(--border-subtle); border-radius: 999px; background: var(--bg-elevated); }
        .page-presentation .queue-line { border: 1px solid var(--border-subtle); border-radius: 10px; padding: 0.8rem; display: grid; gap: 0.45rem; }
        .page-presentation .badge { padding: 0.25rem 0.55rem; border-radius: 999px; border: 1px solid var(--border-subtle); color: var(--text-secondary); font-weight: 600; }
        .page-presentation .modal { border: 1px solid var(--border-strong); border-radius: 12px; padding: 0.75rem; background: var(--bg-elevated); display: grid; gap: 0.5rem; }
        .page-presentation .toggle { display: inline-flex; align-items: center; gap: 0.5rem; }
        .page-presentation .qr-box { border: 1px dashed var(--border-strong); padding: 1rem; border-radius: 12px; display: grid; place-items: center; gap: 0.35rem; }
        .page-presentation .footnote { color: var(--text-secondary); font-size: 0.95rem; }
    </style>

    <div class="wrap">
        <section class="panel" aria-label="Hero">
            <div class="panel-body hero">
                <div>
                    <p class="pill">Live walkthrough</p>
                    <h1>Guide people through Ghost Room in one smooth demo</h1>
                    <p>Follow the agenda below: each step links to a real UI snippet, so you can narrate and click through without guessing.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="{{ route('rooms.create') }}">Create a room</a>
                        <a class="btn btn-ghost" href="/join">Join as participant</a>
                        <a class="btn btn-ghost" href="#agenda">See the plan</a>
                    </div>
                </div>
                <div class="snippet">
                    <div class="room-card">
                        <div class="room-title">Your rooms</div>
                        <div class="room-meta">
                            <span class="status-pill">Active</span>
                            <span>3 rooms</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <button class="btn btn-primary btn-sm">Create room</button>
                        <button class="btn btn-ghost btn-sm">Import</button>
                    </div>
                    <p class="footnote" style="margin:0;">Start here: spotlight the CTA, then move into the steps.</p>
                </div>
            </div>
        </section>

        <section class="panel" id="agenda" aria-label="Agenda">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="map"></i>
                    <span>Presentation agenda</span>
                </div>
                <span class="pill">Follow top to bottom</span>
            </div>
            <div class="panel-body agenda">
                <div class="agenda-item"><strong>1. Dashboard</strong> <small>Highlight the “Create room” button.</small></div>
                <div class="agenda-item"><strong>2. Create room</strong> <small>Title + description auto-filled, press Create.</small></div>
                <div class="agenda-item"><strong>3. Room chat</strong> <small>Send demo message; ask user to add emoji; press Reply and send.</small></div>
                <div class="agenda-item"><strong>4. Queue</strong> <small>Send a question (sound), click to mark read, tap status chip to mark answered, switch filter tabs.</small></div>
                <div class="agenda-item"><strong>5. Ban flow</strong> <small>Noisy message → Ban → confirm modal → Bans tab → Unban.</small></div>
                <div class="agenda-item"><strong>6. Quick responses</strong> <small>Send a canned reply, let replies come in, open Replies tab/thread.</small></div>
                <div class="agenda-item"><strong>7. Sounds</strong> <small>Toggle message/question sounds.</small></div>
                <div class="agenda-item"><strong>8. QR</strong> <small>Show the QR join button.</small></div>
                <div class="agenda-item"><strong>9. Return home</strong> <small>Jump back to dashboard.</small></div>
                <div class="agenda-item"><strong>10. Dashboard wrap-up</strong> <small>Show room list; close/reopen/delete a room.</small></div>
            </div>
        </section>

        <section class="panel" aria-label="Screens to show">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="monitor-smartphone"></i>
                    <span>Screens you will click through</span>
                </div>
                <span class="pill">Real UI snippets</span>
            </div>
            <div class="panel-body grid">
                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Dashboard CTA</p>
                        <span class="pill">Step 1</span>
                    </div>
                    <div class="snippet">
                        <div class="room-card">
                            <div class="room-title">Your rooms</div>
                            <div class="room-meta">
                                <span class="status-pill">Active</span>
                                <span class="status-pill status-muted">Finished</span>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <button class="btn btn-primary btn-sm">Create room</button>
                            <button class="btn btn-ghost btn-sm">Import</button>
                        </div>
                        <p class="footnote">Say: “Click Create room to start the demo.”</p>
                    </div>
                </div>

                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Create room</p>
                        <span class="pill">Step 2</span>
                    </div>
                    <div class="snippet form-inline">
                        <label>
                            <span>Title</span>
                            <input value="Data Structures Live Q&A" readonly>
                        </label>
                        <label>
                            <span>Description</span>
                            <textarea rows="2" readonly>Show the QR on screen, collect questions, mark answered/later.</textarea>
                        </label>
                        <button class="btn btn-primary btn-sm">Create</button>
                        <p class="footnote">Say: “Fields are pre-filled; press Create to enter the room.”</p>
                    </div>
                </div>

                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Room chat</p>
                        <span class="pill">Step 3</span>
                    </div>
                    <div class="snippet">
                        <div class="message">
                            <div class="message-header">
                                <div class="message-meta"><strong>Anna</strong> · 10:12</div>
                                <span class="status-pill">Question</span>
                            </div>
                            <div>Could you repeat the shortcut example?</div>
                            <div class="reactions">
                                <span class="reaction-chip">👍 <span class="count">3</span></span>
                                <span class="reaction-chip">🔥 <span class="count">1</span></span>
                            </div>
                        </div>
                        <div class="message">
                            <div class="message-header">
                                <div class="message-meta"><strong>Host</strong> · 10:13</div>
                                <span class="badge">Reply</span>
                            </div>
                            <div>Press Reply, add an emoji, send your answer.</div>
                        </div>
                        <p class="footnote">Action: send demo message, ask user to react, then reply.</p>
                    </div>
                </div>

                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Queue</p>
                        <span class="pill">Step 4</span>
                    </div>
                    <div class="snippet">
                        <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                            <button class="btn btn-sm btn-ghost">All</button>
                            <button class="btn btn-sm btn-primary">New</button>
                            <button class="btn btn-sm btn-ghost">Later</button>
                            <button class="btn btn-sm btn-ghost">Answered</button>
                        </div>
                        <div class="queue-line">
                            <div class="snippet-top">
                                <span><strong>Question</strong> · 10:15</span>
                                <span class="status-pill">New</span>
                            </div>
                            <div>Click to mark read; tap status chip to mark answered; filter tabs reorder.</div>
                            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                <button class="btn btn-sm btn-primary">Answered</button>
                                <button class="btn btn-sm btn-ghost">Later</button>
                                <button class="btn btn-sm btn-ghost">Ignored</button>
                            </div>
                        </div>
                        <p class="footnote">Play the question sound as you drop it into the queue.</p>
                    </div>
                </div>

                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Ban flow</p>
                        <span class="pill">Step 5</span>
                    </div>
                    <div class="snippet">
                        <div class="message">
                            <div class="message-header">
                                <div class="message-meta"><strong>Noisy user</strong> · 10:16</div>
                            </div>
                            <div>Spam message preview…</div>
                            <button class="btn btn-sm btn-danger">Ban</button>
                        </div>
                        <div class="modal">
                            <strong>Confirm ban</strong>
                            <p>They will be blocked from chat and questions.</p>
                            <div style="display:flex; gap:0.5rem;">
                                <button class="btn btn-danger btn-sm">Confirm</button>
                                <button class="btn btn-ghost btn-sm">Cancel</button>
                            </div>
                        </div>
                        <div class="queue-line">
                            <div class="snippet-top">
                                <span><strong>Bans tab</strong></span>
                                <button class="btn btn-ghost btn-sm">Unban</button>
                            </div>
                            <div class="footnote">Show how to unban quickly.</div>
                        </div>
                    </div>
                </div>

                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Quick responses</p>
                        <span class="pill">Step 6</span>
                    </div>
                    <div class="snippet">
                        <div class="queue-line">
                            <div class="snippet-top">
                                <span><strong>Quick response</strong></span>
                                <button class="btn btn-primary btn-sm">Send</button>
                            </div>
                            <div>“Any questions before we move on?”</div>
                        </div>
                        <div class="message">
                            <div class="message-header">
                                <div class="message-meta"><strong>Replies tab</strong></div>
                            </div>
                            <div>Open Replies to read incoming answers.</div>
                        </div>
                        <p class="footnote">Ask the audience to respond so you can show the Replies thread.</p>
                    </div>
                </div>

                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Sounds & QR</p>
                        <span class="pill">Steps 7–8</span>
                    </div>
                    <div class="snippet">
                        <label class="toggle">
                            <input type="checkbox" checked disabled>
                            <span>Message sounds</span>
                        </label>
                        <label class="toggle">
                            <input type="checkbox" checked disabled>
                            <span>Question sounds</span>
                        </label>
                        <div class="qr-box">
                            <i data-lucide="qr-code"></i>
                            <span>Show QR join button</span>
                        </div>
                        <p class="footnote">Flip sounds on/off, then present the QR join to the room.</p>
                    </div>
                </div>

                <div class="snippet-card">
                    <div class="snippet-top">
                        <p class="snippet-title">Return + wrap</p>
                        <span class="pill">Steps 9–10</span>
                    </div>
                    <div class="snippet">
                        <div class="room-card">
                            <div class="room-title">Room list</div>
                            <div class="room-meta">
                                <span class="status-pill">Active</span>
                                <span class="status-pill status-muted">Finished</span>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.45rem; flex-wrap:wrap;">
                            <button class="btn btn-sm btn-ghost">Close</button>
                            <button class="btn btn-sm btn-ghost">Reopen</button>
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </div>
                        <p class="footnote">Return home, then show close/reopen/delete to finish the tour.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
