<?php
// Dosya yolları
$maas_dosyasi = __DIR__ . '/maas.json';
$doviz_kur_dosyasi = __DIR__ . '/doviz-kuru.json';
$enf_orani_dosyasi = __DIR__ . '/tuik-aylik.json';
$log_dosyasi = __DIR__ . '/hata-kaydi.txt';

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

// Belirli tarih için maaş getir
function maasiTarihIleGetir($tarih, $maaslar) {
    $hedef_tarih = strtotime($tarih);
    $uygun_maas = null;
    $en_yakin_tarih = 0;
    foreach ($maaslar as $anahtar => $maas) {        
        if (strpos($anahtar, '_comment') === 0) {
            continue;
        }        
        $giris_tarihi = null;        
        // sadece yıl ise ("2023")
        if (preg_match('/^\d{4}$/', $anahtar)) {
            $giris_tarihi = strtotime($anahtar . '-01-01');
        }
        // yıl-ay düzeni ("2024-08")
        elseif (preg_match('/^\d{4}-\d{2}$/', $anahtar)) {
            $giris_tarihi = strtotime($anahtar . '-01');
        }        
        // Bu giriş hedef tarih için geçerliyse ve en yakın tarihse
        if ($giris_tarihi && $giris_tarihi <= $hedef_tarih && $giris_tarihi > $en_yakin_tarih) {
            $en_yakin_tarih = $giris_tarihi;
            $uygun_maas = $maas;
        }
    }    
    return $uygun_maas;
}

// Maaş verilerindeki yılları getir
function yillariMaaslardanGetir($maaslar) {
    $yillar = [];
    foreach ($maaslar as $anahtar => $maas) {
        if (strpos($anahtar, '_comment') === 0) {
            continue;
        }        
        if (preg_match('/^(\d{4})/', $anahtar, $eslesenler)) {
            $yillar[] = intval($eslesenler[1]);
        }
    }
    return array_unique($yillar);
}

// İş günü kontrolü
function isGunuMu($tarih) {
    $haftanin_gunu = date('N', strtotime($tarih));
    return $haftanin_gunu >= 1 && $haftanin_gunu <= 5;
}

// Ayın ilk iş gününü getir
function ilkIsgununuGetir($yil, $ay) {
    $tarih = sprintf('%d-%02d-01', $yil, $ay);
    while (!isGunuMu($tarih)) {
        $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
    }
    return $tarih;
}

