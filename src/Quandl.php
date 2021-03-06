<?php

namespace Royopa\Quandl;

class Quandl
{
    public $api_key       = null;
    public $format        = null;
    public $cache_handler = null;
    public $was_cached    = false;
    public $force_curl    = false;
    public $no_ssl_verify = false; // disable ssl verification for curl
    public $last_url      = null;
    public $error         = null;

    private static $url_templates = [
        "symbol"  => 'https://www.quandl.com/api/v1/datasets/%s.%s?%s',
        "search"  => 'https://www.quandl.com/api/v1/datasets.%s?%s',
        "list"    => 'https://www.quandl.com/api/v2/datasets.%s?%s',
    ];
    
    public function __construct($api_key = null, $format = 'object')
    {
        $this->api_key = $api_key;
        $this->format  = $format;
    }

    // getSymbol returns data for a given symbol.
    public function getSymbol($symbol, $params = null)
    {
        $url = $this->getUrl(
            'symbol',
            $symbol,
            $this->getFormat(),
            $this->arrangeParams($params)
        );

        return $this->getData($url);
    }

    // getSearch returns results for a search query.
    // CSV output is not supported with this node so if format
    // is set to CSV, the result will fall back to object mode.
    public function getSearch($query, $page = 1, $per_page = 300)
    {
        $params = $this->constructParams($query, $page, $per_page);
        $url    = $this->generateUrl('search', true, $params);

        return $this->getData($url);
    }

    // getList returns the list of symbols for a given source.
    public function getList($query, $page = 1, $per_page = 300)
    {
        $params          = $this->constructParams($query, $page, $per_page);
        $params["query"] = "*";
        $url             = $this->generateUrl('list', false, $params);

        return $this->getData($url);
    }

    //generate the url basead in parameters
    public function generateUrl($type = '', $format = false, $params)
    {
        $url = $this->getUrl(
            $type,
            $this->getFormat($format),
            $this->arrangeParams($params)
        );

        return $url;
    }

    // getFormat returns one of the three formats supported by Quandl.
    // It is here for two reasons: 
    //  1) we also allow 'object' format. this will be sent to Quandl
    //     as "json" but the getData method will return a json_decoded
    //     output.
    //  2) some Quandl nodes do not support CSV (namely search).
    private function getFormat($omit_csv = false)
    {
        if (($this->format == 'csv' && $omit_csv) || $this->format == 'object') {
            return 'json';
        }

        return $this->format;
    }

    // getUrl receives a kind that points to a URL template and 
    // a variable number of parameters, which will be replaced
    // in the template.
    /**
     * @param string $kind
     */
    private function getUrl($kind)
    {
        $template       = self::$url_templates[$kind];
        $args           = array_slice(func_get_args(), 1);
        $this->last_url = trim(vsprintf($template, $args), '?&');
        
        return $this->last_url;
    }

    // getData executes the download operation and returns the result
    // as is, or json-decoded if 'object' type was requested.
    /**
     * @param string $url
     */
    private function getData($url)
    {
        $result = $this->executeDownload($url);
        
        if ($this->format == 'object') {
            return json_decode($result);
        }

        return $result;
    }

    // executeDownload gets a URL, and returns the downloaded document
    // either from cache (if cache_handler is set) or from Quandl.
    private function executeDownload($url)
    {
        if (! $this->cache_handler) {
            $data = $this->download($url);
            return $data;
        }
        
        $data = $this->attemptGetFromCache($url);

        if (!$data) {
            throw new \Exception('Error in download document');
        }

        return $data;
    }

    // attemptGetFromCache is called if a cache_handler is available.
    // It will call the cache handler with a get request, return the 
    // document if found, and will ask it to store the downloaded 
    // object where applicable.
    private function attemptGetFromCache($url)
    {
        $this->was_cached = false;
        $data = call_user_func($this->cache_handler, 'get', $url);
        
        if ($data) {
            $this->was_cached = true;
            return $data;
        }

        $data = $this->download($url);
        
        if ($data) {
            call_user_func($this->cache_handler, 'set', $url, $data);
        }

        return $data;
    }

    // arrangeParams converts a parameters array to a query string.
    // In addition, we add some patches:
    //  1) trim_start and trim_end are converted from any plain
    //     language syntax to Quandl format
    //  2) api_key is appended
    private function arrangeParams($params)
    {
        if ($this->api_key) {
            $params['auth_token'] = $this->api_key;
        }
        
        if (!$params) {
            return $params;   
        }
        
        foreach (['trim_start', 'trim_end'] as $v) {
            if (isset($params[$v])) {
                $params[$v] = self::convertToQuandlDate($params[$v]);
            }
        }
        
        return http_build_query($params);
    }

    // convertToQuandlDate converts any time string supported by
    // PHP (e.g. "today-30 days") to the format needed by Quandl
    private static function convertToQuandlDate($time_str)
    {
        return date('Y-m-d', strtotime($time_str));
    }

    /*
     * download fetches url with file_get_contents or curl fallback
     * You can force curl download by setting force_curl to true.
     * You can disable SSL verification for curl by setting 
     * no_ssl_verify to true (solves "SSL certificate problem")
     */
    private function download($url)
    {
        if (! $this->checkUrl($url)) {
            return false;
        }
        
        if (ini_get('allow_url_fopen') && !$this->force_curl) {
            return $this->getDataWithFileGetContents($url);
        }

        if (!function_exists('curl_version')) {
            $this->error = 'Enable allow_url_fopen or curl';
            return false;
        }

        return $this->getDataWithCurl($url);
    }

    //check url
    private function checkUrl($url)
    {
        $headers_url = get_headers($url);
        $http_code   = $headers_url[0];

        if (strpos($http_code, '404') !== false) {
            $this->error = 'URL not found or invalid URL';
            return false;
        }

        return true;
    }

    //download data with file_get_contents PHP function
    private function getDataWithFileGetContents($url)
    {
        try {
            return file_get_contents($url);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    //download data with file_get_contents PHP function
    private function getDataWithCurl($url)
    {
        $curl = curl_init();
            
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // disable ssl verification for curl
        if ($this->no_ssl_verify) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }
           
        $data  = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if (! $error) {
            return $data;
        }
        
        $this->error = $error;
        
        return false;
    }

    //construct a array with parameters used to query
    private function constructParams($query, $page = 1, $per_page = 300)
    {
        $params = [
            "per_page" => $per_page, 
            "page"     => $page, 
            "query"    => $query,
        ];

        return $params;
    }
}
