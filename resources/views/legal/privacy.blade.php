<x-app-layout page-class="page-legal">
    <section class="panel legal-page">
        <header class="panel-header">
            <p class="panel-title">
                <span>Privacy & terms</span>
            </p>
            <a
                class="btn btn-sm btn-ghost legal-back-btn"
                href="{{ auth()->check() ? route('dashboard') : route('rooms.join') }}"
            >
                <i data-lucide="arrow-left"></i>
                <span>Back</span>
            </a>
        </header>

        <article class="panel-body px-6 py-6 legal-content">
            <h1>Privacy Notice</h1>

            <h2>1. Who we are</h2>
            <p>This service is operated by a private individual.</p>
            <ul>
                <li>Data Controller: the owner of this site.</li>
                <li>Contact: <strong><a href="mailto:zloydeveloper.info@gmail.com">zloydeveloper.info@gmail.com</a></strong></li>
            </ul>

            <h2>2. What we collect</h2>
            <p>We collect only the data needed to operate the chat:</p>
            <p><strong>Account data (beta users):</strong></p>
            <ul>
                <li>name or display name</li>
                <li>email</li>
                <li>hashed password</li>
            </ul>
            <p><strong>Chat data:</strong></p>
            <ul>
                <li>room titles and descriptions</li>
                <li>participant display names</li>
                <li>public messages and questions</li>
                <li>ratings (if used)</li>
            </ul>
            <p><strong>Technical data:</strong></p>
            <ul>
                <li>IP address</li>
                <li>browser headers</li>
                <li>device fingerprint</li>
                <li>timestamps (used for security and abuse prevention)</li>
            </ul>
            <p><strong>Cookies:</strong></p>
            <ul>
                <li>session cookie for login and CSRF protection</li>
                <li>The site uses only essential cookies.</li>
            </ul>

            <h2>3. Legal basis</h2>
            <p>We process data on the following grounds:</p>
            <ul>
                <li>providing the chat service (contract-like use)</li>
                <li>legitimate interest (security, rate limiting, preventing abuse)</li>
                <li>consent for any optional cookies or features that may require it</li>
            </ul>

            <h2>4. Why we use this data</h2>
            <ul>
                <li>operate the chat and let users join rooms</li>
                <li>authenticate beta accounts</li>
                <li>protect the service from spam and attacks</li>
                <li>troubleshoot issues and maintain performance</li>
            </ul>
            <p>There is no automated decision-making or profiling.</p>

            <h2>5. Storage and retention</h2>
            <ul>
                <li>Chat rooms and messages remain until removed by the site owner.</li>
                <li>Beta account data is kept until the user requests deletion.</li>
                <li>IP logs and security data are kept only as long as needed for protection and debugging.</li>
                <li>Backups (if any) follow the same deletion rules.</li>
            </ul>
            <p>Data is hosted within the EU/EEA.</p>

            <h2>6. Security</h2>
            <p>We take basic measures to protect personal data:</p>
            <ul>
                <li>hashed passwords (bcrypt/argon2)</li>
                <li>access to server and admin tools is restricted</li>
                <li>security monitoring and log checks</li>
            </ul>

            <h2>7. Your rights</h2>
            <p>You can request:</p>
            <ul>
                <li>access to your data</li>
                <li>correction or deletion</li>
                <li>restriction of processing</li>
                <li>objection to processing based on legitimate interest</li>
                <li>portability for your account data</li>
            </ul>
            <p>We respond within 30 days.<br>For complaints, you may contact the Finnish Data Protection Ombudsman.</p>

            <h2>8. Sharing and transfers</h2>
            <ul>
                <li>We do not sell personal data.</li>
                <li>Data is shared only with service providers needed to run the website (for example, hosting or email).</li>
                <li>There are no transfers outside the EU/EEA unless legally required safeguards are in place.</li>
            </ul>

            <h2>9. Contact</h2>
            <ul>
                <li>For privacy requests, write to: <strong><a href="mailto:zloydeveloper.info@gmail.com">zloydeveloper.info@gmail.com</a></strong></li>
            </ul>
        </article>
    </section>
</x-app-layout>
