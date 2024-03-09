<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\DAGModel;

use Ds\Queue;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Module\DataType\SwhCoreID;
use Module\Globals\Formatting;
use Module\Globals\Helper;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use stdClass;
use Throwable;
use TypeError;
use UnhandledMatchError;

abstract class GraphTraversal
{
    public const SUPPORTED_OPTIONS = ["withHeaders"];

    /**
     * @param SwhCoreID $snapshot
     * @param array $urlQueues
     * @return stdClass|Throwable
     * @throws Exception
     */
    public static function traverseFromSnp(SwhCoreID $snapshot, array &$urlQueues = []): stdClass|Throwable
    {
        $revisionID = self::obtainRevID($snapshot, $urlQueues['branchName'] ?? Null);

        if($revisionID instanceof Throwable){
            return $revisionID;
        }

        if(is_null($revisionID)){

            if(preg_match('/^[a-f0-9]{40}$/i', $urlQueues["branchName"]->toArray()[0])){

                HTTPClient::addLogs("Historical commit detected rather than branch name or latest commit. Falling back to historical commits");

                $oldCommit = self::traverseRevLogFromSnp($snapshot->getSwhid(), $urlQueues["branchName"]->toArray()[0]);

                if(is_null($oldCommit)){
                    throw new Exception("Traverse Error: Commit Doesn't Exist", 66);
                }
                $revisionID = $oldCommit;
                goto bypass_branch;
            }
            if(isset($urlQueues["path"])){

                $urlQueues["branchName"]->push($urlQueues["path"]->pop());   // FIFO

                HTTPClient::addLogs("Archiving is amending to branch: ". implode("/", $urlQueues['branchName']->toArray()));

                if($urlQueues['path']->isEmpty()){
                    unset($urlQueues['path']);
                    HTTPClient::addLogs("Path queue has been exhausted. Queue has been unset.");
                }
                return self::traverseFromSnp($snapshot, $urlQueues);
            }
            throw new Exception("Traverse Error: Branch Doesn't Exist", 77);
        }
        bypass_branch:

        $dirOrCntID = self::obtainPathID($revisionID, $urlQueues['path'] ?? Null );
        if($dirOrCntID instanceof Throwable){
            return $dirOrCntID;
        }

        return Helper::object_merge($snapshot,
            $revisionID instanceof SwhCoreID ? $revisionID : new stdClass(),
            $dirOrCntID
        );
    }

    /**
     * @param SwhCoreID $snapshot
     * @param Queue|null $branchQueue
     * @return SwhCoreID|Throwable|Null
     * @throws Exception
     */
    private static function obtainRevID(SwhCoreID $snapshot, ?Queue $branchQueue) : SwhCoreID|Throwable|Null
    {
        $revisionOrReleaseID  = GraphEdges::getRevOrRelFromSnp($snapshot->getSwhid(),
            isset($branchQueue)
                ? implode('/', $branchQueue->toArray())
                : ["*/main", "*/master"]);

        if ($revisionOrReleaseID instanceof Throwable) {
            if(!is_a($revisionOrReleaseID, TypeError::class)){
                return $revisionOrReleaseID;
            }
            throw new TypeError('Traverse Error: Missing Revision/Release swhID');
        }

        if(!is_null($revisionOrReleaseID) && $revisionOrReleaseID->getInitials() === Formatting::SWH_OBJECT_TYPES[Formatting::SWH_RELEASE]){

            return self::traverseFromRelToRev($revisionOrReleaseID->getSwhid());
        }

            /** @var SwhCoreID $revisionID */
        $revisionID = $revisionOrReleaseID;

        return $revisionID;
    }

