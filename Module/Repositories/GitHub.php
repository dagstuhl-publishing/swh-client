<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Repositories;

use Illuminate\Support\Str;
use Module\HTTPConnector\HTTPClient;
use ReflectionException;

final class GitHub extends Git
{
    private const GITHUB = 'github.com';
    protected const VISIT_TYPE = 'git';
    protected static int $breakpointPosition;
    public array $decomposedURL = [];

    /**
     * @throws ReflectionException
     */
    public function __construct(string $url)
    {
        $this->decomposedURL = parse_url(preg_replace('/\/$/i','', rawurldecode($url)));

        $match = Str::of($this->decomposedURL['host'])->match("/".self::GITHUB."/i");

        if($match->isEmpty()) {
            HTTPClient::addLogs($this->url.': Non-GitHub Repository');
            throw new ReflectionException('Non-GitHub Repository');
        }

        parent::__construct($url);
    }

    protected static function setBreakpointPosition(): void
    {
        self::$breakpointPosition = 2;
    }

}
