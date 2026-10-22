<?php

namespace App\Payments\Bold;

interface BoldHttpClient
{
    /** @return array{status:?int,body:string,content_type:?string,uncertain:bool} */
    public function create(#[\SensitiveParameter] string $key, #[\SensitiveParameter] string $body): array;

    /** @return array{status:?int,body:string,content_type:?string,uncertain:bool} */
    public function lookup(#[\SensitiveParameter] string $key, #[\SensitiveParameter] string $id): array;
}
