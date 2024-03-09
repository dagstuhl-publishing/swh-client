<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Logging;

use DateTime;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Psr\Log\LoggerInterface;
use Monolog\Logger as MonologLogger;

trait Logger
{
    public static bool $echoFlag = false;

    public static bool $logFileDatestamp = false;

    protected static ?LoggerInterface $logger = null;

    public static array $errorMessages;

    protected static function openLog(): void
    {
        $monolog = new MonologLogger('Logger');

        $fileName = self::$logFileDatestamp
            ? 'RequestsLogs/'.(new DateTime())->format('Y-m-d').'-swhAPI.log'
            : 'RequestsLogs/swhAPI.log';

        $lineFormatter = new LineFormatter("[%datetime%] %channel%.%level_name%: %message%\n", 'Y-m-d H:i:s');

        $handler = new StreamHandler($fileName, Level::Debug);
        $handler->setFormatter($lineFormatter);

        $monolog->pushHandler($handler);

        self::$logger = new IlluminateLogger($monolog);
    }

    public static function setLogOptions(...$options):void
    {
        if(isset($options['isVerbose']) && is_bool($options['isVerbose'])){
            self::$echoFlag = $options['isVerbose'];
        }

        if(isset($options['fileDatestamp']) && is_bool($options['fileDatestamp'])){
            self::$logFileDatestamp =  $options['fileDatestamp'];
            self::openLog();
        }
    }

    public static function addErrors(string $errorLog): void
    {
        if(!isset(self::$logger)) self::openLog();

        self::$logger->error($errorLog);
        self::$errorMessages[] = (new DateTime())->format('H:i:s') ." --> ".$errorLog;

        if(self::$echoFlag){
            echo "\n".$errorLog."\n";
        }
    }

    public static function addLogs(string $infoLog): void
    {
        if(!isset(self::$logger)) self::openLog();

        self::$logger->info($infoLog);

        if(self::$echoFlag){
            echo "\n".$infoLog."\n";
        }
    }

    public static function getErrors(bool $stringFormat = false): array|string
    {
        $errorMessages = self::$errorMessages;
        self::$errorMessages = [];
        return $stringFormat
            ? implode("\n", $errorMessages)
            : $errorMessages;
    }

}