    /**
     * @param SwhCoreID $revisionID
     * @param Queue|null $pathQueue
     * @return SwhCoreID|Throwable
     */
    private static function obtainPathID(SwhCoreID $revisionID, ?Queue $pathQueue) : SwhCoreID|Throwable
    {
        $rootDirectoryID = GraphEdges::getRootDirFromRev($revisionID->getSwhid());

        if ($rootDirectoryID instanceof Throwable) {
            if(!is_a($rootDirectoryID, TypeError::class)){
                return $rootDirectoryID;
            }
            throw new TypeError('Traverse Error: Missing Root Directory swhID');
        }
        $pathID = $rootDirectoryID;

        if(isset($pathQueue)){
            $pathString= implode('/', $pathQueue->toArray());

            HTTPClient::addLogs("Archiving left path to: $pathString");

            $pathID = self::traverseFromDir($rootDirectoryID->getSwhid(), $pathString);

            if ($pathID instanceof Throwable) {
                if(!is_a($pathID, TypeError::class)){
                    return $pathID;
                }
                throw new TypeError("Traverse Error: Missing path swhID for $pathString");
            }
        }
        return $pathID;
    }

    /**
     * @param string $revisionID
     * @param string $path
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getFullNodeFromRev(string $revisionID, string $path, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $path = Str::of(rawurlencode(Str::of($path)->trim()->replaceMatches('/^\/|\/$/',"")))->replaceMatches('/%2F/',"/");

        $responseType = HTTPClient::$responseType;

        try {
            Helper::validateOptions($flags);

            $revisionID = Formatting::extractHex($revisionID, Formatting::SWH_REVISION);

            $pathContents = SyncHTTP::call("GET",'revisionPath', collect([trim($revisionID), $path]), ...$flags);

            if($pathContents instanceof Throwable){
                return $pathContents;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $pathContents->$responseType(), "headers" => $pathContents->headers()])
                : $pathContents->$responseType();

        }catch(RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    400 => "An invalid revision sha1_git: $revisionID",
                    404 => "Requested Revision was not found in SWH: $revisionID",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (Exception $e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }
    /**
     * @param string $revisionID
     * @param string $path
     * @return SwhCoreID|Throwable
     */
    public static function traverseFromRev(string $revisionID, string $path): SwhCoreID|Throwable
    {
        try {
            $pathContents = self::getFullNodeFromRev($revisionID, $path);

            if ($pathContents instanceof Throwable) {
                return $pathContents;
            }
            $pathContents = Formatting::reCastTo($pathContents, HTTPClient::RESPONSE_TYPE_ARRAY);

            return match ($pathContents["type"]){
                'file'=> new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_CONTENT, Arr::get($pathContents, 'content.checksums.sha1_git'))),
                'dir' => new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_DIRECTORY, Arr::collapse($pathContents["content"])["dir_id"])),
            };

        }catch (TypeError|UnhandledMatchError|Exception $e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $releaseID
     * @return SwhCoreID|Throwable
     */
    public static function traverseFromRelToRev(string $releaseID): SwhCoreID | Throwable
    {
        do{
            /** @var SwhCoreID|Throwable $relID */
            $relID = GraphEdges::getRevOrRelFromRel($releaseID);

            if($relID instanceof Throwable) return $relID;

        }while($relID->getInitials() === Formatting::SWH_OBJECT_TYPES[Formatting::SWH_RELEASE]);

        return $relID;
    }

    /**
     * @param string $directoryID
     * @param string $path
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getFullNodeFromDir(string $directoryID, string $path, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $path = Str::of(rawurlencode(Str::of($path)->trim()->replaceMatches('/^\/|\/$/',"")))->replaceMatches('/%2F/',"/");

        $responseType = HTTPClient::$responseType;

        try {
            Helper::validateOptions($flags);

            $directoryID = Formatting::extractHex($directoryID, Formatting::SWH_DIRECTORY);

            $responseDir = SyncHTTP::call("GET",'directoryPath', collect([trim($directoryID), $path]), ...$flags);

            if($responseDir instanceof Throwable){
                return $responseDir;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseDir->$responseType(), "headers" => $responseDir->headers()])
                : $responseDir->$responseType();

        }catch(RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    400 => "An invalid directory checksum: $directoryID",
                    404 => "Requested path for Directory/Content cannot be found in this directory: $directoryID",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (TypeError|Exception $e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }


    /**
     * @param string $directoryID
     * @param string $path
     * @return SwhCoreID|Throwable
     */
    public static function traverseFromDir(string $directoryID, string $path): SwhCoreID|Throwable
    {
        try {
            $pathContents = self::getFullNodeFromDir($directoryID, $path);

            if($pathContents instanceof Throwable){
                return $pathContents;
            }

            $pathContents = Formatting::reCastTo($pathContents, HTTPClient::RESPONSE_TYPE_ARRAY);

            if(Arr::isList($pathContents)){
                return new SwhCoreID($directoryID);
            }

            return new SwhCoreID(Formatting::formatSwhIDs(
                match($pathContents['type']){
                    'dir' => Formatting::SWH_DIRECTORY,
                    'file' => Formatting::SWH_CONTENT,
                    'rev' => Formatting::SWH_REVISION    // as mentioned in docs!
                },
                $pathContents['target']));

        }catch (TypeError|UnhandledMatchError|Exception $e){
            HTTPClient::addErrors($e->getMessage());
            return $e;
        }
    }
    /**
     * @param string $revisionID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getFullRevisionLog(string $revisionID, ...$flags): Iterable|Collection|stdClass|Throwable       // interacts with the BFS traversal on the revision graph
    {                                                                                                   // todo: swh is limited to 1,000 commit logging . Linux repo has > 1,000,000 commits!
        $responseType = HTTPClient::$responseType;
        try{
            Helper::validateOptions($flags);

            $revisionID = Formatting::extractHex($revisionID, Formatting::SWH_REVISION);

            $responseRevLog = SyncHTTP::call("GET", 'revisionLog', collect([$revisionID, "?limit=1000"]), ...$flags);

            if($responseRevLog instanceof Throwable){
                return $responseRevLog;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseRevLog->$responseType(), "headers" => $responseRevLog->headers()])
                : $responseRevLog->$responseType();


        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid Revision identifier: $revisionID",
                    404 => "Requested Revision was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (TypeError|Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }
    /**
     * @param string $revisionID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function mapRevisionsLog(string $revisionID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $allRevisionsLogs = self::getFullRevisionLog($revisionID, ...$flags);

        if($allRevisionsLogs instanceof Throwable){
            return $allRevisionsLogs;
        }
        $allRevisionsLogs = Formatting::reCastTo($allRevisionsLogs, HTTPClient::RESPONSE_TYPE_ARRAY);

        $revisionMapping = Arr::pluck($allRevisionsLogs["response"] ?? $allRevisionsLogs, "parents", 'id');

        $revisionMapping = Formatting::reCastTo($revisionMapping, HTTPClient::$responseType);

        return $flags['withHeaders'] ?? false
            ? collect(["response" => $revisionMapping, "headers" => $allRevisionsLogs["headers"]])
            : $revisionMapping;
    }

    /**
     * @param string $snapshotID
     * @param string $commitHash
     * @return SwhCoreID|Throwable|Null
     */
    public static function traverseRevLogFromSnp(string $snapshotID, string $commitHash) : SwhCoreID|Null|Throwable
    {
        $revisions =  GraphEdges::getSnapshotEdges($snapshotID);

        if($revisions instanceof Throwable){
            return $revisions;
        }
        $revisions = array_values(Formatting::reCastTo($revisions,HTTPClient::RESPONSE_TYPE_ARRAY));

        foreach ($revisions as $revisionID){
            $allCommits = self::mapRevisionsLog($revisionID);
            $allCommits = array_keys(Formatting::reCastTo($allCommits, HTTPClient::RESPONSE_TYPE_ARRAY));

            if(is_int(array_search($commitHash, $allCommits))){
                return new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_REVISION, $commitHash));
            }
        }
        return Null;
    }

    /**
     * @param string $revisionID
     * @param string $commitHash
     * @return SwhCoreID|Throwable|Null
     */
    public static function traverseRevLogFromRev(string $revisionID, string $commitHash) : SwhCoreID|Null|Throwable
    {
        $allCommits = array_keys(Formatting::reCastTo(self::mapRevisionsLog($revisionID), HTTPClient::RESPONSE_TYPE_ARRAY));

        return is_int(array_search($commitHash, $allCommits))
            ? new SwhCoreID(Formatting::formatSwhIDs(Formatting::SWH_REVISION, $commitHash))
            : Null;
    }
}
