<?php
// TCMB döviz kuru ve EVDS enflasyon verisi çekme katmanı

// Bir tarih için denenecek TCMB kur XML adreslerini döndürür
function tcmbKurUrlleri($tarih) {
    $yil_ay = date('Ym', strtotime($tarih));
    $gun_ay_yil = date('dmY', strtotime($tarih));
    $tam_tarih = date('Ymd', strtotime($tarih));
    return [
        "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$gun_ay_yil}.xml",
        "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$tam_tarih}.xml",
    ];
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
                                file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT), LOCK_EX);
                                return $kur_degeri;
                            }
                        }
                    }
                }
            }
        }
    }
    // Tarihi geçmiş için
    $denenecek_adresler = tcmbKurUrlleri($tarih);
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
                           file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT), LOCK_EX);
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
                file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT), LOCK_EX);
                return $kur_degeri;
            }
        }
        // Sonraki gün
        if (isGunuMu($sonraki_tarih) && strtotime($sonraki_tarih) <= time()) {
            $kur_degeri = tarihIcinKurDegeriDene($sonraki_tarih, $baglam);
            if ($kur_degeri !== null) {
               $doviz_kurlari[$tarih] = $kur_degeri;
               $doviz_kurlari[$sonraki_tarih] = $kur_degeri;
               file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT), LOCK_EX);
               return $kur_degeri;
            }
        }        
        $deneme_sayisi++;
    }
    // Hata kaydı bas
    file_put_contents($log_dosyasi, "Veri alınamadı: $tarih - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $doviz_kurlari[$tarih] = 'Veri yok';
    file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT), LOCK_EX);
    return null;
}
// Yardımcı döviz kuru getirme fonksiyonu
function tarihIcinKurDegeriDene($tarih, $baglam) {
    $denenecek_adresler = tcmbKurUrlleri($tarih);
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
                $adres = tcmbKurUrlleri($tarih)[0];
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $adres);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
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
    file_put_contents($doviz_kur_dosyasi, json_encode($doviz_kurlari, JSON_PRETTY_PRINT), LOCK_EX);
}

// TÜİK aylık enflasyon oranlarını EVDS'ten çek (resmi TÜFE Genel Endeks, aylık % değişim)
function enflasyonOranlariniGetir(&$enf_orani, $enf_orani_dosyasi, $maaslar, $log_dosyasi, $api_key, $seri_kodu = 'TP.GENENDEKS.T1') {
    if (empty($api_key)) {
        return; // Anahtar yoksa otomatik çekme devre dışı; mevcut dosya elle kullanılır
    }

    // En erken maaş ayını bul (başlangıç tarihi)
    $bas_zaman = null;
    foreach ($maaslar as $anahtar => $deger) {
        $t = girisTarihiniCoz($anahtar);
        if ($t && ($bas_zaman === null || $t < $bas_zaman)) {
            $bas_zaman = $t;
        }
    }
    if ($bas_zaman === null) return;

    // Olması gereken aylar: ilk maaş ayından bu aya kadar
    $gereken = [];
    $imlec = $bas_zaman;
    $bu_ay = strtotime(date('Y-m-01'));
    while ($imlec <= $bu_ay) {
        $gereken[] = date('Y-m', $imlec);
        $imlec = strtotime(date('Y-m-01', $imlec) . ' +1 month');
    }
    $eksik = array_diff($gereken, array_keys($enf_orani));

    // Hız sınırı: eksik ay yoksa ve dosya son 6 saatte güncellendiyse API'ye gitme
    if (empty($eksik) && file_exists($enf_orani_dosyasi)
        && (time() - filemtime($enf_orani_dosyasi)) < 6 * 3600) {
        return;
    }

    // EVDS isteği: TÜFE Genel Endeks (2003=100), aylık (frequency=5), yüzde değişim (formulas=1)
    $bas = date('01-m-Y', $bas_zaman);
    $bit = date('01-m-Y');
    $adres = "https://evds3.tcmb.gov.tr/igmevdsms-dis/series={$seri_kodu}"
        . "&startDate={$bas}&endDate={$bit}&type=json&frequency=5&formulas=1";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $adres);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['key: ' . $api_key]);
    $yanit = curl_exec($ch);
    $http_kodu = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($yanit === false || $http_kodu !== 200) {
        file_put_contents($log_dosyasi, "EVDS enflasyon isteği başarısız (HTTP $http_kodu): " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        return;
    }
    $veri = json_decode($yanit, true);
    if (!isset($veri['items']) || !is_array($veri['items'])) {
        file_put_contents($log_dosyasi, "EVDS enflasyon yanıtı çözümlenemedi: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        return;
    }

    $degisti = false;
    foreach ($veri['items'] as $kayit) {
        if (!isset($kayit['Tarih'])) continue;
        // Tarih "YYYY-M" -> "YYYY-MM"
        $parca = explode('-', $kayit['Tarih']);
        if (count($parca) !== 2) continue;
        $anahtar = sprintf('%04d-%02d', (int)$parca[0], (int)$parca[1]);
        // Değer alanı: "Tarih"/"UNIXTIME" dışındaki alan (örn. "TP_GENENDEKS_T1-1")
        $deger = null;
        foreach ($kayit as $alan => $v) {
            if ($alan === 'Tarih' || $alan === 'UNIXTIME') continue;
            $deger = $v;
            break;
        }
        if ($deger === null || $deger === '') continue;
        $enf_orani[$anahtar] = round((float)$deger, 2);
        $degisti = true;
    }
    if ($degisti) {
        krsort($enf_orani); // Yeni -> eski sıralama (mevcut dosya stiliyle uyumlu)
        file_put_contents($enf_orani_dosyasi, json_encode($enf_orani, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

