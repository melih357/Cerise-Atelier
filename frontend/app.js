const API = '../backend/api.php';
let meta = { countries: [], crime_types: [] };
let currentQuestions = [];

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

async function init() {
  meta = await api('meta');
  if (!meta.ok) return alert('Veritabanı bağlantısı kurulamadı. database.sql içe aktarıldı mı?');
  qs('disclaimer').textContent = meta.disclaimer;
  optionList(qs('countrySelect'), meta.countries);
  optionList(qs('crimeSelect'), meta.crime_types);
  handleCountryChange();
}

function handleCountryChange(){
  const country = meta.countries.find(c => String(c.id) === qs('countrySelect').value);
  const show = country && Number(country.requires_region) === 1;
  qs('regionLabel').classList.toggle('hidden', !show);
  qs('regionInput').classList.toggle('hidden', !show);
  qs('countryInfo').innerHTML = country ? `<b>${esc(country.official_name || country.name)}</b><br>${esc(country.legal_family || '')} · ${esc(country.government_system || '')}<br><span>${esc(country.legal_system_note || '')}</span>` : '';
}

function collectAnswers(){
  const answers = {};
  currentQuestions.forEach(q => {
    if (q.key === 'value') {
      const amount = qs('q_value_amount')?.value || '';
      const currency = qs('q_value_currency')?.value || '';
      const type = qs('q_value_type')?.value || '';
      answers.value = [amount ? `Tutar/değer: ${amount}` : '', currency ? `Para birimi: ${currency}` : '', type ? `Değer türü: ${type}` : ''].filter(Boolean).join(' · ');
    } else {
      answers[q.key] = qs(`q_${q.key}`)?.value || '';
    }
  });
  if (qs('regionInput').value) answers.region = qs('regionInput').value;
  return answers;
}

const questionOptions = {
  force: ['Hayır, zor kullanma/tehdit yok', 'Evet, tehdit vardı', 'Evet, fiziksel müdahale vardı', 'Emin değilim'],
  first_offense: ['Evet, ilk olayım', 'Hayır, daha önce benzer kayıt/dava var', 'Devam eden başka dosya var', 'Emin değilim'],
  complaint: ['Mağdur şikâyetçi değil / bilmiyorum', 'Mağdur şikâyetçi', 'Zarar giderildi', 'Uzlaşma ihtimali var', 'Emin değilim'],
  age: ['18 yaşından büyüktüm', '18 yaşından küçüktüm', '15 yaşından küçüktüm', 'Emin değilim'],
  evidence: ['Kamera kaydı var', 'Mesaj/sosyal medya kaydı var', 'Tanık var', 'Belge/banka hareketi var', 'Delil yok / bilmiyorum'],
  place_time: ['Mağaza/işyeri', 'Ev/özel alan', 'Okul', 'İnternet/sosyal medya', 'Trafik/kamuya açık alan', 'Tarih ve yer net değil'],
  region: ['Eyalet/bölge bilmiyorum', 'Bölgeyi ayrıca yukarıya yazdım'],
  ownership: ['Eşya tamamen başkasına aitti', 'Emanet verilmişti', 'Ortak kullanımdı', 'Bana ait olduğunu düşündüm', 'Emin değilim'],
  return: ['İade edildi', 'Zarar karşılandı', 'Kısmen karşılandı', 'İade edilmedi / zarar duruyor', 'Emin değilim'],
  deception: ['Sahte beyan yok', 'Sahte ilan/mesaj vardı', 'Sahte belge/hesap vardı', 'Yanlış anlaşılma olabilir', 'Emin değilim'],
  benefit: ['Menfaat elde edilmedi', 'Para alındı', 'Ürün/hizmet alındı', 'Hesap/erişim elde edildi', 'Emin değilim'],
  message_record: ['Mesaj kaydı var', 'Ses/video kaydı var', 'Tanık var', 'Kayıt yok', 'Emin değilim'],
  publicity: ['Herkese açık ortamda oldu', 'Özel mesaj/özel ortamda oldu', 'Sınırlı kişi gördü', 'Emin değilim'],
  injury_report: ['Doktor/darp raporu var', 'Rapor yok ama yaralanma var', 'Basit kızarıklık/ağrı var', 'Yaralanma yok', 'Emin değilim']
};

