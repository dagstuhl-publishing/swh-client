<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\OriginVisits;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Module\DAGModel\GraphEdges;
use Module\DataType\SwhCoreID;
use Module\Globals\Formatting;
use Module\Globals\Helper;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use ReflectionException;
use stdClass;
use Throwable;
use TypeError;

class SwhVisits extends SyncHTTP
{
    private const FULL_VISIT = "full";
    private const VISIT_STATUS = [self::FULL_VISIT, 'created', 'partial', 'not_found', 'failed', 'ongoing' ];
    public const SUPPORTED_OPTIONS = ['stringType', 'withHeaders', 'distinctSnaps', 'requireSnapshot'];
    private const EXISTS = 'Exists in SWH';


    public function __construct(public string $url, ...$options)
    {
        parent::__construct();

        $this->url = Str::of($this->url)->trim();

        self::setOptions(...$options);
    }

    /**
     * @param string|int $visit
     * @return SwhCoreID|Throwable
     */
    public function getSnpFromVisit(string|int $visit): SwhCoreID|Throwable
    {
        try{
            $visit = Str::of(strtolower($visit))->replaceMatches('/\s/',"");

            $responseVisit = match (true) {
                is_numeric($visit->value()),
                    $visit->value() === 'latest' => $this->getVisit($visit->value(), requireSnapshot: true),
                $visit->value() === 'first' => $this->showFirstFullVisit(),
                $visit->value() === 'last' => $this->showLastFullVisit(),
                default => NULL,
            };

            if($responseVisit instanceof Throwable){
                return $responseVisit;
            }
            if(is_null($responseVisit)){
                throw new Exception("Unrecognised VisitID provided. Supported is: a numeric type or the word 'latest'", 815);
            }

            $responseVisit = $responseVisit instanceof Response
                ? $responseVisit->json()
                : Formatting::reCastTo($responseVisit, HTTPClient::RESPONSE_TYPE_ARRAY);

            return is_null($responseVisit['snapshot'])
                ? throw new Exception("Snapshot might not have been generated yet. Received status: {$responseVisit['status']}", 816)
                : new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_SNAPSHOT, $responseVisit['snapshot']));

        }catch (RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    404 => "Requested visit can not be found in SWH for: $this->url",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (TypeError|Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string|int $visitDateOrNumber
     * @return SwhCoreID|Throwable
     */
    public function getSnpFromVisits(string|int $visitDateOrNumber): SwhCoreID|Throwable
    {
        try{
            $responseVisits = $this->buildAllVisits();

            if($responseVisits instanceof Throwable){
                return $responseVisits;
            }

            $matchingVisit = Helper::grabMatching($responseVisits, $visitDateOrNumber);

            if(is_null($matchingVisit)){
                throw new Exception("No matching visit data for this visit date: (".$this->url .", $visitDateOrNumber)", 260);
            }

            return $matchingVisit['status'] === self::FULL_VISIT
                ? new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_SNAPSHOT, $matchingVisit['snapshot']))
                : $this->getSnpFromVisit($matchingVisit['visit']);

        }catch (TypeError|Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getAllSnapshotsFromVisits(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allVisits = $this->buildAllVisits(...$flags);

        if($allVisits instanceof Throwable){
            return $allVisits;
        }
        $allVisits = Formatting::reCastTo($allVisits, HTTPClient::RESPONSE_TYPE_ARRAY);

        $allFullVisits = Arr::where($allVisits["response"] ?? $allVisits, function ($visitArray){
            return $visitArray['status'] === self::FULL_VISIT;
        });

        $allSnapshots = Arr::pluck($allFullVisits["response"] ?? $allFullVisits, Formatting::SWH_SNAPSHOT, 'date');

        $allSnapshots = Arr::map($allSnapshots, function ($snapshotHex){
            $snpID = Formatting::formatSwhIDs(Formatting::SWH_SNAPSHOT, $snapshotHex ?? '');
            return $snpID instanceof Exception
                ? Null
                : $snpID;
        });

        if(isset($flags["distinctSnaps"]) && $flags["distinctSnaps"]) {
            $allSnapshots = Arr::where(array_unique($allSnapshots), function ($snapshot){
                return !is_null($snapshot);
            });
        }

        $allSnapshots = Formatting::reCastTo($allSnapshots, self::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $allSnapshots, "headers" => $allVisits["headers"]])
            : $allSnapshots;
    }

    /**
     * @param int|string $visit
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getVisit(int|string $visit, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $visit = Str::of(strtolower($visit))->replaceMatches('/\s/',"");
        $responseType = self::$responseType;
        try{
            Helper::validateOptions($flags);

            $append2Url = isset($flags['requireSnapshot']) && $visit->value() === 'latest'
                ? collect([$this->url, $visit->value(). "?require_snapshot=".var_export($flags['requireSnapshot'], true)])
                : collect([$this->url, $visit->value()]);

            $responseVisit = match (true) {
                is_numeric($visit->value()),
                    $visit->value() === 'latest' => $this->invokeEndpoint("GET", 'visit', $append2Url, ...$flags),
                default => NULL,
            };
            if($responseVisit instanceof Throwable){
                return $responseVisit;
            }
            if(is_null($responseVisit)){
                throw new Exception("Unrecognised VisitID provided. Supported is: a numeric type or the word 'latest'",815);
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseVisit->$responseType(), "headers" => $responseVisit->headers()])
                : $responseVisit->$responseType();

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    404 => "Requested visit was not found in SWH for: $this->url",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getAllVisits(...$flags): Iterable|Collection|stdClass|Throwable
    {
        return $this->buildAllVisits(...$flags);
    }

    /**
     * @param int|NULL $perPage
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    private function buildAllVisits(int $perPage = NULL, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $responseType = self::$responseType;

        try{
            Helper::validateOptions($flags);

            $append2Url= $perPage === NULL
                ? collect([$this->url,""])
                : collect([$this->url, "?per_page=".$perPage]);

            $responseVisits = $this->invokeEndpoint("GET", 'visits', $append2Url, ...$flags);

            if($responseVisits instanceof Throwable){
                return $responseVisits;
            }

            if(isset($responseVisits->headers()["Link"])){
                $headerLink =  Str::of($responseVisits->headers()["Link"][0])->explode(";")[0];
                parse_str(parse_url($headerLink, PHP_URL_QUERY), $result);
                $perPage = (int)$result["last_visit"] + count($responseVisits->json());
                return $this->buildAllVisits($perPage, ...$flags);
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseVisits->$responseType(), "headers" => $responseVisits->headers()])
                : $responseVisits->$responseType();

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    404 => "No visits were found in SWH for: $this->url",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }
    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function showAllFullVisits(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allVisits = $this->buildAllVisits(...$flags);

        if($allVisits instanceof Throwable){
            return $allVisits;
        }
        $allVisitsCasted = Formatting::reCastTo($allVisits['response'] ?? $allVisits, self::RESPONSE_TYPE_ARRAY);

        $allFullVisits = array_merge([], Arr::where($allVisitsCasted, function($visitArray) {
            return $visitArray["status"] === self::FULL_VISIT;
        }));

        $allFullVisits = Formatting::reCastTo($allFullVisits, self::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $allFullVisits, "headers" => $allVisits["headers"]])
            : $allFullVisits;
    }

    /**
     * @param string $visitStatus
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function showVisitsByStatus(string $visitStatus, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        try {
            if (!in_array($visitStatus, self::VISIT_STATUS)) {
                throw new Exception("'$visitStatus' is not a correct SWH visit status");
            }
            $allVisits = $this->buildAllVisits(...$flags);

            if ($allVisits instanceof Throwable) {
                return $allVisits;
            }
            $allVisitsCasted = Formatting::reCastTo($allVisits['response'] ?? $allVisits, HTTPClient::RESPONSE_TYPE_ARRAY);

            $allFullVisits = array_merge(Arr::where($allVisitsCasted, function ($visitArray) use ($visitStatus) {
                return $visitArray["status"] === $visitStatus;
            }));

            $allFullVisits = Formatting::reCastTo($allFullVisits, self::$responseType);

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $allFullVisits, "headers" => $allVisits["headers"]])
                : $allFullVisits;

        }catch (Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function showDistinctFullVisits(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allVisits = $this->showAllFullVisits(...$flags);

        if($allVisits instanceof Throwable){
            return $allVisits;
        }
        $allVisitsCasted = Formatting::reCastTo($allVisits['response'] ?? $allVisits, HTTPClient::RESPONSE_TYPE_ARRAY);

        $distinctFullVisits = array_values(
            Arr::sortDesc(
                Arr::keyBy(Arr::sort($allVisitsCasted, fn($visitArray) => strtotime($visitArray["date"])), "snapshot")
                ,
                fn ($ascSortedVisitArray) => strtotime($ascSortedVisitArray["date"])
            )
        );

        $distinctFullVisits = Formatting::reCastTo($distinctFullVisits, self::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $distinctFullVisits, "headers" => $allVisits["headers"]])
            : $distinctFullVisits;
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function showFirstFullVisit(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allVisits = $this->buildAllVisits(...$flags);

        if($allVisits instanceof Throwable){
            return $allVisits;
        }
        $allVisits = Formatting::reCastTo($allVisits, HTTPClient::RESPONSE_TYPE_ARRAY);

        $allFullVisits = Arr::first(array_merge([], Arr::sort($allVisits["response"] ?? $allVisits, fn ($visitArray) => $visitArray['date']))
            , fn($sortedVisitArray) => $sortedVisitArray["status"] === self::FULL_VISIT);

        $allFullVisits = Formatting::reCastTo($allFullVisits, self::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $allFullVisits, "headers" => $allVisits["headers"]])
            : $allFullVisits;
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function showLastFullVisit(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allVisits = $this->buildAllVisits(...$flags);

        if($allVisits instanceof Throwable){
            return $allVisits;
        }
        $allVisits = Formatting::reCastTo($allVisits, HTTPClient::RESPONSE_TYPE_ARRAY);

        $allFullVisits = Arr::first($allVisits["response"] ?? $allVisits, fn($visitArray) => $visitArray["status"] === self::FULL_VISIT);

        $allFullVisits = Formatting::reCastTo($allFullVisits, self::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $allFullVisits, "headers" => $allVisits["headers"]])
            : $allFullVisits;
    }

    /**
     * @param int $visitNumber
     * @param ...$flags
     * @return String|bool|Throwable
     */
    public function visitExists(int $visitNumber, ...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $responseVisit = $this->invokeEndpoint("HEAD", 'visit', collect([$this->url, $visitNumber]), ...$flags);

            if($responseVisit instanceof Throwable){
                return $responseVisit;
            }

            return $flags['stringType'] ?? false
                ? "Visit #: '$visitNumber' for $this->url --> ". self::EXISTS
                : boolval($responseVisit);

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    404 => "Requested Visit '$visitNumber' was not found in SWH for: $this->url",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    public function buildGraphNodes(): iterable|Throwable
    {
        $start = microtime(true);

        $allSnapshots = $this->getAllSnapshotsFromVisits(distinctSnaps: true);

        if($allSnapshots instanceof Throwable){
            return $allSnapshots;
        }

        $allSnapshots = Formatting::reCastTo($allSnapshots, HTTPClient::RESPONSE_TYPE_ARRAY);

        $snapshots = $revisions = [];

        array_walk($allSnapshots, function ($snapshot) use(&$snapshots, $revisions){

            $snapshotEdges = GraphEdges::getSnapshotEdges($snapshot);

            $snapshotEdges = Formatting::reCastTo($snapshotEdges, HTTPClient::RESPONSE_TYPE_ARRAY);

            array_walk($snapshotEdges, function ($revisionID, $branch) use(&$revisions){
                $revisionEdges = GraphEdges::getRevisionEdges($revisionID);

                $directoryEdges = GraphEdges::getDirectoryEdges($revisionEdges["directory"]);

                $directoryEdges = Formatting::reCastTo($directoryEdges, HTTPClient::RESPONSE_TYPE_ARRAY);

                $directoryEdges = Arr::map($directoryEdges, function ($directoryNode){
                    try {
                        $dirID = (new SwhCoreID($directoryNode))->dir;
                        return [ $dirID => GraphEdges::getDirectoryEdges($directoryNode)];
                    }catch (ReflectionException $e){
                        self::addLogs($e->getMessage());
                        return $directoryNode;
                    }
                });

                $directoryEdgesKeyed = [$revisionEdges["directory"] => $directoryEdges];

                $revisionEdges["directory"] = $directoryEdgesKeyed;

                $revisionEdgesKeyed = ['branch' => $branch, $revisionID => $revisionEdges];

                $revisions[] = $revisionEdgesKeyed;
            });

            $snapshotEdgesKeyed = [$snapshot => $revisions];

            $snapshots[] = $snapshotEdgesKeyed;

        });

        $stop = microtime(true);
        self::addLogs("--> Graph built in: ".round($stop - $start, 2)." seconds");

        return Arr::map($allSnapshots, function ($snapshot) use(&$snapshots){
            $current = current($snapshots);
            next($snapshots);
            return $current;
        });

    }

}
