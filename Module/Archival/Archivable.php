<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Archival;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Module\DataType\SwhCoreID;
use Module\Globals\Formatting;
use Module\Globals\Helper;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use stdClass;
use Throwable;
use TypeError;

class Archivable extends SyncHTTP
{
    public const SUPPORTED_OPTIONS = ["withHeaders", "distinct"];

    private const ARCHIVAL_SUCCEEDED = "succeeded";

    private const FULL_VISIT = "full";

    private array $archiveHeaders = [];

    public function __construct(public string $url, public string $visitType, ...$options)
    {
        parent::__construct();

        self::setOptions(...$options);
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getAllArchives(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $responseType = self::$responseType;
        try {
            Helper::validateOptions($flags);

            $archivalRequests = $this->invokeEndpoint("GET",'save', collect([$this->url, $this->visitType]), ...$flags);

            if($archivalRequests instanceof Throwable){
                return $archivalRequests;
            }
            $this->archiveHeaders = $archivalRequests->headers();

            return $flags['withHeaders'] ?? false
                ? collect(["response"=>$archivalRequests->$responseType(), "headers" => $this->archiveHeaders])
                : $archivalRequests->$responseType();

        }catch (RequestException $e){
            $this->addErrors($e->getCode().": " . match ($e->response->status()){
                    400 => "An invalid Visit Type or URL: ". $this->url,
                    404 => "No Archival requests found in SWH for the pair: (".$this->url ." , " .$this->visitType.")",
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
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getAllSnapshotsFromArchives(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $archivalRequests = $this->getAllArchives(...$flags);

        if($archivalRequests instanceof Throwable){
            return $archivalRequests;
        }
        $archivalRequests = Formatting::reCastTo($archivalRequests, HTTPClient::RESPONSE_TYPE_ARRAY);

        $succeededRequests  = Arr::where($archivalRequests["response"] ?? $archivalRequests, function ($requestArray){
            return $requestArray['save_task_status'] === self::ARCHIVAL_SUCCEEDED;
        });

        $mappedSnapshots = Arr::pluck($succeededRequests["response"] ?? $succeededRequests, "snapshot_swhid", 'save_request_date');

        if(isset($flags["distinct"]) && $flags["distinct"]) {
            $mappedSnapshots = Arr::where(array_unique($mappedSnapshots), function ($snapshot){
                return !is_null($snapshot);
            });
        }

        $mappedSnapshots = Formatting::reCastTo($mappedSnapshots, self::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $mappedSnapshots, "headers" => $archivalRequests["headers"]])
            : $mappedSnapshots;
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function showDistinctArchives(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allArchives = $this->getAllArchives(...$flags);

        if($allArchives instanceof Throwable){
            return $allArchives;
        }

        $allArchives = Formatting::reCastTo($allArchives, HTTPClient::RESPONSE_TYPE_ARRAY);

        $snapshots = array_unique(Arr::pluck($allArchives['response'] ?? $allArchives, "snapshot_swhid"));

        $distinctArchives = [];

        array_walk($snapshots, function($snapshot) use ($allArchives, &$distinctArchives) {
            $distinctArchives[]= Arr::first($allArchives, function($archiveArray) use($snapshot) {
                return $archiveArray["snapshot_swhid"] === $snapshot;
            });
        });

        $distinctArchives = Formatting::reCastTo($distinctArchives, self::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $distinctArchives, "headers" => $allArchives["headers"]])
            : $distinctArchives;
    }

    /**
     * @param string|int $saveRequestDateOrID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getFullArchivalRequest(string|int $saveRequestDateOrID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        try {
            $archivalRequests = $this->getAllArchives(...$flags);

            if($archivalRequests instanceof Throwable){
                return $archivalRequests;
            }
            $matchingArchival = Helper::grabMatching($archivalRequests["response"] ?? $archivalRequests, $saveRequestDateOrID);

            if(is_null($matchingArchival)){
                throw new Exception("No matching archival data for the pair: (".$this->url .", $saveRequestDateOrID)", 240);
            }

            $matchingArchival = Formatting::reCastTo($matchingArchival, self::$responseType);

            return $flags['withHeaders'] ?? false
                ? collect(["response"=>$matchingArchival, "headers" => $this->archiveHeaders])
                : $matchingArchival;

        }catch (Exception $e){
            $this->addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param int $saveRequestID
     * @param ...$flags
     * @return SwhCoreID|Throwable|Null
     */
    public function getSnpFromSaveRequestID(int $saveRequestID): SwhCoreID|Null|Throwable
    {
        try{
            $archivalRequest = $this->invokeEndpoint("GET",'saveWithID', collect($saveRequestID));

            if($archivalRequest instanceof Throwable){
                return $archivalRequest;
            }
            $archivalRequest = $archivalRequest->json();

            return $archivalRequest['save_task_status'] === self::ARCHIVAL_SUCCEEDED && $archivalRequest['visit_status'] === self::FULL_VISIT
                ? new SwhCoreID($archivalRequest['snapshot_swhid'])
                : Null;

        }catch (RequestException $e){
            $this->addErrors($e->getCode().": " . match ($e->response->status()){
                    400 => "An invalid Visit Type or URL: ". $this->url,
                    404 => "No archival save request found in SWH for ID: $saveRequestID",
                    403 => "Origin ID: ". $saveRequestID ." is black listed in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (TypeError $e){
            $this->addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

}