function renderQuestionControl(q){
  if (q.key === 'value') {
    return `<div class="value-grid">
      <input id="q_value_amount" inputmode="decimal" placeholder="Örn: 1500" />
      <select id="q_value_currency">
        <option value="TL">TL</option><option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option><option value="Diğer">Diğer</option>
      </select>
      <select id="q_value_type">
        <option value="Yaklaşık piyasa değeri">Yaklaşık piyasa değeri</option>
        <option value="Zarar miktarı">Zarar miktarı</option>
        <option value="Elde edilen menfaat">Elde edilen menfaat</option>
        <option value="Bilmiyorum">Bilmiyorum</option>
      </select>
    </div>`;
  }
  const opts = questionOptions[q.key];
  if (opts) return `<select id="q_${esc(q.key)}"><option value="">Seçiniz</option>${opts.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('')}</select>`;
  return `<input id="q_${esc(q.key)}" placeholder="Kısa cevap yazın" />`;
}

async function analyze(force = false){
  const payload = {
    country_id: Number(qs('countrySelect').value),
    region: qs('regionInput').value,
    event_text: qs('eventText').value,
    answers: collectAnswers(),
    force_result: force
  };
  const data = await api('analyze', payload);
  if (!data.ok) return alert(data.message || 'Hata oluştu');
  if (data.needs_more_info) {
    currentQuestions = data.questions;
    qs('questionsBox').classList.remove('empty-state');
    qs('questionsBox').innerHTML = currentQuestions.map(q => `<div class="question"><label>${esc(q.label)}</label>${renderQuestionControl(q)}</div>`).join('');
    qs('finalBtn').classList.remove('hidden');
    qs('resultCard').classList.add('hidden');
    return;
  }
  renderResult(data.analysis);
}

function listHtml(items){
  return `<ul>${(items || []).map(i => `<li>${esc(i)}</li>`).join('')}</ul>`;
}

function renderResult(a){
  qs('resultTitle').textContent = `${a.country} · ${a.possible_crime_type}`;
  qs('certaintyPill').textContent = `Netlik: %${a.certainty_score} · ${a.certainty_label}`;
  qs('summaryBox').innerHTML = `<h3>Özet Değerlendirme</h3><p>${esc(a.case_summary)}</p><p><b>Beklenen yön:</b> ${esc(a.likely_outcome)}</p>`;

  const topCards = [
    ['Suç ihtimali', a.crime_probability, 'mini'],
    ['Risk seviyesi', a.risk_level, 'mini'],
    ['Avukata danışma', a.lawyer_need, 'mini'],
    ['Eyalet/Bölge', a.region || a.region_warning || 'Özel uyarı yok', 'mini'],
    ['Olası kanun maddeleri', a.law_articles, 'wide'],
    ['Olası yaptırımlar', a.possible_penalty_range, 'wide'],
    ['Sürecin adımları', a.process, 'wide'],
    ['Haklarım / Dikkat', a.rights, 'wide'],
  ];

  const factors = `<div class="result-item wide"><strong>Sonucu Değiştiren Kritik Bilgiler</strong>${(a.decisive_factors || []).map(f => `<p><b>${esc(f.label)}:</b> ${esc(f.value)}</p>`).join('')}</div>`;
  const judicial = `<div class="result-item wide"><strong>Yargı Değerlendirme Simülasyonu</strong>${Object.entries(a.judicial_simulation || {}).map(([k,v]) => `<p><b>${esc(k)}</b><br>${esc(v)}</p>`).join('')}</div>`;
  const next = `<div class="result-item wide"><strong>Şimdi Ne Yapılmalı?</strong>${listHtml(a.next_steps)}</div>`;
  const evidence = `<div class="result-item wide"><strong>Delil Kontrol Listesi</strong>${listHtml(a.evidence_checklist)}</div>`;
  const system = `<div class="result-item wide muted-card"><strong>Ülke Hukuk Mantığı</strong><p>${esc(a.law_style_note || '')}</p><p>${esc(a.legal_family || '')} · ${esc(a.government_system || '')}</p><p>${esc(a.source_note || 'Genel tohum veri')}</p><p>${esc(a.disclaimer)}</p></div>`;

  qs('resultGrid').innerHTML = topCards.map(([k,v,w]) => `<div class="result-item ${w}"><strong>${esc(k)}</strong><span>${esc(v)}</span></div>`).join('') + factors + judicial + next + evidence + system;
  qs('resultCard').classList.remove('hidden');
  qs('resultCard').scrollIntoView({behavior:'smooth'});
}

async function compare(){
  const data = await api('compare', { crime_type_id: Number(qs('crimeSelect').value), limit: 30 });
  qs('compareBox').innerHTML = data.items.map(i => `<div class="compare"><h3>${esc(i.country_name)}</h3><p><b>Sistem:</b> ${esc(i.legal_family || '-')} · ${esc(i.government_system || '-')}</p><p><b>Kanun başlığı:</b> ${esc(i.law_article || '-')}</p><p><b>Aralık:</b> ${esc(i.possible_penalty || '-')}</p><p><b>Süreç:</b> ${esc(i.process_note || '')}</p><p class="muted">${esc(i.source_note || '')}</p></div>`).join('') || '<p class="muted">Bu suç için karşılaştırma verisi henüz yok.</p>';
}


