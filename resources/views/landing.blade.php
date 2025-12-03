
<x-app-layout page-class="page-landing">
    <style>
        .page-landing .landing { max-width: 1220px; margin: 0 auto; padding: 2.6rem 1.25rem 3.8rem; display: grid; gap: 1.35rem; }
        .page-landing .panel { overflow: hidden; border: 1px solid var(--border-subtle); }
        .page-landing .panel-header,
        .page-landing .panel-body,
        .page-landing .panel-footer { padding-inline: 1.45rem; }
        .page-landing .panel-header { padding-block: 1.05rem 0.65rem; }
        .page-landing .panel-body { padding-block: 1.05rem 1.15rem; }
        .page-landing .landing-hero-grid { display: grid; gap: 1.15rem; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); align-items: start; }
        .page-landing .landing-hero__title { font-size: clamp(2rem, 3.8vw, 2.8rem); line-height: 1.12; margin: 0; letter-spacing: -0.01em; }
        .page-landing .hero-lead { color: var(--text-secondary); margin: 0.4rem 0 0.95rem; max-width: 720px; font-size: 1.05rem; line-height: 1.55; }
        .page-landing .eyebrow { letter-spacing: 0.06em; text-transform: uppercase; font-weight: 700; font-size: 0.78rem; margin: 0 0 0.35rem; color: var(--text-secondary); }
        .page-landing .qa-chips { display: grid; gap: 0.55rem; }
        .page-landing .qa-chip { display: grid; grid-template-columns: auto 1fr; gap: 0.55rem; align-items: center; padding: 0.65rem 0.9rem; border: 1px solid var(--border-subtle); border-radius: 14px; background: var(--bg-soft, #f8fafc); line-height: 1.5; }
        .page-landing .qa-q { font-weight: 700; letter-spacing: 0.01em; }
        .page-landing .qa-a { color: var(--text-secondary); }

        .page-landing .landing-hero-card { background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(16,185,129,0.1)), var(--bg-elevated); border-radius: 18px; }
        .page-landing .hero-badges { display: flex; flex-wrap: wrap; gap: 0.55rem; margin-top: 1.05rem; }
        .page-landing .hero-badge { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.48rem 0.88rem; border: 1px solid var(--border-subtle); border-radius: 999px; background: var(--bg-soft, #f8fafc); color: var(--text-secondary); font-weight: 600; }
        .page-landing .hero-preview-body { display: grid; gap: 0.75rem; }
        .page-landing .stat-grid { display: grid; grid-template-columns: minmax(180px, 230px) 1fr; gap: 1.45rem; align-items: center; }
        .page-landing .stat-pie { --pie-fill: 72; width: 200px; height: 200px; border-radius: 50%; background: conic-gradient(var(--accent) calc(var(--pie-fill) * 1%), var(--border-subtle) 0); display: grid; place-items: center; position: relative; box-shadow: 0 12px 32px rgba(15,23,42,0.12); animation: pieFill 1.1s ease forwards; }
        .page-landing .stat-pie::after { content: ""; width: 102px; height: 102px; border-radius: 50%; background: var(--bg-soft, #f8fafc); position: absolute; }
        .page-landing .stat-pie__label { position: relative; font-weight: 800; color: var(--text-primary); font-size: 1.45rem; }
        .page-landing .stat-lead { margin: 0 0 0.3rem; font-weight: 700; font-size: 1.12rem; }
        .page-landing .stat-points { margin: 0; padding-left: 1.1rem; display: grid; gap: 0.3rem; color: var(--text-secondary); line-height: 1.55; }

        .page-landing .ui-grid { display: grid; gap: 1.2rem; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); align-items: start; }
        .page-landing .chat-pane { max-height: 380px; }
        .page-landing .chat-messages { gap: 0.78rem; padding: 0.8rem 0.85rem 1.15rem; max-height: 280px; overflow: auto; line-height: 1.5; }
        .page-landing .chat-pane .message { animation: fadeUp 0.35s ease both; }
        .page-landing .chat-input { padding: 1rem 1.1rem; border-top: 1px solid var(--border-subtle); }
        .page-landing .chat-form { display: grid; gap: 0.65rem; }
        .page-landing .chat-composer { display: grid; grid-template-columns: auto 1fr auto; gap: 0.6rem; align-items: end; }
        .page-landing .composer-btn { border: 1px solid var(--border-subtle); border-radius: 12px; padding: 0.58rem 0.7rem; background: var(--bg-soft, #f8fafc); transition: transform 120ms ease, box-shadow 160ms ease; }
        .page-landing .composer-btn.icon-only { width: 44px; height: 44px; display: grid; place-items: center; padding: 0; }
        .page-landing .composer-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(15,23,42,0.12); }
        .page-landing .composer-send { background: var(--accent); color: #fff; border-color: var(--accent); }
        .page-landing .chat-textarea { min-height: 46px; resize: none; line-height: 1.45; }

        .page-landing .queue-list { gap: 0.8rem; }
        .page-landing .queue-item { padding: 1rem 1.1rem; border-radius: 14px; transition: transform 160ms ease, box-shadow 160ms ease; line-height: 1.55; }
        .page-landing .queue-item:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(15,23,42,0.12); }
        .page-landing .queue-controls { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .page-landing .queue-actions-inline { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

        .page-landing .form-demo { display: grid; gap: 1rem; }
        .page-landing .landing-cta { background: linear-gradient(135deg, rgba(37,99,235,0.16), rgba(16,185,129,0.12)); border-radius: 18px; padding: 1.7rem; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; }

        .page-landing .fade-up { animation: fadeUp 0.4s ease both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }
        @keyframes pieFill { from { --pie-fill: 0; } to { --pie-fill: 72; } }

        @media (max-width: 640px) {
            .page-landing .landing { padding: 1.8rem 1rem 3rem; gap: 1.1rem; }
            .page-landing .panel-header { padding-block: 1rem 0.6rem; }
            .page-landing .panel-body { padding-block: 0.95rem 1.05rem; }
            .page-landing .stat-grid { grid-template-columns: 1fr; }
            .page-landing .ui-grid { grid-template-columns: 1fr; }
        }
    </style>
    <div class="landing">
        <section class="panel landing-hero-card fade-up" aria-label="Hero">
            <div class="panel-body landing-hero-grid">
                <div>
                    <p class="eyebrow">Ghost Room: kysymykset ilman mikkiä</p>
                    <h1 class="landing-hero__title">Kerää opiskelijoiden kysymykset ilman taukoja luennon aikana</h1>
                    <p class="hero-lead">Näytä QR-koodi, opiskelijat kysyvät ja äänestävät anonyymisti. Johtat luentoa ja näet heti, mikä yleisöä todella mietityttää.</p>
                    <div class="hero-actions" style="margin-top:0.85rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <a href="/join" class="btn btn-primary">Kokeile opiskelijana</a>
                        <a href="#demo" class="btn btn-ghost">Katso demo</a>
                        <a href="{{ route('rooms.create') }}" class="btn btn-ghost">Luo huone</a>
                    </div>
                    <div class="hero-badges">
                        <span class="hero-badge">
                            <i data-lucide="qr-code"></i>
                            <span>Sisäänkirjautuminen QR:llä ja koodilla</span>
                        </span>
                        <span class="hero-badge">
                            <i data-lucide="shield-check"></i>
                            <span>Anonymiteetti hallinnassa</span>
                        </span>
                        <span class="hero-badge">
                            <i data-lucide="clock-3"></i>
                            <span>Vastaukset ajallaan</span>
                        </span>
                    </div>
                </div>
                <div class="panel hero-preview">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i data-lucide="sparkles"></i>
                            <span>Ohjaajan työkalut</span>
                        </div>
                    </div>
                    <div class="panel-body hero-preview-body">
                        <div class="qa-chips">
                            <div class="qa-chip">
                                <span class="qa-q">Kysymysjono</span>
                                <span class="qa-a">Tilat "vastattu", "myöhemmin" tai "piilotettu" pitävät kaiken hallinnassa.</span>
                            </div>
                            <div class="qa-chip">
                                <span class="qa-q">Yleisön äänet</span>
                                <span class="qa-a">Reaktiot nostavat tärkeimmät aiheet kärkeen.</span>
                            </div>
                            <div class="qa-chip">
                                <span class="qa-q">Helppo osallistua</span>
                                <span class="qa-a">Ei rekisteröitymistä, toimii puhelimella tai kannettavalla.</span>
                            </div>
                            <div class="qa-chip">
                                <span class="qa-q">Valvonta</span>
                                <span class="qa-a">Hiljennä häiriköt ja suodata roskaposti.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="panel fade-up" aria-label="Miten Ghost Room toimii">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="circle-help"></i>
                    <span>Miten Ghost Room toimii</span>
                </div>
            </div>
            <div class="panel-body">
                <div class="qa-chips">
                    <div class="qa-chip">
                        <span class="qa-q">Liittyminen</span>
                        <span class="qa-a">Skannaa QR tai syötä huonekoodi – tiliä ei tarvita.</span>
                    </div>
                    <div class="qa-chip">
                        <span class="qa-q">Kysymysten lähetys</span>
                        <span class="qa-a">Merkitse viesti "kysymys", jotta keskustelu säilyy selkeänä.</span>
                    </div>
                    <div class="qa-chip">
                        <span class="qa-q">Priorisointi</span>
                        <span class="qa-a">Reaktiot ja äänestykset kertovat, mikä kiinnostaa juuri nyt.</span>
                    </div>
                    <div class="qa-chip">
                        <span class="qa-q">Moderaatio</span>
                        <span class="qa-a">Merkitse viesti "myöhemmin" tai "piilotettu", niin poistat duplikaatit ja aiheet.</span>
                    </div>
                </div>
            </div>
        </section>
        <section class="panel stat fade-up" id="stat" aria-label="Järjestys kysymyksissä">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="pie-chart"></i>
                    <span>Järjestys kysymyksissä</span>
                </div>
                <p class="panel-subtitle">Testit yli 15 ryhmällä: vähemmän hälinää, enemmän merkityksellisiä kysymyksiä.</p>
            </div>
            <div class="panel-body stat-grid">
                <div class="stat-pie" role="img" aria-label="72 % opiskelijoista kysyy useammin" style="--pie-fill: 72;">
                    <span class="stat-pie__label">72%</span>
                </div>
                <div>
                    <p class="stat-lead">Enemmän kysymyksiä, vähemmän keskeytyksiä.</p>
                    <ul class="stat-points">
                        <li>72 % opiskelijoista osallistuu kysymyksiin useammin anonymiteetin ansiosta.</li>
                        <li>Jono järjestyy reaktioiden perusteella, jotta tärkeä nousee esiin.</li>
                        <li>Tilat "myöhemmin" ja "piilotettu" siivoavat duplikaatit ja aiheet.</li>
                    </ul>
                </div>
            </div>
        </section>
        <section class="panel fade-up" id="demo" aria-label="Live-chat ja kysymysjono">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="layout"></i>
                    <span>Live-chat ja kysymysjono</span>
                </div>
                <p class="panel-subtitle">Chat, reaktiot ja tilat päivittyvät reaaliajassa. Voit vastata, lykätä tai piilottaa turhan.</p>
            </div>
            <div class="panel-body ui-grid">
                <div class="panel chat-panel" data-demo-chat>
                    <div class="panel-header">
                        <div class="panel-title">
                            <i data-lucide="message-circle"></i>
                            <span>Ghost Room chat</span>
                        </div>
                        <div class="panel-subtitle">Opiskelijat kirjoittavat puhelimella; tärkeä nousee heti esiin.</div>
                    </div>
                    <div class="chat-pane" data-chat-panel="chat">
                        <ol class="chat-messages messages-container" id="demoChatMessages">
                            <li class="message message--question">
                                <div class="message-avatar colorized" style="background:#2563eb; color:#fff;">AN</div>
                                <div class="message-body">
                                    <div class="message-header">
                                        <span class="message-author">Anna</span>
                                        <div class="message-meta">
                                            <span>10:05</span>
                                            <span class="message-badge message-badge-question">Kysymys</span>
                                        </div>
                                    </div>
                                    <div class="message-text">Miten saan tallenteen luennon jälkeen?</div>
                                    <div class="message-reactions">
                                        <div class="reactions-list">
                                            <button type="button" class="reaction-chip is-active" data-demo-reaction><span class="emoji">👍</span><span class="count">4</span></button>
                                            <button type="button" class="reaction-chip" data-demo-reaction><span class="emoji">👀</span><span class="count">2</span></button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="message message--outgoing">
                                <div class="message-avatar colorized" style="background:#10b981; color:#fff;">PR</div>
                                <div class="message-body">
                                    <div class="message-header">
                                        <span class="message-author">Opettaja</span>
                                        <div class="message-meta">
                                            <span>10:06</span>
                                            <span class="message-badge message-badge-teacher">Host</span>
                                        </div>
                                    </div>
                                    <div class="message-text">QR johdattaa huoneeseen. Julkaisen tallenteen tunnin lopussa ja kerään reaktioilla tärkeät aiheet.</div>
                                    <div class="message-reactions">
                                        <div class="reactions-list">
                                            <button type="button" class="reaction-chip is-active" data-demo-reaction><span class="emoji">❤️</span><span class="count">3</span></button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="message">
                                <div class="message-avatar colorized" style="background:#f97316; color:#fff;">ST</div>
                                <div class="message-body">
                                    <div class="message-header">
                                        <span class="message-author">Opiskelija</span>
                                        <div class="message-meta">
                                            <span>10:07</span>
                                        </div>
                                    </div>
                                    <div class="message-text">Kiitos! Voisitko muistuttaa läksystä chatissa?</div>
                                    <div class="message-reactions">
                                        <div class="reactions-list">
                                            <button type="button" class="reaction-chip" data-demo-reaction><span class="emoji">👍</span><span class="count">1</span></button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="message message--question">
                                <div class="message-avatar colorized" style="background:#8b5cf6; color:#fff;">SM</div>
                                <div class="message-body">
                                    <div class="message-header">
                                        <span class="message-author">Sami</span>
                                        <div class="message-meta">
                                            <span>10:08</span>
                                            <span class="message-badge message-badge-question">Kysymys</span>
                                        </div>
                                    </div>
                                    <div class="message-text">Voinko lähettää kysymyksen etukäteen, jotta näet sen ennen luentoa?</div>
                                </div>
                            </li>
                        </ol>
                    </div>
                    <div class="chat-input">
                        <form class="chat-form" data-demo-chat-form>
                            <div class="chat-send-options">
                                <label class="switch">
                                    <input type="checkbox" name="as_question" value="1" data-chat-question checked>
                                    <span class="switch-track"><span class="switch-thumb"></span></span>
                                    <span class="switch-label">Lähetä kysymyksenä</span>
                                </label>
                            </div>
                            <div class="chat-composer" data-chat-composer>
                                <button type="button" class="composer-btn icon-only" title="Lisää hymiö" data-demo-emoji>
                                    <i data-lucide="smile"></i>
                                </button>
                                <textarea class="chat-textarea" placeholder="Kirjoita kysymys..." rows="1" data-chat-input></textarea>
                                <button type="submit" class="composer-btn composer-send icon-only" title="Lähetä viesti">
                                    <i data-lucide="send"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="panel queue-panel" aria-label="Kysymysjono">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i data-lucide="list-checks"></i>
                            <span>Kysymysjono</span>
                        </div>
                        <div class="panel-subtitle">3 kysymystä odottavat vastaustasi.</div>
                    </div>
                    <div class="panel-body">
                        <ul class="queue-list" id="demoQueueList">
                            <li class="queue-item queue-item-new" data-status="new">
                                <div class="question-header">
                                    <div class="question-meta">
                                        <span class="message-author">Anna</span>
                                        <span class="message-meta">10:08</span>
                                    </div>
                                </div>
                                <div class="question-text">Voiko kysymyksiä lähettää etukäteen, jotta näet ne ennen luentoa?</div>
                                <div class="queue-actions-inline">
                                    <div class="queue-controls">
                                        <button type="button" class="queue-action queue-action-answered" data-demo-status="answered">Vastaa</button>
                                        <button type="button" class="queue-action queue-action-later" data-demo-status="later">Myöhemmin</button>
                                        <button type="button" class="queue-action queue-action-ignored" data-demo-status="ignored">Piilota</button>
                                    </div>
                                </div>
                            </li>
                            <li class="queue-item" data-status="answered">
                                <div class="question-header">
                                    <div class="question-meta">
                                        <span class="message-author">Dmitri</span>
                                        <span class="message-meta">10:05</span>
                                    </div>
                                    <span class="status-pill status-answered">Vastattu</span>
                                </div>
                                <div class="question-text">Mistä löydän linkin luennon muistiinpanoihin?</div>
                                <div class="queue-actions-inline">
                                    <div class="queue-controls">
                                        <button type="button" class="queue-action queue-action-answered" data-demo-status="answered">Vastaa</button>
                                        <button type="button" class="queue-action queue-action-later" data-demo-status="later">Myöhemmin</button>
                                        <button type="button" class="queue-action queue-action-ignored" data-demo-status="ignored">Piilota</button>
                                    </div>
                                </div>
                            </li>
                            <li class="queue-item" data-status="later">
                                <div class="question-header">
                                    <div class="question-meta">
                                        <span class="message-author">Katja</span>
                                        <span class="message-meta">10:03</span>
                                    </div>
                                    <span class="status-pill status-later">Myöhemmin</span>
                                </div>
                                <div class="question-text">Milloin seuraava tenttivalmennus tai palautetilaisuus pidetään?</div>
                                <div class="queue-actions-inline">
                                    <div class="queue-controls">
                                        <button type="button" class="queue-action queue-action-answered" data-demo-status="answered">Vastaa</button>
                                        <button type="button" class="queue-action queue-action-later" data-demo-status="later">Myöhemmin</button>
                                        <button type="button" class="queue-action queue-action-ignored" data-demo-status="ignored">Piilota</button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <section class="panel fade-up" aria-label="Luo huone">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="plus-circle"></i>
                    <span>Luo huone</span>
                </div>
                <span class="pill-soft">1 minuutti</span>
            </div>
            <div class="panel-body px-6 py-5 space-y-5">
                <form class="form-demo" aria-label="Esimerkki huoneen luomisesta">
                    <label class="input-field">
                        <span class="input-label">Huoneen nimi</span>
                        <input type="text" class="field-control" value="SQL-harjoitus – Q&A" readonly>
                    </label>
                    <label class="input-field">
                        <span class="input-label">Kuvaus</span>
                        <textarea class="field-control" rows="3" readonly>Näytä QR-koodi kalvolla, kerää kysymykset ja merkitse tila: vastattu, myöhemmin tai piilotettu.</textarea>
                    </label>
                    <div class="form-footer">
                        <span class="panel-subtitle">Saat QR-koodin ja linkin luomisen jälkeen; opiskelijat liittyvät ilman rekisteröitymistä.</span>
                        <a href="{{ route('rooms.create') }}" class="btn btn-primary">
                            <i data-lucide="sparkles"></i>
                            <span>Luo huone</span>
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <section class="fade-up">
            <div class="landing-cta">
                <div>
                    <p class="eyebrow">10 minuuttia ensimmäiseen huoneeseen</p>
                    <h2 style="margin:0;">Kerää kysymykset anonyymisti ja ilman kaaosta</h2>
                    <p class="panel-subtitle">Näytä QR, anna opiskelijoille kanava kysymyksille ja pidä keskustelu luennon aikataulussa.</p>
                </div>
                <div class="hero-actions" style="display:flex; gap:0.55rem; flex-wrap:wrap;">
                    <a href="{{ route('rooms.create') }}" class="btn btn-primary">Aloita nyt</a>
                    <a href="/join" class="btn btn-ghost">Liity opiskelijana</a>
                </div>
            </div>
        </section>
    </div>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
                    anchor.addEventListener('click', (event) => {
                        const targetId = anchor.getAttribute('href');
                        const el = targetId ? document.querySelector(targetId) : null;
                        if (el) {
                            event.preventDefault();
                            el.scrollIntoView({ behavior: 'smooth' });
                        }
                    });
                });

                const chatMessages = document.getElementById('demoChatMessages');
                const chatForm = document.querySelector('[data-demo-chat-form]');
                const chatInput = chatForm?.querySelector('[data-chat-input]');
                const chatQuestionSwitch = chatForm?.querySelector('[data-chat-question]');
                const queueList = document.getElementById('demoQueueList');
                const statusLabels = { answered: 'Vastattu', later: 'Myöhemmin', ignored: 'Piilotettu', new: 'Uusi' };

                const formatTime = () => {
                    const d = new Date();
                    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
                };

                const appendMessage = ({ author = 'Opiskelija', content = '', outgoing = false, question = false, reactions = [] }) => {
                    if (!chatMessages) return;
                    const wrapper = document.createElement('li');
                    wrapper.className = `message${outgoing ? ' message--outgoing' : ''}${question ? ' message--question' : ''}`;
                    const color = outgoing ? '#10b981' : '#2563eb';
                    const initials = outgoing ? 'PR' : 'ST';
                    const time = formatTime();
                    const reactionHtml = reactions.map((r) => `
                        <button type="button" class="reaction-chip${r.active ? ' is-active' : ''}" data-demo-reaction>
                            <span class="emoji">${r.emoji}</span><span class="count">${r.count}</span>
                        </button>
                    `).join('');
                    wrapper.innerHTML = `
                        <div class="message-avatar colorized" style="background:${color}; color:#fff;">${initials}</div>
                        <div class="message-body">
                            <div class="message-header">
                                <span class="message-author">${author}</span>
                                <div class="message-meta">
                                    <span>${time}</span>
                                    ${outgoing ? '<span class="message-badge message-badge-teacher">Host</span>' : ''}
                                    ${question ? '<span class="message-badge message-badge-question">Kysymys</span>' : ''}
                                </div>
                            </div>
                            <div class="message-text">${content}</div>
                            ${reactionHtml ? `<div class="message-reactions"><div class="reactions-list">${reactionHtml}</div></div>` : ''}
                        </div>
                    `;
                    chatMessages.appendChild(wrapper);
                    chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
                };

                chatForm?.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const text = (chatInput?.value || '').trim();
                    if (!text) return;
                    const isQuestion = Boolean(chatQuestionSwitch?.checked);
                    appendMessage({ author: 'Opiskelija', content: text, question: isQuestion, reactions: [{ emoji: '👍', count: 1, active: true }] });
                    if (chatInput) {
                        chatInput.value = '';
                        chatInput.dispatchEvent(new Event('input'));
                    }
                });

                chatMessages?.addEventListener('click', (event) => {
                    const chip = event.target.closest('[data-demo-reaction]');
                    if (!chip) return;
                    const countEl = chip.querySelector('.count');
                    const current = parseInt(countEl?.textContent || '0', 10) || 0;
                    const isActive = chip.classList.toggle('is-active');
                    const next = Math.max(0, current + (isActive ? 1 : -1));
                    if (countEl) countEl.textContent = String(next);
                });

                const demoQuestions = [
                    'Voisinko lisätä anonyymin kyselyn?',
                    'Mitä tapahtuu kysymyksille, jos suljen huoneen?',
                    'Mistä löydän vastaukset luennon jälkeen?'
                ];
                let dqIndex = 0;
                setInterval(() => {
                    const text = demoQuestions[dqIndex % demoQuestions.length];
                    dqIndex += 1;
                    appendMessage({ author: 'Opiskelija', content: text, question: true, reactions: [{ emoji: '👍', count: 1, active: true }] });
                }, 3800);

                queueList?.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-demo-status]');
                    const item = event.target.closest('.queue-item');
                    if (!btn || !item) return;
                    const status = btn.dataset.demoStatus;
                    let pill = item.querySelector('.status-pill');
                    if (!pill) {
                        pill = document.createElement('span');
                        item.querySelector('.question-header')?.appendChild(pill);
                    }
                    pill.className = `status-pill status-${status}`;
                    pill.textContent = statusLabels[status] || status;
                    item.dataset.status = status;
                    item.classList.toggle('queue-item-new', status === 'new');
                });
            });
        </script>
    @endpush
</x-app-layout>
