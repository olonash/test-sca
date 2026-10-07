const endpoint = '/dashboard-api';
let customerPage = 1;
let customerPageSize = 5;
let customerTotalPages = 0;
let customerSortBy = 'created_at';
let customerSortOrder = 'desc';

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });

  const json = await response.json();
  if (!response.ok) {
    throw new Error(json.error || 'Request failed');
  }

  return json;
}

async function loadCustomers() {
  const customerJson = document.getElementById('customerJson');
  const pageInfo = document.getElementById('customerPageInfo');
  const previousButton = document.getElementById('previousCustomerPage');
  const nextButton = document.getElementById('nextCustomerPage');

  previousButton.disabled = true;
  nextButton.disabled = true;

  try {
    const parameters = new URLSearchParams({
      page: String(customerPage),
      per_page: String(customerPageSize),
      order_by: customerSortBy,
      order: customerSortOrder,
    });
    const result = await fetchJson(`${endpoint}/customers?${parameters}`);
    const customers = result.customers;
    const pagination = result.pagination;
    customerPage = pagination.page;
    customerTotalPages = pagination.total_pages;
    customerJson.textContent = JSON.stringify(result, null, 2);
    pageInfo.textContent = pagination.total === 0
      ? 'Aucun client'
      : `Page ${pagination.page} sur ${pagination.total_pages} · ${pagination.total} clients`;

    previousButton.disabled = pagination.page <= 1;
    nextButton.disabled = pagination.page >= pagination.total_pages;
  } catch (error) {
    customerJson.textContent = JSON.stringify({ error: error.message }, null, 2);
    pageInfo.textContent = 'Impossible de charger les clients';
  }
}

async function ingestEvent() {
  const payload = {
    customer: {
      email: document.getElementById('customerEmail').value,
      name: document.getElementById('customerName').value,
    },
    event: document.getElementById('eventName').value,
    properties: {
      amount: Number(document.getElementById('eventAmount').value || 0),
      product: document.getElementById('eventProduct').value,
    },
    timestamp: new Date().toISOString(),
  };

  const response = await fetchJson(`${endpoint}/events`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  document.getElementById('eventResult').textContent = JSON.stringify(response, null, 2);
}

async function querySegments() {
  const payload = {
    conditions: [
      {
        event: 'purchase',
        property: 'amount',
        operator: '>',
        value: Number(document.getElementById('segmentThreshold').value || 100),
      },
    ],
  };

  const response = await fetchJson(`${endpoint}/segments/query`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  document.getElementById('segmentResult').textContent = JSON.stringify(response, null, 2);
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('previousCustomerPage')?.addEventListener('click', () => {
    if (customerPage > 1) {
      customerPage -= 1;
      loadCustomers();
    }
  });
  document.getElementById('nextCustomerPage')?.addEventListener('click', () => {
    if (customerPage < customerTotalPages) {
      customerPage += 1;
      loadCustomers();
    }
  });
  document.getElementById('customerPageSize')?.addEventListener('change', (event) => {
    customerPageSize = Number(event.target.value);
    customerPage = 1;
    loadCustomers();
  });
  document.getElementById('customerSortBy')?.addEventListener('change', (event) => {
    customerSortBy = event.target.value;
    customerPage = 1;
    loadCustomers();
  });
  document.getElementById('customerSortOrder')?.addEventListener('change', (event) => {
    customerSortOrder = event.target.value;
    customerPage = 1;
    loadCustomers();
  });
  document.getElementById('ingestButton')?.addEventListener('click', ingestEvent);
  document.getElementById('segmentButton')?.addEventListener('click', querySegments);
  loadCustomers();
});