// Döviz kuru getir (tek tarih)
function dovizKuruGetir($tarih, &$doviz_kurlari, $doviz_kur_dosyasi, $log_dosyasi) {
    $tarih_anahtari = $tarih;
    if (isset($doviz_kurlari[$tarih_anahtari]) && $doviz_kurlari[$tarih_anahtari] !== 'Veri yok') {
        return $doviz_kurlari[$tarih_anahtari];
    }    
    $baglam = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);    
    $bugun = date('Y-m-d');
    if ($tarih === $bugun) {
        $denenecek_adresler = [
          "https://www.tcmb.gov.tr/kurlar/today.xml",
          "https://www.tcmb.gov.tr/kurlar/" . date('Ym') . "/" . date('dmY') . ".xml"
        ];
        foreach ($denenecek_adresler as $adres) {
            $xml_icerik = @file_get_contents($adres, false, $baglam);
            if ($xml_icerik !== false) {
                $xml = @simplexml_load_string($xml_icerik);
                if ($xml !== false) {
                    foreach ($xml->Currency as $kur) {
                        if ($kur['Kod'] == 'USD') {
                            $kur_degeri = floatval($kur->ForexBuying);
                            if ($kur_degeri > 0) {
                                $doviz_kurlari[$tarih] = $kur_degeri;
                                file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT));
                                return $kur_degeri;
                            }
                        }
                    }
                }
            }
        }
    }
    // Tarihi geçmiş için
    $yil_ay = date('Ym', strtotime($tarih));
    $gun_ay_yil = date('dmY', strtotime($tarih));
    $tam_tarih = date('Ymd', strtotime($tarih));
    $denenecek_adresler = [
       "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$gun_ay_yil}.xml",
       "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$tam_tarih}.xml"
    ];
    foreach ($denenecek_adresler as $adres) {
        $xml_icerik = @file_get_contents($adres, false, $baglam);
        if ($xml_icerik !== false) {
            $xml = @simplexml_load_string($xml_icerik);
            if ($xml !== false) {
                foreach ($xml->Currency as $kur) {
                    if ($kur['Kod'] == 'USD') {
                        $kur_degeri = floatval($kur->ForexBuying);
                        if ($kur_degeri > 0) {
                           $doviz_kurlari[$tarih] = $kur_degeri;
                           file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT));
                           return $kur_degeri;
                        }
                    }
                }
            }
        }
    }
    // Eğer iş günü değilse
    $deneme_sayisi = 0;
    $onceki_tarih = $tarih;
    $sonraki_tarih = $tarih;
    while ($deneme_sayisi < 5) {
        $onceki_tarih = date('Y-m-d', strtotime($onceki_tarih . ' -1 day'));
        $sonraki_tarih = date('Y-m-d', strtotime($sonraki_tarih . ' +1 day'));
        // Önceki gün denemesi
        if (isGunuMu($onceki_tarih)) {
            $kur_degeri = tarihIcinKurDegeriDene($onceki_tarih, $baglam);
            if ($kur_degeri !== null) {
                $doviz_kurlari[$tarih] = $kur_degeri;
                $doviz_kurlari[$onceki_tarih] = $kur_degeri;
                file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT));
                return $kur_degeri;
            }
        }
        // Sonraki gün
        if (isGunuMu($sonraki_tarih) && strtotime($sonraki_tarih) <= time()) {
            $kur_degeri = tarihIcinKurDegeriDene($sonraki_tarih, $baglam);
            if ($kur_degeri !== null) {
               $doviz_kurlari[$tarih] = $kur_degeri;
               $doviz_kurlari[$sonraki_tarih] = $kur_degeri;
               file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT));
               return $kur_degeri;
            }
        }        
        $deneme_sayisi++;
    }
    // Hata kaydı bas
    file_put_contents($log_dosyasi, "Veri alınamadı: $tarih - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $doviz_kurlari[$tarih] = 'Veri yok';
    file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT));
    return null;
}
// Yardımcı döviz kuru getirme fonksiyonu
function tarihIcinKurDegeriDene($tarih, $baglam) {
    $yil_ay = date('Ym', strtotime($tarih));
    $gun_ay_yil = date('dmY', strtotime($tarih));
    $tam_tarih = date('Ymd', strtotime($tarih));
    $denenecek_adresler = [
        "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$gun_ay_yil}.xml",
        "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$tam_tarih}.xml"
    ];
    foreach ($denenecek_adresler as $adres) {
        $xml_icerik = @file_get_contents($adres, false, $baglam);
        if ($xml_icerik !== false) {
            $xml = @simplexml_load_string($xml_icerik);
            if ($xml !== false) {
                foreach ($xml->Currency as $kur) {
                    if ($kur['Kod'] == 'USD') {
                        $kur_degeri = floatval($kur->ForexBuying);
                        if ($kur_degeri > 0) {
                            return $kur_degeri;
                        }
                    }
                }
            }
        }
    }
    return null;
}

// Toplu döviz kuru getir (çoklu tarih)
function topluDovizKuruGetir($tarihler, &$doviz_kurlari, $doviz_kur_dosyasi, $log_dosyasi) {
    $coklu_baglanti = curl_multi_init();
    $curl_handlelari = [];
    $tarih_handle_eslesmesi = [];
    foreach ($tarihler as $tarih) {
        if (!isset($doviz_kurlari[$tarih]) || $doviz_kurlari[$tarih] === 'Veri yok') {
            $bugun = date('Y-m-d');
            if ($tarih === $bugun) {
                $adres = "https://www.tcmb.gov.tr/kurlar/today.xml";
            } else {
                $yil_ay = date('Ym', strtotime($tarih));
                $gun_ay_yil = date('dmY', strtotime($tarih));
                $adres = "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$gun_ay_yil}.xml";
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $adres);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_multi_add_handle($coklu_baglanti, $ch);
            $curl_handlelari[] = $ch;
            $tarih_handle_eslesmesi[$tarih] = $ch;
        }
    }
    if (empty($curl_handlelari)) {
        curl_multi_close($coklu_baglanti);
        return;
    }
    $calisiyor = null;
    do {
        $durum = curl_multi_exec($coklu_baglanti, $calisiyor);
        if ($calisiyor) {
            curl_multi_select($coklu_baglanti);
        }
    } while ($calisiyor > 0 && $durum == CURLM_OK);
    foreach ($tarih_handle_eslesmesi as $tarih => $ch) {
        $xml_verisi = curl_multi_getcontent($ch);
        $kur_degeri = null;
        if ($xml_verisi !== false && !empty($xml_verisi)) {
            $xml = @simplexml_load_string($xml_verisi);
            if ($xml !== false) {
                foreach ($xml->Currency as $kur) {
                    if ($kur['Kod'] == 'USD') {
                        $kur_degeri = floatval($kur->ForexBuying);
                        if ($kur_degeri > 0) {
                            $doviz_kurlari[$tarih] = $kur_degeri;
                            break;
                        }
                    }
                }
            }
        }        
        if ($kur_degeri === null || $kur_degeri == 0) {
            // Fallback to individual fetch
            $kur_degeri = dovizKuruGetir($tarih, $doviz_kurlari, $doviz_kur_dosyasi, $log_dosyasi);
        }        
        curl_multi_remove_handle($coklu_baglanti, $ch);
        curl_close($ch);
    }    
    curl_multi_close($coklu_baglanti);
    file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT));
}

