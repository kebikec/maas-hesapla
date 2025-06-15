<?php
// Dosya yolları
$maas_dosyasi = __DIR__ . '/maas.json';
$doviz_kur_dosyasi = __DIR__ . '/doviz-kuru.json';
$enf_orani_dosyasi = __DIR__ . '/tuik-aylik.json';
$log_file = __DIR__ . '/hata-kaydi.txt';

// Maaş verilerini yükle
$maaslar = [];
if (file_exists($maas_dosyasi)) {
   $maaslar = json_decode(file_get_contents($maas_dosyasi), true) ?: [];
} else { die('Hata: maas.json dosyası bulunamadı.'); }

// Döviz kurlarını yükle
$doviz_kur_degeri = [];
if (file_exists($doviz_kur_dosyasi)) {
    $doviz_kur_degeri = json_decode(file_get_contents($doviz_kur_dosyasi), true) ?: [];
}

// Enflasyon verilerini yükle
$enf_orani = [];
if (file_exists($enf_orani_dosyasi)) {
    $enf_orani = json_decode(file_get_contents($enf_orani_dosyasi), true) ?: [];
} else { die('Hata: tuik-aylik.json dosyası bulunamadı.'); }

// Belirli tarih için maaş getir
function getSalaryForDate($date, $salaries) {
    $target_date = strtotime($date);
    $applicable_salary = null;
    $latest_applicable_date = 0;
    foreach ($salaries as $key => $salary) {        
        if (strpos($key, '_comment') === 0) {
            continue;
        }        
        $entry_date = null;        
        // sadece yıl ise ("2023")
        if (preg_match('/^\d{4}$/', $key)) {
            $entry_date = strtotime($key . '-01-01');
        }
        // yıl-ay düzeni ("2024-08")
        elseif (preg_match('/^\d{4}-\d{2}$/', $key)) {
            $entry_date = strtotime($key . '-01');
        }        
        // Bu giriş hedef tarih için geçerliyse ve en yakın tarihse
        if ($entry_date && $entry_date <= $target_date && $entry_date > $latest_applicable_date) {
            $latest_applicable_date = $entry_date;
            $applicable_salary = $salary;
        }
    }    
    return $applicable_salary;
}

// Maaş verilerindeki yılları getir
function getYearsFromSalaries($salaries) {
    $years = [];
    foreach ($salaries as $key => $salary) {
        if (strpos($key, '_comment') === 0) {
            continue;
        }        
        if (preg_match('/^(\d{4})/', $key, $matches)) {
            $years[] = intval($matches[1]);
        }
    }
    return array_unique($years);
}

// İş günü kontrolü
function isGunuMu($date) {
    $day_of_week = date('N', strtotime($date));
    return $day_of_week >= 1 && $day_of_week <= 5;
}

// Ayın ilk iş gününü getir
function ilkIsgununuGetir($year, $month) {
    $date = sprintf('%d-%02d-01', $year, $month);
    while (!isGunuMu($date)) {
        $date = date('Y-m-d', strtotime($date . ' +1 day'));
    }
    return $date;
}

