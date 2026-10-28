/* FleetFlow — dependency-free SVG / DOM chart helpers for the standalone app. */

const NS = 'http://www.w3.org/2000/svg';

function el(name, attrs) {
  const node = document.createElementNS(NS, name);
  Object.entries(attrs || {}).forEach(function (entry) {
    node.setAttribute(entry[0], entry[1]);
  });
  return node;
}

function compactPeso(value) {
  const amount = Number(value) || 0;
  if (amount >= 1000000) return '₱' + (amount / 1000000).toFixed(1) + 'M';
  if (amount >= 1000) return '₱' + Math.round(amount / 1000) + 'k';
  return '₱' + Math.round(amount);
}

/* Grouped bar chart — revenue charged vs collected per month. */
function revenueBarChart(data, series) {
  const width = 660;
  const height = 260;
  const pad = { top: 12, right: 12, bottom: 32, left: 58 };
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;
  const svg = el('svg', { viewBox: '0 0 ' + width + ' ' + height, width: '100%', role: 'img' });

  const max = Math.max(1, Math.max.apply(null, data.map(function (row) {
    return Math.max.apply(null, series.map(function (s) { return row[s.key] || 0; }));
  })));
  const step = Math.pow(10, Math.floor(Math.log10(max)));
  const top = Math.ceil(max / step) * step;

  [0, 0.25, 0.5, 0.75, 1].forEach(function (frac) {
    const y = pad.top + plotH - plotH * frac;
    svg.appendChild(el('line', { x1: pad.left, x2: width - pad.right, y1: y, y2: y, stroke: '#e2e8f0', 'stroke-width': 1 }));
    const label = el('text', { x: pad.left - 10, y: y + 3, 'text-anchor': 'end', fill: '#64748b', 'font-size': 10 });
    label.textContent = compactPeso(top * frac);
    svg.appendChild(label);
  });

  const band = plotW / Math.max(1, data.length);
  const barW = Math.min(20, (band - 8) / series.length);

  data.forEach(function (row, index) {
    const bandStart = pad.left + band * index + (band - barW * series.length) / 2;
    series.forEach(function (s, sIndex) {
      const value = row[s.key] || 0;
      const barH = (value / top) * plotH;
      const bar = el('rect', {
        x: bandStart + barW * sIndex,
        y: pad.top + plotH - barH,
        width: Math.max(2, barW - 2),
        height: Math.max(0, barH),
        rx: 3,
        fill: s.color
      });
      const title = el('title');
      title.textContent = row.label + ': ' + s.label + ' ₱' + value.toLocaleString('en-PH');
      bar.appendChild(title);
      svg.appendChild(bar);
    });

    const label = el('text', {
      x: pad.left + band * index + band / 2,
      y: height - 10,
      'text-anchor': 'middle',
      fill: '#64748b',
      'font-size': 11
    });
    label.textContent = row.label;
    svg.appendChild(label);
  });

  return svg;
}

/* Donut chart with a centre total — fleet status breakdown. */
function donutChart(items, centerLabel) {
  const size = 190;
  const radius = 64;
  const stroke = 20;
  const circumference = 2 * Math.PI * radius;
  const total = items.reduce(function (sum, item) { return sum + item.count; }, 0);
  const svg = el('svg', { viewBox: '0 0 ' + size + ' ' + size, width: '100%', style: 'max-width:190px' });

  svg.appendChild(el('circle', { cx: size / 2, cy: size / 2, r: radius, fill: 'none', stroke: '#e2e8f0', 'stroke-width': stroke }));

  let offset = 0;
  items.forEach(function (item) {
    if (!item.count) return;
    const length = (item.count / total) * circumference;
    const arc = el('circle', {
      cx: size / 2,
      cy: size / 2,
      r: radius,
      fill: 'none',
      stroke: item.color,
      'stroke-width': stroke,
      'stroke-dasharray': length + ' ' + (circumference - length),
      'stroke-dashoffset': -offset,
      transform: 'rotate(-90 ' + size / 2 + ' ' + size / 2 + ')'
    });
    const title = el('title');
    title.textContent = item.label + ': ' + item.count;
    arc.appendChild(title);
    svg.appendChild(arc);
    offset += length;
  });

  const value = el('text', {
    x: size / 2,
    y: size / 2 - 2,
    'text-anchor': 'middle',
    fill: '#0f1c33',
    'font-family': 'Poppins, sans-serif',
    'font-size': 26,
    'font-weight': 600
  });
  value.textContent = String(total);
  svg.appendChild(value);

  const caption = el('text', {
    x: size / 2,
    y: size / 2 + 16,
    'text-anchor': 'middle',
    fill: '#64748b',
    'font-size': 9,
    'letter-spacing': '0.08em'
  });
  caption.textContent = centerLabel;
  svg.appendChild(caption);

  return svg;
}

/* Horizontal bar list, rendered as plain DOM. */
function hBarList(items, format) {
  const wrap = document.createElement('div');
  wrap.className = 'hbar-list';

  const max = Math.max(1, Math.max.apply(null, items.map(function (item) { return item.value; })));

  items.forEach(function (item) {
    const row = document.createElement('div');
    row.className = 'hbar';
    row.style.marginBottom = '0.9rem';

    const head = document.createElement('div');
    head.className = 'hbar-head';

    const label = document.createElement('span');
    label.className = 'hbar-label';
    label.textContent = item.label;

    const value = document.createElement('span');
    value.className = 'hbar-value';
    value.textContent = format(item.value);

    head.appendChild(label);
    head.appendChild(value);

    const track = document.createElement('div');
    track.className = 'bar-track';

    const fill = document.createElement('div');
    fill.className = 'bar-fill';
    fill.style.width = Math.max(3, (item.value / max) * 100) + '%';
    track.appendChild(fill);

    row.appendChild(head);
    row.appendChild(track);
    wrap.appendChild(row);
  });

  return wrap;
}

window.Charts = { revenueBarChart: revenueBarChart, donutChart: donutChart, hBarList: hBarList };
