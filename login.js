// login.js
// This script handles authentication for the admin panel. It is deliberately
// simplistic: credentials are hardcoded. In a production environment you
// would never store credentials client‑side and would instead validate on
// the server. For demonstration purposes we keep everything in the browser.

document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const errorElem = document.getElementById('error');

  // Hardcoded credentials. You can change these to whatever you like.
  const adminUsername = 'admin';
  const adminPassword = '1234';

  loginForm.addEventListener('submit', (event) => {
    event.preventDefault();

    // Grab the username and password values. Type assertions are avoided because
    // this is plain JavaScript (not TypeScript).
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    // Reset error message
    errorElem.textContent = '';

    // Check credentials
    if (username === adminUsername && password === adminPassword) {
      // Mark the user as logged in. We use localStorage so that subsequent page
      // loads can detect the logged‑in state. localStorage persists until
      // explicitly cleared.
      localStorage.setItem('loggedIn', 'true');
      // Redirect to admin page
      window.location.href = 'admin.html';
    } else {
      // Display an error message
      errorElem.textContent = 'Invalid username or password';
    }
  });
});