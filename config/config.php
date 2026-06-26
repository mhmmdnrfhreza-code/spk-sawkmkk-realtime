<?php
/**
 * Konfigurasi global SPK SAW-KMKK Realtime (Hybrid Model-Driven + Data-Driven DSS).
 * Sesuaikan kredensial database dengan lingkungan Laragon Anda bila perlu.
 */
return [
    'app_name' => 'SPK SAW-KMKK Realtime',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'spk_sawkmkk_rt',
        'user' => 'root',
        'pass' => '', // Laragon default: kosong
    ],

    // Parameter metode
    'q'        => 7,   // jumlah tingkat skala kualitatif (S1..S7)
    'top_n'    => 10,  // jumlah vendor kandidat teratas (berdasar frekuensi award). 0 = semua
    'currency' => 'GBP', // mata uang sumber OCDS (UK Find a Tender)

    // Bobot kriteria (Model-Driven inti). Bisa di-override objektif via Entropy.
    'bobot' => ['C1' => 7, 'C2' => 6, 'C3' => 5], // S, SB, B

    // Sumber data realtime OCDS (Open Contracting Data Standard v1.1)
    'ocds' => [
        // Urutan sumber yang dicoba; berhenti bila target tercapai / waktu habis.
        // contracts-finder: cakupan luas termasuk NHS (ambang >= GBP 10rb/25rb) -> volume besar.
        // find-a-tender:    notice bernilai tinggi (>= ambang OJEU), pelengkap.
        'sources'       => ['contracts-finder', 'find-a-tender'],
        'cf_endpoint'   => 'https://www.contractsfinder.service.gov.uk/Published/Notices/OCDS/Search',
        'cf_stages'     => 'award',      // ambil tahap award -> langsung berisi supplier + nilai
        'cf_limit'      => 100,          // notice per halaman (maks API)
        'endpoint'      => 'https://www.find-tender.service.gov.uk/api/1.0/ocdsReleasePackages',
        'years_back'    => 5,            // jendela data: 5 tahun terakhir
        'cpv_prefixes'  => ['33'],       // CPV 33xxxxxx = peralatan medis, farmasi, perawatan
        // Pencocokan kata kunci (recall) bila CPV tidak ada/tidak 33xxxxxx.
        'health_keywords' => ['medical', 'hospital', 'health', 'clinical', 'surgical', 'diagnostic',
            'patient', 'pharmaceutical', 'ventilator', 'x-ray', 'mri', 'ct scanner', 'ultrasound',
            'laboratory', 'nhs', 'theatre', 'endoscop', 'defibrillator', 'infusion', 'imaging'],
        'max_pages'     => 80,           // batas aman jumlah halaman per sinkronisasi
        'max_seconds'   => 90,           // anggaran waktu total fetch live (detik)
        'target_health' => 1500,         // berhenti setelah mengumpulkan sekian rilis kesehatan
        'page_pause_s'  => 0,            // jeda antar halaman (detik)
        'timeout'       => 20,           // timeout cURL per request (detik)
        'use_fallback'  => true,         // pakai snapshot lokal jika API tidak dapat dihubungi (NFR-03)
    ],

    // Atribusi lisensi (NFR-07)
    'attribution' => 'Contains Public Sector Information Licensed Under The Open Government Licence v3.0. - Find a Tender Service (GOV.UK) - OCDS API.',
];
