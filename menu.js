// menu.js
// This script populates the customer‑facing menu page with menu items
// stored in localStorage. It presents each item as a card with name,
// description and price.

document.addEventListener('DOMContentLoaded', () => {
  const menuContainer = document.getElementById('menuContainer');
  // Retrieve items from localStorage
  let items = [];
  try {
    const stored = localStorage.getItem('menuItems');
    if (stored) {
      items = JSON.parse(stored);
    }
  } catch (e) {
    console.error('Failed to parse menuItems from localStorage', e);
  }

  // Helper to create a single menu card
  function createCard(item) {
    const card = document.createElement('div');
    card.style.backgroundColor = '#fff';
    card.style.border = '1px solid #ddd';
    card.style.borderRadius = '4px';
    card.style.padding = '16px';
    card.style.marginBottom = '16px';
    card.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';

    const nameEl = document.createElement('h3');
    nameEl.textContent = item.name;
    card.appendChild(nameEl);

    if (item.description) {
      const descEl = document.createElement('p');
      descEl.textContent = item.description;
      card.appendChild(descEl);
    }

    const priceEl = document.createElement('p');
    priceEl.style.fontWeight = 'bold';
    priceEl.textContent = `$${Number(item.price).toFixed(2)}`;
    card.appendChild(priceEl);

    return card;
  }

  // Render cards or placeholder text
  function renderMenu() {
    menuContainer.innerHTML = '';
    if (!items.length) {
      const p = document.createElement('p');
      p.textContent = 'The menu is currently empty. Please check back later.';
      menuContainer.appendChild(p);
      return;
    }
    items.forEach((item) => {
      menuContainer.appendChild(createCard(item));
    });
  }

  renderMenu();
});