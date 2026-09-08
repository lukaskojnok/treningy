(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const config = JSON.parse($('pitch-config').textContent);
  const fields = Object.fromEntries(Object.entries(config).map(([id, field]) => [id, `${id} · ${field.popis}`]));
  const parts = Object.fromEntries(Object.entries(config).map(([id, field]) => [id, Object.keys(field.parts)]));
  const mapParts = field => parts[field].length === 4 ? [parts[field][3], parts[field][1], parts[field][2], parts[field][0]] : parts[field];
  const colors = {B: 'training', A: 'main', C: 'c1', U: 'artificial'};
  let typeFilter = 'all';
  let filterSelection = Object.fromEntries(Object.entries(parts).map(([id, list]) => [id, [...list]]));
  const shownFields = () => Object.keys(fields).filter(id => filterSelection[id]?.length);
  const selectedParts = (field, area) => area === 'full' ? parts[field] : area.split(',').filter(Boolean);
  const areaLabel = (field, area) => {
    const chosen = selectedParts(field, area);
    if (chosen.length === parts[field].length) return 'Celé ihrisko';
    if (!chosen.length) return 'Vyber plochu';
    return `${chosen.length === 1 && parts[field].length === 4 ? 'Štvrtina' : 'Polovica'} · ${chosen.join(' + ')}`;
  };
  const validArea = (field, area) => {
    if (!parts[field]) return false;
    const chosen = selectedParts(field, area);
    if (!chosen.length || new Set(chosen).size !== chosen.length || chosen.some(p => !parts[field].includes(p))) return false;
    return chosen.length === 1 || chosen.length === parts[field].length || (parts[field].length === 4 && [[0, 1], [2, 3], [0, 2], [1, 3]].some(pair => pair.every(index => chosen.includes(parts[field][index]))));
  };
  const overlap = (a, b) => a.field === b.field && selectedParts(a.field, a.area).some(p => selectedParts(b.field, b.area).includes(p));
  const localDate = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const dayAt = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
  const monday = d => dayAt(d, -((d.getDay() + 6) % 7));
  const minutes = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const clock = n => `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
  const escape = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  let selectedDay = new Date(), week = monday(new Date()), editing = null, mode = 'calendar';
  const trainingOpenDay = 4;
  const trainingFrom = new Date();
  const trainingCurrentTo = dayAt(monday(new Date()), 6);
  const trainingNextFrom = dayAt(monday(new Date()), 7);
  const trainingNextTo = dayAt(trainingNextFrom, 6);
  const trainingWindowOpen = ((new Date().getDay() + 6) % 7) + 1 >= trainingOpenDay;
  const trainingTo = trainingWindowOpen ? trainingNextTo : trainingCurrentTo;
  const events = [];
  const dailyTrainings = [
    ['A', 'A1,A2', 'A-mužstvo', 'Ján Horváth'],
    ['A', 'A3,A4', 'U17', 'Martin Kováč'],
    ['B', 'B1,B2', 'U15', 'Peter Novák'],
    ['C', 'C1', 'U13', 'Marek Urban'],
    ['U', 'U1', 'U11', 'Tomáš Varga']
  ];
  // Ukážkové dáta: minulý, aktuálny a budúci týždeň vzhľadom na dnešok.
  // Časy sú v minútach od polnoci; každá položka vytvorí samostatný tréning.
  const afternoonStarts = [
    [840, 900, 960, 1020, 1110],
    [900, 960, 960, 990, 1080],
    [960, 960, 960, 960, 960],
    [870, 930, 1020, 1080, 1140],
    [900, 990, 1050, 1050, 1140],
    [840, 900, 930, 1020, 1080],
    [870, 960, 990, 1080, 1110]
  ];
  const focuses = ['kondícia', 'technika', 'prihrávky', 'streľba', 'taktika', 'herná príprava', 'regenerácia'];
  for (let weekOffset = -1; weekOffset <= 1; weekOffset++) {
    for (let day = 0; day < 7; day++) {
      const date = localDate(dayAt(week, weekOffset * 7 + day));
      const variation = (day + weekOffset + 7) % 7;
      dailyTrainings.forEach(([field, area, title, coach], index) => {
        const start = afternoonStarts[variation][index];
        const duration = variation === 2 ? 90 : [60, 90, 120][(day + index + weekOffset + 7) % 3];
        events.push({id: events.length + 1, type: 'training', date, start: clock(start), end: clock(start + duration), field, area, title: title + ' · ' + focuses[(variation + index) % 7], coach, note: 'Ukážkový popoludňajší tréning.'});
      });
      const morningStart = 480 + (variation % 5) * 30;
      events.push({id: events.length + 1, type: 'training', date, start: clock(morningStart), end: clock(morningStart + 60), field: 'B', area: 'B3,B4', title: 'U9 · ' + focuses[variation], coach: 'Peter Novák', note: 'Ukážkový dopoludňajší tréning.'});
      if (day % 2 === 0) {
        const start = 630 + (variation % 3) * 30;
        events.push({id: events.length + 1, type: 'match', date, start: clock(start), end: clock(start + 60), field: 'A', area: 'full', title: 'U19 · majstrovský zápas', coach: 'Martin Kováč', note: 'Ukážkový zápas na celom ihrisku.'});
      }
    }
  }
  $('field-date').value = localDate(new Date());
  const typeVisible = e => typeFilter === 'all' || e.type === typeFilter;
  const visible = e => typeVisible(e) && selectedParts(e.field, e.area).some(part => filterSelection[e.field]?.includes(part));
  let calendarScroll = 432;
  function render() {
    const widths = [];
    const previousScroll = $('calendar-scroller');
    const horizontalScroll = previousScroll ? previousScroll.scrollLeft : 0;
    const isDay = mode === 'day', dailyNavigation = mode !== 'calendar';
    $('today').textContent = dailyNavigation ? 'Dnes' : 'Tento týždeň';
    ['calendar-tip', 'morning', 'afternoon'].forEach(id => $(id).hidden = mode === 'fields');
    $('prev').setAttribute('aria-label', dailyNavigation ? 'Predchádzajúci deň' : 'Predchádzajúci týždeň');
    $('next').setAttribute('aria-label', dailyNavigation ? 'Nasledujúci deň' : 'Nasledujúci týždeň');
    $('calendar-view').setAttribute('aria-label', isDay ? 'Denný kalendár častí ihrísk' : 'Týždenný kalendár');
    $('date-heading').textContent = dailyNavigation ? selectedDay.toLocaleDateString('sk-SK', {weekday: 'long', day: 'numeric', month: 'numeric', year: 'numeric'}) : `${week.toLocaleDateString('sk-SK', {day: 'numeric', month: 'numeric'})} – ${dayAt(week, 6).toLocaleDateString('sk-SK', {day: 'numeric', month: 'numeric', year: 'numeric'})}`;
    let heads = '<div class="time-heading">ČAS</div>', columns = '<div class="time-axis">';
    for (let h = 8; h < 22; h++) columns += `<span style="top:${(h - 8) * 72 + 3}px">${clock(h * 60)}</span>`;
    columns += '</div>';
    const resources = Object.keys(parts).flatMap(field => parts[field].filter(part => filterSelection[field]?.includes(part)).map(part => ({field, part})));
    const columnCount = isDay ? resources.length : 7;
    for (let i = 0; i < columnCount; i++) {
      const d = isDay ? selectedDay : dayAt(week, i), date = localDate(d);
      const resource = isDay ? resources[i] : null;
      heads += isDay ? `<div class="day-head resource-head ${colors[resource.field]}"><span>${escape(fields[resource.field])}</span><strong>${escape(resource.part)}</strong><small>${escape(config[resource.field].parts[resource.part])}</small></div>` : `<div class="day-head ${date === localDate(new Date()) ? 'today' : ''}"><span>${['PON', 'UTO', 'STR', 'ŠTV', 'PIA', 'SOB', 'NED'][i]}</span><strong>${d.getDate()}</strong></div>`;
      columns += `<div class="day-column ${!isDay && i > 4 ? 'weekend' : ''}"${isDay ? ` data-part="${resource.part}"` : ''}>`;
      for (let slot = 0; slot < 28; slot++) columns += `<button class="slot" data-date="${date}" data-time="${clock(480 + slot * 30)}" ${isDay ? `data-field="${resource.field}" data-area="${resource.part}"` : ''} aria-label="Pridať rezerváciu ${date} o ${clock(480 + slot * 30)}${isDay ? ' · ' + resource.part : ''}"></button>`;
      const daily = events.filter(e => e.date === date && typeVisible(e) && (isDay ? e.field === resource.field && selectedParts(e.field, e.area).includes(resource.part) : visible(e))).sort((a, b) => minutes(a.start) - minutes(b.start));
      const groups = [];
      daily.forEach(e => { let g = groups[groups.length - 1]; if (!g || minutes(e.start) >= g.end) { g = {items: [], end: 0}; groups.push(g); } g.items.push(e); g.end = Math.max(g.end, minutes(e.end)); });
      let maxLanes = 1;
      groups.forEach(g => {
        const ends = [];
        g.items.forEach(e => { let lane = ends.findIndex(end => end <= minutes(e.start)); if (lane < 0) lane = ends.length; ends[lane] = minutes(e.end); e.lane = lane; });
        const sameTime = !isDay && g.items.length > 1 && g.items.every(e => e.start === g.items[0].start && e.end === g.items[0].end);
        maxLanes = Math.max(maxLanes, sameTime ? 1 : ends.length);
        g.items.forEach(e => {
          const durationHeight = (minutes(e.end) - minutes(e.start)) * 72 / 60;
          const top = (minutes(e.start) - 480) * 72 / 60 + (sameTime ? e.lane * durationHeight / g.items.length : 0);
          const height = sameTime ? durationHeight / g.items.length - 1 : durationHeight - 3;
          const left = sameTime ? '3px' : `calc((100% - ${isDay ? 0 : 27}px) * ${e.lane / ends.length} + 3px)`;
          const width = sameTime ? 'calc(100% - 33px)' : `calc((100% - ${isDay ? 0 : 27}px) / ${ends.length} - 6px)`;
          const kind = e.type === 'match' ? 'ZÁPAS' : 'TRÉNING';
          columns += `<button class="event ${colors[e.field]}${e.type === 'match' ? ' event-match' : ''}${sameTime ? ' event-compact' : ''}" data-id="${e.id}" style="top:${top}px;height:${height}px;left:${left};width:${width}" title="${escape(kind + ' · ' + e.title + ' · ' + e.start + '–' + e.end + ' · ' + e.coach + ' · ' + fields[e.field] + ' · ' + areaLabel(e.field, e.area))}"><span class="event-area">${escape(selectedParts(e.field, e.area).join(" + "))}</span><span class="event-time">${e.start} – ${e.end}</span><strong>${e.type === 'match' ? 'ZÁPAS · ' : ''}${escape(e.title)}</strong><small>${escape(fields[e.field])} · ${areaLabel(e.field, e.area)}</small><small>${escape(e.coach)}</small></button>`;
        });
      });
      if (!isDay) for (let slot = 0; slot < 28; slot++) columns += `<button class="slot-add" style="top:${slot * 36}px" data-date="${date}" data-time="${clock(480 + slot * 30)}" title="Pridať rezerváciu o ${clock(480 + slot * 30)}" aria-label="Pridať rezerváciu ${date} o ${clock(480 + slot * 30)}">+</button>`;
      widths.push(Math.max(isDay ? 130 : 174, maxLanes * (isDay ? 130 : 145)));
      columns += '</div>';
    }
    const template = `58px ${widths.map(w => `minmax(${w}px, 1fr)`).join(' ')}`;
    $('calendar').innerHTML = `<div id="calendar-scroller" tabindex="0" role="region" aria-label="Kalendár rezervácií, posúvateľný do strán aj zvislo"><div class="calendar-inner" style="min-width:${58 + widths.reduce((sum, w) => sum + w, 0)}px;--calendar-columns:${template}"><div class="calendar-head">${heads}</div><div class="calendar-body">${columns}</div></div></div>`;
    const scroller = $('calendar-scroller');
    scroller.scrollLeft = horizontalScroll;
    scroller.onscroll = () => { if (mode !== 'fields') calendarScroll = scroller.scrollTop; };
    restoreCalendarScroll();
    renderFields();
  }
  function renderFields() {
    const date = $('field-date').value, time = $('field-time').value;
    $('selected-time').textContent = time;
    $('time-prev').disabled = time === '08:00';
    $('time-next').disabled = time === '21:30';
    $('time-slots').innerHTML = Array.from({length: 28}, (_, i) => { const value = clock(480 + i * 30); return `<button type="button" class="button${value === time ? ' selected' : ''}" data-occupancy-time="${value}" aria-pressed="${value === time}">${value}</button>`; }).join('');
    $('pitches').innerHTML = Object.entries(fields).filter(([id]) => shownFields().includes(id)).map(([id, title]) => {
      const allDaily = events.filter(e => e.field === id && e.date === date).sort((a, b) => a.start.localeCompare(b.start));
      const daily = allDaily.filter(typeVisible);
      return `<article class="pitch-card"><h2>${title}</h2><p>${parts[id].length === 4 ? 'Štvrtina, polovica alebo celé ihrisko' : parts[id].length === 2 ? 'Polovica alebo celé ihrisko' : 'Celá plocha'}</p><div class="pitch parts-${parts[id].length}">${mapParts(id).map(area => {
        const e = allDaily.find(e => selectedParts(e.field, e.area).includes(area) && e.start <= time && e.end > time);
        return `<button class="pitch-half ${e ? `busy${e.type === 'match' ? ' event-match' : ''}` : ''}" ${e ? `data-id="${e.id}"` : `data-field="${id}" data-area="${area}"`}><small>${parts[id].length === 4 ? 'ŠTVRTINA' : parts[id].length === 2 ? 'POLOVICA' : 'PLOCHA'} ${area}</small><strong>${e ? `${e.type === 'match' ? 'ZÁPAS · ' : ''}${escape(e.title)}` : 'Voľná plocha'}</strong><span>${e ? `${e.start} – ${e.end} · ${areaLabel(e.field, e.area)}` : '＋ Rezervovať tento čas'}</span></button>`;
      }).join('')}</div><div class="pitch-list"><h4>REZERVÁCIE V TENTO DEŇ · ${daily.length}</h4>${daily.map(e => `<button class="pitch-booking${e.type === 'match' ? ' event-match' : ''}" data-id="${e.id}"><strong>${e.start} – ${e.end}</strong> · ${e.type === 'match' ? 'ZÁPAS · ' : ''}${escape(e.title)}<br>${areaLabel(e.field, e.area)} · ${escape(e.coach)}</button>`).join('') || '<p>Pre zvolený filter tu nie sú rezervácie.</p>'}</div></article>`;
    }).join('');
  }
  function showForm(data = {}) {
    const form = $('event-form'); form.reset(); editing = data.id || null;
    const defaults = {date: '', start: '', end: '', field: '', area: '', ...data};
    Object.entries(defaults).forEach(([k, v]) => { if (form.elements.namedItem(k)) form.elements.namedItem(k).value = v; });
    $('dialog-title').textContent = editing ? 'Upraviť rezerváciu' : 'Nová rezervácia';
    $('delete-event').hidden = !editing; $('form-error').textContent = ''; updateBookingSummary(); updateFormStep(); updateBookingMapAvailability(); $('event-dialog').showModal();
  }
  function formatReservationDate(value) {
    const date = value instanceof Date ? value : new Date(value + 'T12:00:00');
    return date.toLocaleDateString('sk-SK', {day: 'numeric', month: 'numeric', year: 'numeric'});
  }
  function updateTypeHelp() {
    const form = $('event-form'), type = form.elements.type.value, date = form.elements.date;
    date.removeAttribute('min'); date.removeAttribute('max');
    if (type === 'training') {
      if (!editing) { date.min = localDate(trainingFrom); date.max = localDate(trainingTo); }
      $('type-help').textContent = trainingWindowOpen ? `Tréning možno pridať od dneška do konca nasledujúceho týždňa: ${formatReservationDate(trainingFrom)} – ${formatReservationDate(trainingNextTo)}.` : `Tréning možno pridať od dneška do nedele ${formatReservationDate(trainingCurrentTo)}. Nasledujúci týždeň sa otvorí od štvrtka.`;
    } else if (type === 'match') $('type-help').textContent = 'Zápas možno zapísať ľubovoľne dopredu a nemá limit Áčka.';
    else $('type-help').textContent = 'Najprv vyber tréning alebo zápas.';
  }
  function updateFormStep() {
    const type = $('event-form').elements.type.value;
    $('event-details').hidden = !['training', 'match'].includes(type);
    $('type-help').classList.remove('blocked');
    updateTypeHelp();
  }
  function setDefaultEnd() {
    const form = $('event-form'), type = form.elements.type.value, start = form.elements.start.value;
    if (!type) return;
    form.elements.start.max = type === 'match' ? '19:00' : '20:30';
    if (!start) { updateBookingMapAvailability(); return; }
    const endMinutes = minutes(start) + (type === 'match' ? 180 : 90);
    if (endMinutes > 1320) {
      form.elements.end.value = '';
      $('form-error').textContent = type === 'match' ? 'Zápas musí pri predvolenom trvaní 3 hodiny začať najneskôr o 19:00.' : 'Tréning musí pri predvolenom trvaní 90 minút začať najneskôr o 20:30.';
      updateBookingMapAvailability();
      return;
    }
    form.elements.end.value = clock(endMinutes);
    $('form-error').textContent = '';
    updateBookingMapAvailability();
  }
  function handleTypeChange(event) {
    if (event.target.value === 'match' && event.target.checked && parts.A) {
      const form = $('event-form');
      form.elements.field.value = 'A';
      form.elements.area.value = parts.A.join(',');
      updateBookingSummary();
    }
    updateFormStep();
    if (!$('event-details').hidden) setDefaultEnd();
  }
  function restoreCalendarScroll() {
    if (mode === 'fields') return;
    const target = calendarScroll, scroller = $('calendar-scroller');
    scroller.scrollTop = target;
    requestAnimationFrame(() => { if (scroller.isConnected && mode !== 'fields') scroller.scrollTop = target; });
  }
  function updateBookingSummary() {
    const form = $('event-form');
    $('booking-area-summary').textContent = form.elements.field.value ? `${fields[form.elements.field.value]} · ${areaLabel(form.elements.field.value, form.elements.area.value)}` : 'Vyber ihrisko a plochu';
  }
  function updateBookingMapAvailability() {
    const form = $('event-form');
    const hasTerm = Boolean(form.elements.date.value && form.elements.start.value && form.elements.end.value && form.elements.start.value < form.elements.end.value);
    $('open-booking-map').disabled = !hasTerm;
    if (!form.elements.field.value) $('booking-area-summary').textContent = hasTerm ? 'Vyber ihrisko a plochu' : 'Najprv vyber dátum a čas';
  }
  let pickerMode = 'filter', draft = {};
  function openPicker(context) {
    pickerMode = context;
    if (context === 'filter') draft = Object.fromEntries(Object.entries(filterSelection).map(([id, list]) => [id, [...list]]));
    else {
      const form = $('event-form');
      draft = Object.fromEntries(Object.keys(fields).map(id => [id, id === form.elements.field.value ? [...selectedParts(id, form.elements.area.value)] : []]));
    }
    const form = $('event-form');
    const term = form.elements.date.value && form.elements.start.value ? `${formatReservationDate(form.elements.date.value)} · ${form.elements.start.value}${form.elements.end.value ? ' – ' + form.elements.end.value : ''}` : '';
    $('picker-title').textContent = context === 'filter' ? 'Zobraziť ihriská a časti' : `Vybrať plochu na rezerváciu${term ? ' · ' + term : ''}`;
    $('picker-help').textContent = context === 'filter' ? 'Označ ľubovoľné časti aj z viacerých ihrísk. Kalendár zobrazí všetky rezervácie, ktoré do výberu zasahujú.' : 'Označ štvrtinu, dve susedné štvrtiny alebo celé ihrisko. Obsadenosť platí pre termín vo formulári.';
    $('picker-all').hidden = context !== 'filter';
    $('picker-error').textContent = '';
    renderPicker(); $('pitch-picker').showModal();
  }
  function renderPicker() {
    const form = $('event-form');
    const hasTerm = Boolean(form.elements.date.value && form.elements.start.value && form.elements.end.value && form.elements.start.value < form.elements.end.value);
    $('picker-map').innerHTML = Object.entries(fields).map(([id, name]) => `<div class="map-field ${draft[id].length ? 'active-field' : ''}"><strong>${escape(name)}</strong><div class="map-pitch parts-${parts[id].length}">${mapParts(id).map(part => {
      const active = draft[id].includes(part);
      const busy = pickerMode === 'booking' && hasTerm && events.some(e => e.id !== editing && e.field === id && e.date === form.elements.date.value && e.start < form.elements.end.value && e.end > form.elements.start.value && selectedParts(e.field, e.area).includes(part));
      return `<button type="button" class="map-part ${active ? 'is-chosen' : ''} ${busy ? 'is-busy' : ''}" data-picker-field="${id}" data-picker-part="${part}" aria-pressed="${active}" aria-label="${escape(config[id].parts[part])}${busy ? ', obsadené' : ''}"><b>${escape(part)}</b><span>${escape(config[id].parts[part])}</span><small>${busy ? 'Obsadené' : active ? 'Vybrané ✓' : pickerMode === 'filter' ? 'Nezobrazené' : hasTerm ? 'Voľné' : 'Najprv zadaj termín'}</small></button>`;
    }).join('')}</div><button type="button" class="button map-whole" data-picker-whole="${id}">Celé ihrisko ${escape(id)}</button></div>`).join('');
    $('picker-summary').textContent = `Výber: ${Object.values(draft).flat().join(', ') || 'žiadne časti'}`;
  }
  $('picker-map').onclick = event => {
    const button = event.target.closest('[data-picker-field], [data-picker-whole]'); if (!button) return;
    const id = button.dataset.pickerField || button.dataset.pickerWhole;
    if (pickerMode === 'booking') Object.keys(draft).forEach(key => { if (key !== id) draft[key] = []; });
    if (button.dataset.pickerWhole) draft[id] = [...parts[id]];
    else {
      const part = button.dataset.pickerPart;
      draft[id] = draft[id].includes(part) ? draft[id].filter(p => p !== part) : [...draft[id], part];
    }
    $('picker-error').textContent = ''; renderPicker();
  };
  $('picker-all').onclick = () => { draft = Object.fromEntries(Object.entries(parts).map(([id, list]) => [id, [...list]])); renderPicker(); };
  $('picker-clear').onclick = () => { Object.keys(draft).forEach(id => { draft[id] = []; }); renderPicker(); };
  ['picker-cancel', 'picker-close'].forEach(id => $(id).onclick = () => $('pitch-picker').close());
  $('picker-apply').onclick = () => {
    const ids = Object.keys(draft).filter(id => draft[id].length);
    if (!ids.length) { $('picker-error').textContent = 'Vyber aspoň jednu časť ihriska.'; return; }
    if (pickerMode === 'booking') {
      const id = ids[0], area = draft[id].join(',');
      if (ids.length !== 1 || !validArea(id, area)) { $('picker-error').textContent = 'Vyber štvrtinu, dve susedné štvrtiny alebo celé jedno ihrisko.'; return; }
      const form = $('event-form'); form.elements.field.value = id; form.elements.area.value = area; updateBookingSummary();
    } else {
      filterSelection = Object.fromEntries(Object.entries(draft).map(([id, list]) => [id, [...list]]));
      const all = Object.entries(parts).every(([id, list]) => draft[id].length === list.length);
      $('filter-summary').textContent = all ? 'Všetky ihriská' : Object.values(draft).flat().join(', ');
      render();
    }
    $('pitch-picker').close();
  };
  $('open-field-filter').onclick = () => openPicker('filter');
  $('open-booking-map').onclick = () => openPicker('booking');
  $('morning').onclick = () => { calendarScroll = 0; restoreCalendarScroll(); };
  $('afternoon').onclick = () => { calendarScroll = 432; restoreCalendarScroll(); };
  function setView(view) {
    mode = view; $('calendar-view').hidden = view === 'fields'; $('fields-view').hidden = view !== 'fields';
    ['calendar', 'day', 'fields'].forEach(v => { $('view-' + v).classList.toggle('selected', v === view); $('view-' + v).setAttribute('aria-pressed', String(v === view)); });
    $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false');
    render();
  }
  document.addEventListener('click', event => {
    const el = event.target.closest('[data-id], [data-time], [data-field]');
    if (el?.dataset.id) showForm(events.find(e => e.id === Number(el.dataset.id)));
    else if (el?.dataset.time) showForm({date: el.dataset.date, start: el.dataset.time, end: clock(Math.min(minutes(el.dataset.time) + 90, 1320)), ...(el.dataset.field ? {field: el.dataset.field, area: el.dataset.area} : {})});
    else if (el?.dataset.field && $('field-date').value && $('field-time').value) { const start = $('field-time').value; showForm({date: $('field-date').value, start, end: clock(Math.min(minutes(start) + 90, 1320)), field: el.dataset.field, area: el.dataset.area}); }
    if (!event.target.closest('.menu-wrap')) { $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false'); }
  });
  $('menu-toggle').onclick = () => { $('main-menu').hidden = !$('main-menu').hidden; $('menu-toggle').setAttribute('aria-expanded', String(!$('main-menu').hidden)); };
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false'); } });
  ['calendar', 'day', 'fields'].forEach(v => { $('view-' + v).onclick = () => setView(v); $('menu-' + v).onclick = () => setView(v); });
  function moveDate(direction) {
    if (mode !== 'calendar') { selectedDay = dayAt(selectedDay, direction); week = monday(selectedDay); }
    else { week = dayAt(week, direction * 7); selectedDay = dayAt(selectedDay, direction * 7); }
    $('field-date').value = localDate(selectedDay); render();
  }
  $('prev').onclick = () => moveDate(-1);
  $('next').onclick = () => moveDate(1);
  $('today').onclick = () => { selectedDay = new Date(); week = monday(selectedDay); $('field-date').value = localDate(selectedDay); render(); };
  $('field-date').onchange = () => { if ($('field-date').value) { selectedDay = new Date($('field-date').value + 'T12:00:00'); week = monday(selectedDay); render(); } };
  function selectTime(value) { $('field-time').value = value; renderFields(); }
  $('time-slots').onclick = event => { const button = event.target.closest('[data-occupancy-time]'); if (button) selectTime(button.dataset.occupancyTime); };
  $('time-prev').onclick = () => selectTime(clock(Math.max(480, minutes($('field-time').value) - 30)));
  $('time-next').onclick = () => selectTime(clock(Math.min(1290, minutes($('field-time').value) + 30)));
  $('add-event').onclick = () => showForm();
  document.querySelectorAll('[data-type-filter]').forEach(button => button.onclick = () => {
    typeFilter = button.dataset.typeFilter;
    document.querySelectorAll('[data-type-filter]').forEach(item => { const selected = item === button; item.classList.toggle('selected', selected); item.setAttribute('aria-pressed', String(selected)); });
    render();
  });
  document.querySelectorAll('input[name="type"]').forEach(input => input.onchange = handleTypeChange);
  $('event-form').elements.start.onchange = setDefaultEnd;
  $('event-form').elements.date.onchange = updateBookingMapAvailability;
  $('event-form').elements.end.onchange = updateBookingMapAvailability;
  ['close-dialog', 'cancel-dialog'].forEach(id => $(id).onclick = () => $('event-dialog').close());
  let confirmationResolve = null;
  function finishConfirmation(result) {
    if (!confirmationResolve) return;
    const resolve = confirmationResolve;
    confirmationResolve = null;
    $('confirm-dialog').close();
    resolve(result);
  }
  function askConfirmation(message) {
    $('confirm-message').textContent = message;
    $('confirm-dialog').showModal();
    return new Promise(resolve => { confirmationResolve = resolve; });
  }
  $('confirm-no').onclick = () => finishConfirmation(false);
  $('confirm-close').onclick = () => finishConfirmation(false);
  $('confirm-yes').onclick = () => finishConfirmation(true);
  $('confirm-dialog').addEventListener('cancel', event => { event.preventDefault(); finishConfirmation(false); });
  $('delete-event').onclick = async () => {
    if (!editing || !await askConfirmation('Naozaj chceš vymazať túto rezerváciu? Táto zmena sa nedá vrátiť späť.')) return;
    const index = events.findIndex(e => e.id === editing);
    if (index >= 0) events.splice(index, 1);
    $('event-dialog').close();
    render();
  };
  $('event-form').onsubmit = event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.target)); data.title = data.title.trim(); data.coach = data.coach.trim();
    if (!['training', 'match'].includes(data.type)) { $('form-error').textContent = 'Vyber tréning alebo zápas.'; return; }
    if (!data.title || !data.coach || data.start >= data.end || data.start < '08:00' || data.end > '22:00') { $('form-error').textContent = 'Vyplň názov a trénera. Koniec musí byť po začiatku, v rozsahu 08:00 – 22:00.'; return; }
    if (!validArea(data.field, data.area)) { $('form-error').textContent = 'Vyber jednu štvrtinu, dve susedné štvrtiny (polovicu) alebo celé ihrisko.'; return; }
    if (events.some(e => e.id !== editing && e.date === data.date && e.field === data.field && overlap(e, data) && e.start < data.end && e.end > data.start)) { $('form-error').textContent = 'Táto plocha je v zadanom čase obsadená. Vyber iný čas alebo inú časť ihriska.'; return; }
    if (editing) Object.assign(events.find(e => e.id === editing), data);
    else events.push({...data, id: Math.max(0, ...events.map(e => e.id)) + 1});
    selectedDay = new Date(data.date + 'T12:00:00'); week = monday(selectedDay); $('field-date').value = data.date;
    $('event-dialog').close(); render();
  };
  $('field-legend').innerHTML = Object.entries(fields).map(([id, name]) => `<span><i class="${colors[id]}"></i>${escape(name)}</span>`).join('');
  render();
})();
