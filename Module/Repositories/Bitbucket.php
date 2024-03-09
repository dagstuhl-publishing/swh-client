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

final class Bitbucket extends Repository
{
    private const BITBUCKET = 'bitbucket.org';
    protected const VISIT_TYPE = 'git';
    protected const BREAKPOINTS = ['src'];
    protected static int $breakpointPosition;

    public array $decomposedURL = [];

    /**
     * @throws ReflectionException
     */
    public function __construct(string $url)
    {
        $this->decomposedURL = parse_url(preg_replace('/\/$/i','', rawurldecode($url)));

        $match = Str::of($this->decomposedURL['host'])->match("/".self::BITBUCKET."/i");

        if($match->isEmpty()) {
            HTTPClient::addLogs($this->url.': Non-Bitbucket Repository');
            throw new ReflectionException('Non-Bitbucket Repository');
        }

        parent::__construct($url);
    }

    protected static function setBreakPointPosition(): void
    {
        self::$breakpointPosition = 2;
    }

    protected function getPaths(): ?string
    {
        $pathArray = parent::getPathArray();

        if(count($pathArray)===2){
            return NULL;
        }

        self::setBreakPointPosition();
        return self::analysePaths($pathArray);
    }

    protected function analysePaths(array $pathArray): string
    {
        $bitBucketBreakPoint = parent::getBreakPoint($pathArray);

        parent::splitByBreakpoint('/'.$bitBucketBreakPoint.'/');

        return parent::$repositoryPath;

    }

    protected function setBaseRepositoryURL(): string
    {
        return sprintf('%s://%s%s', $this->decomposedURL['scheme'], $this->decomposedURL['host'], parent::$repositoryBase);
    }

}
