# Cerise Atelier Hukuk Asistanı

Bu proje, WAMP Server üzerinde çalışabilecek şekilde hazırlanmış veritabanlı PHP + MySQL + HTML/CSS/JS tabanlı bir MVP'dir.

## Bu Sürümde Güncellenenler

- Admin giriş ekranı kaldırıldı; veri paneli artık doğrudan açılıyor.
- Soru Sihirbazı daha kullanıcı dostu hale getirildi; çoğu cevap artık seçmeli.
- “Olaydaki malın, zararın veya menfaatin yaklaşık değeri” sorusuna para birimi seçimi eklendi.
- Sonuç ekranı netlik puanı, risk seviyesi, kritik faktörler, beklenen yön, delil listesi ve yapılacak adımları göstermeye devam ediyor.
- Veri panelinden ülke + suç tipine göre kanun maddesi, yaptırım, süreç, haklar ve kaynak notu güncellenebilir.
- Gizli Cerise “Ya Aşık Ya Suçlusun” bölümü daha eğlenceli hale getirildi; %100, %90+, %75+, %50+, %30+ ve düşük sonuçlarda farklı emoji/mesajlar gösteriliyor.

## Panel ve Kullanıcı Ekranı

Veri paneli adresi:

```txt
http://localhost/cerise_legal_assistant/frontend/admin.html
```

Kullanıcı ekranı:

```txt
http://localhost/cerise_legal_assistant/frontend/index.html
```

## Proje Mantığı

Sistem kullanıcıya mahkeme kararı gibi kesin hüküm vermez. Olayı ülkeye göre analiz eder, önce eksik bilgileri sorar ve sonra şu başlıklarda bilgilendirme üretir:

- Olası suç tipi
- Olası ceza / yaptırım aralığı
- Sürecin nasıl ilerleyebileceği
- Dikkat edilmesi gereken haklar
- Yargı değerlendirme simülasyonu
- Netlik puanı
- Risk seviyesi
- Sonucu değiştiren kritik bilgiler
- Delil kontrol listesi
- Ülke karşılaştırması

> Bu proje hukuki tavsiye vermez. Sadece genel bilgilendirme ve eğitim amaçlıdır.

## Klasör Yapısı

```txt
cerise_legal_assistant/
├─ backend/
│  ├─ api.php
│  ├─ config.php
│  └─ db.php
├─ frontend/
│  ├─ index.html
│  ├─ admin.html
│  ├─ app.js
│  ├─ admin.js
│  └─ style.css
├─ assets/
│  └─ cerise-logo-reference.png
├─ uploads/
│  └─ legal_sources/
├─ database/
│  └─ database.sql
├─ migrations/
│  └─ migration.sql
├─ .env
└─ README.md
```

## WAMP Kurulum

1. ZIP dosyasını açın.
2. `cerise_legal_assistant` klasörünü şu dizine taşıyın:

```txt
C:\wamp64\www\cerise_legal_assistant
```

3. WAMP Server'ı başlatın.
4. phpMyAdmin'e girin:

```txt
http://localhost/phpmyadmin
```

5. `database/database.sql` dosyasını içe aktarın.

Bu dosya veritabanını oluşturur; 25 ülkeyi, 15 temel suç tipini, ülke/bölge uyarılarını, kaynak tablolarını ve tüm ülke-suç kombinasyonları için hazır başlangıç kanun içeriklerini ekler.

6. Gerekirse `.env` dosyasını düzenleyin:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=cerise_legal_assistant
DB_USER=root
DB_PASS=
```

WAMP varsayılan kurulumda genelde kullanıcı `root`, şifre ise boştur.

## Mevcut Özellikler

- 25 ülke hazır veri seti
- ABD, İngiltere/Galler, Brezilya, İspanya ve Rusya için eyalet/bölge uyarısı
- Olay metni yazma
- Anahtar kelimeye göre suç tipi tahmini
- Eksik bilgi soruları
- Daha gelişmiş sonuç ekranı
- Netlik puanı ve risk seviyesi
- Sonucu değiştiren kritik faktörler
- Delil kontrol listesi
- Tüm ülkeler için ülke karşılaştırması
- Girişsiz veri paneli
- Veri panelinden kanun verisi güncelleme
- Kaynak PDF klasörü: `uploads/legal_sources/`
- Logo asset klasörü
- Uploads klasörü
- `.env` ile veritabanı ayarı

## Hazır Gelen Ülkeler

Türkiye, Amerika Birleşik Devletleri, Almanya, Fransa, İngiltere ve Galler, Avusturya, Belçika, Brezilya, Bulgaristan, Çek Cumhuriyeti, Danimarka, Estonya, Finlandiya, Güney Afrika, Güney Kore, Hollanda, İrlanda, İspanya, İtalya, Macaristan, Polonya, Portekiz, Romanya, Rusya ve Yunanistan.

## Hazır Gelen Suç Başlıkları

Hırsızlık, dolandırıcılık, hakaret, tehdit, kasten yaralama, trafik kazası, uyuşturucu bulundurma, siber zorbalık, özel hayatın gizliliği, mala zarar verme, telif hakkı ihlali, şantaj, kimlik bilgisi kullanma, ruhsatsız paylaşım/ifşa ve konut dokunulmazlığı.

## Güvenlik Notu

Bu sürümde admin giriş ekranı kaldırılmıştır. Gerçek yayında veri paneli internete açılacaksa ayrıca şunlar eklenmelidir:

- Şifrelerin hashlenerek veritabanında tutulması
- CSRF koruması
- Rate limit
- HTTPS
- Hata mesajlarını production ortamında gizleme
- Admin işlem logları
- Kullanıcı olay kayıtları için KVKK/GDPR uyumlu saklama politikası


## Mini Özellik: Logo Aşk Bölümü
Ana sayfadaki Cerise logosuna veya yanında görünen “Logoya tıkla” çağrısına basınca gizli bir eğlence bölümü açılır.
Bu bölümde kız ismi ve erkek ismi girilerek yüzde formatında eğlence amaçlı aşk oranı gösterilir. Orana göre farklı mesaj ve emoji efektleri çıkar.
Slogan: “Ya aşık ya suçlusun; logoya tıkla, kalp dosyan açılsın.”
Bu özellik hukuki analizden tamamen ayrıdır ve kullanıcı dostu küçük bir marka etkileşimi olarak eklenmiştir.
