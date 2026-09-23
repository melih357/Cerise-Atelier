<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

session_name('CERISE_ADMIN_SESSION');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$action = $_GET['action'] ?? '';

try {
    if ($action === 'meta') {
        $countries = db()->query('SELECT id, name, code, official_name, legal_family, government_system, legal_system_note, requires_region FROM countries ORDER BY name')->fetchAll();
        $crimes = db()->query('SELECT id, name, slug, keywords FROM crime_types ORDER BY name')->fetchAll();
        $sources = db()->query('SELECT id, country_id, title, source_type, local_path, note FROM legal_sources ORDER BY id')->fetchAll();
        json_response([
            'ok' => true,
            'countries' => $countries,
            'crime_types' => $crimes,
            'sources' => $sources,
            'admin_logged_in' => true,
            'disclaimer' => env_value('LEGAL_DISCLAIMER')
        ]);
    }

    if ($action === 'admin_status') {
        json_response(['ok' => true, 'logged_in' => true, 'email' => null, 'message' => 'Giriş kaldırıldı.']);
    }

    if ($action === 'admin_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = request_json();
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $expectedEmail = env_value('ADMIN_EMAIL', 'admin@cerise.local');
        $expectedPassword = env_value('ADMIN_PASSWORD', 'Cerise123!');
        if (hash_equals($expectedEmail, $email) && hash_equals($expectedPassword, $password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_login_time'] = time();
            json_response(['ok' => true, 'message' => 'Giriş başarılı.']);
        }
        json_response(['ok' => false, 'message' => 'E-posta veya şifre hatalı.'], 401);
    }

    if ($action === 'admin_logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        json_response(['ok' => true, 'message' => 'Çıkış yapıldı.']);
    }

    if ($action === 'analyze' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = request_json();
        $countryId = (int)($input['country_id'] ?? 1);
        $region = trim((string)($input['region'] ?? ''));
        $eventText = trim((string)($input['event_text'] ?? ''));
        $answers = $input['answers'] ?? [];
        if ($eventText === '') json_response(['ok' => false, 'message' => 'Olay metni boş olamaz.'], 422);

        $countryStmt = db()->prepare('SELECT * FROM countries WHERE id = ?');
        $countryStmt->execute([$countryId]);
        $country = $countryStmt->fetch();
        if (!$country) json_response(['ok' => false, 'message' => 'Ülke bulunamadı.'], 404);

        $crime = detect_crime($eventText);
        $questions = missing_questions($eventText, $answers, $country, $crime);

        if (count($questions) > 0 && empty($input['force_result'])) {
            json_response([
                'ok' => true,
                'needs_more_info' => true,
                'detected_crime' => $crime,
                'questions' => $questions,
                'message' => 'Daha net sonuç için birkaç bilgi gerekli. Cevaplarsanız sonuç ekranı daha kesin ve anlaşılır olur.'
            ]);
        }

        $analysis = build_analysis($country, $crime, $eventText, $answers, $region);
        save_case($countryId, $region, $eventText, $crime['id'] ?? null, $analysis);
        json_response(['ok' => true, 'needs_more_info' => false, 'analysis' => $analysis]);
    }

    if ($action === 'compare' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = request_json();
        $crimeId = (int)($input['crime_type_id'] ?? 1);
        $limit = (int)($input['limit'] ?? 30);
        if ($limit < 1 || $limit > 50) $limit = 30;
        $stmt = db()->prepare('SELECT lc.*, c.name AS country_name, c.code AS country_code, c.legal_family, c.government_system, c.requires_region FROM legal_contents lc JOIN countries c ON c.id = lc.country_id WHERE lc.crime_type_id = ? ORDER BY c.name LIMIT ' . $limit);
        $stmt->execute([$crimeId]);
        json_response(['ok' => true, 'items' => $stmt->fetchAll(), 'note' => 'ABD, Brezilya, Rusya, İspanya ve İngiltere/Galler gibi yapılarda eyalet/bölge veya ülke içi hukuk alanı sonucu değiştirebilir.']);
    }

    if ($action === 'regions') {
        $countryId = (int)($_GET['country_id'] ?? 0);
        $stmt = db()->prepare('SELECT id, name, note FROM country_regions WHERE country_id = ? ORDER BY name');
        $stmt->execute([$countryId]);
        json_response(['ok' => true, 'regions' => $stmt->fetchAll()]);
    }

    if ($action === 'admin_list') {
        $stmt = db()->query('SELECT lc.*, c.name AS country_name, ct.name AS crime_name FROM legal_contents lc JOIN countries c ON c.id = lc.country_id JOIN crime_types ct ON ct.id = lc.crime_type_id ORDER BY c.name, ct.name LIMIT 400');
        json_response(['ok' => true, 'items' => $stmt->fetchAll()]);
    }

    if (($action === 'admin_save' || $action === 'admin_create') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = request_json();
        $stmt = db()->prepare('INSERT INTO legal_contents (country_id, crime_type_id, law_article, possible_penalty, process_note, rights_note, judge_simulation_note, source_note, is_seed_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                law_article = VALUES(law_article),
                possible_penalty = VALUES(possible_penalty),
                process_note = VALUES(process_note),
                rights_note = VALUES(rights_note),
                judge_simulation_note = VALUES(judge_simulation_note),
                source_note = VALUES(source_note),
                is_seed_data = 0');
        $stmt->execute([
            (int)($input['country_id'] ?? 1),
            (int)($input['crime_type_id'] ?? 1),
            trim((string)($input['law_article'] ?? '')),
            trim((string)($input['possible_penalty'] ?? '')),
            trim((string)($input['process_note'] ?? '')),
            trim((string)($input['rights_note'] ?? '')),
            trim((string)($input['judge_simulation_note'] ?? '')),
            trim((string)($input['source_note'] ?? 'Admin tarafından güncellendi.')),
        ]);
        json_response(['ok' => true, 'message' => 'Kanun verisi kaydedildi/güncellendi.']);
    }

    json_response(['ok' => false, 'message' => 'Geçersiz endpoint.'], 404);
} catch (Throwable $e) {
    $debug = env_value('APP_DEBUG', 'false') === 'true';
    json_response(['ok' => false, 'message' => 'Sunucu hatası.', 'detail' => $debug ? $e->getMessage() : null], 500);
}

function is_admin(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void {
    // Bu sürümde admin girişi kaldırıldı; veri paneli doğrudan çalışır.
    return;
}

function detect_crime(string $text): array {
    $textLower = mb_strtolower($text, 'UTF-8');
    $rows = db()->query('SELECT * FROM crime_types')->fetchAll();
    $best = null; $score = 0;
    foreach ($rows as $row) {
        $keywords = array_filter(array_map('trim', explode(',', mb_strtolower($row['keywords'] ?? '', 'UTF-8'))));
        $localScore = 0;
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($textLower, $keyword)) $localScore++;
        }
        if ($localScore > $score) { $score = $localScore; $best = $row; }
    }
    if (!$best) {
        return ['id' => null, 'name' => 'Belirsiz / genel olay', 'slug' => 'general', 'confidence' => 'düşük', 'keyword_score' => 0];
    }
    $best['confidence'] = $score >= 2 ? 'yüksek' : 'orta';
    $best['keyword_score'] = $score;
    return $best;
}

function missing_questions(string $eventText, array $answers, array $country, array $crime): array {
    $textLower = mb_strtolower($eventText, 'UTF-8');
    $questions = [];
    $base = [
        'value' => 'Olaydaki malın, zararın veya menfaatin yaklaşık değeri nedir? Para birimini de seçin.',
        'force' => 'Zor kullanma, tehdit, cebir veya mağdura fiziksel müdahale var mıydı?',
        'first_offense' => 'Bu ilk suç/ilk olay mı? Daha önce benzer bir kayıt veya dava var mı?',
        'complaint' => 'Mağdur şikâyetçi mi, zarar giderildi mi veya uzlaşma ihtimali var mı?',
        'age' => 'Olay tarihinde yaşınız 18’den küçük müydü?',
        'evidence' => 'Kamera, mesaj, tanık, belge, banka hareketi gibi delil var mı?',
        'place_time' => 'Olay nerede ve ne zaman gerçekleşti? Kamuya açık alan, işyeri, ev veya internet ortamı mı?'
    ];

    foreach ($base as $key => $label) {
        if (!isset($answers[$key]) || trim((string)$answers[$key]) === '') $questions[] = ['key' => $key, 'label' => $label];
    }
    if ((int)$country['requires_region'] === 1 && (!isset($answers['region']) || trim((string)$answers['region']) === '')) {
        $questions[] = ['key' => 'region', 'label' => 'Bu ülkede eyalet/bölge/ülke içi hukuk alanı hangisi?'];
    }

    // Suç tipine göre sonuca doğrudan etki eden ek sorular.
    $slug = $crime['slug'] ?? '';
    $extra = [];
    if ($slug === 'hirsizlik') {
        $extra['ownership'] = 'Eşya size mi aitti, emanet miydi, yoksa başkasına mı aitti?';
        $extra['return'] = 'Eşya iade edildi mi veya zarar karşılandı mı?';
    } elseif ($slug === 'dolandiricilik') {
        $extra['deception'] = 'Karşı tarafı yanıltan beyan, sahte ilan, sahte belge veya aldatıcı hareket var mıydı?';
        $extra['benefit'] = 'Bu olaydan para, ürün, hesap erişimi veya başka bir menfaat elde edildi mi?';
    } elseif ($slug === 'hakaret' || $slug === 'tehdit') {
        $extra['message_record'] = 'Sözler mesaj, ses kaydı, sosyal medya paylaşımı veya tanıkla ispatlanabiliyor mu?';
        $extra['publicity'] = 'Olay herkesin görebileceği şekilde mi gerçekleşti?';
    } elseif ($slug === 'kasten-yaralama') {
        $extra['injury_report'] = 'Doktor raporu, darp raporu veya yaralanmanın derecesini gösteren belge var mı?';
    }
    foreach ($extra as $key => $label) {
        if (!isset($answers[$key]) || trim((string)$answers[$key]) === '') $questions[] = ['key' => $key, 'label' => $label];
    }

    return array_slice($questions, 0, 9);
}

function build_analysis(array $country, array $crime, string $eventText, array $answers, string $region): array {
    $content = null;
    if (!empty($crime['id'])) {
        $stmt = db()->prepare('SELECT * FROM legal_contents WHERE country_id = ? AND crime_type_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([(int)$country['id'], (int)$crime['id']]);
        $content = $stmt->fetch();
    }

    $risk = risk_level($answers, $crime['confidence'] ?? 'düşük');
    $certainty = certainty_score($answers, $crime);
    $factors = decisive_factors($answers, $crime, $country, $region);
    $likelyOutcome = likely_outcome($risk, $certainty, $crime, $answers, $country);
    $nextSteps = next_steps($risk, $crime, $answers);

    return [
        'title' => 'Hukuki Bilgilendirme Analizi',
        'country' => $country['name'],
        'region' => $region ?: ($answers['region'] ?? ''),
        'region_warning' => ((int)$country['requires_region'] === 1) ? 'Bu ülkede eyalet/bölge kuralları sonucu değiştirebilir; bu yüzden nihai değerlendirme seçilen bölgeye göre daraltılmalıdır.' : null,
        'crime_probability' => $crime['confidence'] ?? 'düşük',
        'certainty_score' => $certainty,
        'certainty_label' => certainty_label($certainty),
        'risk_level' => $risk,
        'possible_crime_type' => $crime['name'] ?? 'Belirsiz / genel olay',
        'case_summary' => compact_case_summary($country, $crime, $answers, $risk, $certainty),
        'likely_outcome' => $likelyOutcome,
        'law_articles' => $content['law_article'] ?? 'Veritabanında bu suç tipi için özel madde yok. Admin panelden resmi madde eklenebilir.',
        'source_note' => $content['source_note'] ?? null,
        'possible_penalty_range' => $content['possible_penalty'] ?? 'Kesin ceza söylenemez; ülke, delil, zarar, kast ve yargılama sonucuna göre değişir.',
        'process' => $content['process_note'] ?? 'Genel süreç: başvuru/şikâyet → kolluk veya savcılık incelemesi → ifade → delil değerlendirmesi → dava açılması veya takipsizlik/uzlaşma ihtimali.',
        'rights' => $content['rights_note'] ?? 'Susma hakkı, avukata danışma, doğru beyanda bulunma, delilleri saklama ve resmi evrakları dikkatle inceleme önemlidir.',
        'decisive_factors' => $factors,
        'next_steps' => $nextSteps,
        'evidence_checklist' => evidence_checklist($crime),
        'judicial_simulation' => [
            'Suçun unsurları oluşmuş mu?' => element_assessment($crime, $answers),
            'Kast var mı?' => intent_assessment($answers),
            'Zarar var mı?' => $answers['value'] ?? 'Zarar/değer bilgisi eksik veya belirsiz.',
            'Delil yeterli mi?' => evidence_assessment($answers),
            'Hafifletici/ağırlaştırıcı neden var mı?' => aggravation_assessment($answers),
        ],
        'law_style_note' => $country['legal_system_note'],
        'legal_family' => $country['legal_family'] ?? null,
        'government_system' => $country['government_system'] ?? null,
        'lawyer_need' => lawyer_need($risk, $certainty),
        'disclaimer' => env_value('LEGAL_DISCLAIMER'),
    ];
}

function answer_has(array $answers, string $key, array $needles): bool {
    $value = mb_strtolower((string)($answers[$key] ?? ''), 'UTF-8');
    foreach ($needles as $n) {
        if ($n !== '' && str_contains($value, mb_strtolower($n, 'UTF-8'))) return true;
    }
    return false;
}

function certainty_score(array $answers, array $crime): int {
    $score = (($crime['confidence'] ?? 'düşük') === 'yüksek') ? 35 : ((($crime['confidence'] ?? '') === 'orta') ? 24 : 10);
    foreach (['value','force','first_offense','complaint','age','evidence','place_time'] as $key) {
        if (trim((string)($answers[$key] ?? '')) !== '') $score += 7;
    }
    if (trim((string)($answers['region'] ?? '')) !== '') $score += 5;
    return max(10, min(95, $score));
}

function certainty_label(int $score): string {
    if ($score >= 80) return 'Yüksek netlik';
    if ($score >= 60) return 'Orta-yüksek netlik';
    if ($score >= 40) return 'Orta netlik';
    return 'Düşük netlik';
}

function risk_level(array $answers, string $confidence): string {
    $risk = 1;
    if ($confidence === 'yüksek') $risk += 1;
    if (answer_has($answers, 'force', ['evet','var','tehdit','cebir','zor'])) $risk += 2;
    if (answer_has($answers, 'age', ['evet','18den küçük','18’den küçük','çocuk'])) $risk -= 1;
    if (answer_has($answers, 'complaint', ['şikayet','şikâyet','dava','polis','savcı'])) $risk += 1;
    if (answer_has($answers, 'evidence', ['kamera','tanık','mesaj','belge','dekont','kayıt'])) $risk += 1;
    return $risk >= 4 ? 'yüksek' : ($risk >= 2 ? 'orta' : 'düşük');
}

function lawyer_need(string $risk, int $certainty): string {
    if ($risk === 'yüksek') return 'Acil / güçlü şekilde önerilir';
    if ($risk === 'orta') return 'Önerilir';
    if ($certainty < 50) return 'Bilgi eksik olduğu için önerilir';
    return 'Duruma göre önerilir';
}

function decisive_factors(array $answers, array $crime, array $country, string $region): array {
    $items = [];
    $items[] = ['label' => 'Suç tipi eşleşmesi', 'value' => ($crime['name'] ?? 'Belirsiz') . ' / güven: ' . ($crime['confidence'] ?? 'düşük')];
    $items[] = ['label' => 'Zarar/değer', 'value' => $answers['value'] ?? 'Eksik bilgi'];
    $items[] = ['label' => 'Zor kullanma/tehdit', 'value' => $answers['force'] ?? 'Eksik bilgi'];
    $items[] = ['label' => 'Şikâyet/zararın giderilmesi', 'value' => $answers['complaint'] ?? 'Eksik bilgi'];
    $items[] = ['label' => 'Delil durumu', 'value' => $answers['evidence'] ?? 'Eksik bilgi'];
    if ((int)$country['requires_region'] === 1) {
        $items[] = ['label' => 'Eyalet/bölge', 'value' => $region ?: ($answers['region'] ?? 'Eksik bilgi')];
    }
    return $items;
}

function compact_case_summary(array $country, array $crime, array $answers, string $risk, int $certainty): string {
    $crimeName = $crime['name'] ?? 'belirsiz bir olay';
    $prob = $crime['confidence'] ?? 'düşük';
    return $country['name'] . ' için olay, mevcut anlatıma göre ' . $crimeName . ' başlığına ' . $prob . ' olasılıkla yaklaşıyor. Risk seviyesi ' . $risk . ', netlik puanı %' . $certainty . '. Netliği en çok değiştiren noktalar: zarar/değer, zor kullanma, delil ve şikâyet durumudur.';
}

function likely_outcome(string $risk, int $certainty, array $crime, array $answers, array $country): string {
    if ($certainty < 45) return 'Bilgi eksikliği yüksek. Bu aşamada kesin gibi sunmak doğru olmaz; önce değer, delil, şikâyet ve zor kullanma bilgileri tamamlanmalı.';
    if ($risk === 'yüksek') return 'Anlatım doğruysa dosyanın kolluk/savcılık aşamasına taşınması ve ifade-delil değerlendirmesiyle ciddi şekilde incelenmesi beklenir. Avukat desteği güçlü şekilde önerilir.';
    if ($risk === 'orta') return 'Olayın niteliğine göre şikâyet, uzlaşma, ifade ve savcılık değerlendirmesi gündeme gelebilir. Delil ve zarar bilgisi sonucu belirgin biçimde değiştirir.';
    return 'Mevcut bilgilerle risk düşük görünür; ancak suçun unsurları, delil ve şikâyet durumu netleşmeden kesin sonuç çıkarılamaz.';
}

function next_steps(string $risk, array $crime, array $answers): array {
    $steps = [
        'Olayla ilgili mesaj, dekont, ekran görüntüsü, kamera veya tanık bilgilerini silmeden saklayın.',
        'Resmî bir ifade vermeden önce metni sakin şekilde kronolojik olarak hazırlayın.',
        'Yanlış beyanda bulunmayın; emin olmadığınız kısmı kesinmiş gibi anlatmayın.'
    ];
    if ($risk !== 'düşük') $steps[] = 'Avukata danışmadan detaylı savunma/itiraf metni paylaşmayın.';
    if (($crime['slug'] ?? '') === 'hirsizlik') $steps[] = 'Eşya iade edildiyse veya zarar giderildiyse bunu belgeleyin.';
    return $steps;
}

function evidence_checklist(array $crime): array {
    $base = ['Olay tarihi ve yeri', 'Tarafların kimliği veya iletişim bilgileri', 'Mesaj/kayıt/ekran görüntüsü', 'Tanık bilgisi', 'Zarar veya değer belgesi'];
    if (($crime['slug'] ?? '') === 'kasten-yaralama') $base[] = 'Doktor/darp raporu';
    if (($crime['slug'] ?? '') === 'dolandiricilik') $base[] = 'Dekont, ilan, sözleşme veya ödeme kanıtı';
    return $base;
}

function element_assessment(array $crime, array $answers): string {
    $name = $crime['name'] ?? 'belirsiz olay';
    if (($crime['confidence'] ?? 'düşük') === 'düşük') return 'Olay metni ' . $name . ' için yeterince açık değil. Unsur kontrolü için daha somut anlatım gerekir.';
    return $name . ' açısından temel unsur kontrolü yapılabilir; fakat nihai değerlendirme delil, kast, zarar ve ülke/bölge kuralına bağlıdır.';
}

function intent_assessment(array $answers): string {
    if (answer_has($answers, 'first_offense', ['yanlışlıkla','bilmeden','kazara'])) return 'Anlatımda kastı zayıflatabilecek ifadeler var; bu durum sonucu etkileyebilir.';
    return 'Kast, olayın öncesi/sonrası davranışlar, mesajlar ve delillerle değerlendirilir.';
}

function evidence_assessment(array $answers): string {
    if (answer_has($answers, 'evidence', ['kamera','tanık','mesaj','belge','dekont','kayıt'])) return 'Somut delil olduğu belirtilmiş; bu durum soruşturma/kovuşturma ihtimalini güçlendirebilir.';
    if (trim((string)($answers['evidence'] ?? '')) === '') return 'Delil bilgisi verilmediği için ispat gücü belirsiz.';
    return (string)$answers['evidence'];
}

function aggravation_assessment(array $answers): string {
    $notes = [];
    if (answer_has($answers, 'force', ['evet','var','tehdit','cebir','zor'])) $notes[] = 'Zor kullanma/tehdit varsa risk artar.';
    if (answer_has($answers, 'age', ['evet','18den küçük','18’den küçük','çocuk'])) $notes[] = '18 yaş altı olma özel usul ve sonuçlara yol açabilir.';
    if (answer_has($answers, 'complaint', ['zarar giderildi','iade','uzlaş'])) $notes[] = 'Zararın giderilmesi veya uzlaşma ihtimali sonucu hafifletebilir.';
    return $notes ? implode(' ', $notes) : 'Zor kullanma, yaş, ilk suç, pişmanlık, şikâyet ve zararın giderilmesi sonucu etkileyebilir.';
}

function save_case(int $countryId, string $region, string $eventText, ?int $crimeTypeId, array $analysis): void {
    $stmt = db()->prepare('INSERT INTO case_analyses (country_id, region, event_text, crime_type_id, result_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$countryId, $region, $eventText, $crimeTypeId, json_encode($analysis, JSON_UNESCAPED_UNICODE)]);
}
