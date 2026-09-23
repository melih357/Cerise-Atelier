const API = '../backend/api.php';
let meta = { countries: [], crime_types: [] };
const qs = (id) => document.getElementById(id);
const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));

async function api(action, data = null) {
  const options = data ? { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify(data) } : { credentials:'include' };
  const res = await fetch(`${API}?action=${action}`, options);
  return await res.json();
}

function optionList(select, items, label = 'name') {
  select.innerHTML = items.map(i => `<option value="${i.id}">${esc(i[label])}</option>`).join('');
}

async function init(){
  meta = await api('meta');
  optionList(qs('adminCountry'), meta.countries || []);
  optionList(qs('adminCrime'), meta.crime_types || []);
  qs('panelCard').classList.remove('hidden');
  loadList();
}


async function adminSave(){
  const payload = {
    country_id: Number(qs('adminCountry').value),
    crime_type_id: Number(qs('adminCrime').value),
    law_article: qs('lawArticle').value,
    possible_penalty: qs('penalty').value,
    process_note: qs('process').value,
    rights_note: qs('rights').value,
    judge_simulation_note: qs('judgeNote').value,
    source_note: qs('sourceNote').value
  };
  const data = await api('admin_save', payload);
  qs('adminStatus').textContent = data.message || (data.ok ? 'Kaydedildi.' : 'Kayıt başarısız.');
  if (data.ok) loadList();
}

async function loadList(){
  const data = await api('admin_list');
  if (!data.ok) return qs('adminList').innerHTML = `<p class="muted">${esc(data.message || 'Liste alınamadı')}</p>`;
  qs('adminList').innerHTML = data.items.map(i => `<div class="compare"><h3>${esc(i.country_name)} · ${esc(i.crime_name)}</h3><p><b>Madde:</b> ${esc(i.law_article || '-')}</p><p><b>Yaptırım:</b> ${esc(i.possible_penalty || '-')}</p><p class="muted">${esc(i.source_note || '')}</p></div>`).join('');
}

qs('adminSaveBtn').addEventListener('click', adminSave);
qs('refreshBtn').addEventListener('click', loadList);
init();
