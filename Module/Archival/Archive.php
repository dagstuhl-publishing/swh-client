<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Archival;


use Module\Repositories\Repository;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Module\DAGModel\GraphTraversal;
use Module\DataType\SwhCoreID;
use Module\Globals\Formatting;
use Module\Globals\Helper;
use Module\HTTPConnector\SyncHTTP;
use Module\OriginVisits\SwhOrigins;
use Module\OriginVisits\SwhVisits;
use stdClass;
use Throwable;
use TypeError;
use UnhandledMatchError;

class Archive extends SyncHTTP implements SwhArchive
{
    public const SUPPORTED_OPTIONS = ['withHeaders', 'distinct', 'withTracking'];
    private const FULL_VISIT = 'full';
    private const NOT_FOUND_VISIT = 'not_found';
    private const ARCHIVAL_SUCCEEDED = 'succeeded';
    private const ARCHIVAL_FAILED = 'failed';
    public array $decomposedURL = [];
    public array $nodeHits = [];
    protected stdClass $swhIDs;
    protected SwhVisits $visitObject;
    protected SwhOrigins $originObject;
    protected Archivable $archivable;


    /**
     * @param string $url
     * @param string|null $visitType
     * @param ...$options
     * @throws Exception|UnhandledMatchError
     */
    public function __construct(public string $url, public ?string $visitType = null, ...$options)
    {
        Repository::analysis($this->url, $this->visitType, $this->nodeHits, $this->decomposedURL);

        $this->archivable = new Archivable($this->url, $this->visitType);

        $this->visitObject = new SwhVisits($this->url);
        $this->originObject = new SwhOrigins($this->url);

        parent::__construct();

        self::setOptions(...$options);
    }

    /**
     * @throws Exception|UnhandledMatchError
     */
    public static function repository(string $url, ?string $visitType = NULL, ...$flags): iterable|Collection|stdClass|Throwable
    {
        $newArchival = new self($url, $visitType, ...$flags);

        $currentResponseType = $newArchival::$responseType;
        $newArchival::$responseType = $newArchival::RESPONSE_TYPE_ARRAY;

        $archivalInitialResponse = $newArchival->save2Swh();

        if($archivalInitialResponse instanceof Throwable){
            return $archivalInitialResponse;
        }

        return $flags['withTracking'] ?? false
            ? Formatting::reCastTo($newArchival->trackArchivalStatus($archivalInitialResponse['id']), $currentResponseType)
            : $archivalInitialResponse;
    }

