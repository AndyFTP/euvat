<?php
namespace App;
use Cache;

/**
 * EU Vat helper functions
 *
 * @package    OC
 * @category   Helper
 * @author     Chema <chema@open-classifieds.com>
 * @copyright  (c) 2009-2016 Open Classifieds Team
 * @license    GPL v3
 */

class euvat {

    
    /**
     * gets the country code from the user
     * @param  string $ip_address 
     * @param boolean $force_geioip, forces the usage of geoip not cloudflare
     * @return string             
     */
    public static function country_code($ip_address=NULL, $force_geoip = FALSE)
    {
        //cloudflare installed? no ip?
        if ($ip_address===NULL AND !empty($_SERVER["HTTP_CF_IPCOUNTRY"]) AND !$force_geoip )
        {
            $country_code = $_SERVER["HTTP_CF_IPCOUNTRY"];
        }
        //no cloudflare installed or forced try geoip
        else
        {
            if ($ip_address===NULL)
                $ip_address = Request::$client_ip;

            $country_code = Geoip3::instance()->country_code($ip_address);
        }

        return $country_code;
    }



    /**
     * get country name from a country code, in case not set get by ip
     * @param  string $country_code [description]
     * @param  string $ip_address 
     * @return string
     */
    public static function country_name($country_code=NULL, $ip_address=NULL)
    {
        if ($country_code===NULL OR empty($country_code))
            $country_code = self::country_code($ip_address);

        return (isset(self::$countries[$country_code]))?self::$countries[$country_code]:NULL;
    }

    /**
     * verifies vies number
     * @param  string $vat_number   
     * @param  string $country_code 
     * @return boolean               
     */
    public static function check($vat_number,$country_code=NULL)
    {
        $result = self::companyInfo($vat_number,$country_code)['valid'];
        return [ 'valid' => $result ];
    }

    
    public static function companyInfo($vat_number,$country_code=NULL)
    {
        if ($country_code === NULL)
        {
            //try extract from VAT TODO
        }

        $country_code = strtoupper($country_code);

        //first check if country is part of EU 
        if ( strlen($country_code)==2 AND strlen($vat_number)>=4 AND (array_key_exists($country_code, self::get_vat_rates())) )
        {
            //USAGE of APC cache!!
            $key = 'companyInfo::'.$country_code.$vat_number;

            if ( ($result = Cache::get($key)) == NULL )
            {
                $client = new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
                $result = $client->checkVatApprox(array('countryCode' => $country_code,'vatNumber' => $vat_number));
                Cache::put($key,$result,30*24*60);//30 days cached
            }
            
            
            //valid
            if ($result->valid == TRUE)
            {
                //cast result to array
                $result = (array) $result;
                unset($result['requestIdentifier']);

                //get vat rates
                $vatRate = self::get_vat_rates()[$country_code];
                unset($vatRate['_comment']);
                unset($vatRate['country']);
                unset($vatRate['iso_duplicate_of']);
                $result['vatRate'] = $vatRate;

                return $result;
            }
        }        

        return ['valid'=>'false'];
    }

    /**
     * its a valid vat country?
     * @param  strinf  $country_code 
     * @return boolean               
     */
    public static function is_eu_country($country_code=NULL)
    {   
        if ($country_code===NULL OR empty($country_code))
            $country_code = self::country_code();

        $country_code = strtoupper($country_code);

        $eu_rate = self::get_vat_rates();

        return isset($eu_rate[$country_code]);
    }

    /**
     * get VAT for the copuntry
     * @param  strinf  $country_code 
     * @return integer               
     */
    public static function vat_by_country($country_code)
    {
        $eu_rate = self::get_vat_rates();

        $country_code = strtoupper($country_code);

        if ( isset($eu_rate[$country_code]) AND  is_numeric($eu_rate[$country_code]['standard_rate']) )
            return $eu_rate[$country_code]['standard_rate'];
        else 
            return FALSE;
    }


    /**
     * 
     * @param  boolean $reload  
     * @return void
     */
    public static function get_vat_rates($reload = FALSE)
    {
        //we check the date of our local versions.php
        $vat_rates_file = realpath(dirname(__FILE__)).'/../public/rates.json';

        //if older than a month or ?reload=1 force reload
        if (!file_exists($vat_rates_file) OR time() > strtotime('+1 month',filemtime($vat_rates_file)) OR $reload === TRUE )
        {
            //read from external source http://wceuvatcompliance.s3.amazonaws.com/rates.json OR https://euvatrates.com/rates.json
            $file = file_get_contents('https://euvatrates.com/rates.json?r='.time());
            
            if ($file!==NULL)
                file_put_contents($vat_rates_file, $file);
        }

        //return only the rates
        $rates = json_decode(file_get_contents($vat_rates_file),TRUE);

        return $rates['rates'];
    }


