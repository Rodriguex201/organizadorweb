<?php
namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Portal\Otp\{PrivateOtpStore, PortalOtpService, PortalOtpMailer};
use App\Portal\ReadOnly\PortalReadGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

final class ReadOnlyProformaController extends Controller
{
    private function service(): PortalOtpService
    {
        $key = (string) config('portal_otp.key');
        $allowlist = [];
        foreach (preg_split('/[;,]/',(string) config('portal_otp.allowlist')) as $email) {
            $normalized = PortalOtpService::email($email);
            if ($normalized !== null) $allowlist[] = $normalized;
        }
        return new PortalOtpService(
            new PrivateOtpStore(config('portal_otp.directory'),$key), $key, $allowlist,
            fn ($document,$number) => app(PortalReadGateway::class)->target($document,$number),
            fn ($email,$code) => (new PortalOtpMailer)->send($email,$code),
            fn () => time(),
            config('portal_otp.send_enabled') === true,
        );
    }

    public function __invoke(Request $request): Response
    {
        $detail = null;
        $submitted = false;
        $otpStage = false;
        $otpError = false;
        $challenge = null;
        $auth = null;
        $clearAuth = false;
        $emailChoices = [];
        try {
            if ($request->isMethod('POST')) {
                $otpStage = true;
                $service = $this->service();
                if ($request->input('action') === 'restart') {
                    $service->revoke((string)$request->cookie('portal_otp_auth',''));
                    $clearAuth = true;
                    $otpStage = false;
                } elseif ($request->input('action') === 'choose') {
                    $option=$request->input('option');
                    $challenge=(string)$request->cookie('portal_otp_challenge','');
                    $service->choose((string)$request->server('REMOTE_ADDR'),$challenge,is_string($option)?$option:'');
                } elseif ($request->input('action') === 'verify') {
                    $code = $request->input('code');
                    $auth = $service->verify((string)$request->server('REMOTE_ADDR'),(string)$request->cookie('portal_otp_challenge',''),is_string($code)?$code:'');
                    $otpError = $auth === null;
                } else {
                    $clearAuth = true;
                    $service->revoke((string)$request->cookie('portal_otp_auth',''));
                    $challenge = bin2hex(random_bytes(32));
                    $document = $request->input('documento');
                    $number = $request->input('numero');
                    if (is_string($document) && is_string($number)
                        && strlen($document)<=64 && strlen($number)<=64) {
                        $challenge = $service->request((string)$request->server('REMOTE_ADDR'),$document,$number);
                        $emailChoices = $service->choices($challenge);
                    }
                }
            }
            $token = $auth ?? (!$request->isMethod('POST') ? (string)$request->cookie('portal_otp_auth','') : '');
            if ($token !== '') {
                $grant = ($service ?? $this->service())->grant($token);
                if ($grant !== null && !$request->hasAny(['documento','numero','proforma_id','cliente_id'])) {
                    $detail = app(PortalReadGateway::class)->detail($grant);
                    if ($detail !== null) $otpStage = false;
                }
            }
        } catch (Throwable) {
            $detail = null;
            if ($request->isMethod('POST')) {
                $otpStage = true;
                $otpError = $request->input('action') === 'verify';
                $clearAuth = true;
            }
        }
        $response = response()->view('portal.revision', compact('detail','submitted','otpStage','otpError','emailChoices'))
            ->header('Cache-Control','no-store, private')->header('X-Robots-Tag','noindex, nofollow')
            ->header('Referrer-Policy','same-origin')->header('X-Frame-Options','DENY')
            ->header('Content-Security-Policy',"default-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
        $cookie = fn ($name,$value,$seconds) => Cookie::create($name,$value,time()+$seconds,'/portal',null,$request->isSecure(),true,false,'strict');
        if ($clearAuth) $response->headers->setCookie($cookie('portal_otp_auth','',-3600));
        if ($challenge !== null) $response->headers->setCookie($cookie('portal_otp_challenge',$challenge,300));
        if ($auth !== null) {
            $response->headers->setCookie($cookie('portal_otp_auth',$auth,900));
            $response->headers->setCookie($cookie('portal_otp_challenge','',-3600));
        }
        return $response;
    }
}
