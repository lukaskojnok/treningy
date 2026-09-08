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
  let selectedDay = new Date(), week = monday(new Date()), editing = null, mode = 'quick';
  const events = [];
  const account = JSON.parse($('reservation-config').textContent);
  const allowedTeams = Object.keys(account.teams);
  const isOwnTeam = event => Object.prototype.hasOwnProperty.call(account.teams, event.team);
  let editingVersion = null, saving = false, loaded = false, loadSequence = 0, quickBooking = false, quickTerm = null;
  $('field-date').value = localDate(new Date());
  const typeVisible = e => typeFilter === 'all' || e.type === typeFilter;
  const visible = e => typeVisible(e) && selectedParts(e.field, e.area).some(part => filterSelection[e.field]?.includes(part));
  let calendarScroll = 432;
  async function api(data, query = '') {
    const options = {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}};
    if (data) Object.assign(options, {method: 'POST', headers: {...options.headers, 'Content-Type': 'application/json', 'X-CSRF-Token': account.csrf}, body: JSON.stringify(data)});
    const response = await fetch('api/reservations.php' + query, options);
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Server nevrátil platnú odpoveď. Skontroluj prihlásenie alebo kontaktuj správcu.'); }
    if (!response.ok || !result.ok) { const error = new Error(result.message || 'Požiadavka zlyhala.'); error.status = response.status; throw error; }
    return result;
  }
  function calendarStatus(message, retry = false) {
    $('calendar-status').textContent = [message, ...(account.warnings || [])].filter(Boolean).join(' ');
    $('calendar-status').hidden = !$('calendar-status').textContent;
    $('reload-calendar').hidden = !retry;
  }
  async function render() {
    const sequence = ++loadSequence;
    loaded = false; $('add-event').disabled = true;
    events.splice(0); renderView(); calendarStatus('Načítavam rezervácie…');
    const from = mode === 'quick' ? account.trainingWindow.from : localDate(week);
    const to = mode === 'quick' ? account.trainingWindow.to : localDate(dayAt(week, 6));
    try {
      const result = await api(null, '?from=' + from + '&to=' + to);
      if (sequence !== loadSequence) return;
      events.push(...result.events); loaded = true;
      $('add-event').disabled = !allowedTeams.length;
      renderView(); calendarStatus('');
    } catch (error) { if (sequence === loadSequence) calendarStatus(error.message, true); }
  }
  function renderView() {
    const quick = mode === 'quick';
    $('calendar-view').hidden = mode === 'fields' || quick;
    $('fields-view').hidden = mode !== 'fields';
    $('quick-view').hidden = !quick;
    document.querySelector('.type-filter').hidden = quick;
    document.querySelector('.date-nav').hidden = quick;
    document.querySelector('.filter').hidden = quick;
    document.querySelector('.legend').hidden = quick;
    if (quick) { renderQuick(); return; }
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
          columns += `<button class="event ${colors[e.field]}${e.type === 'match' ? ' event-match' : ''}${sameTime ? ' event-compact' : ''}${isOwnTeam(e) ? '' : ' event-muted'}" data-id="${e.id}" style="top:${top}px;height:${height}px;left:${left};width:${width}" title="${escape(kind + ' · ' + e.title + ' · ' + e.start + '–' + e.end + ' · ' + e.coach + ' · ' + fields[e.field] + ' · ' + areaLabel(e.field, e.area))}"><span class="event-area">${escape(selectedParts(e.field, e.area).join(" + "))}</span><span class="event-time">${e.start} – ${e.end}</span><strong>${e.type === 'match' ? 'ZÁPAS · ' : ''}${escape(e.title)}</strong><small>${escape(fields[e.field])} · ${areaLabel(e.field, e.area)}</small><small>${escape(e.coach)}</small></button>`;
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
  function renderQuick() {
    const from = new Date(account.trainingWindow.from + 'T12:00:00');
    const to = new Date(account.trainingWindow.to + 'T12:00:00');
    const today = localDate(new Date());
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const days = [];
    for (let date = new Date(from); date <= to; date = dayAt(date, 1)) {
      const value = localDate(date);
      const rows = [];
      for (let slot = 480; slot <= 1230; slot += 30) {
        const start = clock(slot), end = clock(slot + 90);
        const started = value < today || (value === today && slot <= currentMinutes);
        const reservations = events.filter(event => event.date === value && event.start === start);
        const saved = reservations.map(event => `<button type="button" class="quick-reservation${event.type === 'match' ? ' is-match' : ''}${isOwnTeam(event) ? '' : ' event-muted'}" data-id="${event.id}" title="Otvoriť detail"><b>✓</b><span>${event.type === 'match' ? 'Zápas · ' : ''}${escape(event.title)}</span><small>${event.start} – ${event.end} · ${escape(event.field)} · ${escape(areaLabel(event.field, event.area))}</small></button>`).join('');
        rows.push(`<div class="quick-row${started ? ' is-past' : ''}"><time datetime="${value}T${start}">${start}<small class="quick-date">${date.getDate()}. ${date.getMonth() + 1}. · ${date.toLocaleDateString('sk-SK', {weekday: 'long'})}</small></time><div class="quick-reservations">${saved}</div><button type="button" class="button quick-add" data-quick-date="${value}" data-quick-time="${start}"${started || !allowedTeams.length || !loaded ? ' disabled' : ''}>＋ Pridať tréning</button></div>`);
      }
      days.push(`<article class="quick-day"><h3><strong>${date.toLocaleDateString('sk-SK', {day: 'numeric', month: 'numeric', year: 'numeric'})}</strong><span>${date.toLocaleDateString('sk-SK', {weekday: 'long'})}</span></h3>${rows.join('')}</article>`);
    }
    $('quick-list').innerHTML = days.join('') || '<p>Momentálne nie sú otvorené žiadne termíny na pridanie tréningu.</p>';
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
        return `<button class="pitch-half ${e ? `busy${e.type === 'match' ? ' event-match' : ''}${isOwnTeam(e) ? '' : ' event-muted'}` : ''}" ${e ? `data-id="${e.id}"` : `data-field="${id}" data-area="${area}"`}><small>${parts[id].length === 4 ? 'ŠTVRTINA' : parts[id].length === 2 ? 'POLOVICA' : 'PLOCHA'} ${area}</small><strong>${e ? `${e.type === 'match' ? 'ZÁPAS · ' : ''}${escape(e.title)}` : 'Voľná plocha'}</strong><span>${e ? `${e.start} – ${e.end} · ${areaLabel(e.field, e.area)}` : '＋ Rezervovať tento čas'}</span></button>`;
      }).join('')}</div><div class="pitch-list"><h4>REZERVÁCIE V TENTO DEŇ · ${daily.length}</h4>${daily.map(e => `<button class="pitch-booking${e.type === 'match' ? ' event-match' : ''}${isOwnTeam(e) ? '' : ' event-muted'}" data-id="${e.id}"><strong>${e.start} – ${e.end}</strong> · ${e.type === 'match' ? 'ZÁPAS · ' : ''}${escape(e.title)}<br>${areaLabel(e.field, e.area)} · ${escape(e.coach)}</button>`).join('') || '<p>Pre zvolený filter tu nie sú rezervácie.</p>'}</div></article>`;
    }).join('');
  }
  function showForm(data = {}) {
    if (!loaded || saving) return;
    prepareForm(data);
    $('event-dialog').showModal();
  }
  function prepareForm(data = {}) {
    const form = $('event-form'); form.reset(); editing = data.id || null; editingVersion = data.version || null;
    const canEdit = editing ? data.canEdit : allowedTeams.length > 0;
    form.dataset.canEdit = String(canEdit);
    form.elements.team.innerHTML = '<option value="">Vyber tím</option>' + Object.entries(account.teams).map(([key, label]) => `<option value="${escape(key)}">${escape(label)}</option>`).join('');
    if (editing && !account.teams[data.team]) form.elements.team.add(new Option(data.title, data.team));
    Array.from(form.elements).forEach(el => { if (el.name && el.name !== 'coach') el.disabled = !canEdit; });
    $('save-event').hidden = !canEdit;
    const defaults = {date: '', start: '', end: '', field: '', area: '', team: allowedTeams.length === 1 ? allowedTeams[0] : '', coach: account.coach, ...data};
    Object.entries(defaults).forEach(([k, v]) => { if (form.elements.namedItem(k)) form.elements.namedItem(k).value = v; });
    $('dialog-title').textContent = editing ? 'Upraviť rezerváciu' : 'Nová rezervácia';
    $('delete-event').hidden = !editing || !canEdit; $('form-error').textContent = canEdit ? '' : (data.editReason || 'Túto rezerváciu nemôžeš upraviť.'); updateBookingSummary(); updateFormStep(); updateBookingMapAvailability();
  }
  function formatReservationDate(value) {
    if (!value) return '';
    return new Date(value + 'T12:00:00').toLocaleDateString('sk-SK', {day: 'numeric', month: 'numeric', year: 'numeric'});
  }
  function updateTypeHelp() {
    const form = $('event-form'), type = form.elements.type.value, date = form.elements.date;
    const trainingNotice = account.trainingWindow.isOpen ? `Tréning možno pridať od dneška do konca nasledujúceho týždňa: ${formatReservationDate(account.trainingWindow.from)} – ${formatReservationDate(account.trainingWindow.nextTo)}.` : `Tréning možno pridať od dneška do nedele ${formatReservationDate(account.trainingWindow.currentTo)}. Nasledujúci týždeň sa otvorí od ${account.trainingWindow.openDayLabel}.`;
    date.removeAttribute('min'); date.removeAttribute('max');
    if (type === 'training') {
      if (!editing) { date.min = account.trainingWindow.from; date.max = account.trainingWindow.to; }
      const team = form.elements.team.value, limit = team ? account.teamLimits[team] : undefined;
      const limitText = limit === null ? ' Na Áčku nemá tento tím týždenný limit.' : limit > 0 ? ` Na Áčku môže mať najviac ${limit}× za týždeň.` : team ? ' Pre tento tím nie je nastavené pravidlo Áčka.' : '';
      $('type-help').textContent = trainingNotice + limitText;
    } else if (type === 'match') $('type-help').textContent = 'Zápas môžeš zapísať ľubovoľne dopredu a nevzťahuje sa naň týždenný limit Áčka.';
    else $('type-help').textContent = trainingNotice + ' Najprv vyber tréning alebo zápas.';
    $('type-help').classList.toggle('training-notice', type !== 'match');
  }
  function updateFormStep() {
    const type = $('event-form').elements.type.value;
    $('event-details').hidden = !['training', 'match'].includes(type);
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
    $('open-booking-map').disabled = form.dataset.canEdit !== 'true' || !hasTerm;
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
    $('picker-apply').textContent = quickBooking ? 'Uložiť rezerváciu' : 'Použiť výber';
    $('picker-cancel').textContent = quickBooking ? 'Zrušiť' : 'Zavrieť bez zmeny';
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
  function closePicker() {
    $('pitch-picker').close();
    quickBooking = false;
    quickTerm = null;
    $('picker-apply').textContent = 'Použiť výber';
    $('picker-cancel').textContent = 'Zavrieť bez zmeny';
  }
  ['picker-cancel', 'picker-close'].forEach(id => $(id).onclick = closePicker);
  $('picker-apply').onclick = async () => {
    const ids = Object.keys(draft).filter(id => draft[id].length);
    if (!ids.length) { $('picker-error').textContent = 'Vyber aspoň jednu časť ihriska.'; return; }
    if (pickerMode === 'booking') {
      const id = ids[0], area = draft[id].join(',');
      if (ids.length !== 1 || !validArea(id, area)) { $('picker-error').textContent = 'Vyber štvrtinu, dve susedné štvrtiny alebo celé jedno ihrisko.'; return; }
      const form = $('event-form'); form.elements.field.value = id; form.elements.area.value = area; updateBookingSummary();
      if (quickBooking) {
        const data = Object.fromEntries(new FormData(form));
        $('picker-apply').disabled = true;
        $('picker-error').textContent = '';
        try {
          await api({...data, action: 'save', id: 0, version: 0});
          closePicker();
          await render();
        } catch (error) {
          $('picker-error').textContent = error.message;
        } finally {
          $('picker-apply').disabled = false;
        }
        return;
      }
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
    mode = view;
    ['calendar', 'day', 'fields', 'quick'].forEach(v => { $('view-' + v).classList.toggle('selected', v === view); $('view-' + v).setAttribute('aria-pressed', String(v === view)); });
    $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false');
    render();
  }
  document.addEventListener('click', event => {
    const quickAdd = event.target.closest('[data-quick-date]');
    if (quickAdd) { startQuickReservation(quickAdd.dataset.quickDate, quickAdd.dataset.quickTime); return; }
    const el = event.target.closest('[data-id], [data-time], [data-field]');
    if (el?.dataset.id) showForm(events.find(e => e.id === Number(el.dataset.id)));
    else if (el?.dataset.time) showForm({date: el.dataset.date, start: el.dataset.time, end: clock(Math.min(minutes(el.dataset.time) + 90, 1320)), ...(el.dataset.field ? {field: el.dataset.field, area: el.dataset.area} : {})});
    else if (el?.dataset.field && $('field-date').value && $('field-time').value) { const start = $('field-time').value; showForm({date: $('field-date').value, start, end: clock(Math.min(minutes(start) + 90, 1320)), field: el.dataset.field, area: el.dataset.area}); }
    if (!event.target.closest('.menu-wrap')) { $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false'); }
  });
  $('menu-toggle').onclick = () => { $('main-menu').hidden = !$('main-menu').hidden; $('menu-toggle').setAttribute('aria-expanded', String(!$('main-menu').hidden)); };
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { $('main-menu').hidden = true; $('menu-toggle').setAttribute('aria-expanded', 'false'); } });
  ['calendar', 'day', 'fields', 'quick'].forEach(v => { $('view-' + v).onclick = () => setView(v); $('menu-' + v).onclick = () => setView(v); });
  function startQuickReservation(date, start) {
    if (!loaded || saving || !allowedTeams.length) return;
    quickTerm = {date, start, end: clock(minutes(start) + 90)};
    if (allowedTeams.length === 1) { continueQuickReservation(allowedTeams[0]); return; }
    $('quick-team-options').innerHTML = allowedTeams.map(team => `<button type="button" class="button" data-quick-team="${escape(team)}">${escape(account.teams[team])}</button>`).join('');
    $('quick-team-dialog').showModal();
  }
  function continueQuickReservation(team) {
    if (!quickTerm || !account.teams[team]) return;
    if ($('quick-team-dialog').open) $('quick-team-dialog').close();
    prepareForm({type: 'training', team, date: quickTerm.date, start: quickTerm.start, end: quickTerm.end, note: ''});
    quickBooking = true;
    openPicker('booking');
  }
  $('quick-team-options').onclick = event => { const button = event.target.closest('[data-quick-team]'); if (button) continueQuickReservation(button.dataset.quickTeam); };
  $('quick-team-close').onclick = () => { $('quick-team-dialog').close(); quickTerm = null; };
  $('quick-team-dialog').addEventListener('cancel', () => { quickTerm = null; });
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
    renderView();
  });
  document.querySelectorAll('input[name="type"]').forEach(input => input.onchange = handleTypeChange);
  $('event-form').elements.team.onchange = updateTypeHelp;
  $('event-form').elements.start.onchange = setDefaultEnd;
  $('event-form').elements.date.onchange = updateBookingMapAvailability;
  $('event-form').elements.end.onchange = updateBookingMapAvailability;
  ['close-dialog', 'cancel-dialog'].forEach(id => $(id).onclick = () => $('event-dialog').close());
  function setSaving(value) {
    saving = value;
    $('save-event').disabled = value;
    $('delete-event').disabled = value;
    $('cancel-dialog').disabled = value;
    $('close-dialog').disabled = value;
  }
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
  $('event-dialog').addEventListener('cancel', event => { if (saving) event.preventDefault(); });
  $('delete-event').onclick = async () => {
    if (saving || !editing) return;
    if (!await askConfirmation('Naozaj chceš vymazať túto rezerváciu? Táto zmena sa nedá vrátiť späť.')) return;
    setSaving(true);
    try {
      await api({action: 'delete', id: editing, version: editingVersion});
      $('event-dialog').close(); await render();
    } catch (error) { $('form-error').textContent = error.message; if (error.status === 409) await render(); }
    finally { setSaving(false); }
  };
  $('event-form').onsubmit = async event => {
    event.preventDefault();
    if (saving) return;
    const data = Object.fromEntries(new FormData(event.target));
    if (!['training', 'match'].includes(data.type)) { $('form-error').textContent = 'Vyber tréning alebo zápas.'; return; }
    if (!account.teams[data.team]) { $('form-error').textContent = 'Vyber svoj tím.'; return; }
    if (!data.date || !data.start || !data.end || data.start >= data.end || data.start < '08:00' || data.end > '22:00') { $('form-error').textContent = 'Vyplň dátum a platný čas od 08:00 do 22:00.'; return; }
    if (!validArea(data.field, data.area)) { $('form-error').textContent = 'Vyber štvrtinu, polovicu alebo celé ihrisko.'; return; }
    setSaving(true); $('form-error').textContent = '';
    try {
      await api({...data, action: 'save', id: editing || 0, version: editingVersion || 0});
      selectedDay = new Date(data.date + 'T12:00:00'); week = monday(selectedDay); $('field-date').value = data.date;
      $('event-dialog').close(); await render();
    } catch (error) { $('form-error').textContent = error.message; if (error.status === 409) await render(); }
    finally { setSaving(false); }
  };
  $('reload-calendar').onclick = () => render();
  $('field-legend').innerHTML = Object.entries(fields).map(([id, name]) => `<span><i class="${colors[id]}"></i>${escape(name)}</span>`).join('');
  render();
})();
