<?php
/**
 * Currency Codes Reference
 * Complete list of currency codes supported by the SoftAPI
 * 
 * Use these currency codes in the 'currency_code' parameter when launching games.
 * The currency parameter is OPTIONAL - if not provided, the default currency will be used.
 * 
 * Usage:
 * $result = launchGame($userId, $balance, $gameUid, 'BDT', 'bn'); // Bangladesh Taka
 */

/**
 * Get all supported currency codes
 * @return array Array of currency codes with details
 */
function getSupportedCurrencies(): array {
    return [
        'AED' => ['name' => 'درهم إماراتي (UAE Dirham)', 'country' => 'AE', 'flag' => '🇦🇪'],
        'AFN' => ['name' => 'افغانۍ (Afghani)', 'country' => 'AF', 'flag' => '🇦🇫'],
        'ALL' => ['name' => 'Lek (Lek)', 'country' => 'AL', 'flag' => '🇦🇱'],
        'AMD' => ['name' => 'Արմենական Դրամ (Armenian Dram)', 'country' => 'AM', 'flag' => '🇦🇲'],
        'ANG' => ['name' => 'Nederlands-Antilliaanse Gulden', 'country' => 'CW', 'flag' => '🇨🇼'],
        'AOA' => ['name' => 'Kwanza', 'country' => 'AO', 'flag' => '🇦🇴'],
        'ARS' => ['name' => 'Peso Argentino', 'country' => 'AR', 'flag' => '🇦🇷'],
        'AUD' => ['name' => 'Australian Dollar', 'country' => 'AU', 'flag' => '🇦🇺'],
        'AWG' => ['name' => 'Arubaanse Florin / Florin Aruba', 'country' => 'AW', 'flag' => '🇦🇼'],
        'AZN' => ['name' => 'Azərbaycan Manatı', 'country' => 'AZ', 'flag' => '🇦🇿'],
        'BAM' => ['name' => 'Konvertibilna Marka', 'country' => 'BA', 'flag' => '🇧🇦'],
        'BBD' => ['name' => 'Barbados Dollar', 'country' => 'BB', 'flag' => '🇧🇧'],
        'BDT' => ['name' => 'টাকা (Taka)', 'country' => 'BD', 'flag' => '🇧🇩'],
        'BGN' => ['name' => 'Български лев (Lev)', 'country' => 'BG', 'flag' => '🇧🇬'],
        'BHD' => ['name' => 'دينار بحريني (Bahraini Dinar)', 'country' => 'BH', 'flag' => '🇧🇭'],
        'BIF' => ['name' => 'Franc Burundais', 'country' => 'BI', 'flag' => '🇧🇮'],
        'BMD' => ['name' => 'Bermudian Dollar', 'country' => 'BM', 'flag' => '🇧🇲'],
        'BND' => ['name' => 'Ringgit Brunei / Brunei Dollar', 'country' => 'BN', 'flag' => '🇧🇳'],
        'BOB' => ['name' => 'Boliviano', 'country' => 'BO', 'flag' => '🇧🇴'],
        'BOV' => ['name' => 'Mvdol', 'country' => 'BO', 'flag' => '🇧🇴'],
        'BRL' => ['name' => 'Real Brasileiro', 'country' => 'BR', 'flag' => '🇧🇷'],
        'BSD' => ['name' => 'Bahamian Dollar', 'country' => 'BS', 'flag' => '🇧🇸'],
        'BTN' => ['name' => 'དངུལ་ཀླད་ (Ngultrum)', 'country' => 'BT', 'flag' => '🇧🇹'],
        'BWP' => ['name' => 'Pula', 'country' => 'BW', 'flag' => '🇧🇼'],
        'BYN' => ['name' => 'Беларуская рубель', 'country' => 'BY', 'flag' => '🇧🇾'],
        'BZD' => ['name' => 'Belize Dollar', 'country' => 'BZ', 'flag' => '🇧🇿'],
        'CAD' => ['name' => 'Canadian Dollar', 'country' => 'CA', 'flag' => '🇨🇦'],
        'CDF' => ['name' => 'Franc Congolais', 'country' => 'CD', 'flag' => '🇨🇩'],
        'CHE' => ['name' => 'WIR-Euro', 'country' => 'CH', 'flag' => '🇨🇭'],
        'CHF' => ['name' => 'Schweizer Franken / Franc Suisse / Franco Svizzero', 'country' => 'CH', 'flag' => '🇨🇭'],
        'CHW' => ['name' => 'WIR-Franken', 'country' => 'CH', 'flag' => '🇨🇭'],
        'CLF' => ['name' => 'Unidad de Fomento', 'country' => 'CL', 'flag' => '🇨🇱'],
        'CLP' => ['name' => 'Peso Chileno', 'country' => 'CL', 'flag' => '🇨🇱'],
        'COP' => ['name' => 'Peso Colombiano', 'country' => 'CO', 'flag' => '🇨🇴'],
        'COU' => ['name' => 'Unidad de Valor Real', 'country' => 'CO', 'flag' => '🇨🇴'],
        'CRC' => ['name' => 'Colón Costarricense', 'country' => 'CR', 'flag' => '🇨🇷'],
        'CUP' => ['name' => 'Peso Cubano', 'country' => 'CU', 'flag' => '🇨🇺'],
        'CVE' => ['name' => 'Escudo Caboverdiano', 'country' => 'CV', 'flag' => '🇨🇻'],
        'CZK' => ['name' => 'Česká koruna', 'country' => 'CZ', 'flag' => '🇨🇿'],
        'DJF' => ['name' => 'Franc Djibouti', 'country' => 'DJ', 'flag' => '🇩🇯'],
        'DKK' => ['name' => 'Dansk krone', 'country' => 'DK', 'flag' => '🇩🇰'],
        'DOP' => ['name' => 'Peso Dominicano', 'country' => 'DO', 'flag' => '🇩🇴'],
        'DZD' => ['name' => 'الدينار الجزائري', 'country' => 'DZ', 'flag' => '🇩🇿'],
        'EGP' => ['name' => 'جنيه مصري', 'country' => 'EG', 'flag' => '🇪🇬'],
        'ERN' => ['name' => 'Nakfa', 'country' => 'ER', 'flag' => '🇪🇷'],
        'ETB' => ['name' => 'ብር (Birr)', 'country' => 'ET', 'flag' => '🇪🇹'],
        'EUR' => ['name' => 'Euro', 'country' => 'EU', 'flag' => '🇪🇺'],
        'FJD' => ['name' => 'Fiji Dollar', 'country' => 'FJ', 'flag' => '🇫🇯'],
        'FKP' => ['name' => 'Falkland Islands Pound', 'country' => 'FK', 'flag' => '🇫🇰'],
        'GBP' => ['name' => 'Pound Sterling', 'country' => 'GB', 'flag' => '🇬🇧'],
        'GEL' => ['name' => 'ქართული ლარი (Lari)', 'country' => 'GE', 'flag' => '🇬🇪'],
        'GHS' => ['name' => 'Ghana Cedi', 'country' => 'GH', 'flag' => '🇬🇭'],
        'GIP' => ['name' => 'Gibraltar Pound', 'country' => 'GI', 'flag' => '🇬🇮'],
        'GMD' => ['name' => 'Dalasi', 'country' => 'GM', 'flag' => '🇬🇲'],
        'GNF' => ['name' => 'Franc Guinéen', 'country' => 'GN', 'flag' => '🇬🇳'],
        'GTQ' => ['name' => 'Quetzal', 'country' => 'GT', 'flag' => '🇬🇹'],
        'GYD' => ['name' => 'Guyana Dollar', 'country' => 'GY', 'flag' => '🇬🇾'],
        'HNL' => ['name' => 'Lempira', 'country' => 'HN', 'flag' => '🇭🇳'],
        'HTG' => ['name' => 'Gourde', 'country' => 'HT', 'flag' => '🇭🇹'],
        'HUF' => ['name' => 'Magyar Forint', 'country' => 'HU', 'flag' => '🇭🇺'],
        'IDR' => ['name' => 'Rupiah', 'country' => 'ID', 'flag' => '🇮🇩'],
        'ILS' => ['name' => 'שקל חדש (Shekel Ḥadash)', 'country' => 'IL', 'flag' => '🇮🇱'],
        'INR' => ['name' => 'भारतीय रुपया (Rupee)', 'country' => 'IN', 'flag' => '🇮🇳'],
        'IQD' => ['name' => 'دينار عراقي', 'country' => 'IQ', 'flag' => '🇮🇶'],
        'IRR' => ['name' => 'ریال ایران', 'country' => 'IR', 'flag' => '🇮🇷'],
        'ISK' => ['name' => 'Íslensk króna', 'country' => 'IS', 'flag' => '🇮🇸'],
        'JMD' => ['name' => 'Jamaican Dollar', 'country' => 'JM', 'flag' => '🇯🇲'],
        'JOD' => ['name' => 'دينار أردني', 'country' => 'JO', 'flag' => '🇯🇴'],
        'JPY' => ['name' => '日本円 (Yen)', 'country' => 'JP', 'flag' => '🇯🇵'],
        'KES' => ['name' => 'Kenyan Shilling', 'country' => 'KE', 'flag' => '🇰🇪'],
        'KGS' => ['name' => 'Кыргыз сом (Som)', 'country' => 'KG', 'flag' => '🇰🇬'],
        'KHR' => ['name' => 'រៀល (Riel)', 'country' => 'KH', 'flag' => '🇰🇭'],
        'KMF' => ['name' => 'Franc Comorien', 'country' => 'KM', 'flag' => '🇰🇲'],
        'KPW' => ['name' => '조선원', 'country' => 'KP', 'flag' => '🇰🇵'],
        'KRW' => ['name' => '원 (Won)', 'country' => 'KR', 'flag' => '🇰🇷'],
        'KWD' => ['name' => 'دينار كويتي', 'country' => 'KW', 'flag' => '🇰🇼'],
        'KYD' => ['name' => 'Cayman Islands Dollar', 'country' => 'KY', 'flag' => '🇰🇾'],
        'KZT' => ['name' => 'Қазақ теңгесі (Tenge)', 'country' => 'KZ', 'flag' => '🇰🇿'],
        'LAK' => ['name' => 'ກີບ (Kip)', 'country' => 'LA', 'flag' => '🇱🇦'],
        'LBP' => ['name' => 'ليرة لبنانية', 'country' => 'LB', 'flag' => '🇱🇧'],
        'LKR' => ['name' => 'ශ්‍රී ලංකා රුපියල් (Rupee)', 'country' => 'LK', 'flag' => '🇱🇰'],
        'LRD' => ['name' => 'Liberian Dollar', 'country' => 'LR', 'flag' => '🇱🇷'],
        'LSL' => ['name' => 'Loti', 'country' => 'LS', 'flag' => '🇱🇸'],
        'LYD' => ['name' => 'دينار ليبي', 'country' => 'LY', 'flag' => '🇱🇾'],
        'MAD' => ['name' => 'درهم مغربي', 'country' => 'MA', 'flag' => '🇲🇦'],
        'MDL' => ['name' => 'Leu Moldovenesc', 'country' => 'MD', 'flag' => '🇲🇩'],
        'MGA' => ['name' => 'Ariary Malagasy', 'country' => 'MG', 'flag' => '🇲🇬'],
        'MKD' => ['name' => 'Македонски денар', 'country' => 'MK', 'flag' => '🇲🇰'],
        'MMK' => ['name' => 'ကျပ် (Kyat)', 'country' => 'MM', 'flag' => '🇲🇲'],
        'MNT' => ['name' => 'Төгрөг (Tögrög)', 'country' => 'MN', 'flag' => '🇲🇳'],
        'MRU' => ['name' => 'أوقية موريتانية (Ouguiya)', 'country' => 'MR', 'flag' => '🇲🇷'],
        'MUR' => ['name' => 'Mauritian Rupee', 'country' => 'MU', 'flag' => '🇲🇺'],
        'MVR' => ['name' => 'ރުފިޔާ (Rufiyaa)', 'country' => 'MV', 'flag' => '🇲🇻'],
        'MWK' => ['name' => 'Kwacha', 'country' => 'MW', 'flag' => '🇲🇼'],
        'MXN' => ['name' => 'Peso Mexicano', 'country' => 'MX', 'flag' => '🇲🇽'],
        'MXV' => ['name' => 'Unidad de Inversión (UDI)', 'country' => 'MX', 'flag' => '🇲🇽'],
        'MYR' => ['name' => 'Ringgit Malaysia', 'country' => 'MY', 'flag' => '🇲🇾'],
        'MZN' => ['name' => 'Metical', 'country' => 'MZ', 'flag' => '🇲🇿'],
        'NAD' => ['name' => 'Namibian Dollar', 'country' => 'NA', 'flag' => '🇳🇦'],
        'NGN' => ['name' => 'Naira', 'country' => 'NG', 'flag' => '🇳🇬'],
        'NIO' => ['name' => 'Córdoba', 'country' => 'NI', 'flag' => '🇳🇮'],
        'NOK' => ['name' => 'Norsk krone', 'country' => 'NO', 'flag' => '🇳🇴'],
        'NPR' => ['name' => 'नेपाली रूपैयाँ', 'country' => 'NP', 'flag' => '🇳🇵'],
        'NZD' => ['name' => 'New Zealand Dollar', 'country' => 'NZ', 'flag' => '🇳🇿'],
        'OMR' => ['name' => 'ريال عماني', 'country' => 'OM', 'flag' => '🇴🇲'],
        'PAB' => ['name' => 'Balboa', 'country' => 'PA', 'flag' => '🇵🇦'],
        'PEN' => ['name' => 'Sol', 'country' => 'PE', 'flag' => '🇵🇪'],
        'PGK' => ['name' => 'Kina', 'country' => 'PG', 'flag' => '🇵🇬'],
        'PHP' => ['name' => 'Peso', 'country' => 'PH', 'flag' => '🇵🇭'],
        'PKR' => ['name' => 'پاکستانی روپیہ', 'country' => 'PK', 'flag' => '🇵🇰'],
        'PLN' => ['name' => 'Złoty', 'country' => 'PL', 'flag' => '🇵🇱'],
        'PYG' => ['name' => 'Guaraní', 'country' => 'PY', 'flag' => '🇵🇾'],
        'QAR' => ['name' => 'ريال قطري', 'country' => 'QA', 'flag' => '🇶🇦'],
        'RON' => ['name' => 'Leu Românesc', 'country' => 'RO', 'flag' => '🇷🇴'],
        'RSD' => ['name' => 'Српски динар', 'country' => 'RS', 'flag' => '🇷🇸'],
        'RUB' => ['name' => 'Российский рубль', 'country' => 'RU', 'flag' => '🇷🇺'],
        'RWF' => ['name' => 'Franc Rwandais', 'country' => 'RW', 'flag' => '🇷🇼'],
        'SAR' => ['name' => 'ريال سعودي', 'country' => 'SA', 'flag' => '🇸🇦'],
        'SBD' => ['name' => 'Solomon Islands Dollar', 'country' => 'SB', 'flag' => '🇸🇧'],
        'SCR' => ['name' => 'Seychelles Rupee', 'country' => 'SC', 'flag' => '🇸🇨'],
        'SDG' => ['name' => 'جنيه سوداني', 'country' => 'SD', 'flag' => '🇸🇩'],
        'SEK' => ['name' => 'Svensk krona', 'country' => 'SE', 'flag' => '🇸🇪'],
        'SGD' => ['name' => 'Singapore Dollar', 'country' => 'SG', 'flag' => '🇸🇬'],
        'SHP' => ['name' => 'Saint Helena Pound', 'country' => 'SH', 'flag' => '🇸🇭'],
        'SLL' => ['name' => 'Leone', 'country' => 'SL', 'flag' => '🇸🇱'],
        'SOS' => ['name' => 'Shilin Soomaaliyeed', 'country' => 'SO', 'flag' => '🇸🇴'],
        'SRD' => ['name' => 'Surinaamse Dollar', 'country' => 'SR', 'flag' => '🇸🇷'],
        'SSP' => ['name' => 'South Sudanese Pound', 'country' => 'SS', 'flag' => '🇸🇸'],
        'STN' => ['name' => 'Dobra', 'country' => 'ST', 'flag' => '🇸🇹'],
        'SYP' => ['name' => 'الليرة السورية', 'country' => 'SY', 'flag' => '🇸🇾'],
        'SZL' => ['name' => 'Lilangeni', 'country' => 'SZ', 'flag' => '🇸🇿'],
        'THB' => ['name' => 'บาทไทย (Baht)', 'country' => 'TH', 'flag' => '🇹🇭'],
        'TJS' => ['name' => 'Сомонӣ', 'country' => 'TJ', 'flag' => '🇹🇯'],
        'TMT' => ['name' => 'Manat', 'country' => 'TM', 'flag' => '🇹🇲'],
        'TND' => ['name' => 'دينار تونسي', 'country' => 'TN', 'flag' => '🇹🇳'],
        'TOP' => ['name' => 'Paʻanga', 'country' => 'TO', 'flag' => '🇹🇴'],
        'TRY' => ['name' => 'Türk Lirası', 'country' => 'TR', 'flag' => '🇹🇷'],
        'TTD' => ['name' => 'Trinidad and Tobago Dollar', 'country' => 'TT', 'flag' => '🇹🇹'],
        'TZS' => ['name' => 'Shilingi ya Tanzania', 'country' => 'TZ', 'flag' => '🇹🇿'],
        'UAH' => ['name' => 'Гривня', 'country' => 'UA', 'flag' => '🇺🇦'],
        'UGX' => ['name' => 'Ugandan Shilling', 'country' => 'UG', 'flag' => '🇺🇬'],
        'USD' => ['name' => 'US Dollar', 'country' => 'US', 'flag' => '🇺🇸'],
        'USN' => ['name' => 'US Dollar (Next day)', 'country' => 'US', 'flag' => '🇺🇸'],
        'UYI' => ['name' => 'Peso Uruguayo en Unidades Indexadas', 'country' => 'UY', 'flag' => '🇺🇾'],
        'UYU' => ['name' => 'Peso Uruguayo', 'country' => 'UY', 'flag' => '🇺🇾'],
        'UZS' => ['name' => 'O\'zbek so\'m', 'country' => 'UZ', 'flag' => '🇺🇿'],
        'VES' => ['name' => 'Bolívar Soberano', 'country' => 'VE', 'flag' => '🇻🇪'],
        'VND' => ['name' => 'đồng', 'country' => 'VN', 'flag' => '🇻🇳'],
        'VUV' => ['name' => 'Vatu', 'country' => 'VU', 'flag' => '🇻🇺'],
        'WST' => ['name' => 'Tala', 'country' => 'WS', 'flag' => '🇼🇸'],
        'XAF' => ['name' => 'Franc CFA', 'country' => 'CM', 'flag' => '🇨🇲'],
        'XAG' => ['name' => 'Silver (ounce)', 'country' => 'INTL', 'flag' => '🪙'],
        'XAU' => ['name' => 'Gold (ounce)', 'country' => 'INTL', 'flag' => '🪙'],
        'XBA' => ['name' => 'EURCO', 'country' => 'INTL', 'flag' => '💱'],
        'XBB' => ['name' => 'EMU', 'country' => 'INTL', 'flag' => '💱'],
        'XBC' => ['name' => 'EUA-9', 'country' => 'INTL', 'flag' => '💱'],
        'XBD' => ['name' => 'EUA-17', 'country' => 'INTL', 'flag' => '💱'],
        'XCD' => ['name' => 'East Caribbean Dollar', 'country' => 'AG', 'flag' => '🇦🇬'],
        'XDR' => ['name' => 'Special Drawing Rights', 'country' => 'INTL', 'flag' => '💱'],
        'XFU' => ['name' => 'UIC Franc', 'country' => 'INTL', 'flag' => '💱'],
        'XOF' => ['name' => 'Franc CFA BCEAO', 'country' => 'SN', 'flag' => '🇸🇳'],
        'XPD' => ['name' => 'Palladium (ounce)', 'country' => 'INTL', 'flag' => '🪙'],
        'XPF' => ['name' => 'Franc Pacifique', 'country' => 'PF', 'flag' => '🇵🇫'],
        'XPT' => ['name' => 'Platinum (ounce)', 'country' => 'INTL', 'flag' => '🪙'],
        'XSU' => ['name' => 'Sucre', 'country' => 'INTL', 'flag' => '💱'],
        'XTS' => ['name' => 'Test Currency Code', 'country' => 'INTL', 'flag' => '🧪'],
        'XUA' => ['name' => 'ADB Unit of Account', 'country' => 'INTL', 'flag' => '💱'],
        'XXX' => ['name' => 'No Currency', 'country' => 'INTL', 'flag' => '❌'],
        'YER' => ['name' => 'ريال يمني', 'country' => 'YE', 'flag' => '🇾🇪'],
        'ZAR' => ['name' => 'Rand', 'country' => 'ZA', 'flag' => '🇿🇦'],
        'ZMW' => ['name' => 'Kwacha', 'country' => 'ZM', 'flag' => '🇿🇲'],
        'ZWG' => ['name' => 'Zimbabwe Gold', 'country' => 'ZW', 'flag' => '🇿🇼'],
        'USDT' => ['name' => 'Tether', 'country' => 'CRYPTO', 'flag' => '₮'],
    ];
}