function openLovePanel(){
  qs('lovePanel').classList.remove('hidden');
  qs('lovePanel').scrollIntoView({behavior:'smooth', block:'start'});
  setTimeout(() => qs('girlName')?.focus(), 250);
}

function closeLovePanel(){
  qs('lovePanel').classList.add('hidden');
}

function normalizeName(name){
  return String(name || '').trim().toLocaleLowerCase('tr-TR').replace(/\s+/g, ' ');
}

function loveScore(girl, boy){
  const girlClean = normalizeName(girl).replace(/ /g,'');
  const boyClean = normalizeName(boy).replace(/ /g,'');
  const text = `${girlClean}|${boyClean}`;
  let hash = 0;
  for (let i = 0; i < text.length; i++) hash = ((hash << 5) - hash) + text.charCodeAt(i);
  const base = Math.abs(hash % 101); // 0-100 arası, düşük ve yüksek sürprizler çıkabilir
  const shared = [...new Set(girlClean)].filter(ch => boyClean.includes(ch)).length;
  const balanceBonus = Math.max(0, 6 - Math.abs(girlClean.length - boyClean.length));
  return Math.max(1, Math.min(100, base + Math.min(shared, 6) + balanceBonus));
}

function loveMood(score){
  if (score === 100) return { emoji:'💖🎉🎊✨🎆', title:'Kalp dosyası: efsane uyum!', cls:'legend', msg:'%100 çıktı! Kalpler imzayı atmış, konfeti patladı. Birbirinize en uyumlu çift sizsiniz!' };
  if (score >= 90) return { emoji:'💘✨🌹', title:'Aşkta beraat değil, direkt müebbet uyum!', cls:'high', msg:'Birbirinize en uyumlu çiftlerden birisiniz. Bu dosyada kalpler aynı kararı vermiş gibi duruyor.' };
  if (score >= 75) return { emoji:'😍🔥🍒', title:'Güçlü çekim var', cls:'good', msg:'Aranızda ciddi bir elektrik var. Bu aşk dosyası umut veriyor, devamı gelebilir.' };
  if (score >= 50) return { emoji:'🙂☕💬', title:'Orta uyum', cls:'mid', msg:'Biraz sohbet, biraz emek, biraz da doğru zaman gerekebilir. Dosya kapanmadı.' };
  if (score >= 30) return { emoji:'😬📁⚖️', title:'Zorlama istersen...', cls:'low', msg:'Kalp mahkemesi bu dosyaya çok sıcak bakmadı. Belki arkadaş kalmak daha güvenli olabilir.' };
  return { emoji:'💤🌙😂', title:'Anca rüyanda', cls:'dream', msg:'Sonuç baya düşük çıktı. Yine de üzülme; bu sadece eğlencelik bir kalp dosyası.' };
}

function loveMessage(score, girl, boy){
  const mood = loveMood(score);
  return `${girl} ve ${boy}: ${mood.msg}`;
}

function calculateLove(){
  const girl = qs('girlName').value.trim();
  const boy = qs('boyName').value.trim();
  if (!girl || !boy) return alert('İki ismi de yazmalısın.');
  const score = loveScore(girl, boy);
  const mood = loveMood(score);
  qs('loveResult').className = `love-result love-${mood.cls}`;
  qs('loveResult').innerHTML = `<div class="emoji-shower">${esc(mood.emoji)}</div><p class="eyebrow">Aşk Dosyası Sonucu</p><div class="love-percent">%${score}</div><div class="love-bar"><span style="width:${score}%"></span></div><h3>${esc(mood.title)}</h3><p><b>${esc(girl)} + ${esc(boy)}</b></p><p>${esc(loveMessage(score, girl, boy))}</p><p class="muted">Slogan: Ya aşık ya suçlusun; logoya tıkla, kalp dosyan açılsın.</p>`;
}

qs('countrySelect').addEventListener('change', handleCountryChange);
qs('analyzeBtn').addEventListener('click', () => analyze(false));
qs('finalBtn').addEventListener('click', () => analyze(true));
qs('compareBtn').addEventListener('click', compare);
qs('logoLoveTrigger').addEventListener('click', openLovePanel);
qs('loveTeaserBtn').addEventListener('click', openLovePanel);
qs('closeLoveBtn').addEventListener('click', closeLovePanel);
qs('loveCalcBtn').addEventListener('click', calculateLove);
['girlName','boyName'].forEach(id => qs(id).addEventListener('keydown', e => { if (e.key === 'Enter') calculateLove(); }));
init();
