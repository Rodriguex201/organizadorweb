<?php
declare(strict_types=1);
namespace App\Payments\Bold {
    // cURL adapter contract test without loading cURL or opening sockets.
    function curl_init($url) { $GLOBALS['url']=$url; return new \stdClass; }
    function curl_setopt_array($h,$options) { $GLOBALS['options']=$options; return true; }
    function curl_setopt($h,$option,$value) { $GLOBALS['options'][$option]=$value; return true; }
    function curl_exec($h) { ++$GLOBALS['dispatches']; ($GLOBALS['options'][CURLOPT_WRITEFUNCTION])($h,'{}'); return !$GLOBALS['timeout']; }
    function curl_errno($h) { return $GLOBALS['timeout']?28:0; }
    function curl_getinfo($h,$option) { return $option===CURLINFO_RESPONSE_CODE?200:'application/json'; }
}
namespace {
    if (extension_loaded('curl') || PDO::getAvailableDrivers()!==[]) throw new \RuntimeException('No network/DB extensions');
    foreach (['CURLOPT_CUSTOMREQUEST','CURLOPT_HTTPHEADER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_FOLLOWLOCATION',
        'CURLOPT_MAXREDIRS','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST','CURLOPT_PROTOCOLS','CURLOPT_VERBOSE',
        'CURLOPT_WRITEFUNCTION','CURLOPT_POSTFIELDS','CURLINFO_RESPONSE_CODE','CURLINFO_CONTENT_TYPE','CURLPROTO_HTTPS'] as $i=>$name) define($name,$i+1);
    require dirname(__DIR__,2).'/vendor/autoload.php';
    $count=0;$check=function($ok,$label)use(&$count){if(!$ok)throw new \RuntimeException($label);++$count;echo "PASS $label\n";};
    $GLOBALS['dispatches']=0;$GLOBALS['timeout']=false;
    $client=new \App\Payments\Bold\CurlBoldHttpClient;
    $r=$client->create('synthetic-key','synthetic-body');$o=$GLOBALS['options'];
    $check($GLOBALS['url']===\App\Services\BoldPaymentService::ENDPOINT&&$o[CURLOPT_CUSTOMREQUEST]==='POST','fixed POST endpoint');
    $check($o[CURLOPT_HTTPHEADER]===['Authorization: x-api-key synthetic-key','Content-Type: application/json','Accept: application/json'],'headers');
    $check($o[CURLOPT_POSTFIELDS]==='synthetic-body','body passed unchanged');
    $check($o[CURLOPT_CONNECTTIMEOUT]===10&&$o[CURLOPT_TIMEOUT]===25,'explicit timeouts');
    $check(!$o[CURLOPT_FOLLOWLOCATION]&&$o[CURLOPT_MAXREDIRS]===0,'no redirects');
    $check($o[CURLOPT_SSL_VERIFYPEER]&&$o[CURLOPT_SSL_VERIFYHOST]===2&&$o[CURLOPT_PROTOCOLS]===CURLPROTO_HTTPS,'TLS verification');
    $check(!$o[CURLOPT_VERBOSE]&&!$r['uncertain'],'no verbose output');
    $client->lookup('synthetic-key','opaque/../id?x');
    $check($GLOBALS['url']===\App\Services\BoldPaymentService::ENDPOINT.'/opaque%2F%2E%2E%2Fid%3Fx','GET ID encoded as a single segment');
    $check($GLOBALS['options'][CURLOPT_CUSTOMREQUEST]==='GET'&&!isset($GLOBALS['options'][CURLOPT_POSTFIELDS]),'GET has no body');
    $GLOBALS['timeout']=true;$before=$GLOBALS['dispatches'];$r=$client->create('synthetic-key','body');
    $check($r['uncertain']&&$r['body']===''&&$GLOBALS['dispatches']===$before+1,'timeout has no retry or partial response');
    echo "OK: $count cURL contract checks; no real HTTP.\n";
}
