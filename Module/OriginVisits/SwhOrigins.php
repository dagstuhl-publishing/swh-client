<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\OriginVisits;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Module\DataType\SwhCoreID;
use Module\Globals\Helper;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use stdClass;
use Throwable;
use TypeError;

class SwhOrigins extends SyncHTTP
{
    public const SUPPORTED_OPTIONS = ['stringType', 'withHeaders'];

    private const EXISTS = 'Exists in SWH';

    public function __construct(public string $url, ...$options)
    {
        parent::__construct();

        $this->url = Str::of($this->url)->trim();

        self::setOptions(...$options);
    }

    /**
     * @return SwhCoreID|Throwable
     */
    public function getOriFromURL(): SwhCoreID|Throwable
    {
        try{
            $responseDataObject = $this->invokeEndpoint("GET","origin", collect($this->url));

            if($responseDataObject instanceof Throwable){
                return $responseDataObject;
            }
            $responseDataObject = $responseDataObject->json();

            return new SwhCoreID(Str::of($responseDataObject["metadata_authorities_url"])->explode("/")['7']);

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode().": " . match ($e->response->status()){
                    404 => "SW origin: $this->url can not be found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch (TypeError $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function getFullOrigin(...$flags): Iterable|Collection|stdClass|Throwable
    {
        $responseType = self::$responseType;

        try{
            Helper::validateOptions($flags);

            $responseOrigin = $this->invokeEndpoint("GET", 'origin', collect($this->url), ...$flags);

            if($responseOrigin instanceof Throwable){
                return $responseOrigin;
            }

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $responseOrigin->$responseType(), "headers" => $responseOrigin->headers()])
                : $responseOrigin->$responseType();

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode()." : " . match ($e->getCode()){
                    404 => "Requested Origin was not found in SWH for: $this->url",
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
     * @return String|bool|Throwable
     */
    public function originExists(...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $responseOri = $this->invokeEndpoint("HEAD",'origin', collect($this->url), ...$flags);

            if($responseOri instanceof Throwable){
                return $responseOri;
            }

            return $flags['stringType'] ?? false
                ? "$this->url --> ".self::EXISTS
                : boolval($responseOri->status());

        }catch (RequestException $e){
            HTTPClient::addErrors($e->getCode().": " . match ($e->response->status()){
                    404 => "SW origin: $this->url was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            HTTPClient::addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

}
