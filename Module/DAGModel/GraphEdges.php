<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\DAGModel;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Module\DataType\SwhCoreID;
use Module\Globals\Formatting;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use stdClass;
use Throwable;
use TypeError;
use UnhandledMatchError;

abstract class GraphEdges
{

    /**
     * @throws Exception
     */
    public static function getRevOrRelFromSnp(string $snapshotID, mixed $branch = ["*/main", "*/master"]): SwhCoreID|Throwable|null
    {
        return self::resolveSnpEdge($snapshotID, $branch);
    }

    /**
     * @param string $snapshotID
     * @param mixed $branch
     * @param string|null $branchesFrom
     * @return SwhCoreID|Throwable|Null
     * @throws Exception
     */
    public static function resolveSnpEdge(string $snapshotID, mixed $branch , ?string $branchesFrom = NULL): SwhCoreID|Null|Throwable
    {
        try{

            $snapshotID = Formatting::extractHex($snapshotID, Formatting::SWH_SNAPSHOT);

            $append2Url = $branchesFrom === NULL
                ? collect(trim($snapshotID))
                : collect(trim($snapshotID)."/?branches_from=".$branchesFrom);

            $responseSnp = SyncHTTP::call("GET", 'snapshot', $append2Url);

            if($responseSnp instanceof Throwable){
                return $responseSnp;
            }
            $responseSnp = $responseSnp->json();

            self::findDefaultBranch($responseSnp, $branch);

            $matchingBranch = self::findFirstMatch($responseSnp, $branch);

            if ($matchingBranch === NULL) {
                return isset($responseSnp["next_branch"])
                    ? self::resolveSnpEdge($snapshotID, $branch, $responseSnp["next_branch"])
                    : Null ;
            }
            else {
                return new SwhCoreID(Formatting::formatSwhIDs(
                    match($matchingBranch['target_type']){
                        'release'  => Formatting::SWH_RELEASE,
                        'revision' => Formatting::SWH_REVISION,
                    },
                    $matchingBranch['target']));
            }
        }catch (RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    400 => "An invalid snapshot identifier: $snapshotID",
                    404 => "Requested Revision or Release can not be found on this Snapshot: " . Formatting::formatSwhIDs('snapshot', $snapshotID),
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (TypeError|UnhandledMatchError|Exception $e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }

    /**
     * @param array $responseSnp
     * @param mixed $branch
     * @return void
     */
    private static function findDefaultBranch(array $responseSnp, mixed &$branch): void
    {
        if(Arr::accessible($branch)) {
            if (isset($responseSnp["branches"]["HEAD"])) {
                $branch = array_merge($branch, array_fill(2, 1,
                    "*/" . Str::substr($responseSnp["branches"]["HEAD"]["target"], strrpos($responseSnp["branches"]["HEAD"]["target"], "/") + 1)));
            }
        }
    }

    /**
     * @param array $responseSnp
     * @param mixed $branch
     * @return array|Null
     */
    private static function findFirstMatch(array $responseSnp, mixed $branch): array|Null
    {
        return Arr::first($responseSnp['branches'],          // branch cases: refs/heads/branch_name, refs/tags/tag_name, refs/heads/features/name, refs/pull/(integer)/head
            function ($val, $key) use ($branch) {
                return match (true) {
                    is_numeric($branch) => Str::is("refs/pull/".$branch."/head", $key),
                    Arr::accessible($branch) => Str::is($branch, $key),
                    preg_match('/^[a-f0-9]{40}$/i', $branch) === 1 => $val['target'] === $branch,
                    default => Str::is("*/" . trim($branch), $key)
                };
            });
    }

    /**
     * @param string $releaseID
     * @return SwhCoreID|Throwable
     */
    public static function getRevOrRelFromRel(string $releaseID): SwhCoreID|Throwable
    {
        try{
            $responseRel = GraphHopping::getFullRelease($releaseID);

            if($responseRel instanceof Throwable){
                return $responseRel;
            }

            $responseRel = Formatting::reCastTo($responseRel, HTTPClient::RESPONSE_TYPE_ARRAY);

            return new SwhCoreID(Formatting::formatSwhIDs(
                match($responseRel['target_type']){
                    'release'  => Formatting::SWH_RELEASE,
                    'revision' => Formatting::SWH_REVISION,
                    'directory' => Formatting::SWH_DIRECTORY,
                    'content' => Formatting::SWH_CONTENT,
                },
                $responseRel['target']));

        }catch (TypeError|UnhandledMatchError$e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }


    /**
     * @param string $revisionID
     * @param string|null $path
     * @return SwhCoreID|Throwable
     */
    public static function getRootDirFromRev(string $revisionID): SwhCoreID|Throwable
    {
        try {
            $responseRev = GraphHopping::getFullRevision($revisionID);

            if($responseRev instanceof Throwable){
                return $responseRev;
            }
            $responseRev = Formatting::reCastTo($responseRev, HTTPClient::RESPONSE_TYPE_ARRAY);

            return new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_DIRECTORY, $responseRev["directory"]));

        }catch (TypeError $e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $snapshotID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getSnapshotEdges(string $snapshotID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $fullSnapshot = GraphHopping::getFullSnapshot($snapshotID, ...$flags);

        if($fullSnapshot instanceof Throwable){
            return $fullSnapshot;
        }
        $fullSnapshot = Formatting::reCastTo($fullSnapshot, HTTPClient::RESPONSE_TYPE_ARRAY);

        $allBranches = Arr::map($fullSnapshot["response"]["branches"] ?? $fullSnapshot["branches"], function($branchData) {
            $nodeID = Formatting::formatSwhIDs($branchData["target_type"] ?? '', $branchData["target"] ?? '');
            return $nodeID instanceof Exception
                ? Null
                : $nodeID;
        });

        $allBranches = Arr::except($allBranches, Arr::where(array_keys($allBranches), function($key){
            return Str::contains($key, ["HEAD"]);
        }));

        $allBranches = Formatting::reCastTo($allBranches, HTTPClient::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $allBranches, "headers" => $fullSnapshot["headers"]])
            : $allBranches;
    }

    /**
     * @param string $revisionID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getRevisionEdges(string $revisionID, ...$flags): Iterable|Collection|stdClass|Throwable
    {

        $fullRevision = GraphHopping::getFullRevision($revisionID, ...$flags);

        if($fullRevision instanceof Throwable){
            return $fullRevision;
        }
        $fullRevision = Formatting::reCastTo($fullRevision, HTTPClient::RESPONSE_TYPE_ARRAY);

        $revisionEdges = Arr::only($fullRevision, ["directory", "parents"]);

        $revisionEdges = Arr::map($revisionEdges, function ($value, $key){
            if($key==='directory'){
                return Formatting::formatSwhIDs(Formatting::SWH_DIRECTORY, $value ?? '');
            }
            return Arr::map($value, function ($array){
                if(array_key_exists('id', $array)){
                    return Formatting::formatSwhIDs(Formatting::SWH_REVISION , $array['id'] ?? '');
                }
                else return Null;
            });
        });

        $allBranches = Formatting::reCastTo($revisionEdges, HTTPClient::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $allBranches, "headers" => $fullRevision["headers"]])
            : $allBranches;

    }

    /**
     * @param string $releaseID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getReleaseEdges(string $releaseID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $fullRelease = GraphHopping::getFullRelease($releaseID, ...$flags);

        if($fullRelease instanceof Throwable){
            return $fullRelease;
        }

        $releaseResponse = Formatting::reCastTo($fullRelease['response'] ?? $fullRelease, HTTPClient::RESPONSE_TYPE_ARRAY);

        $releaseEdge = [$releaseResponse['name'] => Formatting::formatSwhIDs(
            $releaseResponse['target_type'] === 'revision' ? Formatting::SWH_REVISION : Formatting::SWH_RELEASE, $releaseResponse['target'])];

        $releaseEdge = Formatting::reCastTo($releaseEdge, HTTPClient::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $releaseEdge, "headers" => $fullRelease["headers"]])
            : $releaseEdge;
    }

    /**
     * @param string $directoryID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getDirectoryEdges(string $directoryID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allDirectory = GraphHopping::getFullDirectory($directoryID, ...$flags);

        if($allDirectory instanceof Throwable){
            return $allDirectory;
        }
        $allDirectory = Formatting::reCastTo($allDirectory, HTTPClient::RESPONSE_TYPE_ARRAY);

        $allContentsNames = Arr::pluck($allDirectory["response"] ?? $allDirectory, 'name');

        $allContentsIDs = Arr::map($allDirectory["response"]?? $allDirectory, function($edgesNodesArray) {
            $edgeNodeID = Formatting::formatSwhIDs(
                match($edgesNodesArray["type"]) {
                    'dir' => Formatting::SWH_DIRECTORY,
                    'file'=> Formatting::SWH_CONTENT
                }
                , $edgesNodesArray["target"] ?? '');

            return $edgeNodeID instanceof Exception
                ? Null
                : $edgeNodeID;
        });

        $allContents = array_combine($allContentsNames, $allContentsIDs);

        $allContents = Formatting::reCastTo($allContents, HTTPClient::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $allContents, "headers" => $allDirectory["headers"]])
            : $allContents;
    }

    /**
     * @param string $directoryID
     * @param string $seekEdgeTarget
     * @return SwhCoreID|Throwable|Null
     */
    public static function getNextNodeFromDir(string $directoryID, string $seekEdgeTarget): SwhCoreID|Throwable|Null
    {
        try{
            $dirEdges = self::getDirectoryEdges($directoryID);

            if($dirEdges instanceof Throwable){
                return $dirEdges;
            }
            $dirEdges = Formatting::reCastTo($dirEdges, HTTPClient::RESPONSE_TYPE_ARRAY);

            return Arr::hasAny($dirEdges, $seekEdgeTarget)
                ? new SwhCoreID($dirEdges[$seekEdgeTarget])
                : Null;
        }catch (TypeError $e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }

}