/**
 * Get popular currency codes (most commonly used)
 * @return array Array of popular currency codes
 */
function getPopularCurrencies(): array {
    return [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'Pound Sterling',
        'JPY' => 'Japanese Yen',
        'CNY' => 'Chinese Yuan',
        'AUD' => 'Australian Dollar',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Swiss Franc',
        'INR' => 'Indian Rupee',
        'BDT' => 'Bangladeshi Taka',
        'PHP' => 'Philippine Peso',
        'THB' => 'Thai Baht',
        'VND' => 'Vietnamese Dong',
        'IDR' => 'Indonesian Rupiah',
        'MYR' => 'Malaysian Ringgit',
        'SGD' => 'Singapore Dollar',
        'KRW' => 'South Korean Won',
        'BRL' => 'Brazilian Real',
        'RUB' => 'Russian Ruble',
        'AED' => 'UAE Dirham',
    ];
}

/**
 * Validate if a currency code is supported
 * @param string $code Currency code to validate
 * @return bool True if currency code is supported
 */
function isValidCurrencyCode(string $code): bool {
    $currencies = getSupportedCurrencies();
    return isset($currencies[strtoupper($code)]);
}

/**
 * Get currency details by code
 * @param string $code Currency code
 * @return array|null Currency details or null if not found
 */