// Yıllık verileri hesapla
function yillikVerileriHesapla($maaslar, &$doviz_kurlari, $enf_oranlari, $doviz_kur_dosyasi, $log_dosyasi) {
    $yillar = yillariMaaslardanGetir($maaslar);
    $baslangic_yili = min($yillar);
    $su_anki_yil = date('Y');
    $bitis_yili = max($su_anki_yil, max($yillar));
    $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $sonuclar = [];
    $getirilecek_tarihler = [];
    // Pre-fetch için tarihleri hazırla
    for ($yil = $baslangic_yili; $yil <= $bitis_yili; $yil++) {
        $ay_limiti = ($yil == $su_anki_yil) ? date('n') : 12;
        for ($ay = 1; $ay <= $ay_limiti; $ay++) {
            $tarih = ilkIsgununuGetir($yil, $ay);
            if (!isset($doviz_kurlari[$tarih]) || $doviz_kurlari[$tarih] === 'Veri yok') {
                $getirilecek_tarihler[] = $tarih;
            }
        }
    }
    // Toplu döviz kuru getir
    if (!empty($getirilecek_tarihler)) {
        topluDovizKuruGetir($getirilecek_tarihler, $doviz_kurlari, $doviz_kur_dosyasi, $log_dosyasi);
    }
    // Hesapla
    for ($yil = $baslangic_yili; $yil <= $bitis_yili; $yil++) {
        $aylik_usd = [];
        $ay_limiti = ($yil == $su_anki_yil) ? date('n') : 12;
        for ($ay = 1; $ay <= $ay_limiti; $ay++) {
            $tarih = ilkIsgununuGetir($yil, $ay);
            $maas = maasiTarihIleGetir($tarih, $maaslar);
            if ($maas !== null) {
                $kur = $doviz_kurlari[$tarih] ?? 'Veri yok';
                if ($kur !== 'Veri yok' && $kur > 0) {
                    $usd = $maas / $kur;
                    $aylik_usd[$aylar[$ay - 1]] = number_format($usd, 2);
                } else {
                    $aylik_usd[$aylar[$ay - 1]] = 'Veri yok';
                }
            } else {
                $aylik_usd[$aylar[$ay - 1]] = 'Maaş tanımı yok';
            }
        }
        if (!empty($aylik_usd)) {
            $gecerli_usd = array_filter($aylik_usd, function($v) { 
                return $v !== 'Veri yok' && $v !== 'Maaş tanımı yok'; 
            });
            $sayisal_degerler = array_map(function($v) { 
                return floatval(str_replace(',', '', $v)); 
            }, $gecerli_usd);
            $sonuclar[$yil] = [
                'average' => !empty($sayisal_degerler) ? number_format(array_sum($sayisal_degerler) / count($sayisal_degerler), 2) : 'Veri yok',
                'monthly' => $aylik_usd
            ];
        }
    }
    return $sonuclar;
}

