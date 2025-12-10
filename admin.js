// admin.js
// This script provides functionality for the admin panel: enforcing login,
// adding, editing and deleting menu items, and persisting them in localStorage.

document.addEventListener('DOMContentLoaded', () => {
  // Redirect to login page if not logged in
  if (localStorage.getItem('loggedIn') !== 'true') {
    window.location.href = 'index.html';
    return;
  }

  const logoutBtn = document.getElementById('logoutBtn');
  const itemForm = document.getElementById('itemForm');
  const itemsTableBody = document.querySelector('#itemsTable tbody');

  // Load existing items from localStorage or create an empty array
  let items = [];
  try {
    const stored = localStorage.getItem('menuItems');
    if (stored) {
      items = JSON.parse(stored);
    }
  } catch (e) {
    console.error('Failed to parse menuItems from localStorage', e);
  }

  // Render the table
  function renderTable() {
    // Clear existing rows
    itemsTableBody.innerHTML = '';
    if (!items.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 4;
      td.textContent = 'No menu items yet';
      td.style.textAlign = 'center';
      tr.appendChild(td);
      itemsTableBody.appendChild(tr);
      return;
    }
    items.forEach((item) => {
      const tr = document.createElement('tr');
      // Name
      const tdName = document.createElement('td');
      tdName.textContent = item.name;
      tr.appendChild(tdName);
      // Description
      const tdDesc = document.createElement('td');
      tdDesc.textContent = item.description || '';
      tr.appendChild(tdDesc);
      // Price
      const tdPrice = document.createElement('td');
      tdPrice.textContent = Number(item.price).toFixed(2);
      tr.appendChild(tdPrice);
      // Actions
      const tdActions = document.createElement('td');
      const editBtn = document.createElement('button');
      editBtn.textContent = 'Edit';
      editBtn.className = 'btn btn-secondary';
      editBtn.dataset.id = item.id;
      const deleteBtn = document.createElement('button');
      deleteBtn.textContent = 'Delete';
      deleteBtn.className = 'btn btn-danger';
      deleteBtn.dataset.id = item.id;
      tdActions.appendChild(editBtn);
      tdActions.appendChild(deleteBtn);
      tr.appendChild(tdActions);
      itemsTableBody.appendChild(tr);
    });
  }

  // Save items array to localStorage
  function saveItems() {
    localStorage.setItem('menuItems', JSON.stringify(items));
  }

  // Handle form submission to add a new item
  itemForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const nameInput = document.getElementById('itemName');
    const descInput = document.getElementById('itemDesc');
    const priceInput = document.getElementById('itemPrice');

    const newItem = {
      id: Date.now(),
      name: nameInput.value.trim(),
      description: descInput.value.trim(),
      price: parseFloat(priceInput.value)
    };
    // Add item to array and save
    items.push(newItem);
    saveItems();
    renderTable();
    // Clear form
    itemForm.reset();
  });

  // Event delegation for edit and delete buttons
  itemsTableBody.addEventListener('click', (event) => {
    const target = event.target;
    if (target.tagName !== 'BUTTON') return;
    const id = Number(target.dataset.id);
    const itemIndex = items.findIndex((i) => i.id === id);
    if (itemIndex < 0) return;
    // Edit action
    if (target.textContent === 'Edit') {
      const currentItem = items[itemIndex];
      const newName = prompt('Enter new name:', currentItem.name);
      if (newName === null) return; // Cancelled
      const newDesc = prompt('Enter new description:', currentItem.description);
      if (newDesc === null) return;
      const newPriceStr = prompt('Enter new price:', currentItem.price.toString());
      if (newPriceStr === null) return;
      const newPrice = parseFloat(newPriceStr);
      if (isNaN(newPrice)) {
        alert('Invalid price');
        return;
      }
      // Update item
      currentItem.name = newName.trim();
      currentItem.description = newDesc.trim();
      currentItem.price = newPrice;
      saveItems();
      renderTable();
    } else if (target.textContent === 'Delete') {
      // Delete action
      if (confirm('Are you sure you want to delete this item?')) {
        items.splice(itemIndex, 1);
        saveItems();
        renderTable();
      }
    }
  });

  // Logout handler
  logoutBtn.addEventListener('click', () => {
    localStorage.removeItem('loggedIn');
    window.location.href = 'index.html';
  });

  // Initial render
  renderTable();
});