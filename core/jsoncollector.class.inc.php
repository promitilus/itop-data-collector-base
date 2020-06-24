<?php

// Copyright (C) 2014-2020 Combodo SARL
//
//   This application is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with this application. If not, see <http://www.gnu.org/licenses/>

abstract class JsonCollector extends Collector
{
    protected $oFileJson;
    protected $aJson;
    protected $sURL;
    protected $bTT;
    protected $aJsonKey;
    protected $aFieldsKey;

    /**
     * Initalization
     */
    public function __construct()
    {
        parent::__construct();
        $this->oFileJson = null;
        $this->sURL = null;
        $this->aJson = null;
        $this->bTT = false;
        $this->aFieldsKey = null;
    }

    /**
     * Runs the configured query to start fetching the data from the database
     * @see Collector::Prepare()
     */
    public function Prepare()
    {
        $bRet = parent::Prepare();
        if (!$bRet) return false;

        // Read the SQL query from the configuration
        $this->sURL = Utils::GetConfigurationValue(get_class($this)."_jsonurl", '');
        if ($this->sURL == '')
        {
            // Try all lowercase
            $this->sURL = Utils::GetConfigurationValue(strtolower(get_class($this))."_jsonurl", '');
        }
        $isTrueUrl=true;
        if ($this->sURL == '') {
            Utils::Log(LOG_INFO, "Ll");
            $this->sURL = Utils::GetConfigurationValue(get_class($this) . "_jsonway", '');
            if ($this->sURL == '')
            {
                // Try all lowercase
                $this->sURL = Utils::GetConfigurationValue(strtolower(get_class($this))."_jsonway", '');
            }
            if ($this->sURL != '')
            {
                Utils::Log(LOG_INFO, "false");
                $isTrueUrl=false;
            }
        }
        if ($this->sURL == '')
        {
            // No query at all !!
            Utils::Log(LOG_ERR, "[".get_class($this)."] no json URL or way configured! Cannot collect data. The query was expected to be configured as '".strtolower(get_class($this))."_jsonurl' or '".strtolower(get_class($this))."_jsonway' in the configuration file.");
            return false;
        }
        if ($isTrueUrl) {
            $aDataGet = Utils::GetConfigurationValue(strtolower(get_class($this)) . '_jsonpost', array());
            Utils::Log(LOG_INFO, "Uploading data file " . json_encode($aDataGet));
            $iSynchroTimeout = (int)Utils::GetConfigurationValue('itop_synchro_timeout', 600); // timeout in seconds, for a synchro to run

            $aResponseHeaders = null;

            $aRawCurlOptions = Utils::GetConfigurationValue('curl_options', array(CURLOPT_SSLVERSION => CURL_SSLVERSION_SSLv3));
            $aCurlOptions = array();
            foreach ($aRawCurlOptions as $key => $value) {
                // Convert strings like 'CURLOPT_SSLVERSION' to the value of the corresponding define i.e CURLOPT_SSLVERSION = 32 !
                $iKey = (!is_numeric($key)) ? constant((string)$key) : (int)$key;
                $iValue = (!is_numeric($value)) ? constant((string)$value) : (int)$value;
                $aCurlOptions[$iKey] = $iValue;
            }
            $aCurlOptions[CURLOPT_CONNECTTIMEOUT] = $iSynchroTimeout;
            $aCurlOptions[CURLOPT_TIMEOUT] = $iSynchroTimeout;

            //logs
            Utils::Log(LOG_INFO, "synchro url: " . $this->sURL);
            Utils::Log(LOG_INFO, "synchro aDataGet: " . json_encode($aDataGet));
            $this->oFileJson = Utils::DoPostRequest($this->sURL, $aDataGet, null, $aResponseHeaders, $aCurlOptions);
            Utils::Log(LOG_INFO, "synchro oFileJson: " . $this->oFileJson);
        }
        else
        {
            $this->oFileJson = file_get_contents($this->sURL);
        }
        /*************************************/
        if ($this->oFileJson === false)
        {
            $aInfo = $this->sURL->errorInfo();
            Utils::Log(LOG_ERR, "[".get_class($this)."] Failed to get JSON file: '$this->sURL'. Reason: ".$aInfo[0].', '.$aInfo[2]);
            return false;
        }
        $this->aJson = json_decode($this->oFileJson, true);
        if ($this->aJson == null)
        {
            $aInfo = $this->oFileJson->errorInfo();
            Utils::Log(LOG_ERR, "[".get_class($this)."] Failed to translate data from JSON file: '$this->sURL'. Reason: ".$aInfo[0].', '.$aInfo[2]);
            return false;
        }
        $aWay=explode ( '/' , Utils::GetConfigurationValue(strtolower(get_class($this))."_way", ''));

        Utils::Log(LOG_DEBUG, json_encode($this->aJson));
        Utils::Log(LOG_DEBUG, '**************');

        //Get table of Element in JSON file with a specific way

        foreach ($aWay as $sTag){
            Utils::Log(LOG_DEBUG, "tag: ".$sTag);
            if (!array_key_exists (0, $this->aJson) && $sTag != '*')
            {
                $this->aJson=$this->aJson[$sTag];
            }
            else
            {
                $aJsonNew=array();
                foreach ($this->aJson as $aElement)
                {
                    if ($sTag == '*') //Any tag
                    {
                        array_push($aJsonNew, $aElement);
                    }
                    else
                    {
                        array_push($aJsonNew, $aElement[$sTag]);
                    }
                }
                $this->aJson=$aJsonNew;
            }

            //$this->aJson=$this->aJson[$sCode];
            if ($this->aJson == null)
            {
                $aInfo = Utils::GetConfigurationValue(strtolower(get_class($this))."_way", '');
                Utils::Log(LOG_ERR, "[".get_class($this)."] Failed to find way '.$aInfo.' until data in json file: '$this->sURL'.");
                return false;
            }
        }
        $this->aJsonKey = array_keys($this->aJson);
        $this->aFieldsKey = Utils::GetConfigurationValue(strtolower(get_class($this)).'_fields', array());
        Utils::Log(LOG_DEBUG, "aFieldsKey: ". json_encode($this->aFieldsKey));
        Utils::Log(LOG_DEBUG, "aJson: ".json_encode($this->aJson));
        Utils::Log(LOG_DEBUG, "aJsonKey: ".json_encode($this->aJsonKey));
        Utils::Log(LOG_DEBUG, "taille tableau:". count($this->aJson));

        $this->idx = 0;
        return true;
    }

