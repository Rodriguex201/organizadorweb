<?php
declare(strict_types=1);
if (PDO::getAvailableDrivers() !== [] || extension_loaded('mysqli')) throw new RuntimeException('No database drivers allowed');
require __DIR__.'/bold_payment.php';

$capture = null;
$sink = function (string $json) use (&$capture) { $capture = $json; };
$diagnose = function (string $body, ?string $type = 'application/json; charset=utf-8', $svc = null) use ($service, $sink, &$capture, $reject) {
    $capture = null;
    $reject(fn () => ($svc ?? $service)->parseCreationBody(200, $body, $type, $sink), 'contract failure remains rejected');
    return $capture === null ? null : json_decode($capture, true, 512, JSON_THROW_ON_ERROR);
};
$d=$diagnose('{"payload":{"payment_link":42,"url":null},"errors":null}');
$check($d['top_level_keys']===['payload','errors']&&$d['payload_keys']===['payment_link','url'],'exact recognized keys');
$check($d['payment_link_type']==='int'&&$d['url_type']==='null','actual link field types');
$check($d['content_type']['media_type']==='application/json'&&$d['content_type']['charset_utf8'],'response content type sanitized');
$d=$diagnose('{"errors":[]}');$check(!$d['payload_present']&&$d['payload_type']==='missing','missing payload distinguished');
foreach (['[]'=>'array','{}'=>'stdClass','null'=>'null','"failed"'=>'string'] as $json=>$type) {
 $d=$diagnose('{"errors":'.$json.'}');
 $check($d['sanitized_body']['entries']['errors']['type']===$type,'errors retains real JSON type '.$type);
}
$secret='FAKE_SECRET_'.bin2hex(random_bytes(12));
$pii='private-person@example.test';$url='https://checkout.bold.co/LNK_FAKE?token='.$secret;
$d=$diagnose(json_encode(['payload'=>['payment_link'=>$secret,'url'=>$url,'payer'=>['email'=>$pii]],'errors'=>[['code'=>$secret,'message'=>$pii,'detail'=>$url]],$secret=>$secret]),'application/json; secret='.$secret);
$check(!str_contains($capture,$secret)&&!str_contains($capture,$pii)&&!str_contains($capture,'https://'),'no credential payer or URL reflected, including keys and Content-Type');
$check($d['sanitized_body']['entries']['payload']['entries']['payer']['omitted']==='sensitive','payer subtree omitted');
$d=$diagnose('<html>'.$secret.'</html>','text/html');$check(!$d['json_valid']&&!str_contains($capture,$secret),'non JSON body never logged');
$d=$diagnose(str_repeat('x',65537));$check(!$d['body_within_limit']&&strlen($capture)<=8192,'input and output bounded');
$d=$diagnose(json_encode(['errors'=>array_fill(0,500,['message'=>str_repeat($secret,20)])]));$check(strlen($capture)<=8192,'wide structures bounded');
$check($diagnose('{}','application/json',$make([], 'production'))===null,'production no diagnostic sink');
$check($diagnose('{}','application/json',$make(['environment'=>'production']))===null,'non sandbox no diagnostic sink');
$capture=null;
$service->parseCreationBody(200,json_encode($response),'application/json',$sink);
$check($capture===null,'valid response never captured');
$reject(fn ()=>$service->parseCreationBody(200,'{}','application/json',function(){throw new RuntimeException('sensitive sink error');}),'sink failure remains generic');
echo "OK: $count checks; zero HTTP, no database, no raw response files.\n";
