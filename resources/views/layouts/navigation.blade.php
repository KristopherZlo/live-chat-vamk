<header class="app-header">
  <div class="app-title">
    @php $logoRoute = Auth::check() ? route('dashboard') : route('rooms.join'); @endphp
    <div class="app-logo">
      <a class="app-logo-link" href="{{ $logoRoute }}" aria-label="Ghost Room">
        <img src="{{ asset('icons/logo_black.svg') }}" class="app-logo-img app-logo-img--light" alt="Ghost Room logo">
        <img src="{{ asset('icons/logo_white.svg') }}" class="app-logo-img app-logo-img--dark" alt="Ghost Room logo">
      </a>
    </div>
    <div class="app-title-text">
      <a href="{{ route('dashboard') }}" class="room-name">Ghost Room</a>
      <div class="app-subtitle">Instant feedback</div>
    </div>
    <span class="badge">beta</span>
  </div>

  <div class="app-controls">
    <nav class="app-nav">
      @auth
        <div class="app-nav-primary">
          <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
          <a class="btn btn-sm btn-ghost" href="{{ route('rooms.create') }}" data-onboarding-target="create-room-nav">New room</a>
        </div>
        <div class="app-nav-room-actions">
          @stack('room-header-actions')
        </div>
      @endauth
    </nav>

    <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle theme">
      <i data-lucide="moon"></i>
    </button>

    @auth
      <details class="user-menu">
        <summary class="btn btn-sm btn-ghost user-menu-trigger" data-onboarding-target="user-menu-trigger">
          <span>{{ Auth::user()->name }}</span>
          <i data-lucide="chevron-down" aria-hidden="true"></i>
        </summary>
        <div class="user-menu-dropdown">
          <button type="button" class="dropdown-link setting" data-queue-sound-toggle data-onboarding-target="sound-toggle">
            <span>Question sound</span>
            <span class="pill" data-sound-state>On</span>
          </button>
          <a class="dropdown-link" href="{{ route('profile.edit') }}" data-onboarding-target="profile-link">Profile</a>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-link danger">Log out</button>
          </form>
        </div>
      </details>
    @endauth
  </div>
</header>

<div class="mobile-menu-overlay" id="mobileMenu">
  <div class="mobile-menu-card">
    <div class="mobile-menu-handle"></div>
    <div class="mobile-menu-row">
      <div class="mobile-menu-actions">
        @auth
          <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}" data-close-menu>
            <i data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
          </a>
          <a class="btn btn-sm btn-ghost" href="{{ route('rooms.create') }}" data-close-menu>
            <i data-lucide="plus"></i>
            <span>New room</span>
          </a>
        @endauth
        <button class="btn btn-sm btn-ghost" type="button" data-theme-toggle data-close-menu>
          <i data-lucide="sun"></i>
          <span>Toggle theme</span>
        </button>
      </div>
      <div class="mobile-menu-actions">
        @auth
          <a class="btn btn-sm btn-ghost" href="{{ route('profile.edit') }}" data-close-menu>
            <i data-lucide="user"></i>
            <span>Profile</span>
          </a>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary" data-close-menu>
              <i data-lucide="log-out"></i>
              <span>Log out</span>
            </button>
          </form>
        @endauth
      </div>
    </div>

      <div class="mobile-menu-footer">
        <div class="mobile-menu-footer-links">
          <a href="{{ route('privacy') }}" data-close-menu>
            <i data-lucide="shield-check"></i>
            <span>Privacy & terms</span>
          </a>
          <a href="#" data-close-menu>
            <i data-lucide="mail"></i>
            <span>Contact</span>
          </a>
        </div>
    </div>
  </div>
</div>
