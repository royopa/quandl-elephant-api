<?php
//--------------------------------------------------------------
// Examples: Quandl API
//--------------------------------------------------------------
require "./vendor/autoload.php";

if (file_exists(__DIR__.'/.env')) {
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();
}

Kint::enabled(true);

$api_key = getenv('QUANDL_API_KEY');
$symbol  = "GOOG/NASDAQ_AAPL";

// Modify this call to check different samples
$data = example1($api_key, $symbol);
d($data);

// Example 1: Hello Quandl
function example1($api_key, $symbol)
{
    $quandl = new Royopa\Quandl\Quandl();
    return $quandl->getSymbol($symbol);
}

// Example 2: API Key + JSON
function example2($api_key, $symbol) {
    $quandl = new Royopa\Quandl\Quandl($api_key);
    $quandl->format = "json";
    return $quandl->getSymbol($symbol);
}

// Example 3: Date Range + Last URL
function example3($api_key, $symbol)
{
    $quandl = new Royopa\Quandl\Quandl($api_key);
    print $quandl->last_url;
    return $quandl->getSymbol($symbol, [
        "trim_start" => "today-30 days",
        "trim_end"   => "today",
    ]);
}

// Example 4: CSV + More parameters
function example4($api_key, $symbol)
{
    $quandl = new Royopa\Quandl\Quandl($api_key, "csv");
    return $quandl->getSymbol($symbol, [
        "sort_order"      => "desc", // asc|desc
        "exclude_headers" => true,
        "rows"            => 10,
        "column"          => 4, // 4 = close price
    ]);
}

// Example 5: XML + Frequency
function example5($api_key, $symbol)
{
    $quandl = new Royopa\Quandl\Quandl($api_key, "xml");
    return $quandl->getSymbol($symbol, [
        "collapse" => "weekly" // none|daily|weekly|monthly|quarterly|annual
    ]);
}

// Example 6: Search
function example6($api_key, $symbol)
{
    $quandl = new Royopa\Quandl\Quandl($api_key);
    return $quandl->getSearch("crude oil");
}

// Example 7: Symbol Lists
function example7($api_key, $symbol)
{
    $quandl = new Royopa\Quandl\Quandl($api_key, "csv");
    return $quandl->getList("WIKI", 1, 10);
}

// Example 8: Error Handling
function example8($api_key, $symbol)
{
    $quandl = new Royopa\Quandl\Quandl($api_key, "csv");
    $result = $quandl->getSymbol("DEBUG/INVALID");
    
    if ($quandl->error and !$result) {
        return $quandl->error . " - " . $quandl->last_url;        
    }

    return $result;
}
