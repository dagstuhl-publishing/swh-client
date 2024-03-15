<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Repositories;

use Ds\Queue;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Module\HTTPConnector\HTTPClient;
use ReflectionException;
use UnhandledMatchError;

abstract class Repository
{
    protected static array $supportedRepositories = [
        GitHub::class,
        GitLab::class,
        Bitbucket::class
    ];
    private const VISIT_TYPES =[
        self::GITTYPE,
        self::BZRTYPE,
        self::HGTYPE,
        self::SVNTYPE,
    ];
    private const GITTYPE = 'git';
    private const BZRTYPE = 'bzr';
    private const HGTYPE = 'hg';
    private const SVNTYPE = 'svn';

    public string $url = '';
    public ?string $visitType = NULL;
    public array $decomposedURL = [];
    public array $nodeHits = [];
    protected string $repositoryBase;
    protected string $repositoryPath;



    public function __construct(string $url)
    {
        self::setInitialRepositoryBase();
        $this->visitType = static::VISIT_TYPE;

        $urlPaths  = static::getPaths();
        $this->url = static::setBaseRepositoryURL();

        self::setPaths($urlPaths);
    }

    /**
     * @throws Exception|UnhandledMatchError
     */
    public static function analysis(string &$url, ?string &$visitType, array &$nodeHits, array &$decomposedURL): void
    {
        if(!is_null($visitType)) {
            self::ensureSupportedVisitType($visitType);
        }

        $url = Str::of($url)->trim();

        $repositoryInstance = NULL;
        foreach (self::$supportedRepositories as $supportedRepository){
            try{
                $repositoryInstance = new $supportedRepository($url);
                break;

            }catch(ReflectionException $e){
                continue;
            }
        }

        if(is_null($repositoryInstance)) {
            HTTPClient::addErrors("Non-supported repository URL: ".$url. ". If this was git or bitbucket, please report a bug");
            throw new UnhandledMatchError('Non-supported repository URL');
        }

        $repositoryInstance->setArchiveParameters($url, $visitType, $nodeHits, $decomposedURL);
    }

    protected function setArchiveParameters(string &$url, ?string &$visitType, array &$nodeHits, array &$decomposedURL): void
    {
        $url = $this->url;
        $visitType = $this->visitType;
        $nodeHits  = $this->nodeHits;
        $decomposedURL = $this->decomposedURL;
    }

    protected function getPathArray(): array
    {
        $this->decomposedURL['path'] = preg_replace('/\/$/', '', $this->decomposedURL['path']);

        return explode("/", substr($this->decomposedURL['path'], 1));
    }

    protected static function getBreakPoint(array $pathArray): string
    {
        return Arr::first(static::BREAKPOINTS, function ($val) use($pathArray){
            return Str::is($val, $pathArray[static::$breakpointPosition]);
        });
    }

    private function setInitialRepositoryBase(): void
    {
        $this->repositoryBase = $this->decomposedURL["path"];
    }

    protected function splitByBreakpoint(string $breakpoint): void
    {
        $pathArray = explode($breakpoint, $this->decomposedURL["path"]);

        $this->repositoryBase = $pathArray[0];
        $this->repositoryPath = $pathArray[1];
    }

    protected function setPaths(?string $urlPaths): void
    {
        if(is_null($urlPaths)){
            return;
        }

        HTTPClient::addLogs("Archiving is considering repository arguments: ". preg_replace('/\//i',', ', $urlPaths));

        $urlQueues["branchName"] = new Queue(array(substr($urlPaths, 0, strpos($urlPaths,"/" ) ? : Null)));

        if(Str::substrCount($urlPaths, "/")===0){
            $this->nodeHits = $urlQueues;
            HTTPClient::addLogs("Archiving is considering branch: ". implode("/", $urlQueues['branchName']->toArray()));
            return;
        }
        $urlQueues["path"] = new Queue(explode('/', substr($urlPaths, strpos($urlPaths, "/") +1 )));

        HTTPClient::addLogs("Archiving is considering branch: ". implode("/", $urlQueues['branchName']->toArray()) .
            " and initial path: ". implode('/', $urlQueues['path']->toArray()));

        $this->nodeHits = $urlQueues;

    }

    /**
     * @throws Exception
     */
    private static function ensureSupportedVisitType($visitType): void
    {
        if(!in_array(Str::of($visitType)->trim(), self::VISIT_TYPES)){
            throw new Exception("Unsupported Visit Type");
        }
    }
}
