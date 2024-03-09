<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Globals;

use Ds\Queue;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Module\DataType\SwhCoreID;
use Module\HTTPConnector\HTTPClient;
use stdClass;
use Throwable;
use TypeError;

abstract class Formatting
{

    public const SWH_ORIGIN='origin';
    public const SWH_SNAPSHOT='snapshot';
    public const SWH_REVISION='revision';
    public const SWH_RELEASE='release';
    public const SWH_DIRECTORY='directory';
    public const SWH_CONTENT='content';
    public const SWH_HEADING = "swh";
    public const SWH_VERSION = 1;
    public const SEPARATOR = [
        'colon'=>':',
        'semicolon'=>';'
    ];
    public const SWH_OBJECT_TYPES = [
        self::SWH_ORIGIN    => "ori",
        self::SWH_SNAPSHOT  => "snp",
        self::SWH_REVISION  => "rev",
        self::SWH_RELEASE   => "rel",
        self::SWH_DIRECTORY => "dir",
        self::SWH_CONTENT   => "cnt",
    ];

    public const SWH_CONTEXTS = [
        "Directory-Context" => "~;origin=~;visit=~;anchor=~",
        "Revision-Context"  => "~;origin=~;visit=~",
        "Snapshot-Context" =>  "~;origin=~",
        "Content-Context" =>   "~;origin=~;visit=~;anchor=~;path=~",
    ];

    /**
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    public static function reCastTo(mixed $data, string $type): mixed
    {
        return match($type){
            HTTPClient::RESPONSE_TYPE_OBJECT   => $data instanceof Collection ? json_decode(json_encode($data->toArray())) : json_decode(json_encode($data)), // object <= (array|object)
            HTTPClient::RESPONSE_TYPE_ARRAY    => $data instanceof Collection ? json_decode(json_encode($data->toArray()), true) : json_decode(json_encode($data), true),
            HTTPClient::RESPONSE_TYPE_COLLECT  => $data instanceof Collection ? $data : collect($data),
            default => $data
        };
    }

    /**
     * @param $swhID
     * @return String
     */
    public static function reFormatSwhID($swhID) : String
    {
        return preg_replace('/\s/i',"", $swhID);
    }

    /**
     * @param string $swhID
     * @return string|null
     */
    public static function extractSwhIdInitials(string $swhID) : ?string
    {
        if(self::getSwhIdDataType($swhID) instanceof SwhCoreID){
            return Str::of($swhID)->explode(self::SEPARATOR['colon'])[2];
        }
        return Null;
    }

    /**
     * @param string $id
     * @return SwhCoreID|Throwable
     */
    public static function getSwhIdDataType(string &$id): SwhCoreID|Throwable
    {
        $id = self::reFormatSwhID($id);
        try {
            return new SwhCoreID($id);
        }catch (TypeError $e){
            HTTPClient::addLogs($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $swhid
     * @param string $type
     * @return string
     * @throws Exception
     */
    public static function extractHex(string $swhid, string $type): string
    {
        if(self::getSwhIdDataType($swhid) instanceof SwhCoreID){

            self::extractSwhIdInitials($swhid) === self::SWH_OBJECT_TYPES[$type]
                ?:throw new Exception("Will not proceed: Expected a ". $type ." SWHID");

            $swhid = Str::of($swhid)->explode(self::SEPARATOR['colon'])[3];
        }
        return $swhid;
    }

    /**
     * @param string $swhID
     * @return string
     */
    public static function extractHexEncoding(string $swhID) : string
    {
        if(self::getSwhIdDataType($swhID) instanceof SwhCoreID){
            return Str::of($swhID)->explode(self::SEPARATOR['colon'])[3];
        }
       return $swhID;
    }

    /**
     * @param string $swhObject
     * @param string $swhHexId
     * @return String|Exception
     */
    public static function formatSwhIDs(string $swhObject, string $swhHexId): String|Exception
    {
        if(!isset(self::SWH_OBJECT_TYPES[$swhObject]) || !preg_match('/^[a-fA-F0-9]{40}$/i', $swhHexId)){
            return new Exception('Cannot combine invalid format');
        }
        return Arr::join(
            [
                self::SWH_HEADING,
                self::SWH_VERSION,
                self::SWH_OBJECT_TYPES[$swhObject],
                Str::lower($swhHexId)
            ],
            self::SEPARATOR['colon']);
    }

    /**
     * @param stdClass $swhIDs
     * @param string $url
     * @param Queue|null $path
     * @return null[]|string[]
     */
    public static function getContexts(stdClass $swhIDs, string $url, ?Queue $path = null): array
    {
        if(isset($swhIDs->cnt)){
            return ['Content-Context'=> self::formatContext($swhIDs, $url, 'Content-Context', implode('/', $path->toArray()))];
        }
        $contexts = array_keys(self::SWH_CONTEXTS);
        array_pop($contexts);


        $replaceArrays = [
            [ $swhIDs->dir, $url, $swhIDs->snp, $swhIDs->rev ?? $swhIDs->rel ],
            [ $swhIDs->rev ?? $swhIDs->rel, $url, $swhIDs->snp ],
            [ $swhIDs->snp, $url ],
        ];

        $replaceArrays = array_combine($contexts, $replaceArrays);

        return Arr::map($replaceArrays, function ($replaceArray, $key) use ($replaceArrays){
            return Str::replaceArray('~', $replaceArray , self::SWH_CONTEXTS[$key]);
        });
    }

    /**
     * @param stdClass $swhIDs
     * @param string $url
     * @param string $context
     * @param string|null $path
     * @return string|null
     */
    public static function formatContext(stdClass $swhIDs, string $url, string $context, string $path = null): ?string
    {
        $replaceArrays = match(strtolower($context)){
            "directory-context" => [ $swhIDs->dir, $url, $swhIDs->snp, $swhIDs->rev ?? $swhIDs->rel ],
            "revision-context"  => [ $swhIDs->rev ?? $swhIDs->rel, $url, $swhIDs->snp ],
            "snapshot-context"  => [ $swhIDs->snp, $url ],
            "content-context"  =>  [ $swhIDs->cnt, $url, $swhIDs->snp, $swhIDs->rev ?? $swhIDs->rel, $path ],
            default => NULL
        };
        if($replaceArrays === Null){
            return Null;
        }

        return Str::replaceArray('~', $replaceArrays , self::SWH_CONTEXTS[$context]);
    }

}
