<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\HTTPConnector;

use Module\Globals\HTTP;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncHTTP extends HTTPClient
{

    public function __construct()
    {
        parent::__construct();

        $this->HTTPRequest = $this->HTTPRequest->withOptions([
            'synchronous' => true
            ]);
    }

    /**
     * @throws RequestException
     */
    public static function call(string $method, string $on, Collection $append2Url, ...$options): Response|iterable|Throwable
    {
        $newHTTP = new self();
        return $newHTTP->invokeEndpoint($method, $on, $append2Url, ...$options);
    }

    /**
     * @param string $method
     * @param string $on
     * @param Collection $append2Url
     * @param ...$options
     * @return Response|iterable|Throwable
     * @throws RequestException
     */
    public function invokeEndpoint(string $method, string $on, Collection $append2Url, ...$options): Response|iterable|Throwable
    {
        $method = Str::lower($method);

        try{
            self::addLogs("Logging started for ".$on);

            parent::prepareForInvoke($method, $on,$append2Url);

            $uri = is_null($append2Url)
                ? $on
                : Str::replaceArray('~', $append2Url->toArray(), self::$apiURL . self::API_ENDPOINTS[$on]['route']);

            self::addLogs("Invoking '$method' on --> ". $uri);

            $response = $this->HTTPRequest->withOptions(['delay' => $options["delay"] ?? 0, 'debug' => $options["debug"] ?? false])->$method($uri);

            return match (true){
                $response->redirect() => new Exception('Exceeded redirects preset.', '7'),
                $response->successful() && $response->status() === 200 => $response
            };

        }catch(RequestException $e){

            return $this->handleRequestException($e);
        }
        catch(GuzzleRequestException | ConnectionException | ValidationException $e){

            $this->addErrors((match (substr(get_class($e), strrpos(get_class($e), "\\" ) )  ){
                    "\GuzzleRequestException" => "GuzzleHTTP Request Exception occurred --> ",
                    "\ConnectionException" => "Connection Error --> ",
                    "\ValidationException" => "Validation Exception. PrepareForInvoke() Error --> "
                })
                .$e->getCode().": ".$e->getMessage()
            );
            return $e;
        }
        catch(Exception $e){
            $this->addErrors(match(true) {
                $e->getCode() === 980   => "PrepareForInvoke() Error --> " . $e->getMessage(),
                in_array($e->getCode(), self::$serverErrorCodes) => "Server Error --> " . $e->getMessage().": ".$e->getCode(),
                default => "Some other Error occurred: --> " . $e->getMessage().": ".$e->getCode(),
            });
            return $e;
        }
    }

    /**
     * @throws RequestException
     */
    private function handleRequestException(RequestException $e): iterable
    {
        $ErrorResponse = $e->response->json();

        $ErrorMessage = "Non-Successful HTTP Status Code: ".$e->response->status()." --> Reason: ";

        $this->addErrors($ErrorMessage . match (true) {
                $e->response->status() === 403 => "Forbidden Status code. No granted access or invalid token",
                default => $ErrorResponse['reason'] ?? $ErrorResponse
            });

        $contentPattern = substr(self::API_ENDPOINTS['content']['route'], 0, strpos(self::API_ENDPOINTS['content']['route'], ":") + 1 );
        $multipleContentEndpoint = preg_match("#".$contentPattern."([a-fA-F0-9]{40}|[a-fA-F0-9]{64})/[a-z]+#i", $e->response->effectiveUri()->getPath());

        $e->response->throwIf( !$multipleContentEndpoint );

        return $e->response->json();
    }

    /**
     * @param string $method
     * @param string $uri
     * @param ...$options
     * @return Response
     * @throws ClientException|ServerException
     */
    public static function request(string $method, string $uri, ...$options): Response
    {
        $method = Str::lower($method);

        self::addLogs("Invoking '$method' on --> ". $uri);

        return HTTP::withOptions(
            [
                'delay' => $options["delay"] ?? 0,
                'debug' => $options["debug"] ?? false,
                'decode_content' => 'gzip',
                'version' => '1.1',
                'http_errors' => true,
                'verify' => true,
                'force_ip_resolve' => 'v4',
                'allow_redirects' => ['max' => 2, 'protocols' => ['https'], 'track_redirects' => true],
            ])
            ->timeout($options["timeout"] ?? 3)
            ->connectTimeout($options["connectTimeout"] ?? 3)
            ->retry($options["retry"] ?? 3, $options["sleepMS"] ?? 500)
            ->$method($uri);
    }

    /**
     * @param string $method
     * @param string $URI
     * @return bool|Throwable
     */
    public function isHttpMethodAllowed(string $method, string $URI): bool|Throwable
    {
        try{
            self::addLogs("Checking HTTP Method: " . $method . " on --> " . $URI);

            $response = $this->HTTPRequest->head($URI);

            $allowHeader = $response->header('Allow');

            if (!Str::contains($allowHeader, Str::upper($method))) {
                throw new Exception("HTTP method '$method' is not allowed on this endpoint", 405);
            }

            self::addLogs("Method '$method' is allowed on this route");
            return true;

        }catch(RequestException $e){
            $this->addErrors("HEAD Test didn't pass on this given route: ".$e->response->status());
            return $e;
        }catch(Exception $e){
            $this->addErrors($e->getCode() . " : " .$e->getMessage());
            return $e;
        }
    }

}





