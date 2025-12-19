const HOUR_MS = 60 * 60 * 1000;

function getGreetingByHour(date: Date = new Date()): string {
  const hour = date.getHours();
  if (hour >= 5 && hour < 12) return 'Good morning';
  if (hour >= 12 && hour < 17) return 'Good afternoon';
  if (hour >= 17 && hour < 22) return 'Good evening';
  return 'Good night';
}

export function updateDashboardGreeting(): void {
  const greetingEl = document.getElementById('dashboardGreeting');
  if (!greetingEl) return;
  const name = greetingEl.dataset.username || '';
  const greeting = getGreetingByHour();
  greetingEl.textContent = name ? `${greeting}, ${name}` : greeting;
}

export function scheduleGreetingRefresh(): void {
  if (!document.getElementById('dashboardGreeting')) return;
  const now = new Date();
  const msElapsedThisHour = now.getMinutes() * 60 * 1000 + now.getSeconds() * 1000 + now.getMilliseconds();
  const msToNextHour = Math.max(HOUR_MS - msElapsedThisHour, 0);

  setTimeout(() => {
    updateDashboardGreeting();
    setInterval(updateDashboardGreeting, HOUR_MS);
  }, msToNextHour);
}
