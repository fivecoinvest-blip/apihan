<?php
/**
 * Language Codes Reference
 * Complete list of language codes supported by the SoftAPI
 * 
 * Use these language codes in the 'language' parameter when launching games.
 * The language parameter is OPTIONAL - if not provided, the default language will be used.
 * 
 * Usage:
 * $result = launchGame($userId, $balance, $gameUid, 'BDT', 'bn'); // Bengali
 */

/**
 * Get all supported language codes
 * @return array Array of language codes with details
 */
function getSupportedLanguages(): array {
    return [
        // Afghanistan
        'ps' => ['name' => 'پښتو (Pashto)', 'country' => 'AF', 'flag' => '🇦🇫'],
        
        // Albania
        'sq' => ['name' => 'Shqip (Albanian)', 'country' => 'AL', 'flag' => '🇦🇱'],
        
        // Armenia
        'hy' => ['name' => 'Հայերեն (Hayeren / Armenian)', 'country' => 'AM', 'flag' => '🇦🇲'],
        
        // Azerbaijan
        'az' => ['name' => 'Azərbaycan dili (Azerbaijani)', 'country' => 'AZ', 'flag' => '🇦🇿'],
        
        // Bosnia
        'bs' => ['name' => 'Bosanski (Bosnian)', 'country' => 'BA', 'flag' => '🇧🇦'],
        
        // Bangladesh
        'bn' => ['name' => 'বাংলা (Bangla)', 'country' => 'BD', 'flag' => '🇧🇩'],
        
        // Belgium
        'wa' => ['name' => 'Walon (Walloon)', 'country' => 'BE', 'flag' => '🇧🇪'],
        
        // Bulgaria
        'bg' => ['name' => 'Български (Bulgarian)', 'country' => 'BG', 'flag' => '🇧🇬'],
        
        // Burundi
        'rn' => ['name' => 'Ikirundi (Kirundi)', 'country' => 'BI', 'flag' => '🇧🇮'],
        
        // Bolivia
        'ay' => ['name' => 'Aymar aru', 'country' => 'BO', 'flag' => '🇧🇴'],
        
        // Bhutan
        'dz' => ['name' => 'རྫོང་ཁ (Dzongkha)', 'country' => 'BT', 'flag' => '🇧🇹'],
        
        // Botswana
        'tn' => ['name' => 'Setswana (Tswana)', 'country' => 'BW', 'flag' => '🇧🇼'],
        
        // Belarus
        'be' => ['name' => 'Беларуская (Belarusian)', 'country' => 'BY', 'flag' => '🇧🇾'],
        
        // Canada
        'oj' => ['name' => 'Anishinaabemowin', 'country' => 'CA', 'flag' => '🇨🇦'],
        'cr' => ['name' => 'Nēhiyawēwin (Cree)', 'country' => 'CA', 'flag' => '🇨🇦'],
        'iu' => ['name' => 'ᐃᓄᒃᑎᑐᑦ (Inuktitut)', 'country' => 'CA', 'flag' => '🇨🇦'],
        
        // Congo
        'kg' => ['name' => 'Kikongo', 'country' => 'CD', 'flag' => '🇨🇩'],
        'ln' => ['name' => 'Lingála', 'country' => 'CD', 'flag' => '🇨🇩'],
        'lu' => ['name' => 'Tshiluba', 'country' => 'CD', 'flag' => '🇨🇩'],
        
        // Central African Republic
        'sg' => ['name' => 'Sängö', 'country' => 'CF', 'flag' => '🇨🇫'],
        
        // Switzerland
        'rm' => ['name' => 'Rumantsch (Romansh)', 'country' => 'CH', 'flag' => '🇨🇭'],
        
        // China
        'za' => ['name' => 'Vahcuengh / 話僮 (Zhuang)', 'country' => 'CN', 'flag' => '🇨🇳'],
        'ug' => ['name' => 'ئۇيغۇرچە (Uyghurche / Uyghur)', 'country' => 'CN', 'flag' => '🇨🇳'],
        'bo' => ['name' => 'བོད་སྐད་ (Bod skad / Tibetan)', 'country' => 'CN', 'flag' => '🇨🇳'],
        'ii' => ['name' => 'ꆈꌠ꒿ Nuosuhxop (Yi)', 'country' => 'CN', 'flag' => '🇨🇳'],
        
        // Czech Republic
        'cs' => ['name' => 'Čeština (Czech)', 'country' => 'CZ', 'flag' => '🇨🇿'],
        
        // Germany
        'de' => ['name' => 'Deutsch (German)', 'country' => 'DE', 'flag' => '🇩🇪'],
        
        // Denmark
        'da' => ['name' => 'Dansk (Danish)', 'country' => 'DK', 'flag' => '🇩🇰'],
        
        // Estonia
        'et' => ['name' => 'Eesti (Estonian)', 'country' => 'EE', 'flag' => '🇪🇪'],
        
        // Eritrea
        'ti' => ['name' => 'ትግርኛ (Tigrinya)', 'country' => 'ER', 'flag' => '🇪🇷'],
        
        // Spain
        'an' => ['name' => 'Aragonés', 'country' => 'ES', 'flag' => '🇪🇸'],
        'ca' => ['name' => 'Català (Catalan)', 'country' => 'ES', 'flag' => '🇪🇸'],
        'es' => ['name' => 'Español (Spanish)', 'country' => 'ES', 'flag' => '🇪🇸'],
        'eu' => ['name' => 'Euskara (Basque)', 'country' => 'ES', 'flag' => '🇪🇸'],
        'gl' => ['name' => 'Galego (Galician)', 'country' => 'ES', 'flag' => '🇪🇸'],
        
        // Ethiopia
        'om' => ['name' => 'Afaan Oromoo', 'country' => 'ET', 'flag' => '🇪🇹'],
        'aa' => ['name' => 'Afaraf', 'country' => 'ET', 'flag' => '🇪🇹'],
        'am' => ['name' => 'አማርኛ (Amharic)', 'country' => 'ET', 'flag' => '🇪🇹'],
        
        // Finland
        'fi' => ['name' => 'Suomi (Finnish)', 'country' => 'FI', 'flag' => '🇫🇮'],
        
        // Fiji
        'fj' => ['name' => 'Vakaviti (Fijian)', 'country' => 'FJ', 'flag' => '🇫🇯'],
        
        // Faroe Islands
        'fo' => ['name' => 'Føroyskt (Faroese)', 'country' => 'FO', 'flag' => '🇫🇴'],
        
        // France
        'br' => ['name' => 'Brezhoneg (Breton)', 'country' => 'FR', 'flag' => '🇫🇷'],
        'co' => ['name' => 'Corsu (Corsican)', 'country' => 'FR', 'flag' => '🇫🇷'],
        'fr' => ['name' => 'Français (French)', 'country' => 'FR', 'flag' => '🇫🇷'],
        'oc' => ['name' => 'Occitan', 'country' => 'FR', 'flag' => '🇫🇷'],
        
        // United Kingdom
        'cy' => ['name' => 'Cymraeg (Welsh)', 'country' => 'GB', 'flag' => '🇬🇧'],
        'en' => ['name' => 'English', 'country' => 'GB', 'flag' => '🇬🇧'],
        'gd' => ['name' => 'Gàidhlig (Scottish Gaelic)', 'country' => 'GB', 'flag' => '🇬🇧'],
        'kw' => ['name' => 'Kernewek (Cornish)', 'country' => 'GB', 'flag' => '🇬🇧'],
        
        // Georgia
        'ab' => ['name' => 'Аԥсуа (Abkhazian)', 'country' => 'GE', 'flag' => '🇬🇪'],
        'ka' => ['name' => 'ქართული (Kartuli / Georgian)', 'country' => 'GE', 'flag' => '🇬🇪'],
        
        // Ghana
        'ak' => ['name' => 'Akan / Akanne', 'country' => 'GH', 'flag' => '🇬🇭'],
        'ee' => ['name' => 'Eʋegbe', 'country' => 'GH', 'flag' => '🇬🇭'],
        'tw' => ['name' => 'Twi', 'country' => 'GH', 'flag' => '🇬🇭'],
        
        // Greenland
        'kl' => ['name' => 'Kalaallisut (Greenlandic)', 'country' => 'GL', 'flag' => '🇬🇱'],
        
        // Greece
        'el' => ['name' => 'Ελληνικά (Greek)', 'country' => 'GR', 'flag' => '🇬🇷'],
        
        // Guam
        'ch' => ['name' => 'Chamoru', 'country' => 'GU', 'flag' => '🇬🇺'],
        
        // Croatia
        'hr' => ['name' => 'Hrvatski (Croatian)', 'country' => 'HR', 'flag' => '🇭🇷'],
        
        // Haiti
        'ht' => ['name' => 'Kreyòl Ayisyen (Haitian Creole)', 'country' => 'HT', 'flag' => '🇭🇹'],
        
        // Hungary
        'hu' => ['name' => 'Magyar (Hungarian)', 'country' => 'HU', 'flag' => '🇭🇺'],
        
        // Indonesia
        'id' => ['name' => 'Bahasa Indonesia (Indonesian)', 'country' => 'ID', 'flag' => '🇮🇩'],
        'jv' => ['name' => 'Basa Jawa (Javanese)', 'country' => 'ID', 'flag' => '🇮🇩'],
        'su' => ['name' => 'Basa Sunda (Sundanese)', 'country' => 'ID', 'flag' => '🇮🇩'],
        
        // Ireland
        'ga' => ['name' => 'Gaeilge (Irish)', 'country' => 'IE', 'flag' => '🇮🇪'],
        
        // Israel
        'yi' => ['name' => 'ייִדיש (Yidish / Yiddish)', 'country' => 'IL', 'flag' => '🇮🇱'],
        'he' => ['name' => 'עברית (Ivrit / Hebrew)', 'country' => 'IL', 'flag' => '🇮🇱'],
        
        // Isle of Man
        'gv' => ['name' => 'Gaelg / Gailck (Manx)', 'country' => 'IM', 'flag' => '🇮🇲'],
        
        // India
        'ks' => ['name' => 'कशुर / کٲشُر (Kashmiri)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'bh' => ['name' => 'भोजपुरी (Bhojpuri)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'mr' => ['name' => 'मराठी (Marathi)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'hi' => ['name' => 'हिन्दी (Hindi)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'as' => ['name' => 'অসমীয়া (Asamiya)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'pa' => ['name' => 'ਪੰਜਾਬੀ (Panjabi)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'gu' => ['name' => 'ગુજરાતી (Gujarati)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'or' => ['name' => 'ଓଡ଼ିଆ (Odia)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'ta' => ['name' => 'தமிழ் (Tamil)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'te' => ['name' => 'తెలుగు (Telugu)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'kn' => ['name' => 'ಕನ್ನಡ (Kannada)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'ml' => ['name' => 'മലയാളം (Malayalam)', 'country' => 'IN', 'flag' => '🇮🇳'],
        
        // Iraq
        'ku' => ['name' => 'Kurdî (Kurdish)', 'country' => 'IQ', 'flag' => '🇮🇶'],
        
        // Iran
        'fa' => ['name' => 'فارسی (Fārsi / Persian)', 'country' => 'IR', 'flag' => '🇮🇷'],
        
        // Iceland
        'is' => ['name' => 'Íslenska (Icelandic)', 'country' => 'IS', 'flag' => '🇮🇸'],
        
        // Italy
        'it' => ['name' => 'Italiano (Italian)', 'country' => 'IT', 'flag' => '🇮🇹'],
        'sc' => ['name' => 'Sardu (Sardinian)', 'country' => 'IT', 'flag' => '🇮🇹'],
        
        // Japan
        'ja' => ['name' => '日本語 (Nihongo / Japanese)', 'country' => 'JP', 'flag' => '🇯🇵'],
        
        // Kenya
        'ki' => ['name' => 'Gikuyu', 'country' => 'KE', 'flag' => '🇰🇪'],
        
        // Kyrgyzstan
        'ky' => ['name' => 'Кыргызча (Kyrgyz)', 'country' => 'KG', 'flag' => '🇰🇬'],
        
        // Cambodia
        'km' => ['name' => 'ខ្មែរ (Khmer)', 'country' => 'KH', 'flag' => '🇰🇭'],
        
        // South Korea
        'ko' => ['name' => '한국어 / 조선말 (Korean)', 'country' => 'KR', 'flag' => '🇰🇷'],
        
        // Kazakhstan
        'kk' => ['name' => 'Қазақ тілі (Qazaq / Kazakh)', 'country' => 'KZ', 'flag' => '🇰🇿'],
        
        // Laos
        'lo' => ['name' => 'ລາວ (Lao)', 'country' => 'LA', 'flag' => '🇱🇦'],
        
        // Sri Lanka
        'si' => ['name' => 'සිංහල (Sinhala)', 'country' => 'LK', 'flag' => '🇱🇰'],
        
        // Lesotho
        'st' => ['name' => 'Sesotho', 'country' => 'LS', 'flag' => '🇱🇸'],
        
        // Lithuania
        'lt' => ['name' => 'Lietuvių (Lithuanian)', 'country' => 'LT', 'flag' => '🇱🇹'],
        
        // Luxembourg
        'lb' => ['name' => 'Lëtzebuergesch (Luxembourgish)', 'country' => 'LU', 'flag' => '🇱🇺'],
        
        // Moldova
        'mo' => ['name' => 'Moldovenească (obsolete; now Romanian: Română)', 'country' => 'MD', 'flag' => '🇲🇩'],
        
        // Madagascar
        'mg' => ['name' => 'Malagasy', 'country' => 'MG', 'flag' => '🇲🇬'],
        
        // Marshall Islands
        'mh' => ['name' => 'Kajin M̧ajeļ (Marshallese)', 'country' => 'MH', 'flag' => '🇲🇭'],
        
        // North Macedonia
        'mk' => ['name' => 'Македонски (Macedonian)', 'country' => 'MK', 'flag' => '🇲🇰'],
        
        // Mali
        'bm' => ['name' => 'Bamanankan', 'country' => 'ML', 'flag' => '🇲🇱'],
        
        // Myanmar
        'my' => ['name' => 'မြန်မာစာ (Myanmar)', 'country' => 'MM', 'flag' => '🇲🇲'],
        
        // Mongolia
        'mn' => ['name' => 'Монгол (Mongol)', 'country' => 'MN', 'flag' => '🇲🇳'],
        
        // Malta
        'mt' => ['name' => 'Malti (Maltese)', 'country' => 'MT', 'flag' => '🇲🇹'],
        
        // Maldives
        'dv' => ['name' => 'ދިވެހި (Divehi)', 'country' => 'MV', 'flag' => '🇲🇻'],
        
        // Malawi
        'ny' => ['name' => 'ChiCheŵa (Chichewa)', 'country' => 'MW', 'flag' => '🇲🇼'],
        
        // Malaysia
        'ms' => ['name' => 'Bahasa Melayu (Malay)', 'country' => 'MY', 'flag' => '🇲🇾'],
        
        // Namibia
        'kj' => ['name' => 'Kuanyama / Oshikwanyama', 'country' => 'NA', 'flag' => '🇳🇦'],
        'ng' => ['name' => 'Oshindonga', 'country' => 'NA', 'flag' => '🇳🇦'],
        'hz' => ['name' => 'Otjiherero', 'country' => 'NA', 'flag' => '🇳🇦'],
        
        // Niger
        'kr' => ['name' => 'Kanuri', 'country' => 'NE', 'flag' => '🇳🇪'],
        
        // Nigeria
        'ig' => ['name' => 'Asụsụ Igbo', 'country' => 'NG', 'flag' => '🇳🇬'],
        'ha' => ['name' => 'Hausa', 'country' => 'NG', 'flag' => '🇳🇬'],
        'yo' => ['name' => 'Yorùbá', 'country' => 'NG', 'flag' => '🇳🇬'],
        
        // Netherlands
        'fy' => ['name' => 'Frysk (Frisian)', 'country' => 'NL', 'flag' => '🇳🇱'],
        'li' => ['name' => 'Limburgs', 'country' => 'NL', 'flag' => '🇳🇱'],
        'nl' => ['name' => 'Nederlands (Dutch)', 'country' => 'NL', 'flag' => '🇳🇱'],
        
        // Norway
        'se' => ['name' => 'Davvisámegiella (Northern Sami)', 'country' => 'NO', 'flag' => '🇳🇴'],
        'no' => ['name' => 'Norsk (Norwegian)', 'country' => 'NO', 'flag' => '🇳🇴'],
        'nb' => ['name' => 'Norsk bokmål (Norwegian Bokmål)', 'country' => 'NO', 'flag' => '🇳🇴'],
        'nn' => ['name' => 'Norsk nynorsk (Norwegian Nynorsk)', 'country' => 'NO', 'flag' => '🇳🇴'],
        
        // Nepal
        'ne' => ['name' => 'नेपाली (Nepali)', 'country' => 'NP', 'flag' => '🇳🇵'],
        
        // Nauru
        'na' => ['name' => 'Dorerin Naoero (Nauru)', 'country' => 'NR', 'flag' => '🇳🇷'],
        
        // New Zealand
        'mi' => ['name' => 'Māori', 'country' => 'NZ', 'flag' => '🇳🇿'],
        
        // Peru
        'qu' => ['name' => 'Runa Simi / Kichwa (Quechua)', 'country' => 'PE', 'flag' => '🇵🇪'],
        
        // French Polynesia
        'ty' => ['name' => 'Reo Tahiti (Tahitian)', 'country' => 'PF', 'flag' => '🇵🇫'],
        
        // Papua New Guinea
        'ho' => ['name' => 'Hiri Motu', 'country' => 'PG', 'flag' => '🇵🇬'],
        
        // Philippines
        'ph' => ['name' => 'Filipino (Tagalog)', 'country' => 'PH', 'flag' => '🇵🇭'],
        'tl' => ['name' => 'Tagalog', 'country' => 'PH', 'flag' => '🇵🇭'],
        
        // Pakistan
        'ur' => ['name' => 'اردو (Urdu)', 'country' => 'PK', 'flag' => '🇵🇰'],
        'sd' => ['name' => 'سنڌي / सिन्धी (Sindhi)', 'country' => 'PK', 'flag' => '🇵🇰'],
        
        // Poland
        'pl' => ['name' => 'Polski (Polish)', 'country' => 'PL', 'flag' => '🇵🇱'],
        
        // Portugal
        'pt' => ['name' => 'Português (Portuguese)', 'country' => 'PT', 'flag' => '🇵🇹'],
        
        // Paraguay
        'gn' => ['name' => 'Avañe\'ẽ (Guarani)', 'country' => 'PY', 'flag' => '🇵🇾'],
        
        // Romania
        'ro' => ['name' => 'Română (Romanian)', 'country' => 'RO', 'flag' => '🇷🇴'],
        
        // Serbia
        'sr' => ['name' => 'Српски (Srpski / Serbian)', 'country' => 'RS', 'flag' => '🇷🇸'],
        
        // Russia
        'ba' => ['name' => 'Башҡортса (Bashqortsa)', 'country' => 'RU', 'flag' => '🇷🇺'],
        'os' => ['name' => 'Ирон æвзаг (Ossetian)', 'country' => 'RU', 'flag' => '🇷🇺'],
        'kv' => ['name' => 'Коми кыв (Komi)', 'country' => 'RU', 'flag' => '🇷🇺'],
        'av' => ['name' => 'Магӏарул мацӏ (Avar)', 'country' => 'RU', 'flag' => '🇷🇺'],
        'ce' => ['name' => 'Нохчиј мотт (Chechen)', 'country' => 'RU', 'flag' => '🇷🇺'],
        'ru' => ['name' => 'Русский (Russian)', 'country' => 'RU', 'flag' => '🇷🇺'],
        'tt' => ['name' => 'Татар (Tatar)', 'country' => 'RU', 'flag' => '🇷🇺'],
        'cv' => ['name' => 'Чӑвашла (Chuvash)', 'country' => 'RU', 'flag' => '🇷🇺'],
        
        // Rwanda
        'rw' => ['name' => 'Kinyarwanda', 'country' => 'RW', 'flag' => '🇷🇼'],
        
        // Saudi Arabia
        'ar' => ['name' => 'العربية (Arabic)', 'country' => 'SA', 'flag' => '🇸🇦'],
        
        // Sweden
        'sv' => ['name' => 'Svenska (Swedish)', 'country' => 'SE', 'flag' => '🇸🇪'],
        
        // Slovenia
        'sl' => ['name' => 'Slovenščina (Slovenian)', 'country' => 'SI', 'flag' => '🇸🇮'],
        
        // Slovakia
        'sk' => ['name' => 'Slovenčina (Slovak)', 'country' => 'SK', 'flag' => '🇸🇰'],
        
        // Senegal
        'ff' => ['name' => 'Fulfulde', 'country' => 'SN', 'flag' => '🇸🇳'],
        'wo' => ['name' => 'Wolof', 'country' => 'SN', 'flag' => '🇸🇳'],
        
        // Somalia
        'so' => ['name' => 'Soomaali (Somali)', 'country' => 'SO', 'flag' => '🇸🇴'],
        
        // Eswatini
        'ss' => ['name' => 'SiSwati (Swati)', 'country' => 'SZ', 'flag' => '🇸🇿'],
        
        // Thailand
        'th' => ['name' => 'ไทย (Thai)', 'country' => 'TH', 'flag' => '🇹🇭'],
        
        // Tajikistan
        'tg' => ['name' => 'Тоҷикӣ (Tajik)', 'country' => 'TJ', 'flag' => '🇹🇯'],
        
        // Turkmenistan
        'tk' => ['name' => 'Türkmençe (Turkmen)', 'country' => 'TM', 'flag' => '🇹🇲'],
        
        // Tonga
        'to' => ['name' => 'Lea Fakatonga (Tongan)', 'country' => 'TO', 'flag' => '🇹🇴'],
        
        // Turkey
        'tr' => ['name' => 'Türkçe (Turkish)', 'country' => 'TR', 'flag' => '🇹🇷'],
        
        // Tanzania
        'sw' => ['name' => 'Kiswahili (Swahili)', 'country' => 'TZ', 'flag' => '🇹🇿'],
        
        // Ukraine
        'uk' => ['name' => 'Українська (Ukrainian)', 'country' => 'UA', 'flag' => '🇺🇦'],
        
        // Uganda
        'lg' => ['name' => 'Luganda', 'country' => 'UG', 'flag' => '🇺🇬'],
        
        // United States
        'nv' => ['name' => 'Diné bizaad (Navajo)', 'country' => 'US', 'flag' => '🇺🇸'],
        'ik' => ['name' => 'Iñupiaq', 'country' => 'US', 'flag' => '🇺🇸'],
        
        // Uzbekistan
        'uz' => ['name' => 'O\'zbekcha (Uzbek)', 'country' => 'UZ', 'flag' => '🇺🇿'],
        
        // Vietnam
        'vi' => ['name' => 'Tiếng Việt (Vietnamese)', 'country' => 'VN', 'flag' => '🇻🇳'],
        
        // Vanuatu
        'bi' => ['name' => 'Bislama', 'country' => 'VU', 'flag' => '🇻🇺'],
        
        // Samoa
        'sm' => ['name' => 'Gagana Samoa (Samoan)', 'country' => 'WS', 'flag' => '🇼🇸'],
        
        // South Africa
        'af' => ['name' => 'Afrikaans', 'country' => 'ZA', 'flag' => '🇿🇦'],
        'nr' => ['name' => 'IsiNdebele (South)', 'country' => 'ZA', 'flag' => '🇿🇦'],
        'xh' => ['name' => 'isiXhosa (Xhosa)', 'country' => 'ZA', 'flag' => '🇿🇦'],
        'zu' => ['name' => 'isiZulu (Zulu)', 'country' => 'ZA', 'flag' => '🇿🇦'],
        've' => ['name' => 'Tshivenda (Venda)', 'country' => 'ZA', 'flag' => '🇿🇦'],
        'ts' => ['name' => 'Xitsonga (Tsonga)', 'country' => 'ZA', 'flag' => '🇿🇦'],
        
        // Zimbabwe
        'sn' => ['name' => 'chiShona (Shona)', 'country' => 'ZW', 'flag' => '🇿🇼'],
        'nd' => ['name' => 'IsiNdebele (North)', 'country' => 'ZW', 'flag' => '🇿🇼'],
        
        // International/Constructed Languages
        'vo' => ['name' => 'Volapük', 'country' => 'INTL', 'flag' => '🌐'],
        'sh' => ['name' => 'Srpskohrvatski (obsolete)', 'country' => 'INTL', 'flag' => '🌐'],
        'sa' => ['name' => 'संस्कृतम् (Sanskrit)', 'country' => 'INTL', 'flag' => '🌐'],
        'pi' => ['name' => 'पाळि (Pāli)', 'country' => 'INTL', 'flag' => '🌐'],
        'la' => ['name' => 'Latīna (Latin)', 'country' => 'INTL', 'flag' => '🌐'],
        'io' => ['name' => 'Ido', 'country' => 'INTL', 'flag' => '🌐'],
        'ie' => ['name' => 'Interlingue', 'country' => 'INTL', 'flag' => '🌐'],
        'ia' => ['name' => 'Interlingua', 'country' => 'INTL', 'flag' => '🌐'],
        'eo' => ['name' => 'Esperanto', 'country' => 'INTL', 'flag' => '🌐'],
        'cu' => ['name' => 'Словѣньскъ (Old Church Slavonic)', 'country' => 'INTL', 'flag' => '🌐'],
        'ae' => ['name' => '𐬀𐬎𐬎𐬀𐬯𐬙𐬀 (Avestan)', 'country' => 'INTL', 'flag' => '🌐'],
    ];
}

/**
 * Get popular language codes (most commonly used)
 * @return array Array of popular language codes
 */
function getPopularLanguages(): array {
    return [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'bn' => 'Bengali',
        'id' => 'Indonesian',
        'th' => 'Thai',
        'vi' => 'Vietnamese',
        'tr' => 'Turkish',
        'pl' => 'Polish',
        'nl' => 'Dutch',
        'sv' => 'Swedish',
    ];
}

/**
 * Validate if a language code is supported
 * @param string $code Language code to validate
 * @return bool True if language code is supported
 */
function isValidLanguageCode(string $code): bool {
    $languages = getSupportedLanguages();
    return isset($languages[strtolower($code)]);
}

/**
 * Get language details by code
 * @param string $code Language code
 * @return array|null Language details or null if not found
 */
function getLanguageDetails(string $code): ?array {
    $languages = getSupportedLanguages();
    $code = strtolower($code);
    return $languages[$code] ?? null;
}

// Example Usage:
if (php_sapi_name() !== 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'popular':
            echo json_encode([
                'title' => 'Popular Language Codes',
                'languages' => getPopularLanguages()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'validate':
            $code = $_GET['code'] ?? '';
            $isValid = isValidLanguageCode($code);
            $details = getLanguageDetails($code);
            echo json_encode([
                'code' => $code,
                'valid' => $isValid,
                'details' => $details
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'list':
        default:
            echo json_encode([
                'title' => 'All Supported Language Codes',
                'total' => count(getSupportedLanguages()),
                'note' => 'Language parameter is OPTIONAL - if not provided, default language will be used',
                'languages' => getSupportedLanguages()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
    }
}

?>
