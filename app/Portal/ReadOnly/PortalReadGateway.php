<?php

namespace App\Portal\ReadOnly;

/** Small injectable boundary; the production implementation always opens the guarded PDO. */
class PortalReadGateway
{
    public function target(string $document, string $number): ?array
    {
        if (!ProformaReadService::validInput($document,$number)) return null;
        $reader = MySqlReviewReader::open(config('portal_review'));
        try { return (new ProformaReadService($reader))->otpTarget($document,$number); }
        finally { $reader->close(); }
    }

    public function detail(array $grant): ?array
    {
        $reader = MySqlReviewReader::open(config('portal_review'));
        try { return (new ProformaReadService($reader))->authorizedDetail($grant); }
        finally { $reader->close(); }
    }
}