// Döviz kuru getir (tek tarih)
function fetchExchangeRate($date, &$exchange_rates, $exchange_rates_file, $log_file) {
    $date_key = $date;
    if (isset($exchange_rates[$date_key]) && $exchange_rates[$date_key] !== 'Veri yok') {
        return $exchange_rates[$date_key];
    }    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);    
    $today = date('Y-m-d');
    if ($date === $today) {
        $urls_to_try = [
          "https://www.tcmb.gov.tr/kurlar/today.xml",
          "https://www.tcmb.gov.tr/kurlar/" . date('Ym') . "/" . date('dmY') . ".xml"
        ];
        foreach ($urls_to_try as $url) {
            $xml_content = @file_get_contents($url, false, $context);
            if ($xml_content !== false) {
                $xml = @simplexml_load_string($xml_content);
                if ($xml !== false) {
                    foreach ($xml->Currency as $cur) {
                        if ($cur['Kod'] == 'USD') {
                            $rate = floatval($cur->ForexBuying);
                            if ($rate > 0) {
                                $exchange_rates[$date] = $rate;
                                file_put_contents($exchange_rates_file, json_encode($exchange_rates, JSON_PRETTY_PRINT));
                                return $rate;
                            }
                        }
                    }
                }
            }
        }
    }
    // Tarihi geçmiş için
    $yil_ay = date('Ym', strtotime($date));
    $gun_ay_yil = date('dmY', strtotime($date));
    $tam_tarih = date('Ymd', strtotime($date));
    $urls_to_try = [
       "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$gun_ay_yil}.xml",
       "https://www.tcmb.gov.tr/kurlar/{$yil_ay}/{$tam_tarih}.xml"
    ];
    foreach ($urls_to_try as $url) {
        $xml_content = @file_get_contents($url, false, $context);
        if ($xml_content !== false) {
            $xml = @simplexml_load_string($xml_content);
            if ($xml !== false) {
                foreach ($xml->Currency as $cur) {
                    if ($cur['Kod'] == 'USD') {
                        $rate = floatval($cur->ForexBuying);
                        if ($rate > 0) {
                           $exchange_rates[$date] = $rate;
                           file_put_contents($exchange_rates_file, json_encode($exchange_rates, JSON_PRETTY_PRINT));
                           return $rate;
                        }
                    }
                }
            }
        }
    }
    // Eğer iş günü değilse
    $attempts = 0;
    $prev_date = $date;
    $next_date = $date;
    while ($attempts < 5) {
        $prev_date = date('Y-m-d', strtotime($prev_date . ' -1 day'));
        $next_date = date('Y-m-d', strtotime($next_date . ' +1 day'));
        // Önceki gün denemesi
        if (isGunuMu($prev_date)) {
            $rate = tryFetchRateForDate($prev_date, $context);
            if ($rate !== null) {
                $exchange_rates[$date] = $rate;
                $exchange_rates[$prev_date] = $rate;
                file_put_contents($exchange_rates_file, json_encode($exchange_rates, JSON_PRETTY_PRINT));
                return $rate;
            }
        }
        // Sonraki gün
        if (isGunuMu($next_date) && strtotime($next_date) <= time()) {
            $rate = tryFetchRateForDate($next_date, $context);
            if ($rate !== null) {
               $exchange_rates[$date] = $rate;
               $exchange_rates[$next_date] = $rate;
               file_put_contents($exchange_rates_file, json_encode($exchange_rates, JSON_PRETTY_PRINT));
               return $rate;
            }
        }        
        $attempts++;
    }
    // Hata kaydı bas
    file_put_contents($log_file, "Veri alınamadı: $date - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $exchange_rates[$date] = 'Veri yok';
    file_put_contents($exchange_rates_file, json_encode($exchange_rates, JSON_PRETTY_PRINT));
    return null;
}
// Yardımcı döviz kuru getirme fonksiyonu
function tryFetchRateForDate($date, $context) {
    $year_month = date('Ym', strtotime($date));
    $day_month_year = date('dmY', strtotime($date));
    $full_date = date('Ymd', strtotime($date));
    $urls_to_try = [
        "https://www.tcmb.gov.tr/kurlar/{$year_month}/{$day_month_year}.xml",
        "https://www.tcmb.gov.tr/kurlar/{$year_month}/{$full_date}.xml"
    ];
    foreach ($urls_to_try as $url) {
        $xml_content = @file_get_contents($url, false, $context);
        if ($xml_content !== false) {
            $xml = @simplexml_load_string($xml_content);
            if ($xml !== false) {
                foreach ($xml->Currency as $cur) {
                    if ($cur['Kod'] == 'USD') {
                        $rate = floatval($cur->ForexBuying);
                        if ($rate > 0) {
                            return $rate;
                        }
                    }
                }
            }
        }
    }
    return null;
}

