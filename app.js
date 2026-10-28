/* FleetFlow — Vehicle Rental Management System
   Standalone front end: routing, screens, forms, validation and the index.php client. */

(function () {
  'use strict';

  const API = 'index.php';

  const view = document.getElementById('view');
  const navEl = document.getElementById('nav');
  const titleEl = document.getElementById('page-title');
  const subEl = document.getElementById('page-sub');
  const sidebar = document.getElementById('sidebar');
  const scrim = document.getElementById('scrim');
  const modalRoot = document.getElementById('modal-root');
  const toastHost = document.getElementById('toasts');
  const todayChip = document.getElementById('today-chip');

  /* Charging rules — index.php is authoritative, these mirror it for the live preview. */
  const PRICING_RULES = {
    Car: { longRentDays: 7, discount: 0.1, note: 'Cars get 10% off rentals of 7 days or more.' },
    Motorcycle: { longRentDays: 3, discount: 0.05, note: 'Motorcycles get 5% off rentals of 3 days or more.' },
    Van: { longRentDays: 7, discount: 0.15, note: 'Vans get 15% off rentals of 7 days or more.' }
  };
  const LATE_FEE_RATE = 0.2;

  const VEHICLE_CATEGORIES = ['Car', 'Motorcycle', 'Van'];
  const TRANSMISSIONS = ['Automatic', 'Manual'];
  const FUEL_TYPES = ['Gasoline', 'Diesel', 'Electric', 'Hybrid'];
  const VEHICLE_STATUSES = ['Available', 'Rented', 'Reserved', 'Maintenance'];
  const RENTAL_STATUSES = ['Reserved', 'Active', 'Completed', 'Cancelled'];

  const PAGES = [
    { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', sub: 'Fleet, rental and revenue overview' },
    { key: 'vehicles', label: 'Vehicles', icon: 'car', sub: 'Fleet inventory and availability' },
    { key: 'customers', label: 'Customers', icon: 'users', sub: 'Renter records and contact details' },
    { key: 'rentals', label: 'Rentals', icon: 'key', sub: 'Bookings, charges and payments' },
    { key: 'reports', label: 'Reports', icon: 'chart', sub: 'Revenue and vehicle utilization' }
  ];

  const store = { vehicles: [], customers: [], rentals: [] };

  const filters = {
    vehicles: { q: '', status: 'All', category: 'All' },
    customers: { q: '' },
    rentals: { q: '', status: 'All' }
  };

  const reportRange = { from: '', to: '' };
  let rangeReady = false;

  /* ---------------- backend client ---------------- */
  async function api(params, body) {
    const query = new URLSearchParams(params).toString();
    const options = { headers: { 'Content-Type': 'application/json' } };
    if (body !== undefined) {
      options.method = 'POST';
      options.body = JSON.stringify(body);
    }

    let response;
    try {
      response = await fetch(API + '?' + query, options);
    } catch (error) {
      throw new Error('Could not reach index.php.');
    }

    const raw = await response.text();
    let payload = null;
    try {
      payload = raw ? JSON.parse(raw) : null;
    } catch (error) {
      throw new Error('index.php returned an unexpected response. Check your PHP version (7.4+).');
    }

    if (!payload || payload.ok === false) {
      throw new Error((payload && payload.error) || 'Request failed (' + response.status + ')');
    }
    return payload;
  }

  function applyData(data) {
    if (!data) return;
    if (Array.isArray(data.vehicles)) store.vehicles = data.vehicles;
    if (Array.isArray(data.customers)) store.customers = data.customers;
    if (Array.isArray(data.rentals)) store.rentals = data.rentals;
  }

  async function loadAll() {
    const payload = await api({ resource: 'all' });
    applyData(payload.data);
    if (!rangeReady) {
      reportRange.from = addDaysISO(todayISO(), -29);
      reportRange.to = todayISO();
      rangeReady = true;
    }
  }

  async function mutate(resource, action, payload, id) {
    const params = { resource: resource, action: action };
    if (id) params.id = id;
    const result = await api(params, payload || {});
    applyData(result.data);
    return result.message || 'Saved';
  }

  const vehicleById = function (id) {
    return store.vehicles.find(function (item) { return item.vehicle_id === id; }) || null;
  };
  const customerById = function (id) {
    return store.customers.find(function (item) { return item.customer_id === id; }) || null;
  };
  const rentalById = function (id) {
    return store.rentals.find(function (item) { return item.rental_id === id; }) || null;
  };

  /* ---------------- helpers ---------------- */
  const esc = function (value) {
    return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  };

  const icon = function (name, cls) {
    return '<svg class="ico ' + (cls || '') + '"><use href="#i-' + name + '"></use></svg>';
  };

  const div = function (cls) {
    const node = document.createElement('div');
    if (cls) node.className = cls;
    return node;
  };

  const sum = function (rows, key) {
    return rows.reduce(function (total, row) { return total + (Number(row[key]) || 0); }, 0);
  };

  const options = function (list, selected) {
    return list.map(function (item) {
      return '<option value="' + esc(item) + '"' + (item === selected ? ' selected' : '') + '>' + esc(item) + '</option>';
    }).join('');
  };

  function parse(value) {
    if (!value) return null;
    const date = new Date(String(value) + 'T00:00:00');
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function toISODate(date) {
    if (!date) return '';
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
  }

  function todayISO() { return toISODate(new Date()); }

  function addDaysISO(value, days) {
    const date = parse(value) || new Date();
    date.setDate(date.getDate() + Number(days || 0));
    return toISODate(date);
  }

  function daysBetween(start, end) {
    const a = parse(start);
    const b = parse(end);
    if (!a || !b) return 0;
    return Math.round((b - a) / 86400000);
  }

  function formatDate(value) {
    const date = parse(value);
    return date ? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
  }

  function formatLongDate(value) {
    const date = typeof value === 'string' ? parse(value) : value;
    return date ? date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '—';
  }

  function peso(amount) {
    return '₱' + (Number(amount) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /* ---------------- pricing (mirror of index.php) ---------------- */
  function rentalDays(start, end) {
    const span = daysBetween(start, end);
    return span > 0 ? span : 1;
  }

  function calculateCost(category, dailyRate, days) {
    const rule = PRICING_RULES[category] || PRICING_RULES.Car;
    const rate = Number(dailyRate) || 0;
    const count = Math.max(1, Number(days) || 1);
    const subtotal = rate * count;
    const discount = count >= rule.longRentDays ? subtotal * rule.discount : 0;
    return { days: count, subtotal: subtotal, discount: discount, total: subtotal - discount, rule: rule };
  }

  function lateDays(expectedEnd, actualReturn) {
    const span = daysBetween(expectedEnd, actualReturn);
    return span > 0 ? span : 0;
  }

  function lateFee(dailyRate, days) {
    return (Number(dailyRate) || 0) * LATE_FEE_RATE * Math.max(0, Number(days) || 0);
  }

  function paymentStatus(total, paid) {
    const due = Number(total) || 0;
    const received = Number(paid) || 0;
    if (received >= due && due > 0) return 'Paid';
    if (received > 0) return 'Partial';
    return 'Unpaid';
  }

  function derivedRentalStatus(startDate) {
    const start = parse(startDate);
    if (!start) return 'Reserved';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return start <= today ? 'Active' : 'Reserved';
  }

  function previewCharge(vehicle, values) {
    const days = rentalDays(values.start_date, values.end_date);
    const cost = calculateCost(vehicle.category, vehicle.daily_rate, days);
    const extra = values.actual_return_date ? lateDays(values.end_date, values.actual_return_date) : 0;
    const penalty = lateFee(vehicle.daily_rate, extra);
    const total = cost.total + penalty;
    const paid = Number(values.amount_paid) || 0;
    return {
      days: days,
      subtotal: cost.subtotal,
      discount: cost.discount,
      rule: cost.rule,
      lateDays: extra,
      lateFee: penalty,
      total: total,
      paid: paid,
      balance: Math.max(0, total - paid),
      paymentStatus: paymentStatus(total, paid)
    };
  }

  function nextId(prefix, records, field, width) {
    const numbers = records.map(function (record) {
      return Number(String(record[field] || '').replace(/[^0-9]/g, ''));
    }).filter(function (value) { return Number.isFinite(value) && value > 0; });

    const highest = numbers.length ? Math.max.apply(null, numbers) : 0;
    return prefix + '-' + String(highest + 1).padStart(width, '0');
  }

  /* ---------------- view helpers ---------------- */
  const TONES = {
    vehicle: { Available: 'emerald', Rented: 'blue', Reserved: 'violet', Maintenance: 'amber' },
    rental: { Active: 'blue', Completed: 'emerald', Reserved: 'violet', Cancelled: 'slate' },
    payment: { Paid: 'emerald', Partial: 'amber', Unpaid: 'rose' }
  };

  const badge = function (status, kind) {
    const tone = (TONES[kind] || {})[status] || '';
    return '<span class="badge ' + tone + '">' + esc(status || '—') + '</span>';
  };

  const statCard = function (data) {
    return (
      '<div class="card stat"><div class="stat-top"><div>' +
      '<p class="stat-label">' + esc(data.label) + '</p>' +
      '<p class="stat-value">' + esc(data.value) + '</p>' +
      (data.hint ? '<p class="stat-hint">' + esc(data.hint) + '</p>' : '') +
      '</div><span class="stat-icon ' + (data.tone || '') + '">' + icon(data.icon) + '</span></div></div>'
    );
  };

  const sectionCard = function (title, sub, body, action) {
    return (
      '<section class="card card-pad"><div class="card-head"><div><h3 class="card-title">' + esc(title) + '</h3>' +
      (sub ? '<p class="card-sub">' + esc(sub) + '</p>' : '') +
      '</div>' + (action || '') + '</div>' + body + '</section>'
    );
  };

  const dataTable = function (headers, rowsHtml, emptyText) {
    if (!rowsHtml) return '<p class="empty">' + esc(emptyText || 'Nothing to show yet.') + '</p>';
    return (
      '<div class="table-wrap"><table class="data"><thead><tr>' +
      headers.map(function (label) { return '<th>' + esc(label) + '</th>'; }).join('') +
      '</tr></thead><tbody>' + rowsHtml + '</tbody></table></div>'
    );
  };

  const vehicleLabel = function (vehicle) {
    if (!vehicle) return 'Unassigned vehicle';
    return ((vehicle.brand || '') + ' ' + (vehicle.model || '')).trim() || vehicle.vehicle_id;
  };

  const customerLabel = function (customer) {
    return customer ? customer.full_name : 'Unknown customer';
  };

  const toast = function (message, isError) {
    const node = document.createElement('div');
    node.className = 'toast' + (isError ? ' error' : '');
    node.textContent = message;
    toastHost.appendChild(node);
    window.setTimeout(function () { node.remove(); }, 3000);
  };

  /* ---------------- modal ---------------- */
  let modalCleanup = null;

  function closeModal() {
    modalRoot.classList.remove('show');
    modalRoot.innerHTML = '';
    if (modalCleanup) modalCleanup();
    modalCleanup = null;
  }

  function openModal(config) {
    modalRoot.innerHTML =
      '<div class="modal-overlay"><form class="modal ' + (config.wide ? '' : 'narrow') + '" novalidate>' +
      '<div class="modal-head"><div><h3>' + esc(config.title) + '</h3>' +
      (config.sub ? '<p class="card-sub">' + esc(config.sub) + '</p>' : '') +
      '</div><button type="button" class="modal-close" data-action="close-modal" aria-label="Close">&times;</button></div>' +
      '<div class="modal-body">' + config.body + '<p class="form-error" data-error hidden></p></div>' +
      '<div class="modal-foot"><button type="button" class="btn outline" data-action="close-modal">Cancel</button>' +
      '<button type="submit" class="btn">' + esc(config.submitLabel || 'Save') + '</button></div></form></div>';
    modalRoot.classList.add('show');

    const form = modalRoot.querySelector('form');
    const errorBox = form.querySelector('[data-error]');
    const submitButton = form.querySelector('button[type="submit"]');

    function showError(message) {
      errorBox.textContent = message;
      errorBox.hidden = false;
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      errorBox.hidden = true;
      submitButton.disabled = true;

      const values = {};
      new FormData(form).forEach(function (value, key) { values[key] = String(value); });

      try {
        const error = await config.onSubmit(values, form);
        if (error) {
          showError(error);
          submitButton.disabled = false;
          return;
        }
        closeModal();
      } catch (failure) {
        showError(failure.message || 'Could not save.');
        submitButton.disabled = false;
      }
    });

    form.addEventListener('input', function () { errorBox.hidden = true; });

    if (config.onInput) {
      form.addEventListener('input', function () { config.onInput(form); });
      form.addEventListener('change', function () { config.onInput(form); });
      config.onInput(form);
    }

    const overlay = modalRoot.querySelector('.modal-overlay');
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) closeModal();
    });

    const onKey = function (event) {
      if (event.key === 'Escape') closeModal();
    };
    document.addEventListener('keydown', onKey);
    modalCleanup = function () { document.removeEventListener('keydown', onKey); };

    const first = form.querySelector('input:not([readonly]), select, textarea');
    if (first) first.focus();
  }

  function confirmDialog(config) {
    openModal({
      title: config.title,
      sub: config.sub,
      submitLabel: config.submitLabel || 'Delete',
      body: '<p class="report-note">' + esc(config.message) + '</p>',
      onSubmit: async function () {
        await config.onConfirm();
        return null;
      }
    });

    const button = modalRoot.querySelector('button[type="submit"]');
    if (button) button.className = 'btn danger';
  }

  /* ---------------- rentals table rows ---------------- */
  const RENTAL_HEADERS = ['Rental', 'Customer', 'Vehicle', 'Period', 'Total', 'Status'];

  function rowActions(rental) {
    const live = rental.rental_status === 'Active' || rental.rental_status === 'Reserved';
    return (
      (live
        ? '<button class="btn sm outline" data-action="return-rental" data-id="' + esc(rental.rental_id) + '" title="Record return">' + icon('check') + ' Return</button>'
        : '') +
      '<button class="btn sm outline" data-action="edit-rental" data-id="' + esc(rental.rental_id) + '" title="Edit rental">' + icon('edit') + '</button>' +
      '<button class="btn sm danger" data-action="delete-rental" data-id="' + esc(rental.rental_id) + '" title="Delete rental">' + icon('trash') + '</button>'
    );
  }

  function rentalRows(rentals, withActions) {
    return rentals.map(function (rental) {
      const customer = customerById(rental.customer_id);
      const vehicle = vehicleById(rental.vehicle_id);
      return (
        '<tr>' +
        '<td class="cell-strong">' + esc(rental.rental_id) + '<p class="cell-sub">' + esc(formatDate(rental.start_date)) + '</p></td>' +
        '<td>' + esc(customerLabel(customer)) + '</td>' +
        '<td>' + esc(vehicleLabel(vehicle)) + '<p class="cell-sub">' + esc(vehicle ? vehicle.vehicle_id : '—') + '</p></td>' +
        '<td class="cell-nowrap">' + esc(formatDate(rental.start_date)) + ' &rarr; ' + esc(formatDate(rental.end_date)) + '<p class="cell-sub">' + rental.rental_days + ' day(s)</p></td>' +
        '<td class="cell-nowrap cell-strong">' + peso(rental.total_cost) + '<p class="cell-sub">' + peso(rental.amount_paid) + ' paid</p></td>' +
        '<td>' + badge(rental.rental_status, 'rental') + '<p class="cell-sub">' + badge(rental.payment_status, 'payment') + '</p></td>' +
        (withActions ? '<td><div class="row-actions">' + rowActions(rental) + '</div></td>' : '') +
        '</tr>'
      );
    }).join('');
  }

  /* ---------------- dashboard ---------------- */
  function monthlyRevenue(rentals, months) {
    const out = [];
    const now = new Date();
    for (let offset = months - 1; offset >= 0; offset -= 1) {
      const date = new Date(now.getFullYear(), now.getMonth() - offset, 1);
      const key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
      const rows = rentals.filter(function (rental) { return String(rental.start_date).indexOf(key) === 0; });
      out.push({
        label: date.toLocaleDateString('en-US', { month: 'short' }),
        charged: sum(rows, 'total_cost'),
        collected: sum(rows, 'amount_paid')
      });
    }
    return out;
  }

  const FLEET_COLORS = { Available: '#059669', Rented: '#2563eb', Reserved: '#7c3aed', Maintenance: '#d97706' };

  function dashboardView() {
    const vehicles = store.vehicles;
    const rentals = store.rentals;
    const customers = store.customers;
    const active = rentals.filter(function (r) { return r.rental_status === 'Active'; });
    const reserved = rentals.filter(function (r) { return r.rental_status === 'Reserved'; });
    const available = vehicles.filter(function (v) { return v.status === 'Available'; });
    const collected = sum(rentals, 'amount_paid');
    const charged = sum(rentals, 'total_cost');

    const fleetItems = VEHICLE_STATUSES.map(function (status) {
      return {
        label: status,
        count: vehicles.filter(function (vehicle) { return vehicle.status === status; }).length,
        color: FLEET_COLORS[status]
      };
    });

    const topVehicles = vehicles.map(function (vehicle) {
      return {
        label: vehicleLabel(vehicle),
        value: rentals.filter(function (rental) { return rental.vehicle_id === vehicle.vehicle_id; }).length
      };
    }).sort(function (a, b) { return b.value - a.value; }).slice(0, 5);

    const recent = rentals.slice().sort(function (a, b) {
      return String(b.start_date).localeCompare(String(a.start_date));
    }).slice(0, 5);

    const node = div('stack');
    node.innerHTML =
      '<div class="toolbar">' +
      '<button class="btn" data-action="new-rental">' + icon('plus') + ' New rental</button>' +
      '<button class="btn outline" data-action="new-vehicle">' + icon('car') + ' Add vehicle</button>' +
      '<button class="btn outline" data-action="new-customer">' + icon('users') + ' Add customer</button>' +
      '</div>' +

      '<div class="stat-grid">' +
      statCard({ icon: 'car', label: 'Fleet size', value: vehicles.length, hint: available.length + ' available now' }) +
      statCard({ icon: 'key', label: 'Live rentals', value: active.length + reserved.length, hint: active.length + ' active · ' + reserved.length + ' reserved', tone: 'violet' }) +
      statCard({ icon: 'users', label: 'Customers', value: customers.length, hint: 'Registered renters', tone: 'slate' }) +
      statCard({ icon: 'money', label: 'Revenue collected', value: peso(collected), hint: peso(Math.max(0, charged - collected)) + ' outstanding', tone: 'emerald' }) +
      '</div>' +

      '<div class="grid-charts">' +
      sectionCard('Revenue trend', 'Charged vs collected over the last 6 months',
        '<div data-chart="revenue"></div>' +
        '<div class="chart-legend"><span><i style="background:#2563eb"></i>Charged</span><span><i style="background:#059669"></i>Collected</span></div>') +
      sectionCard('Fleet status', 'Availability across ' + vehicles.length + ' vehicle(s)',
        '<div class="donut-wrap"><div data-chart="fleet" style="flex:0 1 190px"></div><div class="legend-list">' +
        fleetItems.map(function (item) {
          return '<div class="legend-row"><i style="background:' + item.color + '"></i>' + esc(item.label) + '<span class="count">' + item.count + '</span></div>';
        }).join('') + '</div></div>') +
      '</div>' +

      '<div class="grid-2">' +
      sectionCard('Recent rentals', 'Latest bookings by start date', dataTable(RENTAL_HEADERS, rentalRows(recent, false), 'No rentals recorded yet.')) +
      sectionCard('Most rented vehicles', 'Bookings recorded per vehicle', '<div data-chart="top"></div>') +
      '</div>';

    node.querySelector('[data-chart="revenue"]').appendChild(
      window.Charts.revenueBarChart(monthlyRevenue(rentals, 6), [
        { key: 'charged', label: 'Charged', color: '#2563eb' },
        { key: 'collected', label: 'Collected', color: '#059669' }
      ])
    );
    node.querySelector('[data-chart="fleet"]').appendChild(window.Charts.donutChart(fleetItems, 'Vehicles'));
    node.querySelector('[data-chart="top"]').appendChild(
      window.Charts.hBarList(topVehicles, function (value) { return value + ' rental(s)'; })
    );

    return node;
  }

  /* ---------------- vehicles ---------------- */
  function vehiclesView() {
    const state = filters.vehicles;
    const vehicles = store.vehicles.filter(function (vehicle) {
      const haystack = (vehicle.vehicle_id + ' ' + vehicle.brand + ' ' + vehicle.model + ' ' + vehicle.plate_number).toLowerCase();
      return haystack.indexOf(state.q.toLowerCase()) !== -1 &&
        (state.status === 'All' || vehicle.status === state.status) &&
        (state.category === 'All' || vehicle.category === state.category);
    });

    const rows = vehicles.map(function (vehicle) {
      return (
        '<tr>' +
        '<td class="cell-strong">' + esc(vehicleLabel(vehicle)) + '<p class="cell-sub">' + esc(vehicle.vehicle_id) + ' · ' + esc(vehicle.year) + '</p></td>' +
        '<td class="cell-nowrap">' + esc(vehicle.plate_number) + '</td>' +
        '<td>' + esc(vehicle.category) + '<p class="cell-sub">' + esc(vehicle.transmission) + ' · ' + esc(vehicle.fuel_type) + '</p></td>' +
        '<td class="cell-nowrap">' + esc(vehicle.seating_capacity) + ' seats</td>' +
        '<td class="cell-nowrap cell-strong">' + peso(vehicle.daily_rate) + '<p class="cell-sub">per day</p></td>' +
        '<td>' + badge(vehicle.status, 'vehicle') + '</td>' +
        '<td><div class="row-actions">' +
        '<button class="btn sm outline" data-action="edit-vehicle" data-id="' + esc(vehicle.vehicle_id) + '" title="Edit vehicle">' + icon('edit') + '</button>' +
        '<button class="btn sm danger" data-action="delete-vehicle" data-id="' + esc(vehicle.vehicle_id) + '" title="Delete vehicle">' + icon('trash') + '</button>' +
        '</div></td></tr>'
      );
    }).join('');

    const node = div('stack');
    node.innerHTML =
      '<div class="toolbar">' +
      '<div class="field"><label for="vehicle-search">Search</label><input id="vehicle-search" class="input-wide-search" type="text" placeholder="ID, brand, model or plate" data-filter="vehicles.q" value="' + esc(state.q) + '" /></div>' +
      '<div class="field"><label for="vehicle-status">Status</label><select id="vehicle-status" class="w-sm" data-filter="vehicles.status">' + options(['All'].concat(VEHICLE_STATUSES), state.status) + '</select></div>' +
      '<div class="field"><label for="vehicle-category">Category</label><select id="vehicle-category" class="w-sm" data-filter="vehicles.category">' + options(['All'].concat(VEHICLE_CATEGORIES), state.category) + '</select></div>' +
      '<div class="spacer"></div>' +
      '<button class="btn" data-action="new-vehicle">' + icon('plus') + ' Add vehicle</button>' +
      '</div>' +
      sectionCard('Fleet inventory', vehicles.length + ' of ' + store.vehicles.length + ' vehicle(s) shown',
        dataTable(['Vehicle', 'Plate', 'Category', 'Seats', 'Daily rate', 'Status', ''], rows, 'No vehicles match these filters.'));

    return node;
  }

  function openVehicleForm(vehicle) {
    const editing = Boolean(vehicle);
    const draft = Object.assign({
      vehicle_id: nextId('V', store.vehicles, 'vehicle_id', 3),
      brand: '', model: '', year: new Date().getFullYear(), plate_number: '',
      category: 'Car', transmission: 'Automatic', fuel_type: 'Gasoline',
      seating_capacity: 5, daily_rate: 1500, status: 'Available', description: ''
    }, vehicle || {});

    openModal({
      title: editing ? 'Edit ' + draft.vehicle_id : 'Add a vehicle',
      sub: editing ? 'Update this fleet record' : 'Register a new vehicle in the fleet',
      wide: true,
      submitLabel: editing ? 'Save vehicle' : 'Add vehicle',
      body:
        '<div class="form-grid">' +
        '<div class="field"><label for="v-id">Vehicle ID</label><input id="v-id" name="vehicle_id" value="' + esc(draft.vehicle_id) + '"' + (editing ? ' readonly' : '') + ' /></div>' +
        '<div class="field"><label for="v-plate">Plate number</label><input id="v-plate" name="plate_number" value="' + esc(draft.plate_number) + '" /></div>' +
        '<div class="field"><label for="v-brand">Brand</label><input id="v-brand" name="brand" value="' + esc(draft.brand) + '" /></div>' +
        '<div class="field"><label for="v-model">Model</label><input id="v-model" name="model" value="' + esc(draft.model) + '" /></div>' +
        '<div class="field"><label for="v-year">Year</label><input id="v-year" name="year" type="number" min="1980" max="2100" value="' + esc(draft.year) + '" /></div>' +
        '<div class="field"><label for="v-category">Category</label><select id="v-category" name="category">' + options(VEHICLE_CATEGORIES, draft.category) + '</select></div>' +
        '<div class="field"><label for="v-transmission">Transmission</label><select id="v-transmission" name="transmission">' + options(TRANSMISSIONS, draft.transmission) + '</select></div>' +
        '<div class="field"><label for="v-fuel">Fuel type</label><select id="v-fuel" name="fuel_type">' + options(FUEL_TYPES, draft.fuel_type) + '</select></div>' +
        '<div class="field"><label for="v-seats">Seating capacity</label><input id="v-seats" name="seating_capacity" type="number" min="1" max="30" value="' + esc(draft.seating_capacity) + '" /></div>' +
        '<div class="field"><label for="v-rate">Daily rate (₱)</label><input id="v-rate" name="daily_rate" type="number" min="1" step="50" value="' + esc(draft.daily_rate) + '" /></div>' +
        '<div class="field"><label for="v-status">Status</label><select id="v-status" name="status">' + options(VEHICLE_STATUSES, draft.status) + '</select></div>' +
        '<div class="field span-2"><label for="v-description">Description</label><textarea id="v-description" name="description">' + esc(draft.description) + '</textarea></div>' +
        '</div>',
      onSubmit: async function (values) {
        if (!values.vehicle_id.trim() || !values.brand.trim() || !values.model.trim() || !values.plate_number.trim()) {
          return 'Vehicle ID, brand, model and plate number are required.';
        }
        await mutate('vehicles', editing ? 'update' : 'create', values, editing ? vehicle.vehicle_id : null);
        toast(editing ? 'Vehicle updated' : 'Vehicle added');
        render();
        return null;
      }
    });
  }

  /* ---------------- customers ---------------- */
  function customersView() {
    const state = filters.customers;
    const customers = store.customers.filter(function (customer) {
      const haystack = (customer.customer_id + ' ' + customer.full_name + ' ' + customer.phone + ' ' + customer.email + ' ' + customer.license_number).toLowerCase();
      return haystack.indexOf(state.q.toLowerCase()) !== -1;
    });

    const rows = customers.map(function (customer) {
      const count = store.rentals.filter(function (rental) { return rental.customer_id === customer.customer_id; }).length;
      return (
        '<tr>' +
        '<td class="cell-strong">' + esc(customer.full_name) + '<p class="cell-sub">' + esc(customer.customer_id) + '</p></td>' +
        '<td class="cell-nowrap">' + esc(customer.phone) + '</td>' +
        '<td>' + esc(customer.email || '—') + '<p class="cell-sub">' + esc(customer.address || 'No address on file') + '</p></td>' +
        '<td class="cell-nowrap">' + esc(customer.license_number) + '</td>' +
        '<td class="cell-nowrap">' + count + ' rental(s)</td>' +
        '<td><div class="row-actions">' +
        '<button class="btn sm outline" data-action="edit-customer" data-id="' + esc(customer.customer_id) + '" title="Edit customer">' + icon('edit') + '</button>' +
        '<button class="btn sm danger" data-action="delete-customer" data-id="' + esc(customer.customer_id) + '" title="Delete customer">' + icon('trash') + '</button>' +
        '</div></td></tr>'
      );
    }).join('');

    const node = div('stack');
    node.innerHTML =
      '<div class="toolbar">' +
      '<div class="field"><label for="customer-search">Search</label><input id="customer-search" class="input-wide-search" type="text" placeholder="ID, name, phone or licence" data-filter="customers.q" value="' + esc(state.q) + '" /></div>' +
      '<div class="spacer"></div>' +
      '<button class="btn" data-action="new-customer">' + icon('plus') + ' Add customer</button>' +
      '</div>' +
      sectionCard('Customer records', customers.length + ' of ' + store.customers.length + ' customer(s) shown',
        dataTable(['Customer', 'Phone', 'Email', 'Licence', 'History', ''], rows, 'No customers match this search.'));

    return node;
  }

  function openCustomerForm(customer) {
    const editing = Boolean(customer);
    const draft = Object.assign({
      customer_id: nextId('C', store.customers, 'customer_id', 3),
      full_name: '', phone: '', email: '', address: '', license_number: ''
    }, customer || {});

    openModal({
      title: editing ? 'Edit ' + draft.full_name : 'Add a customer',
      sub: editing ? 'Update this renter record' : 'Register a new renter',
      submitLabel: editing ? 'Save customer' : 'Add customer',
      body:
        '<div class="form-grid">' +
        '<div class="field"><label for="c-id">Customer ID</label><input id="c-id" name="customer_id" value="' + esc(draft.customer_id) + '"' + (editing ? ' readonly' : '') + ' /></div>' +
        '<div class="field"><label for="c-name">Full name</label><input id="c-name" name="full_name" value="' + esc(draft.full_name) + '" /></div>' +
        '<div class="field"><label for="c-phone">Phone</label><input id="c-phone" name="phone" value="' + esc(draft.phone) + '" /></div>' +
        '<div class="field"><label for="c-email">Email</label><input id="c-email" name="email" type="email" value="' + esc(draft.email) + '" /></div>' +
        '<div class="field"><label for="c-license">Licence number</label><input id="c-license" name="license_number" value="' + esc(draft.license_number) + '" /></div>' +
        '<div class="field span-2"><label for="c-address">Address</label><input id="c-address" name="address" value="' + esc(draft.address) + '" /></div>' +
        '</div>',
      onSubmit: async function (values) {
        if (!values.customer_id.trim() || !values.full_name.trim() || !values.phone.trim() || !values.license_number.trim()) {
          return 'Customer ID, full name, phone and licence number are required.';
        }
        await mutate('customers', editing ? 'update' : 'create', values, editing ? customer.customer_id : null);
        toast(editing ? 'Customer updated' : 'Customer added');
        render();
        return null;
      }
    });
  }

  /* ---------------- rentals ---------------- */
  function rentalsView() {
    const state = filters.rentals;
    const rentals = store.rentals.filter(function (rental) {
      const customer = customerById(rental.customer_id);
      const vehicle = vehicleById(rental.vehicle_id);
      const haystack = (rental.rental_id + ' ' + customerLabel(customer) + ' ' + vehicleLabel(vehicle) + ' ' + (vehicle ? vehicle.vehicle_id : '')).toLowerCase();
      return haystack.indexOf(state.q.toLowerCase()) !== -1 && (state.status === 'All' || rental.rental_status === state.status);
    }).slice().sort(function (a, b) {
      return String(b.start_date).localeCompare(String(a.start_date));
    });

    const node = div('stack');
    node.innerHTML =
      '<div class="toolbar">' +
      '<div class="field"><label for="rental-search">Search</label><input id="rental-search" class="input-wide-search" type="text" placeholder="Rental ID, customer or vehicle" data-filter="rentals.q" value="' + esc(state.q) + '" /></div>' +
      '<div class="field"><label for="rental-status">Status</label><select id="rental-status" class="w-sm" data-filter="rentals.status">' + options(['All'].concat(RENTAL_STATUSES), state.status) + '</select></div>' +
      '<div class="spacer"></div>' +
      '<button class="btn" data-action="new-rental">' + icon('plus') + ' New rental</button>' +
      '</div>' +
      sectionCard('Rental transactions', rentals.length + ' of ' + store.rentals.length + ' booking(s) shown',
        dataTable(RENTAL_HEADERS.concat(['']), rentalRows(rentals, true), 'No rentals match these filters.'));

    return node;
  }

  function updateRentalCost(form) {
    const values = {};
    new FormData(form).forEach(function (value, key) { values[key] = String(value); });

    const box = form.querySelector('[data-cost]');
    if (!box) return;

    const vehicle = vehicleById(values.vehicle_id);
    if (!vehicle || !values.start_date || !values.end_date) {
      box.innerHTML = '<p class="report-note">Choose a vehicle and rental dates to calculate the charge.</p>';
      return;
    }

    const charge = previewCharge(vehicle, values);
    const note = charge.discount
      ? charge.rule.note
      : 'No discount applies for this rental length.';

    box.innerHTML =
      '<div class="cost-row"><span>' + charge.days + ' day(s) &times; ' + peso(vehicle.daily_rate) + ' <span class="cell-sub">(' + esc(vehicle.category) + ')</span></span><span>' + peso(charge.subtotal) + '</span></div>' +
      (charge.discount ? '<div class="cost-row"><span>Volume discount</span><span>&minus;' + peso(charge.discount) + '</span></div>' : '') +
      (charge.lateFee ? '<div class="cost-row"><span>Late fee &mdash; ' + charge.lateDays + ' day(s) at 20% of the daily rate</span><span>' + peso(charge.lateFee) + '</span></div>' : '') +
      '<div class="cost-row"><span>Rental charge</span><span>' + peso(charge.total) + '</span></div>' +
      '<div class="cost-row"><span>Amount paid</span><span>' + peso(charge.paid) + '</span></div>' +
      '<div class="cost-row total"><span>Balance due</span><span>' + peso(charge.balance) + ' · ' + esc(charge.paymentStatus) + '</span></div>' +
      '<p class="report-note">' + esc(note) + '</p>';
  }

  function openRentalForm(rental) {
    const editing = Boolean(rental);
    if (!store.customers.length || !store.vehicles.length) {
      toast('Add at least one customer and one vehicle first', true);
      return;
    }

    const draft = Object.assign({
      rental_id: nextId('R', store.rentals, 'rental_id', 4),
      customer_id: store.customers[0].customer_id,
      vehicle_id: store.vehicles[0].vehicle_id,
      start_date: todayISO(),
      end_date: addDaysISO(todayISO(), 2),
      actual_return_date: '',
      amount_paid: 0,
      notes: '',
      rental_status: derivedRentalStatus(todayISO())
    }, rental || {});

    const customerOptions = store.customers.map(function (customer) {
      return '<option value="' + esc(customer.customer_id) + '"' + (customer.customer_id === draft.customer_id ? ' selected' : '') + '>' +
        esc(customer.customer_id + ' · ' + customer.full_name) + '</option>';
    }).join('');

    const vehicleOptions = store.vehicles.map(function (vehicle) {
      return '<option value="' + esc(vehicle.vehicle_id) + '"' + (vehicle.vehicle_id === draft.vehicle_id ? ' selected' : '') + '>' +
        esc(vehicle.vehicle_id + ' · ' + vehicleLabel(vehicle) + ' · ' + peso(vehicle.daily_rate) + '/day' + (vehicle.status === 'Maintenance' ? ' (maintenance)' : '')) + '</option>';
    }).join('');

    openModal({
      title: editing ? 'Edit rental ' + draft.rental_id : 'New rental ' + draft.rental_id,
      sub: 'Charges are calculated by index.php from the vehicle rate, rental length and category discount',
      wide: true,
      submitLabel: editing ? 'Save rental' : 'Create rental',
      body:
        '<div class="form-grid">' +
        '<div class="field"><label for="r-customer">Customer</label><select id="r-customer" name="customer_id">' + customerOptions + '</select></div>' +
        '<div class="field"><label for="r-vehicle">Vehicle</label><select id="r-vehicle" name="vehicle_id">' + vehicleOptions + '</select></div>' +
        '<div class="field"><label for="r-start">Start date</label><input id="r-start" name="start_date" type="date" value="' + esc(draft.start_date) + '" /></div>' +
        '<div class="field"><label for="r-end">Expected return date</label><input id="r-end" name="end_date" type="date" value="' + esc(draft.end_date) + '" /></div>' +
        (editing
          ? '<div class="field"><label for="r-status">Rental status</label><select id="r-status" name="rental_status">' + options(RENTAL_STATUSES, draft.rental_status) + '</select></div>'
          : '<div class="field"><label for="r-status">Rental status</label><input id="r-status" value="' + esc(draft.rental_status) + '" readonly /><span class="hint">Set automatically from the start date</span></div>') +
        '<div class="field"><label for="r-returned">Actual return date</label><input id="r-returned" name="actual_return_date" type="date" value="' + esc(draft.actual_return_date) + '" /><span class="hint">Later than expected adds a 20% late fee per day</span></div>' +
        '<div class="field"><label for="r-paid">Amount paid (₱)</label><input id="r-paid" name="amount_paid" type="number" min="0" step="10" value="' + esc(draft.amount_paid) + '" /></div>' +
        '<div class="field span-2"><label for="r-notes">Notes</label><textarea id="r-notes" name="notes">' + esc(draft.notes) + '</textarea></div>' +
        '<div class="cost-box" data-cost></div>' +
        '</div>',
      onInput: updateRentalCost,
      onSubmit: async function (values) {
        if (!values.customer_id) return 'Choose a customer for this rental.';
        if (!values.vehicle_id) return 'Choose a vehicle for this rental.';
        if (!values.start_date || !values.end_date) return 'Enter the rental start and expected return dates.';

        await mutate('rentals', editing ? 'update' : 'create', {
          rental_id: editing ? rental.rental_id : draft.rental_id,
          customer_id: values.customer_id,
          vehicle_id: values.vehicle_id,
          start_date: values.start_date,
          end_date: values.end_date,
          actual_return_date: values.actual_return_date || '',
          amount_paid: values.amount_paid,
          rental_status: editing ? values.rental_status : derivedRentalStatus(values.start_date),
          notes: values.notes
        }, editing ? rental.rental_id : null);

        toast(editing ? 'Rental updated' : 'Rental ' + draft.rental_id + ' created');
        render();
        return null;
      }
    });
  }

  async function recordReturn(rentalId) {
    const message = await mutate('rentals', 'return', {}, rentalId);
    toast(message);
    render();
  }

  /* ---------------- reports ---------------- */
  function buildReport(from, to) {
    const periodDays = Math.max(1, daysBetween(from, to) + 1);
    const rentals = store.rentals.filter(function (rental) {
      return rental.start_date >= from && rental.start_date <= to;
    });
    const vehicles = store.vehicles;
    const capacity = vehicles.length * periodDays;

    const byVehicle = vehicles.map(function (vehicle) {
      const rows = rentals.filter(function (rental) { return rental.vehicle_id === vehicle.vehicle_id; });
      const rented = sum(rows, 'rental_days');
      return {
        vehicle: vehicle,
        bookings: rows.length,
        daysRented: rented,
        revenue: sum(rows, 'total_cost'),
        utilization: Math.min(100, Math.round((rented / periodDays) * 100))
      };
    }).sort(function (a, b) { return b.daysRented - a.daysRented || b.bookings - a.bookings; });

    const daysRented = sum(rentals, 'rental_days');
    const charged = sum(rentals, 'total_cost');
    const collected = sum(rentals, 'amount_paid');

    return {
      period: { from: from, to: to, days: periodDays },
      rentals: rentals.slice().sort(function (a, b) { return String(b.start_date).localeCompare(String(a.start_date)); }),
      byVehicle: byVehicle,
      totals: {
        charged: charged,
        collected: collected,
        outstanding: Math.max(0, charged - collected),
        lateFee: sum(rentals, 'late_fee'),
        bookings: rentals.length,
        daysRented: daysRented,
        fleetSize: vehicles.length,
        averageBooking: rentals.length ? charged / rentals.length : 0,
        utilization: capacity ? Math.min(100, Math.round((daysRented / capacity) * 100)) : 0
      }
    };
  }

  function reportsView() {
    if (!reportRange.from || !reportRange.to) {
      reportRange.from = addDaysISO(todayISO(), -29);
      reportRange.to = todayISO();
    }

    const report = buildReport(reportRange.from, reportRange.to);

    const utilizationRows = report.byVehicle.map(function (row) {
      return (
        '<tr>' +
        '<td class="cell-strong">' + esc(vehicleLabel(row.vehicle)) + '<p class="cell-sub">' + esc(row.vehicle.vehicle_id) + ' · ' + esc(row.vehicle.status) + '</p></td>' +
        '<td>' + esc(row.vehicle.category) + '</td>' +
        '<td>' + row.bookings + '</td>' +
        '<td class="cell-nowrap">' + row.daysRented + ' day(s)</td>' +
        '<td><div class="util-cell"><div class="bar-track"><div class="bar-fill" style="width:' + row.utilization + '%"></div></div><span class="cell-sub">' + row.utilization + '%</span></div></td>' +
        '<td class="cell-nowrap cell-strong">' + peso(row.revenue) + '</td>' +
        '</tr>'
      );
    }).join('');

    const node = div('stack');
    node.innerHTML =
      '<div class="toolbar">' +
      '<div class="field"><label for="report-from">From</label><input id="report-from" class="w-sm" type="date" value="' + esc(reportRange.from) + '" /></div>' +
      '<div class="field"><label for="report-to">To</label><input id="report-to" class="w-sm" type="date" value="' + esc(reportRange.to) + '" /></div>' +
      '<button class="btn" data-action="generate-report">' + icon('calendar') + ' Generate report</button>' +
      '<div class="spacer"></div>' +
      '<button class="btn outline" data-action="print-report">' + icon('printer') + ' Print / save as PDF</button>' +
      '</div>' +

      '<section class="report">' +
      '<div class="report-head"><div>' +
      '<h3>Vehicle rental management report</h3>' +
      '<p>' + esc(formatLongDate(report.period.from)) + ' &ndash; ' + esc(formatLongDate(report.period.to)) + ' · ' + report.period.days + ' day(s)</p>' +
      '</div><p class="report-note">Generated ' + esc(formatLongDate(new Date())) + '</p></div>' +

      '<div class="stat-grid six" style="margin-bottom:1.25rem">' +
      statCard({ icon: 'money', label: 'Total revenue', value: peso(report.totals.charged), hint: peso(report.totals.lateFee) + ' in late fees' }) +
      statCard({ icon: 'wallet', label: 'Collected', value: peso(report.totals.collected), hint: 'Payments received', tone: 'emerald' }) +
      statCard({ icon: 'trending', label: 'Outstanding', value: peso(report.totals.outstanding), hint: 'Still to be collected', tone: 'amber' }) +
      statCard({ icon: 'calendar', label: 'Rentals', value: report.totals.bookings, hint: 'Avg ' + peso(report.totals.averageBooking) + ' each', tone: 'slate' }) +
      statCard({ icon: 'clock', label: 'Days rented', value: report.totals.daysRented, hint: 'Across ' + report.period.days + ' days', tone: 'violet' }) +
      statCard({ icon: 'car', label: 'Fleet utilization', value: report.totals.utilization + '%', hint: report.totals.fleetSize + ' vehicle(s) in fleet' }) +
      '</div>' +

      sectionCard('Vehicle utilization', 'Booked days and revenue per vehicle for the selected period',
        dataTable(['Vehicle', 'Category', 'Bookings', 'Days rented', 'Utilization (' + report.period.days + ' days)', 'Revenue'], utilizationRows, 'No vehicles registered yet.')) +

      '<div style="height:1.25rem"></div>' +

      sectionCard('Rental transactions', report.rentals.length + ' rental(s) started in the selected period',
        dataTable(RENTAL_HEADERS, rentalRows(report.rentals, false), 'No rentals started in this period.')) +
      '</section>';

    const fromInput = node.querySelector('#report-from');
    const toInput = node.querySelector('#report-to');
    fromInput.addEventListener('change', function () { reportRange.from = fromInput.value; });
    toInput.addEventListener('change', function () { reportRange.to = toInput.value; });

    return node;
  }

  /* ---------------- routing ---------------- */
  function buildNav() {
    navEl.innerHTML = PAGES.map(function (page) {
      return '<a class="nav-link" href="#/' + page.key + '" data-route="' + page.key + '">' + icon(page.icon) + esc(page.label) + '</a>';
    }).join('');
  }

  function currentRoute() {
    const key = (window.location.hash || '#/dashboard').replace('#/', '').split('?')[0];
    return PAGES.some(function (page) { return page.key === key; }) ? key : 'dashboard';
  }

  function render() {
    const route = currentRoute();
    const page = PAGES.find(function (item) { return item.key === route; });

    titleEl.textContent = page.label;
    subEl.textContent = page.sub;
    Array.prototype.forEach.call(navEl.querySelectorAll('.nav-link'), function (link) {
      link.classList.toggle('active', link.dataset.route === route);
    });

    view.innerHTML = '';
    if (route === 'vehicles') view.appendChild(vehiclesView());
    else if (route === 'customers') view.appendChild(customersView());
    else if (route === 'rentals') view.appendChild(rentalsView());
    else if (route === 'reports') view.appendChild(reportsView());
    else view.appendChild(dashboardView());

    labelTableCells(view);

    view.classList.remove('fade-in');
    void view.offsetWidth;
    view.classList.add('fade-in');
  }

  /* Copy each column heading onto its cells so phones can show tables as stacked cards. */
  function labelTableCells(root) {
    Array.prototype.forEach.call(root.querySelectorAll('table.data'), function (table) {
      const heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
        return th.textContent.trim();
      });
      Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (row) {
        Array.prototype.forEach.call(row.children, function (cell, index) {
          if (heads[index]) cell.setAttribute('data-label', heads[index]);
          if (cell.querySelector('.row-actions')) cell.classList.add('cell-actions');
        });
      });
    });
  }

  function offlinePanel(message) {
    const node = div('offline');
    node.innerHTML =
      '<h2>The PHP backend is not reachable</h2>' +
      '<p>' + esc(message) + '</p>' +
      '<p>Open this package through a PHP web server — copy the folder into your web root (XAMPP htdocs, WAMP www) — or run it from inside the folder:</p>' +
      '<code>php -S localhost:8000</code>' +
      '<p>then browse to <strong>http://localhost:8000/index.html</strong>.</p>' +
      '<div style="margin-top:1rem"><button class="btn outline" data-action="reload-data">' + icon('refresh') + ' Try again</button></div>';
    return node;
  }

  async function boot() {
    try {
      await loadAll();
      render();
    } catch (error) {
      view.innerHTML = '';
      view.appendChild(offlinePanel(error.message || 'Unknown error'));
    }
  }

  /* ---------------- events ---------------- */
  function setSidebar(open) {
    sidebar.classList.toggle('open', open);
    scrim.classList.toggle('show', open);
    document.body.classList.toggle('nav-open', open);
    const button = document.getElementById('menu-button');
    if (button) button.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function closeSidebar() {
    setSidebar(false);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 1024 && sidebar.classList.contains('open')) closeSidebar();
  });

  function handleDeleteVehicle(id) {
    const vehicle = vehicleById(id);
    confirmDialog({
      title: 'Delete ' + id,
      sub: 'This cannot be undone',
      message: 'Remove ' + vehicleLabel(vehicle) + ' (' + (vehicle ? vehicle.plate_number : id) + ') from the fleet?',
      onConfirm: async function () {
        const message = await mutate('vehicles', 'delete', {}, id);
        toast(message);
        render();
      }
    });
  }

  function handleDeleteCustomer(id) {
    const customer = customerById(id);
    confirmDialog({
      title: 'Delete ' + id,
      sub: 'This cannot be undone',
      message: 'Remove ' + customerLabel(customer) + ' from the customer records?',
      onConfirm: async function () {
        const message = await mutate('customers', 'delete', {}, id);
        toast(message);
        render();
      }
    });
  }

  function handleDeleteRental(id) {
    const rental = rentalById(id);
    confirmDialog({
      title: 'Delete ' + id,
      sub: 'This cannot be undone',
      message: 'Delete the booking for ' + customerLabel(customerById(rental.customer_id)) + ' (' + formatDate(rental.start_date) + ' → ' + formatDate(rental.end_date) + ')?',
      onConfirm: async function () {
        const message = await mutate('rentals', 'delete', {}, id);
        toast(message);
        render();
      }
    });
  }

  document.addEventListener('click', async function (event) {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const id = trigger.dataset.id;

    if (action === 'toggle-menu') {
      setSidebar(!sidebar.classList.contains('open'));
      return;
    }
    if (action === 'close-modal') { closeModal(); return; }
    if (action === 'reload-data') { boot(); return; }
    if (action === 'new-vehicle') { openVehicleForm(null); return; }
    if (action === 'edit-vehicle') { openVehicleForm(vehicleById(id)); return; }
    if (action === 'delete-vehicle') { handleDeleteVehicle(id); return; }
    if (action === 'new-customer') { openCustomerForm(null); return; }
    if (action === 'edit-customer') { openCustomerForm(customerById(id)); return; }
    if (action === 'delete-customer') { handleDeleteCustomer(id); return; }
    if (action === 'new-rental') { openRentalForm(null); return; }
    if (action === 'edit-rental') { openRentalForm(rentalById(id)); return; }
    if (action === 'delete-rental') { handleDeleteRental(id); return; }
    if (action === 'print-report') { window.print(); return; }

    if (action === 'return-rental') {
      try {
        await recordReturn(id);
      } catch (error) {
        toast(error.message, true);
      }
      return;
    }

    if (action === 'generate-report') {
      if (!reportRange.from || !reportRange.to) { toast('Pick both dates for the report', true); return; }
      if (reportRange.from > reportRange.to) { toast('The start date must be before the end date', true); return; }
      render();
      toast('Report generated for ' + formatDate(reportRange.from) + ' → ' + formatDate(reportRange.to));
      return;
    }

    if (action === 'reset-data') {
      confirmDialog({
        title: 'Reset demo data',
        sub: 'Restores the seeded records',
        message: 'Discard every change saved by index.php and restore the demo fleet, customers and rentals?',
        submitLabel: 'Reset data',
        onConfirm: async function () {
          try {
            const result = await api({ action: 'reset' }, {});
            applyData(result.data);
            toast(result.message || 'Demo data restored');
            render();
          } catch (error) {
            toast(error.message, true);
          }
        }
      });
    }
  });

  navEl.addEventListener('click', closeSidebar);
  scrim.addEventListener('click', closeSidebar);

  function handleFilter(event) {
    const key = event.target.dataset.filter;
    if (!key) return;
    const parts = key.split('.');
    const value = event.target.value;
    filters[parts[0]][parts[1]] = value;
    render();
    if (event.target.type === 'text') {
      const restored = view.querySelector('[data-filter="' + key + '"]');
      if (restored) {
        restored.focus();
        restored.setSelectionRange(value.length, value.length);
      }
    }
  }

  view.addEventListener('input', handleFilter);
  view.addEventListener('change', handleFilter);
  window.addEventListener('hashchange', render);

  /* ---------------- start ---------------- */
  buildNav();
  todayChip.textContent = formatLongDate(new Date());
  boot();
})();