    /**
     * Fetch one row of data from the database
     * The first row is used to check if the columns of the result match the expected "fields"
     * @see Collector::Fetch()
     */
    public function Fetch()
    {
        if ($this->idx < count($this->aJson))
        {
            $aData = $this->aJson[$this->aJsonKey[$this->idx]];
            Utils::Log(LOG_DEBUG, '$aData: '.json_encode($aData));

            $aCurlOptions=array();
            foreach($aData as $key => $value)
            {
                if ($this->idx == 0)
                {
                    Utils::Log(LOG_DEBUG, $key.":". array_search($key,$this->aFieldsKey));
                }
                $aCurlOptions[array_search($key,$this->aFieldsKey)] = $value;
            }

            Utils::Log(LOG_DEBUG, '$aCurlOptions: '.json_encode($aCurlOptions));

            foreach($this->aSkippedAttributes as $sCode)
            {
                unset($aCurlOptions[$sCode]);
            }

            if ($this->idx == 0)
            {
                $aChecks = $this->CheckJSONData($aCurlOptions);
                foreach($aChecks['errors'] as $sError)
                {
                    Utils::Log(LOG_ERR, "[".get_class($this)."] $sError");
                }
                foreach($aChecks['warnings'] as $sWarning)
                {
                    Utils::Log(LOG_WARNING, "[".get_class($this)."] $sWarning");
                }
                if(count($aChecks['errors']) > 0)
                {
                    throw new Exception("Missing columns in the Json file.");
                }
            }
            $this->idx++;
            return $aCurlOptions;
        }
        return false;
    }

    /**
     * Determine if a given attribute is allowed to be missing in the data datamodel.
     *
     * The implementation is based on a predefined configuration parameter named from the
     * class of the collector (all lowercase) with _ignored_attributes appended.
     *
     * Example: here is the configuration to "ignore" the attribute 'location_id' for the class MySQLCollector:
     * <mysqlcollector_ignored_attributes type="array">
     *   	<attribute>location_id</attribute>
     * </mysqlcollector_ignored_attributes>
     * @param string $sAttCode
     * @return boolean True if the attribute can be skipped, false otherwise
     */
    public function AttributeIsOptional($sAttCode)
    {
        $aIgnoredAttributes = Utils::GetConfigurationValue(get_class($this)."_ignored_attributes", null);
        if ($aIgnoredAttributes === null)
        {
            // Try all lowercase
            $aIgnoredAttributes = Utils::GetConfigurationValue(strtolower(get_class($this))."_ignored_attributes", null);
        }
        if (is_array($aIgnoredAttributes))
        {
            if (in_array($sAttCode, $aIgnoredAttributes)) return true;
        }

        return parent::AttributeIsOptional($sAttCode);
    }

    /**
     * Check if the keys of the supplied hash array match the expected fields
     * @param array $aData
     * @return array A hash array with two entries: 'errors' => array of strings and 'warnings' => array of strings
     */
    protected function CheckJSONData($aData)
    {
        $aRet = array('errors' => array(), 'warnings' => array());

        if(!array_key_exists('primary_key', $aData))
        {
            $aRet['errors'][] = 'The mandatory column "primary_key" is missing from the query.';
        }
        foreach($this->aFields as $sCode => $aDefs)
        {
            // Check for missing columns
            if (!array_key_exists($sCode, $aData) && $aDefs['reconcile'])
            {
                $aRet['errors'][] = 'The column "'.$sCode.'", used for reconciliation, is missing from the query.';
            }
            else if (!array_key_exists($sCode, $aData) && $aDefs['update'])
            {
                $aRet['errors'][] = 'The column "'.$sCode.'", used for update, is missing from the query.';
            }

            // Check for useless columns
            if (array_key_exists($sCode, $aData) && !$aDefs['reconcile']  && !$aDefs['update'])
            {
                $aRet['warnings'][] = 'The column "'.$sCode.'" is used neither for update nor for reconciliation.';
            }

        }
        return $aRet;
    }
}