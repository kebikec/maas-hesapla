<?php
// Dosya yolları
$maas_dosyasi = __DIR__ . '/maas.json';
$doviz_kur_dosyasi = __DIR__ . '/doviz-kuru.json';
$enf_orani_dosyasi = __DIR__ . '/tuik-aylik.json';
$asgari_ucret_dosyasi = __DIR__ . '/asgari_ucret.json';
$log_dosyasi = __DIR__ . '/hata-kaydi.txt';

// Yerel ayarlar (API anahtarı vb.) - config.php git'e girmez
$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$evds_api_key = $config['evds_api_key'] ?? '';
$evds_series  = $config['evds_series'] ?? 'TP.GENENDEKS.T1';

// Maaş verilerini yükle
$maaslar = [];
if (file_exists($maas_dosyasi)) {
   $maaslar = json_decode(file_get_contents($maas_dosyasi), true) ?: [];
} else { die('Hata: maas.json dosyası bulunamadı.'); }

// Döviz kurlarını yükle
$doviz_kurlari = [];
if (file_exists($doviz_kur_dosyasi)) {
    $doviz_kurlari = json_decode(file_get_contents($doviz_kur_dosyasi), true) ?: [];
}

// Enflasyon verilerini yükle
$enf_orani = [];
if (file_exists($enf_orani_dosyasi)) {
    $enf_orani = json_decode(file_get_contents($enf_orani_dosyasi), true) ?: [];
} else { die('Hata: tuik-aylik.json dosyası bulunamadı.'); }

// Asgari ücret verilerini yükle
$asgari_ucretler = [];
if (file_exists($asgari_ucret_dosyasi)) {
    $asgari_ucretler = json_decode(file_get_contents($asgari_ucret_dosyasi), true) ?: [];
}

