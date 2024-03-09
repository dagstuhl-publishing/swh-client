<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\DAGModel;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Module\Globals\Formatting;
use Module\Globals\Helper;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use stdClass;
use Throwable;

abstract class GraphHopping
{
    public const SUPPORTED_OPTIONS = ["stringType", "withHeaders"];

    private const EXISTS = 'Exists in SWH';


    /**
     * @param string $snapshotID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getFullSnapshot(string $snapshotID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        return self::buildFullSnapshot($snapshotID, ...$flags);
    }


    /**
     * @param string $snapshotID
     * @param string|NULL $branchesFrom
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    private static function buildFullSnapshot(string $snapshotID, string $branchesFrom = NULL, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $appendBranches2ResponseSnp["branches"]=[];
        $appendBranches2ResponseSnp["next_branch"] = null;

        $responseType = HTTPClient::$responseType;

        try{
            Helper::validateOptions($flags);

            $snapshotID = Formatting::extractHex($snapshotID, Formatting::SWH_SNAPSHOT);

            $append2Url= isset($branchesFrom)
                ? collect($snapshotID."/?branches_from=".$branchesFrom)
                : collect($snapshotID);

            $responseSnp = SyncHTTP::call("GET", 'snapshot', $append2Url, ...$flags);

            if($responseSnp instanceof Throwable){
                return $responseSnp;
            }
            $responseSnpJson = $responseSnp->json();

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid Snapshot identifier: $snapshotID",
                    404 => "Requested Snapshot was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }

        if(isset($responseSnp["next_branch"])){
            HTTPClient::setOptions(responseType: HTTPClient::RESPONSE_TYPE_ARRAY);
            $appendBranches2ResponseSnp = self::buildFullSnapshot($snapshotID, $responseSnpJson["next_branch"]);
        }

        $responseSnpJson["branches"] = array_merge($responseSnpJson["branches"], $appendBranches2ResponseSnp["branches"]);
        $responseSnpJson["next_branch"] = $appendBranches2ResponseSnp["next_branch"];

        $responseSnpCast = Formatting::reCastTo($responseSnpJson, $responseType);
        HTTPClient::setOptions(responseType: $responseType);


        return $flags['withHeaders'] ?? false
            ? collect(["response" => $responseSnpCast, "headers" => $responseSnp->headers()])
            : $responseSnpCast;
    }

    /**
     * @param string $releaseID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getFullRelease(string $releaseID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $responseType = HTTPClient::$responseType;

        try{
            Helper::validateOptions($flags);

            $releaseID = Formatting::extractHex($releaseID, Formatting::SWH_RELEASE);

            $responseRel = SyncHTTP::call("GET", 'release', collect($releaseID), ...$flags);

            if($responseRel instanceof Throwable){
                return $responseRel;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseRel->$responseType(), "headers" => $responseRel->headers()])
                : $responseRel->$responseType();

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid Release identifier: $releaseID",
                    404 => "Requested Release was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $revisionID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getFullRevision(string $revisionID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $responseType = HTTPClient::$responseType;

        try{
            Helper::validateOptions($flags);

            $revisionID = Formatting::extractHex($revisionID, Formatting::SWH_REVISION);

            $responseRev = SyncHTTP::call("GET", 'revision', collect($revisionID), ...$flags);

            if($responseRev instanceof Throwable){
                return $responseRev;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseRev->$responseType(), "headers" => $responseRev->headers()])
                : $responseRev->$responseType();

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid Revision identifier: $revisionID",
                    404 => "Requested Revision was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $directoryID
     * @param ...$flags
     * @return iterable|Collection|Throwable
     */
    public static function getFullDirectory(string $directoryID, ...$flags): Iterable|Collection|Throwable
    {
        $responseType = HTTPClient::$responseType;

        try{
            Helper::validateOptions($flags);

            $directoryID = Formatting::extractHex($directoryID, Formatting::SWH_DIRECTORY);

            $responseDir = SyncHTTP::call("GET", 'directory', collect($directoryID), ...$flags);

            if($responseDir instanceof Throwable){
                return $responseDir;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseDir->$responseType(), "headers" => $responseDir->headers()])
                : $responseDir->$responseType();

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid Directory identifier: $directoryID",
                    404 => "Requested Directory was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $contentID
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public static function getFullContent(string $contentID, ...$flags): Iterable|Collection|stdClass|Throwable
    {
        $responseType = HTTPClient::$responseType;

        try{
            Helper::validateOptions($flags);

            $contentID = Formatting::extractHex($contentID, Formatting::SWH_CONTENT);

            $responseCnt = SyncHTTP::call("GET", 'content', collect($contentID), ...$flags);

            if($responseCnt instanceof Throwable){
                return $responseCnt;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseCnt->$responseType(), "headers" => $responseCnt->headers()])
                : $responseCnt->$responseType();

        }catch (RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    400 => "An invalid Content identifier: $contentID",
                    404 => "Requested Content was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }


    /**
     * @param string $snapshotID
     * @param ...$flags
     * @return String|bool|Throwable
     */
    public static function snapshotExists(string $snapshotID, ...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $snapshotID = Formatting::extractHex($snapshotID, Formatting::SWH_SNAPSHOT);

            $responseSnp = SyncHTTP::call("HEAD", 'snapshot', collect($snapshotID), [], ...$flags);

            if($responseSnp instanceof Throwable){
                return $responseSnp;
            }

            return $flags['stringType'] ?? false
                ? Formatting::formatSwhIDs('snapshot', $snapshotID)." --> " .self::EXISTS
                : boolval($responseSnp->status());

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid Snapshot identifier: $snapshotID",
                    404 => "Requested Snapshot was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $releaseID
     * @param ...$flags
     * @return String|bool|Throwable
     */
    public static function releaseExists(string $releaseID, ...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $releaseID = Formatting::extractHex($releaseID, Formatting::SWH_RELEASE);

            $responseRel = SyncHTTP::call("HEAD",'release', collect($releaseID),...$flags);

            if($responseRel instanceof Throwable){
                return $responseRel;
            }

            return $flags['stringType'] ?? false
                ? Formatting::formatSwhIDs('release', $releaseID)." --> ".self::EXISTS
                : boolval($responseRel->status());

        }catch(RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid Release sha1_git: $releaseID",
                    404 => "Requested Release was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $revisionID
     * @param ...$flags
     * @return String|bool|Throwable
     */
    public static function revisionExists(string $revisionID, ...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $revisionID = Formatting::extractHex($revisionID, Formatting::SWH_REVISION);

            $responseRev = SyncHTTP::call("HEAD",'revision', collect($revisionID), ...$flags);

            if($responseRev instanceof Throwable){
                return $responseRev;
            }

            return $flags['stringType'] ?? false
                ? Formatting::formatSwhIDs('revision', $revisionID)." --> ".self::EXISTS
                : boolval($responseRev->status());

        }catch(RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    400 => "An invalid Revision sha1_git: $revisionID",
                    404 => "Requested Revision was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $directoryID
     * @param ...$flags
     * @return String|bool|Throwable
     */
    public static function directoryExists(string $directoryID, ...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $directoryID = Formatting::extractHex($directoryID, Formatting::SWH_DIRECTORY);

            $responseDir = SyncHTTP::call("HEAD",'directory', collect($directoryID), ...$flags);

            if($responseDir instanceof Throwable){
                return $responseDir;
            }

            return $flags['stringType'] ?? false
                ? Formatting::formatSwhIDs('directory', $directoryID)." --> ".self::EXISTS
                : boolval($responseDir->status());

        }catch(RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    400 => "An invalid Directory sha1_git: $directoryID",
                    404 => "Requested Directory was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $contentID
     * @param ...$flags
     * @return String|bool|Throwable
     */
    public static function contentExists(string $contentID, ...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $contentID = Formatting::extractHex($contentID, Formatting::SWH_CONTENT);

            $responseCnt = SyncHTTP::call("HEAD",'content', collect($contentID),...$flags);

            if($responseCnt instanceof Throwable){
                return $responseCnt;
            }

            return $flags['stringType'] ?? false
                ? Formatting::formatSwhIDs('content', $contentID)." --> ".self::EXISTS
                : boolval($responseCnt->status());

        }catch(RequestException $e) {
            HTTPClient::addErrors($e->getCode() . " : " . match ($e->getCode()) {
                    400 => "An invalid Content sha1_git: $contentID",
                    404 => "Requested Content was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

}
