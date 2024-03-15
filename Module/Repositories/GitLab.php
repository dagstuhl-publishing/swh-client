<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Repositories;

use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Support\Str;
use Module\Globals\HTTP;
use Module\HTTPConnector\HTTPClient;
use ReflectionException;

final class GitLab extends Git
{
    private const GITLAB = 'gitlab';
    protected const VISIT_TYPE = 'git';
    protected static int $breakpointPosition;

    public array $decomposedURL = [];

    /**
     * @throws ReflectionException
     */
    public function __construct(string $url)
    {
        $this->decomposedURL = parse_url(preg_replace('/\/$/i','', rawurldecode($url)));

        $match = Str::of($this->decomposedURL['host'])->match("/".self::GITLAB."/i");

        if($match->isEmpty() && !self::isGitLabByHeaders()) {
            HTTPClient::addLogs($this->url.': Non-GitLab Repository');
            throw new ReflectionException('Non-GitLab Repository');
        }

        parent::__construct($url);
    }

    protected static function setBreakPointPosition(array &$pathArray): void
    {
        $pos = array_search('-', $pathArray);

        if(!$pos){
            $pos = -1;
        }
        else{
            $pathArray[$pos] = preg_replace('/^-/i', $pathArray[$pos+1], $pathArray[$pos]);
        }

        self::$breakpointPosition = $pos;
    }


    private function isGitLabByHeaders(): bool
    {
        try{
            $pendingURL = sprintf('%s://%s%s', $this->decomposedURL['scheme'], $this->decomposedURL['host'], "/help");

            $headResponse = HTTP::withOptions(['allow_redirects' => ['max' => 1]])->HEAD($pendingURL);

            return  array_key_exists('X-Gitlab-Meta', $headResponse->headers())
                || str_contains($headResponse->header("Set-Cookie"), "_gitlab_session")
                || str_contains(@file_get_contents($pendingURL), 'GitLab');

        }catch (TooManyRedirectsException $e){
            HTTPClient::addLogs($this->url.' --> Too many redirects for: '. $pendingURL);
            return false;
        }
    }

}
