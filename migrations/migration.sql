-- Cerise Atelier migration v3
-- Admin panel artık public değildir: backend/api.php içindeki admin_save, admin_list ve admin_create endpointleri oturum ister.
-- Admin bilgileri .env içinden yönetilir:
-- ADMIN_EMAIL=admin@cerise.local
-- ADMIN_PASSWORD=Cerise123!

-- Önceki sürüme kurulu veritabanında tablo yapısı aynı kalır.
-- Bu dosya güvenli admin panel kod güncellemesini belgelemek için bırakılmıştır.