// Grafik verilerini hazırla
function grafikVerileriniHazirla($maaslar, $doviz_kurlari, $enf_oranlari) {
    $grafik_etiketleri = [];
    $grafik_verileri = [];
    $ortalama_verileri = [];
    $enf_duzeltilmis_maas_verileri = [];
    $enf_duzeltilmis_ortalama_verileri = [];
    $aylar_kisa = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $yillar = yillariMaaslardanGetir($maaslar);
    $baslangic_yili = min($yillar);
    $su_anki_yil = date('Y');
    $bitis_yili = max($su_anki_yil, max($yillar));
    $birikimli_toplam = 0;
    $ay_sayisi = 0;
    $enf_duzeltilmis_maas = 100; // İlk ay maaş 100 TL (2022-07)
    $enf_birikimli_toplam = 0;
    $enf_ay_sayisi = 0;
    
    // İlk maaş tarihini ve nominal maaşları belirle
    $ilk_maas_tarihi = null;
    $ilk_maas = null;
    foreach ($maaslar as $anahtar => $maas) {
        if (strpos($anahtar, '_comment') === 0) continue;
        $giris_tarihi = null;
        if (preg_match('/^\d{4}$/', $anahtar)) {
            $giris_tarihi = $anahtar . '-01-01';
        } elseif (preg_match('/^\d{4}-\d{2}$/', $anahtar)) {
            $giris_tarihi = $anahtar . '-01';
        }
        if ($giris_tarihi && (!$ilk_maas_tarihi || strtotime($giris_tarihi) < strtotime($ilk_maas_tarihi))) {
            $ilk_maas_tarihi = $giris_tarihi;
            $ilk_maas = $maas;
        }
    }

    $su_anki_nominal_maas = 100; // Başlangıç nominal maaş
    $temel_enflasyon = 1; // Birikimli enflasyon oranı (başlangıçta 1)

    for ($yil = $baslangic_yili; $yil <= $bitis_yili; $yil++) {
        $ay_limiti = ($yil == $su_anki_yil) ? date('n') : 12;
        for ($ay = 1; $ay <= $ay_limiti; $ay++) {
            $tarih = ilkIsgununuGetir($yil, $ay);
            $nominal_maas = maasiTarihIleGetir($tarih, $maaslar);
            if ($nominal_maas !== null && strtotime($tarih) >= strtotime($ilk_maas_tarihi)) {
                $kur = $doviz_kurlari[$tarih] ?? null;
                if ($kur !== null && $kur !== 'Veri yok' && $kur > 0) {
                    $usd = $nominal_maas / $kur;
                    $birikimli_toplam += $usd;
                    $ay_sayisi++;
                    $grafik_etiketleri[] = $aylar_kisa[$ay - 1] . ' ' . $yil;
                    $grafik_verileri[] = round($usd, 2);
                    $ortalama_verileri[] = round($birikimli_toplam / $ay_sayisi, 2);

                    // Enflasyon düzeltilmiş maaş hesaplama
                    $enf_anahtari = sprintf('%d-%02d', $yil, $ay);
                    if (strtotime($tarih) === strtotime($ilk_maas_tarihi)) {
                        $enf_duzeltilmis_maas = 100; // Başlangıç ayında 100 TL
                        $temel_enflasyon = 1; // Enflasyonu sıfırla
                    } elseif (isset($enf_oranlari[$enf_anahtari])) {
                        $enf_orani = $enf_oranlari[$enf_anahtari] / 100; // Yüzdeyi ondalığa çevir
                        $temel_enflasyon *= (1 + $enf_orani); // Birikimli enflasyon
                        // Reel maaş = Nominal maaşın başlangıç maaşına oranı * (100 / birikimli enflasyon)
                        $enf_duzeltilmis_maas = (100 / $temel_enflasyon) * ($nominal_maas / $ilk_maas);
                    }
                    $enf_duzeltilmis_maas_verileri[] = round($enf_duzeltilmis_maas, 2);
                    $enf_birikimli_toplam += $enf_duzeltilmis_maas;
                    $enf_ay_sayisi++;
                    $enf_duzeltilmis_ortalama_verileri[] = round($enf_birikimli_toplam / $enf_ay_sayisi, 2);
                }
            }
        }
    }    
    return [
        'labels' => $grafik_etiketleri,
        'salary_data' => $grafik_verileri,
        'average_data' => $ortalama_verileri,
        'inflation_adjusted_salary_data' => $enf_duzeltilmis_maas_verileri,
        'inflation_adjusted_average_data' => $enf_duzeltilmis_ortalama_verileri
    ];
}