    /**
     * @param string $url
     * @return Archivable
     * @throws Exception
     */
    public static function of(string $url): Archivable
    {
        $archive = new self($url);

        return new Archivable($archive->url, $archive->visitType);
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function save2Swh(...$flags): iterable|Collection|stdClass|Throwable
    {
        $responseType = self::$responseType;
        try{
            Helper::validateOptions($flags);

            $responseSave = $this->invokeEndpoint("POST",'save', collect([$this->url, $this->visitType]), ...$flags);

            if($responseSave instanceof Throwable){
                return $responseSave;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response"=>$responseSave->$responseType(), "headers" => $responseSave->headers()])
                : $responseSave->$responseType();

        }catch (RequestException $e){
            $this->addErrors($e->getCode().": " . match ($e->response->status()){
                400 => "An invalid Visit Type or URL: ". $this->visitType ." <--> ". $this->url,
                403 => "Origin URL: ". $this->url ." is black listed in SWH",
                default => $e->response->json()['reason'] ?? $e->response->body()
            });
            return $e;
        }catch(Exception $e){
            $this->addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }


    /**
     * @param string|int $saveRequestDateOrID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getArchivalStatus(string|int $saveRequestDateOrID, ...$flags): iterable|Collection|stdClass|Throwable
    {
        try {
            $archivalRequest = $this->archivable->getFullArchivalRequest($saveRequestDateOrID, ...$flags);

            if ($archivalRequest instanceof Throwable) {
                return $archivalRequest;
            }

            $archivalRequest = Formatting::reCastTo($archivalRequest, self::RESPONSE_TYPE_ARRAY);

            if ($archivalRequest['save_task_status'] === self::ARCHIVAL_SUCCEEDED) {

                $traverseToDirectory = GraphTraversal::traverseFromSnp(new SwhCoreID($archivalRequest['snapshot_swhid']), $this->nodeHits);

                if($traverseToDirectory instanceof Throwable){
                    return $traverseToDirectory;
                }

                $originID = $this->originObject->getOriFromURL();

                $this->swhIDs = Helper::object_merge($originID instanceof SwhCoreID ? $originID : new stdClass(), $traverseToDirectory);

                $archivalRequest['swh_id_list'] = $this->swhIDs;

                $archivalRequest['contextual_swh_ids'] = Formatting::getContexts($this->swhIDs, $this->url, $this->nodeHits["path"] ?? null);
            }
            return $archivalRequest['save_task_status'] === self::ARCHIVAL_FAILED
                ? throw new Exception("Archival has failed with id: {$archivalRequest['id']} and save_request_date: {$archivalRequest['save_request_date']}", 55)
                : Formatting::reCastTo($archivalRequest, self::$responseType);

        }catch (TypeError|Exception $e){
            $this->addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string|int $saveRequestDateOrID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function trackArchivalStatus(string|int $saveRequestDateOrID, ...$flags): iterable|Collection|stdClass|Throwable
    {
        do{
            $archivalRequest = $this->getArchivalStatus($saveRequestDateOrID, ...$flags);

            if ($archivalRequest instanceof Throwable) {
                if(is_a($archivalRequest, TypeError::class)){
                    $done = false;
                    continue;
                }
                else{
                    return $archivalRequest;
                }
            }
            $archivalRequest = Formatting::reCastTo($archivalRequest, self::RESPONSE_TYPE_ARRAY);
            self::addLogs("\tRequest Status --> ". $archivalRequest['save_request_status']);
            self::addLogs("\tTask Status --> ". $archivalRequest['save_task_status']);
            self::addLogs("\tVisit Status --> ". $archivalRequest['visit_status']);

            $done = $archivalRequest['save_task_status'] === self::ARCHIVAL_SUCCEEDED;
            self::addLogs("Done --> ".var_export($done, true)."\n");
        }while(!$done);

        return Formatting::reCastTo($archivalRequest, self::$responseType);
    }

    /**
     * @param string|int $saveRequestDateOrID
     * @return SwhCoreID|Throwable|Null
     */
    public function getSnpFromSaveRequest(string|int $saveRequestDateOrID): SwhCoreID|Null|Throwable
    {
        try {
            $archivalRequest = $this->archivable->getFullArchivalRequest($saveRequestDateOrID);

            if($archivalRequest instanceof Throwable){
                return $archivalRequest;
            }

            $archivalRequest = Formatting::reCastTo($archivalRequest, self::RESPONSE_TYPE_ARRAY);

            return $archivalRequest['save_task_status'] === self::ARCHIVAL_SUCCEEDED && $archivalRequest['visit_status'] === self::FULL_VISIT
                ? new SwhCoreID($archivalRequest['snapshot_swhid'])
                : $this->archivable->getSnpFromSaveRequestID($archivalRequest['id']);

        }catch (TypeError|Exception $e){
            $this->addErrors($e->getMessage());
            return $e;
        }
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getLatestArchivalAttempt(...$flags) : iterable|Collection|stdClass|Throwable
    {
        try {
            $latestVisit = $this->visitObject->getVisit("latest", ...$flags);

            if($latestVisit instanceof Throwable){
                return $latestVisit;
            }
            $latestVisit = Formatting::reCastTo($latestVisit, self::RESPONSE_TYPE_ARRAY);

            if($latestVisit['status'] === self::NOT_FOUND_VISIT){
                throw new Exception("Failed archival attempt for URL: $this->url");
            }
            $visitSnapshot = new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_SNAPSHOT, $latestVisit['snapshot']));
            $visitDate = $latestVisit["date"];

            $allArchives = $this->archivable->getAllArchives();

            if($allArchives instanceof Throwable){
                return $allArchives;
            }
            $matchingArchival = Helper::grabMatching($allArchives, $visitDate);

            $traverseToDirectory = GraphTraversal::traverseFromSnp($visitSnapshot, $this->nodeHits);

            $originID = $this->originObject->getOriFromURL();

            $this->swhIDs = Helper::object_merge($originID instanceof SwhCoreID ? $originID : new stdClass(), $traverseToDirectory);

            $matchingArchival['swh_id_list'] = $this->swhIDs;
            $matchingArchival['contextual_swh_ids'] = Formatting::getContexts($this->swhIDs, $this->url, $this->nodeHits["path"] ?? null);

            return Formatting::reCastTo($matchingArchival, self::$responseType);

        }catch (TypeError|Exception $e){
            $this->addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }
}
