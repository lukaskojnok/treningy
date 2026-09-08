(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const fields = {training: 'B · Béčko', main: 'A · Áčko', c1: 'C1', artificial: 'U1 · Umelá tráva'};
  const parts = {training: ['1', '2', '3', '4'], main: ['1', '2', '3', '4'], c1: ['1', '2'], artificial: ['1']};
  const selectedParts = (field, area) => area === 'full' ? parts[field] : area.split(',').filter(Boolean);
  const areaLabel = (field, area) => {
    const chosen = selectedParts(field, area);
    if (chosen.length === parts[field].length) return 'Celé ihrisko';
    if (!chosen.length) return 'Vyber plochu';
    return `${chosen.length === 1 && parts[field].length === 4 ? 'Štvrtina' : 'Polovica'} · ${chosen.join(' + ')}`;
  };
  const validArea = (field, area) => {
    const chosen = selectedParts(field, area);
    if (!chosen.length || new Set(chosen).size !== chosen.length || chosen.some(p => !parts[field].includes(p))) return false;
    return chosen.length === 1 || chosen.length === parts[field].length || (parts[field].length === 4 && ['1,2', '3,4', '1,3', '2,4'].includes([...chosen].sort().join(',')));
  };
  const overlap = (a, b) => a.field === b.field && selectedParts(a.field, a.area).some(p => selectedParts(b.field, b.area).includes(p));
  const localDate = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const dayAt = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
  const monday = d => dayAt(d, -((d.getDay() + 6) % 7));
  const minutes = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const clock = n => `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
  const escape = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  let week = monday(new Date()), editing = null, mode = 'calendar';
  const events = [];
  const seed = [
    [0, '16:00', '17:30', 'main', 'A', 'U15 · tímový tréning', 'Martin Kováč'],
    [0, '16:00', '17:30', 'main', 'B', 'U13 · technika', 'Peter Novák'],
    [0, '18:00', '20:00', 'artificial', 'full', 'A-mužstvo', 'Ján Horváth'],
    [1, '09:00', '10:30', 'training', 'full', 'Individuálny tréning', 'Peter Novák'],
    [1, '15:00', '16:30', 'main', 'full', 'U11 · práca s loptou', 'Martin Kováč'],
    [1, '17:00', '18:30', 'artificial', 'A', 'U17 · príprava', 'Ján Horváth'],
    [2, '16:00', '17:30', 'training', 'full', 'U13 · tímový tréning', 'Peter Novák'],
    [2, '18:00', '19:30', 'main', 'full', 'A-mužstvo', 'Ján Horváth'],
    [3, '10:00', '11:30', 'artificial', 'full', 'Brankársky tréning', 'Martin Kováč'],
    [3, '16:00', '18:00', 'main', 'A', 'U15 · herná príprava', 'Martin Kováč'],
    [4, '15:00', '16:30', 'training', 'full', 'U11 · tímový tréning', 'Peter Novák'],
    [4, '17:00', '19:00', 'main', 'full', 'A-mužstvo · pred zápasom', 'Ján Horváth'],
    [5, '10:00', '12:00', 'main', 'full', 'U15 · prípravný zápas', 'Martin Kováč'],
    [6, '14:00', '16:00', 'main', 'full', 'A-mužstvo · zápas', 'Ján Horváth']
  ];
  seed.forEach((e, i) => events.push({id: i + 1, date: localDate(dayAt(week, e[0])), start: e[1], end: e[2], field: e[3], area: e[4] === 'A' ? (e[3] === 'artificial' ? 'full' : '1,2') : e[4] === 'B' ? '3,4' : e[4], title: e[5], coach: e[6], note: ''}));
  $('field-date').value = localDate(new Date());
  const visible = e => $('field-filter').value === 'all' || e.field === $('field-filter').value;
  let calendarScroll = 336;
  function render() {
    const previousScroll = $('calendar-scroll');
    if (previousScroll) calendarScroll = previousScroll.scrollTop;
    $('date-heading').textContent = `${week.toLocaleDateString('sk-SK', {day: 'numeric', month: 'numeric'})} – ${dayAt(week, 6).toLocaleDateString('sk-SK', {day: 'numeric', month: 'numeric', year: 'numeric'})}`;
    let heads = '<div class="time-heading">ČAS</div>', columns = '<div class="time-axis">';
    for (let h = 8; h < 22; h++) columns += `<span style="top:${(h - 8) * 56 + 3}px">${clock(h * 60)}</span>`;
    columns += '</div>';
    for (let i = 0; i < 7; i++) {
      const d = dayAt(week, i), date = localDate(d);
      heads += `<div class="day-head ${date === localDate(new Date()) ? 'today' : ''}"><span>${['PON', 'UTO', 'STR', 'ŠTV', 'PIA', 'SOB', 'NED'][i]}</span><strong>${d.getDate()}</strong></div>`;
      columns += `<div class="day-column ${i > 4 ? 'weekend' : ''}">`;
      for (let slot = 0; slot < 28; slot++) columns += `<button class="slot" data-date="${date}" data-time="${clock(480 + slot * 30)}" aria-label="Pridať rezerváciu ${date} o ${clock(480 + slot * 30)}"></button>`;
      const daily = events.filter(e => e.date === date && visible(e)).sort((a, b) => minutes(a.start) - minutes(b.start));
      const groups = [];
      daily.forEach(e => { let g = groups[groups.length - 1]; if (!g || minutes(e.start) >= g.end) { g = {items: [], end: 0}; groups.push(g); } g.items.push(e); g.end = Math.max(g.end, minutes(e.end)); });
      groups.forEach(g => {
        const ends = [];
        g.items.forEach(e => { let lane = ends.findIndex(end => end <= minutes(e.start)); if (lane < 0) lane = ends.length; ends[lane] = minutes(e.end); e.lane = lane; });
        g.items.forEach(e => {
          columns += `<button class="event ${e.field}" data-id="${e.id}" style="top:${(minutes(e.start) - 480) * 56 / 60}px;height:${(minutes(e.end) - minutes(e.start)) * 56 / 60 - 3}px;left:calc(${e.lane * 100 / ends.length}% + 3px);width:calc(${100 / ends.length}% - 6px)" title="${escape(e.title + ' · ' + e.coach + ' · ' + fields[e.field] + ' · ' + areaLabel(e.field, e.area))}"><span>${e.start} – ${e.end}</span><strong>${escape(e.title)}</strong><small>${escape(fields[e.field])} · ${areaLabel(e.field, e.area)}</small><small>${escape(e.coach)}</small></button>`;
        });
      });
      columns += '</div>';
    }
    $('calendar').innerHTML = `<div class="calendar-head">${heads}</div><div id="calendar-scroll"><div class="calendar-body">${columns}</div></div>`;
    $('calendar-scroll').scrollTop = calendarScroll;
    $('calendar-scroll').onscroll = () => { calendarScroll = $('calendar-scroll').scrollTop; };
    renderFields();
  }
  function renderFields() {
    const date = $('field-date').value, time = $('field-time').value;
    $('pitches').innerHTML = Object.entries(fields).filter(([id]) => $('field-filter').value === 'all' || $('field-filter').value === id).map(([id, title]) => {
      const daily = events.filter(e => e.field === id && e.date === date).sort((a, b) => a.start.localeCompare(b.start));
      return `<article class="pitch-card"><h2>${title}</h2><p>${parts[id].length === 4 ? 'Štvrtina, polovica alebo celé ihrisko' : parts[id].length === 2 ? 'Polovica alebo celé ihrisko' : 'Celá plocha · členenie doplníme'}</p><div class="pitch parts-${parts[id].length}">${parts[id].map(area => {
        const e = daily.find(e => selectedParts(e.field, e.area).includes(area) && e.start <= time && e.end > time);
        return `<button class="pitch-half ${e ? 'busy' : ''}" ${e ? `data-id="${e.id}"` : `data-field="${id}" data-area="${area}"`}><small>${parts[id].length === 4 ? 'ŠTVRTINA' : parts[id].length === 2 ? 'POLOVICA' : 'PLOCHA'} ${area}</small><strong>${e ? escape(e.title) : 'Voľná plocha'}</strong><span>${e ? `${e.start} – ${e.end} · ${areaLabel(e.field, e.area)}` : '＋ Rezervovať tento čas'}</span></button>`;
      }).join('')}</div><div class="pitch-list"><h4>REZERVÁCIE V TENTO DEŇ · ${daily.length}</h4>${daily.map(e => `<button class="pitch-booking" data-id="${e.id}"><strong>${e.start} – ${e.end}</strong> · ${escape(e.title)}<br>${areaLabel(e.field, e.area)} · ${escape(e.coach)}</button>`).join('') || '<p>Na tento deň nie sú rezervácie.</p>'}</div></article>`;
    }).join('');
  }
  function showForm(data = {}) {
    const form = $('event-form'); form.reset(); editing = data.id || null;
    const defaults = {date: mode === 'fields' ? $('field-date').value : localDate(new Date()), start: '16:00', end: '17:30', field: $('field-filter').value === 'all' ? 'main' : $('field-filter').value, area: '', ...data};
    Object.entries(defaults).forEach(([k, v]) => { if (form.elements.namedItem(k)) form.elements.namedItem(k).value = v; });
    $('dialog-title').textContent = editing ? 'Upraviť rezerváciu' : 'Nová rezervácia';
    $('delete-event').hidden = !editing; $('form-error').textContent = ''; renderAreaMap(); $('event-dialog').showModal();
  }
  function renderAreaMap() {
    const form = $('event-form'), field = form.elements.field.value, area = form.elements.area.value;
    const date = form.elements.date.value, start = form.elements.start.value, end = form.elements.end.value;
    $('area-map').innerHTML = Object.entries(fields).map(([id, name]) => `<div class="map-field ${id === field ? 'active-field' : ''}"><strong>${name}</strong><div class="map-pitch parts-${parts[id].length}">${parts[id].map(part => {
      const active = field === id && selectedParts(field, area).includes(part);
      const busy = events.some(e => e.id !== editing && e.field === id && e.date === date && e.start < end && e.end > start && selectedParts(e.field, e.area).includes(part));
      return `<button type="button" class="map-part ${active ? 'is-chosen' : ''} ${busy ? 'is-busy' : ''}" data-map-field="${id}" data-part="${part}" aria-pressed="${active}" aria-label="${name}, časť ${part}${busy ? ', obsadená' : ''}"><b>${parts[id].length === 1 ? 'Celá plocha' : part}</b><small>${busy ? 'Obsadené' : active ? 'Vybrané ✓' : 'Voľné'}</small></button>`;
    }).join('')}</div><small>${parts[id].length === 4 ? '4 štvrtiny' : parts[id].length === 2 ? '2 polovice nad sebou' : 'Členenie doplníme'}</small></div>`).join('');
    const valid = validArea(field, area);
    $('area-summary').textContent = `${fields[field]} · ${valid ? areaLabel(field, area) : 'Vyber štvrtinu, dve susedné časti alebo celé ihrisko'}`;
    $('area-summary').classList.toggle('danger', !valid);
  }
  $('area-map').onclick = event => {
    const button = event.target.closest('[data-map-field]'); if (!button) return;
    const form = $('event-form'), field = button.dataset.mapField, part = button.dataset.part;
    let chosen = form.elements.field.value === field ? [...selectedParts(field, form.elements.area.value)] : [];
    chosen = chosen.includes(part) ? chosen.filter(p => p !== part) : [...chosen, part];
    form.elements.field.value = field; form.elements.area.value = chosen.sort().join(','); renderAreaMap();
  };
  $('select-whole').onclick = () => { $('event-form').elements.area.value = 'full'; renderAreaMap(); };
  $('clear-area').onclick = () => { $('event-form').elements.area.value = ''; renderAreaMap(); };
  ['date', 'start', 'end'].forEach(name => $('event-form').elements[name].addEventListener('change', renderAreaMap));
  $('morning').onclick = () => { $('calendar-scroll').scrollTop = 0; };
  $('afternoon').onclick = () => { $('calendar-scroll').scrollTop = 336; };
  function setView(view) {
    mode = view; $('calendar-view').hidden = view !== 'calendar'; $('fields-view').hidden = view !== 'fields';
    ['calendar', 'fields'].forEach(v => { $('view-' + v).classList.toggle('selected', v === view); $('view-' + v).setAttribute('aria-pressed', String(v === view)); });
    $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false');
  }
  document.addEventListener('click', event => {
    const el = event.target.closest('[data-id], [data-time], [data-field]');
    if (el?.dataset.id) showForm(events.find(e => e.id === Number(el.dataset.id)));
    else if (el?.dataset.time) showForm({date: el.dataset.date, start: el.dataset.time, end: clock(Math.min(minutes(el.dataset.time) + 90, 1320))});
    else if (el?.dataset.field && $('field-date').value && $('field-time').value) { const start = $('field-time').value; showForm({date: $('field-date').value, start, end: clock(Math.min(minutes(start) + 90, 1320)), field: el.dataset.field, area: el.dataset.area}); }
    if (!event.target.closest('.menu-wrap')) { $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false'); }
  });
  $('menu-toggle').onclick = () => { $('main-menu').hidden = !$('main-menu').hidden; $('menu-toggle').setAttribute('aria-expanded', String(!$('main-menu').hidden)); };
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false'); } });
  ['calendar', 'fields'].forEach(v => { $('view-' + v).onclick = () => setView(v); $('menu-' + v).onclick = () => setView(v); });
  $('prev').onclick = () => { week = dayAt(week, -7); $('field-date').value = localDate(week); render(); };
  $('next').onclick = () => { week = dayAt(week, 7); $('field-date').value = localDate(week); render(); };
  $('today').onclick = () => { week = monday(new Date()); $('field-date').value = localDate(new Date()); render(); };
  $('field-filter').onchange = render;
  $('field-date').onchange = () => { if ($('field-date').value) { week = monday(new Date($('field-date').value + 'T12:00:00')); render(); } };
  $('field-time').onchange = renderFields;
  $('add-event').onclick = () => showForm();
  ['close-dialog', 'cancel-dialog'].forEach(id => $(id).onclick = () => $('event-dialog').close());
  $('delete-event').onclick = () => { const index = events.findIndex(e => e.id === editing); if (index >= 0) events.splice(index, 1); $('event-dialog').close(); render(); };
  $('event-form').onsubmit = event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.target)); data.title = data.title.trim(); data.coach = data.coach.trim();
    if (!data.title || !data.coach || data.start >= data.end || data.start < '08:00' || data.end > '22:00') { $('form-error').textContent = 'Vyplň názov a trénera. Koniec musí byť po začiatku, v rozsahu 08:00 – 22:00.'; return; }
    if (!validArea(data.field, data.area)) { $('form-error').textContent = 'Vyber jednu štvrtinu, dve susedné štvrtiny (polovicu) alebo celé ihrisko.'; return; }
    if (events.some(e => e.id !== editing && e.date === data.date && e.field === data.field && overlap(e, data) && e.start < data.end && e.end > data.start)) { $('form-error').textContent = 'Táto plocha je v zadanom čase obsadená. Vyber iný čas alebo inú časť ihriska.'; return; }
    if (editing) Object.assign(events.find(e => e.id === editing), data);
    else events.push({...data, id: Math.max(0, ...events.map(e => e.id)) + 1});
    week = monday(new Date(data.date + 'T12:00:00')); $('field-date').value = data.date;
    $('event-dialog').close(); render();
  };
  render();
})();
