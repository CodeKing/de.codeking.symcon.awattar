<?php

define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__ . '/libs/helpers/autoload.php');

/**
 * Class aWATTar
 * IP-Symcon aWATTar module
 *
 * @version     0.1
 * @category    Symcon
 * @package     de.codeking.symcon.awattar
 * @author      Frank Herrmann <frank@herrmann.to>
 * @link        https://herrmann.to
 * @link        https://github.com/CodeKing/de.codeking.symcon.awattar
 *
 */
class aWATTar extends Module
{
    use InstanceHelper;

    public $data = [];

    private $api = [
        'DE' => 'https://api.awattar.de/v1/marketdata/current.yaml',
        'AT' => 'https://api.awattar.com/v1/marketdata/current.yaml'
    ];
    private $token;
    private $country;

    protected $name_mappings = [
        'date' => 'Date',
        'price_low' => 'Lowest Price',
        'price_high' => 'Highest Price',
        'price_median' => 'Median Price',
        'price_average' => 'Average Price',
        'price_current' => 'Current Price',
        'price_threshold_00' => 'Price 0-1h',
        'price_threshold_01' => 'Price 1-2h',
        'price_threshold_02' => 'Price 2-3h',
        'price_threshold_03' => 'Price 3-4h',
        'price_threshold_04' => 'Price 4-5h',
        'price_threshold_05' => 'Price 5-6h',
        'price_threshold_06' => 'Price 6-7h',
        'price_threshold_07' => 'Price 7-8h',
        'price_threshold_08' => 'Price 8-9h',
        'price_threshold_09' => 'Price 9-10h',
        'price_threshold_10' => 'Price 10-11h',
        'price_threshold_11' => 'Price 11-12h',
        'price_threshold_12' => 'Price 12-13h',
        'price_threshold_13' => 'Price 13-14h',
        'price_threshold_14' => 'Price 14-15h',
        'price_threshold_15' => 'Price 15-16h',
        'price_threshold_16' => 'Price 16-17h',
        'price_threshold_17' => 'Price 17-18h',
        'price_threshold_18' => 'Price 18-19h',
        'price_threshold_19' => 'Price 19-20h',
        'price_threshold_20' => 'Price 20-21h',
        'price_threshold_21' => 'Price 21-22h',
        'price_threshold_22' => 'Price 22-23h',
        'price_threshold_23' => 'Price 23-24h'
    ];

    protected $profile_mappings = [
        'Date' => '~UnixTimestampDate',
        'Lowest Price' => 'kWhCent',
        'Highest Price' => 'kWhCent',
        'Median Price' => 'kWhCent',
        'Average Price' => 'kWhCent',
        'Current Price' => 'kWhCent',
        'Price 0-1h' => 'kWhCent',
        'Price 1-2h' => 'kWhCent',
        'Price 2-3h' => 'kWhCent',
        'Price 3-4h' => 'kWhCent',
        'Price 4-5h' => 'kWhCent',
        'Price 5-6h' => 'kWhCent',
        'Price 6-7h' => 'kWhCent',
        'Price 7-8h' => 'kWhCent',
        'Price 8-9h' => 'kWhCent',
        'Price 9-10h' => 'kWhCent',
        'Price 10-11h' => 'kWhCent',
        'Price 11-12h' => 'kWhCent',
        'Price 12-13h' => 'kWhCent',
        'Price 13-14h' => 'kWhCent',
        'Price 14-15h' => 'kWhCent',
        'Price 15-16h' => 'kWhCent',
        'Price 16-17h' => 'kWhCent',
        'Price 17-18h' => 'kWhCent',
        'Price 18-19h' => 'kWhCent',
        'Price 19-20h' => 'kWhCent',
        'Price 20-21h' => 'kWhCent',
        'Price 21-22h' => 'kWhCent',
        'Price 22-23h' => 'kWhCent',
        'Price 23-24h' => 'kWhCent'
    ];

    /**
     * create instance
     */
    public function Create()
    {
        parent::Create();

        // register public properties
        $this->RegisterPropertyString('token', '');
        $this->RegisterPropertyString('country', 'DE');

        // register update timer
        $this->RegisterTimer('UpdateData', 0, $this->_getPrefix() . '_Update($_IPS[\'TARGET\']);');
        $this->Update();
    }

    /**
     * run initially update when kernel is ready
     */
    public function onKernelReady()
    {
        $this->Update();
    }

    /**
     * Read config
     */
    private function ReadConfig()
    {
        $this->token = $this->ReadPropertyString('token');
        $this->country = $this->ReadPropertyString('country');
    }

    /**
     * Update & save data
     * @return bool
     */
    public function Update()
    {
        // read config
        $this->ReadConfig();

        // update prices data for next 24h
        if (!$this->UpdatePrices()) {
            return false;
        }

        // save data
        $this->SaveData();


        return true;
    }

    /**
     * Update manually via instance button
     */
    public function UpdateManually()
    {
        if ($this->Update()) {
            echo 'OK';
        }
    }

    /**
     * Update aWATTar prices for the next 24h
     * @return bool
     */
    private function UpdatePrices()
    {
        // get current data
        if ($data = $this->GetData()) {
            // convert yaml to array
            foreach (explode("\n", $data) AS $line) {
                list($key, $value) = explode(':', $line);

                // trim pairs
                $key = trim($key);
                $value = trim($value);

                // convert date
                if ($key == 'date_now_epoch') {
                    $key = 'date';
                    $value = intval($value / 1000);
                }

                // convert price
                if (strstr($key, 'price')) {
                    $value = floatval($value);
                }

                // map names
                if (isset($this->name_mappings[$key])) {
                    $key = $this->name_mappings[$key];
                }

                // append data, if not blacklisted
                if (!strstr($key, 'date_now')
                    && !strstr($key, 'data_price_hour')
                    && !in_array($key, [
                        'date_start',
                        'date_end',
                        'price_unit'
                    ])) {
                    $this->data[$key] = $value;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * save data to variables
     */
    private function SaveData()
    {
        // loop  data and append variables to instance
        $position = 0;
        foreach ($this->data AS $key => $value) {
            $this->CreateVariableByIdentifier([
                'parent_id' => $this->InstanceID,
                'name' => $key,
                'value' => $value,
                'position' => $position
            ]);
            $position++;
        }
    }

    /**
     * get current aWATTar data
     * @return array|bool
     */
    private function GetData()
    {
        // curl options
        $curlOptions = [
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/yaml',
                'User-Agent: IP_Symcon'
            ]
        ];

        // submit token, if available
        if ($this->token) {
            $curlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $curlOptions[CURLOPT_USERPWD] = $this->token;
        }

        // call api
        $ch = curl_init($this->api[$this->country]);
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        curl_close($ch);

        // check & response data
        if ($response && strstr($response, 'date_now')) {
            $this->SetStatus(102);

            $next_timer = strtotime(date('Y-m-d H:00:05', strtotime('+1 hour')));
            $this->SetTimerInterval('UpdateData', $next_timer - time() * 1000); // every hour
            return $response;
        } else {
            $this->SetStatus(200);
            $this->SetTimerInterval('UpdateData', 0); // disable timer
            return false;
        }
    }

    /**
     * create custom variable profile
     * @param string $profile_id
     * @param string $name
     */
    protected function CreateCustomVariableProfile(string $profile_id, string $name)
    {
        switch ($name):
            case 'kWhCent':
                IPS_CreateVariableProfile($profile_id, 2); // float
                IPS_SetVariableProfileDigits($profile_id, 3); // 3 decimals
                IPS_SetVariableProfileText($profile_id, '', ' ct/kWh');
                break;
        endswitch;
    }
}