// Toplu döviz kuru getir (çoklu tarih)
function fetchExchangeRatesParallel($dates, &$exchange_rates, $exchange_rates_file, $log_file) {
    $mh = curl_multi_init();
    $curl_handles = [];
    $date_to_handle = [];
    foreach ($dates as $date) {
        if (!isset($exchange_rates[$date]) || $exchange_rates[$date] === 'Veri yok') {
            $today = date('Y-m-d');
            if ($date === $today) {
                $url = "https://www.tcmb.gov.tr/kurlar/today.xml";
            } else {
                $year_month = date('Ym', strtotime($date));
                $day_month_year = date('dmY', strtotime($date));
                $url = "https://www.tcmb.gov.tr/kurlar/{$year_month}/{$day_month_year}.xml";
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_multi_add_handle($mh, $ch);
            $curl_handles[] = $ch;
            $date_to_handle[$date] = $ch;
        }
    }
    if (empty($curl_handles)) {
        curl_multi_close($mh);
        return;
    }
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh);
        }
    } while ($running > 0 && $status == CURLM_OK);
    foreach ($date_to_handle as $date => $ch) {
        $xml_data = curl_multi_getcontent($ch);
        $rate = null;
        if ($xml_data !== false && !empty($xml_data)) {
            $xml = @simplexml_load_string($xml_data);
            if ($xml !== false) {
                foreach ($xml->Currency as $cur) {
                    if ($cur['Kod'] == 'USD') {
                        $rate = floatval($cur->ForexBuying);
                        if ($rate > 0) {
                            $exchange_rates[$date] = $rate;
                            break;
                        }
                    }
                }
            }
        }        
        if ($rate === null || $rate == 0) {
            // Fallback to individual fetch
            $rate = fetchExchangeRate($date, $exchange_rates, $exchange_rates_file, $log_file);
        }        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }    
    curl_multi_close($mh);
    file_put_contents($exchange_rates_file, json_encode($exchange_rates, JSON_PRETTY_PRINT));
}

// Yıllık verileri hesapla
function calculateYearlyData($salaries, &$exchange_rates, $inflation_rates, $exchange_rates_file, $log_file) {
    $years = getYearsFromSalaries($salaries);
    $baslangic_yili = min($years);
    $suAnki_yil = date('Y');
    $end_year = max($suAnki_yil, max($years));
    $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $sonuclar = [];
    $dates_to_fetch = [];
    // Pre-fetch için tarihleri hazırla
    for ($year = $baslangic_yili; $year <= $end_year; $year++) {
        $month_limit = ($year == $suAnki_yil) ? date('n') : 12;
        for ($month = 1; $month <= $month_limit; $month++) {
            $date = ilkIsgununuGetir($year, $month);
            if (!isset($exchange_rates[$date]) || $exchange_rates[$date] === 'Veri yok') {
                $dates_to_fetch[] = $date;
            }
        }
    }
    // Toplu döviz kuru getir
    if (!empty($dates_to_fetch)) {
        fetchExchangeRatesParallel($dates_to_fetch, $exchange_rates, $exchange_rates_file, $log_file);
    }
    // Hesapla
    for ($year = $baslangic_yili; $year <= $end_year; $year++) {
        $monthly_usd = [];
        $month_limit = ($year == $suAnki_yil) ? date('n') : 12;
        for ($month = 1; $month <= $month_limit; $month++) {
            $date = ilkIsgununuGetir($year, $month);
            $salary = getSalaryForDate($date, $salaries);
            if ($salary !== null) {
                $rate = $exchange_rates[$date] ?? 'Veri yok';
                if ($rate !== 'Veri yok' && $rate > 0) {
                    $usd = $salary / $rate;
                    $monthly_usd[$aylar[$month - 1]] = number_format($usd, 2);
                } else {
                    $monthly_usd[$aylar[$month - 1]] = 'Veri yok';
                }
            } else {
                $monthly_usd[$aylar[$month - 1]] = 'Maaş tanımı yok';
            }
        }
        if (!empty($monthly_usd)) {
            $valid_usd = array_filter($monthly_usd, function($v) { 
                return $v !== 'Veri yok' && $v !== 'Maaş tanımı yok'; 
            });
            $numeric_values = array_map(function($v) { 
                return floatval(str_replace(',', '', $v)); 
            }, $valid_usd);
            $sonuclar[$year] = [
                'average' => !empty($numeric_values) ? number_format(array_sum($numeric_values) / count($numeric_values), 2) : 'Veri yok',
                'monthly' => $monthly_usd
            ];
        }
    }
    return $sonuclar;
}

