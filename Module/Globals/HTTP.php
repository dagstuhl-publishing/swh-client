<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Globals;

use Illuminate\Http\Client\PendingRequest;

abstract class HTTP
{
    public static function withOptions(array $options): PendingRequest
    {
        $request = new PendingRequest();
        return $request->withOptions($options);
    }

    public static function withToken(string $token): PendingRequest
    {
        $request = new PendingRequest();
        return $request->withToken($token);
    }
}