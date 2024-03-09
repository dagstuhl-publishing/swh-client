<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\MetaData;

use Module\OriginVisits\SwhOrigins;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Module\DAGModel\GraphHopping;
use Module\Globals\Formatting;
use Module\Globals\Helper;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use stdClass;
use Throwable;

abstract class SwhMetaData
{
    private const REVISION_METADATA = ["message", "author", "committer", "committer_date", "type", "metadata"];

    private const RELEASE_METADATA = ["message", "author", "date"];

    public const SUPPORTED_OPTIONS = ["withHeaders"];


    /**
     * @param string $contentID
     * @param ...$flags
     * @return Collection|Throwable
     */
    public static function getFullContentWithMetaData(string $contentID, ...$flags): Collection|Throwable
    {
        $responseType = HTTPClient::$responseType;
        try{
            Helper::validateOptions($flags);

            $contentID = Formatting::extractHex($contentID, Formatting::SWH_CONTENT);

            $responseCnt = SyncHTTP::call("GET", 'content', collect($contentID), ...$flags);

            if($responseCnt instanceof Throwable){
                return $responseCnt;
            }

            $fileType = SyncHTTP::call("GET", 'content', collect($contentID."/filetype"));
            $fileType = $fileType['reason'] ?? $fileType->$responseType();

            $language = SyncHTTP::call("GET", 'content', collect($contentID."/language"));
            $language = $language['reason'] ?? $language->$responseType();

            $license = SyncHTTP::call("GET", 'content', collect($contentID."/license"));
            $license = $license['reason'] ?? $license->$responseType();

            $responseCntFiltered = $responseCnt->collect()->forget(["filetype_url", "language_url", "license_url"]);

            $responseCntFiltered = Formatting::reCastTo($responseCntFiltered, $responseType);

            $fullContentResponse = collect(["response" => $responseCntFiltered, "fileType" => $fileType, "language" => $language, "license" => $license]);

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $fullContentResponse, "headers" => $responseCnt->headers()])
                : $fullContentResponse;

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
     * @param string $revisionID
     * @param ...$flags
     * @return iterable|Collection|Throwable
     */
    public static function getRevisionMetaData(string $revisionID, ...$flags) : iterable|Collection|stdClass|Throwable
    {
        $fullRevision = GraphHopping::getFullRevision($revisionID, ...$flags);

        if($fullRevision instanceof Throwable){
            return $fullRevision;
        }
        $fullRevision = Formatting::reCastTo($fullRevision, HTTPClient::RESPONSE_TYPE_ARRAY);

        $revMetaData = Arr::only($fullRevision, self::REVISION_METADATA);

        return Formatting::reCastTo($revMetaData, HTTPClient::$responseType);
    }

    /**
     * @param string $releaseID
     * @param ...$flags
     * @return iterable|Collection|Throwable
     */
    public static function getReleaseMetaData(string $releaseID, ...$flags) : iterable|Collection|stdClass|Throwable
    {
        $fullRelease = GraphHopping::getFullRelease($releaseID, ...$flags);

        if($fullRelease instanceof Throwable){
            return $fullRelease;
        }
        $fullRelease = Formatting::reCastTo($fullRelease, HTTPClient::RESPONSE_TYPE_ARRAY);

        $relMetaData = Arr::only($fullRelease, self::RELEASE_METADATA);

        return Formatting::reCastTo($relMetaData, HTTPClient::$responseType);
    }

    /**
     * @param string $url
     * @return array|Throwable
     */
    public static function getOriginMetaData(string $url): array|Throwable
    {
        $origin = new SwhOrigins($url);

        $fullOriginData = $origin->getFullOrigin();

        if($fullOriginData instanceof Throwable){
            return $fullOriginData;
        }

        $fullOriginData = Formatting::reCastTo($fullOriginData, HTTPClient::RESPONSE_TYPE_ARRAY);

        $sync = new SyncHTTP();
        $forgeList = $sync->request('GET', $fullOriginData['metadata_authorities_url'])->json();

        $metadataListUrls = Arr::pluck($forgeList, 'metadata_list_url');

        $swhMeta = [];
        foreach ($metadataListUrls as $key => $url){
            $swhMeta["metadata_list_url_".$key+1] = $sync->request('GET', $url)->json();

            foreach ($swhMeta["metadata_list_url_".$key+1] as $idx => $arr){
                $swhMeta["metadata_list_url_".$key+1][$idx]['metadata_url'] = $sync->request('GET', $arr['metadata_url'])->json();
            }
        }
        return $swhMeta;
    }
}