// Grafik verilerini hazırla
function prepareChartData($salaries, $exchange_rates, $inflation_rates) {
    $chart_labels = [];
    $chart_data = [];
    $average_data = [];
    $inflation_adjusted_salary_data = [];
    $inflation_adjusted_average_data = [];
    $months_short = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $years = getYearsFromSalaries($salaries);
    $start_year = min($years);
    $current_year = date('Y');
    $end_year = max($current_year, max($years));
    $cumulative_sum = 0;
    $month_count = 0;
    $inflation_adjusted_salary = 100; // İlk ay maaş 100 TL (2022-07)
    $inflation_cumulative_sum = 0;
    $inflation_month_count = 0;
    
    // İlk maaş tarihini ve nominal maaşları belirle
    $ilk_maas_gunu = null;
    $ilk_maas = null;
    foreach ($salaries as $key => $salary) {
        if (strpos($key, '_comment') === 0) continue;
        $entry_date = null;
        if (preg_match('/^\d{4}$/', $key)) {
            $entry_date = $key . '-01-01';
        } elseif (preg_match('/^\d{4}-\d{2}$/', $key)) {
            $entry_date = $key . '-01';
        }
        if ($entry_date && (!$ilk_maas_gunu || strtotime($entry_date) < strtotime($ilk_maas_gunu))) {
            $ilk_maas_gunu = $entry_date;
            $ilk_maas = $salary;
        }
    }

    $current_nominal_salary = 100; // Başlangıç nominal maaş
    $base_inflation = 1; // Birikimli enflasyon oranı (başlangıçta 1)

    for ($year = $start_year; $year <= $end_year; $year++) {
        $month_limit = ($year == $current_year) ? date('n') : 12;
        for ($month = 1; $month <= $month_limit; $month++) {
            $date = ilkIsgununuGetir($year, $month);
            $nominal_salary = getSalaryForDate($date, $salaries);
            if ($nominal_salary !== null && strtotime($date) >= strtotime($ilk_maas_gunu)) {
                $rate = $exchange_rates[$date] ?? null;
                if ($rate !== null && $rate !== 'Veri yok' && $rate > 0) {
                    $usd = $nominal_salary / $rate;
                    $cumulative_sum += $usd;
                    $month_count++;
                    $chart_labels[] = $months_short[$month - 1] . ' ' . $year;
                    $chart_data[] = round($usd, 2);
                    $average_data[] = round($cumulative_sum / $month_count, 2);

                    // Enflasyon düzeltilmiş maaş hesaplama
                    $inflation_key = sprintf('%d-%02d', $year, $month);
                    if (strtotime($date) === strtotime($ilk_maas_gunu)) {
                        $inflation_adjusted_salary = 100; // Başlangıç ayında 100 TL
                        $base_inflation = 1; // Enflasyonu sıfırla
                    } elseif (isset($inflation_rates[$inflation_key])) {
                        $inflation_rate = $inflation_rates[$inflation_key] / 100; // Yüzdeyi ondalığa çevir
                        $base_inflation *= (1 + $inflation_rate); // Birikimli enflasyon
                        // Reel maaş = Nominal maaşın başlangıç maaşına oranı * (100 / birikimli enflasyon)
                        $inflation_adjusted_salary = (100 / $base_inflation) * ($nominal_salary / $ilk_maas);
                    }
                    $inflation_adjusted_salary_data[] = round($inflation_adjusted_salary, 2);
                    $inflation_cumulative_sum += $inflation_adjusted_salary;
                    $inflation_month_count++;
                    $inflation_adjusted_average_data[] = round($inflation_cumulative_sum / $inflation_month_count, 2);
                }
            }
        }
    }    
    return [
        'labels' => $chart_labels,
        'salary_data' => $chart_data,
        'average_data' => $average_data,
        'inflation_adjusted_salary_data' => $inflation_adjusted_salary_data,
        'inflation_adjusted_average_data' => $inflation_adjusted_average_data
    ];
}