function getCurrencyDetails(string $code): ?array {
    $currencies = getSupportedCurrencies();
    $code = strtoupper($code);
    return $currencies[$code] ?? null;
}

/**
 * Get currencies by country code
 * @param string $countryCode Two-letter country code
 * @return array Array of currencies for the country
 */
function getCurrenciesByCountry(string $countryCode): array {
    $currencies = getSupportedCurrencies();
    $result = [];
    
    foreach ($currencies as $code => $details) {
        if (strtoupper($details['country']) === strtoupper($countryCode)) {
            $result[$code] = $details;
        }
    }
    
    return $result;
}

// Example Usage:
if (php_sapi_name() !== 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'popular':
            echo json_encode([
                'title' => 'Popular Currency Codes',
                'currencies' => getPopularCurrencies()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'validate':
            $code = $_GET['code'] ?? '';
            $isValid = isValidCurrencyCode($code);
            $details = getCurrencyDetails($code);
            echo json_encode([
                'code' => strtoupper($code),
                'valid' => $isValid,
                'details' => $details
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'country':
            $countryCode = $_GET['country'] ?? '';
            $currencies = getCurrenciesByCountry($countryCode);
            echo json_encode([
                'country' => strtoupper($countryCode),
                'currencies' => $currencies
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'list':
        default:
            echo json_encode([
                'title' => 'All Supported Currency Codes',
                'total' => count(getSupportedCurrencies()),
                'note' => 'Currency parameter is OPTIONAL - if not provided, default currency will be used',
                'currencies' => getSupportedCurrencies()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
    }
}

?>
