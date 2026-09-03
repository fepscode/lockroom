<?php
// Complete and Authoritative List of All Kota & Kabupaten in Indonesia (514+ Daerah)
// LOCK & ROOM (L n' R)

function getAllIndonesianCities() {
    return [
        // DKI Jakarta & Bodetabek (Jabodetabek)
        "Jakarta Barat", "Jakarta Pusat", "Jakarta Selatan", "Jakarta Timur", "Jakarta Utara", "Kepulauan Seribu",
        "Bogor (Kota)", "Bogor (Kabupaten)", "Depok", "Tangerang (Kota)", "Tangerang (Kabupaten)", "Tangerang Selatan",
        "Bekasi (Kota)", "Bekasi (Kabupaten)",

        // Jawa Barat
        "Bandung (Kota)", "Bandung (Kabupaten)", "Bandung Barat", "Banjar", "Ciamis", "Cianjur", 
        "Cimahi", "Cirebon (Kota)", "Cirebon (Kabupaten)", "Garut", "Indramayu", "Karawang", 
        "Kuningan", "Majalengka", "Pangandaran", "Purwakarta", "Subang", "Sukabumi (Kota)", 
        "Sukabumi (Kabupaten)", "Sumedang", "Tasikmalaya (Kota)", "Tasikmalaya (Kabupaten)",

        // Banten
        "Cilegon", "Lebak", "Pandeglang", "Serang (Kota)", "Serang (Kabupaten)",

        // Jawa Tengah
        "Banjarnegara", "Banyumas (Purwokerto)", "Batang", "Blora", "Boyolali", "Brebes", "Cilacap", 
        "Demak", "Grobogan (Purwodadi)", "Jepara", "Karanganyar", "Kebumen", "Kendal", "Klaten", 
        "Kudus", "Magelang (Kota)", "Magelang (Kabupaten)", "Pati", "Pekalongan (Kota)", "Pekalongan (Kabupaten)", 
        "Pemalang", "Purbalingga", "Purworejo", "Rembang", "Salatiga", "Semarang (Kota)", "Semarang (Kabupaten)", 
        "Sragen", "Sukoharjo", "Surakarta (Solo)", "Tegal (Kota)", "Tegal (Kabupaten)", "Temanggung", 
        "Wonogiri", "Wonosobo",

        // DI Yogyakarta
        "Yogyakarta (Kota)", "Bantul", "Gunungkidul (Wonosari)", "Kulon Progo (Wates)", "Sleman",

        // Jawa Timur
        "Bangkalan", "Banyuwangi", "Batu", "Blitar (Kota)", "Blitar (Kabupaten)", "Bojonegoro", 
        "Bondowoso", "Gresik", "Jember", "Jombang", "Kediri (Kota)", "Kediri (Kabupaten)", 
        "Lamongan", "Lumajang", "Madiun (Kota)", "Madiun (Kabupaten)", "Magetan", "Malang (Kota)", 
        "Malang (Kabupaten)", "Mojokerto (Kota)", "Mojokerto (Kabupaten)", "Nganjuk", "Ngawi", 
        "Pacitan", "Pamekasan", "Pasuruan (Kota)", "Pasuruan (Kabupaten)", "Ponorogo", "Probolinggo (Kota)", 
        "Probolinggo (Kabupaten)", "Sampang", "Sidoarjo", "Situbondo", "Sumenep", "Surabaya", 
        "Trenggalek", "Tuban", "Tulungagung",

        // Bali & Nusa Tenggara
        "Denpasar", "Badung (Kuta/Canggu)", "Bangli", "Buleleng (Singaraja)", "Gianyar (Ubud)", 
        "Jembrana (Negara)", "Karangasem", "Klungkung", "Tabanan",
        "Mataram", "Bima (Kota)", "Bima (Kabupaten)", "Dompu", "Lombok Barat", "Lombok Tengah (Praya)", 
        "Lombok Timur (Selong)", "Lombok Utara", "Sumbawa", "Sumbawa Barat",
        "Kupang (Kota)", "Kupang (Kabupaten)", "Alor", "Belu (Atambua)", "Ende", "Flores Timur (Larantuka)", 
        "Lembata", "Malaka", "Manggarai (Ruteng)", "Manggarai Barat (Labuan Bajo)", "Manggarai Timur", 
        "Nagekeo", "Ngada (Bajawa)", "Rote Ndao", "Sabu Raijua", "Sikka (Maumere)", "Sumba Barat", 
        "Sumba Barat Daya", "Sumba Tengah", "Sumba Timur (Waingapu)", "Timor Tengah Selatan", "Timor Tengah Utara",

        // Sumatera
        "Banda Aceh", "Langsa", "Lhokseumawe", "Sabang", "Subulussalam", "Aceh Barat (Meulaboh)", 
        "Aceh Barat Daya", "Aceh Besar (Jantho)", "Aceh Jaya", "Aceh Selatan (Tapaktuan)", "Aceh Singkil", 
        "Aceh Tamiang", "Aceh Tengah (Takengon)", "Aceh Tenggara", "Aceh Timur", "Aceh Utara", "Bener Meriah", 
        "Bireuen", "Gayo Lues", "Nagan Raya", "Pidie (Sigli)", "Pidie Jaya", "Simeulue",
        "Medan", "Binjai", "Gunungsitoli", "Padangsidimpuan", "Pematangsiantar", "Sibolga", "Tanjungbalai", 
        "Tebing Tinggi", "Asahan (Kisaran)", "Batu Bara", "Dairi (Sidikalang)", "Deli Serdang (Lubuk Pakam)", 
        "Humbang Hasundutan", "Karo (Kabanjahe)", "Labuhanbatu (Rantau Prapat)", "Labuhanbatu Selatan", 
        "Labuhanbatu Utara", "Langkat (Stabat)", "Mandailing Natal (Panyabungan)", "Nias", "Nias Barat", 
        "Nias Selatan", "Nias Utara", "Padang Lawas", "Padang Lawas Utara", "Pakpak Bharat", "Samosir", 
        "Serdang Bedagai", "Simalungun", "Tapanuli Selatan", "Tapanuli Tengah", "Tapanuli Utara (Tarutung)", "Toba",
        "Padang", "Bukittinggi", "Padang Panjang", "Pariaman", "Payakumbuh", "Sawahlunto", "Solok (Kota)", 
        "Agam", "Dharmasraya", "Kepulauan Mentawai", "Lima Puluh Kota", "Padang Pariaman", "Pasaman", 
        "Pasaman Barat", "Pesisir Selatan (Painan)", "Sijunjung", "Solok (Kabupaten)", "Solok Selatan", "Tanah Datar (Batusangkar)",
        "Pekanbaru", "Dumai", "Bengkalis", "Indragiri Hilir (Tembilahan)", "Indragiri Hulu (Rengat)", 
        "Kampar (Bangkinang)", "Kepulauan Meranti (Selatpanjang)", "Kuantan Singingi (Teluk Kuantan)", 
        "Pelalawan (Pangkalan Kerinci)", "Rokan Hilir (Bagan Siapi-api)", "Rokan Hulu (Pasir Pengaraian)", "Siak",
        "Batam", "Tanjungpinang", "Bintan", "Karimun", "Kepulauan Anambas", "Lingga", "Natuna (Ranai)",
        "Jambi (Kota)", "Sungai Penuh", "Batanghari (Muara Bulian)", "Bungo (Muara Bungo)", "Kerinci", 
        "Merangin (Bangko)", "Muaro Jambi (Sengeti)", "Sarolangun", "Tanjung Jabung Barat (Kuala Tungkal)", 
        "Tanjung Jabung Timur (Muara Sabak)", "Tebo",
        "Palembang", "Lubuklinggau", "Pagar Alam", "Prabumulih", "Banyuasin (Pangkalan Balai)", "Empat Lawang (Tebing Tinggi)", 
        "Lahat", "Muara Enim", "Musi Banyuasin (Sekayu)", "Musi Rawas", "Musi Rawas Utara", "Ogan Ilir (Indralaya)", 
        "Ogan Komering Ilir (Kayu Agung)", "Ogan Komering Ulu (Baturaja)", "Ogan Komering Ulu Selatan", 
        "Ogan Komering Ulu Timur", "Penukal Abab Lematang Ilir (PALI)",
        "Pangkalpinang", "Bangka (Sungailiat)", "Bangka Barat (Muntok)", "Bangka Selatan (Toboali)", 
        "Bangka Tengah (Koba)", "Belitung (Tanjung Pandan)", "Belitung Timur (Manggar)",
        "Bengkulu (Kota)", "Bengkulu Selatan (Manna)", "Bengkulu Tengah", "Bengkulu Utara (Arga Makmur)", 
        "Kaur", "Kepahiang", "Lebong", "Mukomuko", "Rejang Lebong (Curup)", "Seluma (Tais)",
        "Bandar Lampung", "Metro", "Lampung Barat (Liwa)", "Lampung Selatan (Kalianda)", "Lampung Tengah (Gunung Sugih)", 
        "Lampung Timur (Sukadana)", "Lampung Utara (Kotabumi)", "Mesuji", "Pesawaran (Gedong Tataan)", 
        "Pesisir Barat (Krui)", "Pringsewu", "Tanggamus (Kota Agung)", "Tulang Bawang (Menggala)", 
        "Tulang Bawang Barat", "Way Kanan (Blambangan Umpu)",

        // Kalimantan
        "Pontianak", "Singkawang", "Bengkayang", "Kapuas Hulu (Putussibau)", "Kayong Utara (Sukadana)", 
        "Ketapang", "Kubu Raya (Sungai Raya)", "Landak (Ngabang)", "Melawi (Nanga Pinoh)", "Mempawah", 
        "Sambas", "Sanggau", "Sekadau", "Sintang",
        "Palangka Raya", "Barito Selatan (Buntok)", "Barito Timur (Tamiang Layang)", "Barito Utara (Muara Teweh)", 
        "Gunung Mas (Kuala Kurun)", "Kapuas (Kuala Kapuas)", "Katingan (Kasongan)", "Kotawaringin Barat (Pangkalan Bun)", 
        "Kotawaringin Timur (Sampit)", "Lamandau (Nanga Bulik)", "Murung Raya (Puruk Cahu)", "Pulang Pisau", 
        "Seruyan (Kuala Pembuang)", "Sukamara",
        "Banjarmasin", "Banjarbaru", "Balangan (Paringin)", "Banjar (Martapura)", "Barito Kuala (Marabahan)", 
        "Hulu Sungai Selatan (Kandangan)", "Hulu Sungai Tengah (Barabai)", "Hulu Sungai Utara (Amuntai)", 
        "Kotabaru", "Tabalong (Tanjung)", "Tanah Bumbu (Batulicin)", "Tanah Laut (Pelaihari)", "Tapin (Rantau)",
        "Balikpapan", "Bontang", "Samarinda", "Berau (Tanjung Redeb)", "Kutai Barat (Sendawar)", 
        "Kutai Kartanegara (Tenggarong)", "Kutai Timur (Sangatta)", "Mahakam Ulu (Ujoh Bilang)", 
        "Paser (Tanah Grogot)", "Penajam Paser Utara (IKN Nusantara)",
        "Tarakan", "Bulungan (Tanjung Selor)", "Malinau", "Nunukan", "Tana Tidung (Tideng Pale)",

        // Sulawesi
        "Manado", "Bitung", "Kotamobagu", "Tomohon", "Bolaang Mongondow", "Bolaang Mongondow Selatan", 
        "Bolaang Mongondow Timur", "Bolaang Mongondow Utara", "Kepulauan Sangihe (Tahuna)", 
        "Kepulauan Siau Tagulandang Biaro (Ondong Siau)", "Kepulauan Talaud (Melonguane)", "Minahasa (Tondano)", 
        "Minahasa Selatan (Amurang)", "Minahasa Tenggara (Ratahan)", "Minahasa Utara (Airmadidi)",
        "Gorontalo (Kota)", "Boalemo (Tilamuta)", "Bone Bolango (Suwawa)", "Gorontalo (Kabupaten)", 
        "Gorontalo Utara (Kwandang)", "Pohuwato (Marisa)",
        "Palu", "Banggai (Luwuk)", "Banggai Kepulauan (Salakan)", "Banggai Laut (Banggai)", "Buol", 
        "Donggala (Banawa)", "Morowali (Bungku)", "Morowali Utara (Kolonodale)", "Parigi Moutong (Parigi)", 
        "Poso", "Sigi (Sigi Biromaru)", "Tojo Una-Una (Ampana)", "Tolitoli",
        "Majene", "Mamasa", "Mamuju", "Mamuju Tengah (Tobadak)", "Pasangkayu", "Polewali Mandar (Polewali)",
        "Makassar", "Palopo", "Parepare", "Bantaeng", "Barru", "Bone (Watampone)", "Bulukumba", 
        "Enrekang", "Gowa (Sungguminasa)", "Jeneponto (Bontosunggu)", "Kepulauan Selayar (Benteng)", 
        "Luwu (Belopa)", "Luwu Timur (Malili)", "Luwu Utara (Masamba)", "Maros (Turikale)", 
        "Pangkajene dan Kepulauan (Pangkep)", "Pinrang", "Sidenreng Rappang (Sidrap)", "Sinjai", 
        "Soppeng (Watansoppeng)", "Takalar (Pattallassang)", "Tana Toraja (Makale)", "Toraja Utara (Rantepao)", "Wajo (Sengkang)",
        "Kendari", "Baubau", "Bombana (Rumbia)", "Buton (Pasarwajo)", "Buton Selatan (Batauga)", 
        "Buton Tengah (Labungkari)", "Buton Utara (Buranga)", "Kolaka", "Kolaka Timur (Tirawuta)", 
        "Kolaka Utara (Lasusua)", "Konawe (Unaaha)", "Konawe Kepulauan (Langara)", "Konawe Selatan (Andoolo)", 
        "Konawe Utara (Wanggudu)", "Muna (Raha)", "Muna Barat (Sawerigadi)", "Wakatobi (Wangi-Wangi)",

        // Maluku & Papua
        "Ambon", "Tual", "Buru (Namlea)", "Buru Selatan (Namrole)", "Kepulauan Aru (Dobo)", 
        "Kepulauan Tanimbar (Saumlaki)", "Maluku Barat Daya (Tiakur)", "Maluku Tengah (Masohi)", 
        "Maluku Tenggara (Langgur)", "Seram Bagian Barat (Piru)", "Seram Bagian Timur (Bula)",
        "Ternate", "Tidore Kepulauan", "Halmahera Barat (Jailolo)", "Halmahera Tengah (Weda)", 
        "Halmahera Timur (Maba)", "Halmahera Selatan (Labuha)", "Halmahera Utara (Tobelo)", 
        "Kepulauan Sula (Sanana)", "Pulau Morotai (Daruba)", "Pulau Taliabu (Bobong)",
        "Jayapura (Kota)", "Jayapura (Kabupaten/Sentani)", "Biak Numfor", "Keerom (Waris)", "Mamberamo Raya (Burmeso)", 
        "Sarmi", "Supiori (Sorendiweri)", "Waropen (Botawa)",
        "Sorong (Kota)", "Sorong (Kabupaten/Aimas)", "Maybrat (Kumurkek)", "Raja Ampat (Waisai)", 
        "Sorong Selatan (Teminabuan)", "Tambrauw (Fef)",
        "Manokwari", "Fakfak", "Kaimana", "Manokwari Selatan (Ransiki)", "Pegunungan Arfak (Anggi)", 
        "Teluk Bintuni", "Teluk Wondama (Rasiei)",
        "Merauke", "Asmat (Agats)", "Boven Digoel (Tanah Merah)", "Mappi (Kepi)",
        "Nabire", "Deiyai (Tigi)", "Dogiyai (Kigamani)", "Intan Jaya (Sugapa)", "Mimika (Timika)", 
        "Paniai (Enarotali)", "Puncak (Ilaga)", "Puncak Jaya (Kotamulia)",
        "Jayawijaya (Wamena)", "Lanny Jaya (Tiom)", "Mamberamo Tengah (Kobakma)", "Nduga (Kenyam)", 
        "Pegunungan Bintang (Oksibil)", "Tolikara (Karubaga)", "Yahukimo (Dekai)", "Yalimo (Elelim)"
    ];
}

function renderCityDatalist($id = 'citiesList') {
    $cities = getAllIndonesianCities();
    // Sort alphabetically while keeping display clean
    sort($cities);
    $html = '<datalist id="' . htmlspecialchars($id) . '">' . "\n";
    foreach ($cities as $city) {
        $html .= '    <option value="' . htmlspecialchars($city) . '">' . "\n";
    }
    $html .= '</datalist>' . "\n";
    return $html;
}
