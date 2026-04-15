# MAAŞ Hesapla Uygulaması Amacı

## Genel Amaç
"MAAŞ Hesapla" uygulaması, çalışanların maaşlarının zaman içinde enflasyon ve kur değişiklikleri nedeniyle nasıl etkilendiğini görsel ve sayısal olarak göstermek amacıyla yazılmıştır. Uygulama, Türk Lirası cinsinden verilen maaşların gerçek satın alma gücünü zaman içinde nasıl değiştiğini analiz etmeyi sağlar.

## Ana Özellikler
- Maaş verilerinin yıllara ve aylara göre girilmesi
- TCMB'den otomatik döviz kuru verisi çekme
- TÜİK enflasyon oranları ile maaşların reel değerlerinin hesaplanması
- Asgari ücrete kıyasla maaş performansının analizi
- Maaşların dolar cinsinden hesaplanması ve görselleştirilmesi
- Enflasyon düzeltilmiş maaşların analizi
- Aylık ve kümülatif ortalama maaş değişimi grafikleri

## Kullanım Amacı
Uygulama, çalışanların "Patron sizi ne kadar kazıklıyor" sorusunun cevabını bulmalarına yardımcı olmayı amaçlamaktadır. Gerçekleşen enflasyon oranları, döviz kurundaki değişimler ve asgari ücret artışları göz önüne alınarak, nominal olarak artış gösteren maaşların reel satın alma gücünün nasıl değiştiğini gösterir.

## Girdi Dosyaları
- `maas.json`: Kullanıcıya ait maaş verileri
- `tuik-aylik.json`: Aylık enflasyon oranları (TÜİK verileri)
- `doviz-kuru.json`: Günlük USD/TRY döviz kuru verileri
- `asgari_ucret.json`: Dönemsel net asgari ücret verileri

## Çıktılar
- Yıllara göre maaşların dolar cinsinden değerleri
- Enflasyonla düzeltilmiş TL maaş değerleri
- Maaşın asgari ücrete oranı (kaç katı olduğu) grafiği
- Zaman içinde maaş değişimi grafikleri
- Kümülatif ortalama maaş hesaplamaları