    /**
     * Array of country codes (ISO 3166-1 alpha-2) and corresponding names
     * @see https://gist.github.com/vxnick/380904
     * @var array
     */
    public static $countries = array
    (
    'AF' => 'Afghanistan',
    'AX' => 'Aland Islands',
    'AL' => 'Albania',
    'DZ' => 'Algeria',
    'AS' => 'American Samoa',
    'AD' => 'Andorra',
    'AO' => 'Angola',
    'AI' => 'Anguilla',
    'AQ' => 'Antarctica',
    'AG' => 'Antigua And Barbuda',
    'AR' => 'Argentina',
    'AM' => 'Armenia',
    'AW' => 'Aruba',
    'AU' => 'Australia',
    'AT' => 'Austria',
    'AZ' => 'Azerbaijan',
    'BS' => 'Bahamas',
    'BH' => 'Bahrain',
    'BD' => 'Bangladesh',
    'BB' => 'Barbados',
    'BY' => 'Belarus',
    'BE' => 'Belgium',
    'BZ' => 'Belize',
    'BJ' => 'Benin',
    'BM' => 'Bermuda',
    'BT' => 'Bhutan',
    'BO' => 'Bolivia',
    'BA' => 'Bosnia And Herzegovina',
    'BW' => 'Botswana',
    'BV' => 'Bouvet Island',
    'BR' => 'Brazil',
    'IO' => 'British Indian Ocean Territory',
    'BN' => 'Brunei Darussalam',
    'BG' => 'Bulgaria',
    'BF' => 'Burkina Faso',
    'BI' => 'Burundi',
    'KH' => 'Cambodia',
    'CM' => 'Cameroon',
    'CA' => 'Canada',
    'CV' => 'Cape Verde',
    'KY' => 'Cayman Islands',
    'CF' => 'Central African Republic',
    'TD' => 'Chad',
    'CL' => 'Chile',
    'CN' => 'China',
    'CX' => 'Christmas Island',
    'CC' => 'Cocos (Keeling) Islands',
    'CO' => 'Colombia',
    'KM' => 'Comoros',
    'CG' => 'Congo',
    'CD' => 'Congo, Democratic Republic',
    'CK' => 'Cook Islands',
    'CR' => 'Costa Rica',
    'CI' => 'Cote D\'Ivoire',
    'HR' => 'Croatia',
    'CU' => 'Cuba',
    'CY' => 'Cyprus',
    'CZ' => 'Czech Republic',
    'DK' => 'Denmark',
    'DJ' => 'Djibouti',
    'DM' => 'Dominica',
    'DO' => 'Dominican Republic',
    'EC' => 'Ecuador',
    'EG' => 'Egypt',
    'SV' => 'El Salvador',
    'GQ' => 'Equatorial Guinea',
    'ER' => 'Eritrea',
    'EE' => 'Estonia',
    'ET' => 'Ethiopia',
    'FK' => 'Falkland Islands (Malvinas)',
    'FO' => 'Faroe Islands',
    'FJ' => 'Fiji',
    'FI' => 'Finland',
    'FR' => 'France',
    'GF' => 'French Guiana',
    'PF' => 'French Polynesia',
    'TF' => 'French Southern Territories',
    'GA' => 'Gabon',
    'GM' => 'Gambia',
    'GE' => 'Georgia',
    'DE' => 'Germany',
    'GH' => 'Ghana',
    'GI' => 'Gibraltar',
    'GR' => 'Greece',
    'GL' => 'Greenland',
    'GD' => 'Grenada',
    'GP' => 'Guadeloupe',
    'GU' => 'Guam',
    'GT' => 'Guatemala',
    'GG' => 'Guernsey',
    'GN' => 'Guinea',
    'GW' => 'Guinea-Bissau',
    'GY' => 'Guyana',
    'HT' => 'Haiti',
    'HM' => 'Heard Island & Mcdonald Islands',
    'VA' => 'Holy See (Vatican City State)',
    'HN' => 'Honduras',
    'HK' => 'Hong Kong',
    'HU' => 'Hungary',
    'IS' => 'Iceland',
    'IN' => 'India',
    'ID' => 'Indonesia',
    'IR' => 'Iran, Islamic Republic Of',
    'IQ' => 'Iraq',
    'IE' => 'Ireland',
    'IM' => 'Isle Of Man',
    'IL' => 'Israel',
    'IT' => 'Italy',
    'JM' => 'Jamaica',
    'JP' => 'Japan',
    'JE' => 'Jersey',
    'JO' => 'Jordan',
    'KZ' => 'Kazakhstan',
    'KE' => 'Kenya',
    'KI' => 'Kiribati',
    'KR' => 'Korea',
    'KW' => 'Kuwait',
    'KG' => 'Kyrgyzstan',
    'LA' => 'Lao People\'s Democratic Republic',
    'LV' => 'Latvia',
    'LB' => 'Lebanon',
    'LS' => 'Lesotho',
    'LR' => 'Liberia',
    'LY' => 'Libyan Arab Jamahiriya',
    'LI' => 'Liechtenstein',
    'LT' => 'Lithuania',
    'LU' => 'Luxembourg',
    'MO' => 'Macao',
    'MK' => 'Macedonia',
    'MG' => 'Madagascar',
    'MW' => 'Malawi',
    'MY' => 'Malaysia',
    'MV' => 'Maldives',
    'ML' => 'Mali',
    'MT' => 'Malta',
    'MH' => 'Marshall Islands',
    'MQ' => 'Martinique',
    'MR' => 'Mauritania',
    'MU' => 'Mauritius',
    'YT' => 'Mayotte',
    'MX' => 'Mexico',
    'FM' => 'Micronesia, Federated States Of',
    'MD' => 'Moldova',
    'MC' => 'Monaco',
    'MN' => 'Mongolia',
    'ME' => 'Montenegro',
    'MS' => 'Montserrat',
    'MA' => 'Morocco',
    'MZ' => 'Mozambique',
    'MM' => 'Myanmar',
    'NA' => 'Namibia',
    'NR' => 'Nauru',
    'NP' => 'Nepal',
    'NL' => 'Netherlands',
    'AN' => 'Netherlands Antilles',
    'NC' => 'New Caledonia',
    'NZ' => 'New Zealand',
    'NI' => 'Nicaragua',
    'NE' => 'Niger',
    'NG' => 'Nigeria',
    'NU' => 'Niue',
    'NF' => 'Norfolk Island',
    'MP' => 'Northern Mariana Islands',
    'NO' => 'Norway',
    'OM' => 'Oman',
    'PK' => 'Pakistan',
    'PW' => 'Palau',
    'PS' => 'Palestinian Territory, Occupied',
    'PA' => 'Panama',
    'PG' => 'Papua New Guinea',
    'PY' => 'Paraguay',
    'PE' => 'Peru',
    'PH' => 'Philippines',
    'PN' => 'Pitcairn',
    'PL' => 'Poland',
    'PT' => 'Portugal',
    'PR' => 'Puerto Rico',
    'QA' => 'Qatar',
    'RE' => 'Reunion',
    'RO' => 'Romania',
    'RU' => 'Russian Federation',
    'RW' => 'Rwanda',
    'BL' => 'Saint Barthelemy',
    'SH' => 'Saint Helena',
    'KN' => 'Saint Kitts And Nevis',
    'LC' => 'Saint Lucia',
    'MF' => 'Saint Martin',
    'PM' => 'Saint Pierre And Miquelon',
    'VC' => 'Saint Vincent And Grenadines',
    'WS' => 'Samoa',
    'SM' => 'San Marino',
    'ST' => 'Sao Tome And Principe',
    'SA' => 'Saudi Arabia',
    'SN' => 'Senegal',
    'RS' => 'Serbia',
    'SC' => 'Seychelles',
    'SL' => 'Sierra Leone',
    'SG' => 'Singapore',
    'SK' => 'Slovakia',
    'SI' => 'Slovenia',
    'SB' => 'Solomon Islands',
    'SO' => 'Somalia',
    'ZA' => 'South Africa',
    'GS' => 'South Georgia And Sandwich Isl.',
    'ES' => 'Spain',
    'LK' => 'Sri Lanka',
    'SD' => 'Sudan',
    'SR' => 'Suriname',
    'SJ' => 'Svalbard And Jan Mayen',
    'SZ' => 'Swaziland',
    'SE' => 'Sweden',
    'CH' => 'Switzerland',
    'SY' => 'Syrian Arab Republic',
    'TW' => 'Taiwan',
    'TJ' => 'Tajikistan',
    'TZ' => 'Tanzania',
    'TH' => 'Thailand',
    'TL' => 'Timor-Leste',
    'TG' => 'Togo',
    'TK' => 'Tokelau',
    'TO' => 'Tonga',
    'TT' => 'Trinidad And Tobago',
    'TN' => 'Tunisia',
    'TR' => 'Turkey',
    'TM' => 'Turkmenistan',
    'TC' => 'Turks And Caicos Islands',
    'TV' => 'Tuvalu',
    'UG' => 'Uganda',
    'UA' => 'Ukraine',
    'AE' => 'United Arab Emirates',
    'GB' => 'United Kingdom',
    'US' => 'United States',
    'UM' => 'United States Outlying Islands',
    'UY' => 'Uruguay',
    'UZ' => 'Uzbekistan',
    'VU' => 'Vanuatu',
    'VE' => 'Venezuela',
    'VN' => 'Viet Nam',
    'VG' => 'Virgin Islands, British',
    'VI' => 'Virgin Islands, U.S.',
    'WF' => 'Wallis And Futuna',
    'EH' => 'Western Sahara',
    'YE' => 'Yemen',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabwe',
    );


  
    
}
