<?php
// Child process for double-consumption test. No DB drivers, no Laravel bootstrap.
require __DIR__.'/../../vendor/autoload.php';
if (PDO::getAvailableDrivers() !== [] || extension_loaded('mysqli')) exit(3);
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$service=new App\Portal\Otp\PortalOtpService(
    new App\Portal\Otp\PrivateOtpStore($input['directory'],$input['key']),$input['key'],[],fn()=>null,fn()=>null,fn()=>$input['now']
);
echo $service->verify('concurrent-ip',$input['token'],$input['code'])!==null ? '1' : '0';
