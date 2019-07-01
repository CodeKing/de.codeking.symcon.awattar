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
        'DE' => 'https://api.awattar.de/v1/marketdata',
        'AT' => 'https://api.awattar.com/v1/marketdata'
    ];

    private $token;
    private $country;

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
        // check configuration data
        $validConfig = $this->ReadConfig();

        // update timer
        if ($validConfig) {
            $this->SetTimerInterval('UpdateData', 3600 * 1000);
            $this->Update();
        }
    }

    /**
     * Read config
     * @return bool
     */
    private function ReadConfig(): bool
    {
        $this->token = $this->ReadPropertyString('token');
        $this->country = $this->ReadPropertyString('country');

        return $this->country == 'DE' || ($this->token && $this->country == 'AT');
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
        } else {
            echo 'Error';
        }
    }

    /**
     * Update aWATTar prices for the next 24h
     * @return bool
     */
    private function UpdatePrices()
    {
        // get current data
        if ($json_data = $this->GetData()) {
            $this->data = [
                'Date' => time(),
                'Lowest Price' => 9999999999999,
                'Highest Price' => 0,
                'Median Price' => '',
                'Average Price' => '',
                'Current Price' => 0
            ];

            $prices = [];
            foreach ($json_data AS $item) {
                // convert price to kWh
                $price = $item['marketprice'] / 10;

                // get start / end hour
                $hour_start = date('G', $item['start_timestamp'] / 1000);
                $hour_end = date('G', $item['end_timestamp'] / 1000);
                if ($hour_end == 0) {
                    $hour_end = 24;
                }

                // build price key
                $key = 'Price ' . $hour_start . '-' . $hour_end . 'h';

                // add data
                $this->data[$key] = $price;
                $prices[] = $price;

                // calculate lowest price
                if ($price < $this->data['Lowest Price']) {
                    $this->data['Lowest Price'] = $price;
                }

                // calculate highest price
                if ($price > $this->data['Highest Price']) {
                    $this->data['Highest Price'] = $price;
                }

                // calculate current price
                if ($hour_start == date('G')) {
                    $this->data['Current Price'] = $price;

                }
            }

            // calculate average price
            $this->data['Average Price'] = round(array_sum($prices) / count($prices), 4);

            // calculate median price
            $this->data['Median Price'] = $this->_calculateMedianPrice($prices);

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
        // build params
        $params = [
            'start' => strtotime(date('d.m.Y 00:00:00')) * 1000
        ];

        // curl options
        $curlOptions = [
            CURLOPT_TIMEOUT => 60,
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
        $ch = curl_init($this->api[$this->country] . '?' . http_build_query($params));
        curl_setopt_array($ch, $curlOptions);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        // check & response data
        if ($response && json_last_error() == JSON_ERROR_NONE) {
            $this->SetStatus(102);

            $next_timer = strtotime(date('Y-m-d H:00:10', strtotime('+1 hour')));
            $this->SetTimerInterval('UpdateData', ($next_timer - time()) * 1000); // every hour
            return isset($response['data']) ? $response['data'] : [];;
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

    /**
     * calculate median price
     * @param array $arr
     * @return float|int|null
     */
    protected function _calculateMedianPrice(array $arr)
    {
        if (0 === count($arr)) {
            return null;
        }

        // sort the data
        $count = count($arr);
        asort($arr);

        // get the mid-point keys (1 or 2 of them)
        $mid = floor(($count - 1) / 2);
        $keys = array_slice(array_keys($arr), $mid, (1 === $count % 2 ? 1 : 2));
        $sum = 0;
        foreach ($keys as $key) {
            $sum += $arr[$key];
        }
        return $sum / count($keys);
    }
}