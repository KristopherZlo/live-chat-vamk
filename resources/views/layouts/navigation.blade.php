<header class="app-header">
  <div class="app-title">
    <div class="app-title-text">
      <a href="{{ route('dashboard') }}" class="room-name">Live Chat</a>
      <div class="room-code">VAMK ? lecture Q&A</div>
    </div>
    <span class="badge">beta</span>
  </div>

  <div class="app-controls">
    <nav class="app-nav">
      <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
      @auth
        <a class="btn btn-sm btn-ghost" href="{{ route('rooms.create') }}">New room</a>
      @endauth
    </nav>

    <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle theme">
      <i data-lucide="moon"></i>
    </button>

    @auth
      <details class="user-menu">
        <summary class="btn btn-sm btn-ghost user-menu-trigger">
          <span>{{ Auth::user()->name }}</span>
          <i data-lucide="chevron-down" aria-hidden="true"></i>
        </summary>
        <div class="user-menu-dropdown">
          <a class="dropdown-link" href="{{ route('profile.edit') }}">Profile</a>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-link danger">Log out</button>
          </form>
        </div>
      </details>
    @else
      <a class="btn btn-sm btn-primary" href="{{ route('login') }}">Sign in</a>
      <a class="btn btn-sm btn-ghost" href="{{ route('register') }}">Register</a>
    @endauth
  </div>
</header>

<div class="mobile-menu-overlay" id="mobileMenu">
  <div class="mobile-menu-card">
    <div class="mobile-menu-handle"></div>
    <div class="mobile-menu-row">
      <div class="mobile-menu-actions">
        <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}" data-close-menu>Dashboard</a>
        @auth
          <a class="btn btn-sm btn-ghost" href="{{ route('rooms.create') }}" data-close-menu>New room</a>
        @endauth
        <button class="btn btn-sm btn-ghost" type="button" data-theme-toggle data-close-menu>Toggle theme</button>
      </div>
      <div class="mobile-menu-actions">
        @auth
          <a class="btn btn-sm btn-ghost" href="{{ route('profile.edit') }}" data-close-menu>Profile</a>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary" data-close-menu>Log out</button>
          </form>
        @else
          <a class="btn btn-sm btn-primary" href="{{ route('login') }}" data-close-menu>Sign in</a>
          <a class="btn btn-sm btn-ghost" href="{{ route('register') }}" data-close-menu>Register</a>
        @endauth
      </div>
    </div>

    <div class="mobile-menu-footer">
      <div class="mobile-menu-footer-links">
        <a href="#" data-close-menu>GDPR</a>
        <a href="#" data-close-menu>Contact</a>
      </div>
      <div class="mobile-menu-footer-lang">
        <button class="footer-lang active" type="button" data-close-menu>FI</button>
        <button class="footer-lang" type="button" data-close-menu>RU</button>
        <button class="footer-lang" type="button" data-close-menu>EN</button>
      </div>
    </div>
  </div>
</div>
